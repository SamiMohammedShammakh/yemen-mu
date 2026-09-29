# نشر نظام الخطط السنوية — دليل التثبيت على استضافة cPanel

## لماذا ليست InfinityFree؟

InfinityFree تشغّل PHP 8.3، لكنها لا تلبّي شرط الأمان الأساسي لهذا المشروع:

- **جذر الموقع ثابت على `htdocs` ولا يمكن تغييره، ولا يمكن وضع أي ملف خارجه.** لذلك لا يمكن إبقاء `.env` و`config` و`database` و`storage` و`vendor` خارج جذر الويب. الحل الذي تقترحه InfinityFree نفسها هو رفع المشروع كله داخل `htdocs` وحمايته بقواعد `.htaccess` فقط. وهذا ما رفضتَه في المتطلبات.
- **لا يوجد SSH ولا Composer ولا تشغيل `php artisan`،** فلا يمكن توليد المفتاح أو تشغيل migrations على الخادم.
- **لا توجد مهام مجدولة (cron)،** فلن تعمل التنبيهات اليومية.
- **نظام الحماية من البوتات يحجب أي طلب لا يأتي من متصفح،** فواجهات `/api/v1` غير قابلة للاستخدام من خارج المتصفح.
- **حدود الملفات:** ملف PHP أكبر من 1 م.ب يُحذف بصمت، وأي ملف أكبر من 10 م.ب يُرفض. وتمنع الشروط أيضًا «أرشيفات zip المعدّة للتنزيل»، وحزمة السنة في النظام ملف ZIP.
- **الصفحة 404 التي ظهرت لك:** رفعتَ المجلد كاملًا داخل `htdocs`، فأصبح `index.php` في `htdocs/public/` وليس في الجذر، ولا يوجد ما يوجّه الطلبات إليه.

هذه الحزمة إذن مجهّزة لأقل استضافة مناسبة: استضافة مشتركة بلوحة cPanel (أو ما يماثلها) تتوفر فيها الشروط التالية.

## الحد الأدنى المطلوب من الاستضافة

| البند | المطلوب |
|---|---|
| PHP | **8.3 أو 8.4** (اختُبرت الحزمة على 8.3.35 و8.4.21) |
| إضافات PHP | `pdo_mysql` `mbstring` `gd` `zip` `dom` `xml` `xmlreader` `xmlwriter` `simplexml` `fileinfo` `iconv` `intl` `ctype` `tokenizer` `openssl` `zlib` |
| قاعدة البيانات | MySQL 8 أو MariaDB 10.6 وما بعده |
| مكان الملفات | إمكانية رفع مجلد **بجوار** `public_html` لا داخله (متاحة في cPanel عادةً) |
| سطر الأوامر | SSH أو «Terminal» في cPanel، لتشغيل `php artisan` |
| المهام المجدولة | Cron Jobs (موصى به؛ يوجد بديل أدناه) |
| الموارد | ‏75 م.ب مساحة، نحو 7,700 ملف، `memory_limit` ‏256M، `upload_max_filesize` ‏10M على الأقل |
| الشهادة | SSL مجانية (AutoSSL / Let's Encrypt) |

قبل الشراء اسأل الدعم الفني سؤالًا واحدًا: «هل تتيحون PHP 8.3 مع SSH أو Terminal وCron، وهل يمكنني رفع مجلد خارج public_html؟».

## محتوى الحزمة

```
ymd-plans/      ← التطبيق كاملًا: app, config, database, storage, vendor … (خارج جذر الويب)
public_html/    ← ما يراه الزوار فقط: index.php و .htaccess و css و fonts و img
DEPLOY-AR.md    ← هذا الدليل
```

- المكتبات (`vendor`) مثبّتة مسبقًا للإنتاج ومتوافقة مع PHP 8.3، فلا حاجة إلى Composer على الخادم.
- لا تحتوي الحزمة على `.env` ولا مفتاح `APP_KEY` ولا أي كلمة مرور ولا حسابات تجريبية.

---

## خطوات التثبيت

### 1) أنشئ قاعدة البيانات
من cPanel ← **MySQL Databases**:
1. أنشئ قاعدة بيانات.
2. أنشئ مستخدمًا بكلمة مرور قوية.
3. أضف المستخدم إلى القاعدة بصلاحية **ALL PRIVILEGES**.

احفظ الاسم الكامل للقاعدة وللمستخدم كما يظهران في اللوحة (غالبًا بصيغة `cpaneluser_name`).

### 2) اضبط PHP
من **Select PHP Version** أو **MultiPHP Manager**: اختر **8.3** للنطاق، وتأكد من تفعيل الإضافات المذكورة في الجدول أعلاه.

### 3) ارفع الملفات
1. افتح **File Manager** وانتقل إلى المجلد الرئيسي للحساب (`/home/اسم_المستخدم`)، **وليس** إلى `public_html`.
2. احذف الملفات الافتراضية داخل `public_html` إن وُجدت (`index.html` و`default.php`).
3. ارفع `ymd-plans-deploy.zip` إلى المجلد الرئيسي، ثم اضغط **Extract**.

النتيجة المطلوبة:
```
/home/اسم_المستخدم/ymd-plans/
/home/اسم_المستخدم/public_html/index.php
/home/اسم_المستخدم/public_html/.htaccess
```

إن كان جذر نطاقك مجلدًا آخر (مثل `domains/example.com/public_html`) فضع `ymd-plans` بجواره في المستوى نفسه، ولا تغيّر شيئًا آخر. المسار داخل `public_html/index.php` نسبي (`../ymd-plans`).

### 4) أنشئ ملف الإعدادات
افتح **Terminal** (أو اتصل عبر SSH) ونفّذ:

```bash
cd ~/ymd-plans
php -v                     # يجب أن يظهر 8.3 أو 8.4
cp .env.production.example .env
chmod 600 .env
```

افتح `ymd-plans/.env` في محرر File Manager واملأ هذه الحقول فقط:

| الحقل | القيمة |
|---|---|
| `APP_URL` | رابط موقعك الكامل، مثل `https://plans.example.org` |
| `DB_HOST` | `localhost`، ما لم تذكر الاستضافة غيره |
| `DB_DATABASE` | اسم القاعدة من الخطوة 1 |
| `DB_USERNAME` | اسم المستخدم من الخطوة 1 |
| `DB_PASSWORD` | كلمة مرور المستخدم |

اترك `APP_KEY` فارغًا، فهو يُولَّد في الخطوة التالية.

> إن أظهر `php -v` إصدارًا أقدم من 8.3 فاطلب من الدعم مسار PHP 8.3 لسطر الأوامر (مثل `/opt/cpanel/ea-php83/root/usr/bin/php`) واستخدمه بدل `php` في كل الأوامر.

### 5) المفتاح وقاعدة البيانات والصلاحيات

```bash
cd ~/ymd-plans
php artisan key:generate --force     # يكتب APP_KEY عشوائيًا داخل .env
php artisan migrate --force          # ينشئ الجداول
php artisan db:seed --force          # المناصب السبعة وقواعد الحالات فقط (بلا بيانات تجريبية)
php artisan admin:create             # يطلب البريد وكلمة المرور (مخفية، 12 حرفًا على الأقل مع رقم ورمز)

chmod -R 775 storage bootstrap/cache
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

- إن ظهر خطأ «Permission denied» في الصفحة لاحقًا فجرّب `chmod -R 755` بدل `775`، فبعض الاستضافات تشغّل PHP باسم مستخدمك نفسه.
- **مهم:** بعد أي تعديل على `.env` أعد تشغيل `php artisan config:cache`، وإلا تبقى القيم القديمة مستخدمة.

### 6) SSL وإعادة التوجيه إلى HTTPS
1. فعّل الشهادة من **SSL/TLS Status** أو **AutoSSL**.
2. افتح `public_html/.htaccess` وأزل علامة `#` من بداية الأسطر الثلاثة تحت «إعادة التوجيه إلى HTTPS».

### 7) المهمة المجدولة (التنبيهات اليومية)
من **Cron Jobs** أضف مهمة تعمل كل دقيقة (`* * * * *`) بهذا الأمر:

```
cd /home/اسم_المستخدم/ymd-plans && php artisan schedule:run >> /dev/null 2>&1
```

تولّد هذه المهمة التنبيهات يوميًا الساعة 07:00: التحديثات المستحقة، والمهام المتأخرة، والمؤشرات تحت المستهدف، والأدلة المنتظرة.

**إن لم تتوفر Cron:** اجعل `ALERTS_RUN_ON_REQUEST=true` في `.env` ثم شغّل `php artisan config:cache`. بذلك تُولَّد التنبيهات مرة يوميًا مع أول زيارة للموقع في اليوم، بعد إرسال الصفحة للزائر.

---

## الاختبار بعد النشر

افتح هذه الروابط، واستبدل `https://YOUR-DOMAIN` برابطك:

| الرابط | المتوقع |
|---|---|
| `https://YOUR-DOMAIN/` | يحوّلك إلى صفحة تسجيل الدخول بالتصميم والشعار |
| `https://YOUR-DOMAIN/up` | صفحة «Application up» |
| `https://YOUR-DOMAIN/css/app.css` | ملف الأنماط |
| `https://YOUR-DOMAIN/img/logo-full.png` | شعار الجمعية |
| `https://YOUR-DOMAIN/.env` | **403 أو 404** (يجب ألا يظهر أي محتوى) |
| `https://YOUR-DOMAIN/ymd-plans/.env` | **403 أو 404** |

ثم:
1. سجّل الدخول بحساب `admin:create`. يجب أن تُفتح «الحسابات والمناصب».
2. أنشئ حسابًا لكل منصب وأسنِد له منصبه.
3. سجّل الدخول بحساب مسؤول التخطيط، ثم من «السنوات والأرباع» أنشئ سنة التخطيط.
4. جرّب بحساب الرئيس وحساب أحد المسؤولين، ونزّل ملف PDF من «التقارير والتنزيلات».

## عند حدوث مشكلة

| العَرَض | السبب والحل |
|---|---|
| صفحة 500 بيضاء ونص «Your Composer dependencies require PHP >= 8.3.0» | إصدار PHP للنطاق أقدم من 8.3. غيّره من الخطوة 2 |
| «Application folder not found» | مجلد `ymd-plans` ليس بجوار `public_html`. انقله، أو عدّل `$appPath` في `public_html/index.php` |
| خطأ 500 بعد تعديل `.env` | شغّل `php artisan config:clear` ثم `php artisan config:cache` |
| «No application encryption key» | لم تُنفَّذ `php artisan key:generate --force` |
| «SQLSTATE[HY000] [1045]» | بيانات القاعدة في `.env` غير صحيحة، أو المستخدم غير مضاف إلى القاعدة |
| الصفحات تعمل والأنماط لا تظهر | `APP_URL` لا يطابق رابط الموقع (http أو https أو www) |

التفاصيل الفنية للأخطاء تُكتب في `ymd-plans/storage/logs/`، ولا تظهر للزوار لأن `APP_DEBUG=false`.

## النسخ الاحتياطي

خذ نسخة دورية من:
- قاعدة البيانات (من **phpMyAdmin** ← Export، أو **Backup** في cPanel).
- المجلد `ymd-plans/storage/app/private`، وفيه الأدلة المرفوعة والملفات المُصدَّرة.
- الملف `ymd-plans/.env`. **بدون `APP_KEY` نفسه لا يمكن فتح الجلسات المشفّرة القديمة.**
