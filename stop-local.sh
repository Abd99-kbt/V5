#!/bin/bash

# ===============================================
# سكريپت إيقاف النظام - نظام V5
# Stop System Script - V5 System
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

print_header "إيقاف نظام V5 - Stopping V5 System"

# دالة لإيقاف العمليات على منفذ معين
stop_port() {
    local port=$1
    local service_name=$2
    
    print_step "إيقاف $service_name على المنفذ $port"
    
    # البحث عن العمليات الجارية على المنفذ
    if lsof -Pi :$port -sTCP:LISTEN -t >/dev/null 2>&1; then
        # الحصول على معرفات العمليات
        pids=$(lsof -ti:$port)
        
        if [ ! -z "$pids" ]; then
            for pid in $pids; do
                print_info "إيقاف العملية PID: $pid"
                kill -TERM $pid 2>/dev/null
                sleep 1
                
                # التحقق إذا كانت العملية لا تزال تعمل
                if kill -0 $pid 2>/dev/null; then
                    print_warning "إجبار إنهاء العملية PID: $pid"
                    kill -KILL $pid 2>/dev/null
                fi
            done
            
            # انتظار قصير للتأكد من الإيقاف
            sleep 2
            
            # التحقق مرة أخيرة
            if lsof -Pi :$port -sTCP:LISTEN -t >/dev/null 2>&1; then
                print_warning "لا يزال المنفذ $port مستخدماً"
            else
                print_success "$service_name تم إيقافه بنجاح"
            fi
        else
            print_info "لا توجد عمليات جارية على المنفذ $port"
        fi
    else
        print_info "لا توجد عمليات جارية على المنفذ $port"
    fi
}

# دالة تنظيف العمليات حسب الاسم
stop_by_name() {
    local process_name=$1
    local display_name=$2
    
    print_step "البحث عن عمليات $display_name"
    
    # البحث عن العمليات الجارية
    pids=$(pgrep -f "$process_name")
    
    if [ ! -z "$pids" ]; then
        for pid in $pids; do
            print_info "إيقاف العملية $display_name (PID: $pid)"
            kill -TERM $pid 2>/dev/null
        done
        
        # انتظار الإيقاف الطبيعي
        sleep 3
        
        # التحقق من العمليات المتبقية
        remaining_pids=$(pgrep -f "$process_name")
        if [ ! -z "$remaining_pids" ]; then
            print_warning "إجبار إنهاء عمليات $display_name المتبقية"
            for pid in $remaining_pids; do
                kill -KILL $pid 2>/dev/null
            done
        fi
        
        print_success "$display_name تم إيقافه بنجاح"
    else
        print_info "لا توجد عمليات $display_name جارية"
    fi
}

# دالة تنظيف cache
clear_cache() {
    print_step "تنظيف cache النظام"
    
    # تنظيف Laravel cache
    if [ -f "artisan" ]; then
        php artisan cache:clear 2>/dev/null
        php artisan config:clear 2>/dev/null
        php artisan route:clear 2>/dev/null
        php artisan view:clear 2>/dev/null
        print_success "Laravel cache تم تنظيفه"
    fi
}

# دالة عرض العمليات المتبقية
show_remaining_processes() {
    print_step "التحقق من العمليات المتبقية"
    
    # فحص المنافذ المستخدمة
    local ports=(8000 5173 3000)
    local found_processes=false
    
    for port in "${ports[@]}"; do
        if lsof -Pi :$port -sTCP:LISTEN -t >/dev/null 2>&1; then
            print_warning "المنفذ $port لا يزال مستخدماً:"
            lsof -Pi :$port -sTCP:LISTEN
            found_processes=true
        fi
    done
    
    # فحص عمليات PHP
    php_pids=$(pgrep -f "php.*artisan.*serve")
    if [ ! -z "$php_pids" ]; then
        print_warning "عمليات PHP خادم التطوير المتبقية:"
        ps aux | grep "php.*artisan.*serve" | grep -v grep
        found_processes=true
    fi
    
    # فحص عمليات Node/Vite
    node_pids=$(pgrep -f "vite|node")
    if [ ! -z "$node_pids" ]; then
        print_warning "عمليات Node/Vite المتبقية:"
        ps aux | grep -E "(vite|node)" | grep -v grep
        found_processes=true
    fi
    
    if [ "$found_processes" = false ]; then
        print_success "جميع العمليات تم إيقافها بنجاح"
    fi
}

# دالة عرض المساعدة
show_help() {
    echo "استخدام / Usage: $0 [option]"
    echo ""
    echo "الخيارات / Options:"
    echo "  --help, -h          عرض هذه المساعدة / Show this help"
    echo "  --force             إجبار إيقاف جميع العمليات / Force stop all processes"
    echo "  --no-cache          عدم تنظيف cache / Skip cache clearing"
    echo "  --quiet, -q         وضع هادئ (قليل من الرسائل) / Quiet mode"
    echo ""
    echo "أمثلة / Examples:"
    echo "  $0                  إيقاف النظام بشكل طبيعي / Normal system stop"
    echo "  $0 --force          إجبار إيقاف جميع العمليات / Force stop all"
    echo "  $0 --no-cache       إيقاف بدون تنظيف cache / Stop without cache clear"
    echo ""
}

# المتغيرات
FORCE_STOP=false
CLEAR_CACHE=true
QUIET_MODE=false

# معالجة المعاملات
while [[ $# -gt 0 ]]; do
    case $1 in
        --help|-h)
            show_help
            exit 0
            ;;
        --force)
            FORCE_STOP=true
            shift
            ;;
        --no-cache)
            CLEAR_CACHE=false
            shift
            ;;
        --quiet|-q)
            QUIET_MODE=true
            shift
            ;;
        *)
            print_error "معامل غير معروف: $1"
            show_help
            exit 1
            ;;
    esac
done

# دالة طباعة مختصرة
if [ "$QUIET_MODE" = true ]; then
    print_info() { echo -e "${CYAN}ℹ️  $1${NC}"; }
    print_step() { echo "🔄 $1"; }
    print_success() { echo -e "${GREEN}✅ $1${NC}"; }
    print_warning() { echo -e "${YELLOW}⚠️  $1${NC}"; }
fi

print_info "بدء عملية إيقاف النظام..."

# إيقاف العمليات على المنافذ المحددة
stop_port 8000 "خادم Laravel"
stop_port 5173 "خادم Vite"
stop_port 3000 "خادم Node"

# إيقاف العمليات حسب الاسم
stop_by_name "artisan serve" "Laravel Server"
stop_by_name "npm run dev" "Vite Dev Server"
stop_by_name "vite" "Vite Process"
stop_by_name "php.*queue:listen" "Queue Workers"

# تنظيف cache
if [ "$CLEAR_CACHE" = true ]; then
    clear_cache
fi

# إظهار العمليات المتبقية
if [ "$FORCE_STOP" = false ]; then
    show_remaining_processes
else
    print_step "إجبار إيقاف جميع العمليات المتبقية"
    
    # إيقاف جميع عمليات PHP artisan
    pkill -f "php.*artisan" 2>/dev/null
    
    # إيقاف جميع عمليات node/vite
    pkill -f "node" 2>/dev/null
    pkill -f "vite" 2>/dev/null
    
    # إيقاف جميع عمليات npm
    pkill -f "npm" 2>/dev/null
    
    print_success "تم إجبار إيقاف جميع العمليات"
fi

# رسالة ختامية
print_header "تم إيقاف النظام بنجاح"
if [ "$QUIET_MODE" = false ]; then
    echo -e "${GREEN}🎉 نظام V5 تم إيقافه بنجاح!${NC}"
    echo -e "${BLUE}💡 لتشغيل النظام مرة أخرى استخدم: ./start-local.sh${NC}"
    echo -e "${CYAN}🔄 أو: bash start-local.sh${NC}"
    echo ""
fi

exit 0