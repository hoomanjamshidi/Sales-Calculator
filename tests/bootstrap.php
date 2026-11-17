<?php
/** جانشین‌های محدود مدل ووکامرس برای آزمون محاسبه بدون تغییر سایت واقعی. */
declare(strict_types=1);
require_once dirname(__DIR__).'/vendor/autoload.php';
function wp_timezone(): DateTimeZone {return new DateTimeZone('Asia/Tehran');}
function wp_strip_all_tags(string $value): string {return strip_tags($value);}
function apply_filters(string $hook, mixed $value): mixed {return $value;}
function get_bloginfo(string $show=''): string {return 'فروشگاه <آزمون> & شرکا';}
function wp_mkdir_p(string $dir): bool {return is_dir($dir)||mkdir($dir,0777,true);}
/** متای خام قلم سفارش؛ آزمون‌ها آن را در $GLOBALS['sc_item_meta'] می‌گذارند. */
function wc_get_order_item_meta(int $id,string $key,bool $single=true): mixed {return $GLOBALS['sc_item_meta'][$id][$key]??'';}
function wc_get_product(int $id):?object {return $id===17?(new class {public function get_name():string {return 'پیراهن';}}):null;}
class WC_Order_Item_Product {
    public function __construct(private array $data) {}
    public function get_total():string {return (string)($this->data['total']??'0');}
    public function get_total_tax():string {return (string)($this->data['tax']??'0');}
    public function get_quantity():string {return (string)($this->data['qty']??'0');}
    public function get_meta(string $key,bool $single=true):mixed {return $this->data[$key]??null;}
    public function get_formatted_meta_data(string $hide='',bool $include=true):array {return array_map(static fn($k,$v)=>(object)['display_key'=>$k,'display_value'=>$v],array_keys($this->data['meta']??[]),array_values($this->data['meta']??[]));}
    public function get_product_id():int {return $this->data['product_id']??0;}
    public function get_variation_id():int {return $this->data['variation_id']??0;}
    public function get_name():string {return $this->data['name']??'محصول';}
}
class WC_Order {
    public function __construct(private array $data) {}
    public function get_date_paid():?DateTimeImmutable {return $this->data['paid']??null;}
    public function get_status():string {return $this->data['status']??'processing';}
    public function get_id():int {return $this->data['id']??1;}
    public function get_currency():string {return $this->data['currency']??'IRT';}
    public function get_refunds():array {return $this->data['refunds']??[];}
    public function get_items(string $type='line_item'):array {return $this->data['items']??[];}
    public function get_item(int $id):?WC_Order_Item_Product {return $this->data['items'][$id]??null;}
}
class FakeRefund {
    public function __construct(private array $items,private string $amount) {}
    public function get_items(array $types=[]):array {return $this->items;}
    public function get_amount():string {return $this->amount;}
}
