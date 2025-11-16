# دليل الإرشادات المتقدمة - نظام V5
## تشغيل متقدم وإدارة النظام

---

## 📋 المحتويات

1. [التشغيل في وضع التطوير](#-التشغيل-في-وضع-التطوير)
2. [التشغيل في وضع الإنتاج المحلي](#-التشغيل-في-وضع-الإنتاج-المحلي)
3. [مراقبة Logs](#-مراقبة-logs)
4. [استخدام الأدوات المدمجة](#-استخدام-الأدوات-المدمجة)
5. [إعدادات الأمان المتقدمة](#-إعدادات-الأمان-المتقدمة)
6. [تحسين الأداء](#-تحسين-الأداء)
7. [إدارة قاعدة البيانات المتقدمة](#-إدارة-قاعدة-البيانات-المتقدمة)
8. [المراقبة والتشخيص](#-المراقبة-والتشخيص)
9. [أتمتة المهام](#-أتمتة-المهام)
10. [إدارة الإصدارات](#-إدارة-الإصدارات)

---

## 🛠️ التشغيل في وضع التطوير

### 1. إعدادات التطوير المتقدمة

#### ملف .env للتطوير
```env
# Application Configuration
APP_NAME="V5 Sales System"
APP_ENV=local
APP_KEY=base64:generated_key_here
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Damascus

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=v5_development
DB_USERNAME=v5_dev_user
DB_PASSWORD=dev_password_123

# Cache Configuration
CACHE_DRIVER=file
CACHE_PREFIX=v5_dev_cache

# Session Configuration
SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/tmp
SESSION_DOMAIN=

# Queue Configuration
QUEUE_CONNECTION=sync

# Mail Configuration
MAIL_MAILER=log
MAIL_HOST=localhost
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="dev@v5-system.com"
MAIL_FROM_NAME="${APP_NAME}"

# Development Tools
TELESCOPE_ENABLED=true
DEBUGBAR_ENABLED=true
LOG_LEVEL=debug

# Vite Configuration
VITE_APP_NAME="${APP_NAME}"
```

### 2. تشغيل خوادم التطوير

#### التشغيل المتعدد الخدمات
```bash
# تشغيل جميع الخدمات في مرة واحدة
./start-local.sh

# أو استخدام concurrently مباشرة
npx concurrently \
  "php artisan serve --host=0.0.0.0 --port=8000" \
  "npm run dev -- --host=0.0.0.0 --port=5173" \
  "php artisan queue:work --verbose" \
  "php artisan horizon" \
  --names="Laravel,Vite,Queue,Horizon" --kill-others
```

#### التشغيل مع Supervisord
```bash
# إنشاء ملف إعدادات Supervisord
sudo nano /etc/supervisor/conf.d/v5-dev.conf

# محتوى الملف:
[program:v5-laravel]
command=php /path/to/project/artisan serve --host=0.0.0.0 --port=8000
directory=/path/to/project
user=www-data
autostart=true
autorestart=true
stderr_logfile=/var/log/v5/laravel.err.log
stdout_logfile=/var/log/v5/laravel.out.log

[program:v5-vite]
command=npm run dev
directory=/path/to/project
user=www-data
autostart=true
autorestart=true
stderr_logfile=/var/log/v5/vite.err.log
stdout_logfile=/var/log/v5/vite.out.log

[program:v5-queue]
command=php /path/to/project/artisan queue:work
directory=/path/to/project
user=www-data
autostart=true
autorestart=true
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/v5/queue.log
```

### 3. إعدادات IDE والبيئة

#### VS Code Settings
```json
// .vscode/settings.json
{
    "php.validate.executablePath": "/usr/bin/php",
    "php.suggest.basic": false,
    "php.codeSniffer.standard": "PSR12",
    "intelephense.files.maxSize": 5000000,
    "intelephense.telemetry.enabled": false,
    "intelephense.files.maxMemory": 4096,
    "phpunit.phpunit": "./vendor/bin/phpunit",
    "phpunit.allowsdux": true,
    "laravel-ide-helper.generate_models": true,
    "laravel-ide-helper.generate_events": true,
    "laravel-ide-helper.generate_facades": true,
    "laravel-ide-helper.meta_filename": "_ide_helper_meta.php"
}
```

#### Xdebug Configuration
```ini
; في php.ini للتطوير
[xdebug]
zend_extension=xdebug.so
xdebug.mode=debug
xdebug.start_with_request=yes
xdebug.client_host=localhost
xdebug.client_port=9003
xdebug.log=/var/log/xdebug.log
xdebug.idekey=VSCODE
xdebug.profiler_enable=1
xdebug.profiler_output_dir=/tmp
```

---

## 🏭 التشغيل في وضع الإنتاج المحلي

### 1. إعدادات الإنتاج

#### ملف .env للإنتاج المحلي
```env
# Application Configuration
APP_NAME="V5 Sales System"
APP_ENV=production
APP_KEY=base64:production_key_here
APP_DEBUG=false
APP_URL=https://localhost:8443
APP_TIMEZONE=Asia/Damascus

# Security
APP_SECURE_COOKIES=true
FORCE_HTTPS=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=strict

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=v5_production
DB_USERNAME=v5_prod_user
DB_PASSWORD=secure_prod_password_123

# Cache Configuration
CACHE_DRIVER=redis
CACHE_PREFIX=v5_prod_cache
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=redis_password_123
REDIS_PORT=6379

# Session Configuration
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true

# Queue Configuration
QUEUE_CONNECTION=redis

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=noreply@v5-system.com
MAIL_PASSWORD=app_specific_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@v5-system.com"
MAIL_FROM_NAME="${APP_NAME}"

# Storage
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=v5-production-bucket
AWS_USE_PATH_STYLE_ENDPOINT=false

# Logging
LOG_LEVEL=warning
LOG_CHANNEL=daily

# Security Headers
SECURITY_HEADERS_ENABLED=true
CSP_ENABLED=true
```

### 2. إعداد Nginx للإنتاج المحلي

#### ملف إعدادات Nginx
```nginx
# /etc/nginx/sites-available/v5-production
server {
    listen 80;
    server_name localhost v5.local;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name localhost v5.local;
    root /path/to/project/public;
    index index.php index.html;

    # SSL Configuration
    ssl_certificate /path/to/ssl/cert.pem;
    ssl_certificate_key /path/to/ssl/key.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';" always;

    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;

    # Laravel Configuration
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Security
    client_max_body_size 10M;
    server_tokens off;
}
```

### 3. PHP-FPM Configuration

#### إعدادات PHP-FPM
```ini
; /etc/php/8.2/fpm/pool.d/v5.conf
[v5]
user = www-data
group = www-data
listen = /run/php/php8.2-fpm-v5.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 15
pm.max_requests = 1000
pm.process_idle_timeout = 60s

; Performance tuning
php_admin_value[error_log] = /var/log/php8.2-fpm-v5.log
php_admin_flag[log_errors] = on
php_value[session.save_handler] = redis
php_value[session.save_path] = "tcp://127.0.0.1:6379?auth=redis_password_123"
php_value[soap.wsdl_cache_dir] = /var/lib/php/soap_cache
```

---

## 📊 مراقبة Logs

### 1. إعداد نظام Log

#### إعدادات Log في config/logging.php
```php
<?php
return [
    'default' => env('LOG_CHANNEL', 'stack'),
    'deprecations' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily', 'slack'],
            'ignore_exceptions' => false,
        ],
        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => env('LOG_LEVEL', 'critical'),
        ],
        'system' => [
            'driver' => 'daily',
            'path' => storage_path('logs/system.log'),
            'level' => 'info',
            'days' => 7,
        ],
        'security' => [
            'driver' => 'daily',
            'path' => storage_path('logs/security.log'),
            'level' => 'info',
            'days' => 30,
        ],
        'performance' => [
            'driver' => 'daily',
            'path' => storage_path('logs/performance.log'),
            'level' => 'info',
            'days' => 7,
        ],
    ],
];
```

### 2. مراقبة Logs في الوقت الفعلي

#### سكريبت مراقبة Logs
```bash
#!/bin/bash
# monitor-logs.sh

echo "🔍 مراقبة ملفات Log..."

# مراقبة Laravel log
echo "📋 مراقبة Laravel Log:"
tail -f storage/logs/laravel.log

# مراقبة في terminal آخر
echo "🔐 مراقبة Security Log:"
tail -f storage/logs/security.log

# مراقبة في terminal آخر
echo "⚡ مراقبة Performance Log:"
tail -f storage/logs/performance.log

# مراقبة أخطاء PHP
echo "🐘 مراقبة PHP Errors:"
tail -f /var/log/php8.2-fpm.log

# مراقبة Nginx
echo "🌐 مراقبة Nginx:"
sudo tail -f /var/log/nginx/access.log
sudo tail -f /var/log/nginx/error.log
```

### 3. تحليل Logs

#### سكريبت تحليل Log
```bash
#!/bin/bash
# analyze-logs.sh

echo "📊 تحليل ملفات Log..."

# إحصائيات الأخطاء
echo "🚨 إحصائيات الأخطاء (آخر 24 ساعة):"
find storage/logs/ -name "*.log" -mtime -1 -exec grep -i "error\|exception\|failed" {} \; | wc -l

# أكثر الأخطاء تكراراً
echo "❗ أكثر الأخطاء تكراراً:"
grep -i "error" storage/logs/laravel.log | awk '{print $NF}' | sort | uniq -c | sort -nr | head -10

# إحصائيات الأداء
echo "⚡ إحصائيات الأداء:"
grep "execution time" storage/logs/performance.log | awk '{sum+=$NF} END {print "متوسط وقت التنفيذ: " sum/NR " ثانية"}'

# مراقبة المنافذ
echo "🔌 حالة المنافذ:"
netstat -tulpn | grep -E ":80|:443|:3306|:6379"

# مراقبة الذاكرة
echo "💾 استخدام الذاكرة:"
free -h

# مراقبة المساحة
echo "💿 استخدام المساحة:"
df -h
```

---

## 🔧 استخدام الأدوات المدمجة

### 1. Laravel Telescope

#### تفعيل Telescope
```bash
# تثبيت Telescope
composer require laravel/telescope --dev

# نشر Telescope
php artisan telescope:install

# تشغيل migrations
php artisan migrate

# تفعيل Telescope في .env
TELESCOPE_ENABLED=true
TELESCOPE_DRIVER=database
```

#### إعداد Telescope
```php
// config/telescope.php
<?php
return [
    'enabled' => env('TELESCOPE_ENABLED', false),
    'driver' => env('TELESCOPE_DRIVER', 'database'),
    'storage' => [
        'database' => [
            'connection' => env('DB_CONNECTION', 'mysql'),
            'table' => 'telescope_entries',
        ],
    ],
    'collectors' => [
        'request' => true,
        'query' => true,
        'command' => true,
        'schedule' => true,
        'job' => true,
        'cache' => true,
        'event' => true,
        'notification' => true,
    ],
];
```

### 2. Laravel Debugbar

#### تفعيل Debugbar
```bash
# تثبيت Debugbar
composer require barryvdh/laravel-debugbar --dev

# تفعيل في .env
DEBUGBAR_ENABLED=true
```

### 3. Laravel Horizon

#### إعداد Horizon
```bash
# تثبيت Horizon
composer require laravel/horizon

# نشر Horizon
php artisan horizon:install

# تشغيل migrations
php artisan migrate

# بدء Horizon
php artisan horizon
```

#### إعداد Horizon
```php
// config/horizon.php
<?php
return [
    'use' => 'default',
    'prefix' => env('HORIZON_PREFIX', 'horizon:'),
    'big_integers' => false,
    'cache' => 'default',
    'namespace' => 'App\\Jobs\\',
    'environments' => [
        'production' => [
            'monitor' => 'horizon:monitor',
        ],
        'local' => [
            'balance' => 'auto',
            'processes' => 3,
            'tries' => 1,
        ],
    ],
];
```

### 4. سكريبتات المراقبة

#### سكريبت مراقبة النظام
```bash
#!/bin/bash
# system-monitor.sh

# إعدادات
LOG_FILE="/var/log/v5-monitor.log"
THRESHOLD_CPU=80
THRESHOLD_MEMORY=80
THRESHOLD_DISK=90

# دالة تسجيل
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a $LOG_FILE
}

# مراقبة CPU
cpu_usage=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1 | tr -d ' ')
if (( $(echo "$cpu_usage > $THRESHOLD_CPU" | bc -l) )); then
    log_message "⚠️ تحذير: CPU Usage مرتفع: ${cpu_usage}%"
fi

# مراقبة الذاكرة
memory_usage=$(free | grep Mem | awk '{printf "%.1f", $3/$2 * 100.0}')
if (( $(echo "$memory_usage > $THRESHOLD_MEMORY" | bc -l) )); then
    log_message "⚠️ تحذير: Memory Usage مرتفع: ${memory_usage}%"
fi

# مراقبة المساحة
disk_usage=$(df -h / | awk 'NR==2 {print $5}' | cut -d'%' -f1)
if (( disk_usage > THRESHOLD_DISK )); then
    log_message "⚠️ تحذير: Disk Usage مرتفع: ${disk_usage}%"
fi

# مراقبة قاعدة البيانات
if ! php artisan migrate:status > /dev/null 2>&1; then
    log_message "❌ خطأ: مشكلة في قاعدة البيانات"
fi

# مراقبة الخدمات
if ! pgrep -f "php artisan serve" > /dev/null; then
    log_message "❌ تحذير: خادم Laravel لا يعمل"
fi

if ! pgrep -f "npm run dev" > /dev/null; then
    log_message "❌ تحذير: خادم Vite لا يعمل"
fi

log_message "✅ تم فحص النظام بنجاح"
```

---

## 🔒 إعدادات الأمان المتقدمة

### 1. CSP (Content Security Policy)

#### إعداد CSP
```php
// app/Http/Middleware/ContentSecurityPolicy.php
<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval' cdn.jsdelivr.net; " .
               "style-src 'self' 'unsafe-inline' cdn.jsdelivr.net fonts.googleapis.com; " .
               "font-src 'self' fonts.gstatic.com; " .
               "img-src 'self' data: https:; " .
               "connect-src 'self' wss: https:;";

        $response = $next($request);
        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
```

### 2. Rate Limiting المتقدم

#### إعداد Rate Limiting
```php
// routes/api.php
use Illuminate\Support\Facades\RateLimiter;

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
});

// Custom Rate Limiter
Route::middleware(['throttle:api'])->group(function () {
    Route::apiResource('products', ProductController::class);
});
```

### 3. Audit Logging

#### إعداد Audit Logging
```php
// app/Models/User.php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

class User extends Model
{
    use LogsActivity;
    
    protected static $logName = 'user';
    protected static $logAttributes = ['name', 'email', 'username'];
    protected static $logOnlyDirty = true;

    protected function getDescriptionForEvent(string $eventName): string
    {
        return "User has been {$eventName}";
    }
}

// في Controller
activity()
    ->performedOn($user)
    ->causedBy(Auth::user())
    ->withProperties(['ip' => request()->ip(), 'user_agent' => request()->userAgent()])
    ->log('User updated');
```

---

## ⚡ تحسين الأداء

### 1. Database Optimization

#### إعداد Query Builder
```php
// في Model
class Product extends Model
{
    protected $fillable = ['name', 'price', 'description'];
    
    // استخدام Eager Loading
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->with(['category', 'images'])
                    ->select('id', 'name', 'price', 'category_id');
    }
    
    // استخدام Indexes
    protected $indexes = [
        'status',
        'category_id',
        'price',
        'created_at'
    ];
}
```

#### Migration للـ Indexes
```php
// database/migrations/2024_01_01_000000_add_indexes_to_products.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index('status');
            $table->index('category_id');
            $table->index('price');
            $table->index('created_at');
            $table->index(['category_id', 'status']);
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['category_id']);
            $table->dropIndex(['price']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['category_id', 'status']);
        });
    }
};
```

### 2. Cache Optimization

#### إعداد Cache المتقدم
```php
// config/cache.php
return [
    'default' => env('CACHE_DRIVER', 'redis'),
    'stores' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'lock_connection' => 'default',
        ],
    ],
    'prefix' => env('CACHE_PREFIX', 'v5_cache'),
];
```

#### Cache Service
```php
// app/Services/CacheService.php
<?php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CacheService
{
    private $ttl = 3600; // 1 hour
    
    public function getProducts()
    {
        return Cache::remember('products.active', $this->ttl, function () {
            return DB::table('products')
                ->where('status', 'active')
                ->select('id', 'name', 'price')
                ->get();
        });
    }
    
    public function invalidateProducts()
    {
        Cache::forget('products.active');
    }
    
    public function rememberWithTags($key, $tags, $callback)
    {
        return Cache::tags($tags)->remember($key, $this->ttl, $callback);
    }
}
```

### 3. Queue Optimization

#### إعداد Queue
```php
// config/queue.php
return [
    'default' => env('QUEUE_CONNECTION', 'redis'),
    'connections' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => 'default',
            'retry_after' => 90,
            'block_for' => null,
        ],
    ],
];
```

#### Job Class
```php
// app/Jobs/ProcessOrder.php
<?php
namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 3;
    public $backoff = [30, 60, 120];

    public function __construct(public Order $order)
    {
        //
    }

    public function handle()
    {
        // معالجة الطلب
        $this->order->process();
        
        // إرسال إشعار
        // تحديث المخزون
        // إنشاء فاتورة
    }
}
```

---

## 📊 إدارة قاعدة البيانات المتقدمة

### 1. Database Monitoring

#### سكريبت مراقبة قاعدة البيانات
```bash
#!/bin/bash
# db-monitor.sh

DB_NAME="v5_development"
DB_USER="v5_user"
DB_PASSWORD="password"

# فحص حجم قاعدة البيانات
echo "📊 حجم قاعدة البيانات:"
mysql -u$DB_USER -p$DB_PASSWORD -e "SELECT table_schema AS 'Database', ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'DB Size in MB' FROM information_schema.TABLES WHERE table_schema = '$DB_NAME' GROUP BY table_schema;"

# فحص الجداول الكبيرة
echo "📈 أكبر الجداول:"
mysql -u$DB_USER -p$DB_PASSWORD -e "SELECT table_name AS 'Table', ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)' FROM information_schema.TABLES WHERE table_schema = '$DB_NAME' ORDER BY (data_length + index_length) DESC LIMIT 10;"

# فحص连接的 الفعالة
echo "🔌连接的 النشطة:"
mysql -u$DB_USER -p$DB_PASSWORD -e "SHOW PROCESSLIST;" | grep -E "Sleep|Query"

# فحص الاستعلامات البطيئة
echo "🐌 الاستعلامات البطيئة:"
mysql -u$DB_USER -p$DB_PASSWORD -e "SELECT * FROM mysql.slow_log ORDER BY start_time DESC LIMIT 5;"
```

### 2. Database Backup Automation

#### سكريبت النسخ الاحتياطية
```bash
#!/bin/bash
# backup-database.sh

DB_NAME="v5_development"
DB_USER="v5_user"
DB_PASSWORD="password"
BACKUP_DIR="/var/backups/v5"
DATE=$(date +%Y%m%d_%H%M%S)

# إنشاء مجلد النسخ الاحتياطية
mkdir -p $BACKUP_DIR

# نسخ احتياطية كاملة
echo "📦 إنشاء نسخ احتياطية كاملة..."
mysqldump -u$DB_USER -p$DB_PASSWORD --single-transaction --routines --triggers $DB_NAME > $BACKUP_DIR/full_backup_$DATE.sql

# ضغط النسخة الاحتياطية
gzip $BACKUP_DIR/full_backup_$DATE.sql

# حفظ آخر 7 نسخ فقط
find $BACKUP_DIR -name "full_backup_*.sql.gz" -mtime +7 -delete

# إرسال إشعار
echo "✅ تم إنشاء نسخ احتياطية بنجاح: full_backup_$DATE.sql.gz"
```

### 3. Performance Analysis

#### سكريبت تحليل الأداء
```php
<?php
// database/performance/analyzer.php
require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

class DatabasePerformanceAnalyzer
{
    public function analyzeQueries($hours = 24)
    {
        $results = DB::select("
            SELECT 
                query_time,
                lock_time,
                rows_sent,
                rows_examined,
                sql_text
            FROM mysql.slow_log 
            WHERE start_time >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            ORDER BY query_time DESC
            LIMIT 10
        ", [$hours]);

        echo "🐌 أبطأ 10 استعلامات في آخر {$hours} ساعة:\n";
        foreach ($results as $result) {
            echo "وقت الاستعلام: {$result->query_time}s\n";
            echo "الصفوف المرسلة: {$result->rows_sent}\n";
            echo "الصفوف المفحوصة: {$result->rows_examined}\n";
            echo "الاستعلام: " . substr($result->sql_text, 0, 100) . "...\n";
            echo str_repeat("-", 50) . "\n";
        }
    }

    public function suggestIndexes()
    {
        $tables = DB::select("SHOW TABLES");
        
        foreach ($tables as $table) {
            $tableName = array_values((array)$table)[0];
            $indexes = DB::select("SHOW INDEX FROM {$tableName}");
            
            echo "📊 جدول: {$tableName}\n";
            echo "الفهارس الموجودة:\n";
            foreach ($indexes as $index) {
                echo "  - {$index->Key_name} ({$index->Column_name})\n";
            }
            echo "\n";
        }
    }
}

// تشغيل التحليل
$analyzer = new DatabasePerformanceAnalyzer();
$analyzer->analyzeQueries(24);
$analyzer->suggestIndexes();
```

---

## 🔧 أتمتة المهام

### 1. Laravel Scheduler

#### إعداد Task Scheduler
```php
// app/Console/Kernel.php
<?php
namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // نسخ احتياطية يومية
        $schedule->command('backup:run')->daily()->at('02:00');

        // تنظيف السجلات القديمة
        $schedule->command('logs:clear')->weekly();

        // إرسال تقارير أسبوعية
        $schedule->command('reports:send-weekly')->weekly()->mondays()->at('09:00');

        // فحص الأمان
        $schedule->command('security:check')->daily()->at('03:00');

        // تحسين قاعدة البيانات
        $schedule->command('db:optimize')->weekly()->sundays()->at('01:00');

        // مراقبة النظام
        $schedule->command('system:monitor')->everyFiveMinutes();

        // فحص انتهاء الصلاحية للمستخدمين
        $schedule->command('users:check-expiry')->daily()->at('08:00');
    }
}
```

### 2. Custom Commands

#### إنشاء Command مخصص
```php
<?php
// app/Console/Commands/SystemMonitor.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\SystemHealthReport;

class SystemMonitor extends Command
{
    protected $signature = 'system:monitor {--email=admin@v5-system.com}';
    protected $description = 'Monitor system health and send report';

    public function handle()
    {
        $this->info('🔍 بدء فحص النظام...');

        $health = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'memory' => $this->getMemoryUsage(),
            'disk' => $this->getDiskUsage(),
        ];

        $this->displayHealth($health);

        if ($this->option('email')) {
            Mail::to($this->option('email'))->send(new SystemHealthReport($health));
        }

        return Command::SUCCESS;
    }

    private function checkDatabase()
    {
        try {
            DB::connection()->getPdo();
            return 'healthy';
        } catch (\Exception $e) {
            return 'unhealthy';
        }
    }

    private function checkCache()
    {
        try {
            $key = 'health_check_' . time();
            cache()->put($key, 'test', 10);
            $value = cache()->get($key);
            return $value === 'test' ? 'healthy' : 'unhealthy';
        } catch (\Exception $e) {
            return 'unhealthy';
        }
    }

    private function getMemoryUsage()
    {
        $memory = memory_get_usage(true);
        return [
            'used' => $this->formatBytes($memory),
            'percentage' => round(($memory / 1024 / 1024) / 1024 * 100, 2)
        ];
    }

    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes > 1024 && $i < 3; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
```

### 3. Task Automation Scripts

#### سكريبت صيانة شامل
```bash
#!/bin/bash
# maintenance.sh

echo "🔧 بدء أعمال الصيانة..."

# 1. مسح الكاش
echo "🧹 مسح الكاش..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 2. نسخ احتياطية
echo "💾 إنشاء نسخ احتياطية..."
./backup-database.sh

# 3. تحسين قاعدة البيانات
echo "⚡ تحسين قاعدة البيانات..."
php artisan db:optimize

# 4. تنظيف السجلات القديمة
echo "🗂️ تنظيف السجلات..."
find storage/logs/ -name "*.log" -mtime +30 -delete

# 5. إرسال تقرير
echo "📧 إرسال تقرير الصيانة..."
php artisan system:monitor --email=admin@v5-system.com

# 6. تحديث التبعيات
echo "📦 تحديث التبعيات..."
composer update --no-dev --optimize-autoloader

echo "✅ تمت أعمال الصيانة بنجاح!"
```

---

## 🔄 إدارة الإصدارات

### 1. Git Workflow

#### إعداد Git
```bash
# إعداد branches
git checkout -b develop
git checkout -b feature/new-feature
git checkout -b hotfix/urgent-fix

# إعداد tags للإصدارات
git tag -a v1.0.0 -m "Initial release"
git push origin v1.0.0
```

### 2. Deployment Scripts

#### سكريبت النشر
```bash
#!/bin/bash
# deploy.sh

VERSION=$1
BRANCH=${2:-main}

if [ -z "$VERSION" ]; then
    echo "الاستخدام: $0 <version> [branch]"
    exit 1
fi

echo "🚀 بدء النشر: $VERSION"

# 1. إنشاء release branch
git checkout -b release/$VERSION
git push origin release/$VERSION

# 2. تشغيل الاختبارات
echo "🧪 تشغيل الاختبارات..."
./vendor/bin/phpunit

# 3. فحص الأمان
echo "🔒 فحص الأمان..."
composer security-audit

# 4. تحسين الإنتاج
echo "⚡ تحسين الإنتاج..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 5. نسخ احتياطية
echo "💾 إنشاء نسخ احتياطية..."
./backup-database.sh

# 6. تحديث التبعيات
echo "📦 تحديث التبعيات..."
composer install --no-dev --optimize-autoloader

# 7. تشغيل migrations
echo "🗄️ تشغيل migrations..."
php artisan migrate --force

# 8. نشر
echo "🌐 نشر التطبيق..."
php artisan down
php artisan up

echo "✅ تم النشر بنجاح: $VERSION"
```

### 3. Rollback Strategy

#### سكريبت الإرجاع
```bash
#!/bin/bash
# rollback.sh

VERSION=$1

if [ -z "$VERSION" ]; then
    echo "الاستخدام: $0 <version>"
    exit 1
fi

echo "↩️ بدء الإرجاع إلى الإصدار: $VERSION"

# 1. إيقاف التطبيق
echo "⏸️ إيقاف التطبيق..."
php artisan down

# 2. استعادة قاعدة البيانات
echo "🗄️ استعادة قاعدة البيانات..."
mysql -u username -p database < backup_$VERSION.sql

# 3. العودة للكود السابق
echo "🔄 العودة للكود السابق..."
git checkout $VERSION

# 4. إعادة تثبيت التبعيات
echo "📦 إعادة تثبيت التبعيات..."
composer install --no-dev --optimize-autoloader

# 5. تشغيل migrations إذا لزم الأمر
echo "🗄️ تشغيل migrations..."
php artisan migrate --force

# 6. إعادة تشغيل التطبيق
echo "▶️ إعادة تشغيل التطبيق..."
php artisan up

echo "✅ تم الإرجاع بنجاح إلى الإصدار: $VERSION"
```

---

**🎯 نصائح للـ Advanced Users:**

1. **استخدم Containerization** - Docker لسهولة النشر
2. **راقب الأداء باستمرار** - استخدم أدوات مراقبة متقدمة
3. **اعمل نسخ احتياطية منتظمة** - جدول نسخ احتياطية آلي
4. **استخدم CI/CD** - أتمتة عملية النشر
5. **اتبع Best Practices** - Laravel Best Practices
6. **راقب Security** - فحص أمني دوري
7. **حسن قاعدة البيانات** - استخدم Indexes و Query Optimization
8. **استخدم Cache بذكاء** - Redis للمعالجة المتقدمة
9. **راقب Logs بدقة** - Use structured logging
10. **اعمل Disaster Recovery Plan** - خطة لاستعادة النظام

---

**آخر تحديث**: 2025-11-06  
**رقم الإصدار**: 1.0  
**بواسطة**: فريق تطوير V5