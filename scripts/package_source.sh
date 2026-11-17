#!/usr/bin/env bash
# بستهٔ سورس مستند را بدون وابستگی‌های نصب‌شده و فایل‌های خصوصی آزمون می‌سازد.
set -euo pipefail
cd "$(dirname "$0")/.."
rm -rf .qa/source-package
mkdir -p .qa/source-package/sales-calculator-source/assets output
root=.qa/source-package/sales-calculator-source
cp sales-calculator.php uninstall.php README.md CHANGELOG.md CLAUDE.md .gitignore composer.json composer.lock package.json package-lock.json vite.config.ts tsconfig.json tailwind.config.js postcss.config.js eslint.config.js phpunit.xml phpcs.xml phpstan.neon components.json "$root/"
if [[ -f output/QA-REPORT.md ]]; then
    cp output/QA-REPORT.md "$root/"
elif [[ -f QA-REPORT.md ]]; then
    cp QA-REPORT.md "$root/"
fi
cp -R src ui tests scripts tools assets/fonts "$root/"
mv "$root/fonts" "$root/assets/fonts"
rm -f output/sales-calculator-source.zip
( cd .qa/source-package && zip -q -r ../../output/sales-calculator-source.zip sales-calculator-source -x '*.DS_Store' )
