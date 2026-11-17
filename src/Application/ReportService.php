<?php
/**
 * ساخت نسخهٔ ثابت گزارش و خواندن صفحه‌بندی‌شدهٔ محصولات و متغیرها.
 *
 * @package SalesCalculator
 */

declare(strict_types=1);
namespace SalesCalculator\Application;

use SalesCalculator\Infrastructure\Database;
use SalesCalculator\Domain\ReportFilter;
use SalesCalculator\Domain\Currency;
use SalesCalculator\Infrastructure\Catalog;
/**
 * ReportService مسئول بخش مشخص گزارش فروش است.
 */
final class ReportService {
	/** درگاه داده و صف به سرویس گزارش تزریق می‌شوند. */
	public function __construct( private Database $database, private Queue $queue, private Catalog $catalog ) {}
	/** گزارش فقط پس از تکمیل آماده‌سازی اولیه ساخته می‌شود. */
	public function create( array $input, int $owner ): string {
		if ( ! get_option( 'sc_ready' ) || get_option( 'sc_error' ) || get_option( 'sc_timezone' ) !== wp_timezone_string() ) {
			throw new \InvalidArgumentException( 'ابتدا همگام‌سازی اطلاعات را کامل کنید.' ); }
		$filters = ReportFilter::validate( $input );
		$id      = wp_generate_uuid4();
		if ( $this->database->db->insert(
			$this->database->table( 'reports' ),
			array(
				'id'      => $id,
				'owner'   => $owner,
				'state'   => 'queued',
				'filters' => wp_json_encode( $filters ),
				'summary' => '{}',
				'created' => gmdate( 'Y-m-d H:i:s' ),
				'error'   => '',
			)
		) === false ) {
			throw new \RuntimeException( 'Cannot create report' ); }
		$this->queue->push( 'report', array( 'id' => $id ) );
		return $id;
	}
	/** قفل و تراکنش، نسخهٔ ثابت را از تغییر هم‌زمان سفارش‌ها مستقل می‌کنند. */
	public function build( string $id ): void {
		$d = $this->database;
		$d->locked(
			function () use ( $d, $id ): void {
				$report = $this->get( $id );
				if ( $report['state'] === 'ready' ) {
					return; }
				if ( ! get_option( 'sc_ready' ) ) {
					throw new \RuntimeException( 'Index not ready' ); }
				$f     = $report['filters'];
				$where = $d->db->prepare( 'day BETWEEN %s AND %s AND currency=%s', $f['from'], $f['to'], $f['currency'] );
				if ( $f['products'] ) {
					$where .= ' AND product_id IN (' . implode( ',', array_map( 'intval', $f['products'] ) ) . ')'; }
				// دستهٔ محصول از کاتالوگ امروز خوانده می‌شود؛ دستهٔ بی‌محصول نتیجهٔ خالی می‌دهد.
				$categories = array_map( 'intval', (array) ( $f['categories'] ?? array() ) );
				if ( $categories ) {
					$in     = $this->catalog->productsInCategories( $categories );
					$where .= ' AND product_id IN (' . ( $in ? implode( ',', $in ) : '0' ) . ')'; }
				$names = $categories ? $this->catalog->categoryNames( $categories ) : array();
				$d->transaction(
					function () use ( $d, $id, $where, $f, $names ): void {
						$d->query( $d->db->prepare( 'DELETE FROM ' . $d->table( 'rows' ) . ' WHERE report_id=%s', $id ) );
						// مبالغ ریالی و هزار‌واحدی در نسخهٔ ثابت به تومان تبدیل می‌شوند؛ ضریب از فهرست ثابت Currency است.
						$rate   = Currency::factor( (string) $f['currency'] );
						$factor = $rate === null || $rate === '1' ? '' : '*' . $rate;
						$sql    = $d->db->prepare( 'INSERT INTO ' . $d->table( 'rows' ) . ' (report_id,product_id,variation_id,title,parent_title,attributes,sold,returned,amount) SELECT %s,product_id,variation_id,MAX(title),MAX(parent_title),MAX(attributes),SUM(sold),SUM(returned),SUM(amount)' . $factor . ' FROM ' . $d->table( 'daily' ) . ' WHERE ' . $where . ' GROUP BY product_id,variation_id HAVING SUM(sold)<>0 OR SUM(returned)<>0 OR SUM(amount)<>0', $id );
						$d->query( $sql );
						$summary               = $d->db->get_row( $d->db->prepare( 'SELECT COALESCE(SUM(sold),0) sold,COALESCE(SUM(returned),0) returned,COALESCE(SUM(sold-returned),0) quantity,COALESCE(SUM(CASE WHEN product_id>0 THEN amount ELSE 0 END),0) amount,COALESCE(SUM(CASE WHEN product_id=0 THEN amount ELSE 0 END),0) unallocated FROM ' . $d->table( 'rows' ) . ' WHERE report_id=%s', $id ), ARRAY_A );
						$summary['orders']     = (int) $d->db->get_var( 'SELECT COUNT(DISTINCT order_id) FROM ' . $d->table( 'items' ) . ' WHERE ' . $where . ' AND product_id>0' );
						$summary['asOf']       = gmdate( 'c' );
						$summary['timezone']   = wp_timezone_string();
						$summary['unit']       = Currency::label( (string) $f['currency'] );
						$summary['categories'] = $names;
						$d->db->update(
							$d->table( 'reports' ),
							array(
								'state'   => 'ready',
								'summary' => wp_json_encode( $summary ),
								'error'   => '',
							),
							array( 'id' => $id )
						);
					}
				);
			}
		);
	}
	/** دریافت قرارداد گزارش با داده‌های JSON رمزگشایی‌شده. */
	public function get( string $id ): array {
		$d   = $this->database;
		$row = $d->db->get_row( $d->db->prepare( 'SELECT * FROM ' . $d->table( 'reports' ) . ' WHERE id=%s', $id ), ARRAY_A );
		if ( ! $row ) {
			throw new \InvalidArgumentException( 'گزارش پیدا نشد یا منقضی شده است.' ); }
		$row['filters'] = json_decode( $row['filters'], true );
		$row['summary'] = json_decode( $row['summary'], true );
		return $row;
	}
	/** گروه‌بندی والدها همراه زیرردیف‌ها؛ مرتب‌سازی فقط با فهرست مجاز. */
	public function page( string $id, int $page, string $search, string $sort, string $direction ): array {
		$d         = $this->database;
		$sort      = array(
			'title'    => 'title',
			'sold'     => 'sold',
			'returned' => 'returned',
			'quantity' => 'quantity',
			'amount'   => 'amount',
		)[ $sort ] ?? 'amount';
		$direction = $direction === 'asc' ? 'ASC' : 'DESC';
		$where     = $d->db->prepare( 'report_id=%s AND product_id>0', $id );
		// جست‌وجو ابتدا محصول والد را انتخاب می‌کند؛ سایر متغیرهای همان محصول حذف نمی‌شوند.
		if ( $search !== '' ) {
			$like   = '%' . $d->db->esc_like( $search ) . '%';
			$where .= $d->db->prepare( ' AND product_id IN (SELECT product_id FROM ' . $d->table( 'rows' ) . ' WHERE report_id=%s AND (title LIKE %s OR parent_title LIKE %s OR attributes LIKE %s))', $id, $like, $like, $like ); }
		$total   = (int) $d->db->get_var( 'SELECT COUNT(DISTINCT product_id) FROM ' . $d->table( 'rows' ) . ' WHERE ' . $where );
		$page    = min( max( 1, $page ), max( 1, (int) ceil( $total / 25 ) ) );
		$parents = $d->db->get_results( 'SELECT product_id,MAX(parent_title) title,SUM(sold) sold,SUM(returned) returned,SUM(sold-returned) quantity,SUM(amount) amount FROM ' . $d->table( 'rows' ) . ' WHERE ' . $where . ' GROUP BY product_id ORDER BY ' . $sort . ' ' . $direction . ',product_id ASC LIMIT 25 OFFSET ' . ( ( $page - 1 ) * 25 ), ARRAY_A );
		$parents = $this->catalog->markDeleted( $parents );
		foreach ( $parents as &$parent ) {
			$parent['children'] = $this->catalog->markDeleted( $d->db->get_results( $d->db->prepare( 'SELECT *,sold-returned quantity FROM ' . $d->table( 'rows' ) . ' WHERE report_id=%s AND product_id=%d ORDER BY variation_id,id', $id, $parent['product_id'] ), ARRAY_A ) );
		}
		return array(
			'rows'  => $parents,
			'total' => $total,
			'page'  => $page,
			'pages' => max( 1, (int) ceil( $total / 25 ) ),
		);
	}
	/** نشان «حذف‌شده» برای ردیف‌های خروجی. */
	public function markDeleted( array $rows ): array {
		return $this->catalog->markDeleted( $rows );
	}
	/** پیمایش با کلید افزایشی برای خروجی بزرگ؛ حافظه به اندازهٔ یک دسته محدود است. */
	public function iterate( string $id ): \Generator {
		$d     = $this->database;
		$after = 0;
		do {
			$rows = $this->markDeleted( $d->db->get_results( $d->db->prepare( 'SELECT *,sold-returned quantity FROM ' . $d->table( 'rows' ) . ' WHERE report_id=%s AND product_id>0 AND id>%d ORDER BY id LIMIT 500', $id, $after ), ARRAY_A ) );
			foreach ( $rows as $row ) {
				$after = (int) $row['id'];
				yield $row; }
					$count = count( $rows );
		} while ( $count === 500 );
	}
}
