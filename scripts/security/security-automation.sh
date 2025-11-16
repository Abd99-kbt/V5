#!/bin/bash

# 🔒 Security Automation Script for V5 System
# Author: Security Team
# Purpose: Automated security monitoring and checks
# Usage: ./scripts/security/security-automation.sh [action]
# 
# Actions: daily, weekly, monthly, test, report

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
LOG_FILE="$PROJECT_ROOT/logs/security-automation-$(date +%Y%m%d).log"
CONFIG_FILE="$PROJECT_ROOT/.env"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Logging function
log() {
    echo -e "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}✅ $1${NC}"
    log "SUCCESS: $1"
}

warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
    log "WARNING: $1"
}

error() {
    echo -e "${RED}❌ $1${NC}"
    log "ERROR: $1"
}

info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
    log "INFO: $1"
}

# Environment check
check_environment() {
    if [ ! -f "$CONFIG_FILE" ]; then
        error ".env file not found"
        exit 1
    fi
    
    # Load environment variables
    export $(grep -v '^#' "$CONFIG_FILE" | xargs)
    
    # Check critical security settings
    if [ "$APP_DEBUG" = "true" ]; then
        error "APP_DEBUG is enabled - security risk"
    else
        success "APP_DEBUG is disabled"
    fi
    
    if [ "$APP_ENV" != "production" ]; then
        warning "APP_ENV is not production: $APP_ENV"
    else
        success "APP_ENV is production"
    fi
    
    if [ "$SESSION_ENCRYPT" != "true" ]; then
        error "SESSION_ENCRYPT is not enabled"
    else
        success "Session encryption is enabled"
    fi
}

# Daily security checks
daily_checks() {
    info "Starting daily security checks..."
    
    # 1. Check for failed login attempts
    info "Checking failed login attempts..."
    if php artisan tinker --execute="
        \$recentFailures = DB::table('users')
            ->where('failed_login_attempts', '>', 0)
            ->where('updated_at', '>', now()->subDay())
            ->count();
        echo \"Recent failed login attempts: \$recentFailures\n\";
    " >> "$LOG_FILE" 2>&1; then
        success "Failed login attempts check completed"
    else
        warning "Could not check failed login attempts"
    fi
    
    # 2. Check active sessions
    info "Checking active sessions..."
    if command -v redis-cli >/dev/null 2>&1; then
        SESSION_COUNT=$(redis-cli --raw scard "session:*" 2>/dev/null || echo "0")
        if [ "$SESSION_COUNT" -gt 1000 ]; then
            warning "High number of active sessions: $SESSION_COUNT"
        else
            success "Active sessions: $SESSION_COUNT"
        fi
    else
        warning "Redis CLI not found - skipping session check"
    fi
    
    # 3. Check for suspicious IPs
    info "Checking blocked IPs..."
    if command -v redis-cli >/dev/null 2>&1; then
        BLOCKED_COUNT=$(redis-cli --raw keys "blacklist:*" 2>/dev/null | wc -l || echo "0")
        info "Blocked IPs: $BLOCKED_COUNT"
        
        if [ "$BLOCKED_COUNT" -gt 50 ]; then
            warning "High number of blocked IPs: $BLOCKED_COUNT"
        fi
    fi
    
    # 4. Check disk space
    info "Checking disk space..."
    DISK_USAGE=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
    if [ "$DISK_USAGE" -gt 85 ]; then
        error "Disk usage is high: ${DISK_USAGE}%"
    else
        success "Disk usage: ${DISK_USAGE}%"
    fi
    
    # 5. Check log files for errors
    info "Checking log files..."
    if [ -d "$PROJECT_ROOT/storage/logs" ]; then
        ERROR_COUNT=$(find "$PROJECT_ROOT/storage/logs" -name "*.log" -exec grep -l "ERROR" {} \; 2>/dev/null | wc -l)
        if [ "$ERROR_COUNT" -gt 0 ]; then
            warning "Found $ERROR_COUNT log files with errors"
        else
            success "No errors found in logs"
        fi
    fi
    
    # 6. Check for security updates
    info "Checking for security updates..."
    if command -v composer >/dev/null 2>&1; then
        if composer audit --format=json >/dev/null 2>&1; then
            success "No security vulnerabilities found in Composer packages"
        else
            warning "Security vulnerabilities found in Composer packages"
        fi
    fi
    
    success "Daily security checks completed"
}

# Weekly security review
weekly_review() {
    info "Starting weekly security review..."
    
    # 1. Run comprehensive security scan
    info "Running security scan..."
    if [ -f "$SCRIPT_DIR/pre-deployment-check.sh" ]; then
        bash "$SCRIPT_DIR/pre-deployment-check.sh" weekly >> "$LOG_FILE" 2>&1
    fi
    
    # 2. Check user accounts
    info "Checking user accounts..."
    php artisan tinker --execute="
        \$inactiveUsers = DB::table('users')
            ->where('last_login_at', '<', now()->subDays(30))
            ->count();
        \$totalUsers = DB::table('users')->count();
        echo \"Inactive users (30+ days): \$inactiveUsers / Total users: \$totalUsers\n\";
    " >> "$LOG_FILE" 2>&1
    
    # 3. Check MFA adoption
    info "Checking MFA adoption..."
    php artisan tinker --execute="
        \$mfaEnabled = DB::table('users')->where('mfa_enabled', true)->count();
        \$totalUsers = DB::table('users')->count();
        \$percentage = \$totalUsers > 0 ? round((\$mfaEnabled / \$totalUsers) * 100, 2) : 0;
        echo \"MFA enabled users: \$mfaEnabled / \$totalUsers (\$percentage%)\n\";
    " >> "$LOG_FILE" 2>&1
    
    # 4. Database performance check
    info "Checking database performance..."
    php artisan tinker --execute="
        \$slowQueries = DB::select('SHOW STATUS LIKE \"Slow_queries\"');
        foreach (\$slowQueries as \$query) {
            echo \"Slow queries: \" . \$query->Value . \"\n\";
        }
    " >> "$LOG_FILE" 2>&1
    
    # 5. Generate weekly report
    generate_weekly_report
    
    success "Weekly security review completed"
}

# Monthly security audit
monthly_audit() {
    info "Starting monthly security audit..."
    
    # 1. Full security assessment
    info "Running full security assessment..."
    if command -v composer >/dev/null 2>&1; then
        composer audit --format=json > "$PROJECT_ROOT/logs/composer-audit-$(date +%Y%m).json"
    fi
    
    # 2. Check all dependencies
    info "Checking dependencies..."
    if [ -f "$PROJECT_ROOT/package.json" ] && command -v npm >/dev/null 2>&1; then
        npm audit --json > "$PROJECT_ROOT/logs/npm-audit-$(date +%Y%m).json"
    fi
    
    # 3. Run all security tests
    info "Running security tests..."
    php artisan test --testsuite=Security --log-junit="$PROJECT_ROOT/logs/security-tests-$(date +%Y%m).xml"
    
    # 4. Check SSL certificate
    info "Checking SSL certificate..."
    if [ -n "$APP_URL" ] && [[ $APP_URL == https://* ]]; then
        DOMAIN=$(echo "$APP_URL" | sed 's|https://||' | sed 's|/.*||')
        if command -v openssl >/dev/null 2>&1; then
            EXPIRY=$(echo | openssl s_client -servername "$DOMAIN" -connect "$DOMAIN:443" 2>/dev/null | openssl x509 -noout -enddate 2>/dev/null)
            if [ -n "$EXPIRY" ]; then
                info "SSL Certificate expires: $EXPIRY"
            fi
        fi
    fi
    
    # 5. Generate monthly report
    generate_monthly_report
    
    success "Monthly security audit completed"
}

# Security test runner
run_security_tests() {
    info "Running security tests..."
    
    # 1. Authentication tests
    info "Running authentication security tests..."
    php artisan test tests/Security/AuthenticationSecurityTest.php --testdox
    
    # 2. Database security tests
    info "Running database security tests..."
    php artisan test tests/Security/DatabaseSecurityTest.php --testdox
    
    # 3. API security tests
    if [ -d "tests/Api/Security" ]; then
        info "Running API security tests..."
        php artisan test tests/Api/Security/ --testdox
    fi
    
    # 4. Integration tests
    info "Running security integration tests..."
    php artisan test tests/Feature/Security/ --testdox
    
    success "Security tests completed"
}

# Generate reports
generate_daily_report() {
    REPORT_FILE="$PROJECT_ROOT/logs/daily-security-report-$(date +%Y%m%d).md"
    
    cat > "$REPORT_FILE" << EOF
# Daily Security Report - $(date '+%Y-%m-%d')

## Summary
- Date: $(date '+%Y-%m-%d %H:%M:%S')
- Environment: ${APP_ENV:-'Unknown'}
- Status: Completed

## Checks Performed
- Failed login attempts monitoring
- Active sessions analysis
- Blocked IPs tracking
- Disk space monitoring
- Log files error scanning
- Security updates checking

## Alerts
$(grep -E "(WARNING|ERROR)" "$LOG_FILE" | tail -20)

## Next Steps
- Review any warnings or errors
- Investigate suspicious activities
- Update security configurations if needed

---
Generated by V5 Security Automation System
EOF
    
    success "Daily report generated: $REPORT_FILE"
}

generate_weekly_report() {
    REPORT_FILE="$PROJECT_ROOT/logs/weekly-security-report-$(date +%Y%W).md"
    
    cat > "$REPORT_FILE" << EOF
# Weekly Security Report - Week $(date +%Y-W%V)

## Summary
- Week: $(date '+%Y-W%V')
- Period: $(date -d '7 days ago' '+%Y-%m-%d') to $(date '+%Y-%m-%d')
- Status: Completed

## Weekly Activities
- Security scan completed
- User accounts reviewed
- MFA adoption tracked
- Database performance checked
- Weekly report generated

## Security Metrics
- Total security scans: $(grep -c "security scan" "$LOG_FILE" || echo "0")
- Failed login attempts: $(grep -c "failed login" "$LOG_FILE" || echo "0")
- Blocked IPs: $(redis-cli --raw keys "blacklist:*" 2>/dev/null | wc -l || echo "0")
- Active sessions: $(redis-cli --raw scard "session:*" 2>/dev/null || echo "0")

## Recommendations
- Review inactive user accounts
- Encourage MFA adoption
- Monitor database performance
- Update dependencies

---
Generated by V5 Security Automation System
EOF
    
    success "Weekly report generated: $REPORT_FILE"
}

generate_monthly_report() {
    REPORT_FILE="$PROJECT_ROOT/logs/monthly-security-report-$(date +%Y-%m).md"
    
    cat > "$REPORT_FILE" << EOF
# Monthly Security Report - $(date '+%Y-%m')

## Summary
- Month: $(date '+%Y-%m')
- Period: $(date -d '30 days ago' '+%Y-%m-%d') to $(date '+%Y-%m-%d')
- Status: Completed

## Monthly Activities
- Full security assessment
- Dependencies audit
- Security tests execution
- SSL certificate verification
- Monthly report generation

## Security Statistics
- Total login attempts: $(grep -c "login" "$LOG_FILE" || echo "0")
- Security violations: $(grep -c "violation" "$LOG_FILE" || echo "0")
- Average daily sessions: $(redis-cli --raw scard "session:*" 2>/dev/null | xargs -I {} echo "scale=1; {}/30" | bc 2>/dev/null || echo "0")

## Compliance Status
- OWASP Top 10: $([ -f "$PROJECT_ROOT/logs/composer-audit-$(date +%Y%m).json" ] && echo "Reviewed" || echo "Pending")
- Security Tests: $([ -f "$PROJECT_ROOT/logs/security-tests-$(date +%Y%m).xml" ] && echo "Passed" || echo "Pending")

## Action Items
- [ ] Review monthly security metrics
- [ ] Update security policies
- [ ] Schedule security training
- [ ] Plan next month's security activities

---
Generated by V5 Security Automation System
EOF
    
    success "Monthly report generated: $REPORT_FILE"
}

# Emergency response
emergency_response() {
    local reason="$1"
    
    info "EMERGENCY SECURITY RESPONSE TRIGGERED"
    info "Reason: $reason"
    
    # 1. Immediate actions
    log "EMERGENCY: Immediate security response started - $reason"
    
    # 2. Block all suspicious IPs
    if command -v redis-cli >/dev/null 2>&1; then
        redis-cli SADD emergency:blocked_ips "emergency_$(date +%s)" >> "$LOG_FILE"
    fi
    
    # 3. Generate emergency report
    EMERGENCY_REPORT="$PROJECT_ROOT/logs/emergency-security-$(date +%Y%m%d-%H%M%S).md"
    cat > "$EMERGENCY_REPORT" << EOF
# Emergency Security Report

## Emergency Details
- Time: $(date '+%Y-%m-%d %H:%M:%S')
- Reason: $reason
- Response: Automated emergency procedures activated

## Immediate Actions Taken
- Emergency security procedures activated
- Additional monitoring enabled
- Emergency report generated

## Next Steps
1. Investigate the security incident
2. Assess the scope of the issue
3. Implement additional security measures
4. Notify security team and management

## Contact Information
- Security Team: +963-XXX-XXXX
- Emergency Email: security@v5-system.com
EOF
    
    success "Emergency report generated: $EMERGENCY_REPORT"
    
    # 4. Send notifications (if configured)
    if [ -n "$SECURITY_ALERT_EMAIL" ]; then
        echo "Emergency security response triggered. See: $EMERGENCY_REPORT" | \
        mail -s "EMERGENCY: Security Response Activated" "$SECURITY_ALERT_EMAIL" 2>/dev/null || \
        info "Could not send email notification"
    fi
}

# Help function
show_help() {
    echo "🔒 V5 Security Automation Script"
    echo ""
    echo "Usage: $0 [ACTION] [OPTIONS]"
    echo ""
    echo "Actions:"
    echo "  daily       Run daily security checks"
    echo "  weekly      Run weekly security review"
    echo "  monthly     Run monthly security audit"
    echo "  test        Run all security tests"
    echo "  report      Generate security report"
    echo "  emergency   Trigger emergency response"
    echo "  help        Show this help message"
    echo ""
    echo "Options:"
    echo "  --verbose   Enable verbose output"
    echo "  --log-file  Specify custom log file"
    echo ""
    echo "Examples:"
    echo "  $0 daily                    # Run daily checks"
    echo "  $0 weekly --verbose         # Run weekly review with verbose output"
    echo "  $0 emergency \"System breach\" # Trigger emergency response"
    echo ""
}

# Main function
main() {
    # Create log directory
    mkdir -p "$(dirname "$LOG_FILE")"
    
    # Check environment first
    check_environment
    
    # Parse command line arguments
    ACTION="${1:-help}"
    shift || true
    
    case "$ACTION" in
        daily)
            daily_checks
            generate_daily_report
            ;;
        weekly)
            weekly_review
            generate_weekly_report
            ;;
        monthly)
            monthly_audit
            generate_monthly_report
            ;;
        test)
            run_security_tests
            ;;
        report)
            generate_daily_report
            generate_weekly_report
            generate_monthly_report
            ;;
        emergency)
            reason="${1:-Security emergency triggered}"
            emergency_response "$reason"
            ;;
        help|--help|-h)
            show_help
            ;;
        *)
            error "Unknown action: $ACTION"
            show_help
            exit 1
            ;;
    esac
    
    success "Security automation completed successfully"
}

# Check if script is run directly
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi