<?php
/**
 * ترکیب سرویس‌ها و اتصال آن‌ها به چرخهٔ عمر وردپرس و ووکامرس.
 *
 * @package SalesCalculator
 */

declare(strict_types=1);
namespace SalesCalculator;

use SalesCalculator\Application\{Queue, SyncService, ReportService};
use SalesCalculator\Infrastructure\{Schema, Database, IndexRepository, Catalog};
use SalesCalculator\Domain\SalesCalculator;
use SalesCalculator\Export\{ExportService, PdfWriter, ExcelWriter};
use SalesCalculator\Http\Controller;
/**
 * وابستگی‌ها را می‌سازد و صفحه، API، صف و رویدادهای ووکامرس را ثبت می‌کند.
 */
final class Plugin {
	/**
	 * شناسهٔ واقعی صفحه؛ عنوان ترجمه‌شدهٔ منوی ووکامرس در نام هوک اثر دارد.
	 *
	 * @var string
	 */
	private string $hook = '';
	/** ثبت منو، API و کارهای پس‌زمینه بدون تغییر فرانت‌اند فروشگاه. */
	public function boot(): void {
		global $wpdb;
		$upgrading = get_option( 'sc_schema' ) !== Schema::VERSION;
		if ( $upgrading ) {
			Schema::install(); }
		$database   = new Database( $wpdb );
		$queue      = new Queue();
		$catalog    = new Catalog( $database );
		$sync       = new SyncService( $database, new IndexRepository( $database ), new SalesCalculator(), $queue, $catalog );
		$reports    = new ReportService( $database, $queue, $catalog );
		$exports    = new ExportService( $database, $reports, $queue, new PdfWriter(), new ExcelWriter() );
		$controller = new Controller( $database, $reports, $sync, $exports, $catalog );
		// دانلود admin-post خارج از چرخهٔ REST نیز باید ثبت شود.
		add_action( 'admin_post_sc_download', array( $controller, 'download' ) );
		add_action( 'rest_api_init', array( $controller, 'register' ) );
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action(
			'init',
			static function () use ( $queue, $upgrading ): void {
				// این هوک در همهٔ درخواست‌های سایت اجرا می‌شود؛ شروع همگام‌سازی فقط در صف ثبت می‌شود تا درخواست
				// فروشگاه هرگز منتظر قفل نمایه نماند یا با خطای پایگاه داده متوقف نشود.
				try {
					$rebuild = null;
					if ( $upgrading || ! get_option( 'sc_generation' ) ) {
						$rebuild = $upgrading;
					} elseif ( get_option( 'sc_ready' ) && ! get_option( 'sc_scanning' ) && get_option( 'sc_timezone' ) !== wp_timezone_string() ) {
						// تغییر منطقهٔ زمانی روز سفارش‌ها را عوض می‌کند؛ پیمایش مجدد نمایه را بدون حذف داده اصلاح می‌کند.
						$rebuild = false;
					}
					$payload = array( 'rebuild' => $rebuild );
					if ( $rebuild !== null && ! as_has_scheduled_action( 'sc_job', array( 'start', $payload, 0 ), 'sales-calculator' ) ) {
						$queue->push( 'start', $payload ); }
					if ( ! as_has_scheduled_action( 'sc_maintenance', array(), 'sales-calculator' ) ) {
						as_schedule_recurring_action( time() + HOUR_IN_SECONDS, HOUR_IN_SECONDS, 'sc_maintenance', array(), 'sales-calculator' ); }
				} catch ( \Throwable $error ) {
					// درخواست بعدی دوباره تلاش می‌کند؛ پیش از آن نسخهٔ Schema را برمی‌گردانیم تا ارتقا جا نماند.
					if ( $upgrading ) {
						delete_option( 'sc_schema' ); }
					wc_get_logger()->warning( 'Sales Calculator init deferred: ' . get_class( $error ), array( 'source' => 'sales-calculator' ) );
				}
			},
			20
		);
		add_action(
			'sc_maintenance',
			static function () use ( $sync, $exports ): void {
				$exports->cleanup();
				if ( (int) get_option( 'sc_last_reconcile', 0 ) < time() - DAY_IN_SECONDS ) {
					$sync->start();
					update_option( 'sc_last_reconcile', time(), false ); }
			}
		);
		add_action(
			'sc_job',
			static function ( string $kind, array $payload, int $attempt ) use ( $sync, $reports, $exports, $queue, $database ): void {
				try {
					match ( $kind ) {
						'start'=>$sync->start( (bool) $payload['rebuild'] ),
						'scan'=>$sync->scan( $payload ),
						'prune'=>$sync->prune( (int) $payload['after'] ),
						'order'=>$sync->one( (int) $payload['id'] ),
						'report'=>$reports->build( $payload['id'] ),
						'export'=>$exports->build( $payload ),
						default=>throw new \InvalidArgumentException( 'Unknown task' ),
					};
				} catch ( \Throwable $error ) {
					if ( $attempt >= 5 && in_array( $kind, array( 'report', 'export' ), true ) ) {
						$database->db->update(
							$database->table( $kind === 'report' ? 'reports' : 'exports' ),
							array(
								'state' => 'failed',
								'error' => 'پردازش کامل نشد؛ دوباره گزارش یا خروجی را بسازید.',
							),
							array( 'id' => $payload['id'] )
						);
					}
					$queue->retry( $kind, $payload, $attempt, $error );
				}
			},
			10,
			3
		);
		// تغییر سفارش فقط صف ایجاد می‌کند؛ مسیر خرید منتظر گزارش‌گیری نمی‌ماند.
		$changed = static function ( $id ) use ( $queue ): void {
			$args = array( 'order', array( 'id' => (int) $id ), 0 );
			// ذخیره‌های پیاپی یک سفارش فقط یک کار در انتظار می‌سازند؛ کار در حال اجرا تغییر تازه را نمی‌بیند.
			if ( (int) $id > 0 && ! is_int( as_next_scheduled_action( 'sc_job', $args, 'sales-calculator' ) ) ) {
				$queue->push( 'order', $args[1] ); }
		};
		foreach ( array( 'woocommerce_new_order', 'woocommerce_update_order', 'woocommerce_order_status_changed', 'woocommerce_delete_order', 'woocommerce_trash_order', 'woocommerce_untrash_order', 'woocommerce_order_refunded' ) as $hook ) {
			add_action( $hook, $changed, 20, 1 ); }
		add_action(
			'woocommerce_refund_deleted',
			static function ( $refundId, $orderId ) use ( $changed ): void {
				$changed( $orderId );
			},
			20,
			2
		);
		add_action(
			'woocommerce_update_order_refund',
			static function ( $id ) use ( $changed ): void {
				$refund = wc_get_order( $id );
				if ( $refund ) {
					$changed( $refund->get_parent_id() );
				} },
			20,
			1
		);
	}
	/** صفحهٔ گزارش تنها برای دارندگان مجوز گزارش‌های ووکامرس. */
	public function menu(): void {
		$title      = (string) Brand::get()['menu_title'];
		$this->hook = (string) add_submenu_page(
			'woocommerce',
			$title,
			$title,
			'view_woocommerce_reports',
			'sales-calculator',
			static function (): void {
				echo '<div id="sales-calculator" dir="rtl" lang="fa"></div>';
			}
		);
	}
	/** تنظیمات اولیه فقط اطلاعات عمومی فروشگاه و nonce نشست را شامل می‌شوند. */
	public function assets( string $hook ): void {
		if ( $this->hook === '' || $hook !== $this->hook ) {
			return; }
		$css = SALES_CALCULATOR_DIR . 'assets/build/app.css';
		$js  = SALES_CALCULATOR_DIR . 'assets/build/app.js';
		if ( ! is_file( $css ) || ! is_file( $js ) ) {
			// نصب از سورس بدون npm run build؛ به‌جای صفحهٔ خالی، علت را نشان می‌دهیم.
			wp_add_inline_script( 'common', 'document.addEventListener("DOMContentLoaded",function(){var r=document.getElementById("sales-calculator");if(r)r.textContent="فایل‌های رابط کاربری ساخته نشده‌اند؛ npm run build را اجرا کنید یا بستهٔ ZIP انتشار را نصب کنید.";});' );
			return;
		}
		wp_enqueue_style( 'sales-calculator', plugins_url( 'assets/build/app.css', SALES_CALCULATOR_FILE ), array(), SALES_CALCULATOR_VERSION . '.' . filemtime( $css ) );
		wp_enqueue_script( 'sales-calculator', plugins_url( 'assets/build/app.js', SALES_CALCULATOR_FILE ), array(), SALES_CALCULATOR_VERSION . '.' . filemtime( $js ), true );
		// نسخهٔ اختصاصی مشتری فقط توکن‌های رنگ و فونت را بازنویسی می‌کند؛ ساختار رابط یکی است.
		$brand_css = Brand::css();
		if ( $brand_css !== '' ) {
			wp_add_inline_style( 'sales-calculator', $brand_css ); }
		$brand = Brand::get();
		wp_add_inline_script(
			'sales-calculator',
			'window.scConfig=' . wp_json_encode(
				array(
					'api'      => rest_url( 'sales-calculator/v1/' ),
					'nonce'    => wp_create_nonce( 'wp_rest' ),
					'store'    => get_bloginfo( 'name' ),
					'today'    => \SalesCalculator\Domain\ReportFilter::today(),
					'currency' => get_woocommerce_currency(),
					'fonts'    => plugins_url( 'assets/fonts/', SALES_CALCULATOR_FILE ),
					'brand'    => array(
						'custom'  => Brand::custom(),
						'name'    => $brand['name'],
						'nameEn'  => $brand['name_en'],
						'tagline' => $brand['tagline'],
						'logo'    => Brand::url( 'logo' ),
						'mark'    => Brand::url( 'mark' ),
						'yarn'    => count( $brand['yarn'] ) > 0,
					),
				)
			) . ';',
			'before'
		);
	}
}
