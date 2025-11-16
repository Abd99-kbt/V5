# دليل العمليات التشغيلية - Laravel Production System

## نظرة عامة
هذا الدليل يغطي جميع العمليات التشغيلية اليومية والأسبوعية والشهرية والربع سنوية لضمان تشغيل النظام بكفاءة عالية.

---

## 1. قائمة المهام اليومية (Daily Operations Checklist)

### 1.1 الفحوصات اليومية الأساسية

#### Morning System Health Check (8:00 AM)
- [ ] **تطبيق العمليات التشغيلية** - تشغيل السكريبت اليومي للصحة
- [ ] **مراقبة الأداء** - فحص أوقات الاستجابة وأخطاء الخادم
- [ ] **فحص المساحة** - التأكد من توفر مساحة القرص الكافية
- [ ] **فحص الذاكرة** - التأكد من عدم وجود تسريب للذاكرة
- [ ] **فحص الخدمات** - التأكد من عمل جميع الخدمات بشكل طبيعي
- [ ] **فحص الأمان** - مراجعة محاولات الدخول المشبوهة
- [ ] **فحص النسخ الاحتياطية** - التأكد من نجاح نسخ الأمس

#### إنشاء `scripts/operations/daily-checklist.sh`:
```bash
#!/bin/bash

# Daily Operations Checklist Script
# This script performs comprehensive daily system checks

set -euo pipefail

APP_NAME="laravel-app"
APP_PATH="/var/www/your-app"
LOG_DIR="/var/log/daily-checks"
DATE=$(date +%Y%m%d)
LOG_FILE="$LOG_DIR/daily-checklist-$DATE.log"
ALERT_EMAIL="admin@yourdomain.com"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log() {
    echo -e "${GREEN}[$(date +'%H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"
}

# Initialize log directory
mkdir -p "$LOG_DIR"

log "=========================================="
log "بدء الفحص اليومي للنظام - $APP_NAME"
log "التاريخ: $(date)"
log "=========================================="

# 1. Application Health Check
check_application() {
    log "1. فحص صحة التطبيق..."
    
    # Check main application endpoint
    if curl -f -s http://localhost/health > /dev/null; then
        log "  ✓ التطبيق يستجيب على /health"
    else
        error "  ✗ التطبيق لا يستجيب على /health"
    fi
    
    # Check admin panel
    if curl -f -s http://localhost/admin > /dev/null; then
        log "  ✓ لوحة الإدارة متاحة"
    else
        warning "  ⚠ لوحة الإدارة قد لا تكون متاحة"
    fi
}

# 2. Database Health Check
check_database() {
    log "2. فحص صحة قاعدة البيانات..."
    
    # Check MySQL connection
    if mysql -uapp_user -pstrong_password -e "SELECT 1;" > /dev/null 2>&1; then
        log "  ✓ اتصال قاعدة البيانات يعمل"
        
        # Get database size
        DB_SIZE=$(mysql -uapp_user -pstrong_password -e "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'DB Size in MB' FROM information_schema.tables WHERE table_schema='production_app';" 2>/dev/null | tail -1)
        log "  ℹ حجم قاعدة البيانات: ${DB_SIZE}MB"
        
    else
        error "  ✗ فشل في الاتصال بقاعدة البيانات"
    fi
}

# 3. Cache Health Check
check_cache() {
    log "3. فحص صحة الذاكرة المؤقتة..."
    
    # Check Redis
    if redis-cli ping > /dev/null 2>&1; then
        log "  ✓ Redis متصل ويعمل"
        
        # Get Redis info
        REDIS_INFO=$(redis-cli info memory | grep used_memory_human | cut -d: -f2 | tr -d '\r')
        log "  ℹ استخدام ذاكرة Redis: $REDIS_INFO"
        
    else
        error "  ✗ Redis غير متصل"
    fi
}

# 4. System Resources Check
check_system_resources() {
    log "4. فحص موارد النظام..."
    
    # Disk space
    DISK_USAGE=$(df -h / | awk 'NR==2{print $5}' | sed 's/%//')
    if [ "$DISK_USAGE" -lt 80 ]; then
        log "  ✓ مساحة القرص: ${DISK_USAGE}% مستخدمة"
    elif [ "$DISK_USAGE" -lt 90 ]; then
        warning "  ⚠ مساحة القرص: ${DISK_USAGE}% مستخدمة (تحذير)"
    else
        error "  ✗ مساحة القرص: ${DISK_USAGE}% مستخدمة (حرج)"
    fi
    
    # Memory usage
    MEM_USAGE=$(free | grep Mem | awk '{printf "%.0f", $3/$2 * 100.0}')
    if [ "$MEM_USAGE" -lt 80 ]; then
        log "  ✓ استخدام الذاكرة: ${MEM_USAGE}%"
    elif [ "$MEM_USAGE" -lt 90 ]; then
        warning "  ⚠ استخدام الذاكرة: ${MEM_USAGE}% (تحذير)"
    else
        error "  ✗ استخدام الذاكرة: ${MEM_USAGE}% (حرج)"
    fi
}

# 5. Services Status Check
check_services() {
    log "5. فحص حالة الخدمات..."
    
    SERVICES=("nginx" "php8.2-fpm" "mysql" "redis" "laravel-queue-worker")
    
    for service in "${SERVICES[@]}"; do
        if systemctl is-active --quiet "$service"; then
            log "  ✓ $service يعمل"
        else
            error "  ✗ $service لا يعمل"
        fi
    done
}

# 6. Log Analysis
check_logs() {
    log "6. تحليل السجلات..."
    
    # Check application logs for errors today
    ERROR_COUNT=$(find "$APP_PATH/storage/logs" -name "*.log" -exec grep -h "$(date +'Y-m-d')" {} \; 2>/dev/null | grep -i error | wc -l)
    
    if [ "$ERROR_COUNT" -eq 0 ]; then
        log "  ✓ لا توجد أخطاء في سجلات التطبيق اليوم"
    else
        warning "  ⚠ عدد الأخطاء في سجلات التطبيق اليوم: $ERROR_COUNT"
    fi
}

# Main execution
main() {
    check_application
    check_database
    check_cache
    check_system_resources
    check_services
    check_logs
    
    log "=========================================="
    log "انتهى الفحص اليومي"
    log "=========================================="
}

# Run if executed directly
if [ "${BASH_SOURCE[0]}" == "${0}" ]; then
    main "$@"
fi
```

#### إضافة Cron Job للفحص اليومي:
```bash
# Add to crontab
sudo crontab -e

# Daily system health check at 8:00 AM
0 8 * * * /var/www/your-app/scripts/operations/daily-checklist.sh
```

### 1.2 فحوصات الأداء اليومية

#### Afternoon Performance Review (2:00 PM)
- [ ] **مراجعة أوقات الاستجابة** - فحص متوسط أوقات الاستجابة
- [ ] **فحص أحمال قاعدة البيانات** - مراجعة الاستعلامات البطيئة
- [ ] **فحص استخدام الذاكرة** - التأكد من عدم وجود تسريبات
- [ ] **مراجعة السجلات** - فحص الأخطاء والتحذيرات

#### Evening System Preparation (6:00 PM)
- [ ] **تحضير للنشر الليلي** - فحص أن جميع الإعدادات جاهزة
- [ ] **تنظيف السجلات** - ضغط وتنظيف السجلات القديمة
- [ ] **فحص الأمان** - مراجعة نشاط اليوم والمصادقة
- [ ] **تحديث المراقبة** - تحديث إعدادات التنبيهات

---

## 2. المهام الأسبوعية (Weekly Maintenance Tasks)

### 2.1 Sunday System Audit

#### إنشاء `scripts/operations/weekly-audit.sh`:
```bash
#!/bin/bash

# Weekly System Audit Script
# Usage: ./weekly-audit.sh

set -euo pipefail

APP_NAME="laravel-app"
APP_PATH="/var/www/your-app"
LOG_FILE="/var/log/weekly-audit-$(date +%Y%W).log"
REPORT_FILE="/var/log/weekly-audit-report-$(date +%Y%W).txt"

log() { echo "[$(date +'%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"; }

log "Starting weekly system audit..."

# 1. System performance review
log "Performing system performance review..."

# CPU usage over the week
log "CPU Usage Analysis:"
sar -u 1 1 2>/dev/null || log "sar not available"

# Memory usage patterns
log "Memory Usage Analysis:"
free -h

# Disk usage patterns
log "Disk Usage Analysis:"
df -h

# 2. Application performance analysis
log "Analyzing application performance..."

# Laravel performance metrics
php -r "
    // Check Laravel configuration cache
    if (file_exists('$APP_PATH/bootstrap/cache/config.php')) {
        echo 'Config cache: EXISTS' . PHP_EOL;
    } else {
        echo 'Config cache: MISSING' . PHP_EOL;
    }
    
    if (file_exists('$APP_PATH/bootstrap/cache/routes.php')) {
        echo 'Route cache: EXISTS' . PHP_EOL;
    } else {
        echo 'Route cache: MISSING' . PHP_EOL;
    }
"

# 3. Database maintenance
log "Performing database maintenance..."

# Analyze and optimize tables
mysql -uapp_user -pstrong_password -e "
    SELECT 
        table_name,
        ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)',
        table_rows
    FROM information_schema.tables 
    WHERE table_schema = 'production_app'
    ORDER BY (data_length + index_length) DESC
    LIMIT 10
" 2>/dev/null || log "Database analysis failed"

# 4. Security audit
log "Performing security audit..."

# Check file permissions
find "$APP_PATH" -type f -name "*.php" -perm /o+w 2>/dev/null | head -10 | while read file; do
    log "World-writable file: $file"
done

# 5. Log analysis
log "Analyzing log files..."

# Application log analysis
ERROR_COUNT=$(find "$APP_PATH/storage/logs" -name "*.log" -exec grep -i error {} \; 2>/dev/null | wc -l)
WARNING_COUNT=$(find "$APP_PATH/storage/logs" -name "*.log" -exec grep -i warning {} \; 2>/dev/null | wc -l)

log "Application log statistics:"
log "  - Errors found: $ERROR_COUNT"
log "  - Warnings found: $WARNING_COUNT"

# 6. Performance optimization
log "Performing performance optimization..."

# Clear old cache entries
find "$APP_PATH/storage/framework/cache" -type f -mtime +1 -delete 2>/dev/null || true
find "$APP_PATH/storage/framework/sessions" -type f -mtime +1 -delete 2>/dev/null || true
find "$APP_PATH/storage/framework/views" -type f -mtime +1 -delete 2>/dev/null || true

# Optimize database tables
mysql -uapp_user -pstrong_password -e "OPTIMIZE TABLE \`production_app\`;" 2>/dev/null || log "Database optimization failed"

# 7. Generate weekly report
log "Generating weekly audit report..."

cat > "$REPORT_FILE" << EOF
تقرير المراجعة الأسبوعية
=======================
التطبيق: $APP_NAME
الأسبوع: $(date +%Y-W%V)
التاريخ: $(date)

النتائج الرئيسية:
- عدد الأخطاء: $ERROR_COUNT
- عدد التحذيرات: $WARNING_COUNT

التوصيات:
- مراجعة ملفات PHP التي لها صلاحيات كتابة للجميع
- تحليل الأخطاء المتكررة في سجلات التطبيق
- تحسين الاستعلامات البطيئة في قاعدة البيانات
- تنظيف السجلات القديمة
- تحديث إعدادات الأمان حسب الحاجة

الخطوات التالية:
- متابعة المراقبة اليومية
- تنفيذ التحسينات المطلوبة
- تحديث الوثائق
- مراجعة الأمان
EOF

log "Weekly audit report generated: $REPORT_FILE"

log "Weekly system audit completed"
```

#### إضافة Cron Job للمراجعة الأسبوعية:
```bash
# Add to crontab
sudo crontab -e

# Weekly system audit on Sunday at 1:00 AM
0 1 * * 0 /var/www/your-app/scripts/operations/weekly-audit.sh
```

---

## 3. المراجعات الشهرية (Monthly Reviews)

### 3.1 Monthly Security Review

#### إنشاء `scripts/operations/monthly-security-review.sh`:
```bash
#!/bin/bash

# Monthly Security Review Script
# Usage: ./monthly-security-review.sh

set -euo pipefail

APP_NAME="laravel-app"
APP_PATH="/var/www/your-app"
LOG_FILE="/var/log/monthly-security-$(date +%Y%m).log"
REPORT_FILE="/var/log/monthly-security-report-$(date +%Y%m).txt"

log() { echo "[$(date +'%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"; }

log "Starting monthly security review..."

# 1. SSL Certificate Review
log "Reviewing SSL certificates..."

# Check certificate expiration
if command -v openssl &> /dev/null; then
    DOMAIN="yourdomain.com"
    CERT_INFO=$(echo | openssl s_client -servername "$DOMAIN" -connect "$DOMAIN:443" 2>/dev/null | openssl x509 -noout -text)
    
    echo "$CERT_INFO" | grep -E "(Subject:|Issuer:|Not Before:|Not After:)" | log
fi

# 2. Security Headers Review
log "Reviewing security headers..."

# Check security headers
HEADERS=$(curl -I -s https://$DOMAIN)

log "Security headers status:"
echo "$HEADERS" | grep -i "X-Frame-Options" | log || log "X-Frame-Options: MISSING"
echo "$HEADERS" | grep -i "X-Content-Type-Options" | log || log "X-Content-Type-Options: MISSING"
echo "$HEADERS" | grep -i "X-XSS-Protection" | log || log "X-XSS-Protection: MISSING"
echo "$HEADERS" | grep -i "Strict-Transport-Security" | log || log "Strict-Transport-Security: MISSING"

# 3. User Security Analysis
log "Analyzing user security..."

# Check inactive users
if mysql -uapp_user -pstrong_password production_app -e "SHOW TABLES LIKE 'users';" > /dev/null 2>&1; then
    INACTIVE_USERS=$(mysql -uapp_user -pstrong_password production_app -e "
        SELECT COUNT(*) FROM users 
        WHERE last_login_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND status = 'active';
    " 2>/dev/null | tail -1)
    
    log "Inactive users (30+ days): $INACTIVE_USERS"
fi

# 4. File Permissions Review
log "Reviewing file permissions..."

# Check sensitive file permissions
if [ -f "$APP_PATH/.env" ]; then
    ENV_PERMS=$(stat -c "%a" "$APP_PATH/.env" 2>/dev/null || echo "N/A")
    log "Config file (.env) permissions: $ENV_PERMS"
fi

# 5. Generate security report
log "Generating security report..."

cat > "$REPORT_FILE" << EOF
تقرير المراجعة الأمنية الشهرية
============================
التطبيق: $APP_NAME
الشهر: $(date +%Y-%m)
التاريخ: $(date)

نتائج المراجعة الأمنية:

1. شهادات SSL:
$(echo "$CERT_INFO" | grep -E "(Subject:|Issuer:|Not Before:|Not After:)" 2>/dev/null || echo "Certificate information not available")

2. الرؤوس الأمنية:
$(echo "$HEADERS" | grep -i "X-Frame-Options" 2>/dev/null || echo "X-Frame-Options: MISSING")

3. إحصائيات المستخدم:
- المستخدمون غير النشطين: $INACTIVE_USERS

4. أذونات الملفات:
- ملف التكوين: $ENV_PERMS

التوصيات الأمنية:
- تحديث شهادات SSL قبل انتهاء الصلاحية
- تطبيق جميع الرؤوس الأمنية المطلوبة
- مراجعة الحسابات غير النشطة
- تشديد سياسات كلمة المرور
- تفعيل المراقبة الأمنية المستمرة
EOF

log "Monthly security review completed. Report saved: $REPORT_FILE"
```

#### إضافة Cron Job للمراجعة الأمنية:
```bash
# Add to crontab
sudo crontab -e

# Monthly security review on the 1st at 2:00 AM
0 2 1 * * /var/www/your-app/scripts/operations/monthly-security-review.sh
```

---

## 4. المراجعات الربع سنوية (Quarterly Reviews)

### 4.1 Quarterly System Upgrade

#### إنشاء `scripts/operations/quarterly-upgrade.sh`:
```bash
#!/bin/bash

# Quarterly System Upgrade Script
# Usage: ./quarterly-upgrade.sh

set -euo pipefail

APP_PATH="/var/www/your-app"
LOG_FILE="/var/log/quarterly-upgrade-$(date +%YQ$(( ($(date +%m)-1)/3+1 ))).log"
BACKUP_DIR="/var/backups/pre-upgrade"
REPORT_FILE="/var/log/quarterly-upgrade-report-$(date +%YQ$(( ($(date +%m)-1)/3+1 ))).txt"

log() { echo "[$(date +'%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"; }

log "Starting quarterly system upgrade..."

# 1. Pre-upgrade backup
log "Creating pre-upgrade backup..."
mkdir -p "$BACKUP_DIR"
BACKUP_NAME="pre-upgrade-$(date +%Y%m%d_%H%M%S)"

tar -czf "$BACKUP_DIR/$BACKUP_NAME.tar.gz" \
    -C "$APP_PATH" . \
    || error "Backup creation failed"

log "Pre-upgrade backup created: $BACKUP_NAME.tar.gz"

# 2. System updates
log "Updating system packages..."
apt update && apt upgrade -y || warning "System update failed"

# 3. PHP updates
log "Updating PHP and extensions..."
apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-xml \
    php8.2-curl php8.2-zip php8.2-mbstring php8.2-bcmath \
    php8.2-gd php8.2-redis || warning "PHP update failed"

# 4. Application dependencies
log "Updating application dependencies..."
cd "$APP_PATH"
sudo -u www-data composer update --no-dev || warning "Composer update failed"
npm update || warning "NPM update failed"

# 5. Performance optimization
log "Optimizing performance..."
sudo -u www-data php artisan optimize
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

# 6. Generate upgrade report
log "Generating upgrade report..."

cat > "$REPORT_FILE" << EOF
تقرير الترقية الربع سنوية
========================
التطبيق: Laravel Production System
الربع: $(date +%Y-Q$(( ($(date +%m)-1)/3+1 )))
التاريخ: $(date)

تم تنفيذ الترقية بنجاح:

1. النسخ الاحتياطية:
- تم إنشاء نسخة احتياطية قبل الترقية
- اسم الملف: $BACKUP_NAME.tar.gz

2. التحديثات المنفذة:
- تحديث حزم النظام
- تحديث PHP وإضافاته
- تحديث مكتبات التطبيق
- تحديث Node.js packages

3. التحسينات:
- تحسين الأداء
- تحديث ذاكرات التخزين المؤقت

4. الخطوات التالية:
- مراقبة الأداء لمدة 24 ساعة
- اختبار الوظائف الرئيسية
- مراجعة السجلات للأخطاء
- تحديث الوثائق

النسخ الاحتياطية متاحة في: $BACKUP_DIR
EOF

log "Quarterly upgrade completed successfully!"
log "Upgrade report saved: $REPORT_FILE"
log "Pre-upgrade backup: $BACKUP_DIR/$BACKUP_NAME.tar.gz"
```

#### إضافة Cron Job للترقية الربع سنوية:
```bash
# Add to crontab
sudo crontab -e

# Quarterly upgrade on the 1st day of quarter at 3:00 AM
0 3 1 1,4,7,10 * /var/www/your-app/scripts/operations/quarterly-upgrade.sh
```

---

## الخلاصة

هذا الدليل يضمن:
- فحص يومي شامل لحالة النظام
- صيانة أسبوعية منهجية
- مراجعة أمنية شهرية
- ترقية وتطوير ربع سنوي
- مراقبة مستمرة للأداء

الهدف هو ضمان تشغيل مستقر وآمن وفعال للنظام على المدى الطويل من خلال العمليات التشغيلية المنتظمة والمنهجية.