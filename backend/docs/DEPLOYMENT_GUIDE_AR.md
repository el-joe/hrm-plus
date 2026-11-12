# دليل النشر والتشغيل — HRM Plus

**الإصدار:** 1.0.0 (قيد الإصدار) · **آخر تحديث:** 2026-11-12
**الجمهور:** فريق التشغيل ومسؤولو الخوادم. الأوامر تُنفَّذ من مجلد `backend/` ما لم يُذكر غير ذلك.

> الخطوات أدناه تحقق منها فريق التطوير بتثبيت نظيف في وضع الإنتاج (بلا بيانات عرض) وفي الوضع المحلي. ما يرد تحت عنوان **«افتراض»** لم يُختبر على خادم العميل ويجب تأكيده عند التنفيذ.

---

## جدول المحتويات

1. [مكونات النظام](#1-مكونات-النظام)
2. [متطلبات الخادم](#2-متطلبات-الخادم)
3. [التثبيت لأول مرة](#3-التثبيت-لأول-مرة)
4. [متغيرات البيئة](#4-متغيرات-البيئة)
5. [خادم الويب](#5-خادم-الويب)
6. [HTTPS](#6-https)
7. [عامل الطابور (Queue Worker)](#7-عامل-الطابور-queue-worker)
8. [المجدول (Scheduler)](#8-المجدول-scheduler)
9. [التخزين والصلاحيات](#9-التخزين-والصلاحيات)
10. [البريد الإلكتروني](#10-البريد-الإلكتروني)
11. [الإشعارات الفورية (FCM)](#11-الإشعارات-الفورية-fcm)
12. [ما بعد التثبيت: الإعداد الأولي من الواجهة](#12-ما-بعد-التثبيت-الإعداد-الأولي-من-الواجهة)
13. [تطبيق الجوال: البناء والإعداد](#13-تطبيق-الجوال-البناء-والإعداد)
14. [الترقية إلى إصدار جديد](#14-الترقية-إلى-إصدار-جديد)
15. [النسخ الاحتياطي والاستعادة](#15-النسخ-الاحتياطي-والاستعادة)
16. [المراقبة والصيانة](#16-المراقبة-والصيانة)
17. [بيئة التطوير والعرض](#17-بيئة-التطوير-والعرض)
18. [مشكلات شائعة](#18-مشكلات-شائعة)
19. [الافتراضات](#19-الافتراضات)

---

## 1. مكونات النظام

| المكوّن | التقنية |
|---|---|
| تطبيق الويب والواجهة البرمجية | Laravel 13 على PHP 8.3 (Blade + Tailwind 4 + JavaScript أصلي، مجمّع بـ Vite) |
| قاعدة البيانات | MySQL 8 (`utf8mb4`) |
| الطابور والتخزين المؤقت والجلسات | قاعدة البيانات (افتراضيًا) — يمكن استخدام Redis |
| ملفات PDF العربية | mPDF 8 مع خط Cairo المضمّن في `resources/fonts/cairo` |
| تطبيق الجوال | Flutter 3.47 / Dart 3.13 (أندرويد و iOS) |
| الإشعارات الفورية | Firebase Cloud Messaging HTTP v1 |

العمليات الدائمة في الخادم: **خادم الويب + PHP-FPM**، **عامل الطابور**، و**المجدول** (cron كل دقيقة).

---

## 2. متطلبات الخادم

| البند | المطلوب |
|---|---|
| نظام التشغيل | Linux (التطوير على Ubuntu) |
| PHP | **8.3** مع الإضافات: `bcmath`، `gd`، `intl`، `mbstring`، `pdo_mysql`، `zip`، `sodium`، و`openssl`، `ctype`، `fileinfo`، `tokenizer`، `xml`، `curl`، `dom` (القياسية في Laravel) |
| Composer | 2.x |
| MySQL | **8.0** بترميز `utf8mb4` وترتيب `utf8mb4_unicode_ci` |
| Node.js | **20.19 أو أحدث** للبناء فقط (يحذّر `npm ci` من أن الحزمة `concurrently` تطلب Node 22، وهي تُستخدم فقط في `composer dev` للتطوير؛ البناء يعمل على Node 20) |
| خادم ويب | Apache 2.4 مع `mod_rewrite`، أو Nginx + PHP-FPM |
| مدير عمليات | Supervisor أو systemd لعامل الطابور |
| cron | لتشغيل المجدول كل دقيقة |
| شهادة TLS | مطلوبة للإنتاج (انظر القسم 6) |

**افتراض — الموارد:** لشركة حتى بضع مئات من الموظفين: 2 vCPU، 4 GB RAM، 40 GB قرص (الملفات الخاصة تكبر مع المستندات والسير الذاتية). حدّ رفع الملفات في PHP/خادم الويب ≥ 50 MB (`upload_max_filesize`، `post_max_size`، `LimitRequestBody` / `client_max_body_size`).

---

## 3. التثبيت لأول مرة

```bash
# 1) الشيفرة
git clone <repository> /var/www/hrm-plus
cd /var/www/hrm-plus/backend

# 2) اعتماديات PHP (بلا حزم التطوير)
composer install --no-dev --optimize-autoloader

# 3) الواجهة الأمامية
npm ci
npm run build            # ينتج public/build

# 4) ملف البيئة
cp .env.example .env
#   عدّل: APP_ENV=production, APP_DEBUG=false, APP_URL=https://hr.example.com,
#         DB_*, LOG_LEVEL=warning, MAIL_* (انظر القسم 4)

# 5) مفتاح التطبيق — احفظه في مكان آمن (لا يمكن فك تشفير البيانات بدونه)
php artisan key:generate --force

# 6) إنشاء الجداول وزرع بيانات الإنتاج المرجعية
php artisan migrate --seed --force

# 7) أول حساب مدير نظام (كلمة المرور تُطلب بلا عرض، أو من HRM_ADMIN_PASSWORD)
php artisan hrm:create-super-admin --name="مدير النظام" --email=admin@example.com

# 8) التخزين المؤقت للإعدادات والمسارات والقوالب والأحداث
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### 3.1 ما يزرعه `migrate --seed` في الإنتاج

- الصلاحيات (246) والأدوار الافتراضية الأحد عشر.
- مسارات الاعتماد (16).
- قوالب الإشعارات (432) لكل حدث × قناة × لغة.
- البيانات المرجعية: مراحل التوظيف الافتراضية، نموذج تقييم مقابلة عام، قائمة انضمام قياسية، حالات الحضور، سياسة حضور عامة، مقياس تقدير 1–5 وكفاءات، فئات الأصول، أنواع المخالفات، تصنيفات الطلبات وقوالب الخطابات.

**لا يُزرع:** مستخدمون، موظفون، ملف الشركة، فروع، أنواع إجازات، مكونات رواتب أو جداول نظامية — تُنشأ من الواجهة بعد الدخول الأول (القسم 12). الإعدادات تبدأ بالقيم الافتراضية في `config/settings.php`.

- `db:seed` في الإنتاج يحتاج `--force`.
- **لا تشغّل** `migrate:fresh --seed` على خادم الإنتاج: يحذف كل البيانات.

### 3.2 أمر `hrm:create-super-admin`

```bash
php artisan hrm:create-super-admin --name="..." --email=...
# تثبيت آلي بلا تفاعل:
HRM_ADMIN_PASSWORD='...' php artisan hrm:create-super-admin --name="..." --email=... --no-interaction
```

يتحقق من كلمة المرور حسب سياسة الأمان، ويسجّل العملية في سجل التدقيق.

---

## 4. متغيرات البيئة

كل المتغيرات موثقة في `backend/.env.example`. أهمها للإنتاج:

| المتغير | القيمة في الإنتاج | ملاحظة |
|---|---|---|
| `APP_NAME` | `"HRM Plus"` أو اسم الشركة | |
| `APP_ENV` | `production` | يعطّل بيانات العرض وصفحة الوظائف افتراضيًا ويقيّد `/docs/api` |
| `APP_DEBUG` | `false` | إلزامي |
| `APP_KEY` | يُولَّد مرة واحدة | **لا تغيّره بعد التشغيل**: يفك تشفير أرقام الهوية والحسابات البنكية والبيانات الحيوية والإعدادات السرية |
| `APP_URL` | `https://hr.example.com` | يجب أن يطابق العنوان الفعلي؛ `https` يجعل ملف تعريف الجلسة Secure تلقائيًا |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `ar` / `en` | |
| `LOG_LEVEL` | `warning` | |
| `DB_CONNECTION`، `DB_HOST`، `DB_PORT`، `DB_DATABASE`، `DB_USERNAME`، `DB_PASSWORD` | مستخدم مخصص بصلاحيات على قاعدة النظام فقط | |
| `SESSION_DRIVER` | `database` | شاشة «إعدادات الحساب» تعرض المتصفحات المسجلة من جدول الجلسات |
| `SESSION_LIFETIME` | `120` | ومدة الخمول قابلة للضبط من الإعدادات ← الأمان |
| `SESSION_SECURE_COOKIE` | (اتركه فارغًا) | يُفعَّل تلقائيًا مع `https`؛ اضبطه `false` فقط إن اضطررت للعمل عبر HTTP |
| `QUEUE_CONNECTION` | `database` | أو `redis` |
| `CACHE_STORE` | `database` | يجب أن يدعم الأقفال (لـ `onOneServer` في المجدول) — `database` أو `redis` |
| `MAIL_MAILER`، `MAIL_HOST`، `MAIL_PORT`، `MAIL_USERNAME`، `MAIL_PASSWORD`، `MAIL_FROM_ADDRESS`، `MAIL_FROM_NAME` | خادم SMTP حقيقي | مع `log` (الافتراضي) تُكتب الرسائل ورموز استعادة كلمة المرور في سجل الخادم فقط. يمكن أيضًا ضبط SMTP من الإعدادات ← إرسال البريد الإلكتروني |
| `AUTH_LOGIN_MAX_ATTEMPTS` / `AUTH_LOGIN_DECAY_SECONDS` | 5 / 60 | قفل الدخول (والقيم في الإعدادات ← الأمان) |
| `SANCTUM_TOKEN_EXPIRATION` | `43200` | عمر رمز تطبيق الجوال بالدقائق (30 يومًا) |
| `SECURITY_HEADERS` / `SECURITY_CSP` | `true` / `true` | ترويسات الأمان وسياسة أمان المحتوى (CSP) — `config/security.php` |
| `SECURITY_HSTS_MAX_AGE` | `31536000` | يُرسل على HTTPS فقط |
| `SECURITY_EXPORTS_PER_MINUTE` | `20` | حد التصدير لكل مستخدم في الدقيقة |
| `SECURITY_CSP_IMG_SRC` / `SECURITY_CSP_CONNECT_SRC` | فارغ | أصول إضافية إن قُدّمت الملفات من CDN |
| `REPORTS_QUEUE_THRESHOLD_XLSX` / `_CSV` / `_PDF`، `REPORTS_PDF_MAX_ROWS` | 5000 / 5000 / 400، 3000 | (في `.env.example` و`config/reports.php`) التصدير الأكبر من الحد يُجهَّز في الخلفية؛ وتصدير PDF أكبر من `REPORTS_PDF_MAX_ROWS` صفًا يُرفض |
| `HRM_ADMIN_PASSWORD` | (لا يُحفظ في `.env`) | فقط عند تشغيل `hrm:create-super-admin` آليًا |

أسرار SMTP ومفتاح FCM يمكن إدخالها من الواجهة (تُحفظ مشفرة في قاعدة البيانات)، لذلك لا يلزم وضعها في `.env`.

---

## 5. خادم الويب

جذر الموقع هو **`backend/public`** فقط. لا تكشف أي مجلد آخر.

### 5.1 Apache

مثال مبني على ملف التطوير `deploy/apache/hrm-plus.loc.conf`:

```apache
<VirtualHost *:443>
    ServerName hr.example.com
    DocumentRoot /var/www/hrm-plus/backend/public

    <Directory /var/www/hrm-plus/backend/public>
        AllowOverride All
        Require all granted
    </Directory>

    # رفع المستندات والسير الذاتية
    LimitRequestBody 52428800

    SSLEngine on
    SSLCertificateFile    /etc/ssl/certs/hr.example.com.crt
    SSLCertificateKeyFile /etc/ssl/private/hr.example.com.key

    ErrorLog ${APACHE_LOG_DIR}/hrm-plus_error.log
    CustomLog ${APACHE_LOG_DIR}/hrm-plus_access.log combined
</VirtualHost>

<VirtualHost *:80>
    ServerName hr.example.com
    Redirect permanent / https://hr.example.com/
</VirtualHost>
```

```bash
sudo a2enmod rewrite ssl
sudo a2ensite hrm-plus
sudo apache2ctl configtest && sudo systemctl reload apache2
```

### 5.2 Nginx (افتراض — لم يُختبر في المشروع)

```nginx
server {
    listen 443 ssl http2;
    server_name hr.example.com;
    root /var/www/hrm-plus/backend/public;
    index index.php;
    client_max_body_size 50m;

    ssl_certificate     /etc/ssl/certs/hr.example.com.crt;
    ssl_certificate_key /etc/ssl/private/hr.example.com.key;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
    location ~ /\.(?!well-known) { deny all; }
}
server { listen 80; server_name hr.example.com; return 301 https://$host$request_uri; }
```

---

## 6. HTTPS

HTTPS **إلزامي** في الإنتاج لثلاثة أسباب:

1. **الجلسات:** مع `APP_URL` يبدأ بـ `https://` يصبح ملف تعريف الجلسة Secure، فيفشل الدخول عبر HTTP العادي.
2. **تطبيق الجوال:** إصدارات التطبيق النهائية تتطلب HTTPS (HTTP مسموح فقط في نسخة أندرويد التطويرية).
3. **تسجيل الحضور من بوابة الويب:** المتصفحات لا تسمح بالوصول إلى **الموقع** و**الكاميرا** إلا في سياق آمن: `https://` أو `localhost` أثناء التطوير. على HTTP العادي تعرض بطاقة «تسجيل الحضور / الانصراف» في البوابة الرسالة «لا يعمل الموقع والكاميرا إلا عبر اتصال آمن (HTTPS)…» ولا يمكن التسجيل إلا من تطبيق الجوال أو جهاز البصمة.

ترويسة `Permissions-Policy` يرسلها النظام نفسه (`SecurityHeaders`): `camera=(self)` و`geolocation=(self)` لصفحات `/portal` فقط، و`()` (ممنوع) في بقية النظام. لا تُضف ترويسة `Permissions-Policy` أخرى في Apache / Nginx أو الوكيل العكسي تمنع الكاميرا أو الموقع، وإلا ظهرت للموظف «الوصول إلى الموقع محظور…» رغم سماحه في المتصفح.

للتحقق بعد النشر: افتح `https://<النطاق>/portal`، وتأكد من قفل المتصفح وأن الشارات في بطاقة الحضور تظهر، ثم جرّب تسجيلًا من حساب تجريبي (يطلب المتصفح إذن الموقع).

ترويسة HSTS تُرسل تلقائيًا على HTTPS (`SECURITY_HSTS_MAX_AGE`). إن كان النظام خلف موازن أحمال يُنهي TLS، تأكد من تمرير `X-Forwarded-Proto` وإعداد الوكلاء الموثوقين (**افتراض**: إعداد `trustProxies` حسب بنية العميل).

---

## 7. عامل الطابور (Queue Worker)

مطلوب دائمًا لـ: معالجة أحداث الحضور، سحب الأجهزة المجدول، الإشعارات (داخل النظام، البريد، الجوال)، رسائل المرشحين، حساب المسيرات الكبيرة، التصدير الكبير، وإعادة حساب الحضور بعد اعتماد الإجازات.

مثال Supervisor (`/etc/supervisor/conf.d/hrm-plus-worker.conf`):

```ini
[program:hrm-plus-worker]
command=php /var/www/hrm-plus/backend/artisan queue:work --queue=default --sleep=3 --tries=3 --max-time=3600
directory=/var/www/hrm-plus/backend
user=www-data
numprocs=2
autostart=true
autorestart=true
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=/var/www/hrm-plus/backend/storage/logs/worker.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start hrm-plus-worker:*
```

بعد كل نشر: `php artisan queue:restart` ليقرأ العمال الشيفرة الجديدة. المهام الفاشلة في جدول `failed_jobs` (`php artisan queue:failed`، `queue:retry`).

---

## 8. المجدول (Scheduler)

سطر cron واحد للمستخدم الذي يشغّل الويب:

```cron
* * * * * cd /var/www/hrm-plus/backend && php artisan schedule:run >> /dev/null 2>&1
```

يشغّل 22 مهمة مجدولة (كلها `withoutOverlapping` و`onOneServer` — تحتاج مخزنًا مؤقتًا يدعم الأقفال):

| الوقت | الأمر | الغرض |
|---|---|---|
| كل 5 دقائق | `attendance:sync-devices` | سحب أجهزة الحضور |
| كل 5 دقائق | `announcements:publish-scheduled` | نشر الإعلانات المجدولة |
| كل 10 دقائق | `attendance:process --pending` | الأحداث غير المعالجة |
| كل ساعة | `recruitment:send-interview-reminders`، `hr-requests:escalate` | تذكير المقابلات، تصعيد الطلبات |
| كل ساعة :15 | `notifications:attendance-alerts` | عدم تسجيل الانصراف، التأخير، الغياب |
| 00:10 | `employees:apply-scheduled-changes` | تطبيق النقل/الترقية/الراتب المستقبلي |
| 00:20 | `recruitment:expire-offers` | |
| 00:30 / 00:40 | `leave:rollover` / `leave:accrue` | سنة الإجازات والاستحقاق |
| 01:30 | `attendance:process` | إعادة حساب أمس |
| 02:15 | `face:purge` | الاحتفاظ بالبيانات الحيوية |
| 02:45 | `payroll:scan-corrections` | تصحيحات الرواتب |
| 03:15 | `system:purge-retention` | الاحتفاظ بالبيانات |
| 03:30 | `reports:prune-exports` | حذف التصديرات بعد 7 أيام |
| 06:30 | `offboarding:detect-contract-expiries` | |
| 07:00 | `employees:send-expiry-reminders` | المستندات والعقود |
| 07:10 / 07:20 | `training:send-certificate-reminders` / `performance:send-acknowledgement-reminders` | |
| 07:30 / 07:40 | `notifications:people-events` / `notifications:training-reminders` | |
| أيام العمل 08:00 | `notifications:approvals-digest` | ملخص الاعتمادات |

الأوقات بالمنطقة الزمنية للتطبيق. تحقق بـ `php artisan schedule:list`.

---

## 9. التخزين والصلاحيات

- `storage/` و`bootstrap/cache/` قابلة للكتابة من مستخدم الويب.
- الملفات الخاصة تحت `storage/app/private/*` (employees، recruitment، biometric، training، disciplinary، hr-requests، announcements، وقسائم الرواتب). الأقراص المحلية تكتب الملفات بصلاحيات **0640** والمجلدات **0750** (قابلة للقراءة من **المجموعة**)، حتى تبقى الملفات التي تنشئها أوامر سطر الأوامر والمهام قابلة للقراءة من خادم الويب. «الآخرون» لا يملكون أي صلاحية.
- اجعل مستخدم سطر الأوامر والمجدول وعامل الطابور في مجموعة خادم الويب، ومجلد `storage` بمجموعة `www-data` مع setgid:

```bash
sudo chown -R deploy:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 2775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 0664 {} \;
# الملفات الخاصة تُنشأ لاحقًا بـ 0640/0750 تلقائيًا
```

- `php artisan storage:link` **غير مطلوب**؛ لا تُقدَّم الملفات الخاصة عبر HTTP أبدًا، بل عبر مسارات مصرح بها ومسجلة.
- **مجلد mPDF المؤقت:** `storage/framework/mpdf` (يُنشأ تلقائيًا؛ يجب أن يكون قابلًا للكتابة من الويب والعامل). يُستخدم لتخزين خطوط Cairo المعالجة لملفات PDF (قسائم الرواتب، التقارير، الخطابات).
- ملف السجلات: `storage/logs/laravel.log` — ضع تدويرًا للسجلات (logrotate) أو `LOG_STACK=daily`.

---

## 10. البريد الإلكتروني

- اضبط SMTP في `.env` أو من **الإعدادات ← إرسال البريد الإلكتروني** (كلمة المرور مشفرة) ثم **إرسال تجريبي**.
- يُستخدم لـ: روابط استعادة كلمة المرور على الويب، **رموز OTP لتطبيق الجوال**، دعوات حسابات الموظفين، رسائل المرشحين، والإشعارات بالبريد.
- البريد يُرسل عبر الطابور: عامل الطابور يجب أن يعمل.
- إلى أن تتوفر بيانات SMTP من العميل (B6) يبقى `MAIL_MAILER=log`: الرسائل تُكتب في `storage/logs/laravel.log`.

---

## 11. الإشعارات الفورية (FCM)

حالة حالية: مزوّد **محاكاة** (B5). للتفعيل:

1. أنشئ مشروع Firebase، وأضف تطبيق أندرويد (`com.hrmplus.employee` — معرّف مؤقت، B8) وتطبيق iOS.
2. من Firebase: حساب خدمة (Service account) بصيغة JSON.
3. **الإعدادات ← إشعارات الجوال**: المزوّد FCM، ارفع **مفتاح حساب خدمة FCM** (يُحفظ مشفرًا)، معرّف قناة أندرويد `hrm_plus_default`، ثم **إشعار تجريبي**.
4. في التطبيق: `google-services.json` و`GoogleService-Info.plist` ومفتاح APNs (القسم 13).
5. يجب أن يسمح الخادم باتصال صادر HTTPS إلى `oauth2.googleapis.com` و`fcm.googleapis.com` (**افتراض** حول جدار الحماية لدى العميل).

---

## 12. ما بعد التثبيت: الإعداد الأولي من الواجهة

ادخل `https://hr.example.com/admin/login` بحساب مدير النظام، ثم بالترتيب:

1. **الإعدادات**: اللغة والمنطقة (المنطقة الزمنية، العملة، صيغ التاريخ)، الأمان، إرسال البريد، رقم الموظف، الحضور، الرواتب، الإجازات، تطبيق الجوال (أقل إصدار، روابط المتاجر)، التوظيف (صفحة الوظائف العامة متوقفة افتراضيًا في الإنتاج).
2. **الهيكل التنظيمي**: ملف الشركة والشعار، الفروع ومواقع الحضور (الإحداثيات ونصف القطر)، الأقسام، الفرق، الوظائف، المسميات، الدرجات، أنواع التوظيف، مراكز التكلفة.
3. **الورديات والجداول**: الورديات، جداول العمل وإسنادها، العطلات الرسمية، مراجعة سياسة الحضور العامة.
4. **الإجازات**: أنواع الإجازات وسياساتها (وفق قانون العمل المعتمد).
5. **الرواتب**: مكونات الراتب، الهياكل وإسنادها، **القواعد النظامية المعتمدة** (B7)، دولة القواعد النظامية في الإعدادات.
6. **الموظفون**: الاستيراد من Excel أو الإضافة اليدوية، ثم حسابات الدخول.
7. **مستخدمو الإدارة**: الحسابات بأدوارها ونطاقاتها.
8. **أجهزة الحضور**: إضافة الأجهزة (حاليًا عبر استيراد الملفات حتى يتوفر محوّل الجهاز الحقيقي — B3).

---

## 13. تطبيق الجوال: البناء والإعداد

من مجلد `application/`:

```bash
flutter pub get
# أندرويد (بعد قبول التراخيص: flutter doctor --android-licenses — B9)
flutter build appbundle --release \
  --dart-define=API_BASE_URL=https://hr.example.com/api/v1 \
  --dart-define=APP_VERSION=1.0.0 \
  --dart-define=ANDROID_CHANNEL_ID=hrm_plus_default
# iOS (على macOS مع Xcode)
cd ios && pod install && cd ..
flutter build ipa --release --dart-define=API_BASE_URL=https://hr.example.com/api/v1 --dart-define=APP_VERSION=1.0.0
```

| البند | التفاصيل |
|---|---|
| معرّف الحزمة | `com.hrmplus.employee` — **مؤقت** حتى يزوّد العميل المعرّف الحقيقي والعلامة التجارية (B8) |
| `API_BASE_URL` | عنوان الواجهة البرمجية بلا شرطة أخيرة، HTTPS في الإصدار النهائي |
| `APP_VERSION` | يُقارن بـ «أقل إصدار مسموح» في الإعدادات ← تطبيق الجوال |
| `ANDROID_CHANNEL_ID` | يطابق إعداد `push.android_channel_id` في الخادم |
| أذونات أندرويد | الإنترنت، حالة الشبكة، الموقع الدقيق والتقريبي، الكاميرا، الإشعارات (أندرويد 13+) |
| iOS | `NSLocationWhenInUseUsageDescription`، `NSCameraUsageDescription`، قدرة Push Notifications وRemote notifications، مفتاح APNs (.p8) في Firebase |
| Firebase | `android/app/google-services.json`، `ios/Runner/GoogleService-Info.plist` (B5). بدونها يعمل التطبيق مع تعطيل الإشعارات الفورية |
| التوقيع | مفتاح توقيع أندرويد (keystore) وشهادات Apple من العميل (**افتراض**: النشر عبر Google Play وApp Store أو MDM للعميل) |

بعد نشر إصدار جديد من التطبيق حدّث «أحدث إصدار» و(عند الحاجة) «أقل إصدار مسموح» في الإعدادات.

---

## 14. الترقية إلى إصدار جديد

```bash
cd /var/www/hrm-plus/backend
php artisan down                         # وضع الصيانة
git pull                                 # أو نشر الحزمة الجديدة
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan permissions:sync             # يضيف الصلاحيات الجديدة ويحدّث منح الأدوار الافتراضية
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan queue:restart
php artisan up
```

- `permissions:sync` يضيف الصلاحيات الجديدة، ويمنح الأدوار الافتراضية ما أُضيف إلى مجموعاتها، ويسحب ما منحه سابقًا ثم أُزيل من المجموعة الافتراضية — دون المساس بما عدّله المسؤول يدويًا. جرّبه أولًا بـ `--dry-run`.
- خذ نسخة احتياطية قبل كل ترقية (القسم 15).
- اقرأ CHANGELOG.md لملاحظات الإصدار.

---

## 15. النسخ الاحتياطي والاستعادة

يجب نسخ **ثلاثة** عناصر معًا:

1. **قاعدة البيانات:**
   ```bash
   mysqldump --single-transaction --routines --triggers -u backup -p hrm_plus | gzip > /backup/hrm-plus-$(date +%F).sql.gz
   ```
2. **الملفات الخاصة:** `backend/storage/app/private/` (المستندات، السير الذاتية، الأدلة التأديبية، الملفات الحيوية المشفرة، المرفقات، القسائم).
3. **ملف `.env`** وخصوصًا **`APP_KEY`** — بدونه لا يمكن قراءة الأعمدة المشفرة ولا الملفات الحيوية المشفرة.

**افتراض — السياسة:** نسخة يومية للقاعدة والملفات، احتفاظ 30 يومًا، نسخة خارج الخادم مشفرة، واختبار استعادة ربع سنوي. تعدّل حسب سياسة العميل.

**الاستعادة:** أعد الملفات و`.env` بنفس `APP_KEY`، ثم استورد قاعدة البيانات، ثم `php artisan config:cache` وإعادة تشغيل العمال.

---

## 16. المراقبة والصيانة

| ماذا | كيف |
|---|---|
| صحة التطبيق | `GET /up` (مسار فحص Laravel) |
| الأخطاء | `storage/logs/laravel.log` (لا تُكتب فيه بيانات حساسة) |
| الطابور | `php artisan queue:failed`؛ مراقبة عملية Supervisor |
| المجدول | `php artisan schedule:list`؛ تحقق من آخر تشغيل للمهام في سجلاتها |
| الأجهزة | لوحة الحضور، حالة الأجهزة الصحية، تقرير مزامنة الأجهزة |
| الإشعارات | الإدارة ← النظام ← **سجل الإرسال** (الفاشل وإعادة المحاولة) |
| الأمان | سجل التدقيق، محاولات الدخول الفاشلة (لوحة التحكم) |
| الاحتفاظ | الإعدادات ← الاحتفاظ بالبيانات، والتحقق بالوجه (مدد الحذف) |
| وثائق الواجهة البرمجية | `/docs/api` (مدير النظام فقط في الإنتاج) |

---

## 17. بيئة التطوير والعرض

- تحتاج حزم التطوير (Faker): `composer install` بدون `--no-dev`.
- `php artisan migrate:fresh --seed` في بيئة غير الإنتاج يزرع بيانات عرض كاملة (14 مستخدمًا، 61 موظفًا، نحو 2,400 يوم حضور، مسيرات رواتب...) ويستغرق 4–5 دقائق. الحسابات في `DEV_ACCOUNTS.md` (كلمة المرور `password`) — **لا تُستخدم أبدًا خارج التطوير**.
- تشغيل محلي: `composer dev` أو `php artisan serve` + `npm run dev` + `php artisan queue:work` + `php artisan schedule:work`.
- مضيف تطوير Apache: `sudo deploy/setup-local-sudo.sh` (يضيف `hrm-plus.loc`).
- الاختبارات: `php artisan test` (قاعدة اختبار MySQL منفصلة `hrm-plus_testing`)، و`flutter test` في `application/`.

---

## 18. مشكلات شائعة

| العرض | السبب والحل |
|---|---|
| الدخول يعود لصفحة الدخول دون رسالة | `APP_URL` بـ https والموقع يُفتح عبر http → فعّل TLS أو `SESSION_SECURE_COOKIE=false` مؤقتًا |
| «Unable to locate file in Vite manifest» | لم يُنفَّذ `npm run build` |
| رموز OTP ورسائل البريد لا تصل | `MAIL_MAILER=log` أو عامل الطابور متوقف |
| الحضور لا يُحسب / الإشعارات لا تُرسل | عامل الطابور أو cron لا يعمل |
| ملفات PDF لا تُنشأ أو خط عربي مفكك | `storage/framework/mpdf` غير قابل للكتابة، أو إضافة `gd`/`mbstring` مفقودة |
| «Permission denied» على ملفات أنشأها cron | مستخدم cron ليس في مجموعة الويب؛ راجع القسم 9 |
| `/careers` يعطي 404 | صفحة الوظائف متوقفة افتراضيًا في الإنتاج — الإعدادات ← التوظيف |
| `/docs/api` ممنوع | متاح لمدير النظام المسجّل دخوله فقط خارج البيئة المحلية |
| بطاقة الحضور في البوابة تعرض «لا يعمل الموقع والكاميرا إلا عبر اتصال آمن (HTTPS)…» | البوابة مفتوحة عبر http — فعّل TLS (القسم 6) وافتح الرابط بـ `https://` |
| «الوصول إلى الموقع محظور…» مع أن المتصفح يسمح | ترويسة `Permissions-Policy` إضافية من خادم الويب أو الوكيل تمنع `geolocation` / `camera` — احذفها واترك ترويسة النظام |
| صلاحيات جديدة لا تظهر بعد الترقية | لم يُشغَّل `permissions:sync` أو لم يُحدَّث `config:cache` |
| انتهاك CSP في المتصفح لصور من نطاق آخر | أضف النطاق في `SECURITY_CSP_IMG_SRC` / `SECURITY_CSP_CONNECT_SRC` |

---

## 19. الافتراضات

ما يلي افتراضات صريحة لم تُتحقق على بنية العميل:

1. نظام Linux مع Apache 2.4 (مُختبر) أو Nginx (مثال غير مختبر في المشروع).
2. خادم واحد يشغّل الويب والعامل والمجدول؛ في حال عدة خوادم يجب مخزن مؤقت مشترك يدعم الأقفال (قاعدة البيانات أو Redis) وتخزين ملفات مشترك لـ `storage/app/private`.
3. موارد الخادم المقترحة (2 vCPU / 4 GB) لبضع مئات من الموظفين.
4. شهادة TLS يوفرها العميل (أو Let's Encrypt).
5. السماح باتصال صادر إلى SMTP وإلى خدمات Google لـ FCM.
6. سياسة النسخ الاحتياطي المقترحة (يومي، 30 يومًا) حتى تُحدَّد سياسة العميل.
7. إعداد الوكلاء الموثوقين عند وجود موازن أحمال.
8. التوزيع عبر متاجر التطبيقات بحسابات مطور يملكها العميل.
