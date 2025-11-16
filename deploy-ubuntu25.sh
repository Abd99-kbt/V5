#!/bin/bash

# ===============================================
# سكريبت النشر الشامل لمشروع V5 على Ubuntu 25
# V5 Project Deployment Script for Ubuntu 25
# ===============================================

set -e  # إيقاف السكريبت عند حدوث خطأ

# متغيرات التكوين
PROJECT_NAME="V5"
PROJECT_PATH="/var/www/v5"
DB_NAME="v5_production"
DB_USER="v5_user"
DB_PASS=$(openssl rand -base64 32)
APP_URL="https://your-domain.com"
WEB_USER="www-data"
PHP_VERSION="8.2"

# الألوان للنص
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# دوال المساعدة
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

check_root() {
    if [[ $EUID -ne 0 ]]; then
        log_error "يجب تشغيل السكريبت كـ root (استخدم sudo)"
        exit 1
    fi
}

# ===============================================
# تثبيت المتطلبات الأساسية (بدون ترقية النظام)
# ===============================================
install_requirements() {
    log_info "تثبيت المتطلبات الأساسية (بدون ترقية النظام)..."
    
    # تحديث قائمة الحزم فقط (بدون ترقية)
    apt update -y
    
    # تثبيت الحزم الأساسية
    apt install -y curl wget git unzip software-properties-common apt-transport-https ca-certificates gnupg lsb-release
    
    # تثبيت أدوات البناء الأساسية
    apt install -y build-essential cmake pkg-config
    
    # تثبيت أدوات المراقبة والتشخيص
    apt install -y htop iotop netstat-nat tcpdump
    
    log_success "تم تثبيت المتطلبات الأساسية بنجاح"
}

# ===============================================
# تثبيت وتكوين MySQL
# ===============================================
install_mysql() {
    log_info "تثبيت وتكوين MySQL..."
    
    # إضافة مستودع MySQL
    wget https://dev.mysql.com/get/mysql-apt-config_0.8.24-1_all.deb
    dpkg -i mysql-apt-config_0.8.24-1_all.deb
    rm mysql-apt-config_0.8.24-1_all.deb
    
    # تحديث قائمة الحزم
    apt update -y
    
    # تثبيت MySQL Server
    apt install -y mysql-server mysql-client
    
    # بدء خدمة MySQL
    systemctl start mysql
    systemctl enable mysql
    
    # تأمين MySQL
    log_warning "سيتم الآن تأمين MySQL - اضغط Enter لكل سؤال واستخدم كلمة مرور قوية للـ root"
    mysql_secure_installation
    
    log_success "تم تثبيت وتكوين MySQL"
}

# ===============================================
# إنشاء قاعدة البيانات والمستخدم
# ===============================================
setup_database() {
    log_info "إنشاء قاعدة البيانات والمستخدم..."
    
    # إنشاء قاعدة البيانات
    mysql -u root -p <<EOF
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF

    log_success "تم إنشاء قاعدة البيانات: ${DB_NAME}"
    log_success "مستخدم قاعدة البيانات: ${DB_USER}"
    log_warning "كلمة مرور قاعدة البيانات: ${DB_PASS}"
    
    # حفظ معلومات قاعدة البيانات
    cat > /root/database_info.txt <<EOF
معلومات قاعدة البيانات - V5 Project
========================================
اسم قاعدة البيانات: ${DB_NAME}
اسم المستخدم: ${DB_USER}
كلمة المرور: ${DB_PASS}
تاريخ الإنشاء: $(date)
EOF
    
    chmod 600 /root/database_info.txt
}

# ===============================================
# تثبيت PHP والمكونات المطلوبة
# ===============================================
install_php() {
    log_info "تثبيت PHP ${PHP_VERSION} والمكونات المطلوبة..."
    
    # إضافة مستودع PHP
    add-apt-repository -y ppa:ondrej/php
    apt update -y
    
    # تثبيت PHP والمكونات
    apt install -y \
        php${PHP_VERSION} \
        php${PHP_VERSION}-fpm \
        php${PHP_VERSION}-mysql \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-gd \
        php${PHP_VERSION}-zip \
        php${PHP_VERSION}-intl \
        php${PHP_VERSION}-bcmath \
        php${PHP_VERSION}-soap \
        php${PHP_VERSION}-redis \
        php${PHP_VERSION}-imagick \
        php${PHP_VERSION}-opcache
    
    # إعداد PHP-FPM
    systemctl start php${PHP_VERSION}-fpm
    systemctl enable php${PHP_VERSION}-fpm
    
    log_success "تم تثبيت PHP ${PHP_VERSION}"
}

# ===============================================
# تثبيت Composer
# ===============================================
install_composer() {
    log_info "تثبيت Composer..."
    
    # تحميل وتثبيت Composer
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
    
    # تحديث PATH
    echo 'export PATH="$PATH:$HOME/.composer/vendor/bin"' >> ~/.bashrc
    
    log_success "تم تثبيت Composer"
}

# ===============================================
# تثبيت Node.js و npm
# ===============================================
install_nodejs() {
    log_info "تثبيت Node.js و npm..."
    
    # إضافة مستودع Node.js
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
    
    # تثبيت Node.js
    apt install -y nodejs
    
    # تثبيت yarn كبديل لـ npm
    npm install -g yarn
    
    log_success "تم تثبيت Node.js $(node --version) و npm $(npm --version)"
}

# ===============================================
# تثبيت وتكوين Nginx
# ===============================================
install_nginx() {
    log_info "تثبيت وتكوين Nginx..."
    
    # تثبيت Nginx
    apt install -y nginx
    
    # إنشاء ملف التكوين للموقع
    cat > /etc/nginx/sites-available/v5 <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${APP_URL};
    root ${PROJECT_PATH}/public;
    index index.php index.html index.htm;
    
    # إعادة توجيه HTTP إلى HTTPS
    return 301 https://\$server_name\$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name ${APP_URL};
    root ${PROJECT_PATH}/public;
    index index.php index.html index.htm;
    
    # إعدادات SSL
    ssl_certificate /etc/letsencrypt/live/${APP_URL}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/${APP_URL}/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;
    ssl_prefer_server_ciphers off;
    
    # إعدادات الأمان
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
    
    # تحسينات الأداء
    gzip on;
    gzip_vary on;
    gzip_min_length 10240;
    gzip_proxied expired no-cache no-store private must-revalidate auth;
    gzip_types
        text/plain
        text/css
        text/xml
        text/javascript
        application/javascript
        application/xml+rss
        application/json;
    
    # Laravel routes
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    
    # PHP processing
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }
    
    # Static files caching
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~ \.env$ {
        deny all;
    }
}
EOF

    # تفعيل الموقع
    ln -sf /etc/nginx/sites-available/v5 /etc/nginx/sites-enabled/
    
    # إزالة الموقع الافتراضي
    rm -f /etc/nginx/sites-enabled/default
    
    # اختبار التكوين
    nginx -t
    
    # بدء الخدمة
    systemctl restart nginx
    systemctl enable nginx
    
    log_success "تم تثبيت وتكوين Nginx"
}

# ===============================================
# تثبيت وتكوين SSL باستخدام Let's Encrypt
# ===============================================
install_ssl() {
    log_info "تثبيت وتكوين SSL..."
    
    # تثبيت Certbot
    apt install -y certbot python3-certbot-nginx
    
    # الحصول على شهادة SSL
    log_warning "سيتم الآن الحصول على شهادة SSL لـ ${APP_URL}"
    certbot --nginx -d ${APP_URL} --non-interactive --agree-tos --email admin@${APP_URL}
    
    # تجديد الشهادة تلقائياً
    (crontab -l 2>/dev/null; echo "0 12 * * * /usr/bin/certbot renew --quiet") | crontab -
    
    log_success "تم تثبيت وتكوين SSL"
}

# ===============================================
# إعداد مجلد المشروع
# ===============================================
setup_project() {
    log_info "إعداد مجلد المشروع..."
    
    # إنشاء مجلد المشروع
    mkdir -p ${PROJECT_PATH}
    cd ${PROJECT_PATH}
    
    # نقل ملفات المشروع (افتراض أن المشروع موجود في المجلد الحالي)
    # يجب نسخ ملفات V5 إلى هذا المجلد
    
    # إعداد الصلاحيات
    chown -R ${WEB_USER}:${WEB_USER} ${PROJECT_PATH}
    chmod -R 755 ${PROJECT_PATH}
    chmod -R 775 ${PROJECT_PATH}/storage
    chmod -R 775 ${PROJECT_PATH}/bootstrap/cache
    
    log_success "تم إعداد مجلد المشروع: ${PROJECT_PATH}"
}

# ===============================================
# تثبيت تبعيات المشروع
# ===============================================
install_dependencies() {
    log_info "تثبيت تبعيات المشروع..."
    
    cd ${PROJECT_PATH}
    
    # تثبيت تبعيات PHP
    sudo -u ${WEB_USER} composer install --optimize-autoloader --no-dev
    
    # تثبيت تبعيات Node.js
    sudo -u ${WEB_USER} npm ci --production
    sudo -u ${WEB_USER} npm run build
    
    log_success "تم تثبيت تبعيات المشروع"
}

# ===============================================
# إعداد ملف البيئة
# ===============================================
setup_environment() {
    log_info "إعداد ملف البيئة..."
    
    cd ${PROJECT_PATH}
    
    # إنشاء ملف .env
    cp .env.example .env
    
    # توليد APP_KEY
    sudo -u ${WEB_USER} php artisan key:generate --force
    
    # تحديث إعدادات .env
    sed -i "s/APP_URL=.*/APP_URL=${APP_URL}/" .env
    sed -i "s/DB_DATABASE=.*/DB_DATABASE=${DB_NAME}/" .env
    sed -i "s/DB_USERNAME=.*/DB_USERNAME=${DB_USER}/" .env
    sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=${DB_PASS}/" .env
    sed -i "s/APP_ENV=.*/APP_ENV=production/" .env
    sed -i "s/APP_DEBUG=.*/APP_DEBUG=false/" .env
    sed -i "s/LOG_LEVEL=.*/LOG_LEVEL=warning/" .env
    
    # تحديث إعدادات الأداء
    sed -i "s/CACHE_STORE=.*/CACHE_STORE=redis/" .env
    sed -i "s/SESSION_DRIVER=.*/SESSION_DRIVER=redis/" .env
    sed -i "s/QUEUE_CONNECTION=.*/QUEUE_CONNECTION=redis/" .env
    
    # إعداد Redis
    systemctl start redis-server
    systemctl enable redis-server
    
    log_success "تم إعداد ملف البيئة"
}

# ===============================================
# إعداد قاعدة البيانات
# ===============================================
setup_database_migrations() {
    log_info "إعداد قاعدة البيانات..."
    
    cd ${PROJECT_PATH}
    
    # تشغيل migrations
    sudo -u ${WEB_USER} php artisan migrate --force
    
    # تشغيل seeders
    sudo -u ${WEB_USER} php artisan db:seed --force
    
    # إنشاء رابط التخزين
    sudo -u ${WEB_USER} php artisan storage:link
    
    # إنشاء مفتاح التطبيق إذا لم يكن موجوداً
    sudo -u ${WEB_USER} php artisan key:generate
    
    log_success "تم إعداد قاعدة البيانات"
}

# ===============================================
# إعداد مراقبة النظام
# ===============================================
setup_monitoring() {
    log_info "إعداد مراقبة النظام..."
    
    # تثبيت أدوات المراقبة
    apt install -y fail2ban ufw
    
    # تكوين UFW
    ufw --force reset
    ufw default deny incoming
    ufw default allow outgoing
    ufw allow ssh
    ufw allow 'Nginx Full'
    ufw allow mysql
    ufw --force enable
    
    # تكوين Fail2ban
    cat > /etc/fail2ban/jail.local <<EOF
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5

[nginx-http-auth]
enabled = true
filter = nginx-http-auth
logpath = /var/log/nginx/error.log
maxretry = 3

[nginx-limit-req]
enabled = true
filter = nginx-limit-req
logpath = /var/log/nginx/error.log
maxretry = 10
EOF

    systemctl restart fail2ban
    systemctl enable fail2ban
    
    log_success "تم إعداد مراقبة النظام"
}

# ===============================================
# إعداد النسخ الاحتياطية
# ===============================================
setup_backup() {
    log_info "إعداد النسخ الاحتياطية..."
    
    # إنشاء مجلد النسخ الاحتياطية
    mkdir -p /var/backups/v5
    
    # سكريبت النسخ الاحتياطية
    cat > /usr/local/bin/backup-v5.sh <<'EOF'
#!/bin/bash
BACKUP_DIR="/var/backups/v5"
DATE=$(date +%Y%m%d_%H%M%S)
PROJECT_DIR="/var/www/v5"
DB_NAME="v5_production"
DB_USER="v5_user"

# الحصول على كلمة مرور قاعدة البيانات
DB_PASS=$(grep DB_PASSWORD /var/www/v5/.env | cut -d '=' -f2)

# نسخ قاعدة البيانات
mysqldump -u ${DB_USER} -p${DB_PASS} ${DB_NAME} > ${BACKUP_DIR}/db_backup_${DATE}.sql

# نسخ ملفات المشروع
tar -czf ${BACKUP_DIR}/files_backup_${DATE}.tar.gz -C /var/www v5

# حذف النسخ القديمة (أكثر من 7 أيام)
find ${BACKUP_DIR} -name "*.sql" -mtime +7 -delete
find ${BACKUP_DIR} -name "*.tar.gz" -mtime +7 -delete

echo "تم إنشاء النسخة الاحتياطية: ${DATE}"
EOF

    chmod +x /usr/local/bin/backup-v5.sh
    
    # جدولة النسخ الاحتياطية
    (crontab -l 2>/dev/null; echo "0 2 * * * /usr/local/bin/backup-v5.sh") | crontab -
    
    log_success "تم إعداد النسخ الاحتياطية"
}

# ===============================================
# إنشاء سكريبت التحكم
# ===============================================
create_control_script() {
    log_info "إنشاء سكريبت التحكم..."
    
    cat > /usr/local/bin/v5-control <<'EOF'
#!/bin/bash
case "$1" in
    start)
        echo "بدء تشغيل V5..."
        systemctl start php8.2-fpm
        systemctl start nginx
        systemctl start mysql
        systemctl start redis-server
        ;;
    stop)
        echo "إيقاف V5..."
        systemctl stop php8.2-fpm
        systemctl stop nginx
        systemctl stop mysql
        systemctl stop redis-server
        ;;
    restart)
        echo "إعادة تشغيل V5..."
        systemctl restart php8.2-fpm
        systemctl restart nginx
        systemctl restart mysql
        systemctl restart redis-server
        ;;
    status)
        echo "حالة الخدمات:"
        systemctl status php8.2-fpm --no-pager -l
        systemctl status nginx --no-pager -l
        systemctl status mysql --no-pager -l
        systemctl status redis-server --no-pager -l
        ;;
    logs)
        echo "سجلات Nginx:"
        tail -f /var/log/nginx/error.log
        ;;
    *)
        echo "الاستخدام: $0 {start|stop|restart|status|logs}"
        exit 1
        ;;
esac
EOF

    chmod +x /usr/local/bin/v5-control
    
    log_success "تم إنشاء سكريبت التحكم"
}

# ===============================================
# التحقق النهائي من النظام
# ===============================================
verify_installation() {
    log_info "التحقق من صحة التثبيت..."
    
    # التحقق من الخدمات
    services=("nginx" "mysql" "php8.2-fpm" "redis-server")
    for service in "${services[@]}"; do
        if systemctl is-active --quiet $service; then
            log_success "$service يعمل بشكل طبيعي"
        else
            log_error "$service لا يعمل"
        fi
    done
    
    # التحقق من الموقع
    if curl -s -o /dev/null -w "%{http_code}" http://localhost | grep -q "200\|301\|302"; then
        log_success "الموقع يعمل بشكل طبيعي"
    else
        log_warning "تحقق من إعدادات الموقع"
    fi
    
    # عرض معلومات النظام
    echo ""
    log_info "=== معلومات النشر ==="
    echo "مجلد المشروع: ${PROJECT_PATH}"
    echo "رابط الموقع: ${APP_URL}"
    echo "قاعدة البيانات: ${DB_NAME}"
    echo "مستخدم قاعدة البيانات: ${DB_USER}"
    echo "كلمة مرور قاعدة البيانات: ${DB_PASS}"
    echo ""
    log_info "ملف معلومات قاعدة البيانات: /root/database_info.txt"
    log_info "سكريبت التحكم: v5-control {start|stop|restart|status|logs}"
    echo ""
}

# ===============================================
# الوظيفة الرئيسية
# ===============================================
main() {
    echo "=============================================="
    echo "    سكريبت النشر الشامل لمشروع V5"
    echo "    V5 Project Deployment Script for Ubuntu 25"
    echo "=============================================="
    echo ""
    
    check_root
    install_requirements
    install_mysql
    setup_database
    install_php
    install_composer
    install_nodejs
    install_nginx
    install_ssl
    setup_project
    install_dependencies
    setup_environment
    setup_database_migrations
    setup_monitoring
    setup_backup
    create_control_script
    verify_installation
    
    echo ""
    echo "=============================================="
    log_success "تم الانتهاء من نشر مشروع V5 بنجاح!"
    echo "=============================================="
}

# تشغيل السكريبت
main "$@"