#!/bin/bash

# ===============================================
# سكريپت التشغيل السريع - نظام V5
# Quick Start Script - V5 System
# ===============================================

# الألوان للنصوص
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
WHITE='\033[1;37m'
NC='\033[0m' # No Color

# دالة طباعة النصوص الملونة
print_header() {
    echo -e "${BLUE}================================================${NC}"
    echo -e "${WHITE}$1${NC}"
    echo -e "${BLUE}================================================${NC}"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_info() {
    echo -e "${CYAN}ℹ️  $1${NC}"
}

print_step() {
    echo -e "${PURPLE}🔄 $1${NC}"
}

# متغير للتحقق من وجود الأخطاء
ERRORS=()

# دالة للتحقق من الأوامر المطلوبة
check_command() {
    if ! command -v $1 &> /dev/null; then
        ERRORS+=("Command '$1' not found. Please install $2")
        return 1
    fi
    return 0
}

# دالة التحقق من المتطلبات الأساسية
check_requirements() {
    print_header "فحص المتطلبات الأساسية / Checking Requirements"
    
    # فحص PHP
    if check_command "php" "PHP 8.2+"; then
        PHP_VERSION=$(php -r "echo PHP_VERSION;")
        print_success "PHP is installed: $PHP_VERSION"
        
        # التحقق من إصدار PHP
        if ! php -r "exit(version_compare(PHP_VERSION, '8.2.0') >= 0 ? 0 : 1);"; then
            ERRORS+=("PHP 8.2+ is required. Current version: $PHP_VERSION")
        fi
    fi
    
    # فحص Composer
    if check_command "composer" "Composer"; then
        COMPOSER_VERSION=$(composer --version | head -n1)
        print_success "Composer is installed: $COMPOSER_VERSION"
    fi
    
    # فحص Node.js
    if check_command "node" "Node.js 18+"; then
        NODE_VERSION=$(node -v)
        print_success "Node.js is installed: $NODE_VERSION"
        
        # التحقق من إصدار Node.js
        if ! node -e "process.exit(parseInt(process.version.slice(1)) >= 18 ? 0 : 1)"; then
            ERRORS+=("Node.js 18+ is required. Current version: $NODE_VERSION")
        fi
    fi
    
    # فحص npm
    if check_command "npm" "npm"; then
        NPM_VERSION=$(npm -v)
        print_success "npm is installed: $NPM_VERSION"
    fi
    
    # فحص MySQL
    if check_command "mysql" "MySQL"; then
        print_success "MySQL client is available"
    fi
    
    # فحص Redis
    if check_command "redis-cli" "Redis"; then
        print_success "Redis client is available"
    else
        print_warning "Redis client not found. Redis is optional for development."
    fi
    
    if [ ${#ERRORS[@]} -gt 0 ]; then
        print_error "Some requirements are missing:"
        for error in "${ERRORS[@]}"; do
            echo -e "${RED}  - $error${NC}"
        done
        echo ""
        print_info "Please install missing requirements and try again."
        exit 1
    fi
    
    print_success "All requirements are satisfied!"
    echo ""
}

# دالة إعداد ملف البيئة
setup_environment() {
    print_header "إعداد ملف البيئة / Setting Up Environment"
    
    if [ ! -f .env ]; then
        if [ -f .env.example ]; then
            print_step "نسخ ملف البيئة من النموذج / Copying environment template"
            cp .env.example .env
            print_success "ملف البيئة تم إنشاؤه / Environment file created"
        else
            print_error "ملف .env.example غير موجود / .env.example not found"
            exit 1
        fi
    else
        print_info "ملف البيئة موجود بالفعل / Environment file already exists"
    fi
    
    # تحديث إعدادات التطوير
    print_step "تحديث إعدادات التطوير / Updating development settings"
    sed -i 's/APP_ENV=local/APP_ENV=local/' .env
    sed -i 's/APP_DEBUG=false/APP_DEBUG=true/' .env
    sed -i 's/APP_URL=http:\/\/localhost:8080/APP_URL=http:\/\/localhost:8000/' .env
    sed -i 's/DB_DATABASE=laravel/DB_DATABASE=v5_development/' .env
    sed -i 's/CACHE_STORE=database/CACHE_STORE=file/' .env
    sed -i 's/SESSION_DRIVER=database/SESSION_DRIVER=file/' .env
    sed -i 's/QUEUE_CONNECTION=database/QUEUE_CONNECTION=sync/' .env
    sed -i 's/LOG_LEVEL=debug/LOG_LEVEL=debug/' .env
    
    # تعطيل Redis للبيئة المحلية
    sed -i 's/REDIS_HOST=127.0.0.1/REDIS_HOST=127.0.0.1/' .env
    sed -i 's/REDIS_PASSWORD=null/REDIS_PASSWORD=null/' .env
    
    print_success "إعدادات التطوير تم تحديثها / Development settings updated"
    echo ""
}

# دالة تثبيت التبعيات
install_dependencies() {
    print_header "تثبيت التبعيات / Installing Dependencies"
    
    # تثبيت Composer dependencies
    print_step "تثبيت Composer dependencies"
    if composer install --no-dev --optimize-autoloader; then
        print_success "Composer dependencies installed successfully"
    else
        print_error "فشل في تثبيت Composer dependencies"
        exit 1
    fi
    
    # تثبيت npm dependencies
    print_step "تثبيت npm dependencies"
    if npm install; then
        print_success "npm dependencies installed successfully"
    else
        print_error "فشل في تثبيت npm dependencies"
        exit 1
    fi
    
    echo ""
}

# دالة إعداد قاعدة البيانات
setup_database() {
    print_header "إعداد قاعدة البيانات / Setting Up Database"
    
    # توليد مفتاح التطبيق
    print_step "توليد مفتاح التطبيق / Generating application key"
    php artisan key:generate --force
    print_success "مفتاح التطبيق تم توليده / Application key generated"
    
    # تشغيل migrations
    print_step "تشغيل migrations / Running migrations"
    if php artisan migrate --force; then
        print_success "Database migrations completed"
    else
        print_error "فشل في تشغيل migrations"
        print_warning "تأكد من أن قاعدة البيانات تعمل وأن البيانات صحيحة في ملف .env"
        exit 1
    fi
    
    # إنشاء رابط التخزين
    print_step "إنشاء رابط التخزين / Creating storage link"
    php artisan storage:link
    print_success "رابط التخزين تم إنشاؤه / Storage link created"
    
    # مسح الكاش
    print_step "مسح الكاش / Clearing cache"
    php artisan cache:clear
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    print_success "الكاش تم مسحه / Cache cleared"
    
    echo ""
}

# دالة إنشاء البيانات التجريبية
create_test_data() {
    print_header "إنشاء البيانات التجريبية / Creating Test Data"
    
    # التحقق من وجود سكريپت إنشاء المستخدمين
    if [ -f "create_test_users.php" ]; then
        print_step "تشغيل سكريپت إنشاء المستخدمين / Running test user creation script"
        php create_test_users.php
        print_success "المستخدمين التجريبيين تم إنشاؤهم / Test users created"
    else
        print_warning "سكريپت إنشاء المستخدمين غير موجود / User creation script not found"
    fi
    
    # إنشاء بيانات إضافية إذا لزم الأمر
    print_step "تشغيل seeders إضافية / Running additional seeders"
    php artisan db:seed --force
    print_success "البيانات التجريبية تم إنشاؤها / Test data created"
    
    echo ""
}

# دالة بناء Frontend
build_frontend() {
    print_header "بناء Frontend / Building Frontend"
    
    print_step "بناء ملفات الإنتاج / Building production files"
    if npm run build; then
        print_success "Frontend build completed successfully"
    else
        print_warning "فشل في بناء Frontend. سيتم استخدام وضع التطوير"
    fi
    
    echo ""
}

# دالة بدء الخوادم
start_servers() {
    print_header "بدء الخوادم / Starting Servers"
    
    # التحقق من وجود منافذ مطلوبة
    if lsof -Pi :8000 -sTCP:LISTEN -t >/dev/null; then
        print_warning "المنفذ 8000 مستخدم بالفعل. سيتم إيقاف العملية السابقة"
        fuser -k 8000/tcp
        sleep 2
    fi
    
    if lsof -Pi :5173 -sTCP:LISTEN -t >/dev/null; then
        print_warning "المنفذ 5173 مستخدم بالفعل. سيتم إيقاف العملية السابقة"
        fuser -k 5173/tcp
        sleep 2
    fi
    
    print_success "الخوادم جاهزة للبدء / Servers ready to start"
    echo ""
    
    # بدء الخوادم
    print_info "بدء تشغيل الخوادم... / Starting servers..."
    echo -e "${CYAN}================================================${NC}"
    echo -e "${WHITE}الخوادم تعمل الآن / Servers are now running:${NC}"
    echo -e "${GREEN}🌐 Laravel Server: http://localhost:8000${NC}"
    echo -e "${GREEN}⚡ Vite Dev Server: http://localhost:5173${NC}"
    echo -e "${BLUE}================================================${NC}"
    echo ""
    print_info "للإيقاف اضغط Ctrl+C / Press Ctrl+C to stop"
    echo ""
    
    # استخدام concurrently لتشغيل عدة خوادم
    if command -v concurrently &> /dev/null; then
        exec npx concurrently -c "#93c5fd,#c4b5fd,#fb7185,#fdba74" \
            "php artisan serve --host=0.0.0.0 --port=8000" \
            "npm run dev -- --host=0.0.0.0 --port=5173" \
            "php artisan queue:listen --tries=1" \
            --names="Laravel,Vite,Queue" --kill-others
    else
        # البديل إذا لم يكن concurrently متاح
        exec php artisan serve --host=0.0.0.0 --port=8000
    fi
}

# دالة مساعدة
show_help() {
    echo "استخدام / Usage: $0 [option]"
    echo ""
    echo "الخيارات / Options:"
    echo "  --help, -h          عرض هذه المساعدة / Show this help"
    echo "  --check-only        فحص المتطلبات فقط / Check requirements only"
    echo "  --skip-deps         تخطي تثبيت التبعيات / Skip dependency installation"
    echo "  --skip-db          تخطي إعداد قاعدة البيانات / Skip database setup"
    echo "  --no-test-data     عدم إنشاء بيانات تجريبية / Skip test data creation"
    echo ""
    echo "أمثلة / Examples:"
    echo "  $0                  تشغيل كامل / Full setup"
    echo "  $0 --check-only     فحص المتطلبات فقط / Check requirements only"
    echo "  $0 --skip-deps      تخطي التبعيات / Skip dependencies"
    echo ""
}

# المتغيرات
SKIP_DEPS=false
SKIP_DB=false
SKIP_TEST_DATA=false
CHECK_ONLY=false

# معالجة المعاملات
while [[ $# -gt 0 ]]; do
    case $1 in
        --help|-h)
            show_help
            exit 0
            ;;
        --check-only)
            CHECK_ONLY=true
            shift
            ;;
        --skip-deps)
            SKIP_DEPS=true
            shift
            ;;
        --skip-db)
            SKIP_DB=true
            shift
            ;;
        --no-test-data)
            SKIP_TEST_DATA=true
            shift
            ;;
        *)
            print_error "معامل غير معروف: $1"
            show_help
            exit 1
            ;;
    esac
done

# بدء عملية الإعداد
print_header "بدء التشغيل السريع لنظام V5 / Starting V5 Quick Setup"
echo ""
print_info "سيتم إعداد النظام في خطوات متعددة / System will be set up in multiple steps"
echo ""

# فحص المتطلبات
check_requirements

if [ "$CHECK_ONLY" = true ]; then
    print_success "فحص المتطلبات اكتمل / Requirements check completed"
    exit 0
fi

# إعداد ملف البيئة
setup_environment

# تثبيت التبعيات (إذا لم يتم تخطيها)
if [ "$SKIP_DEPS" = false ]; then
    install_dependencies
fi

# إعداد قاعدة البيانات (إذا لم يتم تخطيها)
if [ "$SKIP_DB" = false ]; then
    setup_database
fi

# إنشاء بيانات تجريبية (إذا لم يتم تخطيها)
if [ "$SKIP_TEST_DATA" = false ]; then
    create_test_data
fi

# بناء Frontend
build_frontend

# بدء الخوادم
start_servers