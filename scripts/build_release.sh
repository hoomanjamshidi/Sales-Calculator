#!/usr/bin/env bash
# ساخت تکرارپذیر ZIP افزونه با کتابخانه‌های ایزوله و بدون ابزارهای توسعه.
set -euo pipefail
cd "$(dirname "$0")/.."
npm ci --no-audit --no-fund >/dev/null
npm run build
composer install --no-interaction >/dev/null
# php-scoper ابزار ساخت است و به PHP 8.2+ نیاز دارد؛ جدا از وابستگی‌های افزونه (PHP 8.0+) نصب می‌شود.
composer install --no-interaction --working-dir=tools/php-scoper >/dev/null
VERSION=$(sed -n "s/^define( 'SALES_CALCULATOR_VERSION', '\(.*\)' );$/\1/p" sales-calculator.php)
rm -rf .qa/prod .qa/scoped
mkdir -p .qa/prod
cp composer.json composer.lock .qa/prod/
composer install --no-dev --no-interaction --working-dir=.qa/prod --optimize-autoloader >/dev/null
php -d memory_limit=1G tools/php-scoper/vendor/bin/php-scoper add-prefix .qa/prod/vendor --output-dir=.qa/scoped --prefix=SalesCalculatorVendor --force --no-config > .qa/scoper.log
rm -rf lib
mkdir lib
rsync -a --exclude='composer' --exclude='autoload.php' --exclude='ttfonts' --exclude='test' --exclude='tests' --exclude='docs' --exclude='samples' .qa/scoped/ lib/
cp scripts/scoped-autoload.php lib/autoload.php
php scripts/fix_scoped_mpdf.php
php -r '
$root=realpath(".qa/prod/vendor");
$classes=require "$root/composer/autoload_classmap.php";
$map=[];
foreach($classes as $name=>$path){
 if(str_starts_with($name,"SalesCalculator\\")||str_starts_with($name,"Composer\\Autoload\\")||$name==="Composer\\InstalledVersions")continue;
 if(!str_starts_with($path,$root."/"))continue;
 $relative=substr($path,strlen($root)+1);
 if(!is_file("lib/".$relative))continue;
 $map["SalesCalculatorVendor\\".$name]=$relative;
}
file_put_contents("lib/classmap.php","<?php\n/** Generated class map for private dependencies. */\nreturn ".var_export($map,true).";\n");
echo "Scoped classes: ".count($map)."\n";
'
mkdir -p output
# BRAND=<slug> نسخهٔ اختصاصی مشتری را با بستهٔ brands/<slug> می‌سازد؛ بدون آن نسخهٔ عمومی ساخته می‌شود.
BRAND="${BRAND:-}"
NAME="sales-calculator-$VERSION"
if [[ -n "$BRAND" ]]; then
  [[ -f "brands/$BRAND/brand.php" ]] || { echo "Unknown brand: $BRAND" >&2; exit 1; }
  NAME="sales-calculator-$BRAND-$VERSION"
fi
rm -f "output/$NAME.zip"
rm -rf .qa/package
mkdir -p .qa/package/sales-calculator
cp sales-calculator.php uninstall.php README.md CHANGELOG.md .qa/package/sales-calculator/
cp -R src assets lib .qa/package/sales-calculator/
if [[ -n "$BRAND" ]]; then
  cp -R "brands/$BRAND" .qa/package/sales-calculator/brand
  BRAND_NAME=$(php -r 'define("ABSPATH","/");echo (require $argv[1])["name"];' "brands/$BRAND/brand.php")
  # نام نمایشی در فهرست افزونه‌ها؛ نامک و جدول‌ها یکی می‌مانند تا به‌روزرسانی و داده‌ها پیوسته باشند.
  php -r '$f=$argv[1];file_put_contents($f,preg_replace("/^ \* Plugin Name: .*$/m"," * Plugin Name: ".$argv[2]." | گزارش فروش محصولات",file_get_contents($f),1));' .qa/package/sales-calculator/sales-calculator.php "$BRAND_NAME"
fi
( cd .qa/package && zip -q -r "../../output/$NAME.zip" sales-calculator -x '*.DS_Store' )
( cd output && shasum -a 256 sales-calculator-*.zip > SHA256SUMS )
