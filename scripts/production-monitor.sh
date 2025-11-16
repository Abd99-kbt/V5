#!/bin/bash

# Production Monitoring Script
# Monitors Laravel application health and performance
# Usage: ./production-monitor.sh [command] [options]

set -euo pipefail

# Configuration
APP_NAME="laravel-app"
APP_PATH="/var/www/your-app"
LOG_DIR="/var/log/production-monitor"
METRICS_FILE="/tmp/laravel-metrics-$(date +%Y%m%d).json"
ALERT_THRESHOLD_CPU=80
ALERT_THRESHOLD_MEMORY=85
ALERT_THRESHOLD_DISK=90
ALERT_THRESHOLD_RESPONSE_TIME=5
MYSQL_USER="app_user"
MYSQL_PASSWORD="strong_password"
MYSQL_DATABASE="production_app"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

# Logging
log() {
    echo -e "${GREEN}[$(date +'%H:%M:%S')]${NC} $1" | tee -a "${LOG_DIR}/monitor-$(date +%Y%m%d).log"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "${LOG_DIR}/monitor-$(date +%Y%m%d).log"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "${LOG_DIR}/monitor-$(date +%Y%m%d).log"
}

info() {
    echo -e "${BLUE}[INFO]${NC} $1" | tee -a "${LOG_DIR}/monitor-$(date +%Y%m%d).log"
}

# Initialize monitoring
init_monitoring() {
    mkdir -p "$LOG_DIR"
    chmod 750 "$LOG_DIR"
}

# Collect system metrics
collect_system_metrics() {
    local cpu_usage=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | awk -F'%' '{print $1}' | cut -d. -f1)
    local memory_usage=$(free | grep Mem | awk '{printf "%.0f", $3/$2 * 100.0}')
    local disk_usage=$(df -h / | awk 'NR==2{print $5}' | sed 's/%//')
    local load_avg=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | sed 's/,//')
    
    log "System metrics - CPU: ${cpu_usage}% Memory: ${memory_usage}% Disk: ${disk_usage}% Load: $load_avg"
}

# Collect application metrics
collect_application_metrics() {
    local response_time
    response_time=$(curl -w "%{time_total}" -o /dev/null -s http://localhost 2>/dev/null || echo "999")
    
    local app_status="down"
    if curl -f -s http://localhost/health > /dev/null 2>&1; then
        app_status="up"
    fi
    
    log "Application metrics - Status: $app_status Response Time: ${response_time}s"
}

# Check for alerts
check_alerts() {
    local alerts=()
    
    # Check system alerts
    local cpu_usage=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | awk -F'%' '{print $1}' | cut -d. -f1)
    local memory_usage=$(free | grep Mem | awk '{printf "%.0f", $3/$2 * 100.0}')
    local disk_usage=$(df -h / | awk 'NR==2{print $5}' | sed 's/%//')
    local response_time=$(curl -w "%{time_total}" -o /dev/null -s http://localhost 2>/dev/null || echo "999")
    
    if [ "$cpu_usage" -gt "$ALERT_THRESHOLD_CPU" ]; then
        alerts+=("HIGH CPU USAGE: ${cpu_usage}%")
    fi
    
    if [ "$memory_usage" -gt "$ALERT_THRESHOLD_MEMORY" ]; then
        alerts+=("HIGH MEMORY USAGE: ${memory_usage}%")
    fi
    
    if [ "$disk_usage" -gt "$ALERT_THRESHOLD_DISK" ]; then
        alerts+=("HIGH DISK USAGE: ${disk_usage}%")
    fi
    
    if (( $(echo "$response_time > $ALERT_THRESHOLD_RESPONSE_TIME" | bc -l 2>/dev/null || echo 0) )); then
        alerts+=("SLOW RESPONSE TIME: ${response_time}s")
    fi
    
    # Check application status
    local app_status="down"
    if curl -f -s http://localhost/health > /dev/null 2>&1; then
        app_status="up"
    fi
    if [ "$app_status" != "up" ]; then
        alerts+=("APPLICATION DOWN")
    fi
    
    # Output alerts
    if [ ${#alerts[@]} -gt 0 ]; then
        error "ALERTS DETECTED:"
        for alert in "${alerts[@]}"; do
            echo "  🚨 $alert"
        done
        return 1
    else
        log "✓ All metrics within normal ranges"
        return 0
    fi
}

# Show current status
show_status() {
    log "Current System Status:"
    echo "======================"
    
    # System info
    echo "System:"
    echo "  CPU Load: $(uptime | awk -F'load average:' '{print $2}')"
    echo "  Memory: $(free -h | grep Mem | awk '{print $3 "/" $2}')"
    echo "  Disk: $(df -h / | awk 'NR==2{print $3 "/" $2 " (" $5 " used)"}')"
    echo
    
    # Application status
    echo "Application:"
    if curl -f -s http://localhost/health > /dev/null 2>&1; then
        echo "  Status: ✓ Running"
        echo "  Response Time: $(curl -w "%{time_total}" -o /dev/null -s http://localhost 2>/dev/null || echo 'N/A')s"
    else
        echo "  Status: ✗ Down"
    fi
    echo
    
    # Services status
    echo "Services:"
    local services=("nginx" "php8.2-fpm" "mysql" "redis")
    for service in "${services[@]}"; do
        if systemctl is-active --quiet "$service" 2>/dev/null; then
            echo "  $service: ✓ Running"
        else
            echo "  $service: ✗ Stopped"
        fi
    done
}

# Single monitoring cycle
run_monitoring_cycle() {
    log "Starting monitoring cycle..."
    
    # Collect metrics
    collect_system_metrics
    collect_application_metrics
    
    # Check for alerts
    if ! check_alerts; then
        # Send alert notification (if mail is configured)
        if command -v mail >/dev/null 2>&1; then
            echo "Alert generated at $(date)" | mail -s "Laravel Production Alert" admin@yourdomain.com 2>/dev/null || true
        fi
    fi
    
    log "Monitoring cycle completed"
}

# Main function
main() {
    local command=${1:-"status"}
    
    case $command in
        "once"|"single")
            init_monitoring
            run_monitoring_cycle
            ;;
        "status")
            show_status
            ;;
        "help"|"--help"|"-h")
            echo "Laravel Production Monitor"
            echo "========================="
            echo
            echo "Usage: $0 [command]"
            echo
            echo "Commands:"
            echo "  status          - Show current system status"
            echo "  once            - Run single monitoring cycle"
            echo "  help            - Show this help"
            echo
            echo "Configuration:"
            echo "  Alert Thresholds:"
            echo "    CPU: ${ALERT_THRESHOLD_CPU}%"
            echo "    Memory: ${ALERT_THRESHOLD_MEMORY}%"
            echo "    Disk: ${ALERT_THRESHOLD_DISK}%"
            echo "    Response Time: ${ALERT_THRESHOLD_RESPONSE_TIME}s"
            echo
            ;;
        *)
            error "Unknown command: $command"
            echo "Use '$0 help' for usage information"
            exit 1
            ;;
    esac
}

# Run main function
main "$@"