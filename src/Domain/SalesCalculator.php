<?php
/**
 * تبدیل سفارش به اقلام گزارش با محاسبات اعشاری دقیق و حفظ سابقهٔ محصول.
 *
 * @package SalesCalculator
 */

declare(strict_types=1);
namespace SalesCalculator\Domain;

/**
 * سفارش ووکامرس را به سطرهای فروش و مرجوعی قابل نمایه‌سازی تبدیل می‌کند.
 */
final class SalesCalculator {
	/** دقت ثابت مانع خطای ممیز شناور در جمع مبالغ و تعدادهای کسری می‌شود. */
	public const SCALE = 8;
	/**
	 * پایهٔ شناسهٔ مصنوعی برای قلمی که شناسهٔ محصول ندارد.
	 *
	 * شناسهٔ قلم سفارش با شناسهٔ محصول هم‌دامنه نیست؛ بدون این فاصله، قلم بدون محصول
	 * با شمارهٔ ۸۱ با محصول واقعی ۸۱ در یک ردیف جمع می‌شد. مقدار زیر ۲^۵۳ می‌ماند تا
	 * در جاوااسکریپت دقیق بماند.
	 */
	public const DETACHED_PRODUCT_BASE = 8000000000000000;
	/** استخراج فقط فروش پرداخت‌شده؛ مرجوعی مربوط به تاریخ پرداخت اصلی است. */
	public function extract( \WC_Order $order ): array {
		if ( ! $order->get_date_paid() || ! in_array( $order->get_status(), self::countedStatuses(), true ) ) {
			return array(); }
		$date        = ( new \DateTimeImmutable( '@' . $order->get_date_paid()->getTimestamp() ) )->setTimezone( wp_timezone() );
		$base        = array(
			'order_id' => $order->get_id(),
			'day'      => $date->format( 'Y-m-d' ),
			'currency' => $order->get_currency(),
		);
		$refunds     = array();
		$unallocated = '0';
		foreach ( $order->get_refunds() as $refund ) {
			$attributed = '0';
			foreach ( $refund->get_items( array( 'line_item', 'shipping', 'fee' ) ) as $item ) {
				// فقط انواع قلمی که مبلغ دارند در تفکیک مرجوعی شرکت می‌کنند.
				if ( ! $item instanceof \WC_Order_Item_Product && ! $item instanceof \WC_Order_Item_Shipping && ! $item instanceof \WC_Order_Item_Fee ) {
					continue;
				}
				// مالیات در total اقلام نیست؛ برای باقیماندهٔ کل مرجوعی جدا حساب می‌شود.
				$attributed = bcadd( $attributed, self::decimal( $item->get_total(), true ), self::SCALE );
				$attributed = bcadd( $attributed, self::decimal( $item->get_total_tax(), true ), self::SCALE );
				if ( ! ( $item instanceof \WC_Order_Item_Product ) ) {
					continue; }
				$id = (int) $item->get_meta( '_refunded_item_id', true );
				if ( ! $id || ! $order->get_item( $id ) ) {
					$unallocated = bcadd( $unallocated, self::decimal( $item->get_total(), true ), self::SCALE );
					continue;
				}
				$refunds[ $id ]           ??= array(
					'quantity' => '0',
					'amount'   => '0',
				);
				$refunds[ $id ]['quantity'] = bcadd( $refunds[ $id ]['quantity'], self::decimal( $item->get_quantity(), true ), self::SCALE );
				$refunds[ $id ]['amount']   = bcadd( $refunds[ $id ]['amount'], self::decimal( $item->get_total(), true ), self::SCALE );
			}
			$remaining = bcsub( self::decimal( $refund->get_amount() ), $attributed, self::SCALE );
			if ( bccomp( $remaining, '0', self::SCALE ) > 0 ) {
				$unallocated = bcadd( $unallocated, $remaining, self::SCALE ); }
		}
		$rows = array();
		foreach ( $order->get_items( 'line_item' ) as $id => $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$attributes = array();
			foreach ( $item->get_formatted_meta_data( '_', true ) as $meta ) {
				$attributes[] = wp_strip_all_tags( (string) $meta->display_key ) . ': ' . wp_strip_all_tags( (string) $meta->display_value );
			}
			// نام والد در نمایه ثبت می‌شود تا نام متغیر جای نام محصول اصلی ننشیند.
			// ووکامرس پس از حذف کامل محصول get_product_id() را صفر برمی‌گرداند، اما شناسه در متای قلم می‌ماند.
			// get_meta کلیدهای داخلی ووکامرس را برنمی‌گرداند؛ wc_get_order_item_meta مستقیم از جدول متا می‌خواند.
			$product_id   = $item->get_product_id() > 0 ? $item->get_product_id() : (int) wc_get_order_item_meta( (int) $id, '_product_id', true );
			$variation_id = $item->get_variation_id() > 0 ? $item->get_variation_id() : (int) wc_get_order_item_meta( (int) $id, '_variation_id', true );
			$parent       = $product_id > 0 ? wc_get_product( $product_id ) : null;
			$parent_title = $parent ? $parent->get_name() : $item->get_name();
			// شناسهٔ اصلی حتی پس از حذف محصول در سفارش باقی می‌ماند.
			$rows[] = $base + array(
				'item_id'      => (int) $id,
				'product_id'   => $product_id > 0 ? $product_id : self::DETACHED_PRODUCT_BASE + (int) $id,
				'variation_id' => $variation_id,
				'title'        => $item->get_name(),
				'parent_title' => $parent_title,
				'attributes'   => implode( '، ', $attributes ),
				'sold'         => self::decimal( $item->get_quantity() ),
				'returned'     => $refunds[ $id ]['quantity'] ?? '0',
				'amount'       => bcsub( self::decimal( $item->get_total() ), $refunds[ $id ]['amount'] ?? '0', self::SCALE ),
			);
		}
		if ( bccomp( $unallocated, '0', self::SCALE ) > 0 ) {
			$rows[] = $base + array(
				'item_id'      => 0,
				'product_id'   => 0,
				'variation_id' => 0,
				'title'        => 'مرجوعی تخصیص‌نیافته',
				'parent_title' => 'مرجوعی تخصیص‌نیافته',
				'attributes'   => '',
				'sold'         => '0',
				'returned'     => '0',
				'amount'       => bcsub( '0', $unallocated, self::SCALE ),
			);
		}
		return $rows;
	}
	/**
	 * وضعیت‌های پرداخت‌شدهٔ ووکامرس (قابل گسترش با فیلتر woocommerce_order_is_paid_statuses
	 * برای وضعیت‌های سفارشی مانند «ارسال‌شده») به‌همراه سفارش‌های مسترد‌شده.
	 */
	private static function countedStatuses(): array {
		$paid = function_exists( 'wc_get_is_paid_statuses' ) ? (array) wc_get_is_paid_statuses() : array( 'processing', 'completed' );
		return (array) apply_filters( 'sales_calculator_counted_statuses', array_values( array_unique( array_merge( $paid, array( 'refunded' ) ) ) ) );
	}
	/** مقدار ووکامرس بدون نماد علمی به رشتهٔ اعشاری معتبر bcmath تبدیل می‌شود. */
	private static function decimal( mixed $value, bool $absolute = false ): string {
		$text = is_string( $value ) || is_int( $value ) ? trim( (string) $value ) : '';
		if ( ! preg_match( '/^-?\d+(\.\d+)?$/D', $text ) ) {
			$text = sprintf( '%.' . self::SCALE . 'F', (float) $value ); }
		return $absolute ? ltrim( $text, '-' ) : $text;
	}
}
