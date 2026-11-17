<?php
/**
 * ایجاد و ارتقای جداول مستقل گزارش؛ هیچ جدول ووکامرس تغییر نمی‌کند.
 *
 * @package SalesCalculator
 */

declare(strict_types=1);
namespace SalesCalculator\Infrastructure;

/**
 * ساختار جداول اختصاصی و مهاجرت نسخه‌های قدیمی را مدیریت می‌کند.
 */
final class Schema {
	/** نسخهٔ ساختار برای اجرای مهاجرت فقط در زمان نیاز. */
	public const VERSION = '6';
	/** ایجاد جداول با موتور تراکنشی و شاخص‌های بازه، محصول و مالک گزارش. */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$p       = $wpdb->prefix . 'sc_';
		$c       = $wpdb->get_charset_collate();
		$columns = 'product_id bigint unsigned NOT NULL, variation_id bigint unsigned NOT NULL DEFAULT 0, title text NOT NULL, parent_title text NOT NULL, attributes text NOT NULL, sold decimal(26,8) NOT NULL DEFAULT 0, returned decimal(26,8) NOT NULL DEFAULT 0, amount decimal(26,8) NOT NULL DEFAULT 0';
		$queries = array(
			"CREATE TABLE {$p}items (order_id bigint unsigned NOT NULL, item_id bigint unsigned NOT NULL, day date NOT NULL, currency varchar(12) NOT NULL, {$columns}, PRIMARY KEY  (order_id,item_id), KEY date_currency (day,currency,product_id), KEY product_date (product_id,day)) ENGINE=InnoDB {$c};",
			"CREATE TABLE {$p}daily (day date NOT NULL, currency varchar(12) NOT NULL, {$columns}, PRIMARY KEY  (day,currency,product_id,variation_id)) ENGINE=InnoDB {$c};",
			"CREATE TABLE {$p}reports (id varchar(36) NOT NULL, owner bigint unsigned NOT NULL, state varchar(20) NOT NULL, filters longtext NOT NULL, summary longtext NOT NULL, created datetime NOT NULL, error text NOT NULL, PRIMARY KEY  (id), KEY owner_created (owner,created)) ENGINE=InnoDB {$c};",
			"CREATE TABLE {$p}rows (id bigint unsigned NOT NULL AUTO_INCREMENT, report_id varchar(36) NOT NULL, {$columns}, PRIMARY KEY  (id), KEY report_product (report_id,product_id,variation_id)) ENGINE=InnoDB {$c};",
			"CREATE TABLE {$p}exports (id varchar(36) NOT NULL, report_id varchar(36) NOT NULL, owner bigint unsigned NOT NULL, format varchar(8) NOT NULL, state varchar(20) NOT NULL, path text NOT NULL, created datetime NOT NULL, error text NOT NULL, PRIMARY KEY  (id), KEY created (created)) ENGINE=InnoDB {$c};",
			// دستهٔ محصولات فروخته‌شده نگه داشته می‌شود تا محصول حذف‌شده در فیلتر دسته هم بماند.
			"CREATE TABLE {$p}categories (product_id bigint unsigned NOT NULL, term_id bigint unsigned NOT NULL, PRIMARY KEY  (product_id,term_id), KEY term (term_id)) ENGINE=InnoDB {$c};",
		);
		foreach ( $queries as $query ) {
			dbDelta( $query ); }
		// dbDelta در ارتقای جدول قدیمی همیشه ستون متنی تازه را تشخیص نمی‌دهد.
		foreach ( array( 'items', 'daily', 'rows' ) as $name ) {
			$table = $p . $name;
			// این بررسی فقط هنگام ارتقای نسخه انجام می‌شود؛ کش برای ساختار جدول معنا ندارد.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$column = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $table, 'parent_title' ) );
			// تغییر ساختار زیر کنترل نسخهٔ Schema است و فقط برای مهاجرت قدیمی اجرا می‌شود.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( ! $column && false === $wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN parent_title text NOT NULL AFTER title', $table ) ) ) {
				throw new \RuntimeException( 'Cannot upgrade report table' );
			}
		}
		update_option( 'sc_schema', self::VERSION, false );
	}
}
