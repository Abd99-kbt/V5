# دليل الصيانة الشامل - Laravel Production System

## نظرة عامة
هذا الدليل يغطي جميع جوانب صيانة نظام Laravel في بيئة الإنتاج، من الصيانة اليومية إلى العمليات المتقدمة.

---

## 1. الصيانة اليومية

### 1.1 فحوصات الصحة اليومية

#### إنشاء `/var/www/your-app/scripts/maintenance/daily-health-check.sh`:
```bash
#!/bin/bash

# Daily Health Check Script
# This script performs comprehensive daily system health checks

set -euo pipefail

APP_NAME="laravel-app"
APP_PATH="/var/www/your-app"
LOG_FILE="/var/log/daily-health-check-$(date +%Y%m%d).log"
EMAIL_NOTIFICATION="admin@yourdomain.com"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Logging function
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"
}

# Initialize log
log "Starting daily health check for $APP_NAME"

# 1. Check Application Status
check_application() {
    log "Checking application status..."
    
    # Check if application is responding
    if curl -f -s http://localhost/health > /dev/null; then
        log "✓ Application is responding"
    else
        error "✗ Application health check failed"
        return 1
    fi
    
    # Check Filament admin panel
    if curl -f -s http://localhost/admin > /dev/null; then
        log "✓ Filament admin panel is accessible"
    else
        warning "⚠ Filament admin panel may not be accessible"
    fi
}

# 2. Check Database Connection
check_database() {
    log "Checking database connection..."
    
    if mysql -uapp_user -pstrong_password -e "SELECT 1;" > /dev/null 2>&1; then
        log "✓ Database connection successful"
        
        # Check database size
        DB_SIZE=$(mysql -uapp_user -pstrong_password -e "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'DB Size in MB' FROM information_schema.tables WHERE table_schema='production_app';" 2>/dev/null | tail -1)
        log "Database size: ${DB_SIZE}MB"
    else
        error "✗ Database connection failed"
        return 1
    fi
}

# 3. Check Redis Connection
check_redis() {
    log "Checking Redis connection..."
    
    if redis-cli ping > /dev/null 2>&1; then
        log "✓ Redis connection successful"
        
        # Check Redis memory usage
        REDIS_MEMORY=$(redis-cli info memory | grep used_memory_human | cut -d: -f2 | tr -d '\r')
        log "Redis memory usage: $REDIS_MEMORY"
    else
        error "✗ Redis connection failed"
        return 1
    fi
}

# 4. Check Disk Space
check_disk_space() {
    log "Checking disk space..."
    
    DISK_USAGE=$(df -h / | awk 'NR==2{print $5}' | sed 's/%//')
    
    if [ "$DISK_USAGE" -lt 80 ]; then
        log "✓ Disk space OK: ${DISK_USAGE}%"
    elif [ "$DISK_USAGE" -lt 90 ]; then
        warning "⚠ Disk space warning: ${DISK_USAGE}%"
    else
        error "✗ Disk space critical: ${DISK_USAGE}%"
    fi
    
    # Check specific directories
    APP_SIZE=$(du -sh "$APP_PATH" 2>/dev/null | cut -f1)
    LOG_SIZE=$(du -sh /var/log/nginx 2>/dev/null | cut -f1)
    
    log "Application size: $APP_SIZE"
    log "Nginx logs size: $LOG_SIZE"
}

# 5. Check Memory Usage
check_memory() {
    log "Checking memory usage..."
    
    MEM_USAGE=$(free | grep Mem | awk '{printf "%.0f", $3/$2 * 100.0}')
    
    if [ "$MEM_USAGE" -lt 80 ]; then
        log "✓ Memory usage OK: ${MEM_USAGE}%"
    elif [ "$MEM_USAGE" -lt 90 ]; then
        warning "⚠ Memory usage warning: ${MEM_USAGE}%"
    else
        error "✗ Memory usage critical: ${MEM_USAGE}%"
    fi
    
    # Check PHP memory limit
    PHP_MEMORY_LIMIT=$(php -r "echo ini_get('memory_limit');")
    log "PHP memory limit: $PHP_MEMORY_LIMIT"
}

# 6. Check Services Status
check_services() {
    log "Checking service status..."
    
    SERVICES=("php8.2-fpm" "nginx" "mysql" "redis")
    
    for service in "${SERVICES[@]}"; do
        if systemctl is-active --quiet "$service"; then
            log "✓ $service is running"
        else
            error "✗ $service is not running"
        fi
    done
}

# 7. Check Application Logs
check_logs() {
    log "Checking application logs..."
    
    # Check Laravel logs for errors today
    ERROR_COUNT=$(find "$APP_PATH/storage/logs" -name "*.log" -exec grep -h "$(date +'Y-m-d')" {} \; 2>/dev/null | grep -i error | wc -l)
    WARNING_COUNT=$(find "$APP_PATH/storage/logs" -name "*.log" -exec grep -h "$(date +'Y-m-d')" {} \; 2>/dev/null | grep -i warning | wc -l)
    
    if [ "$ERROR_COUNT" -eq 0 ]; then
        log "✓ No errors found in application logs today"
    else
        warning "⚠ Found $ERROR_COUNT errors in application logs today"
    fi
    
    if [ "$WARNING_COUNT" -lt 10 ]; then
        log "✓ Few warnings in application logs today ($WARNING_COUNT)"
    else
        warning "⚠ High warning count: $WARNING_COUNT warnings today"
    fi
}

# 8. Check Queue Status
check_queue() {
    log "Checking queue status..."
    
    if mysql -uapp_user -pstrong_password production_app -e "SHOW TABLES LIKE 'jobs';" > /dev/null 2>&1; then
        PENDING_JOBS=$(mysql -uapp_user -pstrong_password production_app -e "SELECT COUNT(*) FROM jobs WHERE reserved_at IS NULL;" 2>/dev/null | tail -1)
        FAILED_JOBS=$(mysql -uapp_user -pstrong_password production_app -e "SELECT COUNT(*) FROM failed_jobs;" 2>/dev/null | tail -1)
        
        log "Pending jobs: $PENDING_JOBS"
        log "Failed jobs: $FAILED_JOBS"
        
        if [ "$FAILED_JOBS" -gt 0 ]; then
            warning "⚠ Found $FAILED_JOBS failed jobs"
        fi
    else
        log "Queue tables not found (may not be configured)"
    fi
}

# 9. Check SSL Certificate
check_ssl() {
    log "Checking SSL certificate..."
    
    if command -v openssl &> /dev/null; then
        DOMAIN="yourdomain.com"
        EXPIRY_DATE=$(echo | openssl s_client -servername "$DOMAIN" -connect "$DOMAIN:443" 2>/dev/null | openssl x509 -noout -dates | grep notAfter | cut -d= -f2)
        EXPIRY_EPOCH=$(date -d "$EXPIRY_DATE" +%s)
        CURRENT_EPOCH=$(date +%s)
        DAYS_UNTIL_EXPIRY=$(( (EXPIRY_EPOCH - CURRENT_EPOCH) / 86400 ))
        
        if [ "$DAYS_UNTIL_EXPIRY" -gt 30 ]; then
            log "✓ SSL certificate valid for $DAYS_UNTIL_EXPIRY more days"
        elif [ "$DAYS_UNTIL_EXPIRY" -gt 7 ]; then
            warning "⚠ SSL certificate expires in $DAYS_UNTIL_EXPIRY days"
        else
            error "✗ SSL certificate expires in $DAYS_UNTIL_EXPIRY days - URGENT!"
        fi
    else
        warning "⚠ OpenSSL not available for SSL check"
    fi
}

# 10. Check Backup Status
check_backup() {
    log "Checking backup status..."
    
    BACKUP_DIR="/var/backups/$APP_NAME"
    if [ -d "$BACKUP_DIR" ]; then
        LAST_BACKUP=$(find "$BACKUP_DIR" -name "*.gz" -type f -printf '%T@ %p\n' | sort -n | tail -1 | cut -d' ' -f2-)
        if [ -n "$LAST_BACKUP" ]; then
            LAST_BACKUP_TIME=$(date -r "$LAST_BACKUP" +"%Y-%m-%d %H:%M:%S")
            log "Last backup: $LAST_BACKUP_TIME"
        else
            warning "⚠ No backups found"
        fi
    else
        warning "⚠ Backup directory not found"
    fi
}

# Send notification email
send_notification() {
    if command -v mail &> /dev/null && [ -n "$EMAIL_NOTIFICATION" ]; then
        {
            echo "Daily Health Check Report - $APP_NAME"
            echo "Generated: $(date)"
            echo "=================================="
            echo ""
            cat "$LOG_FILE"
        } | mail -s "Daily Health Check - $APP_NAME" "$EMAIL_NOTIFICATION" || true
    fi
}

# Main execution
main() {
    check_application
    check_database
    check_redis
    check_disk_space
    check_memory
    check_services
    check_logs
    check_queue
    check_ssl
    check_backup
    
    log "Daily health check completed"
    
    # Send notification if configured
    send_notification
    
    # Keep only last 7 days of logs
    find /var/log -name "daily-health-check-*.log" -mtime +7 -delete 2>/dev/null || true
}

# Run if executed directly
if [ "${BASH_SOURCE[0]}" == "${0}" ]; then
    main "$@"
fi
```

#### إضافة Cron Job:
```bash
# Add to crontab
sudo crontab -e

# Daily health check at 6 AM
0 6 * * * /var/www/your-app/scripts/maintenance/daily-health-check.sh
```

### 1.2 تنظيف يومي للملفات

#### إنشاء `/var/www/your-app/scripts/maintenance/daily-cleanup.sh`:
```bash
#!/bin/bash

# Daily Cleanup Script
# Cleans up temporary files, logs, and optimizes the system

set -euo pipefail

APP_PATH="/var/www/your-app"
LOG_FILE="/var/log/daily-cleanup-$(date +%Y%m%d).log"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() { echo -e "${GREEN}[$(date +'%H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"; }
warning() { echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"; }

log "Starting daily cleanup..."

# 1. Clean Laravel temporary files
log "Cleaning Laravel temporary files..."
find "$APP_PATH/storage/framework/cache/data" -type f -mtime +1 -delete 2>/dev/null || true
find "$APP_PATH/storage/framework/sessions" -type f -mtime +1 -delete 2>/dev/null || true
find "$APP_PATH/storage/framework/views" -type f -mtime +1 -delete 2>/dev/null || true
find "$APP_PATH/storage/logs" -name "*.log" -type f -size +100M -exec gzip {} \; 2>/dev/null || true

# 2. Clean old log files (keep 30 days)
log "Cleaning old log files..."
find /var/log/nginx -name "*.log" -mtime +30 -delete 2>/dev/null || true
find /var/log/php8.2-fpm*.log -mtime +30 -delete 2>/dev/null || true
find "$APP_PATH/storage/logs" -name "*.gz" -mtime +30 -delete 2>/dev/null || true

# 3. Clean session data
log "Cleaning expired sessions..."
sudo -u www-data php "$APP_PATH/artisan" session:table > /dev/null 2>&1 || true

# 4. Clear expired cache
log "Clearing expired cache entries..."
sudo -u www-data php "$APP_PATH/artisan" cache:prune-stale-tags 2>/dev/null || true

# 5. Clean composer cache
log "Cleaning composer cache..."
sudo -u www-data composer clear-cache 2>/dev/null || true

# 6. Clean NPM cache
log "Cleaning NPM cache..."
sudo -u www-data npm cache clean --force 2>/dev/null || true

# 7. Clean system temp files
log "Cleaning system temporary files..."
find /tmp -type f -mtime +1 -user www-data -delete 2>/dev/null || true
find /var/tmp -type f -mtime +1 -user www-data -delete 2>/dev/null || true

# 8. Optimize MySQL tables (weekly)
if [ $(date +%u) -eq 1 ]; then  # Monday
    log "Optimizing MySQL tables (weekly)..."
    mysql -uapp_user -pstrong_password -e "OPTIMIZE TABLE \`production_app\`;" 2>/dev/null || warning "MySQL optimization failed"
fi

log "Daily cleanup completed"
```

---

## 2. الصيانة الأسبوعية

### 2.1 الفحص الشامل للنظام

#### إنشاء `/var/www/your-app/scripts/maintenance/weekly-system-audit.sh`:
```bash
#!/bin/bash

# Weekly System Audit Script
# Performs comprehensive system audit and optimization

set -euo pipefail

APP_NAME="laravel-app"
APP_PATH="/var/www/your-app"
LOG_FILE="/var/log/weekly-audit-$(date +%Y%W).log"
AUDIT_REPORT="/var/log/weekly-audit-report-$(date +%Y%W).txt"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log() { echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"; }
warning() { echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"; }
error() { echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"; }

# Initialize audit report
cat > "$AUDIT_REPORT" << EOF
نظام المراجعة الأسبوعي
===============================
التطبيق: $APP_NAME
التاريخ: $(date)
الأسبوع: $(date +%Y-W%V)

EOF

# 1. System Information Collection
collect_system_info() {
    log "Collecting system information..."
    
    {
        echo "معلومات النظام:"
        echo "================"
        echo "نظام التشغيل: $(lsb_release -d | cut -f2)"
        echo "إصدار Kernel: $(uname -r)"
        echo "المعالج: $(lscpu | grep 'Model name' | cut -d: -f2 | xargs)"
        echo "الذاكرة: $(free -h | grep 'Mem:' | awk '{print $2}')"
        echo "مساحة القرص: $(df -h / | awk 'NR==2{print $4}') من $(df -h / | awk 'NR==2{print $2}')"
        echo "وقت التشغيل: $(uptime -p)"
        echo ""
    } >> "$AUDIT_REPORT"
}

# 2. Security Audit
security_audit() {
    log "Performing security audit..."
    
    {
        echo "مراجعة الأمان:"
        echo "==============="
        
        # Check for security updates
        echo "فحص التحديثات الأمنية:"
        apt list --upgradable 2>/dev/null | grep -i security | wc -l | xargs -I {} echo "تحديثات أمنية متاحة: {}"
        
        # Check SSH configuration
        if [ -f /etc/ssh/sshd_config ]; then
            SSH_ROOT_LOGIN=$(grep -i "PermitRootLogin" /etc/ssh/sshd_config | grep -v "^#" | head -1 | awk '{print $2}')
            echo "تسجيل دخول root عبر SSH: $SSH_ROOT_LOGIN"
        fi
        
        # Check firewall status
        echo "حالة جدار الحماية:"
        if command -v ufw &> /dev/null; then
            ufw status | grep -E "(Status|80|443|22)" | head -4
        fi
        
        # Check fail2ban status
        echo "حالة Fail2ban:"
        if systemctl is-active fail2ban &> /dev/null; then
            fail2ban-client status 2>/dev/null | head -5
        else
            echo "Fail2ban غير مفعل"
        fi
        
        echo ""
    } >> "$AUDIT_REPORT"
}

# 3. Application Security Scan
app_security_scan() {
    log "Performing application security scan..."
    
    {
        echo "فحص أمن التطبيق:"
        echo "================="
        
        # Check file permissions
        echo "فحص أذونات الملفات:"
        find "$APP_PATH" -type f -perm /o+w 2>/dev/null | head -10 | while read file; do
            echo "ملف قابل للكتابة للجميع: $file"
        done
        
        # Check for .env file exposure
        if [ -f "$APP_PATH/.env" ]; then
            ENV_PERMS=$(stat -c "%a" "$APP_PATH/.env")
            echo "أذونات ملف .env: $ENV_PERMS"
            if [ "$ENV_PERMS" != "600" ]; then
                warning "تحذير: ملف .env يجب أن يكون له أذونات 600"
            fi
        fi
        
        echo ""
    } >> "$AUDIT_REPORT"
}

# 4. Database Performance Audit
database_audit() {
    log "Performing database audit..."
    
    {
        echo "مراجعة قاعدة البيانات:"
        echo "======================"
        
        # Database size and growth
        DB_SIZE=$(mysql -uapp_user -pstrong_password -e "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'DB Size in MB' FROM information_schema.tables WHERE table_schema='production_app';" 2>/dev/null | tail -1)
        echo "حجم قاعدة البيانات: ${DB_SIZE}MB"
        
        # Table sizes
        echo "أحجام الجداول:"
        mysql -uapp_user -pstrong_password production_app -e "
            SELECT 
                table_name AS 'الجدول',
                ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'الحجم (MB)'
            FROM information_schema.TABLES 
            WHERE table_schema = 'production_app'
            ORDER BY (data_length + index_length) DESC
            LIMIT 10;
        " 2>/dev/null
        
        # Check for large tables
        echo "الجداول الكبيرة (> 100MB):"
        mysql -uapp_user -pstrong_password production_app -e "
            SELECT 
                table_name AS 'الجدول',
                ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'الحجم (MB)'
            FROM information_schema.TABLES 
            WHERE table_schema = 'production_app'
            AND ((data_length + index_length) / 1024 / 1024) > 100
            ORDER BY (data_length + index_length) DESC;
        " 2>/dev/null
        
        echo ""
    } >> "$AUDIT_REPORT"
}

# 5. Performance Analysis
performance_audit() {
    log "Performing performance analysis..."
    
    {
        echo "تحليل الأداء:"
        echo "=============="
        
        # CPU and memory usage
        CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | awk -F'%' '{print $1}')
        MEM_USAGE=$(free | grep Mem | awk '{printf "%.0f", $3/$2 * 100.0}')
        echo "استخدام المعالج: ${CPU_USAGE}%"
        echo "استخدام الذاكرة: ${MEM_USAGE}%"
        
        # Top processes
        echo "أعلى العمليات استخداماً للموارد:"
        ps aux --sort=-%cpu | head -6
        
        # Disk I/O
        echo "حالة قرص التخزين:"
        iostat -x 1 1 2>/dev/null | tail -10 || echo "iostat غير متاح"
        
        echo ""
    } >> "$AUDIT_REPORT"
}

# 6. Log Analysis
log_analysis() {
    log "Analyzing system logs..."
    
    {
        echo "تحليل السجلات:"
        echo "==============="
        
        # Error count in application logs
        ERROR_COUNT=$(find "$APP_PATH/storage/logs" -name "*.log" -exec grep -h "$(date +'Y-m-d')" {} \; 2>/dev/null | grep -i error | wc -l)
        echo "أخطاء التطبيق اليوم: $ERROR_COUNT"
        
        # Most common errors
        echo "الأخطاء الأكثر تكراراً:"
        find "$APP_PATH/storage/logs" -name "*.log" -exec grep -h "$(date +'Y-m-d')" {} \; 2>/dev/null | grep -i error | cut -d'[' -f3- | cut -d']' -f1 | sort | uniq -c | sort -nr | head -5
        
        # Nginx access patterns
        echo "نمط طلبات Nginx:"
        if [ -f /var/log/nginx/access.log ]; then
            echo "الطلبات الأخيرة:"
            tail -10 /var/log/nginx/access.log | awk '{print $1}' | sort | uniq -c | sort -nr | head -5
        fi
        
        echo ""
    } >> "$AUDIT_REPORT"
}

# 7. Backup Verification
backup_verification() {
    log "Verifying backup integrity..."
    
    {
        echo "التحقق من النسخ الاحتياطية:"
        echo "============================="
        
        BACKUP_DIR="/var/backups/$APP_NAME"
        if [ -d "$BACKUP_DIR" ]; then
            BACKUP_COUNT=$(find "$BACKUP_DIR" -name "*.gz" -type f | wc -l)
            echo "عدد ملفات النسخ الاحتياطية: $BACKUP_COUNT"
            
            # Check latest backup
            LATEST_BACKUP=$(find "$BACKUP_DIR" -name "*.gz" -type f -printf '%T@ %p\n' | sort -n | tail -1 | cut -d' ' -f2-)
            if [ -n "$LATEST_BACKUP" ]; then
                BACKUP_DATE=$(date -r "$LATEST_BACKUP" +"%Y-%m-%d %H:%M:%S")
                BACKUP_SIZE=$(du -h "$LATEST_BACKUP" | cut -f1)
                echo "آخر نسخة احتياطية: $BACKUP_DATE ($BACKUP_SIZE)"
                
                # Test backup integrity
                if file "$LATEST_BACKUP" | grep -q "gzip"; then
                    echo "✓ آخر نسخة احتياطية صحيحة"
                else
                    echo "✗ آخر نسخة احتياطية تالفة"
                fi
            fi
        else
            echo "⚠ دليل النسخ الاحتياطية غير موجود"
        fi
        
        echo ""
    } >> "$AUDIT_REPORT"
}

# 8. Generate Recommendations
generate_recommendations() {
    log "Generating recommendations..."
    
    {
        echo "التوصيات:"
        echo "========="
        
        # System recommendations
        if [ $(df / | awk 'NR==2{print $5}' | sed 's/%//') -gt 80 ]; then
            echo "• تنظيف مساحة القرص - المساحة أقل من 20%"
        fi
        
        if [ $(free | grep Mem | awk '{printf "%.0f", $3/$2 * 100.0}') -gt 85 ]; then
            echo "• تحسين استخدام الذاكرة - أكثر من 85%"
        fi
        
        # Security recommendations
        if ! systemctl is-active fail2ban &> /dev/null; then
            echo "• تفعيل Fail2ban للحماية من الهجمات"
        fi
        
        if ! grep -q "PermitRootLogin no" /etc/ssh/sshd_config 2>/dev/null; then
            echo "• تعطيل تسجيل دخول root عبر SSH"
        fi
        
        # Application recommendations
        if [ $(find "$APP_PATH/storage/logs" -name "*.log" -exec grep -h "$(date +'Y-m-d')" {} \; 2>/dev/null | grep -i error | wc -l) -gt 10 ]; then
            echo "• مراجعة أخطاء التطبيق المتكررة"
        fi
        
        echo ""
    } >> "$AUDIT_REPORT"
}

# Main execution
main() {
    log "Starting weekly system audit"
    
    collect_system_info
    security_audit
    app_security_scan
    database_audit
    performance_audit
    log_analysis
    backup_verification
    generate_recommendations
    
    log "Weekly audit completed. Report saved to: $AUDIT_REPORT"
    
    # Email report if configured
    if command -v mail &> /dev/null; then
        mail -s "تقرير المراجعة الأسبوعي - $APP_NAME" -a "$AUDIT_REPORT" admin@yourdomain.com < /dev/null 2>/dev/null || true
    fi
}

main "$@"
```

### 2.2 تحسين أسبوعي

#### إنشاء `/var/www/your-app/scripts/maintenance/weekly-optimization.sh`:
```bash
#!/bin/bash

# Weekly Optimization Script
# Optimizes database, caches, and system performance

set -euo pipefail

APP_NAME="laravel-app"
APP_PATH="/var/www/your-app"
LOG_FILE="/var/log/weekly-optimization-$(date +%Y%W).log"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() { echo -e "${GREEN}[$(date +'%H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"; }
warning() { echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"; }

log "Starting weekly optimization..."

# 1. Database Optimization
optimize_database() {
    log "Optimizing database..."
    
    # Optimize all tables
    mysql -uapp_user -pstrong_password production_app -e "
        SELECT CONCAT('OPTIMIZE TABLE \`', table_name, '\`;') as query
        FROM information_schema.tables 
        WHERE table_schema = 'production_app'
        AND table_type = 'BASE TABLE';
    " 2>/dev/null | while read -r query; do
        mysql -uapp_user -pstrong_password production_app -e "$query" 2>/dev/null || warning "Failed to optimize table"
    done
    
    # Analyze tables
    mysql -uapp_user -pstrong_password production_app -e "
        SELECT CONCAT('ANALYZE TABLE \`', table_name, '\`;') as query
        FROM information_schema.tables 
        WHERE table_schema = 'production_app'
        AND table_type = 'BASE TABLE';
    " 2>/dev/null | while read -r query; do
        mysql -uapp_user -pstrong_password production_app -e "$query" 2>/dev/null || warning "Failed to analyze table"
    done
    
    log "Database optimization completed"
}

# 2. Redis Optimization
optimize_redis() {
    log "Optimizing Redis..."
    
    # Memory optimization
    redis-cli --rdb /dev/null 2>/dev/null || warning "Redis not available"
    
    # Clear expired keys
    redis-cli EVAL "return redis.call('del', unpack(redis.call('keys', '*:expired:*')))" 0 2>/dev/null || true
    
    log "Redis optimization completed"
}

# 3. Laravel Cache Optimization
optimize_laravel_cache() {
    log "Optimizing Laravel caches..."
    
    # Clear and regenerate all caches
    sudo -u www-data php "$APP_PATH/artisan" cache:clear
    sudo -u www-data php "$APP_PATH/artisan" config:clear
    sudo -u www-data php "$APP_PATH/artisan" route:clear
    sudo -u www-data php "$APP_PATH/artisan" view:clear
    
    # Regenerate optimized caches
    sudo -u www-data php "$APP_PATH/artisan" config:cache
    sudo -u www-data php "$APP_PATH/artisan" route:cache
    sudo -u www-data php "$APP_PATH/artisan" view:cache
    
    log "Laravel cache optimization completed"
}

# 4. System Package Update
update_packages() {
    log "Updating system packages..."
    
    # Update package lists
    apt update 2>/dev/null || warning "Failed to update package lists"
    
    # Check for security updates
    SECURITY_UPDATES=$(apt list --upgradable 2>/dev/null | grep -i security | wc -l)
    if [ "$SECURITY_UPDATES" -gt 0 ]; then
        log "Found $SECURITY_UPDATES security updates"
        warning "Security updates available - manual review required"
    fi
    
    log "Package update check completed"
}

# 5. File System Optimization
optimize_filesystem() {
    log "Optimizing file system..."
    
    # Preload frequently used files
    if [ -f "$APP_PATH/bootstrap/preload.php" ]; then
        sudo -u www-data php "$APP_PATH/bootstrap/preload.php" 2>/dev/null || warning "Preload failed"
    fi
    
    log "File system optimization completed"
}

# 6. Cleanup temporary files
cleanup_temp() {
    log "Cleaning up temporary files..."
    
    # Clean application temp files
    find "$APP_PATH/storage/framework/cache/data" -type f -mtime +7 -delete 2>/dev/null || true
    find "$APP_PATH/storage/framework/sessions" -type f -mtime +7 -delete 2>/dev/null || true
    
    # Clean system temp
    find /tmp -type f -mtime +7 -delete 2>/dev/null || true
    find /var/tmp -type f -mtime +7 -delete 2>/dev/null || true
    
    log "Temporary files cleanup completed"
}

# Main execution
main() {
    optimize_database
    optimize_redis
    optimize_laravel_cache
    update_packages
    optimize_filesystem
    cleanup_temp
    
    log "Weekly optimization completed"
}

main "$@"
```

#### إضافة Cron Jobs:
```bash
# Add to crontab
sudo crontab -e

# Weekly system audit on Sunday at 2 AM
0 2 * * 0 /var/www/your-app/scripts/maintenance/weekly-system-audit.sh

# Weekly optimization on Sunday at 3 AM
0 3 * * 0 /var/www/your-app/scripts/maintenance/weekly-optimization.sh
```

---

## 3. الصيانة الشهرية

### 3.1 الفحص الشامل للشهادات والتحديثات

#### إنشاء `/var/www/your-app/scripts/maintenance/monthly-security-audit.sh`:
```bash
#!/bin/bash

# Monthly Security Audit Script
# Comprehensive security audit and certificate check

set -euo pipefail

APP_NAME="laravel-app"
APP_PATH="/var/www/your-app"
LOG_FILE="/var/log/monthly-security-audit-$(date +%Y%m).log"
SECURITY_REPORT="/var/log/security-audit-report-$(date +%Y%m).txt"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log() { echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"; }
warning() { echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"; }
error() { echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"; }

# Initialize report
cat > "$SECURITY_REPORT" << EOF
تقرير المراجعة الأمنية الشهرية
===============================
التطبيق: $APP_NAME
التاريخ: $(date)
الشهر: $(date +%Y-%m)

EOF

# 1. SSL Certificate Analysis
analyze_ssl_certificates() {
    log "Analyzing SSL certificates..."
    
    {
        echo "تحليل شهادات SSL:"
        echo "================="
        
        DOMAIN="yourdomain.com"
        
        # Get certificate details
        if command -v openssl &> /dev/null; then
            CERT_INFO=$(echo | openssl s_client -servername "$DOMAIN" -connect "$DOMAIN:443" 2>/dev/null | openssl x509 -noout -text)
            
            echo "معلومات الشهادة:"
            echo "$CERT_INFO" | grep -E "(Subject:|Issuer:|Not Before:|Not After:)" | sed 's/^/  /'
            
            # Calculate days until expiry
            EXPIRY_DATE=$(echo | openssl s_client -servername "$DOMAIN" -connect "$DOMAIN:443" 2>/dev/null | openssl x509 -noout -enddate | cut -d= -f2)
            EXPIRY_EPOCH=$(date -d "$EXPIRY_DATE" +%s)
            CURRENT_EPOCH=$(date +%s)
            DAYS_UNTIL_EXPIRY=$(( (EXPIRY_EPOCH - CURRENT_EPOCH) / 86400 ))
            
            echo "المتبقي حتى انتهاء الصلاحية: $DAYS_UNTIL_EXPIRY يوم"
            
            if [ "$DAYS_UNTIL_EXPIRY" -lt 30 ]; then
                warning "تحذير: الشهادة تنتهي خلال $DAYS_UNTIL_EXPIRY يوم"
            fi
        fi
        
        echo ""
    } >> "$SECURITY_REPORT"
}

# 2. Security Headers Check
check_security_headers() {
    log "Checking security headers..."
    
    {
        echo "فحص الرؤوس الأمنية:"
        echo "==================="
        
        HEADERS=$(curl -I -s https://$DOMAIN)
        
        # Check for security headers
        echo "حالة الرؤوس الأمنية:"
        echo "$HEADERS" | grep -i "X-Frame-Options" | sed 's/^/  /' || echo "  ⚠ X-Frame-Options مفقود"
        echo "$HEADERS" | grep -i "X-Content-Type-Options" | sed 's/^/  /' || echo "  ⚠ X-Content-Type-Options مفقود"
        echo "$HEADERS" | grep -i "X-XSS-Protection" | sed 's/^/  /' || echo "  ⚠ X-XSS-Protection مفقود"
        echo "$HEADERS" | grep -i "Strict-Transport-Security" | sed 's/^/  /' || echo "  ⚠ Strict-Transport-Security مفقود"
        echo "$HEADERS" | grep -i "Content-Security-Policy" | sed 's/^/  /' || echo "  ⚠ Content-Security-Policy مفقود"
        
        echo ""
    } >> "$SECURITY_REPORT"
}

# 3. Vulnerability Assessment
vulnerability_assessment() {
    log "Performing vulnerability assessment..."
    
    {
        echo "تقييم الثغرات الأمنية:"
        echo "======================"
        
        # Check for Composer security advisories
        if command -v composer &> /dev/null; then
            echo "فحص تحديثات الأمان للمشروع:"
            cd "$APP_PATH"
            SECURITY_AUDIT=$(composer audit --format=json 2>/dev/null | jq -r '.advisories | length' 2>/dev/null || echo "0")
            echo "عدد التحذيرات الأمنية: $SECURITY_AUDIT"
            
            if [ "$SECURITY_AUDIT" -gt 0 ]; then
                composer audit 2>/dev/null | head -20
            fi
        fi
        
        # Check system vulnerabilities
        echo "فحص ثغرات النظام:"
        if command -v apt &> /dev/null; then
            VULNERABLE_PACKAGES=$(apt list --upgradable 2>/dev/null | grep -i security | wc -l)
            echo "حزم تحتاج تحديثات أمنية: $VULNERABLE_PACKAGES"
        fi
        
        echo ""
    } >> "$SECURITY_REPORT"
}

# 4. User Account Security
user_security_audit() {
    log "Auditing user accounts..."
    
    {
        echo "مراجعة أمان حسابات المستخدمين:"
        echo "==============================="
        
        # Check for inactive users
        INACTIVE_USERS=$(mysql -uapp_user -pstrong_password production_app -e "
            SELECT COUNT(*) FROM users 
            WHERE last_login_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND status = 'active';
        " 2>/dev/null | tail -1)
        
        echo "المستخدمين غير النشطين (30+ يوم): $INACTIVE_USERS"
        
        # Check for users with weak passwords
        WEAK_PASSWORDS=$(mysql -uapp_user -pstrong_password production_app -e "
            SELECT COUNT(*) FROM users 
            WHERE password_updated_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
        " 2>/dev/null | tail -1)
        
        echo "المستخدمين بكلمات مرور قديمة (90+ يوم): $WEAK_PASSWORDS"
        
        # Failed login attempts
        FAILED_LOGINS=$(mysql -uapp_user -pstrong_password production_app -e "
            SELECT COUNT(*) FROM user_activities 
            WHERE activity_type = 'login_failed' 
            AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY);
        " 2>/dev/null | tail -1)
        
        echo "محاولات الدخول الفاشلة (آخر 7 أيام): $FAILED_LOGINS"
        
        echo ""
    } >> "$SECURITY_REPORT"
}

# 5. File System Security
file_security_audit() {
    log "Auditing file system security..."
    
    {
        echo "مراجعة أمان نظام الملفات:"
        echo "========================="
        
        # Check for world-writable files
        WORLD_WRITABLE=$(find "$APP_PATH" -type f -perm /o+w 2>/dev/null | wc -l)
        echo "الملفات القابلة للكتابة للجميع: $WORLD_WRITABLE"
        
        if [ "$WORLD_WRITABLE" -gt 0 ]; then
            find "$APP_PATH" -type f -perm /o+w 2>/dev/null | head -10
        fi
        
        # Check sensitive file permissions
        echo "أذونات الملفات الحساسة:"
        if [ -f "$APP_PATH/.env" ]; then
            ENV_PERMS=$(stat -c "%a" "$APP_PATH/.env" 2>/dev/null || echo "N/A")
            echo "ملف .env: $ENV_PERMS (يجب أن يكون 600)"
        fi
        
        if [ -f "$APP_PATH/composer.json" ]; then
            COMPOSER_PERMS=$(stat -c "%a" "$APP_PATH/composer.json" 2>/dev/null || echo "N/A")
            echo "ملف composer.json: $COMPOSER_PERMS"
        fi
        
        echo ""
    } >> "$SECURITY_REPORT"
}

# Generate security recommendations
generate_security_recommendations() {
    log "Generating security recommendations..."
    
    {
        echo "التوصيات الأمنية:"
        echo "================="
        
        # SSL recommendations
        echo "شهادات SSL:"
        echo "• تجديد الشهادات قبل انتهاء صلاحيتها بـ 30 يوم"
        echo "• استخدام شهادات بتقنية SHA-256 على الأقل"
        echo "• تطبيق HSTS headers"
        
        # Application security
        echo "أمان التطبيق:"
        echo "• تحديث جميع المكتبات والمكونات بانتظام"
        echo "• تفعيل التقارير الأمنية لمراقبة محاولات الاختراق"
        echo "• مراجعة صلاحيات المستخدمين دورياً"
        
        # System security
        echo "أمان النظام:"
        echo "• تطبيق جميع تحديثات الأمان فوراً"
        echo "• استخدام fail2ban لحماية من هجمات brute force"
        echo "• مراجعة سجلات الدخول والحسابات المشبوهة"
        
        echo ""
    } >> "$SECURITY_REPORT"
}

# Main execution
main() {
    log "Starting monthly security audit"
    
    analyze_ssl_certificates
    check_security_headers
    vulnerability_assessment
    user_security_audit
    file_security_audit
    generate_security_recommendations
    
    log "Monthly security audit completed. Report saved to: $SECURITY_REPORT"
    
    # Send report via email
    if command -v mail &> /dev/null; then
        mail -s "تقرير المراجعة الأمنية الشهرية - $APP_NAME" -a "$SECURITY_REPORT" admin@yourdomain.com < /dev/null 2>/dev/null || true
    fi
}

main "$@"
```

---

## 4. إدارة النسخ الاحتياطية

### 4.1 استراتيجية النسخ الاحتياطي

#### إنشاء `/var/www/your-app/scripts/backup/backup-strategy.sh`:
```bash
#!/bin/bash

# Backup Strategy Implementation Script
# Implements 3-2-1 backup strategy: 3 copies, 2 different media, 1 offsite

set -euo pipefail

APP_NAME="laravel-app"
APP_PATH="/var/www/your-app"
LOCAL_BACKUP_DIR="/var/backups/$APP_NAME"
OFFSITE_BACKUP_DIR="/mnt/backups/offsite"
CLOUD_BACKUP_S3="s3://your-backup-bucket/laravel-app"

# Retention periods (in days)
FULL_BACKUP_RETENTION=90
INCREMENTAL_BACKUP_RETENTION=30
DAILY_BACKUP_RETENTION=7

log() { echo -e "[$(date +'%Y-%m-%d %H:%M:%S')] $1"; }

create_full_backup() {
    local backup_name="${APP_NAME}_full_$(date +%Y%m%d_%H%M%S)"
    local backup_dir="${LOCAL_BACKUP_DIR}/full/$backup_name"
    
    log "Creating full backup: $backup_name"
    
    mkdir -p "$backup_dir"
    
    # 1. Database backup
    log "Backing up database..."
    mysqldump --user=app_user --password=strong_password \
        --single-transaction --routines --triggers \
        production_app | gzip > "$backup_dir/database.sql.gz"
    
    # 2. Application files
    log "Backing up application files..."
    tar --exclude="$APP_PATH/vendor" \
        --exclude="$APP_PATH/node_modules" \
        --exclude="$APP_PATH/.git" \
        --exclude="$APP_PATH/storage/logs" \
        -czf "$backup_dir/application.tar.gz" \
        -C "$APP_PATH" .
    
    # 3. Configuration files
    log "Backing up configuration..."
    tar -czf "$backup_dir/config.tar.gz" \
        /etc/nginx /etc/php/8.2/fpm /etc/mysql /etc/redis 2>/dev/null || true
    
    # 4. Redis backup
    log "Backing up Redis..."
    redis-cli --rdb - | gzip > "$backup_dir/redis.rdb.gz" || true
    
    # 5. Create backup manifest
    cat > "$backup_dir/manifest.txt" << EOF
Backup Name: $backup_name
Type: Full Backup
Date: $(date)
Application: $APP_NAME
Version: $(cd "$APP_PATH" && git describe --tags --always 2>/dev/null || echo "unknown")
Components:
- Database: database.sql.gz
- Application: application.tar.gz
- Config: config.tar.gz
- Redis: redis.rdb.gz
EOF
    
    # 6. Calculate checksums
    cd "$backup_dir"
    find . -type f -exec sha256sum {} \; > checksums.sha256
    cd - > /dev/null
    
    log "Full backup completed: $backup_dir"
    
    # 7. Cleanup old full backups
    find "${LOCAL_BACKUP_DIR}/full" -type d -mtime +$FULL_BACKUP_RETENTION -exec rm -rf {} + 2>/dev/null || true
    
    # 8. Copy to offsite
    copy_to_offsite "$backup_dir"
    
    # 9. Copy to cloud
    copy_to_cloud "$backup_dir"
}

create_incremental_backup() {
    local backup_name="${APP_NAME}_incremental_$(date +%Y%m%d_%H%M%S)"
    local backup_dir="${LOCAL_BACKUP_DIR}/incremental/$backup_name"
    
    log "Creating incremental backup: $backup_name"
    
    mkdir -p "$backup_dir"
    
    # Find last full backup
    local last_full_backup=$(find "${LOCAL_BACKUP_DIR}/full" -type d -name "${APP_NAME}_full_*" -printf '%T@ %p\n' | sort -n | tail -1 | cut -d' ' -f2-)
    
    if [ -z "$last_full_backup" ]; then
        log "No full backup found, creating full backup instead"
        create_full_backup
        return
    fi
    
    # 1. Database incremental (only today's changes)
    log "Backing up database changes..."
    mysqldump --user=app_user --password=strong_password \
        --single-transaction --where="DATE(created_at) = CURDATE()" \
        production_app | gzip > "$backup_dir/database_incremental.sql.gz" 2>/dev/null || true
    
    # 2. Changed files since last backup
    log "Backing up changed files..."
    find "$APP_PATH" -type f -newermt "$(stat -c %Y "$last_full_backup" 2>/dev/null | xargs -I {} date -d @{} +%Y-%m-%d)" \
        ! -path "$APP_PATH/vendor/*" \
        ! -path "$APP_PATH/node_modules/*" \
        ! -path "$APP_PATH/.git/*" \
        ! -path "$APP_PATH/storage/logs/*" \
        -exec tar -czf "$backup_dir/changed_files.tar.gz" -T - \; 2>/dev/null || true
    
    # 3. Create manifest
    cat > "$backup_dir/manifest.txt" << EOF
Backup Name: $backup_name
Type: Incremental Backup
Date: $(date)
Based on: $last_full_backup
Components:
- Database changes: database_incremental.sql.gz
- Changed files: changed_files.tar.gz
EOF
    
    log "Incremental backup completed: $backup_dir"
    
    # 4. Cleanup old incremental backups
    find "${LOCAL_BACKUP_DIR}/incremental" -type d -mtime +$INCREMENTAL_BACKUP_RETENTION -exec rm -rf {} + 2>/dev/null || true
}

copy_to_offsite() {
    local backup_dir="$1"
    local backup_name=$(basename "$backup_dir")
    
    if [ -d "$OFFSITE_BACKUP_DIR" ]; then
        log "Copying backup to offsite location..."
        rsync -avz "$backup_dir/" "$OFFSITE_BACKUP_DIR/$backup_name/" 2>/dev/null || warning "Offsite copy failed"
    fi
}

copy_to_cloud() {
    local backup_dir="$1"
    local backup_name=$(basename "$backup_dir")
    
    if command -v aws &> /dev/null; then
        log "Copying backup to cloud..."
        aws s3 sync "$backup_dir" "${CLOUD_BACKUP_S3}/$(date +%Y-%m)/$backup_name/" \
            --storage-class GLACIER 2>/dev/null || warning "Cloud copy failed"
    fi
}

verify_backup() {
    local backup_dir="$1"
    
    log "Verifying backup: $backup_dir"
    
    # Check if all files exist
    local manifest="$backup_dir/manifest.txt"
    if [ -f "$manifest" ]; then
        while IFS= read -r line; do
            if [[ "$line" == *":"* ]] && [[ "$line" != "Components:"* ]]; then
                local component=$(echo "$line" | cut -d':' -f1 | xargs)
                local file=$(echo "$line" | cut -d':' -f2- | xargs)
                if [ -n "$file" ] && [ "$file" != "-" ]; then
                    if [ ! -f "$backup_dir/$file" ]; then
                        error "Missing file: $file"
                        return 1
                    fi
                fi
            fi
        done < "$manifest"
    fi
    
    # Verify checksums
    if [ -f "$backup_dir/checksums.sha256" ]; then
        cd "$backup_dir"
        if ! sha256sum -c checksums.sha256 > /dev/null 2>&1; then
            error "Checksum verification failed"
            return 1
        fi
        cd - > /dev/null
    fi
    
    log "Backup verification passed"
}

# Main execution
main() {
    local backup_type="${1:-full}"
    
    case "$backup_type" in
        "full")
            create_full_backup
            ;;
        "incremental")
            create_incremental_backup
            ;;
        "verify")
            local backup_dir="$2"
            if [ -n "$backup_dir" ]; then
                verify_backup "$backup_dir"
            else
                echo "Usage: $0 verify <backup_directory>"
                exit 1
            fi
            ;;
        *)
            echo "Usage: $0 {full|incremental|verify <backup_dir>}"
            exit 1
            ;;
    esac
}

main "$@"
```

#### إضافة Cron Jobs للنسخ الاحتياطي:
```bash
# Add to crontab
sudo crontab -e

# Full backup every Sunday at 3 AM
0 3 * * 0 /var/www/your-app/scripts/backup/backup-strategy.sh full

# Incremental backup daily at 2 AM (except Sunday)
0 2 * * 1-6 /var/www/your-app/scripts/backup/backup-strategy.sh incremental

# Backup verification weekly on Saturday at 4 AM
0 4 * * 6 /var/www/your-app/scripts/backup/backup-strategy.sh verify "$(find /var/backups/laravel-app/full -type d -name 'laravel-app_full_*' -printf '%T@ %p\n' | sort -n | tail -1 | cut -d' ' -f2-)"
```

---

## الخلاصة

هذا الدليل يغطي:
- الفحوصات اليومية للصحة العامة
- الصيانة الأسبوعية والتحسين
- الصيانة الشهرية الشاملة
- إدارة النسخ الاحتياطية المتقدمة
- فحص الأمان الدوري

الهدف هو ضمان تشغيل النظام بكفاءة عالية وأمان تام من خلال إجراءات صيانة منتظمة ومنهجية.