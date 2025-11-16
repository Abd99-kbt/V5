#!/bin/bash

# Security Pre-deployment Check Script
# Author: Security Team
# Purpose: Perform comprehensive security checks before deployment
# Usage: ./scripts/security/pre-deployment-check.sh [environment]

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
ENVIRONMENT=${1:-"staging"}
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
LOG_FILE="logs/security-pre-deploy-${ENVIRONMENT}-${TIMESTAMP}.log"
CHECKS_FAILED=0
CHECKS_PASSED=0

# Directories
PROJECT_ROOT=$(pwd)
SCRIPTS_DIR="$PROJECT_ROOT/scripts"
LOGS_DIR="$PROJECT_ROOT/logs"

# Create logs directory if it doesn't exist
mkdir -p "$LOGS_DIR"

# Logging function
log() {
    echo -e "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Success function
success() {
    echo -e "${GREEN}✅ $1${NC}"
    log "SUCCESS: $1"
    ((CHECKS_PASSED++))
}

# Warning function
warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
    log "WARNING: $1"
}

# Error function
error() {
    echo -e "${RED}❌ $1${NC}"
    log "ERROR: $1"
    ((CHECKS_FAILED++))
}

# Info function
info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
    log "INFO: $1"
}

# Print header
print_header() {
    echo -e "${BLUE}"
    echo "=================================="
    echo "🔒 SECURITY PRE-DEPLOYMENT CHECK"
    echo "=================================="
    echo -e "${NC}"
    log "Starting security pre-deployment check for environment: $ENVIRONMENT"
}

# Check if running in correct environment
check_environment() {
    info "Checking environment configuration..."
    
    # Check if .env file exists
    if [ ! -f ".env" ]; then
        error ".env file not found"
        return 1
    fi
    
    # Check environment-specific variables
    case $ENVIRONMENT in
        "production")
            if grep -q "^APP_DEBUG=true" .env; then
                error "APP_DEBUG is enabled in production environment"
            else
                success "APP_DEBUG is disabled for production"
            fi
            
            if ! grep -q "^APP_FORCE_HTTPS=true" .env; then
                warning "APP_FORCE_HTTPS is not enabled"
            else
                success "APP_FORCE_HTTPS is enabled"
            fi
            ;;
        "staging"|"development")
            # Staging/development specific checks
            info "Environment: $ENVIRONMENT - some production checks skipped"
            ;;
    esac
}

# Check file permissions
check_file_permissions() {
    info "Checking file permissions..."
    
    # Critical files that should have restricted permissions
    critical_files=(
        ".env"
        ".env.example"
        "composer.json"
        "composer.lock"
    )
    
    for file in "${critical_files[@]}"; do
        if [ -f "$file" ]; then
            perms=$(stat -c "%a" "$file")
            if [ "$file" = ".env" ]; then
                if [ "$perms" != "600" ]; then
                    error ".env file permissions are $perms, should be 600"
                else
                    success ".env file permissions are correct (600)"
                fi
            else
                if [ "$perms" != "644" ]; then
                    warning "$file permissions are $perms, should be 644"
                else
                    success "$file permissions are correct (644)"
                fi
            fi
        fi
    done
    
    # Check directory permissions
    critical_dirs=(
        "storage"
        "bootstrap/cache"
        "storage/app"
        "storage/framework"
        "storage/logs"
    )
    
    for dir in "${critical_dirs[@]}"; do
        if [ -d "$dir" ]; then
            perms=$(stat -c "%a" "$dir")
            if [ "$perms" != "755" ]; then
                error "$dir directory permissions are $perms, should be 755"
            else
                success "$dir directory permissions are correct (755)"
            fi
        fi
    done
}

# Check PHP version
check_php_version() {
    info "Checking PHP version..."
    
    php_version=$(php -r "echo PHP_VERSION;")
    required_version="8.0.0"
    
    if php -r "exit(version_compare('$php_version', '$required_version', '>=') ? 0 : 1);"; then
        success "PHP version $php_version meets minimum requirement ($required_version)"
    else
        error "PHP version $php_version is below minimum requirement ($required_version)"
    fi
    
    # Check for unsupported PHP versions
    unsupported_versions=("5.6" "7.0" "7.1" "7.2" "7.3")
    for version in "${unsupported_versions[@]}"; do
        if [[ $php_version == $version* ]]; then
            error "PHP version $php_version is no longer supported"
        fi
    done
}

# Check Composer dependencies
check_composer_dependencies() {
    info "Checking Composer dependencies..."
    
    if [ ! -f "composer.json" ]; then
        error "composer.json not found"
        return 1
    fi
    
    # Check if composer.lock exists
    if [ ! -f "composer.lock" ]; then
        error "composer.lock not found - run 'composer install' first"
        return 1
    fi
    
    # Run composer audit
    info "Running Composer security audit..."
    if command -v composer >/dev/null 2>&1; then
        if composer audit --format=json >/dev/null 2>&1; then
            success "No known security vulnerabilities in Composer dependencies"
        else
            error "Security vulnerabilities found in Composer dependencies"
            warning "Run 'composer audit' to see details"
        fi
    else
        warning "Composer not available - skipping dependency audit"
    fi
    
    # Check for development dependencies in production
    if [ "$ENVIRONMENT" = "production" ]; then
        if grep -q '"require-dev"' composer.json; then
            error "Development dependencies found in production composer.json"
            warning "Remove require-dev section before production deployment"
        else
            success "No development dependencies in production"
        fi
    fi
}

# Check Laravel security configuration
check_laravel_security() {
    info "Checking Laravel security configuration..."
    
    # Check if APP_KEY is set
    if ! grep -q "^APP_KEY=" .env || grep -q "^APP_KEY=$" .env; then
        error "APP_KEY is not set or empty"
        warning "Run 'php artisan key:generate' to generate APP_KEY"
    else
        success "APP_KEY is configured"
    fi
    
    # Check if security middleware is registered
    kernel_file="app/Http/Kernel.php"
    if [ -f "$kernel_file" ]; then
        if grep -q "SecurityHeaders" "$kernel_file"; then
            success "SecurityHeaders middleware is registered"
        else
            error "SecurityHeaders middleware not found in Kernel.php"
        fi
        
        if grep -q "PreventCommonAttacks" "$kernel_file"; then
            success "PreventCommonAttacks middleware is registered"
        else
            error "PreventCommonAttacks middleware not found in Kernel.php"
        fi
    else
        error "Kernel.php not found"
    fi
    
    # Check session security
    if grep -q "^SESSION_SECURE=true" .env; then
        success "Secure sessions are enabled"
    else
        warning "Secure sessions not enabled (SESSION_SECURE=true)"
    fi
    
    if grep -q "^SESSION_HTTP_ONLY=true" .env; then
        success "HTTP-only sessions are enabled"
    else
        warning "HTTP-only sessions not enabled (SESSION_HTTP_ONLY=true)"
    fi
}

# Check database security
check_database_security() {
    info "Checking database security configuration..."
    
    # Check database credentials
    if grep -q "DB_HOST=localhost\|DB_HOST=127.0.0.1" .env && [ "$ENVIRONMENT" = "production" ]; then
        error "Database on localhost in production environment"
        warning "Use dedicated database server for production"
    else
        success "Database configuration appears appropriate for environment"
    fi
    
    # Check if database SSL is configured
    if grep -q "DB_SSL_MODE=" .env; then
        success "Database SSL mode is configured"
    else
        warning "Database SSL mode not configured"
    fi
}

# Check SSL/HTTPS configuration
check_ssl_configuration() {
    info "Checking SSL/HTTPS configuration..."
    
    # Check if HTTPS is enforced
    if grep -q "^FORCE_HTTPS=true" .env; then
        success "HTTPS enforcement is enabled"
    else
        warning "HTTPS enforcement not enabled"
    fi
    
    # Check APP_URL for HTTPS
    app_url=$(grep "^APP_URL=" .env | cut -d'=' -f2)
    if [[ $app_url == https://* ]]; then
        success "APP_URL uses HTTPS"
    else
        error "APP_URL does not use HTTPS: $app_url"
    fi
}

# Check for sensitive files
check_sensitive_files() {
    info "Checking for sensitive files..."
    
    # Files that should not be in production
    sensitive_files=(
        ".env.local"
        ".env.development.local"
        ".env.test.local"
        "phpinfo.php"
        "info.php"
        "test.php"
        "debug.php"
    )
    
    for file in "${sensitive_files[@]}"; do
        if [ -f "$file" ]; then
            error "Sensitive file found in production: $file"
            warning "Remove $file before deployment"
        fi
    done
    
    # Check for backup files
    backup_files=$(find . -name "*.bak" -o -name "*.backup" -o -name "*~" 2>/dev/null | head -10)
    if [ -n "$backup_files" ]; then
        warning "Backup files found in project directory"
        echo "$backup_files" | while read file; do
            warning "  - $file"
        done
    fi
}

# Check git configuration
check_git_configuration() {
    info "Checking Git configuration..."
    
    # Check if .git directory exists
    if [ -d ".git" ]; then
        warning ".git directory is accessible - consider removing in production"
    fi
    
    # Check for .gitignore
    if [ -f ".gitignore" ]; then
        success ".gitignore file exists"
        
        # Check for important ignore patterns
        important_patterns=(
            ".env"
            "vendor/"
            "node_modules/"
            "storage/logs/"
            "storage/framework/cache/"
            "storage/framework/sessions/"
            "storage/framework/views/"
        )
        
        for pattern in "${important_patterns[@]}"; do
            if grep -q "$pattern" .gitignore; then
                success "Pattern '$pattern' is in .gitignore"
            else
                warning "Pattern '$pattern' not found in .gitignore"
            fi
        done
    else
        error ".gitignore file not found"
    fi
}

# Check npm dependencies (if exists)
check_npm_dependencies() {
    info "Checking npm dependencies..."
    
    if [ -f "package.json" ]; then
        if [ -f "package-lock.json" ]; then
            success "package-lock.json exists"
        else
            warning "package-lock.json not found"
        fi
        
        # Check for known vulnerable packages (basic check)
        if command -v npm >/dev/null 2>&1; then
            if npm audit --audit-level=high >/dev/null 2>&1; then
                success "No high-level security vulnerabilities in npm dependencies"
            else
                error "High-level security vulnerabilities found in npm dependencies"
                warning "Run 'npm audit' to see details"
            fi
        fi
    else
        info "No package.json found - skipping npm checks"
    fi
}

# Check server configuration
check_server_configuration() {
    info "Checking server configuration..."
    
    # Check for .htaccess file (Apache)
    if [ -f ".htaccess" ]; then
        success ".htaccess file exists"
        
        # Check for security headers in .htaccess
        if grep -q "Header always set X-Frame-Options" .htaccess; then
            success "Security headers configured in .htaccess"
        else
            warning "Security headers not found in .htaccess"
        fi
    else
        info "No .htaccess file found"
    fi
    
    # Check nginx configuration (if exists)
    if [ -f "nginx.conf" ] || [ -f "/etc/nginx/nginx.conf" ]; then
        success "Nginx configuration found"
        
        # Check for security headers
        if grep -q "add_header.*X-Frame-Options" /etc/nginx/nginx.conf 2>/dev/null; then
            success "Security headers configured in Nginx"
        else
            warning "Security headers not found in Nginx configuration"
        fi
    fi
}

# Check log file permissions
check_log_security() {
    info "Checking log file security..."
    
    # Check if logs directory has correct permissions
    if [ -d "storage/logs" ]; then
        log_perms=$(stat -c "%a" storage/logs)
        if [ "$log_perms" = "755" ]; then
            success "Logs directory has correct permissions (755)"
        else
            error "Logs directory has incorrect permissions: $log_perms (should be 755)"
        fi
        
        # Check for recent log files
        recent_logs=$(find storage/logs -name "*.log" -mtime -1 2>/dev/null | wc -l)
        if [ $recent_logs -gt 0 ]; then
            success "Recent log files found ($recent_logs files)"
        else
            warning "No recent log files found"
        fi
    fi
}

# Performance and resource checks
check_performance_security() {
    info "Checking performance and resource limits..."
    
    # Check PHP memory limit
    memory_limit=$(php -r "echo ini_get('memory_limit');")
    if [ "$memory_limit" = "-1" ] || [ -z "$memory_limit" ]; then
        warning "PHP memory limit is unlimited - consider setting a limit"
    else
        success "PHP memory limit is set: $memory_limit"
    fi
    
    # Check max execution time
    max_execution_time=$(php -r "echo ini_get('max_execution_time');")
    if [ "$max_execution_time" = "0" ]; then
        warning "PHP max_execution_time is unlimited"
    else
        success "PHP max_execution_time is set: $max_execution_time seconds"
    fi
    
    # Check upload limits
    upload_max_filesize=$(php -r "echo ini_get('upload_max_filesize');")
    post_max_size=$(php -r "echo ini_get('post_max_size');")
    
    success "Upload limits - Max file: $upload_max_filesize, Post: $post_max_size"
}

# Generate security report
generate_security_report() {
    info "Generating security report..."
    
    report_file="logs/security-report-${ENVIRONMENT}-${TIMESTAMP}.json"
    
    cat > "$report_file" << EOF
{
    "timestamp": "$(date -Iseconds)",
    "environment": "$ENVIRONMENT",
    "summary": {
        "checks_passed": $CHECKS_PASSED,
        "checks_failed": $CHECKS_FAILED,
        "total_checks": $((CHECKS_PASSED + CHECKS_FAILED))
    },
    "php_version": "$(php -r 'echo PHP_VERSION;')",
    "composer_lock_exists": $([ -f "composer.lock" ] && echo "true" || echo "false"),
    "env_file_exists": $([ -f ".env" ] && echo "true" || echo "false"),
    "app_key_set": $(grep -q "^APP_KEY=.*" .env && echo "true" || echo "false"),
    "https_enforced": $(grep -q "^FORCE_HTTPS=true" .env && echo "true" || echo "false"),
    "secure_sessions": $(grep -q "^SESSION_SECURE=true" .env && echo "true" || echo "false")
}
EOF
    
    success "Security report generated: $report_file"
}

# Main execution
main() {
    print_header
    
    # Run all checks
    check_environment
    check_file_permissions
    check_php_version
    check_composer_dependencies
    check_laravel_security
    check_database_security
    check_ssl_configuration
    check_sensitive_files
    check_git_configuration
    check_npm_dependencies
    check_server_configuration
    check_log_security
    check_performance_security
    
    # Generate report
    generate_security_report
    
    # Summary
    echo
    echo -e "${BLUE}=================================="
    echo "🔒 SECURITY CHECK SUMMARY"
    echo "=================================="
    echo -e "${NC}"
    
    echo -e "${GREEN}✅ Checks Passed: $CHECKS_PASSED${NC}"
    echo -e "${RED}❌ Checks Failed: $CHECKS_FAILED${NC}"
    echo -e "${BLUE}📊 Total Checks: $((CHECKS_PASSED + CHECKS_FAILED))${NC}"
    
    if [ $CHECKS_FAILED -eq 0 ]; then
        echo
        echo -e "${GREEN}🎉 All security checks passed! Safe to deploy.${NC}"
        log "SECURITY CHECK RESULT: PASSED - Safe to deploy"
        exit 0
    else
        echo
        echo -e "${RED}🚨 Security checks failed! Please fix issues before deploying.${NC}"
        echo -e "${YELLOW}Check $LOG_FILE for detailed log information.${NC}"
        log "SECURITY CHECK RESULT: FAILED - Do not deploy"
        exit 1
    fi
}

# Run main function
main "$@"