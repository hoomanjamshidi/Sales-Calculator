# بستهٔ برند (نسخهٔ اختصاصی مشتری)

برای ساخت نسخهٔ اختصاصی یک مشتری، پوشه‌ای به نام `brands/<slug>/` بسازید و این فایل‌ها را در آن بگذارید:

- `brand.php` — پیکربندی برند (نمونهٔ زیر)
- `logo.png` — لوگوی افقی برای سربرگ رابط و PDF
- `mark.png` — نماد مربعی برای آیکن رابط

```php
<?php
defined( 'ABSPATH' ) || exit;

return array(
	'slug'       => 'acme',
	'name'       => 'نام فروشگاه',
	'name_en'    => 'Acme Store',
	'tagline'    => 'گزارش فروش فروشگاه',
	'menu_title' => 'گزارش فروش',
	'logo'       => 'logo.png',
	'mark'       => 'mark.png',
	'colors'     => array(
		'primary'    => '#1E40AF', // رنگ اصلی
		'secondary'  => '#16A34A', // رنگ دوم (وضعیت موفق)
		'background' => '#F3F4F6', // پس‌زمینه
		'foreground' => '#17243D', // رنگ متن
	),
	// اختیاری: رنگ‌های نوار تزئینی بالای صفحه و زیر عنوان PDF.
	'yarn'       => array(),
	// اختیاری: فونت پوستهٔ سایت (فقط اگر فایل آن در پوستهٔ همان سایت باشد بارگذاری می‌شود).
	'theme_font' => array(),
);
```

سپس بسته را بسازید:

```bash
BRAND=acme scripts/build_release.sh   # output/sales-calculator-acme-<version>.zip
```

بدون `BRAND`، نسخهٔ عمومی ساخته می‌شود. نامک، جدول‌ها و داده‌ها در همهٔ نسخه‌ها یکی است.
