# دليل استكشاف الأخطاء - Laravel Production System

## نظرة عامة
هذا الدليل يوفر حلولاً شاملة للمشاكل الشائعة في نظام Laravel الإنتاجي، مع أدوات تشخيصية وأوامر للمساعدة في حل المشاكل بسرعة.

---

## 1. المشاكل الشائعة والحلول

### 1.1 مشاكل التطبيق

#### مشكلة: التطبيق لا يحمل (White Screen of Death)
**الأعراض:**
- صفحة بيضاء بدون أي محتوى
- لا توجد رسائل خطأ
- لا يعمل أي endpoint

**أسباب محتملة:**
- خطأ في PHP fatal
- مشكلة في صلاحيات الملفات
- مشكلة في اتصال قاعدة البيانات
- مشكلة في إعدادات PHP

**خطوات التشخيص:**
```bash
# 1. فحص سجلات الخادم
tail -f /var/log/nginx/error.log
tail -f /var/log/php8.2-fpm.log

# 2. فحص سجلات Laravel
tail -f /var/www/your-app/storage/logs/laravel.log

# 3. فحص حالة PHP-FPM
systemctl status php8.2-fpm

# 4. اختبار اتصال PHP
php -v
php -m | grep -E "(mysql|redis|curl|mbstring)"

# 5. فحص صلاحيات الملفات
ls -la /var/www/your-app/
ls -la /var/www/your-app/storage/
ls -la /var/www/your-app/bootstrap/cache/
```

**الحلول:**
```bash
# 1. إصلاح صلاحيات الملفات
sudo chown -R www-data:www-data /var/www/your-app
sudo chmod -R 755 /var/www/your-app
sudo chmod -R 775 /var/www/your-app/storage
sudo chmod -R 775 /var/www/your-app/bootstrap/cache

# 2. إعادة تشغيل الخدمات
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx

# 3. تحديث Composer
cd /var/www/your-app
sudo -u www-data composer install --no-dev --optimize-autoloader

# 4. تحديث ذاكرات التخزين المؤقت
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
```

#### مشكلة: خطأ 500 Internal Server Error
**الأعراض:**
- رسالة خطأ "Internal Server Error"
- سجلات nginx تحتوي على errors
- قد يكون هناك timeout

**خطوات التشخيص:**
```bash
# 1. فحص سجلات nginx
tail -50 /var/log/nginx/error.log

# 2. فحص سجلات PHP-FPM
tail -50 /var/log/php8.2-fpm.log

# 3. فحص سجلات التطبيق
tail -50 /var/www/your-app/storage/logs/laravel.log

# 4. فحص إعدادات PHP
php --ini
php -i | grep -E "(error_reporting|display_errors|log_errors)"
```

**الحلول:**
```bash
# 1. تفعيل عرض الأخطاء مؤقتاً للتشخيص
sudo nano /etc/php/8.2/fpm/conf.d/99-debug.ini

# إضافة:
# display_errors = On
# display_startup_errors = On
# error_reporting = E_ALL
# log_errors = On

# 2. إعادة تشغيل PHP-FPM
sudo systemctl restart php8.2-fpm
```

### 1.2 مشاكل قاعدة البيانات

#### مشكلة: Connection Refused أو Access Denied
**الأعراض:**
- خطأ "Connection refused"
- خطأ "Access denied for user"
- التطبيق لا يستطيع الاتصال

**خطوات التشخيص:**
```bash
# 1. فحص حالة MySQL
systemctl status mysql

# 2. اختبار الاتصال
mysql -uapp_user -pstrong_password -h 127.0.0.1 -e "SELECT 1;"

# 3. فحص المنافذ
netstat -tlnp | grep 3306
ss -tlnp | grep 3306

# 4. فحص السجلات
tail -50 /var/log/mysql/error.log
```

**الحلول:**
```bash
# 1. بدء MySQL
sudo systemctl start mysql

# 2. إنشاء المستخدم إذا لم يكن موجوداً
mysql -u root -p
CREATE USER 'app_user'@'localhost' IDENTIFIED BY 'strong_password';
GRANT ALL PRIVILEGES ON production_app.* TO 'app_user'@'localhost';
FLUSH PRIVILEGES;

# 3. إعادة إنشاء قاعدة البيانات
CREATE DATABASE production_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# 4. تشغيل migrations
cd /var/www/your-app
sudo -u www-data php artisan migrate --force
```

#### مشكلة: Queries بطيئة أو Memory Exhausted
**الأعراض:**
- استجابة بطيئة جداً
- خطأ "Memory exhausted"
- timeout في الاستعلامات

**خطوات التشخيص:**
```bash
# 1. فحص الاستعلامات البطيئة
mysql -u root -p -e "
SELECT * FROM mysql.slow_log 
WHERE start_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
ORDER BY query_time DESC 
LIMIT 10;"

# 2. فحص العمليات النشطة
mysql -u root -p -e "SHOW PROCESSLIST;"

# 3. فحص الذاكرة
free -h
ps aux | grep mysql
```

**الحلول:**
```bash
# 1. زيادة memory limit مؤقتاً
sudo nano /etc/php/8.2/fpm/conf.d/99-memory.ini
# memory_limit = 512M

# 2. تحسين MySQL settings
sudo nano /etc/mysql/conf.d/mysql-production.cnf
# innodb_buffer_pool_size = 2G

# 3. إعادة تشغيل الخدمات
sudo systemctl restart php8.2-fpm
sudo systemctl restart mysql
```

### 1.3 مشاكل Redis

#### مشكلة: Redis لا يستجيب
**الأعراض:**
- خطأ "Connection refused"
- Application cache لا يعمل
- Sessions تفقد

**خطوات التشخيص:**
```bash
# 1. فحص حالة Redis
systemctl status redis
redis-cli ping

# 2. فحص السجلات
tail -50 /var/log/redis/redis-server.log

# 3. فحص الذاكرة
redis-cli info memory
```

**الحلول:**
```bash
# 1. بدء Redis
sudo systemctl start redis

# 2. تنظيف الذاكرة إذا كانت ممتلئة
redis-cli FLUSHDB

# 3. إعادة تشغيل Redis
sudo systemctl restart redis
```

### 1.4 مشاكل الأداء

#### مشكلة: استجابة بطيئة
**الأعراض:**
- أوقات تحميل طويلة
- timeouts
- user complaints

**خطوات التشخيص:**
```bash
# 1. فحص أوقات الاستجابة
curl -w "@-" -o /dev/null -s <<< "time_total: %{time_total}" http://localhost

# 2. فحص CPU usage
top
htop

# 3. فحص Disk I/O
iostat -x 1 5

# 4. فحص العمليات النشطة
ps aux | sort -rk 3,3 | head -10
```

**الحلول:**
```bash
# 1. تحسين OPcache
sudo nano /etc/php/8.2/fpm/conf.d/99-opcache.ini
# opcache.memory_consumption=512

# 2. تحسين Redis
redis-cli CONFIG SET maxmemory-policy allkeys-lru

# 3. إعادة تشغيل الخدمات
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx
```

---

## 2. أماكن ملفات السجلات

### 2.1 ملفات النظام

#### سجلات النظام الأساسية
```bash
# سجلات النظام
/var/log/syslog              # سجلات النظام العامة
/var/log/auth.log            # سجلات المصادقة
/var/log/kern.log            # سجلات النواة

# سجلات التطبيق
/var/log/nginx/error.log     # أخطاء Nginx
/var/log/nginx/access.log    # سجلات الوصول لـ Nginx
/var/log/php8.2-fpm.log      # سجلات PHP-FPM
/var/log/mysql/error.log     # أخطاء MySQL
/var/log/redis/redis-server.log  # سجلات Redis
```

#### سجلات التطبيق
```bash
# Laravel Application Logs
/var/www/your-app/storage/logs/laravel.log        # السجل الرئيسي
/var/www/your-app/storage/logs/laravel.log.1      # أرشيف
/var/www/your-app/storage/logs/scheduler.log      # سجل Scheduler
/var/www/your-app/storage/logs/queue.log          # سجل Queue

# Laravel Framework Logs
/var/www/your-app/storage/logs/filament.log       # سجلات Filament
/var/www/your-app/storage/logs/backpack.log       # سجلات Backpack

# Laravel Custom Logs
/var/www/your-app/storage/logs/auth.log           # سجلات المصادقة
/var/www/your-app/storage/logs/security.log       # سجلات الأمان
/var/www/your-app/storage/logs/performance.log    # سجلات الأداء
```

### 2.2 أوامر مراقبة السجلات

#### أوامر مفيدة لمراقبة السجلات
```bash
# مراقبة السجلات في الوقت الفعلي
tail -f /var/log/nginx/error.log
tail -f /var/log/php8.2-fpm.log
tail -f /var/www/your-app/storage/logs/laravel.log

# البحث في السجلات
grep "error" /var/log/nginx/error.log
grep -i "fatal" /var/www/your-app/storage/logs/laravel.log
grep "$(date +'Y-m-d')" /var/www/your-app/storage/logs/laravel.log

# إحصائيات السجلات
wc -l /var/log/nginx/access.log
grep "2024-01" /var/log/nginx/access.log | wc -l
awk '{print $1}' /var/log/nginx/access.log | sort | uniq -c | sort -nr
```

---

## 3. الأوامر التشخيصية

### 3.1 أوامر النظام الأساسية

#### معلومات النظام
```bash
# معلومات النظام الأساسية
uname -a                           # معلومات النواة
cat /etc/os-release                # إصدار نظام التشغيل
uptime                             # مدة التشغيل
whoami                             # المستخدم الحالي
pwd                                # المجلد الحالي

# معلومات الأجهزة
lscpu                              # معلومات المعالج
free -h                            # معلومات الذاكرة
df -h                              # معلومات المساحة
lsblk                              # معلومات الأقراص
```

#### معلومات الشبكة
```bash
# معلومات الشبكة
ip addr show                       # عناوين IP
ip route show                      # جدول التوجيه
netstat -tlnp                      # المنافذ المفتوحة
ss -tlnp                          # منافذ TCP/UDP
ping -c 4 8.8.8.8                 # اختبار الاتصال
```

#### معلومات العمليات
```bash
# معلومات العمليات
ps aux                             # جميع العمليات
ps -ef | grep php                 # عمليات PHP
top                                # العمليات في الوقت الفعلي
htop                               # واجهة تفاعلية للعمليات
pgrep -f "php"                     # معرفات عمليات PHP
```

### 3.2 أوامر قاعدة البيانات

#### MySQL Diagnostics
```bash
# معلومات الاتصال
mysql -u root -p -e "SHOW STATUS;"                    # حالة MySQL
mysql -u root -p -e "SHOW VARIABLES;"                 # متغيرات MySQL
mysql -u root -p -e "SHOW PROCESSLIST;"               # العمليات النشطة
mysql -u root -p -e "SHOW ENGINE INNODB STATUS \G;"   # حالة InnoDB

# إحصائيات قاعدة البيانات
mysql -u root -p -e "
SELECT 
    table_schema AS 'Database',
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
FROM information_schema.tables 
WHERE table_schema = 'production_app'
GROUP BY table_schema;"

# الاستعلامات البطيئة
mysql -u root -p -e "
SELECT * FROM mysql.slow_log 
WHERE start_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
ORDER BY query_time DESC LIMIT 10;"
```

#### Redis Diagnostics
```bash
# معلومات Redis
redis-cli info                    # معلومات شاملة
redis-cli info memory             # معلومات الذاكرة
redis-cli info stats              # إحصائيات
redis-cli info clients            # معلومات العملاء

# مراقبة Redis
redis-cli monitor                 # مراقبة جميع الأوامر
redis-cli --latency               # مراقبة latency

# معلومات المفاتيح
redis-cli keys "*" | wc -l        # عدد المفاتيح
redis-cli info keyspace           # معلومات keyspace
```

### 3.3 أوامر Laravel

#### Laravel Diagnostics
```bash
# معلومات التطبيق
php artisan --version             # إصدار Laravel
php artisan env                   # متغيرات البيئة
php artisan config:show           # إعدادات التكوين
php artisan route:list            # قائمة المسارات

# حالة النظام
php artisan tinker               # shell تفاعلي
php artisan queue:work --dry-run # اختبار queue
php artisan schedule:list        # قائمة المهام المجدولة

# إصلاح المشاكل
php artisan config:clear         # تنظيف cache التكوين
php artisan route:clear          # تنظيف cache المسارات
php artisan view:clear           # تنظيف cache المشاهدات
php artisan cache:clear          # تنظيف cache العام
```

---

## 4. إجراءات الفحص الصحيحة

### 4.1 فحص سريع شامل

#### إنشاء `scripts/diagnostics/quick-health-check.sh`:
```bash
#!/bin/bash

# Quick Health Check Script
# Usage: ./quick-health-check.sh

echo "=== Quick System Health Check ==="
echo "Timestamp: $(date)"
echo

# 1. System Status
echo "1. System Status:"
uptime
echo

# 2. Application Status
echo "2. Application Status:"
if curl -f -s http://localhost/health > /dev/null; then
    echo "✓ Application: Responsive"
else
    echo "✗ Application: Not responding"
fi

if curl -f -s http://localhost/admin > /dev/null; then
    echo "✓ Admin Panel: Available"
else
    echo "✗ Admin Panel: Not available"
fi
echo

# 3. Database Status
echo "3. Database Status:"
if mysql -uapp_user -pstrong_password -e "SELECT 1;" > /dev/null 2>&1; then
    echo "✓ MySQL: Connected"
else
    echo "✗ MySQL: Connection failed"
fi
echo

# 4. Cache Status
echo "4. Cache Status:"
if redis-cli ping > /dev/null 2>&1; then
    echo "✓ Redis: Connected"
else
    echo "✗ Redis: Connection failed"
fi
echo

# 5. Services Status
echo "5. Services Status:"
for service in nginx php8.2-fpm mysql redis; do
    if systemctl is-active --quiet $service; then
        echo "✓ $service: Running"
    else
        echo "✗ $service: Not running"
    fi
done
echo

# 6. Resource Usage
echo "6. Resource Usage:"
echo "CPU Load: $(uptime | awk -F'load average:' '{print $2}')"
echo "Memory: $(free -h | grep Mem | awk '{print $3 "/" $2}')"
echo "Disk: $(df -h / | awk 'NR==2{print $3 "/" $2 " (" $5 " used)"}')"
echo

echo "=== Health Check Complete ==="
```

### 4.2 فحص مفصل للنظام

#### إنشاء `scripts/diagnostics/detailed-system-check.sh`:
```bash
#!/bin/bash

# Detailed System Check Script
# Usage: ./detailed-system-check.sh

LOG_FILE="/var/log/system-check-$(date +%Y%m%d_%H%M%S).log"

log() {
    echo "[$(date)] $1" | tee -a "$LOG_FILE"
}

log "Starting detailed system check..."

# 1. Application Details
log "Application Details:"
cd /var/www/your-app
php artisan --version
php artisan env
echo "Laravel Cache Status:"
ls -la bootstrap/cache/
echo

# 2. Database Details
log "Database Details:"
mysql -uapp_user -pstrong_password production_app -e "
SELECT 'Tables' as info, COUNT(*) as count FROM information_schema.tables WHERE table_schema = 'production_app'
UNION ALL
SELECT 'Table Size (MB)', ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.tables WHERE table_schema = 'production_app';
"
echo

# 3. Performance Metrics
log "Performance Metrics:"
echo "Response Time Test:"
time curl -s -o /dev/null -w "Total: %{time_total}s\n" http://localhost
echo

# 4. Error Analysis
log "Error Analysis:"
ERROR_COUNT=$(find /var/www/your-app/storage/logs -name "*.log" -exec grep -h "$(date +'Y-m-d')" {} \; 2>/dev/null | grep -i error | wc -l)
WARNING_COUNT=$(find /var/www/your-app/storage/logs -name "*.log" -exec grep -h "$(date +'Y-m-d')" {} \; 2>/dev/null | grep -i warning | wc -l)
log "Today's Errors: $ERROR_COUNT"
log "Today's Warnings: $WARNING_COUNT"
echo

log "Detailed system check completed. Log: $LOG_FILE"
```

---

## 5. معلومات الاتصال للدعم

### 5.1 جهات الاتصال الأساسية

#### معلومات الفريق التقني
```yaml
Team Contacts:
  Technical Manager:
    Name: "اسم المدير التقني"
    Email: "tech.manager@yourdomain.com"
    Phone: "+1234567890"
    On-Call: "24/7"

  Lead Developer:
    Name: "اسم قائد التطوير"
    Email: "lead.dev@yourdomain.com"
    Phone: "+1234567891"
    Specialization: "Backend, Database, Performance"

  DevOps Engineer:
    Name: "اسم مهندس DevOps"
    Email: "devops@yourdomain.com"
    Phone: "+1234567892"
    Specialization: "Infrastructure, Monitoring, CI/CD"
```

#### معلومات الطوارئ
```yaml
Emergency Contacts:
  Primary On-Call:
    Phone: "+1234567900"
    Email: "emergency@yourdomain.com"
    Escalation: "Contact Technical Manager after 30 minutes"

  Secondary On-Call:
    Phone: "+1234567901"
    Email: "secondary@yourdomain.com"
```

### 5.2 ساحات المشاكل المتخصصة

#### تصنيف المشاكل والأولويات
```yaml
Problem Classification:
  Critical (P0):
    Response Time: "15 minutes"
    Examples:
      - Complete system outage
      - Data loss or corruption
      - Security breach
      - Payment system failure

  High (P1):
    Response Time: "1 hour"
    Examples:
      - Major functionality broken
      - Performance severely degraded
      - Database connectivity issues
      - Authentication problems

  Medium (P2):
    Response Time: "4 hours"
    Examples:
      - Minor functionality issues
      - Performance problems
      - Configuration issues
      - Feature requests

  Low (P3):
    Response Time: "24 hours"
    Examples:
      - UI/UX issues
      - Documentation requests
      - Minor bug reports
      - Enhancement suggestions
```

### 5.3 قنوات التواصل

#### قنوات الدعم
```yaml
Support Channels:
  Emergency:
    Phone: "+1234567900"  # For P0 issues only
    Email: "emergency@yourdomain.com"
    Slack: "#emergency-response"

  General Support:
    Email: "support@yourdomain.com"
    Slack: "#support"
    Dashboard: "https://support.yourdomain.com"

  Development:
    Email: "dev-team@yourdomain.com"
    Slack: "#development"
    JIRA: "https://jira.yourdomain.com"
```

---

## الخلاصة

هذا الدليل يوفر:
- حلولاً شاملة للمشاكل الشائعة
- مواقع واضحة لجميع ملفات السجلات
- أوامر تشخيصية متقدمة
- إجراءات فحص منهجية
- معلومات اتصال منظمة للدعم

الهدف هو تمكين فريق الدعم من حل المشاكل بسرعة وكفاءة مع الحفاظ على استقرار النظام.