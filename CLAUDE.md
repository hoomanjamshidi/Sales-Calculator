# Sales Calculator — راهنمای کار روی پروژه

افزونهٔ وردپرس/ووکامرس برای گزارش فروش محصولات و متغیرها، فارسی و راست‌به‌چپ، با خروجی اکسل و PDF. یک نسخهٔ عمومی و نسخه‌های اختصاصی مشتری از همین کد ساخته می‌شوند.

## قواعد پایه

- **PHP ۸٫۰ تا ۸٫۵.** از ویژگی‌های بعد از ۸٫۰ استفاده نکن: `readonly`، `enum`، `never`، `new` در مقدار پیش‌فرض، فراخوانی `foo(...)`. ارتقای سازنده (constructor promotion) مجاز است. `composer.json` پلتفرم را روی `8.0.2` قفل کرده؛ وابستگی تازه باید با ۸٫۰ تا ۸٫۵ سازگار باشد.
- **وابستگی PHP فقط mPDF است.** اکسل با `src/Export/ExcelWriter.php` و ZipArchive نوشته می‌شود. PhpSpreadsheet عمداً حذف شد، چون نسخهٔ سازگار با ۸٫۰ از ۸٫۵ پشتیبانی نمی‌کند.
- **شناسه‌ها انگلیسی، توضیح‌ها و پیام‌ها فارسی.** سبک کد WordPress Coding Standards است (`phpcs.xml`).
- **هیچ‌وقت `wp_date()` یا `date_i18n()` برای تاریخ میلادی به کار نبر.** افزونه‌های شمسی‌ساز (پارسی‌دیت، WP-Jalali) خروجی آن‌ها را شمسی می‌کنند. «امروز» از `ReportFilter::today()` می‌آید و تاریخ سفارش از timestamp. رابط هم «امروز» را با `safeToday()` در `ui/src/dates.ts` کنترل می‌کند.
- قرارداد API همیشه تاریخ میلادی `Y-m-d` است. تقویم شمسی/میلادی فقط برای نمایش است.

## ساختار

- `sales-calculator.php`: نقطهٔ ورود. ثابت‌ها با پیشوند `SALES_CALCULATOR_` تعریف می‌شوند؛ بررسی افزونه‌های PHP و وجود ووکامرس اینجاست.
- `src/Domain`:
  - `SalesCalculator`: تبدیل سفارش به اقلام.
  - `ReportFilter`: اعتبارسنجی ورودی و `today()`.
  - `Currency`: تبدیل تومانی.
- `src/Application`:
  - `SyncService`: نمایه‌سازی.
  - `ReportService`: نسخهٔ ثابت گزارش.
  - `Queue`: Action Scheduler.
- `src/Infrastructure`:
  - `Database`: قفل و تراکنش.
  - `Schema`: جدول‌ها.
  - `IndexRepository`
  - `Catalog`: دسته‌بندی‌ها و نشان «حذف‌شده».
- `src/Export`:
  - `ExcelWriter`
  - `PdfWriter`
  - `ExportService`
  - `Format`: تاریخ و عدد فارسی.
- `src/Http/Controller.php`: REST (`sales-calculator/v1`) و دانلود از `admin-post.php`.
- `src/Brand.php` و `brands/<slug>/`: هویت بصری نسخهٔ مشتری (نام، رنگ، لوگو).
- `ui/src`: رابط React + TypeScript + Tailwind 3 که با Vite به `assets/build/app.{js,css}` ساخته می‌شود.
- `lib/`: کتابخانه‌های پیشونددار (`SalesCalculatorVendor\`). خروجی ساخت است؛ دستی ویرایش نکن.

## منطق مهم دامنه

- مبنای گزارش تاریخ پرداخت است. وضعیت‌های شمرده‌شده `wc_get_is_paid_statuses()` به‌علاوهٔ `refunded` است (فیلتر `sales_calculator_counted_statuses`). مبلغ پس از تخفیف و مرجوعی و بدون مالیات و ارسال حساب می‌شود.
- گزارش از نمایهٔ خود افزونه (`sc_items`، `sc_daily`) ساخته می‌شود، نه مستقیم از سفارش‌ها. هر تغییر سفارش از راه هوک‌ها یک کار صف می‌سازد.
- **محصول حذف‌شده در محاسبه می‌ماند.** شناسهٔ آن از متای قلم خوانده می‌شود: `wc_get_order_item_meta($id, '_product_id')`، چون پس از حذف، `get_product_id()` صفر برمی‌گرداند و `get_meta()` کلیدهای داخلی را نمی‌دهد. `Catalog::markDeleted()` نشان «حذف‌شده» را برای رابط، اکسل و PDF می‌گذارد.
- قلم دستیِ بدون محصول شناسهٔ مصنوعی `DETACHED_PRODUCT_BASE + item_id` می‌گیرد (کمتر از ۲^۵۳).
- **فیلتر دسته** زیردسته‌ها را هم شامل می‌شود. جدول `sc_categories` دستهٔ محصولات فروخته‌شده را هنگام نمایه‌سازی نگه می‌دارد تا محصول حذف‌شده در فیلتر دسته بماند.
- **تومان:** IRR ÷۱۰، IRHR ×۱۰۰، IRHT ×۱۰۰۰ (`Currency`). تبدیل هنگام ساخت نسخهٔ ثابت گزارش انجام می‌شود و `summary.unit` برچسب واحد است. فیلتر `sales_calculator_toman` تبدیل را خاموش می‌کند.
- **صف:** گزارش و خروجی در Action Scheduler ساخته می‌شوند. اگر صف هاست اجرا نشود (loopback یا WP-Cron خراب)، `Controller::nudge()` پس از ۵ ثانیه کار سررسیدهٔ همان شناسه را در درخواست وضعیت اجرا می‌کند. فقط کاری اجرا می‌شود که آرگومانش همان شناسه است، چون جست‌وجوی AS شناسهٔ کار را هم تطبیق می‌دهد.
- هوک `init` در همهٔ درخواست‌های سایت اجرا می‌شود. در آن هرگز قفل نگیر و همگام‌سازی را مستقیم اجرا نکن؛ فقط کار `start` را در صف بگذار.
- **تغییر ساختار جدول یا قالب نمایه:** `Schema::VERSION` را بالا ببر. ارتقا خودکار نمایه را از سفارش‌ها بازسازی می‌کند.

## رابط کاربری

- کامپوننت‌ها از [طرحک](https://tarhak.ir) هستند: `npx @tarhak/cli@latest add <slug>`. همه در `ui/src/components/` و سازگارشده‌اند (date-field دو تقویمی است، save-button دکمهٔ وضعیت‌دار، toast-stack با `useToasts`). فهرست کامل از MCP به نشانی `https://tarhak.ir/api/mcp` گرفته می‌شود.
- توکن‌های رنگی FarsiUI (`--primary`، `--card`، …) فقط داخل `#sales-calculator` در `ui/src/style.css` تعریف شده‌اند. Tailwind با `important: '#sales-calculator'` و بدون preflight است. reset‌ها با `:where()` نوشته شده‌اند تا کلاس‌های Tailwind همیشه برنده باشند.
- در PDF (mPDF) سبک کلاس روی عناصر داخل سلول جدول و چند کلاس روی یک عنصر اعمال نمی‌شود؛ از سبک درون‌خطی استفاده کن. `SetDirectionality('rtl')` لازم است.

## دستورها

```bash
composer install && npm ci
vendor/bin/phpunit
vendor/bin/phpcs --standard=phpcs.xml        # شامل PHPCompatibility برای ۸٫۰ به بالا
vendor/bin/phpstan analyse --memory-limit=2G  # stubهای وردپرس/ووکامرس، دامنهٔ PHP ۸٫۰ تا ۸٫۵
npm run lint && npx tsc --noEmit && npm test
npm run build
scripts/build_release.sh                       # output/sales-calculator-<version>.zip
BRAND=<slug> scripts/build_release.sh            # output/sales-calculator-<slug>-<version>.zip
```

پس از هر تغییر هر دو بسته را دوباره بساز. php-scoper جدا در `tools/php-scoper` نصب می‌شود.

## آزمون سرتاسری (Docker)

- سایت انتشار `scqa-release` روی `http://127.0.0.1:8098` با وردپرس ۷٫۱، ووکامرس ۱۱ و HPOS است و پایگاه دادهٔ آن `scqa-db-1` است. اسکریپت‌های کمکی در `.qa/release-private` قرار دارند و داخل کانتینر در `/sc-private` دیده می‌شوند:
  - `upgrade.php`: نصب ZIP؛ باید با `docker exec -u www-data` اجرا شود.
  - `session.php`: کوکی ورود و nonce برای کاربر آزمایشی `scadmin`.
  - `runq.php`: اجرای صف.
  - `e2e.py`: کل جریان REST و دانلود.
- داخل این کانتینر loopback به `127.0.0.1:8098` وصل نمی‌شود و WP-Cron خودبه‌خود اجرا نمی‌شود. این مشکل محیط آزمون است، نه افزونه. برای شبیه‌سازی cron سرور از `realcron.php` استفاده کن.
- برای بازبینی ظاهری PDF: `pdftoppm -r 110 -png file.pdf out`. اکسل را با openpyxl بازخوانی کن.
- نتیجهٔ آزمون‌ها را در `output/QA-REPORT.md` ثبت کن.
