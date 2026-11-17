<?php
/**
 * ساخت PDF فارسی چندصفحه‌ای با فونت واقعی وزیر و جدول دارای سربرگ تکراری.
 *
 * @package SalesCalculator
 */

declare(strict_types=1);
namespace SalesCalculator\Export;

use SalesCalculator\Brand;

/**
 * هر بخش گزارش را با جدول راست‌به‌چپ و فونت تعبیه‌شدهٔ وزیر به PDF تبدیل می‌کند.
 */
final class PdfWriter {
	/** هر بخش حداکثر ۲۰۰۰ ردیف دارد تا مصرف حافظه مستقل از کل گزارش باشد. */
	public function write( array $report, array $rows, string $path, string $temporary, int $part = 1 ): void {
		// بستهٔ انتشار mPDF را با پیشوند اختصاصی بارگذاری می‌کند.
		$pdf_class = ( defined( 'SALES_CALCULATOR_SCOPED' ) && SALES_CALCULATOR_SCOPED ? 'SalesCalculatorVendor\\' : '' ) . 'Mpdf\\Mpdf';
		$pdf       = new $pdf_class(
			array(
				'mode'           => 'utf-8',
				'format'         => 'A4-L',
				'tempDir'        => $temporary,
				'fontDir'        => array( SALES_CALCULATOR_DIR . 'assets/fonts' ),
				'fontdata'       => array(
					'vazir' => array(
						'R'      => 'Vazir-Regular.ttf',
						'B'      => 'Vazir-Bold.ttf',
						'useOTL' => 0xFF,
					),
				),
				'default_font'   => 'vazir',
				'directionality' => 'rtl',
				'margin_left'    => 12,
				'margin_right'   => 12,
				'margin_top'     => 15,
				'margin_bottom'  => 17,
			)
		);
		// سازندهٔ mPDF گزینهٔ directionality را بازنشانی می‌کند؛ بدون این فراخوانی جدول و عنوان چپ‌به‌راست چیده می‌شوند.
		$pdf->SetDirectionality( 'rtl' );
		$pdf->SetTitle( 'گزارش فروش محصولات' );
		$pdf->SetAuthor( get_bloginfo( 'name' ) );
		$pdf->SetHTMLFooter( '<div style="font-size:8px;color:#9aa3b2;text-align:center;border-top:0.5px solid #e8ebf1;padding-top:4px">گزارش فروش محصولات • بخش ' . Format::number( $part ) . ' • صفحهٔ {PAGENO} از {nbpg}</div>' );
		$f     = $report['filters'];
		$s     = $report['summary'];
		$range = Format::date( $f['from'], $f['calendar'] ) . ' تا ' . Format::date( $f['to'], $f['calendar'] );
		$cats  = (array) ( $s['categories'] ?? array() );
		// گزارش‌های پیش از تبدیل تومانی واحد ذخیره‌شده ندارند و همان کد ارز را نشان می‌دهند.
		$unit = (string) ( $s['unit'] ?? $f['currency'] );
		// رنگ‌ها از برند نسخه می‌آیند؛ نسخهٔ عمومی همان آبی قبلی را دارد.
		$primary = Brand::mix( 'primary' );
		$text    = Brand::mix( 'foreground' );
		$tint    = Brand::mix( 'primary', 0.92 );
		$soft    = Brand::mix( 'primary', 0.97 );
		$muted   = '#7a8599';
		$line    = '#e8ebf1';
		$html    = '<style>body{font-family:vazir;color:' . $text . ';font-size:9.5pt;direction:rtl;text-align:right}'
			. 'h1{color:' . $text . ';font-size:20pt;font-weight:bold;margin:0}'
			. '.eyebrow{color:' . $primary . ';font-size:8.5pt;font-weight:bold}.muted{color:' . $muted . ';font-size:8.5pt}'
			. '.chip{background:' . $tint . ';color:' . $primary . ';font-size:8.5pt;padding:3px 9px;border-radius:9px}'
			. '.kpi{border-collapse:separate;border-spacing:5px 0;width:100%;margin:12px 0 14px}'
			. '.kpi td{background:' . $soft . ';border:1px solid ' . $tint . ';border-radius:10px;padding:10px 14px;vertical-align:top}'
			. '.kpi .label{color:' . $muted . ';font-size:8pt}.kpi .value{font-size:16pt;font-weight:bold;color:' . $text . '}.kpi .unit{font-size:8pt;color:' . $muted . '}'
			. '.grid{border-collapse:collapse;width:100%;table-layout:fixed}'
			. '.grid th{background:' . $tint . ';color:' . $primary . ';font-size:8.5pt;font-weight:bold;padding:9px 10px;text-align:right;border-bottom:1.5px solid ' . $primary . '}'
			. '.grid td{padding:8px 10px;border-bottom:0.6px solid ' . $line . ';vertical-align:middle}'
			. '.grid tr.alt td{background:' . $soft . '}'
			. '.grid .c{text-align:center}.grid .n{color:' . $muted . ';font-size:8pt;text-align:center}'
			. '.grid .title{font-weight:bold}.grid .attr{color:' . $muted . ';font-size:8pt}'
			. '.grid .amount{text-align:left;font-weight:bold}.grid .ret{color:#d03a4c}.grid .zero{color:#b6bdc9}'
			. '.grid tr.total td{background:' . $tint . ';font-weight:bold;border-top:1.5px solid ' . $primary . ';border-bottom:0}'
			. '.yarn td{height:1.6mm;padding:0;border:0;font-size:1pt;line-height:1pt}</style>';
		$logo    = Brand::path( 'logo' );
		$store   = Brand::custom() ? (string) Brand::get()['name'] : (string) get_bloginfo( 'name' );
		$html   .= '<table style="width:100%;border-collapse:collapse"><tr><td style="vertical-align:middle;padding:0"><div class="eyebrow">' . Format::html( $store ) . '</div><h1>گزارش فروش محصولات</h1>'
			. '<div style="margin-top:4px;color:' . $muted . ';font-size:9pt">بازه: <b style="color:' . $primary . '">' . Format::html( $range ) . '</b> &nbsp;•&nbsp; واحد پول: <b style="color:' . $primary . '">' . Format::html( $unit ) . '</b> &nbsp;•&nbsp; دسته‌بندی: <b style="color:' . $primary . '">' . Format::html( $cats ? implode( '، ', $cats ) : 'همه' ) . '</b> &nbsp;•&nbsp; بخش ' . Format::number( $part ) . '</div></td>';
		if ( $logo !== '' ) {
			$html .= '<td style="padding:0;text-align:left;width:40mm;vertical-align:middle"><img src="' . Format::html( $logo ) . '" style="height:18mm"></td>'; }
		$html .= '</tr></table>';
		$yarn  = Brand::get()['yarn'];
		if ( $yarn ) {
			$html .= '<table class="yarn" style="width:100%;margin:8px 0 0"><tr>';
			foreach ( $yarn as $color ) {
				$html .= '<td style="background:' . $color . '">&nbsp;</td>'; }
			$html .= '</tr></table>';
		}
		$cards = array(
			array( 'مبلغ خالص محصولات', Format::number( $s['amount'] ), $unit ),
			array( 'تعداد خالص فروخته‌شده', Format::number( $s['quantity'] ), 'عدد' ),
			array( 'سفارش‌های مرتبط', Format::number( $s['orders'] ), 'سفارش' ),
			array( 'مرجوعی', Format::number( $s['returned'] ?? 0 ), 'عدد' ),
		);
		$html .= '<table class="kpi"><tr>';
		foreach ( $cards as $i => $card ) {
			// کارت مبلغ با رنگ برند پررنگ می‌شود تا عدد اصلی گزارش اول دیده شود.
			$lead = $i === 0;
			// mPDF سبک کلاس را روی عناصر درون سلول اعمال نمی‌کند؛ سبک درون‌خطی لازم است.
			$html .= '<td style="background:' . ( $lead ? $primary : $soft ) . ';border:1px solid ' . ( $lead ? $primary : $tint ) . ';border-radius:10px;padding:10px 14px"><div style="color:' . ( $lead ? '#ffffff' : $muted ) . ';font-size:8pt">' . $card[0] . '</div><div style="font-size:17pt;font-weight:bold;color:' . ( $lead ? '#ffffff' : $text ) . '">' . $card[1] . ' <span style="font-size:8pt;font-weight:normal;color:' . ( $lead ? '#ffffff' : $muted ) . '">' . Format::html( (string) $card[2] ) . '</span></div></td>'; }
		$html .= '</tr></table>';
		$html .= '<table class="grid" dir="rtl"><thead><tr><th width="5%" class="c">#</th><th width="41%">محصول / متغیر</th><th width="11%" class="c">فروش</th><th width="11%" class="c">مرجوعی</th><th width="11%" class="c">خالص</th><th width="21%" style="text-align:left">مبلغ خالص (' . Format::html( $unit ) . ')</th></tr></thead><tbody>';
		if ( ! $rows ) {
			$html .= '<tr><td colspan="6" class="c muted" style="padding:24px">در این بازه فروشی ثبت نشده است.</td></tr>'; }
		$index = ( $part - 1 ) * 2000;
		foreach ( $rows as $row ) {
			++$index;
			$returned = (float) $row['returned'] > 0;
			$html    .= '<tr' . ( $index % 2 === 0 ? ' class="alt"' : '' ) . '><td class="n">' . Format::number( $index ) . '</td>'
				. '<td><div style="font-weight:bold">' . Format::html( $row['title'] ) . ( ! empty( $row['deleted'] ) ? ' <span style="background:#fde8eb;color:#c8323f;font-size:7.5pt;font-weight:bold">&nbsp;حذف‌شده&nbsp;</span>' : '' ) . '</div>' . ( $row['attributes'] !== '' ? '<div style="color:' . $muted . ';font-size:8pt">' . Format::html( $row['attributes'] ) . '</div>' : '' ) . '</td>'
				. '<td class="c">' . Format::number( $row['sold'] ) . '</td>'
				. '<td class="c" style="color:' . ( $returned ? '#d03a4c;font-weight:bold' : '#b6bdc9' ) . '">' . Format::number( $row['returned'] ) . '</td>'
				. '<td class="c">' . Format::number( $row['quantity'] ) . '</td>'
				. '<td class="amount">' . Format::number( $row['amount'] ) . '</td></tr>';
		}
		if ( $rows ) {
			$html .= '<tr class="total"><td></td><td>جمع کل گزارش</td><td class="c">' . Format::number( $s['sold'] ?? 0 ) . '</td><td class="c">' . Format::number( $s['returned'] ?? 0 ) . '</td><td class="c">' . Format::number( $s['quantity'] ) . '</td><td class="amount">' . Format::number( $s['amount'] ) . '</td></tr>'; }
		$html .= '</tbody></table>';
		$html .= '<p class="muted" style="margin-top:10px">مبنا: تاریخ پرداخت؛ پس از تخفیف و مرجوعی، بدون مالیات و ارسال. مرجوعی تخصیص‌نیافته: ' . Format::number( abs( (float) $s['unallocated'] ) ) . ' • نسخهٔ داده‌ها: ' . Format::html( Format::dateTime( (string) $s['asOf'], (string) $f['calendar'], (string) $s['timezone'] ) ) . ' • منطقهٔ زمانی: ' . Format::html( $s['timezone'] ) . '</p>';
		$pdf->WriteHTML( '<div dir="rtl">' . $html . '</div>' );
		$pdf->Output( $path, 'F' );
	}
}
