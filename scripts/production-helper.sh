#!/bin/bash

# Laravel Production Helper Script
# Usage: ./production-helper.sh [command]

set -euo pipefail

APP_PATH="/var/www/your-app"
APP_NAME="laravel-app"
MYSQL_USER="app_user"
MYSQL_PASSWORD="strong_password"
MYSQL_DATABASE="production_app"
LOG_FILE="/var/log/production-helper-$(date +%Y%m%d_%H%M%S).log"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log() {
    echo -e "${GREEN}[$(date +'%H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"
}

info() {
    echo -e "${BLUE}[INFO]${NC} $1" | tee -a "$LOG_FILE"
}

# Check if running as root for certain commands
check_root() {
    if [ "$EUID" -ne 0 ]; then
        error "This command requires root privileges. Please run with sudo."
        exit 1
    fi
}

# System information
show_system_info() {
    log "System Information:"
    echo "========================"
    
    info "Operating System:"
    lsb_release -d 2>/dev/null | cut -f2 || cat /etc/os-release | grep PRETTY_NAME | cut -d'"' -f2
    echo
    
    info "Kernel Version:"
    uname -r
    echo
    
    info "Uptime:"
    uptime
    echo
    
    info "CPU Information:"
    lscpu | grep "Model name" | cut -d: -f2 | xargs
    echo "CPU Cores: $(nproc)"
    echo
    
    info "Memory Information:"
    free -h | grep Mem:
    echo "Total Swap: $(free -h | grep Swap | awk '{print $2}')"
    echo
    
    info "Disk Usage:"
    df -h | grep -E '^/dev/'
    echo
    
    info "Network Information:"
    ip addr show | grep "inet " | grep -v "127.0.0.1" | awk '{print $NF ": " $2}' || ifconfig | grep "inet " | grep -v "127.0.0.1"
    echo
}

# Application status check
check_application_status() {
    log "Application Status Check:"
    echo "=========================="
    
    # Check if application directory exists
    if [ -d "$APP_PATH" ]; then
        info "✓ Application directory exists: $APP_PATH"
    else
        error "✗ Application directory not found: $APP_PATH"
        return 1
    fi
    
    # Check Laravel files
    local laravel_files=("artisan" "composer.json" ".env" "public/index.php")
    for file in "${laravel_files[@]}"; do
        if [ -f "$APP_PATH/$file" ]; then
            info "✓ $file exists"
        else
            warning "⚠ $file missing"
        fi
    done
    
    # Test application response
    if curl -f -s http://localhost/health > /dev/null 2>&1; then
        info "✓ Application responds to health check"
    else
        error "✗ Application does not respond to health check"
    fi
    
    # Check Laravel cache
    local cache_files=("bootstrap/cache/config.php" "bootstrap/cache/routes.php" "bootstrap/cache/packages.php")
    for cache_file in "${cache_files[@]}"; do
        if [ -f "$APP_PATH/$cache_file" ]; then
            info "✓ Cache file exists: $cache_file"
        else
            warning "⚠ Cache file missing: $cache_file"
        fi
    done
    
    # Laravel version
    cd "$APP_PATH"
    if command -v php >/dev/null 2>&1; then
        local laravel_version=$(php artisan --version 2>/dev/null | grep Laravel || echo "Unable to determine")
        info "Laravel Version: $laravel_version"
    fi
}

# Database status check
check_database_status() {
    log "Database Status Check:"
    echo "========================"
    
    # Check MySQL service
    if systemctl is-active --quiet mysql 2>/dev/null || systemctl is-active --quiet mysqld 2>/dev/null; then
        info "✓ MySQL service is running"
    else
        error "✗ MySQL service is not running"
    fi
    
    # Test MySQL connection
    if mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "SELECT 1;" > /dev/null 2>&1; then
        info "✓ MySQL connection successful"
        
        # Get database size
        local db_size=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'Size (MB)'
            FROM information_schema.tables 
            WHERE table_schema = '$MYSQL_DATABASE';" 2>/dev/null | tail -1)
        info "Database size: ${db_size} MB"
        
        # Get table count
        local table_count=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "
            SELECT COUNT(*) FROM information_schema.tables 
            WHERE table_schema = '$MYSQL_DATABASE';" 2>/dev/null | tail -1)
        info "Number of tables: $table_count"
        
    else
        error "✗ MySQL connection failed"
    fi
    
    # Check MySQL performance
    if mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "SHOW ENGINE INNODB STATUS \G;" > /dev/null 2>&1; then
        info "✓ MySQL InnoDB is accessible"
    else
        warning "⚠ MySQL InnoDB status check failed"
    fi
}

# Cache and Session check
check_cache_status() {
    log "Cache and Session Status Check:"
    echo "================================"
    
    # Check Redis
    if command -v redis-cli >/dev/null 2>&1; then
        if redis-cli ping >/dev/null 2>&1; then
            info "✓ Redis is running and responding"
            
            # Redis memory usage
            local redis_memory=$(redis-cli info memory | grep used_memory_human | cut -d: -f2 | tr -d '\r')
            info "Redis memory usage: $redis_memory"
            
            # Redis connected clients
            local redis_clients=$(redis-cli info clients | grep connected_clients | cut -d: -f2 | tr -d '\r')
            info "Redis connected clients: $redis_clients"
            
        else
            error "✗ Redis is not responding"
        fi
    else
        warning "⚠ Redis CLI not found"
    fi
    
    # Check storage directories
    local storage_dirs=("storage/app" "storage/framework" "storage/logs")
    for dir in "${storage_dirs[@]}"; do
        if [ -d "$APP_PATH/$dir" ]; then
            info "✓ $dir directory exists"
            # Check permissions
            if [ -w "$APP_PATH/$dir" ]; then
                info "✓ $dir is writable"
            else
                error "✗ $dir is not writable"
            fi
        else
            error "✗ $dir directory missing"
        fi
    done
}

# Service status check
check_service_status() {
    log "Service Status Check:"
    echo "====================="
    
    local services=("nginx" "php8.2-fpm" "mysql" "redis")
    
    for service in "${services[@]}"; do
        if systemctl is-active --quiet "$service" 2>/dev/null; then
            info "✓ $service is running"
            
            # Get service PID if available
            local pid=$(systemctl show -p MainPID --value "$service" 2>/dev/null)
            if [ "$pid" != "0" ] && [ -n "$pid" ]; then
                info "  PID: $pid"
            fi
        else
            error "✗ $service is not running"
        fi
    done
}

# Resource usage check
check_resource_usage() {
    log "Resource Usage Check:"
    echo "====================="
    
    # CPU Load
    local load_avg=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | sed 's/,//')
    info "CPU Load Average (1 min): $load_avg"
    
    # Memory Usage
    local mem_info=$(free -h | grep Mem)
    info "Memory: $mem_info"
    
    # Disk Usage
    info "Disk Usage:"
    df -h | grep -E '^/dev/' | while read line; do
        echo "  $line"
    done
    
    # Top processes by CPU
    info "Top 5 CPU Usage Processes:"
    ps aux --sort=-%cpu | head -6 | tail -5 | while read line; do
        echo "  $line"
    done
    
    # Top processes by Memory
    info "Top 5 Memory Usage Processes:"
    ps aux --sort=-%mem | head -6 | tail -5 | while read line; do
        echo "  $line"
    done
}

# Security check
security_check() {
    log "Security Check:"
    echo "==============="
    
    # Check file permissions
    local sensitive_files=(".env" "composer.json" "package.json")
    for file in "${sensitive_files[@]}"; do
        if [ -f "$APP_PATH/$file" ]; then
            local perms=$(stat -c "%a" "$APP_PATH/$file" 2>/dev/null || echo "N/A")
            info "$file permissions: $perms"
            
            # Check if world-writable
            if [ "$perms" != "N/A" ]; then
                local last_digit=${perms: -1}
                if [ $((last_digit & 2)) -ne 0 ]; then
                    warning "⚠ $file is world-writable"
                fi
            fi
        fi
    done
    
    # Check for world-writable files in application
    local world_writable=$(find "$APP_PATH" -type f -perm /o+w 2>/dev/null | wc -l)
    if [ "$world_writable" -gt 0 ]; then
        warning "⚠ Found $world_writable world-writable files in application"
    else
        info "✓ No world-writable files found in application"
    fi
    
    # Check firewall status
    if command -v ufw >/dev/null 2>&1; then
        local firewall_status=$(ufw status 2>/dev/null | grep "Status:" | awk '{print $2}')
        info "UFW Firewall Status: $firewall_status"
    fi
    
    # Check failed login attempts (if user_activities table exists)
    if mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "SHOW TABLES LIKE 'user_activities';" >/dev/null 2>&1; then
        local failed_logins=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "
            SELECT COUNT(*) FROM user_activities 
            WHERE activity_type = 'login_failed' 
            AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR);" 2>/dev/null | tail -1)
        info "Failed login attempts (24h): $failed_logins"
    fi
}

# Performance check
performance_check() {
    log "Performance Check:"
    echo "=================="
    
    # Test response time
    local start_time=$(date +%s%3N)
    curl -s -o /dev/null -w "%{time_total}" http://localhost 2>/dev/null | while read response_time; do
        if (( $(echo "$response_time < 2.0" | bc -l 2>/dev/null || echo 0) )); then
            info "✓ Application response time: ${response_time}s"
        else
            warning "⚠ Slow response time: ${response_time}s"
        fi
    done
    
    # Check PHP OPcache status
    if php -r "echo opcache_get_status() ? 'enabled' : 'disabled';" 2>/dev/null | grep -q "enabled"; then
        info "✓ OPcache is enabled"
    else
        warning "⚠ OPcache is disabled"
    fi
    
    # Check MySQL slow queries (if enabled)
    if mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "SHOW VARIABLES LIKE 'slow_query_log';" 2>/dev/null | grep "slow_query_log" | grep -q "ON"; then
        local slow_queries=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "SHOW GLOBAL STATUS LIKE 'Slow_queries';" 2>/dev/null | tail -1 | cut -f2)
        info "MySQL slow queries: $slow_queries"
    else
        warning "⚠ MySQL slow query log is disabled"
    fi
}

# Log analysis
analyze_logs() {
    log "Log Analysis:"
    echo "============="
    
    # Laravel application logs
    if [ -d "$APP_PATH/storage/logs" ]; then
        local laravel_log_count=$(find "$APP_PATH/storage/logs" -name "*.log" | wc -l)
        info "Laravel log files: $laravel_log_count"
        
        # Check for errors today
        local today_errors=$(find "$APP_PATH/storage/logs" -name "*.log" -exec grep -h "$(date +'Y-m-d')" {} \; 2>/dev/null | grep -i error | wc -l)
        local today_warnings=$(find "$APP_PATH/storage/logs" -name "*.log" -exec grep -h "$(date +'Y-m-d')" {} \; 2>/dev/null | grep -i warning | wc -l)
        
        info "Today's Laravel errors: $today_errors"
        info "Today's Laravel warnings: $today_warnings"
        
        if [ "$today_errors" -gt 10 ]; then
            warning "⚠ High number of errors today"
        else
            info "✓ Reasonable error count"
        fi
    else
        warning "⚠ Laravel log directory not found"
    fi
    
    # Nginx logs
    if [ -f "/var/log/nginx/error.log" ]; then
        local nginx_errors=$(grep "$(date +'%Y/%m/%d')" /var/log/nginx/error.log 2>/dev/null | wc -l)
        info "Today's Nginx errors: $nginx_errors"
    fi
    
    # System logs
    if [ -f "/var/log/syslog" ]; then
        local system_errors=$(grep "$(date +'Y-m-d')" /var/log/syslog 2>/dev/null | grep -i error | wc -l)
        info "Today's system errors: $system_errors"
    fi
}

# Quick fix functions
quick_fixes() {
    echo "Available quick fixes:"
    echo "1. Fix file permissions"
    echo "2. Clear Laravel caches"
    echo "3. Restart all services"
    echo "4. Optimize database"
    echo "5. Clean log files"
    echo "6. Backup application"
    
    read -p "Choose a fix (1-6): " choice
    
    case $choice in
        1)
            log "Fixing file permissions..."
            sudo chown -R www-data:www-data "$APP_PATH"
            sudo chmod -R 755 "$APP_PATH"
            sudo chmod -R 775 "$APP_PATH/storage"
            sudo chmod -R 775 "$APP_PATH/bootstrap/cache"
            info "✓ File permissions fixed"
            ;;
        2)
            log "Clearing Laravel caches..."
            cd "$APP_PATH"
            sudo -u www-data php artisan cache:clear
            sudo -u www-data php artisan config:clear
            sudo -u www-data php artisan route:clear
            sudo -u www-data php artisan view:clear
            info "✓ Laravel caches cleared"
            ;;
        3)
            log "Restarting all services..."
            sudo systemctl restart php8.2-fpm
            sudo systemctl restart nginx
            sudo systemctl restart redis
            info "✓ All services restarted"
            ;;
        4)
            log "Optimizing database..."
            mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "OPTIMIZE TABLE \`$MYSQL_DATABASE\`;" 2>/dev/null || warning "Database optimization failed"
            info "✓ Database optimization completed"
            ;;
        5)
            log "Cleaning log files..."
            find /var/log -name "*.log" -mtime +7 -exec gzip {} \; 2>/dev/null || true
            find /var/log -name "*.log.*" -mtime +30 -delete 2>/dev/null || true
            info "✓ Log files cleaned"
            ;;
        6)
            log "Creating application backup..."
            local backup_file="/var/backups/app-backup-$(date +%Y%m%d_%H%M%S).tar.gz"
            mkdir -p /var/backups
            tar -czf "$backup_file" -C "$APP_PATH" . || error "Backup failed"
            info "✓ Application backed up: $backup_file"
            ;;
        *)
            error "Invalid choice"
            ;;
    esac
}

# Help function
show_help() {
    echo "Laravel Production Helper Script"
    echo "================================="
    echo
    echo "Usage: $0 [command]"
    echo
    echo "Commands:"
    echo "  status          - Check application status"
    echo "  database        - Check database status"
    echo "  cache           - Check cache and sessions"
    echo "  services        - Check service status"
    echo "  resources       - Check resource usage"
    echo "  security        - Run security check"
    echo "  performance     - Run performance check"
    echo "  logs            - Analyze logs"
    echo "  system          - Show system information"
    echo "  fix             - Quick fix menu"
    echo "  all             - Run all checks"
    echo "  help            - Show this help"
    echo
    echo "Examples:"
    echo "  $0 status"
    echo "  $0 all"
    echo "  $0 fix"
    echo
    echo "Log file: $LOG_FILE"
}

# Main execution
main() {
    local command=${1:-"help"}
    
    case $command in
        status)
            check_application_status
            ;;
        database)
            check_database_status
            ;;
        cache)
            check_cache_status
            ;;
        services)
            check_service_status
            ;;
        resources)
            check_resource_usage
            ;;
        security)
            security_check
            ;;
        performance)
            performance_check
            ;;
        logs)
            analyze_logs
            ;;
        system)
            show_system_info
            ;;
        fix)
            check_root
            quick_fixes
            ;;
        all)
            show_system_info
            check_application_status
            check_database_status
            check_cache_status
            check_service_status
            check_resource_usage
            security_check
            performance_check
            analyze_logs
            ;;
        help|--help|-h)
            show_help
            ;;
        *)
            error "Unknown command: $command"
            show_help
            exit 1
            ;;
    esac
}

# Run main function
main "$@"