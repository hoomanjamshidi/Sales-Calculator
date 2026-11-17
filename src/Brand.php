<?php
/**
 * هویت بصری نسخهٔ افزونه؛ نسخهٔ اختصاصی مشتری با پوشهٔ brand/ در بسته فعال می‌شود.
 *
 * @package SalesCalculator
 */

declare(strict_types=1);
namespace SalesCalculator;

/**
 * پیکربندی برند را یک‌بار می‌خواند و مقادیر پیش‌فرض نسخهٔ عمومی را تکمیل می‌کند.
 */
final class Brand {
	/**
	 * پیکربندی کش‌شده.
	 *
	 * @var array<string,mixed>|null
	 */
	private static ?array $config = null;

	/** پیکربندی کامل؛ نسخهٔ عمومی همان ظاهر پیش‌فرض قبلی را دارد. */
	public static function get(): array {
		if ( self::$config !== null ) {
			return self::$config; }
		$defaults = array(
			'slug'       => '',
			'name'       => 'Sales Calculator',
			'name_en'    => 'Sales Calculator',
			'tagline'    => 'گزارش‌های فروش ووکامرس',
			'menu_title' => 'گزارش فروش محصولات',
			'logo'       => '',
			'mark'       => '',
			'colors'     => array(
				'primary'    => '#1E40AF',
				'secondary'  => '#16A34A',
				'background' => '',
				'foreground' => '#17243D',
			),
			'yarn'       => array(),
			'theme_font' => array(),
		);
		$file     = defined( 'SALES_CALCULATOR_DIR' ) ? SALES_CALCULATOR_DIR . 'brand/brand.php' : '';
		$custom   = $file !== '' && is_file( $file ) ? include $file : array();
		$config   = array_replace( $defaults, is_array( $custom ) ? $custom : array() );
		// رنگ‌ها جداگانه ادغام می‌شوند تا برند فقط رنگ‌های لازم را تعیین کند.
		$config['colors'] = array_replace( $defaults['colors'], (array) ( $custom['colors'] ?? array() ) );
		foreach ( $config['colors'] as $key => $hex ) {
			if ( $hex !== '' && ! preg_match( '/^#[0-9a-fA-F]{6}$/D', (string) $hex ) ) {
				$config['colors'][ $key ] = $defaults['colors'][ $key ]; }
		}
		$config['yarn'] = array_values( array_filter( (array) $config['yarn'], static fn( $hex ): bool => is_string( $hex ) && (bool) preg_match( '/^#[0-9a-fA-F]{6}$/D', $hex ) ) );
		self::$config   = $config;
		return $config;
	}

	/** آیا نسخهٔ اختصاصی مشتری است. */
	public static function custom(): bool {
		return self::get()['slug'] !== '';
	}

	/** رنگ برند با درصدی از سفید ترکیب می‌شود؛ برای پس‌زمینه‌های روشن در PDF و اکسل. */
	public static function mix( string $key, float $white = 0.0 ): string {
		$hex = (string) self::get()['colors'][ $key ];
		$out = '#';
		foreach ( array( 1, 3, 5 ) as $offset ) {
			$channel = hexdec( substr( $hex, $offset, 2 ) );
			$out    .= sprintf( '%02X', (int) round( $channel + ( 255 - $channel ) * $white ) );
		}
		return $out;
	}

	/** مسیر فایل دیسک یکی از تصاویر برند یا رشتهٔ خالی. */
	public static function path( string $key ): string {
		if ( ! defined( 'SALES_CALCULATOR_DIR' ) ) {
			return ''; }
		$name = (string) ( self::get()[ $key ] ?? '' );
		$file = SALES_CALCULATOR_DIR . 'brand/' . basename( $name );
		return $name !== '' && is_file( $file ) ? $file : '';
	}

	/** نشانی عمومی یکی از تصاویر برند یا رشتهٔ خالی. */
	public static function url( string $key ): string {
		return self::path( $key ) !== '' ? plugins_url( 'brand/' . basename( (string) self::get()[ $key ] ), SALES_CALCULATOR_FILE ) : '';
	}

	/**
	 * متغیرهای رنگی رابط به‌صورت سه‌تایی HSL، هم‌قالب توکن‌های FarsiUI.
	 *
	 * فقط رنگ‌های تعیین‌شده در برند بازنویسی می‌شوند؛ نسخهٔ عمومی چیزی برنمی‌گرداند.
	 */
	public static function css(): string {
		if ( ! self::custom() ) {
			return ''; }
		$c    = self::get()['colors'];
		$vars = array();
		if ( $c['primary'] !== '' ) {
			$p                         = self::hsl( $c['primary'] );
			$vars['primary']           = $p;
			$vars['ring']              = $p;
			$vars['accent']            = self::tint( $c['primary'], 94 );
			$vars['accent-foreground'] = self::tint( $c['primary'], 38 );
		}
		if ( $c['secondary'] !== '' ) {
			$vars['success'] = self::tint( $c['secondary'], 36 ); }
		if ( $c['background'] !== '' ) {
			$vars['background'] = self::hsl( $c['background'] );
			$vars['muted']      = self::hsl( $c['background'] ); }
		if ( $c['foreground'] !== '' ) {
			$vars['foreground']      = self::hsl( $c['foreground'] );
			$vars['card-foreground'] = self::hsl( $c['foreground'] ); }
		$css = '#sales-calculator{';
		foreach ( $vars as $name => $value ) {
			$css .= '--' . $name . ':' . $value . ';'; }
		$yarn = self::get()['yarn'];
		if ( $yarn ) {
			$css .= '--sc-yarn:linear-gradient(90deg,' . implode( ',', $yarn ) . ');'; }
		$font = self::fontFaces();
		if ( $font !== '' ) {
			$css .= '--font-estedad:"' . self::get()['theme_font']['family'] . '",Vazir;font-family:"' . self::get()['theme_font']['family'] . '",Vazir,Tahoma,sans-serif;'; }
		return $font . $css . '}';
	}

	/** فونت پوستهٔ سایت فقط وقتی فایلش روی همین وردپرس موجود است بارگذاری می‌شود. */
	private static function fontFaces(): string {
		$font = self::get()['theme_font'];
		if ( empty( $font['family'] ) || empty( $font['files'] ) || ! function_exists( 'get_theme_root' ) ) {
			return ''; }
		$css = '';
		foreach ( (array) $font['files'] as $weight => $relative ) {
			$relative = ltrim( str_replace( '..', '', (string) $relative ), '/' );
			if ( ! is_file( get_theme_root() . '/' . $relative ) ) {
				continue; }
			$css .= '@font-face{font-family:"' . preg_replace( '/[^A-Za-z0-9 _-]/', '', (string) $font['family'] ) . '";font-weight:' . (int) $weight . ';font-style:normal;font-display:swap;src:url("' . esc_url( get_theme_root_uri() . '/' . $relative ) . '") format("woff2")}';
		}
		return $css;
	}

	/** تبدیل hex به سه‌تایی «H S% L%». */
	private static function hsl( string $hex ): string {
		[ $h, $s, $l ] = self::toHsl( $hex );
		return round( $h ) . ' ' . round( $s ) . '% ' . round( $l ) . '%';
	}

	/** همان فام و اشباع با روشنایی دلخواه؛ برای پس‌زمینه‌های کم‌رنگ و متن روی آن‌ها. */
	private static function tint( string $hex, int $lightness ): string {
		[ $h, $s ] = self::toHsl( $hex );
		return round( $h ) . ' ' . round( $s ) . '% ' . $lightness . '%';
	}

	/** محاسبهٔ HSL استاندارد. */
	private static function toHsl( string $hex ): array {
		$r   = hexdec( substr( $hex, 1, 2 ) ) / 255;
		$g   = hexdec( substr( $hex, 3, 2 ) ) / 255;
		$b   = hexdec( substr( $hex, 5, 2 ) ) / 255;
		$max = max( $r, $g, $b );
		$min = min( $r, $g, $b );
		$l   = ( $max + $min ) / 2;
		if ( $max === $min ) {
			return array( 0.0, 0.0, $l * 100 ); }
		$d = $max - $min;
		$s = $l > 0.5 ? $d / ( 2 - $max - $min ) : $d / ( $max + $min );
		if ( $max === $r ) {
			$h = ( $g - $b ) / $d + ( $g < $b ? 6 : 0 );
		} elseif ( $max === $g ) {
			$h = ( $b - $r ) / $d + 2;
		} else {
			$h = ( $r - $g ) / $d + 4;
		}
		return array( $h * 60, $s * 100, $l * 100 );
	}
}
