<?php
/**
 * اصلاح نام پویاي کلاس‌های HTML در نسخهٔ ایزولهٔ mPDF.
 * php-scoper ارجاع‌های رشته‌ای به Mpdf\Tag را تغییر نمی‌دهد؛ بدون این اصلاح
 * متن PDF تولید می‌شود اما هیچ تگ و استایلی اعمال نخواهد شد.
 */
declare( strict_types=1 );

$from = <<<'SOURCE'
'Mpdf\Tag\\'
SOURCE;
$to = <<<'SOURCE'
'SalesCalculatorVendor\Mpdf\Tag\\'
SOURCE;

foreach ( array( 'lib/mpdf/mpdf/src/Tag.php', 'lib/mpdf/mpdf/src/Tag/Tag.php' ) as $path ) {
	$content = file_get_contents( $path );
	if ( false === $content || substr_count( $content, $from ) !== 1 ) {
		throw new RuntimeException( 'Unexpected scoped mPDF source: ' . $path );
	}
	file_put_contents( $path, str_replace( $from, $to, $content ) );
}
