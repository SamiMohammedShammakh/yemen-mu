# نظام الخطط السنوية والمتابعة
### جمعية المهندسين اليمنيين في تركيا — YEMENLİ MÜHENDİSLER DERNEĞİ

منصة عربية (من اليمين إلى اليسار، تعمل على الجوال والحاسوب) لإدارة الخطة السنوية لكل منصب في الجمعية، وتقسيمها إلى أربعة أرباع، ومتابعة تنفيذها بمؤشرات قابلة للقياس وأدلة يتحقق منها مسؤول التخطيط، مع تنزيل كل خطة ونتائجها ملفاتٍ مستقلة (PDF / DOCX / XLSX / CSV / ZIP).

## المتطلبات

| المكوّن | الإصدار |
|---|---|
| PHP | 8.3 أو أحدث، مع الامتدادات: `pdo_sqlite` أو `pdo_mysql`، `mbstring`، `intl`، `gd`، `zip` |
| Composer | 2.x |
| قاعدة البيانات | SQLite (افتراضي) أو MySQL 8 / MariaDB 10.6 |
| خادم الويب | Apache أو Nginx يشير إلى مجلد `public/` |

لا يحتاج المشروع إلى Node أو بناء واجهات: الأنماط في `public/css/app.css` والخط Cairo في `public/fonts`.

## التثبيت

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate

# SQLite (الأبسط)
touch database/database.sqlite
# أو MySQL: عدّل DB_CONNECTION=mysql و DB_HOST و DB_DATABASE و DB_USERNAME و DB_PASSWORD في .env

php artisan migrate --force
php artisan db:seed --force          # المناصب السبعة + قواعد الحالات + حساب مدير النظام
php artisan serve                    # للتجربة المحلية: http://localhost:8000
```

عيّن في `.env` قبل التهيئة: `ADMIN_EMAIL` و`ADMIN_PASSWORD` لحساب مدير النظام التقني، و`APP_TIMEZONE=Europe/Istanbul`، و`APP_URL`.

أضف المهمة المجدولة لتوليد التنبيهات يوميًا:

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

### بيانات تجريبية

```bash
php artisan migrate:fresh --seed --seeder=DemoSeeder
```

أو انسخ القاعدة التجريبية الجاهزة المرفقة: `cp database/demo.sqlite database/database.sqlite`.

تُنشئ حسابًا لكل منصب (كلمة المرور `Demo@2026`)، وسنتي 2026 و2027، وخمس خطط 2026 معتمدة ونشطة، وتحديثات للشؤون الهندسية تطابق المثال في المواصفات (12 برنامجًا سنويًا، 4 معتمدة حتى نهاية الربع الثاني ← 80% مقابل مستهدف النصف الأول و33.3% مقابل المستهدف السنوي)، وإقفال الربع الأول.

| المنصب | البريد |
|---|---|
| الرئيس | president@ymd-tr.org |
| نائب الرئيس | vp@ymd-tr.org |
| أمين السر | secretary@ymd-tr.org |
| مسؤول الشؤون المالية والإدارية | finance@ymd-tr.org |
| مسؤول التخطيط والمتابعة | planning@ymd-tr.org |
| مسؤول العلاقات العامة والإعلام | media@ymd-tr.org |
| مسؤول الشؤون الهندسية | engineering@ymd-tr.org |
| مدير النظام التقني | admin@ymd-tr.org — كلمة المرور `ChangeMe!2026` |

> غيّر كل كلمات المرور قبل التشغيل الفعلي، ولا تشغّل `DemoSeeder` على قاعدة الإنتاج.

## التشغيل على الإنتاج

```bash
APP_ENV=production APP_DEBUG=false
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

الملفات المرفوعة والملفات المُصدَّرة تُحفظ في `storage/app/private` (خارج `public`)، ولا تُخدم إلا عبر مسارات تتحقق من الصلاحية. خذ نسخة احتياطية دورية من قاعدة البيانات ومن `storage/app/private`.

## الاختبارات

```bash
php vendor/bin/phpunit --testdox
```

25 اختبارًا و340 تحققًا تغطي الحالات العشر المطلوبة. النتائج في [docs/05-test-results.md](docs/05-test-results.md).

## التوثيق

| الملف | المحتوى |
|---|---|
| [docs/01-api.md](docs/01-api.md) | توثيق الواجهات (JSON) |
| [docs/02-roles-permissions.md](docs/02-roles-permissions.md) | دليل إدارة المناصب والصلاحيات |
| [docs/03-new-year.md](docs/03-new-year.md) | دليل إنشاء سنة جديدة وإقفال الأرباع |
| [docs/04-downloads.md](docs/04-downloads.md) | دليل تنزيل الخطط والنتائج |
| [docs/05-test-results.md](docs/05-test-results.md) | نتائج اختبارات الصلاحيات والحساب والتنزيل |
| [docs/06-database.md](docs/06-database.md) | جداول قاعدة البيانات وقواعد الحساب |
| `database/schema.sql` | مخطط قاعدة البيانات كاملًا |

## بنية الشيفرة

```
app/Support/Access.php           قواعد الصلاحيات (مصدر واحد لكل الصفحات وواجهات API والبحث والتنزيل)
app/Support/Workspace.php        سياق مساحة العمل والسنة والربع
app/Services/Calculator.php      حساب الإنجاز حسب نوع المؤشر (تراكمي/دوري/نقطة زمنية)
app/Services/Results.php         مصدر موحّد للنتائج: لقطة الإقفال للربع المغلق، والحساب المباشر للمفتوح
app/Services/PlanValidator.php   فحص اكتمال الخطة قبل الإرسال
app/Services/PlanWorkflow.php    دورة الاعتماد وحفظ النسخ
app/Services/ChangeRequestService.php  طلبات تعديل الخطة المعتمدة ونتائج الأرباع المغلقة
app/Services/ProgressService.php التحديثات والأدلة والتحقق ونقل المهام
app/Services/QuarterService.php  إقفال الأرباع ولقطاتها
app/Services/Export/*            بناء التقارير وعارضات PDF/DOCX/XLSX/CSV وحزمة ZIP
resources/views/*                الشاشات (Blade، RTL)
tests/Feature/*                  اختبارات القبول
```
