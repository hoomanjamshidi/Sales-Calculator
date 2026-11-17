<?php
/**
 * بارگذاری کتابخانه‌های ایزولهٔ بستهٔ نصب؛ فهرست کلاس‌ها هنگام ساخت تولید می‌شود.
 *
 * @package SalesCalculator
 */
declare(strict_types=1);
$sc_classmap = require __DIR__ . '/classmap.php';
spl_autoload_register(static function (string $class_name) use ($sc_classmap): void {
    if (!str_starts_with($class_name, 'SalesCalculatorVendor\\')) { return; }
    $path = $sc_classmap[$class_name] ?? null;
    if ($path !== null) { require_once __DIR__ . '/' . $path; }
});
// این دو فایل توابع عمومی داخلی کتابخانه‌ها را تعریف می‌کنند.
require_once __DIR__ . '/myclabs/deep-copy/src/DeepCopy/deep_copy.php';
require_once __DIR__ . '/mpdf/mpdf/src/functions.php';
