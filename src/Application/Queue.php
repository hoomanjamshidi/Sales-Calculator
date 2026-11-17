<?php
/**
 * صف قابل ادامه با تلاش مجدد محدود و پیام خطای قابل مشاهده.
 *
 * @package SalesCalculator
 */

declare(strict_types=1);
namespace SalesCalculator\Application;

/**
 * کارهای کوتاه نمایه‌سازی و خروجی را به Action Scheduler می‌سپارد.
 */
final class Queue {
	/** ارسال کارهای کوچک به صف موجود ووکامرس. */
	public function push( string $kind, array $payload = array(), int $attempt = 0, int $delay = 0 ): void {
		$args = array( $kind, $payload, $attempt );
		if ( $delay > 0 ) {
			as_schedule_single_action( time() + $delay, 'sc_job', $args, 'sales-calculator' ); } else {
			as_enqueue_async_action( 'sc_job', $args, 'sales-calculator' ); }
	}
	/** شکست‌های موقت با فاصلهٔ افزایشی مجدداً اجرا می‌شوند. */
	public function retry( string $kind, array $payload, int $attempt, \Throwable $error ): void {
		wc_get_logger()->error(
			'Sales Calculator task failed: ' . get_class( $error ),
			array(
				'source' => 'sales-calculator',
				'task'   => $kind,
			)
		);
		if ( $attempt < 5 ) {
			$this->push( $kind, $payload, $attempt + 1, min( 300, 10 * ( 2 ** $attempt ) ) );
			return; }
		// فقط شکست پیمایش کامل نمایه را نامعتبر می‌کند؛ خطای گزارش یا خروجی روی رکورد خودش ثبت می‌شود.
		if ( $kind === 'scan' ) {
			update_option( 'sc_error', 'بخشی از پردازش کامل نشد؛ وضعیت هاست و صف ووکامرس را بررسی و همگام‌سازی را دوباره اجرا کنید.', false );
			// بدون آزادکردن این پرچم، شروع دوباره و تطبیق روزانه برای همیشه رد می‌شدند.
			update_option( 'sc_scanning', false, false );
		} elseif ( $kind === 'order' || $kind === 'prune' ) {
			// تطبیق دوره‌ای بعدی سفارش جاافتاده را دوباره نمایه می‌کند.
			delete_option( 'sc_last_reconcile' ); }
		throw $error;
	}
}
