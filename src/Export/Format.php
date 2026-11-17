<?php
/**
 * قالب‌بندی مشترک تاریخ و عدد در فایل‌های فارسی.
 *
 * @package SalesCalculator
 */

declare(strict_types=1);
namespace SalesCalculator\Export;

/**
 * اعداد، تاریخ‌ها و متن امن را برای فایل‌های خروجی آماده می‌کند.
 */
final class Format {
	/** ارقام فارسی صرفاً برای نمایش استفاده می‌شوند، نه ذخیرهٔ عدد در اکسل. */
	public static function number( string|int|float $value ): string {
		return strtr(
			number_format( (float) $value, abs( (float) $value - round( (float) $value ) ) > 0.00000001 ? 2 : 0 ),
			array(
				'0' => '۰',
				'1' => '۱',
				'2' => '۲',
				'3' => '۳',
				'4' => '۴',
				'5' => '۵',
				'6' => '۶',
				'7' => '۷',
				'8' => '۸',
				'9' => '۹',
			)
		);
	}
	/** تبدیل نمایشی تاریخ بدون وابستگی به منطقهٔ زمانی سرور. */
	public static function date( string $value, string $calendar ): string {
		$formatter = new \IntlDateFormatter( $calendar === 'jalali' ? 'fa_IR@calendar=persian' : 'fa_IR@calendar=gregorian', \IntlDateFormatter::SHORT, \IntlDateFormatter::NONE, 'UTC', \IntlDateFormatter::TRADITIONAL, 'yyyy/MM/dd' );
		$formatted = $formatter->format( new \DateTimeImmutable( $value, new \DateTimeZone( 'UTC' ) ) );
		return $formatted === false ? $value : $formatted;
	}
	/**
	 * زمان ساخت نسخهٔ داده‌ها در منطقهٔ زمانی فروشگاه.
	 *
	 * رشتهٔ ISO خام در متن راست‌به‌چپ به‌هم می‌ریخت (مثلاً 22T19:32:42+00:00-10-2026)؛ خروجی با ارقام فارسی
	 * و همان تقویم گزارش ساخته می‌شود.
	 */
	public static function dateTime( string $value, string $calendar, string $timezone ): string {
		try {
			$zone = new \DateTimeZone( $timezone !== '' ? $timezone : 'UTC' );
		} catch ( \Exception ) {
			$zone = new \DateTimeZone( 'UTC' );
		}
		$formatter = new \IntlDateFormatter( $calendar === 'jalali' ? 'fa_IR@calendar=persian' : 'fa_IR@calendar=gregorian', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT, $zone, \IntlDateFormatter::TRADITIONAL, "yyyy/MM/dd'، ساعت' HH:mm" );
		try {
			$formatted = $formatter->format( new \DateTimeImmutable( $value ) );
		} catch ( \Exception ) {
			return $value;
		}
		return $formatted === false ? $value : $formatted;
	}
	/** متن ورودی سفارش فقط پس از تبدیل به متن امن وارد HTML خروجی می‌شود. */
	public static function html( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ); }
}
