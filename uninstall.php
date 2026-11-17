<?php
/**
 * حذف کامل داده‌های مشتق‌شدهٔ افزونه؛ سفارش‌های ووکامرس دست نمی‌خورند.
 *
 * @package SalesCalculator
 */

declare(strict_types=1);
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit; }
global $wpdb;
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'sc_maintenance', array(), 'sales-calculator' );
	as_unschedule_all_actions( 'sc_job', array(), 'sales-calculator' );
}
// فایل‌های خروجی فقط زیر پوشهٔ تصادفی خود افزونه حذف می‌شوند.
$sc_root = get_option( 'sc_private_dir' );
if ( is_string( $sc_root ) && str_contains( basename( $sc_root ), 'sc-' ) && is_dir( $sc_root ) ) {
	$sc_files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $sc_root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $sc_files as $sc_file ) {
		$sc_file->isDir() && ! $sc_file->isLink() ? rmdir( $sc_file->getPathname() ) : unlink( $sc_file->getPathname() ); }
	rmdir( $sc_root );
}
foreach ( array( 'items', 'daily', 'reports', 'rows', 'exports', 'categories' ) as $sc_table ) {
	// حذف جدول اختصاصی هنگام حذف افزونه؛ کش در این مرحله معنا ندارد.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'sc_' . $sc_table ) );
}
foreach ( array( 'sc_schema', 'sc_generation', 'sc_scanning', 'sc_progress', 'sc_ready', 'sc_error', 'sc_synced_at', 'sc_changed_at', 'sc_timezone', 'sc_last_reconcile', 'sc_private_dir' ) as $sc_option ) {
	delete_option( $sc_option ); }
