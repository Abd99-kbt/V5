# دليل النشر الأساسي للإنتاج - Laravel Application

## نظرة عامة
هذا الدليل يغطي الخطوات الأساسية اللازمة لنشر تطبيق Laravel للإنتاج بشكل آمن ومحسن.

---

## 1. متطلبات النظام والبيئة

### متطلبات الخادم
- **نظام التشغيل:** Ubuntu 20.04+ / CentOS 8+ / RHEL 8+
- **الذاكرة:** 2GB RAM (مستحسن 4GB+)
- **المساحة:** 20GB مساحة فارغة (مستحسن 50GB+)
- **المعالج:** 2 CPU cores (مستحسن 4+ cores)

### متطلبات البرمجيات
```bash
# PHP 8.2+ مع الإضافات المطلوبة
PHP Version: 8.2+
Required Extensions:
- BCMath PHP Extension
- Ctype PHP Extension
- cURL PHP Extension
- DOM PHP Extension
- Fileinfo PHP Extension
- Filter PHP Extension
- Intl PHP Extension
- Mbstring PHP Extension
- OpenSSL PHP Extension
- PCRE PHP Extension
- PDO PHP Extension
- Tokenizer PHP Extension
- XML PHP Extension
- GD PHP Extension أو ImageMagick
- Redis Extension
- MySQL Client
- Composer 2.5+
- Node.js 18+ and NPM 8+
```

### تثبيت المتطلبات
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-xml \
    php8.2-curl php8.2-zip php8.2-mbstring php8.2-bcmath \
    php8.2-gd php8.2-redis mysql-server redis-server \
    nginx curl git unzip

# تثبيت Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# تثبيت Node.js
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs
```

---

## 2. إعداد قاعدة البيانات

### MySQL/MariaDB
```bash
# تسجيل الدخول لـ MySQL
sudo mysql -u root -p

# إنشاء قاعدة البيانات والمستخدم
CREATE DATABASE production_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'app_user'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT ALL PRIVILEGES ON production_app.* TO 'app_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### تحسين إعدادات MySQL
إنشاء ملف `/etc/mysql/conf.d/mysql.cnf`:
```ini
[mysqld]
# إعدادات الأداء
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

# إعدادات الاتصال
max_connections = 200
connect_timeout = 60
wait_timeout = 28800

# إعدادات الاستعلام
query_cache_type = 1
query_cache_size = 64M
query_cache_limit = 2M

# إعدادات المحرك
default-storage-engine = INNODB
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
```

### إنشاء جداول التطبيق
```bash
# تشغيل migration tables
php artisan migrate --force

# تشغيل seeders إذا لزم الأمر
php artisan db:seed --force
```

---

## 3. إعدادات Redis وCache

### تثبيت Redis
```bash
# Ubuntu/Debian
sudo apt install redis-server

# تعديل إعدادات Redis
sudo nano /etc/redis/redis.conf
```

### إعدادات Redis المقترحة
```bash
# في ملف /etc/redis/redis.conf
maxmemory 512mb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
```

### اختبار Redis
```bash
# تشغيل اختبار
redis-cli ping
# يجب أن يعيد: PONG
```

### إعدادات Cache في Laravel
في ملف `.env`:
```env
# Cache Configuration
CACHE_STORE=redis
CACHE_PREFIX=production_app
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

---

## 4. إعداد متغيرات البيئة

### إنشاء ملف .env للإنتاج
```env
# ===========================================
# PRODUCTION ENVIRONMENT VARIABLES
# ===========================================

# Application Configuration
APP_NAME="نظام إدارة المبيعات"
APP_ENV=production
APP_KEY=base64:YOUR_GENERATED_KEY_HERE
APP_DEBUG=false
APP_URL=https://yourdomain.com
APP_LOCALE=ar
APP_FALLBACK_LOCALE=en

# Security Settings
BCRYPT_ROUNDS=12
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_DOMAIN=.yourdomain.com
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=strict

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=production_app
DB_USERNAME=app_user
DB_PASSWORD=strong_password_here

# Redis Configuration
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Queue Configuration
QUEUE_CONNECTION=redis

# Cache Configuration
CACHE_STORE=redis
CACHE_PREFIX=production_app

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.yourprovider.com
MAIL_PORT=587
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=your_smtp_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"

# Logging Configuration
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning
LOG_DEPRECATIONS_CHANNEL=null

# Security Headers
FORCE_HTTPS=true
APP_TRUST_PROXIES=*

# Performance Settings
TELESCOPE_ENABLED=false
DEBUGBAR_ENABLED=false

# AWS/S3 Configuration (اختياري)
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-s3-bucket
AWS_USE_PATH_STYLE_ENDPOINT=false

# Rate Limiting
API_RATE_LIMIT=60
AUTH_RATE_LIMIT=5
LOGIN_RATE_LIMIT=3

# Broadcasting (إذا كان مطلوب)
BROADCAST_CONNECTION=redis
PUSHER_APP_ID=your_pusher_app_id
PUSHER_APP_KEY=your_pusher_app_key
PUSHER_APP_SECRET=your_pusher_app_secret
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1
```

### توليد APP_KEY
```bash
# توليد مفتاح التطبيق
php artisan key:generate --force
```

---

## 5. إعداد SSL وHTTPS

### تثبيت Certbot
```bash
# Ubuntu/Debian
sudo apt install certbot python3-certbot-nginx

# أو تثبيت standalone
sudo apt install certbot
```

### الحصول على شهادة SSL
```bash
# للحصول على شهادة nginx
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com

# أو شهادة standalone (يجب إيقاف nginx مؤقتاً)
sudo certbot certonly --standalone -d yourdomain.com
```

### تجديد الشهادات التلقائي
```bash
# إضافة cron job
sudo crontab -e

# إضافة السطر التالي
0 12 * * * /usr/bin/certbot renew --quiet
```

### إعدادات Nginx لـ HTTPS
```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;
    
    root /var/www/your-app/public;
    index index.php;
    
    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;
    
    # Security Headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    
    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied expired no-cache no-store private must-revalidate auth;
    gzip_types
        text/plain
        text/css
        text/xml
        text/javascript
        application/javascript
        application/xml+rss
        application/json;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    
    error_page 404 /index.php;
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        
        # Timeouts
        fastcgi_connect_timeout 60s;
        fastcgi_send_timeout 60s;
        fastcgi_read_timeout 60s;
    }
    
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## 6. تحسين إعدادات الخادم

### إعدادات PHP-FPM
إنشاء ملف `/etc/php/8.2/fpm/pool.d/www.conf`:
```ini
[www]
user = www-data
group = www-data
listen = /var/run/php/php8.2-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

# Process Management
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500

# Performance Settings
request_terminate_timeout = 120s
rlimit_files = 65536
rlimit_core = 0

# Environment Variables
env[HOSTNAME] = $HOSTNAME
env[PATH] = /usr/local/bin:/usr/bin:/bin
env[TMP] = /tmp
env[TMPDIR] = /tmp
env[TEMP] = /tmp

# PHP Settings
php_admin_value[error_log] = /var/log/php8.2-fpm.log
php_admin_flag[log_errors] = on
php_admin_value[memory_limit] = 256M
php_admin_value[max_execution_time] = 60
php_admin_value[upload_max_filesize] = 20M
php_admin_value[post_max_size] = 25M
```

### إعدادات PHP العامة
إنشاء ملف `/etc/php/8.2/fpm/conf.d/99-production.ini`:
```ini
; Production PHP Settings
memory_limit = 256M
max_execution_time = 60
max_input_time = 60
post_max_size = 25M
upload_max_filesize = 20M
max_file_uploads = 20
date.timezone = UTC

; Error Reporting (تحديد الأخطاء المهمة فقط)
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE
display_errors = Off
display_startup_errors = Off
log_errors = On
log_errors_max_len = 1024
ignore_repeated_errors = On
ignore_repeated_source = Off

; OPcache Settings
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.fast_shutdown=1

; Session Settings
session.gc_maxlifetime = 7200
session.cookie_lifetime = 7200
session.cookie_secure = 1
session.cookie_httponly = 1
session.cookie_samesite = Strict
```

### إعدادات Nginx العامة
إنشاء ملف `/etc/nginx/nginx.conf`:
```nginx
user www-data;
worker_processes auto;
worker_rlimit_nofile 65535;

events {
    worker_connections 4096;
    use epoll;
    multi_accept on;
}

http {
    # Basic Settings
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    keepalive_requests 1000;
    types_hash_max_size 2048;
    server_tokens off;
    
    # MIME Types
    include /etc/nginx/mime.types;
    default_type application/octet-stream;
    
    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    
    # Gzip Settings
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_comp_level 6;
    gzip_proxied any;
    gzip_types
        text/plain
        text/css
        text/xml
        text/javascript
        application/javascript
        application/xml+rss
        application/json
        application/xml+dtd
        application/atom+xml
        image/svg+xml;
    
    # Rate Limiting
    limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
    limit_req_zone $binary_remote_addr zone=login:10m rate=1r/s;
    
    # Logging
    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for"';
    
    access_log /var/log/nginx/access.log main;
    error_log /var/log/nginx/error.log warn;
    
    # File Upload Settings
    client_max_body_size 25M;
    client_body_buffer_size 128k;
    client_header_buffer_size 1k;
    large_client_header_buffers 4 4k;
    
    # Timeouts
    client_body_timeout 12;
    client_header_timeout 12;
    keepalive_timeout 15;
    send_timeout 10;
    
    # Include site configurations
    include /etc/nginx/conf.d/*.conf;
    include /etc/nginx/sites-enabled/*;
}
```

---

## 7. خطوات النشر الأساسية

### 1. تحضير الخادم
```bash
# تحديث النظام
sudo apt update && sudo apt upgrade -y

# إنشاء مجلد التطبيق
sudo mkdir -p /var/www
sudo chown www-data:www-data /var/www

# تعطيل الوصول root
sudo usermod -L root
```

### 2. رفع ملفات التطبيق
```bash
# نسخ ملفات التطبيق
rsync -avz --exclude='node_modules' --exclude='.git' \
    --exclude='vendor' --exclude='storage/logs' \
    /path/to/local/app/ /var/www/your-app/

# تعيين الصلاحيات
sudo chown -R www-data:www-data /var/www/your-app
sudo chmod -R 755 /var/www/your-app
sudo chmod -R 775 /var/www/your-app/storage
sudo chmod -R 775 /var/www/your-app/bootstrap/cache
```

### 3. تثبيت Dependencies
```bash
cd /var/www/your-app
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data npm ci --production
sudo -u www-data npm run build
```

### 4. إعداد قاعدة البيانات
```bash
# تشغيل migrations
sudo -u www-data php artisan migrate --force

# إنشاء indexing إضافي للإنتاج
sudo -u www-data php artisan db:seed --force

# إنشاء caches
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
```

### 5. إعداد الأذونات النهائية
```bash
# تأمين ملفات النظام
sudo chmod 600 /var/www/your-app/.env
sudo chmod 600 /var/www/your-app/composer.json
sudo chmod 600 /var/www/your-app/package.json

# تجديد certbot شهادات
sudo certbot renew --nginx
```

### 6. بدء الخدمات
```bash
# إعادة تشغيل PHP-FPM
sudo systemctl restart php8.2-fpm

# إعادة تشغيل Nginx
sudo systemctl restart nginx

# إعادة تشغيل Redis
sudo systemctl restart redis

# تمكين الخدمات
sudo systemctl enable php8.2-fpm nginx redis mysql
```

### 7. اختبار النشر
```bash
# اختبار الوصول للموقع
curl -I https://yourdomain.com

# اختبار قاعدة البيانات
sudo -u www-data php artisan migrate:status

# اختبار Cache
sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan cache:clear

# اختبار Queue
sudo -u www-data php artisan queue:work --tries=3 --timeout=90
```

---

## 8. الفحوصات الأمنية الأساسية

### فحص الثغرات
```bash
# تشغيل فحص الأمان المدمج
composer security-check

# فحص تحديثات الأمان
composer audit

# فحص لينت PHP
php -l /var/www/your-app/artisan
```

### إعداد Firewall
```bash
# تمكين UFW
sudo ufw enable

# السماح بالخدمات الأساسية
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# فحص حالة UFW
sudo ufw status verbose
```

### تأمين SSH
```bash
# تعديل إعدادات SSH
sudo nano /etc/ssh/sshd_config

# التغييرات المطلوبة:
# Port 2222
# PermitRootLogin no
# PasswordAuthentication no
# PubkeyAuthentication yes

# إعادة تشغيل SSH
sudo systemctl restart ssh
```

---

## الخلاصة
هذا الدليل يغطي الخطوات الأساسية لنشر تطبيق Laravel في بيئة الإنتاج. تأكد من:
- إعداد كلمات مرور قوية
- تفعيل HTTPS
- تأمين قاعدة البيانات
- إعداد الـ backups
- مراقبة الأداء

في القسم التالي، سنتناول الدليل المتقدم الذي يشمل Docker وKubernetes.