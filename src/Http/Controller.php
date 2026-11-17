<?php
/**
 * مرز HTTP: مجوز، اعتبارسنجی ورودی و تبدیل خطا به پاسخ فارسی.
 *
 * @package SalesCalculator
 */

declare(strict_types=1);
namespace SalesCalculator\Http;

use SalesCalculator\Infrastructure\Database;
use SalesCalculator\Infrastructure\Catalog;
use SalesCalculator\Application\ReportService;
use SalesCalculator\Application\SyncService;
use SalesCalculator\Export\ExportService;
/**
 * مرز درخواست‌های REST و دانلود را با کنترل دسترسی و پاسخ امن پیاده می‌کند.
 */
final class Controller {
	/** سرویس‌ها تزریق می‌شوند؛ منطق محاسبه در کنترلر قرار ندارد. */
	public function __construct( private Database $database, private ReportService $reports, private SyncService $sync, private ExportService $exports, private Catalog $catalog ) {}
	/** قراردادهای REST داخلی افزونه. */
	public function register(): void {
		$routes = array(
			'/status'                                => array( 'GET', fn()=> $this->status() ),
			'/products'                              => array( 'GET', fn( $r )=>$this->products( (string) $r->get_param( 'q' ) ) ),
			'/sync'                                  => array(
				'POST',
				function ( $r ) {
										$this->sync->start( (bool) $r->get_param( 'rebuild' ) );
										return array( 'ok' => true ); },
			),
			'/categories'                            => array( 'GET', fn()=> $this->catalog->categories() ),
			'/reports'                               => array( 'POST', fn( $r )=>array( 'id' => $this->reports->create( (array) $r->get_json_params(), get_current_user_id() ) ) ),
			'/reports/(?P<id>[a-f0-9-]{36})'         => array(
				'GET',
				function ( $r ) {
									$report = $this->ownedReport( $r['id'] );
					if ( $report['state'] === 'queued' && $this->nudge( $r['id'], $report['created'] ) ) {
						$report = $this->ownedReport( $r['id'] ); }
									return $report + $this->reports->page( $r['id'], (int) $r->get_param( 'page' ), mb_substr( (string) $r->get_param( 'q' ), 0, 200 ), (string) $r->get_param( 'sort' ), (string) $r->get_param( 'direction' ) ); },
			),
			'/reports/(?P<id>[a-f0-9-]{36})/exports' => array(
				'POST',
				function ( $r ) {
									$this->ownedReport( $r['id'] );
									return array( 'id' => $this->exports->create( $r['id'], (string) $r->get_param( 'format' ), get_current_user_id() ) ); },
			),
			'/exports/(?P<id>[a-f0-9-]{36})'         => array(
				'GET',
				function ( $r ) {
					$job = $this->exports->get( $r['id'] );
					$this->assertOwner( $job );
					if ( in_array( $job['state'], array( 'queued', 'building' ), true ) && $this->nudge( $r['id'], $job['created'] ) ) {
						$job = $this->exports->get( $r['id'] ); }
					unset( $job['path'] );
					if ( $job['state'] === 'ready' ) {
						// wp_nonce_url برای HTML نویسهٔ & را escape می‌کند؛ JSON به URL خام نیاز دارد.
						$job['url'] = add_query_arg(
							array(
								'action'   => 'sc_download',
								'id'       => $job['id'],
								'_wpnonce' => wp_create_nonce( 'sc_download_' . $job['id'] ),
							),
							admin_url( 'admin-post.php' )
						);
					} return $job; },
			),
		);
		foreach ( $routes as $route => [$method, $callback] ) {
			register_rest_route(
				'sales-calculator/v1',
				$route,
				array(
					'methods'             => $method,
					'permission_callback' => array( $this, 'permission' ),
					'callback'            => function ( \WP_REST_Request $request ) use ( $callback ) {
						return $this->respond( fn()=> $callback( $request ) );
					},
				)
			);
		}
	}
	/** تمام درخواست‌ها علاوه بر نقش معتبر نیاز به nonce معتبر نشست دارند. */
	public function permission( \WP_REST_Request $request ): bool|\WP_Error {
		if ( ! current_user_can( 'view_woocommerce_reports' ) || ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
			return new \WP_Error( 'sc_forbidden', 'اجازهٔ دسترسی به گزارش را ندارید.', array( 'status' => 403 ) ); }
		return true;
	}
	/** وضعیت همگام‌سازی و ارزهای واقعی موجود در داده‌ها. */
	private function status(): array {
		$d          = $this->database;
		$currencies = $d->db->get_col( 'SELECT DISTINCT currency FROM ' . $d->table( 'daily' ) . ' ORDER BY currency' );
		return array(
			'ready'      => (bool) get_option( 'sc_ready' ) && get_option( 'sc_timezone' ) === wp_timezone_string(),
			'scanning'   => (bool) get_option( 'sc_scanning' ),
			'progress'   => get_option(
				'sc_progress',
				array(
					'processed' => 0,
					'total'     => 0,
				)
			),
			'syncedAt'   => get_option( 'sc_synced_at', null ),
			'changedAt'  => get_option( 'sc_changed_at', null ),
			'error'      => get_option( 'sc_error', null ),
			'currencies' => array_values( array_unique( array_merge( array( get_woocommerce_currency() ), $currencies ) ) ),
			'today'      => \SalesCalculator\Domain\ReportFilter::today(),
			'timezone'   => wp_timezone_string(),
			// as_get_scheduled_actions شمارش را پشتیبانی نمی‌کند؛ شمارش مستقیم از مخزن صف گرفته می‌شود.
			'pending'    => (int) \ActionScheduler::store()->query_actions(
				array(
					'hook'   => 'sc_job',
					'group'  => 'sales-calculator',
					'status' => \ActionScheduler_Store::STATUS_PENDING,
				),
				'count'
			),
		);
	}
	/** جست‌وجوی نمایه شامل محصولات حذف‌شده؛ فقط گزینه‌های محدود بازگردانده می‌شوند. */
	private function products( string $search ): array {
		$d = $this->database;
		// نام والد نمایش داده می‌شود تا محصول متغیر با نام یکی از متغیرهایش ظاهر نشود.
		$like = '%' . $d->db->esc_like( mb_substr( $search, 0, 100 ) ) . '%';
		return $d->db->get_results( $d->db->prepare( 'SELECT product_id id,MAX(parent_title) name FROM ' . $d->table( 'daily' ) . ' WHERE product_id>0 AND (parent_title LIKE %s OR title LIKE %s) GROUP BY product_id ORDER BY name LIMIT 30', $like, $like ), ARRAY_A );
	}
	/**
	 * اجرای کار صف همین گزارش یا خروجی در درخواست وضعیت، وقتی صف خودش اجرا نشده است.
	 *
	 * در هاستی که loopback یا WP-Cron کار نمی‌کند، Action Scheduler کارها را اجرا نمی‌کرد و گزارش و خروجی
	 * برای همیشه «در حال ساخت» می‌ماند. پس از ۵ ثانیه انتظار، فقط کارهای سررسیدهٔ همین شناسه با اجراکنندهٔ
	 * خود Action Scheduler پردازش می‌شوند؛ قفل گزینه‌ای مانع اجرای هم‌زمان در دو درخواست می‌شود.
	 */
	private function nudge( string $id, string $created ): bool {
		if ( strtotime( $created . ' UTC' ) > time() - 5 || ! class_exists( \ActionScheduler::class ) ) {
			return false; }
		$lock = 'sc_nudge_' . $id;
		if ( ! add_option( $lock, time(), '', false ) ) {
			if ( (int) get_option( $lock ) > time() - 300 ) {
				return false; }
			update_option( $lock, time(), false );
		}
		try {
			$actions = \ActionScheduler::store()->query_actions(
				array(
					'hook'         => 'sc_job',
					'group'        => 'sales-calculator',
					'status'       => \ActionScheduler_Store::STATUS_PENDING,
					'search'       => $id,
					'date'         => gmdate( 'Y-m-d H:i:s' ),
					'date_compare' => '<=',
					'per_page'     => 3,
				)
			);
			$ran     = false;
			foreach ( $actions as $action_id ) {
				// جست‌وجوی Action Scheduler متن را به عدد هم تبدیل و با شناسهٔ کار مقایسه می‌کند؛ فقط کاری اجرا می‌شود که آرگومانش همین شناسه است.
				$args = \ActionScheduler::store()->fetch_action( (string) $action_id )->get_args();
				if ( ( $args[1]['id'] ?? null ) !== $id ) {
					continue; }
				\ActionScheduler::runner()->process_action( (int) $action_id, 'Sales Calculator' );
				$ran = true;
			}
			return $ran;
		} finally {
			delete_option( $lock );
		}
	}
	/** گزارش‌ها میان کاربران با حدس شناسه قابل مشاهده نیستند. */
	private function ownedReport( string $id ): array {
		$report = $this->reports->get( $id );
		$this->assertOwner( $report );
		return $report; }
	/** کنترل مالک مشترک میان گزارش و فایل خروجی. */
	private function assertOwner( array $record ): void {
		if ( (int) $record['owner'] !== get_current_user_id() ) {
			throw new \DomainException( 'اجازهٔ دسترسی به این گزارش را ندارید.' ); } }
	/** خطای داخلی بدون افشای SQL یا مسیر سرور به کاربر برگردانده می‌شود. */
	private function respond( callable $action ): mixed {
		try {
			return rest_ensure_response( $action() ); } catch ( \InvalidArgumentException $e ) {
			return new \WP_Error( 'sc_invalid', $e->getMessage(), array( 'status' => 400 ) ); } catch ( \DomainException $e ) {
				return new \WP_Error( 'sc_forbidden', $e->getMessage(), array( 'status' => 403 ) ); } catch ( \Throwable $e ) {
				wc_get_logger()->error( get_class( $e ), array( 'source' => 'sales-calculator' ) );
				return new \WP_Error( 'sc_error', 'عملیات کامل نشد؛ دوباره تلاش کنید یا گزارش خطای ووکامرس را بررسی کنید.', array( 'status' => 500 ) ); }
	}
	/** فایل از فضای خصوصی پس از کنترل نشست و انقضا ارسال می‌شود. */
	public function download(): void {
		$id = sanitize_text_field( wp_unslash( $_GET['id'] ?? '' ) );
		if ( ! current_user_can( 'view_woocommerce_reports' ) ) {
			wp_die( 'دسترسی مجاز نیست.', 403 ); }
		check_admin_referer( 'sc_download_' . $id );
		try {
			$job = $this->exports->get( $id );
			$this->assertOwner( $job );
			$path = realpath( $job['path'] );
			$root = realpath( $this->exports->root() );
			if ( $job['state'] !== 'ready' || strtotime( $job['created'] . ' UTC' ) < time() - DAY_IN_SECONDS || ! $path || ! $root || ! str_starts_with( $path, $root . DIRECTORY_SEPARATOR ) || ! is_file( $path ) ) {
				throw new \RuntimeException( 'File unavailable' ); }
			$ext   = pathinfo( $path, PATHINFO_EXTENSION );
			$types = array(
				'pdf'  => 'application/pdf',
				'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				'zip'  => 'application/zip',
			);
			if ( ! isset( $types[ $ext ] ) ) {
				throw new \RuntimeException( 'Unknown file type' ); }
			// خروجی بافرشدهٔ افزونه‌های دیگر نباید وارد فایل باینری شود.
			while ( ob_get_level() > 0 ) {
				ob_end_clean(); }
			nocache_headers();
			header( 'X-Content-Type-Options: nosniff' );
			header( 'Content-Type: ' . $types[ $ext ] );
			header( 'Content-Disposition: attachment; filename="sales-report.' . $ext . '"' );
			header( 'Content-Length: ' . filesize( $path ) );
			readfile( $path );
			exit;
		} catch ( \Throwable ) {
			wp_die( 'فایل در دسترس نیست یا منقضی شده است.', 403 ); }
	}
}
