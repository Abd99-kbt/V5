# دليل حل المشاكل الشائعة - نظام V5
## إرشادات شاملة لحل المشاكل التقنية

---

## 📋 المحتويات

1. [أخطاء PHP و Composer](#-أخطاء-php-و-composer)
2. [مشاكل قاعدة البيانات](#-مشاكل-قاعدة-البيانات)
3. [مشاكل Node.js و npm](#-مشاكل-nodejs-و-npm)
4. [أخطاء في المنافذ](#-أخطاء-في-المنافذ)
5. [مشاكل الصلاحيات](#-مشاكل-الصلاحيات)
6. [أخطاء Laravel](#-أخطاء-laravel)
7. [مشاكل التخزين والكاش](#-مشاكل-التخزين-والكاش)
8. [مشاكل Authentication](#-مشاكل-authentication)
9. [مشاكل الأداء](#-مشاكل-الأداء)
10. [مشاكل الشبكة والاتصال](#-مشاكل-الشبكة-والاتصال)

---

## 🐘 أخطاء PHP و Composer

### 1. أخطاء إصدار PHP

#### المشكلة: إصدار PHP غير متوافق
```bash
# فحص إصدار PHP
php -v

# النتيجة الخاطئة:
# PHP 7.4.x (cli) - إصدار قديم
```

#### الحل:
```bash
# Windows: تحديث إصدار PHP في XAMPP
# 1. تحميل إصدار PHP 8.2+ من php.net
# 2. استبدال ملفات PHP في XAMPP
# 3. تحديث PATH

# Linux (Ubuntu):
sudo apt update
sudo apt install php8.2 php8.2-cli php8.2-fpm

# تحديد إصدار PHP الافتراضي
sudo update-alternatives --set php /usr/bin/php8.2

# macOS:
brew install php@8.2
brew link --force php@8.2
```

### 2. أخطاء امتدادات PHP

#### المشكلة: امتدادات PHP مفقودة
```bash
# فحص الامتدادات المفقودة
php -m | grep -E "mysql|redis|mbstring|xml|curl|zip"

# النتيجة الخاطئة:
# mysql (missing)
# redis (missing)
```

#### الحل:
```bash
# Linux (Ubuntu):
sudo apt install php8.2-mysql php8.2-xml php8.2-curl \
    php8.2-zip php8.2-mbstring php8.2-bcmath \
    php8.2-json php8.2-tokenizer

# Windows (XAMPP):
# 1. فتح php.ini
# 2. إزالة التعليق من السطور:
extension=mysqli
extension=pdo_mysql
extension=curl
extension=mbstring
extension=xml
extension=zip

# macOS:
brew install php@8.2-mysql php@8.2-redis
```

### 3. أخطاء Composer

#### المشكلة: فشل تثبيت dependencies
```bash
# الخطأ:
# Your requirements could not be resolved to an installable set of packages.
```

#### الحلول:
```bash
# الحل 1: تنظيف cache
composer clear-cache
composer install --no-scripts

# الحل 2: تحديث composer
composer self-update

# الحل 3: استخدام صلاحيات المدير
sudo composer install

# الحل 4: حل تضارب dependencies
composer install --with-all-dependencies

# الحل 5: استخدام flags محددة
composer install --ignore-platform-reqs
composer install --no-dev
```

#### مشكلة: Composer memory limit
```bash
# الخطأ:
# Fatal error: Allowed memory size of X bytes exhausted
```

#### الحل:
```bash
# الحل المؤقت
php -d memory_limit=-1 /usr/local/bin/composer install

# الحل الدائم
# إضافة إلى ~/.bashrc أو ~/.zshrc:
alias composer='php -d memory_limit=-1 /usr/local/bin/composer'
```

---

## 🗄️ مشاكل قاعدة البيانات

### 1. أخطاء الاتصال بقاعدة البيانات

#### المشكلة:_connection refused_
```bash
# الخطأ:
# SQLSTATE[HY000] [2002] Connection refused
```

#### التشخيص:
```bash
# فحص حالة MySQL
# Linux
sudo systemctl status mysql
sudo systemctl status mysqld

# Windows (XAMPP)
# فحص Apache/MySQL في XAMPP Control Panel

# فحص المنافذ
netstat -tulpn | grep :3306
```

#### الحلول:
```bash
# الحل 1: بدء خدمة MySQL
# Linux
sudo systemctl start mysql
sudo systemctl enable mysql

# Windows - تشغيل XAMPP
# بدء Apache و MySQL من Control Panel

# الحل 2: فحص إعدادات .env
cat .env | grep DB_

# يجب أن تكون:
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=v5_system
DB_USERNAME=root
DB_PASSWORD=

# الحل 3: اختبار الاتصال
php artisan tinker
>>> DB::connection()->getPdo();
```

### 2. أخطاء الوصول لقاعدة البيانات

#### المشكلة:_access denied for user_
```bash
# الخطأ:
# SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost'
```

#### الحلول:
```bash
# الحل 1: إعادة تعيين كلمة مرور MySQL
# Linux
sudo mysql -u root -p
ALTER USER 'root'@'localhost' IDENTIFIED BY 'newpassword';
FLUSH PRIVILEGES;
EXIT;

# Windows (XAMPP)
# 1. إيقاف MySQL في XAMPP
# 2. تشغيل في safe mode
mysqld --skip-grant-tables --skip-networking
# 3. في terminal آخر:
mysql -u root
UPDATE mysql.user SET authentication_string = PASSWORD('newpassword') WHERE User = 'root';
FLUSH PRIVILEGES;

# الحل 2: إنشاء مستخدم جديد
sudo mysql -u root -p
CREATE DATABASE v5_system;
CREATE USER 'v5_user'@'localhost' IDENTIFIED BY 'v5_password';
GRANT ALL PRIVILEGES ON v5_system.* TO 'v5_user'@'localhost';
FLUSH PRIVILEGES;
```

### 3. أخطاء Migration

#### المشكلة: table doesn't exist
```bash
# الخطأ:
# Table 'v5_system.users' doesn't exist
```

#### الحلول:
```bash
# الحل 1: تشغيل migrations من جديد
php artisan migrate:fresh
php artisan migrate:fresh --seed

# الحل 2: فحص حالة migrations
php artisan migrate:status

# الحل 3: إعادة تشغيل migration محددة
php artisan migrate:rollback
php artisan migrate

# الحل 4: إنشاء migration يدوياً
php artisan make:migration create_test_table
php artisan migrate
```

### 4. مشاكل SQLite

#### المشكلة: SQLite file permissions
```bash
# الخطأ:
# SQLite SQLSTATE[HY000] [14] unable to open database file
```

#### الحلول:
```bash
# الحل 1: إنشاء ملف SQLite
touch database/database.sqlite
chmod 664 database/database.sqlite

# الحل 2: تحديث .env
DB_CONNECTION=sqlite
DB_DATABASE=/full/path/to/database.sqlite

# الحل 3: اختبار SQLite
php artisan tinker
>>> DB::connection()->getPdo();
```

---

## 📦 مشاكل Node.js و npm

### 1. أخطاء إصدار Node.js

#### المشكلة: Node.js version too old
```bash
# الخطأ:
# Node.js version 16.x detected. Requires 18.x or higher
```

#### الحلول:
```bash
# الحل 1: تحديث Node.js
# Windows/macOS: تحميل من nodejs.org

# Linux (Ubuntu):
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs

# macOS:
brew install node@20
brew link node@20 --force

# الحل 2: استخدام nvm (مُستحسن)
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash
nvm install 20
nvm use 20
nvm alias default 20
```

### 2. أخطاء npm

#### المشكلة: npm install fails
```bash
# الخطأ:
# npm ERR! code ERESOLVE
npm ERR! peer dep missing
```

#### الحلول:
```bash
# الحل 1: تنظيف npm cache
npm cache clean --force
npm cache verify

# الحل 2: حذف node_modules
rm -rf node_modules package-lock.json
npm install

# الحل 3: استخدام flags محددة
npm install --legacy-peer-deps
npm install --force
npm install --no-optional

# الحل 4: تحديث npm
npm install -g npm@latest
```

### 3. مشاكل Vite

#### المشكلة: Vite dev server won't start
```bash
# الخطأ:
# Error: Port 5173 is already in use
```

#### الحلول:
```bash
# الحل 1: إنهاء العمليات على المنفذ
# Windows
netstat -ano | findstr :5173
taskkill /PID <PID_NUMBER> /F

# Linux/macOS
lsof -ti:5173 | xargs kill -9

# الحل 2: استخدام منفذ آخر
npm run dev -- --port 3000

# الحل 3: تحديث Vite
npm update vite
npm install vite@latest
```

---

## 🔌 أخطاء في المنافذ

### 1. منفذ Laravel (8000) مشغول

#### المشكلة: Port 8000 already in use
```bash
# الخطأ:
# Laravel development server started: http://127.0.0.1:8000
# Address already in use
```

#### الحلول:
```bash
# الحل 1: استخدام منفذ آخر
php artisan serve --port=8080
php artisan serve --port=3000

# الحل 2: العثور على العملية وإنهاؤها
# Windows
netstat -ano | findstr :8000
taskkill /PID <PID_NUMBER> /F

# Linux/macOS
lsof -ti:8000 | xargs kill -9
# أو
fuser -k 8000/tcp

# الحل 3: استخدام جميع المنافذ المتاحة
php artisan serve --host=0.0.0.0 --port=8001
```

### 2. منفذ MySQL (3306) مشغول

#### الحلول:
```bash
# فحص المنافذ المشغولة
netstat -tulpn | grep :3306

# تغيير منفذ MySQL
# إضافة إلى /etc/mysql/mysql.conf.d/mysqld.cnf:
port = 3307

# تحديث .env:
DB_PORT=3307
```

### 3. منفذ Redis (6379) مشغول

#### الحلول:
```bash
# فحص Redis
redis-cli ping

# تغيير منفذ Redis
# إضافة إلى /etc/redis/redis.conf:
port 6380

# تحديث .env:
REDIS_PORT=6380

# أو تعطيل Redis
# في .env:
REDIS_HOST=127.0.0.1:6380
```

---

## 🔐 مشاكل الصلاحيات

### 1. مشاكل صلاحيات Linux/macOS

#### المشكلة: Permission denied
```bash
# الخطأ:
# mkdir(): Permission denied
# file_put_contents(): failed to open stream: Permission denied
```

#### الحلول:
```bash
# الحل 1: تعيين صلاحيات المجلدات
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/

# الحل 2: تعيين المالك
sudo chown -R www-data:www-data storage/
sudo chown -R www-data:www-data bootstrap/cache/

# أو إذا كنت المستخدم الحالي
sudo chown -R $USER:www-data storage/
sudo chown -R $USER:www-data bootstrap/cache/

# الحل 3: إنشاء المجلدات المفقودة
mkdir -p storage/logs
mkdir -p storage/framework/{sessions,views,cache}
mkdir -p storage/app/public
mkdir -p bootstrap/cache

# تعيين الصلاحيات
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/
```

### 2. مشاكل صلاحيات الملفات

#### الحلول:
```bash
# فحص الصلاحيات
ls -la storage/
ls -la bootstrap/cache/

# إصلاح شامل
sudo chown -R $USER:$USER .
chmod -R 755 .
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/
chmod -R 644 .env
```

### 3. مشاكل صلاحيات Windows

#### الحلول:
```cmd
# فحص الصلاحيات
icacls storage/
icacls bootstrap\cache\

# إصلاح الصلاحيات
icacls storage /grant Users:F /T
icacls bootstrap\cache /grant Users:F /T
```

---

## 🛠️ أخطاء Laravel

### 1. أخطاء Key غير موجود

#### المشكلة: No application encryption key has been specified
```bash
# الخطأ:
# No application encryption key has been specified.
```

#### الحل:
```bash
# توليد مفتاح التطبيق
php artisan key:generate

# النتيجة المتوقعة:
# Application key set successfully.
```

### 2. أخطاء Autoloader

#### المشكلة: Class not found
```bash
# الخطأ:
# Class 'App\Models\User' not found
```

#### الحلول:
```bash
# الحل 1: إعادة تحميل autoloader
composer dump-autoload

# الحل 2: إعادة تثبيت dependencies
composer install
composer dump-autoload

# الحل 3: مسح cache
php artisan cache:clear
php artisan config:clear
composer dump-autoload
```

### 3. أخطاء Configuration

#### المشكلة: Configuration cache
```bash
# الخطأ:
# Configuration cache not found. Run 'php artisan config:cache' first
```

#### الحلول:
```bash
# مسح cache الإعدادات
php artisan config:clear

# إعادة تخزين cache
php artisan config:cache

# فحص ملف الإعدادات
cat config/app.php | grep 'key'
```

### 4. أخطاء Routes

#### المشكلة: Route not defined
```bash
# الخطأ:
# Route [login] not defined
```

#### الحلول:
```bash
# مسح cache routes
php artisan route:clear

# إعادة تخزين routes
php artisan route:cache

# فحص routes
php artisan route:list
php artisan route:list | grep login
```

---

## 💾 مشاكل التخزين والكاش

### 1. مشاكل Storage Link

#### المشكلة: Storage link broken
```bash
# الخطأ:
# The public/disk link does not exist
```

#### الحلول:
```bash
# إعادة إنشاء الرابط
php artisan storage:link

# فحص الرابط
ls -la public/storage

# إنشاء الرابط يدوياً
ln -s /path/to/project/storage/app/public public/storage
```

### 2. مشاكل Cache

#### المشاكل الشائعة:
```bash
# Cache configuration
# Permission denied
# Cache doesn't update
```

#### الحلول:
```bash
# مسح جميع أنواع الكاش
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# إعادة تخزين الكاش
php artisan config:cache
php artisan route:cache
php artisan view:cache

# فحص مجلدات الكاش
ls -la storage/framework/cache/
ls -la bootstrap/cache/
```

### 3. مشاكل Session

#### المشكلة: Session driver not found
```bash
# الخطأ:
# Session store not set on request
```

#### الحلول:
```bash
# إنشاء جدول sessions
php artisan session:table
php artisan migrate

# تحديث .env
SESSION_DRIVER=database
SESSION_LIFETIME=120

# أو استخدام ملف
SESSION_DRIVER=file
SESSION_LIFETIME=120
```

---

## 🔐 مشاكل Authentication

### 1. مشاكل تسجيل الدخول

#### المشكلة: Invalid credentials
```bash
# الخطأ:
# These credentials do not match our records
```

#### الحلول:
```bash
# إنشاء مستخدم جديد
php artisan make:user

# أو استخدام Tinker
php artisan tinker
>>> $user = new App\Models\User();
>>> $user->name = 'Admin User';
>>> $user->email = 'admin@test.com';
>>> $user->username = 'admin';
>>> $user->password = Hash::make('password123');
>>> $user->email_verified_at = now();
>>> $user->save();
>>> $user->assignRole('admin');

# فحص المستخدم
>>> $user = App\Models\User::where('email', 'admin@test.com')->first();
>>> $user->roles
```

### 2. مشاكل Session

#### المشكلة: CSRF token mismatch
```bash
# الخطأ:
# The page has expired due to inactivity
# CSRF token mismatch
```

#### الحلول:
```bash
# مسح جلسة المستخدم
php artisan session:clear

# تحديث .env
SESSION_DRIVER=file
SESSION_LIFETIME=120

# فحص إعدادات CSRF
cat config/session.php | grep 'csrf'
```

### 3. مشاكل Password Reset

#### المشكلة: Password reset token invalid
```bash
# الخطأ:
# The password reset token is invalid
```

#### الحلول:
```bash
# إنشاء جدول password_resets
php artisan make:migration create_password_resets_table
php artisan migrate

# أو استخدام migration جاهز
php artisan migrate:refresh --path=/database/migrations/2014_10_12_100000_create_password_resets_table.php
```

---

## ⚡ مشاكل الأداء

### 1. بطء في التحميل

#### الحلول:
```bash
# تحسين الأداء
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache

# فحص الذاكرة
php artisan tinker
>>> memory_get_usage(true) / 1024 / 1024 . ' MB';
```

### 2. مشاكل قاعدة البيانات البطيئة

#### الحلول:
```bash
# فحص الاستعلامات البطيئة
php artisan db:show
php artisan db:table users

# إنشاء indices إضافية
php artisan make:migration add_indexes_to_tables
# إضافة indices في migration
```

### 3. مشاكل Cache

#### الحلول:
```bash
# تحسين cache
php artisan cache:clear
php artisan config:cache
php artisan route:cache

# استخدام Redis
# تحديث .env:
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

---

## 🌐 مشاكل الشبكة والاتصال

### 1. مشاكل CORS

#### المشكلة: CORS error
```bash
# الخطأ:
# Access to XMLHttpRequest has been blocked by CORS policy
```

#### الحلول:
```bash
# إضافة CORS middleware
php artisan make:middleware CorsMiddleware

# في app/Http/Middleware/CorsMiddleware.php:
<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

class CorsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        return $response;
    }
}
```

### 2. مشاكل HTTPS

#### الحلول:
```bash
# في .env:
APP_URL=https://localhost
SESSION_SECURE_COOKIE=true
FORCE_HTTPS=true

# في config/app.php:
'url' => env('APP_URL', 'https://localhost'),
```

### 3. مشاكل DNS

#### الحلول:
```bash
# فحص DNS resolution
nslookup localhost
ping localhost

# تحديث hosts file
# Windows: C:\Windows\System32\drivers\etc\hosts
# Linux/macOS: /etc/hosts
127.0.0.1 localhost
127.0.0.1 your-domain.local
```

---

## 🛡️ أخطاء الأمان

### 1. مشاكل SSL/TLS

#### الحلول:
```bash
# إنشاء شهادة SSL محلية
openssl req -x509 -newkey rsa:4096 -keyout storage/certs/key.pem -out storage/certs/cert.pem -days 365 -nodes

# تحديث .env:
APP_URL=https://localhost:8000
SESSION_SECURE_COOKIE=true
```

### 2. مشاكل File Permissions

#### الحلول:
```bash
# فحص صلاحيات الملفات
find . -type f -perm 777
find . -type d -perm 777

# إصلاح الصلاحيات
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod 600 .env
chmod 775 storage/ bootstrap/cache/
```

---

## 🔧 أدوات التشخيص

### 1. سكريبت تشخيص شامل
```bash
#!/bin/bash
# diagnostics.sh

echo "=== System Information ==="
uname -a
php -v
composer --version
node -v
npm -v

echo "=== PHP Extensions ==="
php -m | grep -E "mysql|redis|mbstring|curl|zip|xml"

echo "=== Laravel Status ==="
php artisan --version
php artisan about

echo "=== Database Status ==="
php artisan migrate:status
php artisan tinker --execute="echo 'DB Connection: ' . (DB::connection()->getPdo() ? 'OK' : 'FAILED');"

echo "=== Disk Space ==="
df -h

echo "=== Memory Usage ==="
free -h

echo "=== Running Services ==="
netstat -tulpn | grep -E ":80|:3306|:6379"
```

### 2. فحص Log Files
```bash
# عرض آخر 50 سطر من Laravel log
tail -50 storage/logs/laravel.log

# عرض آخر أخطاء
tail -50 storage/logs/laravel.log | grep -i error

# مراقبة log في الوقت الفعلي
tail -f storage/logs/laravel.log
```

### 3. فحص Health
```bash
# إنشاء health check endpoint
echo '<?php
header("Content-Type: application/json");
try {
    $pdo = new PDO("mysql:host=localhost;dbname=test", "root", "");
    echo json_encode(["status" => "healthy", "db" => "connected"]);
} catch (PDOException $e) {
    echo json_encode(["status" => "unhealthy", "error" => $e->getMessage()]);
}
?>' > public/health.php
```

---

## 📞 طلب المساعدة

### معلومات يجب تجميعها قبل طلب المساعدة:

```bash
# معلومات النظام
echo "=== System Info ===" > support-info.txt
uname -a >> support-info.txt
php -v >> support-info.txt
composer --version >> support-info.txt
node -v >> support-info.txt

# معلومات Laravel
echo "=== Laravel Info ===" >> support-info.txt
php artisan --version >> support-info.txt
php artisan about >> support-info.txt

# Logs
echo "=== Recent Errors ===" >> support-info.txt
tail -20 storage/logs/laravel.log >> support-info.txt

# Configuration
echo "=== Environment ===" >> support-info.txt
cat .env >> support-info.txt
```

### عناوين البريد للدعم:
- **الدعم الفني**: support@v5-system.com
- **المشاكل الطارئة**: emergency@v5-system.com
- **الأمان**: security@v5-system.com

### معلومات مفيدة للدعم:
- وصف مفصل للمشكلة
- خطوات إعادة إنتاج الخطأ
- رسائل الخطأ كاملة
- لقطات شاشة
- معلومات النظام

---

**🎯 نصائح للنجاح:**

1. **اقرأ الأخطاء بعناية** - غالباً ما تحتوي رسائل الخطأ على الحل
2. **استخدم Google** - ابحث عن رسالة الخطأ بالضبط
3. **راجع Logs** - سجلات الأخطاء تحتوي على تفاصيل مهمة
4. **اختبر الحلول تدريجياً** - جرب حل واحد في كل مرة
5. **احتفظ بنسخ احتياطية** - قبل إجراء أي تغييرات مهمة

---

**آخر تحديث**: 2025-11-06  
**رقم الإصدار**: 1.0  
**بواسطة**: فريق تطوير V5