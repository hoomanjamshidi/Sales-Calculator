<?php
/** سناریوهای واقعی مرجوعی و مرز روز بر خروجی اقلام آزموده می‌شوند. */
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use SalesCalculator\Domain\SalesCalculator;
use SalesCalculator\Domain\ReportFilter;
final class SalesCalculatorTest extends TestCase {
    /** تخفیف در مبلغ قلم ووکامرس اعمال شده و زمان پرداخت به روز فروشگاه تبدیل می‌شود. */
    public function test_sale_uses_paid_day_and_discounted_line_amount():void {
        $item=new WC_Order_Item_Product(['total'=>'180.50','qty'=>'2','product_id'=>17,'name'=>'پیراهن']);
        $order=new WC_Order(['paid'=>new DateTimeImmutable('2026-03-21 21:00:00 UTC'),'items'=>[81=>$item]]);
        $rows=(new SalesCalculator())->extract($order);
        self::assertSame('2026-03-22',$rows[0]['day']);
        self::assertSame('180.50000000',$rows[0]['amount']);
        self::assertSame('2',$rows[0]['sold']);
    }
    /** مرجوعی دارای شناسهٔ قلم، هم تعداد و هم مبلغ همان محصول را اصلاح می‌کند. */
    public function test_partial_refund_is_attributed_to_original_item():void {
        $item=new WC_Order_Item_Product(['total'=>'300','qty'=>'3','product_id'=>17,'variation_id'=>27,'name'=>'پیراهن آبی']);
        $refunded=new WC_Order_Item_Product(['total'=>'-100','qty'=>'-1','_refunded_item_id'=>81]);
        $refund=new FakeRefund([25=>$refunded],'100');
        $rows=(new SalesCalculator())->extract(new WC_Order(['paid'=>new DateTimeImmutable('2026-02-01 UTC'),'items'=>[81=>$item],'refunds'=>[$refund]]));
        self::assertCount(1,$rows);
        self::assertSame('1.00000000',$rows[0]['returned']);
        self::assertSame('200.00000000',$rows[0]['amount']);
        self::assertSame(27,$rows[0]['variation_id']);
        self::assertSame('پیراهن',$rows[0]['parent_title']);
    }
    /** مرجوعی بدون قلم محصول وارد جمع فروش محصول نمی‌شود. */
    public function test_unallocated_refund_has_separate_row():void {
        $item=new WC_Order_Item_Product(['total'=>'100','qty'=>'1','product_id'=>7]);
        $refund=new FakeRefund([], '20');
        $rows=(new SalesCalculator())->extract(new WC_Order(['paid'=>new DateTimeImmutable('2026-02-01 UTC'),'items'=>[9=>$item],'refunds'=>[$refund]]));
        self::assertCount(2,$rows);
        self::assertSame('100.00000000',$rows[0]['amount']);
        self::assertSame(0,$rows[1]['product_id']);
        self::assertSame('-20.00000000',$rows[1]['amount']);
    }
    /** مرجوعی صرفاً مبلغی نباید شمار کالاهای برگشتی را زیاد کند. */
    public function test_amount_only_refund_keeps_quantity():void {
        $item=new WC_Order_Item_Product(['total'=>'100','qty'=>'1','product_id'=>17]);
        $refunded=new WC_Order_Item_Product(['total'=>'-20','qty'=>'0','_refunded_item_id'=>81]);
        $refund=new FakeRefund([90=>$refunded],'20');
        $rows=(new SalesCalculator())->extract(new WC_Order(['paid'=>new DateTimeImmutable('2026-02-01 UTC'),'items'=>[81=>$item],'refunds'=>[$refund]]));
        self::assertSame('0.00000000',$rows[0]['returned']);
        self::assertSame('80.00000000',$rows[0]['amount']);
    }
    /** عنوان قلم سفارش برای محصول حذف‌شده همچنان در گزارش باقی می‌ماند. */
    public function test_deleted_product_keeps_historical_title():void {
        $item=new WC_Order_Item_Product(['total'=>'250','qty'=>'1','product_id'=>999,'name'=>'محصول قدیمی']);
        $rows=(new SalesCalculator())->extract(new WC_Order(['paid'=>new DateTimeImmutable('2026-02-01 UTC'),'items'=>[81=>$item]]));
        self::assertSame('محصول قدیمی',$rows[0]['parent_title']);
        self::assertSame(999,$rows[0]['product_id']);
    }
    /** سفارش پرداخت‌نشده یا لغوشده سهمی در فروش ندارد. */
    public function test_unpaid_or_cancelled_orders_are_ignored():void {
        self::assertSame([],(new SalesCalculator())->extract(new WC_Order(['items'=>[1=>new WC_Order_Item_Product(['total'=>'10'])]])));
        self::assertSame([],(new SalesCalculator())->extract(new WC_Order(['paid'=>new DateTimeImmutable('now'),'status'=>'cancelled'])));
    }
    /** مبلغ بسیار کوچک ممیز شناور با نماد علمی نباید محاسبهٔ bcmath را متوقف کند. */
    public function test_scientific_notation_amount_is_accepted():void {
        $item=new WC_Order_Item_Product(['total'=>'100','qty'=>'1','product_id'=>17]);
        $refunded=new WC_Order_Item_Product(['total'=>-1.0E-5,'tax'=>1.0E-5,'qty'=>'0','_refunded_item_id'=>81]);
        $rows=(new SalesCalculator())->extract(new WC_Order(['paid'=>new DateTimeImmutable('2026-02-01 UTC'),'items'=>[81=>$item],'refunds'=>[new FakeRefund([90=>$refunded],'0.00002')]]));
        self::assertSame('99.99999000',$rows[0]['amount']);
    }
    /** قلم بدون محصول نباید با محصولی که همان شماره را دارد یکی شود. */
    public function test_line_without_product_gets_detached_id():void {
        $item=new WC_Order_Item_Product(['total'=>'50','qty'=>'1','product_id'=>0,'name'=>'قلم دستی']);
        $rows=(new SalesCalculator())->extract(new WC_Order(['paid'=>new DateTimeImmutable('2026-02-01 UTC'),'items'=>[81=>$item]]));
        self::assertSame(SalesCalculator::DETACHED_PRODUCT_BASE+81,$rows[0]['product_id']);
        self::assertLessThan(2**53,$rows[0]['product_id']);
    }
    /** دسته‌بندی‌ها اختیاری‌اند؛ خالی یعنی همه و شناسهٔ نامعتبر رد می‌شود. */
    public function test_filter_accepts_categories():void {
        $f=ReportFilter::validate(['from'=>'2026-02-01','to'=>'2026-02-02','currency'=>'IRT','categories'=>['5','5',7]]);
        self::assertSame([5,7],$f['categories']);
        self::assertSame([],ReportFilter::validate(['from'=>'2026-02-01','to'=>'2026-02-02','currency'=>'IRT'])['categories']);
        $this->expectException(InvalidArgumentException::class);
        ReportFilter::validate(['from'=>'2026-02-01','to'=>'2026-02-02','currency'=>'IRT','categories'=>[-1]]);
    }
    /** محصول کاملاً حذف‌شده شناسهٔ واقعی‌اش را از متای قلم نگه می‌دارد، نه شناسهٔ قلم دستی. */
    public function test_force_deleted_product_keeps_real_id_from_meta():void {
        $GLOBALS['sc_item_meta'][8]=['_product_id'=>'20','_variation_id'=>'0'];
        $item=new WC_Order_Item_Product(['total'=>'160000','qty'=>'2','product_id'=>0,'name'=>'محصول قدیمی']);
        $rows=(new SalesCalculator())->extract(new WC_Order(['paid'=>new DateTimeImmutable('2026-02-01 UTC'),'items'=>[8=>$item]]));
        self::assertSame(20,$rows[0]['product_id']);
        self::assertSame('محصول قدیمی',$rows[0]['parent_title']);
    }
    /** ورودی بازه و ارز پیش از هر پرس‌وجو محدود می‌شود. */
    public function test_filter_rejects_reversed_dates():void {
        $this->expectException(InvalidArgumentException::class);
        ReportFilter::validate(['from'=>'2026-02-02','to'=>'2026-02-01','currency'=>'IRT']);
    }
}
