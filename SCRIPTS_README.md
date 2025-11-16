# 🚀 سكريپتات التشغيل السريع - نظام V5
## Quick Start Scripts - V5 System

---

## 📋 نظرة عامة

هذه المجموعة من السكريپتات مصممة لتسهيل **التشغيل السريع** لنظام V5 في بيئة التطوير المحلية. تتضمن:

- ⚡ **التشغيل التلقائي** في أقل من 5 دقائق
- 🛑 **الإيقاف الآمن** مع تنظيف العمليات
- 🔧 **إعدادات محلية محسنة** للتطوير
- 👥 **بيانات تجريبية جاهزة** للاختبار

---

## 📁 الملفات المتضمنة

| الملف | الوصف | نوع الملف |
|------|--------|----------|
| **start-local.sh** | سكريپت التشغيل السريع | Bash Script |
| **stop-local.sh** | سكريپت الإيقاف الآمن | Bash Script |
| **.env.development** | إعدادات البيئة للتطوير | Environment Config |
| **QUICK_START_LOCAL.md** | دليل التشغيل السريع | Documentation |
| **database/seeders/QuickTestSeeder.php** | البيانات التجريبية | PHP Seeder |

---

## ⚡ البدء السريع

### 1. التشغيل التلقائي
```bash
# أعط صلاحيات التشغيل
chmod +x start-local.sh stop-local.sh

# شغل النظام
./start-local.sh
```

### 2. الإيقاف الآمن
```bash
# أوقف النظام
./stop-local.sh
```

---

## 🔧 تفاصيل السكريپت start-local.sh

### الميزات الرئيسية
- ✅ **فحص المتطلبات التلقائي** (PHP, Composer, Node.js, MySQL)
- ✅ **إعداد ملف البيئة** (.env) تلقائياً
- ✅ **تثبيت التبعيات** (Composer + npm)
- ✅ **إعداد قاعدة البيانات** (migrations + seeding)
- ✅ **إنشاء بيانات تجريبية** (10 حسابات اختبار)
- ✅ **بناء Frontend** (Vite build)
- ✅ **تشغيل الخوادم** (Laravel + Vite + Queue)

### الخيارات المتاحة
```bash
./start-local.sh [options]

# خيارات:
--help, -h          عرض المساعدة
--check-only        فحص المتطلبات فقط
--skip-deps         تخطي تثبيت التبعيات
--skip-db          تخطي إعداد قاعدة البيانات
--no-test-data     عدم إنشاء بيانات تجريبية
```

### مثال الاستخدام
```bash
# تشغيل كامل
./start-local.sh

# فحص المتطلبات فقط
./start-local.sh --check-only

# تشغيل بدون تبعيات (إذا كانت مثبتة مسبقاً)
./start-local.sh --skip-deps

# تشغيل بدون بيانات تجريبية
./start-local.sh --no-test-data
```

---

## 🛑 تفاصيل السكريپت stop-local.sh

### الميزات الرئيسية
- 🛑 **إيقاف آمن** لجميع العمليات
- 🧹 **تنظيف العمليات** المتبقية
- 📊 **تقارير حالة** العمليات
- 🔄 **تنظيف Cache** (اختياري)
- 💪 **إجبار الإيقاف** للعمليات المعطوبة

### الخيارات المتاحة
```bash
./stop-local.sh [options]

# خيارات:
--help, -h          عرض المساعدة
--force             إجبار إيقاف جميع العمليات
--no-cache          عدم تنظيف cache
--quiet, -q         وضع هادئ (قليل من الرسائل)
```

### مثال الاستخدام
```bash
# إيقاف طبيعي
./stop-local.sh

# إجبار إيقاف جميع العمليات
./stop-local.sh --force

# إيقاف هادئ
./stop-local.sh --quiet

# إيقاف بدون تنظيف cache
./stop-local.sh --no-cache
```

---

## ⚙️ ملف .env.development

### الإعدادات المحسنة للتطوير

```env
# تطبيق محسن للتطوير
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# واجهة عربية
APP_LOCALE=ar
APP_FALLBACK_LOCALE=en

# قاعدة بيانات محلية
DB_DATABASE=v5_development
DB_USERNAME=root
DB_PASSWORD=

# تخزين مؤقت محلي
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync

# مراقبة مفصلة للتطوير
TELESCOPE_ENABLED=true
DEBUGBAR_ENABLED=true

# إعدادات Vite للتطوير
VITE_HMR_PORT=24678
VITE_HMR_HOST=localhost
```

### الاختلافات عن الإنتاج

| الإعداد | التطوير | الإنتاج |
|---------|---------|----------|
| **APP_DEBUG** | `true` | `false` |
| **CACHE_STORE** | `file` | `redis` |
| **SESSION_DRIVER** | `file` | `redis` |
| **QUEUE_CONNECTION** | `sync` | `redis` |
| **LOG_LEVEL** | `debug` | `warning` |
| **TELESCOPE_ENABLED** | `true` | `false` |

---

## 👥 البيانات التجريبية

### الحسابات المنشأة تلقائياً

| اسم المستخدم | كلمة المرور | الدور | البريد الإلكتروني |
|-------------|-------------|-------|-----------------|
| **admin** | `admin123` | مدير شامل | admin@v5-system.com |
| **dev** | `dev123` | موظف مبيعات | dev@v5-system.com |
| **user** | `user123` | موظف مستودع | user@v5-system.com |
| **keeper1** | `password123` | موظف مستودع | keeper1@v5-system.com |
| **keeper2** | `password123` | موظف مستودع | keeper2@v5-system.com |
| **sales1** | `password123` | موظف مبيعات | sales1@v5-system.com |
| **sales2** | `password123` | موظف مبيعات | sales2@v5-system.com |
| **tracker1** | `password123` | متابع طلبات | tracker@v5-system.com |
| **accountant** | `password123` | محاسب | accountant@v5-system.com |
| **delivery** | `password123` | مسؤول تسليم | delivery@v5-system.com |

### استخدام البيانات التجريبية
```bash
# تشغيل البيانات التجريبية فقط
php artisan db:seed --class=QuickTestSeeder

# تشغيل جميع البيانات
php artisan db:seed

# إعادة تشغيل مع بيانات جديدة
php artisan migrate:fresh --seed
```

---

## 🌐 الروابط بعد التشغيل

### الخدمات المتاحة
| الخدمة | الرابط | الوصف |
|--------|--------|--------|
| **🏠 التطبيق** | http://localhost:8000 | التطبيق الرئيسي |
| **⚡ لوحة الإدارة** | http://localhost:8000/admin | Filament Admin Panel |
| **🔍 Telescope** | http://localhost:8000/telescope | مراقبة Laravel |
| **🎨 Vite** | http://localhost:5173 | خادم التطوير للواجهة |

### منافذ التشغيل
- **8000**: Laravel Server
- **5173**: Vite Dev Server
- **24678**: Vite HMR

---

## 🔧 الأوامر الإضافية

### أوامر مفيدة بعد التشغيل
```bash
# مراقبة السجلات
tail -f storage/logs/laravel.log

# الدخول إلى Tinker
php artisan tinker

# تشغيل اختبارات
php artisan test

# إعادة تشغيل الخادم
php artisan serve --host=0.0.0.0 --port=8000

# تشغيل Queue workers
php artisan queue:work
```

### أوامر التنظيف
```bash
# مسح جميع cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# إعادة بناء التطبيق
php artisan optimize:clear

# حذف ملفات log قديمة
find storage/logs -name "*.log" -mtime +7 -delete
```

---

## 🆘 استكشاف الأخطاء

### المشاكل الشائعة وحلولها

#### ❌ خطأ في الصلاحيات
```bash
# الحل
chmod +x start-local.sh stop-local.sh
chmod -R 775 storage bootstrap/cache
```

#### ❌ خطأ في المنفذ مستخدم
```bash
# الحل - إيقاف العمليات على المنفذ
./stop-local.sh --force

# أو تخصيص منفذ آخر
php artisan serve --port=8001
```

#### ❌ خطأ في قاعدة البيانات
```bash
# التحقق من حالة MySQL
sudo systemctl status mysql

# إعادة تشغيل migrations
php artisan migrate:fresh --seed

# التحقق من إعدادات .env
cat .env | grep DB_
```

#### ❌ مشاكل في Node modules
```bash
# حذف وإعادة تثبيت
rm -rf node_modules package-lock.json
npm install

# أو استخدام Yarn
yarn install
```

#### ❌ مشاكل في Composer
```bash
# تحديث Composer
composer self-update

# حذف وإعادة تثبيت vendor
rm -rf vendor composer.lock
composer install
```

---

## 🛠️ التخصيص والتطوير

### إضافة بيانات تجريبية جديدة
```php
// تعديل ملف database/seeders/QuickTestSeeder.php
// إضافة مستخدمين جدد
$newUser = User::firstOrCreate(
    ['username' => 'newuser'],
    [
        'name' => 'مستخدم جديد',
        'email' => 'newuser@v5-system.com',
        'password' => Hash::make('password123'),
        'email_verified_at' => now(),
    ]
);
```

### تخصيص إعدادات التطوير
```bash
# تحرير ملف .env.development
nano .env.development

# أو إضافة متغيرات جديدة
echo "CUSTOM_SETTING=value" >> .env.development
```

### إضافة سكريپت مخصص
```bash
# إنشاء سكريپت جديد
cat > custom-command.sh << 'EOF'
#!/bin/bash
echo "تنفيذ أمر مخصص..."
php artisan custom:command
EOF

chmod +x custom-command.sh
```

---

## 📈 مراقبة الأداء

### أوامر المراقبة
```bash
# استخدام الذاكرة
php artisan tinker
>>> memory_get_usage(true) / 1024 / 1024 . ' MB'

# عدد المستخدمين المتصلين
php artisan tinker
>>> DB::table('sessions')->count()

# حالة Queue
php artisan queue:monitor
```

### مراقبة قاعدة البيانات
```bash
# اتصال قاعدة البيانات
php artisan tinker
>>> DB::connection()->getPdo();

# عدد الجداول
php artisan tinker
>>> DB::select("SHOW TABLES");
```

---

## 🔐 الأمان في التطوير

### إعدادات الأمان المحلية
- ✅ كلمات مرور افتراضية (يجب تغييرها للإنتاج)
- ✅ Session محدودة (2 ساعة)
- ✅ Debug mode مفعل
- ✅ HTTPS معطل للتطوير

### فحص الثغرات
```bash
# فحص composer
composer audit

# فحص npm
npm audit

# فحص Laravel security
php artisan security:check
```

---

## 📚 المراجع

### الوثائق
- 📖 [دليل التشغيل السريع](QUICK_START_LOCAL.md)
- 🔒 [دليل الأمان](../docs/security/COMPREHENSIVE_SECURITY_GUIDE.md)
- 🚀 [دليل النشر](../docs/production/1-BASIC_DEPLOYMENT_GUIDE.md)
- 📊 [تقرير الأداء](../docs/PERFORMANCE_METRICS_REPORT.md)

### الروابط المفيدة
- [Laravel Documentation](https://laravel.com/docs)
- [Filament Documentation](https://filamentphp.com/docs)
- [Vite Documentation](https://vitejs.dev/docs)
- [Tailwind CSS](https://tailwindcss.com/docs)

---

## 🤝 المساهمة والدعم

### الإبلاغ عن المشاكل
- 📧 البريد الإلكتروني: support@v5-system.com
- 🐛 إبلاغ عن bug: [GitHub Issues]
- 💡 طلب feature: [GitHub Discussions]

### تطوير السكريپتات
```bash
# fork المستودع
git clone [repository-url]

# إنشاء branch جديد
git checkout -b feature/new-script

# تطبيق التغييرات
git commit -m "إضافة ميزة جديدة"

# push الـ branch
git push origin feature/new-script
```

---

## 📄 الترخيص

هذا المشروع مرخص تحت [MIT License](LICENSE).

---

## 📅 معلومات الإصدار

- **الإصدار**: 1.0
- **التاريخ**: 2025-11-06
- **المطور**: فريق تطوير V5
- **اللغة**: PHP 8.2+, Laravel 12+

---

## 🎉 شكر وتقدير

شكراً لاستخدام **نظام V5**! نحن نقدر ملاحظاتكم واقتراحاتكم لتحسين تجربة التشغيل.


---

## 🪟 Windows Support

### نظام التشغيل Windows
في حالة استخدام Windows، يمكنك استخدام ملفات .bat المخصصة:

### التشغيل على Windows
```batch
# التشغيل السريع
start-local.bat

# فحص المتطلبات فقط
start-local.bat --check-only
```

### الإيقاف على Windows
```batch
# إيقاف النظام
stop-local.bat

# إجبار إيقاف العمليات
stop-local.bat --force
```

### متطلبات Windows
- **Git for Windows** (يتضمن Git Bash)
- **Windows Subsystem for Linux (WSL)** (اختياري)
- **PHP 8.2+** (من XAMPP أو تثبيت مباشر)
- **Node.js 18+**
- **MySQL/MariaDB**

### إعداد متطلبات Windows
```batch
# 1. تثبيت PHP من XAMPP
# 2. إضافة PHP إلى PATH
# 3. تثبيت Node.js من nodejs.org
# 4. تثبيت MySQL من XAMPP
# 5. تثبيت Git for Windows
```

### حل مشاكل Windows الشائعة
```batch
# مشكلة: "bash: command not found"
# الحل: تثبيت Git for Windows

# مشكلة: "permission denied"
# الحل: تشغيل Command Prompt كـ Administrator

# مشكلة: MySQL لا يعمل
# الحل: تشغيل XAMPP Control Panel وتشغيل MySQL

# مشكلة: Node modules
# الحل: حذف node_modules وتشغيل npm install مرة أخرى
```
**🚀 ابدأ الآن**: `./start-local.sh`

**📞 الدعم**: support@v5-system.com