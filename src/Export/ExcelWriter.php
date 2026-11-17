<?php
/**
 * خروجی اکسل واقعی با نوع عددی، متن امن و نوشتن جریانی ردیف‌ها روی دیسک.
 *
 * @package SalesCalculator
 */

declare(strict_types=1);
namespace SalesCalculator\Export;

use SalesCalculator\Brand;

/**
 * نسخهٔ ثابت گزارش را به کتاب کار XLSX با شیت خلاصه و جزئیات می‌نویسد.
 *
 * فایل مستقیماً با ZipArchive و XML استاندارد OOXML ساخته می‌شود؛ بنابراین به
 * کتابخانهٔ صفحه‌گسترده وابسته نیست، روی همهٔ نسخه‌های PHP 8 اجرا می‌شود و
 * حافظهٔ مصرفی مستقل از تعداد ردیف‌ها است.
 */
final class ExcelWriter {
	/** بیشترین تعداد ردیف یک شیت اکسل. */
	private const MAX_ROWS = 1048576;
	/** بیشترین طول متن یک سلول اکسل. */
	private const MAX_TEXT = 32767;
	/** شمارهٔ سبک‌های تعریف‌شده در styles.xml. */
	private const STYLE_HEADER = 1;
	private const STYLE_NUMBER = 2;
	private const STYLE_WRAP   = 3;

	/** داده‌های نسخهٔ ثابت به شیت‌های خلاصه و جزئیات منتقل می‌شوند. */
	public function write( array $report, iterable $rows, string $path, string $temporary ): void {
		if ( ! class_exists( \ZipArchive::class ) ) {
			throw new \RuntimeException( 'افزونهٔ zip در PHP فعال نیست.' ); }
		if ( ! is_dir( $temporary ) && ! wp_mkdir_p( $temporary ) ) {
			throw new \RuntimeException( 'فضای موقت قابل نوشتن نیست.' ); }
		$details = $temporary . '/sheet2.xml';
		$last    = $this->writeDetails( $rows, $details );
		$zip     = new \ZipArchive();
		if ( $zip->open( $path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) !== true ) {
			throw new \RuntimeException( 'Cannot create XLSX' ); }
		try {
			$zip->addFromString( '[Content_Types].xml', $this->contentTypes() );
			$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>' );
			$zip->addFromString( 'docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>گزارش فروش محصولات</dc:title><dc:creator>' . self::xml( (string) get_bloginfo( 'name' ) ) . '</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate( 'Y-m-d\TH:i:s\Z' ) . '</dcterms:created></cp:coreProperties>' );
			$zip->addFromString( 'xl/workbook.xml', $this->workbook( $last ) );
			$zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>' );
			$zip->addFromString( 'xl/styles.xml', $this->styles() );
			$zip->addFromString( 'xl/worksheets/sheet1.xml', $this->summary( $report ) );
			$zip->addFile( $details, 'xl/worksheets/sheet2.xml' );
			if ( ! $zip->close() ) {
				throw new \RuntimeException( 'Cannot write XLSX' ); }
		} finally {
			if ( is_file( $details ) ) {
				unlink( $details ); }
		}
	}

	/** ردیف‌ها یکی‌یکی روی دیسک نوشته می‌شوند؛ شمارهٔ آخرین ردیف برمی‌گردد. */
	private function writeDetails( iterable $rows, string $file ): int {
		$handle = fopen( $file, 'wb' );
		if ( $handle === false ) {
			throw new \RuntimeException( 'فضای موقت قابل نوشتن نیست.' ); }
		try {
			$widths = array( 16, 16, 42, 40, 18, 18, 18, 25, 14 );
			fwrite( $handle, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheetViews><sheetView rightToLeft="1" workbookViewId="0"><pane xSplit="4" ySplit="1" topLeftCell="E2" activePane="bottomRight" state="frozen"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="30" customHeight="1"/>' . self::columns( $widths ) . '<sheetData>' );
			fwrite( $handle, self::row( 1, array( 'شناسه محصول', 'شناسه متغیر', 'محصول', 'ویژگی‌ها', 'تعداد فروش', 'مرجوعی', 'تعداد خالص', 'مبلغ خالص', 'وضعیت محصول' ), true ) );
			$line = 1;
			foreach ( $rows as $row ) {
				++$line;
				if ( $line > self::MAX_ROWS ) {
					throw new \RuntimeException( 'تعداد ردیف‌ها از ظرفیت یک شیت اکسل بیشتر است؛ بازه یا محصولات را محدود کنید.' ); }
				$cells = array(
					self::number( $row['product_id'] ),
					self::number( $row['variation_id'] ),
					(string) $row['title'],
					(string) $row['attributes'],
					self::number( $row['sold'] ),
					self::number( $row['returned'] ),
					self::number( $row['quantity'] ),
					self::number( $row['amount'] ),
					// محصول حذف‌شده همچنان در محاسبه هست؛ فقط برچسب می‌خورد.
					! empty( $row['deleted'] ) ? 'حذف‌شده' : 'فعال',
				);
				fwrite( $handle, self::row( $line, $cells ) );
			}
			fwrite( $handle, '</sheetData><autoFilter ref="A1:I' . $line . '"/></worksheet>' );
			return $line;
		} finally {
			fclose( $handle );
		}
	}

	/** شیت خلاصه با سربرگ رنگی و مقادیر عددی واقعی. */
	private function summary( array $report ): string {
		$f     = $report['filters'];
		$s     = $report['summary'];
		$pairs = array(
			array( 'گزارش فروش محصولات', Brand::custom() ? (string) Brand::get()['name'] : (string) get_bloginfo( 'name' ) ),
			array( 'از تاریخ', Format::date( $f['from'], $f['calendar'] ) ),
			array( 'تا تاریخ', Format::date( $f['to'], $f['calendar'] ) ),
			array( 'واحد پول', (string) ( $s['unit'] ?? $f['currency'] ) ),
			array( 'تعداد خالص', self::number( $s['quantity'] ) ),
			array( 'مبلغ خالص محصولات', self::number( $s['amount'] ) ),
			array( 'تعداد سفارش', self::number( $s['orders'] ) ),
			array( 'مرجوعی تخصیص‌نیافته', self::number( ltrim( (string) $s['unallocated'], '-' ) ) ),
			array( 'نسخهٔ داده‌ها', Format::dateTime( (string) $s['asOf'], (string) $f['calendar'], (string) $s['timezone'] ) ),
			array( 'منطقه زمانی', (string) $s['timezone'] ),
			array( 'دسته‌بندی', ( $s['categories'] ?? array() ) ? implode( '، ', (array) $s['categories'] ) : 'همه' ),
			array( 'مبنای گزارش', 'تاریخ پرداخت؛ پس از تخفیف و مرجوعی؛ بدون مالیات و ارسال' ),
		);
		$xml   = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheetViews><sheetView rightToLeft="1" tabSelected="1" workbookViewId="0"/></sheetViews><sheetFormatPr defaultRowHeight="28" customHeight="1"/>' . self::columns( array( 30, 75 ) ) . '<sheetData>';
		foreach ( $pairs as $i => $pair ) {
			$xml .= self::row( $i + 1, $pair, $i === 0 );
		}
		return $xml . '</sheetData></worksheet>';
	}

	/** یک ردیف؛ عدد با سبک هزارگان و متن به‌صورت رشتهٔ درون‌خطی ذخیره می‌شود. */
	private static function row( int $line, array $cells, bool $header = false ): string {
		$xml = '<row r="' . $line . '">';
		foreach ( array_values( $cells ) as $index => $value ) {
			$ref = chr( 65 + $index ) . $line;
			if ( is_float( $value ) || is_int( $value ) ) {
				$xml .= '<c r="' . $ref . '" s="' . ( $header ? self::STYLE_HEADER : self::STYLE_NUMBER ) . '"><v>' . self::numeric( $value ) . '</v></c>';
				continue;
			}
			// متن سفارش هرگز فرمول تفسیر نمی‌شود، چون نوع سلول صریحاً رشته است.
			$text = mb_substr( (string) $value, 0, self::MAX_TEXT );
			$xml .= '<c r="' . $ref . '" t="inlineStr" s="' . ( $header ? self::STYLE_HEADER : self::STYLE_WRAP ) . '"><is><t xml:space="preserve">' . self::xml( $text ) . '</t></is></c>';
		}
		return $xml . '</row>';
	}

	/** پهنای ستون‌ها به ترتیب از ستون A. */
	private static function columns( array $widths ): string {
		$xml = '<cols>';
		foreach ( $widths as $index => $width ) {
			$xml .= '<col min="' . ( $index + 1 ) . '" max="' . ( $index + 1 ) . '" width="' . $width . '" customWidth="1"/>';
		}
		return $xml . '</cols>';
	}

	/** مقدار اعشاری پایگاه داده بدون از دست رفتن دقت تا حد امکان به عدد تبدیل می‌شود. */
	private static function number( mixed $value ): float|int {
		$text = trim( (string) $value );
		if ( ! is_numeric( $text ) ) {
			return 0; }
		return preg_match( '/^-?\d+$/D', $text ) && strlen( ltrim( $text, '-' ) ) < 16 ? (int) $text : (float) $text;
	}

	/** عدد بدون نماد علمی و صفرهای اضافه در XML نوشته می‌شود. */
	private static function numeric( float|int $value ): string {
		if ( is_int( $value ) ) {
			return (string) $value; }
		$text = rtrim( rtrim( sprintf( '%.8F', $value ), '0' ), '.' );
		return $text === '-0' || $text === '' ? '0' : $text;
	}

	/** نویسه‌های غیرمجاز XML حذف و باقی متن escape می‌شود. */
	private static function xml( string $value ): string {
		$clean = preg_replace( '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value );
		return htmlspecialchars( $clean ?? '', ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8' );
	}

	/** نوع محتوای اجزای بسته. */
	private function contentTypes(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>';
	}

	/** کتاب کار با دو شیت و محدودهٔ فیلتر خودکار جزئیات. */
	private function workbook( int $last ): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView activeTab="0"/></bookViews><sheets><sheet name="خلاصه" sheetId="1" r:id="rId1"/><sheet name="جزئیات" sheetId="2" r:id="rId2"/></sheets><definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="1" hidden="1">\'جزئیات\'!$A$1:$I$' . $last . '</definedName></definedNames></workbook>';
	}

	/** فونت وزیر، سربرگ آبی، قالب عددی هزارگان و شکستن متن. */
	private function styles(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.########"/></numFmts><fonts count="2"><font><sz val="11"/><name val="Vazir"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Vazir"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF' . substr( Brand::mix( 'primary' ), 1 ) . '"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment vertical="center"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
	}
}
