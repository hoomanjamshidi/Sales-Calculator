<?php
/**
 * واحد نمایش گزارش؛ ارزهای ایرانی همیشه به تومان محاسبه و نمایش داده می‌شوند.
 *
 * @package SalesCalculator
 */

declare(strict_types=1);
namespace SalesCalculator\Domain;

/**
 * ضریب تبدیل کد ارز ووکامرس به تومان و برچسب فارسی واحد گزارش.
 */
final class Currency {
	/** ضریب تبدیل هر واحد ایرانی به تومان؛ فهرست ثابت است و مستقیم در SQL به کار می‌رود. */
	private const TOMAN = array(
		'IRR'  => '0.1',
		'IRT'  => '1',
		'IRHR' => '100',
		'IRHT' => '1000',
	);

	/** ضریب تبدیل به تومان، یا null برای ارزی که تبدیل نمی‌شود. */
	public static function factor( string $code ): ?string {
		$factor = self::TOMAN[ strtoupper( $code ) ] ?? null;
		/**
		 * غیرفعال‌کردن تبدیل تومانی برای فروشگاهی که گزارش را به واحد اصلی سفارش می‌خواهد.
		 *
		 * @param bool $enabled فعال بودن تبدیل.
		 */
		return $factor !== null && apply_filters( 'sales_calculator_toman', true ) ? $factor : null;
	}

	/** برچسب واحد مبالغ گزارش. */
	public static function label( string $code ): string {
		return self::factor( $code ) !== null ? 'تومان' : strtoupper( $code );
	}
}
