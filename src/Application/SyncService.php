<?php
/**
 * واردسازی دسته‌ای تاریخچه و رسیدگی به تغییرات سفارش و مرجوعی.
 *
 * @package SalesCalculator
 */

declare(strict_types=1);
namespace SalesCalculator\Application;

use SalesCalculator\Infrastructure\Database;
use SalesCalculator\Infrastructure\IndexRepository;
use SalesCalculator\Infrastructure\Catalog;
use SalesCalculator\Domain\SalesCalculator;
/**
 * تاریخچهٔ سفارش‌ها و تغییرات بعدی را با دسته‌های قابل ادامه نمایه‌سازی می‌کند.
 */
final class SyncService {
	public const BATCH = 100;
	/** وابستگی‌های نمایه‌سازی برای آزمون‌پذیری تزریق می‌شوند. */
	public function __construct( private Database $database, private IndexRepository $index, private SalesCalculator $calculator, private Queue $queue, private Catalog $catalog ) {}
	/** شروع دوره؛ شناسهٔ نسل از اجرای کارهای قدیمی پس از بازسازی جلوگیری می‌کند. */
	public function start( bool $rebuild = false ): void {
		$this->database->locked(
			function () use ( $rebuild ): void {
				if ( get_option( 'sc_scanning' ) && ! $rebuild ) {
					return; }
				if ( $rebuild ) {
					update_option( 'sc_ready', false, false );
					$this->database->transaction(
						function (): void {
							$this->database->query( 'DELETE FROM ' . $this->database->table( 'items' ) );
							$this->database->query( 'DELETE FROM ' . $this->database->table( 'daily' ) );
						}
					);
				}
				$generation = wp_generate_uuid4();
				update_option( 'sc_generation', $generation, false );
				update_option( 'sc_scanning', true, false );
				update_option(
					'sc_progress',
					array(
						'processed' => 0,
						'total'     => 0,
					),
					false
				);
				delete_option( 'sc_error' );
				$this->queue->push(
					'scan',
					array(
						'page'       => 1,
						'generation' => $generation,
					)
				);
			}
		);
	}
	/** یک صفحهٔ محدود؛ پس از شکست همان صفحه با نتیجهٔ یکسان تکرار می‌شود. */
	public function scan( array $payload ): void {
		$this->database->locked(
			function () use ( $payload ): void {
				if ( $payload['generation'] !== get_option( 'sc_generation' ) ) {
					return; }
				$page   = (int) $payload['page'];
				$result = wc_get_orders(
					array(
						'type'     => 'shop_order',
						'limit'    => self::BATCH,
						'paged'    => $page,
						'orderby'  => 'ID',
						'order'    => 'ASC',
						'paginate' => true,
						'return'   => 'ids',
						'status'   => array_keys( wc_get_order_statuses() ),
					)
				);
				foreach ( $result->orders as $id ) {
					$this->syncOrder( (int) $id ); }
				update_option(
					'sc_progress',
					array(
						'processed' => min( $page * self::BATCH, (int) $result->total ),
						'total'     => (int) $result->total,
					),
					false
				);
				if ( $page < $result->max_num_pages ) {
					$this->queue->push(
						'scan',
						array(
							'page'       => $page + 1,
							'generation' => $payload['generation'],
						)
					); } else {
							update_option( 'sc_ready', true, false );
							update_option( 'sc_scanning', false, false );
							update_option( 'sc_synced_at', gmdate( 'c' ), false );
							update_option( 'sc_timezone', wp_timezone_string(), false );
							delete_option( 'sc_error' );
							$this->queue->push( 'prune', array( 'after' => 0 ) );
					}
			}
		);
	}
	/** حذف‌های از دست‌رفته در صف با پیمایش محدود نمایه تطبیق داده می‌شوند. */
	public function prune( int $after ): void {
		$this->database->locked(
			function () use ( $after ): void {
				$d   = $this->database;
				$ids = $d->db->get_col( $d->db->prepare( 'SELECT DISTINCT order_id FROM ' . $d->table( 'items' ) . ' WHERE order_id>%d ORDER BY order_id LIMIT 100', $after ) );
				foreach ( $ids as $id ) {
					if ( ! wc_get_order( (int) $id ) ) {
								$this->index->replace( (int) $id, array() ); }
				}
				if ( count( $ids ) === 100 ) {
					$this->queue->push( 'prune', array( 'after' => (int) end( $ids ) ) ); }
			}
		);
	}
	/** تغییر منفرد فقط زیر قفل مشترک پردازش می‌شود. */
	public function one( int $id ): void {
		$this->database->locked(
			function () use ( $id ): void {
				$this->syncOrder( $id );
				update_option( 'sc_changed_at', gmdate( 'c' ), false );
			}
		);
	}
	/** استخراج اطلاعات منحصراً از مدل رسمی ووکامرس انجام می‌شود. */
	private function syncOrder( int $id ): void {
		$order = wc_get_order( $id );
		if ( $order instanceof \WC_Order_Refund ) {
			$order = wc_get_order( $order->get_parent_id() );
			$id    = $order ? $order->get_id() : $id; }
		$rows = $order instanceof \WC_Order ? $this->calculator->extract( $order ) : array();
		$this->catalog->remember( array_column( $rows, 'product_id' ) );
		$this->index->replace( $id, $rows );
	}
}
