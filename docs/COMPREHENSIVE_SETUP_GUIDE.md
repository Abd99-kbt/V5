# الدليل الشامل لإعداد وتشغيل مشروع V5
## دليل تشغيل مفصل وعملي للمعاينة والتطوير

---

## 📋 المحتويات

1. [فحص المتطلبات الأساسية](#-فحص-المتطلبات-الأساسية)
2. [تثبيت المتطلبات](#-تثبيت-المتطلبات)
3. [إعداد المشروع](#-إعداد-المشروع)
4. [إعداد قاعدة البيانات](#-إعداد-قاعدة-البيانات)
5. [تشغيل المشروع](#-تشغيل-المشروع)
6. [التحقق من نجاح التشغيل](#-التحقق-من-نجاح-التشغيل)
7. [الوصول للموقع](#-الوصول-للموقع)
8. [بيانات الدخول التجريبية](#-بيانات-الدخول-الافتراضية)
9. [الاستكشاف الأولي](#-الاستكشاف-الأولي)
10. [الصيانة الأساسية](#-الصيانة-الأساسية)

---

## 🖥️ فحص المتطلبات الأساسية

### 1. فحص نظام التشغيل
**نظام التشغيل المدعوم:**
- ✅ Windows 10/11 (مع WSL أو Git Bash)
- ✅ Ubuntu 20.04+
- ✅ macOS 10.15+
- ✅ CentOS 8+

**فحص النظام:**
```bash
# فحص نظام التشغيل
# Windows
systeminfo | findstr /B /C:"OS Name" /C:"OS Version"

# Linux/macOS
uname -a
cat /etc/os-release
```

### 2. فحص الذاكرة ومساحة القرص
**المتطلبات المطلوبة:**
- 🟢 RAM: 4GB (المستحسن: 8GB)
- 🟢 مساحة القرص: 10GB (المستحسن: 50GB)

```bash
# فحص الذاكرة
# Windows
systeminfo | findstr "Total Physical Memory"

# Linux/macOS
free -h
# أو
vm_stat

# فحص مساحة القرص
# Windows
dir /-c

# Linux/macOS
df -h
du -sh .
```

### 3. فحص المتطلبات البرمجية

#### فحص PHP
```bash
# التحقق من تثبيت PHP
php -v

# إذا لم يكن مثبتاً، يجب تثبيت PHP 8.2+
```
**النتيجة المتوقعة:**
```
PHP 8.2.x (cli) (built: Oct 25 2023 12:38:15) (NTS)
Copyright (c) The PHP Group
Zend Engine v4.2, Copyright (c) Zend Technologies
```

#### فحص Composer
```bash
# التحقق من تثبيت Composer
composer --version

# إذا لم يكن مثبتاً، يجب تثبيت Composer
```
**النتيجة المتوقعة:**
```
Composer version 2.6.5
```

#### فحص Node.js
```bash
# التحقق من تثبيت Node.js
node -v
npm -v

# إذا لم يكن مثبتاً، يجب تثبيت Node.js 18+
```
**النتيجة المتوقعة:**
```
v20.x.x
10.x.x
```

#### فحص Git
```bash
# التحقق من تثبيت Git
git --version

# إذا لم يكن مثبتاً، يجب تثبيت Git
```
**النتيجة المتوقعة:**
```
git version 2.40.x
```

#### فحص قاعدة البيانات
**MySQL:**
```bash
# التحقق من MySQL
mysql --version

# أو
mysqld --version

# إذا لم يكن مثبتاً، يجب تثبيت MySQL 8.0+
```

**SQLite (بديل أسهل):**
```bash
# التحقق من SQLite
sqlite3 --version

# أو للتحقق من دعم PHP
php -m | grep -i sqlite
```

#### فحص خيارات إضافية
```bash
# التحقق من Redis (اختياري)
redis-cli --version

# التحقق من Apache/Nginx (اختياري)
nginx -v
# أو
httpd -v
```

---

## 🛠️ تثبيت المتطلبات

### 1. تثبيت PHP

#### Windows
**الطريقة الأولى: XAMPP (مُستحسنة للمبتدئين)**
1. تحميل XAMPP من: https://www.apachefriends.org/
2. تثبيت XAMPP
3. تشغيل Apache و MySQL من XAMPP Control Panel

**الطريقة الثانية: تثبيت PHP يدوياً**
1. تحميل PHP من: https://www.php.net/downloads
2. فك الضغط إلى `C:\php\`
3. إضافة `C:\php\` إلى PATH
4. إنشاء ملف `php.ini`

#### Linux (Ubuntu)
```bash
# تحديث نظام التشغيل
sudo apt update && sudo apt upgrade -y

# تثبيت PHP والامتدادات المطلوبة
sudo apt install php8.2 php8.2-cli php8.2-fpm php8.2-mysql \
    php8.2-xml php8.2-curl php8.2-zip php8.2-mbstring \
    php8.2-bcmath php8.2-json php8.2-tokenizer

# التحقق من التثبيت
php -v
```

#### macOS
```bash
# تثبيت Homebrew إذا لم يكن مثبتاً
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"

# تثبيت PHP
brew install php@8.2

# إضافة PHP إلى PATH
echo 'export PATH="/opt/homebrew/opt/php@8.2/bin:$PATH"' >> ~/.zshrc
source ~/.zshrc
```

### 2. تثبيت Composer

#### Windows
1. تحميل Composer من: https://getcomposer.org/download/
2. تشغيل ملف Composer-Setup.exe
3. اتباع التعليمات

#### Linux/macOS
```bash
# تحميل وتثبيت Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# التحقق من التثبيت
composer --version
```

### 3. تثبيت Node.js

#### Windows/macOS
1. تحميل من: https://nodejs.org/
2. تشغيل الملف وتثبيته
3. إعادة تشغيل Terminal/PowerShell

#### Linux (Ubuntu)
```bash
# استخدام NodeSource repository
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs

# التحقق من التثبيت
node -v
npm -v
```

### 4. تثبيت MySQL (أو استخدام SQLite)

#### MySQL
**Windows:**
1. تحميل MySQL من: https://dev.mysql.com/downloads/installer/
2. تشغيل MySQL Installer
3. اختيار "Developer Default"
4. إعداد كلمة مرور root

**Linux (Ubuntu):**
```bash
# تثبيت MySQL Server
sudo apt install mysql-server

# تأمين MySQL
sudo mysql_secure_installation

# إنشاء قاعدة بيانات
sudo mysql -u root -p
CREATE DATABASE v5_system;
CREATE USER 'v5_user'@'localhost' IDENTIFIED BY 'v5_password';
GRANT ALL PRIVILEGES ON v5_system.* TO 'v5_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

#### SQLite (بديل أسهل)
**لا يتطلب تثبيت إضافي - مُضمن في PHP**

### 5. تثبيت Git

#### Windows
1. تحميل من: https://git-scm.com/download/win
2. تشغيل Git-2.40.x-64-bit.exe
3. اتباع التعليمات

#### Linux (Ubuntu)
```bash
# تثبيت Git
sudo apt install git

# إعداد Git
git config --global user.name "Your Name"
git config --global user.email "your.email@example.com"
```

#### macOS
```bash
# تثبيت Git
brew install git

# إعداد Git
git config --global user.name "Your Name"
git config --global user.email "your.email@example.com"
```

---

## 📁 إعداد المشروع

### 1. تحميل المشروع
```bash
# إذا كان المشروع في Git repository
git clone [repository-url] v5-project
cd v5-project

# أو إذا كان لديك ملفات المشروع
# فك ضغط الملفات إلى مجلد v5-project
```

### 2. فحص محتويات المشروع
```bash
# عرض هيكل المشروع
ls -la

# أو على Windows
dir /a
```

**هيكل المشروع المتوقع:**
```
v5-project/
├── .env.example
├── composer.json
├── package.json
├── app/
├── config/
├── database/
├── public/
├── resources/
├── routes/
├── storage/
├── tests/
└── ...
```

### 3. إعداد ملف البيئة
```bash
# نسخ ملف البيئة
cp .env.example .env

# أو على Windows
copy .env.example .env
```

**تعديل ملف .env:**

#### لإعداد MySQL:
```env
APP_NAME="V5 Sales System"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=v5_system
DB_USERNAME=v5_user
DB_PASSWORD=v5_password
```

#### لإعداد SQLite (بديل أسهل):
```env
APP_NAME="V5 Sales System"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database.sqlite
```

### 4. تثبيت التبعيات
```bash
# تثبيت Composer dependencies
composer install

# تثبيت npm dependencies
npm install

# أو استخدام التثبيت المبسط
composer install --no-dev --optimize-autoloader
npm install --production
```

**علامات التحقق:**
- ✅ لا توجد أخطاء في النواتج
- ✅ تم إنشاء مجلد `vendor/`
- ✅ تم إنشاء ملف `package-lock.json`

---

## 🗄️ إعداد قاعدة البيانات

### 1. إنشاء مفتاح التطبيق
```bash
# توليد APP_KEY
php artisan key:generate

# النتيجة المتوقعة:
# Application key set successfully.
```

### 2. تشغيل Migrations
```bash
# تشغيل migrations
php artisan migrate

# النتيجة المتوقعة:
# INFO  Running migrations.
#  2024_01_01_000000_create_users_table ........................... 45ms  DONE
#  2024_01_01_000001_add_username_to_users_table .................. 32ms  DONE
#  ...
# INFO  Done.
```

**إذا حدثت أخطاء:**
```bash
# فحص حالة migrations
php artisan migrate:status

# إعادة تشغيل migrations (احذر - يحذف البيانات)
php artisan migrate:fresh

# مع seeders
php artisan migrate:fresh --seed
```

### 3. إنشاء رابط التخزين
```bash
# إنشاء symbolic link للملفات
php artisan storage:link

# النتيجة المتpected:
# The [public/storage] link has been connected to [storage/app/public].
```

### 4. إعداد الصلاحيات (Linux/macOS)
```bash
# تعيين صلاحيات المجلدات
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/

# تعيين المالك (إذا لزم الأمر)
sudo chown -R $USER:www-data storage/
sudo chown -R $USER:www-data bootstrap/cache/
```

**فحص الصلاحيات:**
```bash
# فحص صلاحيات المجلدات
ls -la storage/
ls -la bootstrap/cache/
```

### 5. إنشاء البيانات التجريبية (اختياري)
```bash
# تشغيل seeders
php artisan db:seed

# أو seeders محددة
php artisan db:seed --class=AdminUserSeeder
php artisan db:seed --class=SampleDataSeeder

# إنشاء مستخدم مدير
php artisan make:user
```

---

## 🚀 تشغيل المشروع

### 1. التشغيل السريع باستخدام السكريبت
**Linux/macOS:**
```bash
# إعطاء صلاحيات للسكريبت
chmod +x start-local.sh

# تشغيل النظام
./start-local.sh
```

**Windows:**
```cmd
# تشغيل السكريبت
start-local.bat
```

### 2. التشغيل اليدوي
```bash
# تشغيل خادم Laravel
php artisan serve

# أو تحديد المنافذ
php artisan serve --host=0.0.0.0 --port=8000

# تشغيل خادم Vite (في Terminal آخر)
npm run dev
```

**النتيجة المتوقعة:**
```
Laravel development server started: http://127.0.0.1:8000
```

### 3. التشغيل المتقدم
```bash
# تشغيل في الخلفية
nohup php artisan serve --host=0.0.0.0 --port=8000 > server.log 2>&1 &

# مع supervisor (Linux)
sudo apt install supervisor
# إنشاء ملف إعدادات في /etc/supervisor/conf.d/
```

---

## ✅ التحقق من نجاح التشغيل

### 1. فحص حالة الخوادم
```bash
# فحص خادم Laravel
curl http://localhost:8000
# يجب إرجاع HTML response

# فحص خادم Vite
curl http://localhost:5173
# يجب إرجاع HTML response
```

### 2. فحص قاعدة البيانات
```bash
# فحص الاتصال
php artisan tinker
>>> DB::connection()->getPdo();
# يجب إرجاع PDO object

# فحص الجداول
php artisan migrate:status
# يجب عرض قائمة الجداول
```

### 3. فحص التخزين
```bash
# فحص رابط التخزين
ls -la public/storage
# يجب عرض روابط للملفات

# فحص كاش Laravel
php artisan cache:clear
# يجب إرجاع رسالة نجاح
```

### 4. فحص الأخطاء
```bash
# فحص logs Laravel
tail -f storage/logs/laravel.log

# فحص logs PHP
tail -f /var/log/php_errors.log
# أو على Windows
type C:\php\logs\php_errors.log
```

### 5. فحص الأداء
```bash
# فحص استخدام الذاكرة
php artisan tinker
>>> memory_get_usage(true) / 1024 / 1024 . ' MB';

# فحص التبعيات
composer audit
```

---

## 🌐 الوصول للموقع

### 1. الروابط الأساسية
| الخدمة | الرابط | الوصف |
|--------|--------|--------|
| **الصفحة الرئيسية** | http://localhost:8000 | صفحة الدخول الرئيسية |
| **لوحة الإدارة** | http://localhost:8000/admin | لوحة تحكم الإدارة |
| **واجهة API** | http://localhost:8000/api | واجهات برمجة التطبيقات |
| **الوثائق** | http://localhost:8000/docs | وثائق API |
| **Health Check** | http://localhost:8000/health | فحص صحة النظام |

### 2. أدوات التطوير
| الأداة | الرابط | الوصف |
|--------|--------|--------|
| **DebugBar** | http://localhost:8000/?debugbar=1 | Laravel DebugBar |
| **Telescope** | http://localhost:8000/telescope | Laravel Telescope |
| **phpMyAdmin** | http://localhost:8080 | إدارة قاعدة البيانات |
| **Vite** | http://localhost:5173 | خادم Vite |

### 3. اختبار الوصول
```bash
# اختبار HTTP response
curl -I http://localhost:8000
# يجب إرجاع HTTP 200 OK

# اختبار من متصفح آخر
curl http://127.0.0.1:8000
```

---

## 🔑 بيانات الدخول الافتراضية

### 1. حسابات المستخدمين المُنشأة افتراضياً

#### حساب المدير الرئيسي
```
البريد الإلكتروني: admin@v5-system.com
كلمة المرور: admin123
الدور: Super Admin
```

#### حساب المطور
```
البريد الإلكتروني: dev@v5-system.com
كلمة المرور: dev123
الدور: Developer
```

#### حساب المستخدم
```
البريد الإلكتروني: user@v5-system.com
كلمة المرور: user123
الدور: User
```

#### حساب المدير الإقليمي
```
البريد الإلكتروني: manager@v5-system.com
كلمة المرور: manager123
الدور: Manager
```

### 2. إنشاء حساب جديد
```bash
# إنشاء مستخدم جديد
php artisan make:user

# أو استخدام Tinker
php artisan tinker
>>> $user = new App\Models\User();
>>> $user->name = 'New User';
>>> $user->email = 'newuser@example.com';
>>> $user->username = 'newuser';
>>> $user->password = Hash::make('password123');
>>> $user->save();
>>> $user->assignRole('admin');
```

### 3. إدارة كلمات المرور
```bash
# تغيير كلمة مرور مستخدم
php artisan tinker
>>> $user = App\Models\User::where('email', 'admin@v5-system.com')->first();
>>> $user->password = Hash::make('newpassword123');
>>> $user->save();
```

---

## 🔍 الاستكشاف الأولي

### 1. استكشاف لوحة الإدارة
1. افتح المتصفح واذهب إلى: http://localhost:8000/admin
2. سجل الدخول باستخدام بيانات المدير
3. استكشف الأقسام المختلفة:
   - Dashboard
   - Users
   - Products
   - Orders
   - Reports
   - Settings

### 2. فحص الواجهات API
1. اذهب إلى: http://localhost:8000/api
2. استعرض التوثيق التفاعلي
3. جرب بعض endpoints

### 3. فحص المميزات الأساسية
```bash
# فحص صحة النظام
curl http://localhost:8000/health

# فحص API
curl http://localhost:8000/api/health

# فحص قاعدة البيانات
php artisan db:show

# فحص الأداء
php artisan route:list | head -10
```

### 4. اختبار الوظائف الأساسية
- ✅ تسجيل الدخول
- ✅ إنشاء مستخدم جديد
- ✅ إضافة منتج
- ✅ إنشاء طلب
- ✅ عرض التقارير
- ✅ تصدير البيانات

---

## 🔧 الصيانة الأساسية

### 1. مسح الكاش
```bash
# مسح جميع أنواع الكاش
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# إعادة تخزين الإعدادات
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 2. تحديث التبعيات
```bash
# تحديث Composer dependencies
composer update

# تحديث npm dependencies
npm update

# فحص الثغرات الأمنية
composer audit
npm audit
```

### 3. نسخ احتياطية
```bash
# نسخ احتياطية قاعدة البيانات
php artisan backup:run

# أو باستخدام mysqldump (MySQL)
mysqldump -u v5_user -p v5_system > backup_$(date +%Y%m%d_%H%M%S).sql

# نسخ احتياطية ملفات النظام
tar -czf system_backup_$(date +%Y%m%d_%H%M%S).tar.gz storage/ public/
```

### 4. مراقبة السجلات
```bash
# عرض سجلات Laravel
tail -f storage/logs/laravel.log

# عرض سجلات الأخطاء
tail -f storage/logs/laravel-$(date +%Y-%m-%d).log

# فحص السجلات الكبيرة
wc -l storage/logs/laravel.log
```

### 5. فحص الأداء
```bash
# عرض routeالأبطأ
php artisan route:list --sort=avg_time

# فحص استخدام الذاكرة
php artisan optimize

# فحص حجم قاعدة البيانات
php artisan db:show
```

---

## 🎯 نصائح للنجاح

### 1. نصائح للمبتدئين
- 📖 اقرأ الأخطاء بعناية قبل البحث
- 🔄 أعد تشغيل الخدمات عند وجود مشاكل
- 📝 احتفظ بسجل للأوامر التي تعمل
- 🧪 اختبر التغييرات في بيئة التطوير أولاً

### 2. نصائح للمطورين
- 🔧 استخدم IDE جيد (VS Code, PhpStorm)
- 📊 فعّل DebugBar لفحص الأداء
- 🧪 اكتب اختبارات للوظائف الجديدة
- 🔄 استخدم Git لإدارة الإصدارات

### 3. نصائح للصيانة
- 📅 جدول مهام الصيانة الدورية
- 🔍 راقب سجلات الأخطاء بانتظام
- 💾 اعمل نسخ احتياطية منتظمة
- 🔐 حدث كلمات المرور دورياً

---

## ❓ الدعم والمساعدة

### 1. البحث في الوثائق
- 📖 راجع هذا الدليل أولاً
- 🔍 ابحث في سجلات الأخطاء
- 📝 اقرأ رسائل الخطأ بعناية

### 2. الحصول على المساعدة
- 💬 اطلب المساعدة من الفريق
- 📧 أرسل تفاصيل الخطأ
- 📸 أرفق لقطات شاشة للخطأ

### 3. معلومات مفيدة للدعم
```bash
# جمع معلومات النظام
php --version
composer --version
node --version
npm --version

# معلومات المشروع
php artisan --version
php artisan about

# معلومات البيئة
cat .env | grep -E "APP_ENV|APP_DEBUG|DB_"
```

---

**🎉 مبروك! أصبحت جاهزاً لاستخدام نظام V5**

---

**آخر تحديث**: 2025-11-06  
**رقم الإصدار**: 1.0  
**بواسطة**: فريق تطوير V5