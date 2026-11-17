<?php
/**
 * مدیریت چرخهٔ فایل‌های خصوصی، بخش‌بندی PDF و حذف خروجی‌های منقضی.
 *
 * @package SalesCalculator
 */

declare(strict_types=1);
namespace SalesCalculator\Export;

use SalesCalculator\Infrastructure\Database;
use SalesCalculator\Application\Queue;
use SalesCalculator\Application\ReportService;
/**
 * ExportService مسئول بخش مشخص گزارش فروش است.
 */
final class ExportService {
	/** سرویس‌های تولید و مخزن گزارش به‌صورت صریح تزریق می‌شوند. */
	public function __construct( private Database $database, private ReportService $reports, private Queue $queue, private PdfWriter $pdf, private ExcelWriter $excel ) {}
	/** مسیر خصوصی خارج از پوشهٔ عمومی وب با نام تصادفی ساخته می‌شود. */
	public function root(): string {
		$root = get_option( 'sc_private_dir' );
		if ( ! $root ) {
			$root = rtrim( sys_get_temp_dir(), '/' ) . '/sc-' . wp_generate_password( 32, false, false );
			add_option( 'sc_private_dir', $root, '', false );
			$root = get_option( 'sc_private_dir' ); }
		if ( ! is_dir( $root ) && ! wp_mkdir_p( $root ) ) {
			throw new \RuntimeException( 'فضای موقت قابل نوشتن نیست.' ); }
		chmod( $root, 0700 );
		return $root;
	}
	/** ایجاد رکورد خروجی فقط برای گزارش کامل و قالب مجاز. */
	public function create( string $reportId, string $format, int $owner ): string {
		if ( ! in_array( $format, array( 'pdf', 'xlsx' ), true ) ) {
			throw new \InvalidArgumentException( 'قالب خروجی معتبر نیست.' ); }
		$report = $this->reports->get( $reportId );
		if ( $report['state'] !== 'ready' || (int) $report['owner'] !== $owner ) {
			throw new \InvalidArgumentException( 'گزارش هنوز آماده نیست.' ); }
		$id       = wp_generate_uuid4();
		$inserted = $this->database->db->insert(
			$this->database->table( 'exports' ),
			array(
				'id'        => $id,
				'report_id' => $reportId,
				'owner'     => $owner,
				'format'    => $format,
				'state'     => 'queued',
				'path'      => '',
				'created'   => gmdate( 'Y-m-d H:i:s' ),
				'error'     => '',
			)
		);
		if ( $inserted === false ) {
			throw new \RuntimeException( 'Cannot create export' ); }
		$this->queue->push(
			'export',
			array(
				'id'    => $id,
				'after' => 0,
				'part'  => 1,
			)
		);
		return $id;
	}
	/** هر بخش PDF یک کار مستقل است؛ تکرار همان کار فایل همان بخش را جایگزین می‌کند. */
	public function build( array $payload ): void {
		$id  = $payload['id'];
		$job = $this->get( $id );
		if ( $job['state'] === 'ready' ) {
			return; }
		$dir = $this->root() . '/' . $id;
		wp_mkdir_p( $dir . '/temp' );
		$report = $this->reports->get( $job['report_id'] );
		$this->database->db->update( $this->database->table( 'exports' ), array( 'state' => 'building' ), array( 'id' => $id ) );
		if ( $job['format'] === 'xlsx' ) {
			$path = $dir . '/sales.xlsx';
			$this->excel->write( $report, $this->reports->iterate( $job['report_id'] ), $path, $dir . '/temp' );
		} else {
			$d    = $this->database;
			$rows = $d->db->get_results( $d->db->prepare( 'SELECT *,sold-returned quantity FROM ' . $d->table( 'rows' ) . ' WHERE report_id=%s AND product_id>0 AND id>%d ORDER BY id LIMIT 2001', $job['report_id'], $payload['after'] ), ARRAY_A );
			$more = count( $rows ) > 2000;
			if ( $more ) {
				array_pop( $rows ); }
			$rows = $this->reports->markDeleted( $rows );
			$part = (int) $payload['part'];
			$path = $dir . '/sales-' . sprintf( '%04d', $part ) . '.pdf';
			$this->pdf->write( $report, $rows, $path, $dir . '/temp', $part );
			if ( $more ) {
				$this->queue->push(
					'export',
					array(
						'id'    => $id,
						'after' => (int) end( $rows )['id'],
						'part'  => $part + 1,
					)
				);
				return; }
			if ( $part > 1 ) {
				$path = $dir . '/sales-pdf.zip';
				$zip  = new \ZipArchive();
				if ( $zip->open( $path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) !== true ) {
					throw new \RuntimeException( 'Cannot create ZIP' ); }
				$parts = glob( $dir . '/sales-*.pdf' );
				foreach ( is_array( $parts ) ? $parts : array() as $file ) {
					$zip->addFile( $file, basename( $file ) ); }
				$zip->close();
			}
		}
		chmod( $path, 0600 );
		$this->database->db->update(
			$this->database->table( 'exports' ),
			array(
				'state' => 'ready',
				'path'  => $path,
				'error' => '',
			),
			array( 'id' => $id )
		);
	}
	/** خواندن رکورد؛ اعتبار مالک در کنترلر بررسی می‌شود. */
	public function get( string $id ): array {
		$d   = $this->database;
		$row = $d->db->get_row( $d->db->prepare( 'SELECT * FROM ' . $d->table( 'exports' ) . ' WHERE id=%s', $id ), ARRAY_A );
		if ( ! $row ) {
			throw new \InvalidArgumentException( 'فایل پیدا نشد یا منقضی شده است.' ); }
		return $row;
	}
	/** حذف فایل‌ها پس از ۲۴ ساعت؛ مسیر حذف فقط زیر ریشهٔ خصوصی پذیرفته می‌شود. */
	public function cleanup(): void {
		$d      = $this->database;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
		$ids    = $d->db->get_col( $d->db->prepare( 'SELECT id FROM ' . $d->table( 'exports' ) . ' WHERE created<%s', $cutoff ) );
		foreach ( $ids as $id ) {
			if ( ! preg_match( '/^[a-f0-9-]{36}$/D', $id ) ) {
				continue; }
			$dir = $this->root() . '/' . $id;
			if ( is_dir( $dir ) ) {
				$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST );
				foreach ( $iterator as $file ) {
					$file->isDir() && ! $file->isLink() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() ); }
				rmdir( $dir );
			}
			$d->db->delete( $d->table( 'exports' ), array( 'id' => $id ) );
		}
		$reports = $d->db->get_col( $d->db->prepare( 'SELECT id FROM ' . $d->table( 'reports' ) . ' WHERE created<%s', $cutoff ) );
		foreach ( $reports as $id ) {
			$d->db->delete( $d->table( 'rows' ), array( 'report_id' => $id ) );
			$d->db->delete( $d->table( 'reports' ), array( 'id' => $id ) ); }
	}
}
