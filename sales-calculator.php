<?php
/**
 * Plugin Name: Sales Calculator | گزارش فروش محصولات
 * Description: گزارش فارسی فروش محصولات و متغیرهای ووکامرس با اکسل و PDF وزیر.
 * Version: 1.2.0
 * Requires at least: 6.6
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce
 * Author: Hooman Jamshidi
 * Author URI: https://hoomanjamshidi.ir
 * License: GPL-3.0-or-later
 * Text Domain: sales-calculator
 *
 * نقطهٔ ورود افزونه؛ راه‌اندازی فقط پس از آماده‌شدن ووکامرس انجام می‌شود.
 *
 * @package SalesCalculator
 */

declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) {
	exit; }
// پیشوند کامل نام ثابت‌ها از برخورد با افزونه‌های دیگری که SC_ تعریف می‌کنند جلوگیری می‌کند.
define( 'SALES_CALCULATOR_VERSION', '1.2.0' );
define( 'SALES_CALCULATOR_FILE', __FILE__ );
define( 'SALES_CALCULATOR_DIR', __DIR__ . '/' );
// بستهٔ انتشار از کتابخانه‌های ایزوله استفاده می‌کند؛ محیط توسعه Composer معمولی دارد.
define( 'SALES_CALCULATOR_SCOPED', is_file( SALES_CALCULATOR_DIR . 'lib/autoload.php' ) );
$sales_calculator_autoload = SALES_CALCULATOR_DIR . ( SALES_CALCULATOR_SCOPED ? 'lib/autoload.php' : 'vendor/autoload.php' );
// نصب از سورس بدون composer install نباید کل سایت را با خطای مرگبار متوقف کند.
if ( is_file( $sales_calculator_autoload ) ) {
	require_once $sales_calculator_autoload;
}
unset( $sales_calculator_autoload );
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'SalesCalculator\\';
		if ( str_starts_with( $class_name, $prefix ) ) {
			$file = SALES_CALCULATOR_DIR . 'src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
			if ( is_file( $file ) ) {
				require_once $file; }
		}
	}
);
register_activation_hook( __FILE__, array( SalesCalculator\Infrastructure\Schema::class, 'install' ) );
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SALES_CALCULATOR_FILE, true );
		}
	}
);
register_deactivation_hook(
	__FILE__,
	static function (): void {
		// کارهای زمان‌بندی‌شده بدون افزونهٔ فعال فقط در صف به‌عنوان شکست ثبت می‌شوند.
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'sc_maintenance', array(), 'sales-calculator' );
			as_unschedule_all_actions( 'sc_job', array(), 'sales-calculator' );
		}
		// سفارش‌های تغییرکرده در زمان غیرفعال‌بودن با پیمایش تازه پس از فعال‌سازی دوباره نمایه می‌شوند.
		delete_option( 'sc_scanning' );
		delete_option( 'sc_generation' );
	}
);
add_action(
	'plugins_loaded',
	static function (): void {
		// محاسبهٔ دقیق مبلغ و تاریخ شمسی بدون این افزونه‌های PHP ممکن نیست.
		$missing = array_values( array_filter( array( 'bcmath', 'intl', 'mbstring', 'zip' ), static fn( string $extension ): bool => ! extension_loaded( $extension ) ) );
		$vendor  = class_exists( SALES_CALCULATOR_SCOPED ? 'SalesCalculatorVendor\\Mpdf\\Mpdf' : 'Mpdf\\Mpdf' );
		if ( class_exists( 'WooCommerce' ) && function_exists( 'as_enqueue_async_action' ) && ! $missing && $vendor ) {
			( new SalesCalculator\Plugin() )->boot();
			return; }
		if ( $missing ) {
			$message = 'گزارش فروش برای اجرا به افزونه‌های PHP زیر نیاز دارد: ' . implode( '، ', $missing );
		} elseif ( ! $vendor ) {
			$message = 'کتابخانه‌های گزارش فروش نصب نشده‌اند؛ بستهٔ ZIP انتشار را نصب کنید یا composer install را اجرا کنید.';
		} else {
			$message = 'گزارش فروش برای اجرا به ووکامرس فعال (همراه Action Scheduler) نیاز دارد.';
		}
		add_action(
			'admin_notices',
			static function () use ( $message ): void {
				echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
			}
		);
	},
	20
);
