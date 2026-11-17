<?php
/**
 * به‌روزرسانی افزایشی نمایه و جمع‌های روزانه بدون بازخوانی کل تاریخچه.
 *
 * @package SalesCalculator
 */

declare(strict_types=1);
namespace SalesCalculator\Infrastructure;

/**
 * نمایهٔ روزانه را پس از تغییر هر سفارش به‌صورت اتمی جایگزین می‌کند.
 */
final class IndexRepository {
	/** مخزن فقط به درگاه پایگاه داده وابسته است. */
	public function __construct( private Database $database ) {}
	/** جایگزینی اتمی اقلام یک سفارش؛ اجرای دوباره نتیجه را دوبرابر نمی‌کند. */
	public function replace( int $orderId, array $rows ): void {
		$d = $this->database;
		$d->transaction(
			function () use ( $d, $orderId, $rows ): void {
				$old = $d->db->get_results( $d->db->prepare( 'SELECT * FROM ' . $d->table( 'items' ) . ' WHERE order_id=%d', $orderId ), ARRAY_A );
				foreach ( $old as $row ) {
					$this->delta( $row, -1 ); }
				$d->query( $d->db->prepare( 'DELETE FROM ' . $d->table( 'items' ) . ' WHERE order_id=%d', $orderId ) );
				foreach ( $rows as $row ) {
					if ( $d->db->insert( $d->table( 'items' ), $row ) === false ) {
						throw new \RuntimeException( 'Cannot index order' ); }
					$this->delta( $row, 1 );
				}
			}
		);
	}
	/** جمع جبری قدیم و جدید، هزینهٔ تغییر را مستقل از تعداد کل سفارش‌ها نگه می‌دارد. */
	private function delta( array $row, int $sign ): void {
		$d   = $this->database;
		$sql = 'INSERT INTO ' . $d->table( 'daily' ) . ' (day,currency,product_id,variation_id,title,parent_title,attributes,sold,returned,amount) VALUES (%s,%s,%d,%d,%s,%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE title=VALUES(title),parent_title=VALUES(parent_title),attributes=VALUES(attributes),sold=sold+VALUES(sold),returned=returned+VALUES(returned),amount=amount+VALUES(amount)';
		$d->query( $d->db->prepare( $sql, $row['day'], $row['currency'], $row['product_id'], $row['variation_id'], $row['title'], $row['parent_title'], $row['attributes'], bcmul( (string) $row['sold'], (string) $sign, 8 ), bcmul( (string) $row['returned'], (string) $sign, 8 ), bcmul( (string) $row['amount'], (string) $sign, 8 ) ) );
	}
}
