<?php
/** واحدهای ایرانی به تومان تبدیل می‌شوند و سایر ارزها دست نمی‌خورند. */
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use SalesCalculator\Domain\Currency;
final class CurrencyTest extends TestCase {
    public function test_iranian_units_convert_to_toman():void {
        self::assertSame('0.1',Currency::factor('IRR'));
        self::assertSame('1',Currency::factor('irt'));
        self::assertSame('1000',Currency::factor('IRHT'));
        self::assertSame('100',Currency::factor('IRHR'));
        self::assertSame('تومان',Currency::label('IRR'));
    }
    public function test_other_currencies_are_untouched():void {
        self::assertNull(Currency::factor('USD'));
        self::assertSame('USD',Currency::label('usd'));
    }
}
