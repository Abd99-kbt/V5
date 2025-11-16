# دليل النشر المتقدم للإنتاج - النشر المحلي المحسن

## نظرة عامة
هذا الدليل يغطي التقنيات المتقدمة لتحسين نشر تطبيق Laravel محلياً مع تركيز على الأداء والأمان والاستقرار.

---

## 1. تحسينات الأداء المحلية

### 1.1 تحسين إعدادات PHP المتقدمة

#### إعدادات OPcache المحسنة
إنشاء ملف `/etc/php/8.2/fpm/conf.d/99-opcache-production.ini`:
```ini
; OPcache Production Settings
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=512
opcache.interned_strings_buffer=32
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.revalidate_freq=60
opcache.save_comments=1
opcache.fast_shutdown=1
opcache.optimization_level=0x7FFEBFFF
opcache.preload=/var/www/your-app/bootstrap/preload.php
opcache.preload_user=www-data
opcache.jit=1255
opcache.jit_buffer_size=64M
```

#### إنشاء ملف Preload
إنشاء `/var/www/your-app/bootstrap/preload.php`:
```php
<?php

/*
|--------------------------------------------------------------------------
| Preload File
|--------------------------------------------------------------------------
|
| This file is used to preload frequently used files into OPcache for
| better performance in production.
|
*/

$files = [
    // Core Laravel files
    __DIR__ . '/../vendor/laravel/framework/src/Illuminate/Foundation/Application.php',
    __DIR__ . '/../vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/HandleExceptions.php',
    __DIR__ . '/../vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/RegisterFacades.php',
    __DIR__ . '/../vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/RegisterProviders.php',
    __DIR__ . '/../vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/BootProviders.php',
    
    // App specific files
    __DIR__ . '/../app/Providers/AppServiceProvider.php',
    __DIR__ . '/../app/Providers/AuthServiceProvider.php',
    __DIR__ . '/../app/Providers/EventServiceProvider.php',
    __DIR__ . '/../app/Providers/RouteServiceProvider.php',
    
    // Common service classes
    __DIR__ . '/../app/Services/AuthenticationService.php',
    __DIR__ . '/../app/Services/CacheManager.php',
    __DIR__ . '/../app/Services/PerformanceMonitor.php',
    __DIR__ . '/../app/Services/SystemMonitor.php',
    
    // Database models
    __DIR__ . '/../app/Models/User.php',
    __DIR__ . '/../app/Models/Order.php',
    __DIR__ . '/../app/Models/Product.php',
    __DIR__ . '/../app/Models/Customer.php',
    
    // Filament files
    __DIR__ . '/../app/Providers/Filament/AdminPanelProvider.php',
];

foreach ($files as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}
```

### 1.2 تحسين إعدادات MySQL المتقدمة

#### إنشاء ملف `/etc/mysql/conf.d/mysql-production.cnf`:
```ini
[mysqld]
# Memory and Buffer Settings
innodb_buffer_pool_size = 2G
innodb_log_file_size = 512M
innodb_log_buffer_size = 64M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT
innodb_file_per_table = 1
innodb_open_files = 1000
innodb_io_capacity = 2000

# Connection Settings
max_connections = 300
max_connect_errors = 100000
connect_timeout = 60
wait_timeout = 28800
interactive_timeout = 28800

# Query Cache
query_cache_type = 1
query_cache_size = 256M
query_cache_limit = 2M
query_cache_strip_comments = 1

# Table Cache
table_open_cache = 2000
table_definition_cache = 1400

# Thread Settings
thread_cache_size = 50
thread_stack = 256K
sort_buffer_size = 2M
read_buffer_size = 2M
read_rnd_buffer_size = 8M
bulk_insert_buffer_size = 16M
myisam_sort_buffer_size = 64M

# Temporary Tables
tmp_table_size = 256M
max_heap_table_size = 256M

# Binary Logging
log-bin = mysql-bin
binlog_format = ROW
expire_logs_days = 7
max_binlog_size = 100M
binlog_cache_size = 32M
sync_binlog = 1
innodb_flush_log_at_trx_commit = 1

# Character Set
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
init_connect = 'SET NAMES utf8mb4'

# Performance Schema
performance_schema = ON
performance_schema_max_table_instances = 500
performance_schema_max_table_handles = 2000

# Slow Query Log
slow_query_log = 1
slow_query_log_file = /var/log/mysql/mysql-slow.log
long_query_time = 2
log_queries_not_using_indexes = 1

# InnoDB Settings
innodb_read_io_threads = 8
innodb_write_io_threads = 8
innodb_thread_concurrency = 0
innodb_concurrency_tickets = 5000
innodb_old_blocks_time = 1000
innodb_buffer_pool_instances = 8
innodb_adaptive_hash_index = 1
innodb_log_compressed_pages = 0
innodb_read_ahead_threshold = 56
innodb_random_read_ahead = 0
```

### 1.3 تحسين إعدادات Redis المتقدمة

#### إنشاء ملف `/etc/redis/redis-production.conf`:
```conf
# Network Settings
bind 127.0.0.1
port 6379
timeout 300
tcp-keepalive 60

# General Settings
daemonize yes
supervised systemd
pidfile /var/run/redis/redis-server.pid
loglevel notice
logfile /var/log/redis/redis-server.log
databases 16

# Snapshotting
save 900 1
save 300 10
save 60 10000
stop-writes-on-bgsave-error yes
rdbcompression yes
rdbchecksum yes
dbfilename dump.rdb
dir /var/lib/redis

# Memory Management
maxmemory 1gb
maxmemory-policy allkeys-lru
maxmemory-samples 5

# AOF (Append Only File)
appendonly yes
appendfilename "appendonly.aof"
appendfsync everysec
no-appendfsync-on-rewrite no
auto-aof-rewrite-percentage 100
auto-aof-rewrite-min-size 64mb
aof-load-truncated yes
aof-use-rdb-preamble yes

# Slow Log
slowlog-log-slower-than 10000
slowlog-max-len 128

# Client Output Buffer Limits
client-output-buffer-limit normal 0 0 0
client-output-buffer-limit replica 256mb 64mb 60
client-output-buffer-limit pubsub 32mb 8mb 60

# Frequency
hz 10
```

### 1.4 تحسين إعدادات Nginx المتقدمة

#### إنشاء ملف `/etc/nginx/conf.d/production-optimization.conf`:
```nginx
# Connection and Worker Settings
worker_rlimit_nofile 65535;
worker_shutdown_timeout 10s;

# Rate Limiting
limit_req_zone $binary_remote_addr zone=api:10m rate=30r/s;
limit_req_zone $binary_remote_addr zone=login:10m rate=3r/s;
limit_req_zone $binary_remote_addr zone=general:10m rate=10r/s;

# Connection Limiting
limit_conn_zone $binary_remote_addr zone=addr:10m;

# Upstream Configuration
upstream php_backend {
    server unix:/var/run/php/php8.2-fpm.sock weight=100 max_fails=3 fail_timeout=30s;
    keepalive 32;
}

# Cache Zones
proxy_cache_path /var/cache/nginx levels=1:2 keys_zone=static_cache:10m max_size=1g inactive=60m use_temp_path=off;
proxy_cache_path /var/cache/nginx levels=1:2 keys_zone=dynamic_cache:10m max_size=500m inactive=30m use_temp_path=off;

# Gzip Optimization
gzip on;
gzip_vary on;
gzip_proxied any;
gzip_comp_level 6;
gzip_min_length 1024;
gzip_types
    application/atom+xml
    application/javascript
    application/json
    application/ld+json
    application/manifest+json
    application/rss+xml
    application/vnd.geo+json
    application/vnd.ms-fontobject
    application/x-font-ttf
    application/x-web-app-manifest+json
    application/xhtml+xml
    application/xml
    font/opentype
    image/bmp
    image/svg+xml
    image/x-icon
    text/cache-manifest
    text/css
    text/plain
    text/vcard
    text/vnd.rim.location.xloc
    text/vtt
    text/x-component
    text/x-cross-domain-policy;

# Static File Caching
location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff|woff2|ttf|eot|svg)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
    add_header Vary Accept-Encoding;
    access_log off;
    
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options SAMEORIGIN;
    add_header X-XSS-Protection "1; mode=block";
}

# PHP-FPM Optimization
location ~ \.php$ {
    # Rate limiting
    limit_req zone=api burst=20 nodelay;
    limit_conn addr 10;
    
    # FastCGI cache
    fastcgi_cache dynamic_cache;
    fastcgi_cache_key $scheme$request_method$host$request_uri;
    fastcgi_cache_valid 200 302 10m;
    fastcgi_cache_valid 404 1m;
    fastcgi_cache_use_stale error timeout invalid_header http_500;
    fastcgi_cache_bypass $skip_cache;
    fastcgi_no_cache $skip_cache;
    
    # FastCGI settings
    fastcgi_pass php_backend;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    include fastcgi_params;
    
    # Timeout settings
    fastcgi_connect_timeout 60s;
    fastcgi_send_timeout 60s;
    fastcgi_read_timeout 60s;
    fastcgi_buffer_size 64k;
    fastcgi_buffers 4 64k;
    fastcgi_busy_buffers_size 128k;
    fastcgi_temp_file_write_size 128k;
    
    # Security
    fastcgi_hide_header X-Powered-By;
    fastcgi_param HTTP_PROXY "";
}

# Health Check Endpoint
location /health {
    access_log off;
    return 200 "healthy\n";
    add_header Content-Type text/plain;
}

# Security: Block access to sensitive files
location ~ /\. {
    deny all;
    access_log off;
    log_not_found off;
}

location ~ \.(htaccess|htpasswd|ini|log|sh|sql|conf)$ {
    deny all;
    access_log off;
    log_not_found off;
}
```

---

## 2. إعدادات الخادم المتقدمة

### 2.1 تحسين Kernel Parameters

#### إنشاء ملف `/etc/sysctl.d/99-laravel-production.conf`:
```bash
# Network Performance
net.core.rmem_max = 16777216
net.core.wmem_max = 16777216
net.ipv4.tcp_rmem = 4096 87380 16777216
net.ipv4.tcp_wmem = 4096 65536 16777216
net.ipv4.tcp_congestion_control = bbr
net.core.default_qdisc = fq

# File System Performance
fs.file-max = 2097152
vm.swappiness = 10
vm.dirty_ratio = 15
vm.dirty_background_ratio = 5
vm.overcommit_memory = 1

# Memory Management
vm.max_map_count = 262144
kernel.shmmax = 17179869184
kernel.shmall = 4194304

# Network Security
net.ipv4.conf.all.rp_filter = 1
net.ipv4.conf.default.rp_filter = 1
net.ipv4.icmp_echo_ignore_broadcasts = 1
net.ipv4.icmp_ignore_bogus_error_responses = 1
net.ipv4.tcp_syncookies = 1
net.ipv4.conf.all.log_martians = 1
net.ipv4.conf.default.log_martians = 1
net.ipv4.ip_forward = 0
net.ipv4.conf.all.send_redirects = 0
net.ipv4.conf.default.send_redirects = 0

# TCP Settings
net.ipv4.tcp_tw_reuse = 1
net.ipv4.tcp_fin_timeout = 30
net.ipv4.tcp_keepalive_time = 300
net.ipv4.tcp_max_syn_backlog = 8192
net.core.netdev_max_backlog = 5000

# Protection
net.ipv4.tcp_rfc1337 = 1
net.ipv4.tcp_sack = 1
net.ipv4.tcp_window_scaling = 1
```

#### تطبيق الإعدادات:
```bash
sudo sysctl -p /etc/sysctl.d/99-laravel-production.conf
```

### 2.2 إعداد Systemd Services المتقدمة

#### إنشاء `/etc/systemd/system/laravel-scheduler.service`:
```ini
[Unit]
Description=Laravel Scheduler
After=network.target

[Service]
Type=oneshot
User=www-data
Group=www-data
ExecStart=/usr/bin/php /var/www/your-app/artisan schedule:run --verbose --no-interaction
StandardOutput=journal
StandardError=journal
SyslogIdentifier=laravel-scheduler

[Install]
WantedBy=multi-user.target
```

#### إنشاء `/etc/systemd/system/laravel-scheduler.timer`:
```ini
[Unit]
Description=Laravel Schedule Timer
Requires=laravel-scheduler.service

[Timer]
OnCalendar=*-*-* *:*:00
Persistent=true

[Install]
WantedBy=timers.target
```

#### إنشاء `/etc/systemd/system/laravel-queue-worker.service`:
```ini
[Unit]
Description=Laravel Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
ExecStart=/usr/bin/php /var/www/your-app/artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal
SyslogIdentifier=laravel-queue-worker
Environment=APP_ENV=production

[Install]
WantedBy=multi-user.target
```

#### تمكين الخدمات:
```bash
sudo systemctl daemon-reload
sudo systemctl enable laravel-scheduler.timer
sudo systemctl enable laravel-queue-worker.service
sudo systemctl start laravel-scheduler.timer
sudo systemctl start laravel-queue-worker.service
```

---

## 3. النسخ الاحتياطي والاستعادة المتقدمة

### 3.1 نظام النسخ الاحتياطي الذكي

#### إنشاء `/var/www/your-app/scripts/backup/advanced-backup.sh`:
```bash
#!/bin/bash

# Laravel Production Advanced Backup Script
# Created: $(date)
# Version: 2.0

set -euo pipefail

# Configuration
APP_NAME="laravel-app"
APP_PATH="/var/www/your-app"
BACKUP_DIR="/var/backups/${APP_NAME}"
RETENTION_DAYS=30
MYSQL_USER="app_user"
MYSQL_PASSWORD="strong_password"
MYSQL_DATABASE="production_app"
COMPRESSION_LEVEL=9

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" >&2
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Create backup directory
mkdir -p "${BACKUP_DIR}/{database,files,config,logs}"

# Generate backup filename with timestamp
BACKUP_DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_NAME="${APP_NAME}_${BACKUP_DATE}"
TEMP_DIR="${BACKUP_DIR}/temp_${BACKUP_NAME}"

log "Starting backup process for ${APP_NAME}"

# 1. Database Backup
log "Creating database backup..."
mysqldump --user=${MYSQL_USER} --password=${MYSQL_PASSWORD} \
    --host=127.0.0.1 --port=3306 --single-transaction \
    --routines --triggers --events --hex-blob \
    --add-drop-table --add-drop-trigger \
    ${MYSQL_DATABASE} | gzip -${COMPRESSION_LEVEL} > \
    "${BACKUP_DIR}/database/${BACKUP_NAME}_database.sql.gz"

# Database integrity check
if ! gunzip -t "${BACKUP_DIR}/database/${BACKUP_NAME}_database.sql.gz"; then
    error "Database backup integrity check failed!"
    exit 1
fi
log "Database backup completed successfully"

# 2. Application Files Backup
log "Creating application files backup..."
mkdir -p "${TEMP_DIR}"

# Create excludes file
cat > "${TEMP_DIR}/excludes.txt" << EOF
${APP_PATH}/storage/logs/*
${APP_PATH}/storage/framework/cache/*
${APP_PATH}/storage/framework/sessions/*
${APP_PATH}/storage/framework/views/*
${APP_PATH}/storage/app/private/*
${APP_PATH}/bootstrap/cache/*
${APP_PATH}/vendor/*
${APP_PATH}/node_modules/*
${APP_PATH}/.git/*
${APP_PATH}/tests/*
${APP_PATH}/.env
EOF

# Backup application files
tar --exclude-from="${TEMP_DIR}/excludes.txt" \
    --exclude="*.tmp" --exclude="*.log" \
    --exclude="Thumbs.db" --exclude=".DS_Store" \
    -czf "${BACKUP_DIR}/files/${BACKUP_NAME}_files.tar.gz" \
    -C "${APP_PATH}" . || {
    error "Application files backup failed!"
    exit 1
}

# 3. Configuration Backup
log "Creating configuration backup..."
tar -czf "${BACKUP_DIR}/config/${BACKUP_NAME}_config.tar.gz" \
    -C "/etc/nginx" . \
    -C "/etc/php/8.2/fpm" . \
    -C "/etc/mysql" . \
    -C "/etc/redis" . \
    -C "/etc/ssl" . 2>/dev/null || warning "Some config files backup failed"

# 4. Redis Backup
log "Creating Redis backup..."
redis-cli --rdb - | gzip -${COMPRESSION_LEVEL} > \
    "${BACKUP_DIR}/database/${BACKUP_NAME}_redis.rdb.gz" || warning "Redis backup failed"

# 5. Cleanup old backups
log "Cleaning up old backups (older than ${RETENTION_DAYS} days)..."
find "${BACKUP_DIR}" -type f -mtime +${RETENTION_DAYS} -delete

# 6. Generate backup report
log "Generating backup report..."
cat > "${BACKUP_DIR}/backup_report_${BACKUP_DATE}.txt" << EOF
Laravel Application Backup Report
Generated: $(date)
Backup Name: ${BACKUP_NAME}

Database Backup:
- File: database/${BACKUP_NAME}_database.sql.gz
- Size: $(du -h "${BACKUP_DIR}/database/${BACKUP_NAME}_database.sql.gz" | cut -f1)
- Checksum: $(sha256sum "${BACKUP_DIR}/database/${BACKUP_NAME}_database.sql.gz" | cut -d' ' -f1)

Application Files Backup:
- File: files/${BACKUP_NAME}_files.tar.gz
- Size: $(du -h "${BACKUP_DIR}/files/${BACKUP_NAME}_files.tar.gz" | cut -f1)
- Checksum: $(sha256sum "${BACKUP_DIR}/files/${BACKUP_NAME}_files.tar.gz" | cut -d' ' -f1)

Total Backup Size: $(du -sh "${BACKUP_DIR}" | cut -f1)
Backup Location: ${BACKUP_DIR}

Notes:
- Database: Full backup with all triggers and routines
- Files: Excluded logs, cache, vendor, node_modules
- Config: Server configurations
- Redis: Complete dataset snapshot
EOF

# 7. Cleanup temp directory
rm -rf "${TEMP_DIR}"

log "Backup process completed successfully!"
log "Backup report: ${BACKUP_DIR}/backup_report_${BACKUP_DATE}.txt"

exit 0
```

#### إضافة Cron Job للنسخ الاحتياطي:
```bash
# Add to crontab
sudo crontab -e

# Daily backup at 2 AM
0 2 * * * /var/www/your-app/scripts/backup/advanced-backup.sh >> /var/log/laravel-backup.log 2>&1
```

---

## 4. مراقبة النظام المتقدمة

### 4.1 إعداد System Monitoring

#### إنشاء `/var/www/your-app/scripts/monitoring/system-monitor.sh`:
```bash
#!/bin/bash

# Laravel Production System Monitor
APP_NAME="laravel-app"
METRICS_FILE="/tmp/laravel_metrics.json"

# Collect System Metrics
collect_system_metrics() {
    local cpu_usage=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | awk -F'%' '{print $1}')
    local mem_usage=$(free | grep Mem | awk '{printf "%.2f", $3/$2 * 100.0}')
    local disk_usage=$(df -h / | awk 'NR==2{printf "%s", $5}')
    
    # Create metrics JSON
    cat > "$METRICS_FILE" << EOF
{
    "timestamp": "$(date -Iseconds)",
    "system": {
        "cpu_usage": $cpu_usage,
        "memory_usage": $mem_usage,
        "disk_usage": "$disk_usage"
    }
}
EOF
}

# Health Check
health_check() {
    local status="healthy"
    local issues=()
    
    # Check disk space
    local disk_usage=$(df / | awk 'NR==2{print $5}' | sed 's/%//')
    if [ $disk_usage -gt 85 ]; then
        status="warning"
        issues+=("Disk space: ${disk_usage}%")
    fi
    
    # Check services
    if ! systemctl is-active --quiet php8.2-fpm; then
        status="critical"
        issues+=("PHP-FPM is not running")
    fi
    
    if ! systemctl is-active --quiet nginx; then
        status="critical"
        issues+=("Nginx is not running")
    fi
    
    # Check application response
    if ! curl -f -s http://localhost/health > /dev/null 2>&1; then
        status="critical"
        issues+=("Application health check failed")
    fi
    
    echo "Health check: $status"
    if [ ${#issues[@]} -gt 0 ]; then
        echo "Issues found:"
        printf '  - %s\n' "${issues[@]}"
    fi
}

# Main execution
main() {
    collect_system_metrics
    health_check
    
    # Save metrics with rotation
    mv "$METRICS_FILE" "/var/log/laravel-metrics-$(date +%Y%m%d).json"
    
    # Keep only last 7 days of metrics
    find /var/log -name "laravel-metrics-*.json" -mtime +7 -delete 2>/dev/null || true
}

main "$@"
```

### 4.2 إعداد Performance Monitoring

#### إنشاء `/var/www/your-app/app/Services/PerformanceMonitor.php`:
```php
<?php

namespace App\Services;

class PerformanceMonitor
{
    private $startTime;
    private $queryCount = 0;
    
    public function __construct()
    {
        $this->startTime = microtime(true);
    }
    
    public function recordQuery($sql, $time)
    {
        $this->queryCount++;
        
        // Log slow queries
        if ($time > 1.0) {
            \Log::warning('Slow query detected', [
                'sql' => $sql,
                'time' => $time,
            ]);
        }
    }
    
    public function getMetrics()
    {
        return [
            'duration' => microtime(true) - $this->startTime,
            'query_count' => $this->queryCount,
            'memory_usage' => memory_get_usage(),
            'peak_memory' => memory_get_peak_usage(),
        ];
    }
}
```

---

## 5. الأمان المتقدم

### 5.1 إعداد Fail2ban للحماية من الهجمات

#### تثبيت Fail2ban:
```bash
sudo apt install fail2ban
```

#### إنشاء `/etc/fail2ban/jail.local`:
```ini
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 3
destemail = admin@yourdomain.com
sendername = Fail2Ban
mta = sendmail

[sshd]
enabled = true
port = ssh
filter = sshd
logpath = /var/log/auth.log
maxretry = 3
bantime = 3600

[nginx-http-auth]
enabled = true
filter = nginx-http-auth
logpath = /var/log/nginx/error.log
maxretry = 3
bantime = 3600

[laravel-auth]
enabled = true
filter = laravel-auth
logpath = /var/www/your-app/storage/logs/laravel.log
maxretry = 5
bantime = 1800
```

#### تطبيق إعدادات Fail2ban:
```bash
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

### 5.2 إعداد AppArmor

```bash
# Enable AppArmor
sudo systemctl enable apparmor
sudo systemctl start apparmor

# Install Laravel profile
sudo aa-enforce /etc/apparmor.d/usr.sbin.nginx
sudo aa-enforce /etc/apparmor.d/usr.sbin.php-fpm8.2
```

---

## 6. تحسينات الأداء المتقدمة

### 6.1 Database Query Optimization

#### إنشاء `/var/www/your-app/scripts/optimization/database-optimizer.php`:
```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DatabaseOptimizer extends Command
{
    protected $signature = 'db:optimize {--analyze : Run ANALYZE TABLE}';
    protected $description = 'Optimize database for production';

    public function handle()
    {
        $this->info('Starting database optimization...');
        
        // Get all tables
        $tables = DB::select('SHOW TABLES');
        $tableNames = array_map('current', $tables);
        
        foreach ($tableNames as $table) {
            $this->line("Optimizing table: {$table}");
            
            // Analyze table
            if ($this->option('analyze')) {
                DB::statement("ANALYZE TABLE `{$table}`");
                $this->line("  ✓ Analyzed");
            }
            
            // Optimize table
            DB::statement("OPTIMIZE TABLE `{$table}`");
            $this->line("  ✓ Optimized");
        }
        
        $this->info('Database optimization completed!');
    }
}
```

### 6.2 Cache Warming Strategy

#### إنشاء `/var/www/your-app/scripts/optimization/cache-warming.php`:
```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class CacheWarming extends Command
{
    protected $signature = 'cache:warm {--url= : Base URL for warmup}';
    protected $description = 'Warm up application cache';

    public function handle()
    {
        $baseUrl = $this->option('url') ?: config('app.url');
        $this->info('Starting cache warming...');
        
        // Warm up caches
        $this->line('Warming configuration cache...');
        $this->call('config:cache');
        
        $this->line('Warming route cache...');
        $this->call('route:cache');
        
        $this->line('Warming view cache...');
        $this->call('view:cache');
        
        // Warm up database queries
        $this->line('Warming database cache...');
        $categories = Cache::remember('warm_categories', 3600, function () {
            return DB::table('categories')->get();
        });
        
        $this->info('Cache warming completed!');
    }
}
```

---

## الخلاصة

هذا الدليل المتقدم يغطي:
- تحسينات الأداء المحلية الشاملة
- إعدادات الخادم المتقدمة
- النسخ الاحتياطي والاستعادة
- مراقبة النظام المتقدمة
- الأمان المتقدم
- تحسينات قاعدة البيانات والكاش

في الدليل التالي، سنتناول دليل الصيانة والتشغيل اليومي للنظام.