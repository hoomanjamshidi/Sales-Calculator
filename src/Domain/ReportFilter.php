<?php
/**
 * قرارداد ورودی گزارش؛ تاریخ‌های بدون ساعت در منطقهٔ زمانی فروشگاه معنا دارند.
 *
 * @package SalesCalculator
 */

declare(strict_types=1);
namespace SalesCalculator\Domain;

/**
 * ورودی بازه، ارز و شناسهٔ محصولات را به قرارداد معتبر گزارش تبدیل می‌کند.
 */
final class ReportFilter {
	/**
	 * امروز به میلادی در منطقهٔ زمانی فروشگاه.
	 *
	 * افزونه‌های شمسی‌ساز (پارسی‌دیت، WP-Jalali و…) خروجی wp_date را شمسی می‌کنند؛ «۱۴۰۵-۰۷-۳۰» در رابط
	 * سال میلادی ۱۴۰۵ (شمسی ۷۸۴) خوانده می‌شد. DateTimeImmutable از این فیلترها اثر نمی‌گیرد.
	 */
	public static function today(): string {
		return ( new \DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y-m-d' );
	}
	/** اعتبارسنجی سخت‌گیرانه پیش از اجرای هر پرس‌وجو. */
	public static function validate( array $input ): array {
		foreach ( array( 'from', 'to' ) as $key ) {
			$value = (string) ( $input[ $key ] ?? '' );
			$date  = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
			if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
				throw new \InvalidArgumentException( 'تاریخ شروع و پایان معتبر وارد کنید.' ); }
		}
		if ( $input['from'] > $input['to'] ) {
			throw new \InvalidArgumentException( 'تاریخ پایان باید بعد از شروع باشد.' ); }
		$currency = strtoupper( (string) ( $input['currency'] ?? '' ) );
		if ( ! preg_match( '/^[A-Z0-9_]{2,12}$/D', $currency ) ) {
			throw new \InvalidArgumentException( 'واحد پول معتبر انتخاب کنید.' ); }
		$products = array_values( array_unique( array_map( 'intval', (array) ( $input['products'] ?? array() ) ) ) );
		if ( count( $products ) > 200 || array_filter( $products, static fn( int $id ): bool=>$id <= 0 ) ) {
			throw new \InvalidArgumentException( 'فیلتر محصول معتبر نیست.' ); }
		// خالی یعنی «همهٔ دسته‌ها».
		$categories = array_values( array_unique( array_map( 'intval', (array) ( $input['categories'] ?? array() ) ) ) );
		if ( count( $categories ) > 100 || array_filter( $categories, static fn( int $id ): bool=>$id <= 0 ) ) {
			throw new \InvalidArgumentException( 'فیلتر دسته‌بندی معتبر نیست.' ); }
		return array(
			'from'       => $input['from'],
			'categories' => $categories,
			'to'         => $input['to'],
			'currency'   => $currency,
			'products'   => $products,
			'calendar'   => ( $input['calendar'] ?? '' ) === 'gregorian' ? 'gregorian' : 'jalali',
		);
	}
}
