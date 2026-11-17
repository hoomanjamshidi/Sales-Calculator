<?php
/**
 * دسترسی کنترل‌شده به جداول افزونه، قفل مشترک و تراکنش‌های قابل بازگشت.
 *
 * @package SalesCalculator
 */

declare(strict_types=1);
namespace SalesCalculator\Infrastructure;

/**
 * نام جداول، خطاهای SQL، تراکنش و قفل مشترک گزارش را یک‌جا مدیریت می‌کند.
 */
final class Database {
	/** اتصال وردپرس به‌صورت صریح دریافت می‌شود. */
	public function __construct( public \wpdb $db ) {}
	/** نام جدول صرفاً از فهرست ثابت داخلی پذیرفته می‌شود. */
	public function table( string $name ): string {
		if ( ! in_array( $name, array( 'items', 'daily', 'reports', 'rows', 'exports', 'categories' ), true ) ) {
			throw new \InvalidArgumentException( 'Unknown table' ); }
		return $this->db->prefix . 'sc_' . $name;
	}
	/** خطای SQL به خطای قابل رسیدگی تبدیل می‌شود. */
	public function query( string $sql ): int {
		$result = $this->db->query( $sql );
		if ( $result === false ) {
			throw new \RuntimeException( 'Database operation failed' ); }
		return $result;
	}
	/** قفل نام‌دار مانع تداخل نمایه‌سازی و ایجاد نسخهٔ ثابت گزارش می‌شود. */
	public function locked( callable $action ): mixed {
		$key = 'sc_' . md5( DB_NAME . $this->db->prefix );
		if ( (int) $this->db->get_var( $this->db->prepare( 'SELECT GET_LOCK(%s, 5)', $key ) ) !== 1 ) {
			throw new \RuntimeException( 'Index is busy; retry' ); }
		try {
			return $action(); } finally {
			$this->db->get_var( $this->db->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) ); }
	}
	/** اجرای اتمی تغییرات؛ هیچ گزارش نیمه‌کاره‌ای منتشر نمی‌شود. */
	public function transaction( callable $action ): mixed {
		$this->query( 'START TRANSACTION' );
		try {
			$result = $action();
			$this->query( 'COMMIT' );
			return $result; } catch ( \Throwable $error ) {
			// شکست ROLLBACK نباید خطای اصلی را پنهان کند.
			$this->db->query( 'ROLLBACK' );
			throw $error; }
	}
}
