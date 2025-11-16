#!/bin/bash

# Production Restore Script
# Restores Laravel application from backups
# Usage: ./production-restore.sh [backup_name] [--type TYPE]

set -euo pipefail

# Configuration
APP_NAME="laravel-app"
APP_PATH="/var/www/your-app"
BACKUP_DIR="/var/backups/${APP_NAME}"
LOG_FILE="/var/log/production-restore-$(date +%Y%m%d_%H%M%S).log"
MYSQL_USER="app_user"
MYSQL_PASSWORD="strong_password"
MYSQL_DATABASE="production_app"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# Logging
log() {
    echo -e "${GREEN}[$(date +'%H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"
}

# Show available backups
show_available_backups() {
    log "Available backups in ${BACKUP_DIR}:"
    echo "==========================================="
    
    if [ ! -d "$BACKUP_DIR" ]; then
        error "Backup directory not found: $BACKUP_DIR"
        return 1
    fi
    
    # Database backups
    if [ -d "${BACKUP_DIR}/database" ]; then
        echo "Database Backups:"
        find "${BACKUP_DIR}/database" -name "*_database.sql.gz" -exec basename {} \; | sort -r | head -10 | while read backup; do
            local size=$(du -h "${BACKUP_DIR}/database/$backup" | cut -f1)
            local date_str=$(echo "$backup" | grep -o '[0-9]\{8\}')
            local date_formatted="${date_str:0:4}-${date_str:4:2}-${date_str:6:2}"
            echo "  📊 $backup ($size) - $date_formatted"
        done
        echo
    fi
    
    # Files backups
    if [ -d "${BACKUP_DIR}/files" ]; then
        echo "Files Backups:"
        find "${BACKUP_DIR}/files" -name "*_files.tar.gz" -exec basename {} \; | sort -r | head -10 | while read backup; do
            local size=$(du -h "${BACKUP_DIR}/files/$backup" | cut -f1)
            local date_str=$(echo "$backup" | grep -o '[0-9]\{8\}')
            local date_formatted="${date_str:0:4}-${date_str:4:2}-${date_str:6:2}"
            echo "  📁 $backup ($size) - $date_formatted"
        done
        echo
    fi
}

# Verify backup integrity
verify_backup() {
    local backup_path=$1
    local backup_name=$2
    
    log "Verifying backup integrity for $backup_name..."
    
    if [ ! -f "$backup_path" ]; then
        error "Backup file not found: $backup_path"
        return 1
    fi
    
    # Check if checksum file exists
    if [ -f "${backup_path}.sha256" ]; then
        if sha256sum -c "${backup_path}.sha256" >/dev/null 2>&1; then
            log "✓ Backup checksum verification passed"
            return 0
        else
            error "✗ Backup checksum verification failed!"
            warning "This backup may be corrupted"
            return 1
        fi
    else
        warning "No checksum file found for $backup_path"
        # Try to decompress to verify integrity
        if gzip -t "$backup_path" 2>/dev/null; then
            log "✓ Backup file integrity check passed"
            return 0
        else
            error "✗ Backup file integrity check failed!"
            return 1
        fi
    fi
}

# Restore database
restore_database() {
    local backup_name=$1
    local db_backup_file="${BACKUP_DIR}/database/${backup_name}_database.sql.gz"
    
    if [ ! -f "$db_backup_file" ]; then
        error "Database backup not found: $db_backup_file"
        return 1
    fi
    
    log "Restoring database from backup: $backup_name"
    
    # Verify backup integrity
    if ! verify_backup "$db_backup_file" "$backup_name"; then
        error "Database backup integrity check failed"
        return 1
    fi
    
    # Stop services that might be using the database
    log "Stopping services..."
    systemctl stop nginx php8.2-fpm 2>/dev/null || true
    
    # Drop and recreate database
    log "Recreating database..."
    mysql -u root -p << EOF
DROP DATABASE IF EXISTS ${MYSQL_DATABASE};
CREATE DATABASE ${MYSQL_DATABASE} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON ${MYSQL_DATABASE}.* TO '${MYSQL_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF
    
    # Restore database
    log "Restoring database data..."
    gunzip -c "$db_backup_file" | mysql -u "${MYSQL_USER}" -p"${MYSQL_PASSWORD}" "${MYSQL_DATABASE}"
    
    if [ $? -eq 0 ]; then
        log "✓ Database restoration completed successfully"
        return 0
    else
        error "✗ Database restoration failed"
        return 1
    fi
}

# Restore application files
restore_application_files() {
    local backup_name=$1
    local files_backup_file="${BACKUP_DIR}/files/${backup_name}_files.tar.gz"
    
    if [ ! -f "$files_backup_file" ]; then
        error "Files backup not found: $files_backup_file"
        return 1
    fi
    
    log "Restoring application files from backup: $backup_name"
    
    # Verify backup integrity
    if ! verify_backup "$files_backup_file" "$backup_name"; then
        error "Files backup integrity check failed"
        return 1
    fi
    
    # Create app directory if it doesn't exist
    mkdir -p "$APP_PATH"
    
    # Extract backup
    log "Extracting application files..."
    tar -xzf "$files_backup_file" -C "$APP_PATH"
    
    # Set permissions
    log "Setting file permissions..."
    chown -R www-data:www-data "$APP_PATH"
    chmod -R 755 "$APP_PATH"
    chmod -R 775 "$APP_PATH/storage" 2>/dev/null || mkdir -p "$APP_PATH/storage"
    chmod -R 775 "$APP_PATH/bootstrap/cache" 2>/dev/null || mkdir -p "$APP_PATH/bootstrap/cache"
    
    log "✓ Application files restoration completed"
    return 0
}

# Post-restore tasks
post_restore_tasks() {
    log "Performing post-restore tasks..."
    
    # Clear caches
    if [ -d "$APP_PATH" ]; then
        cd "$APP_PATH"
        
        log "Clearing Laravel caches..."
        sudo -u www-data php artisan cache:clear 2>/dev/null || true
        sudo -u www-data php artisan config:clear 2>/dev/null || true
        sudo -u www-data php artisan route:clear 2>/dev/null || true
        sudo -u www-data php artisan view:clear 2>/dev/null || true
        
        log "Regenerating caches..."
        sudo -u www-data php artisan config:cache 2>/dev/null || true
        sudo -u www-data php artisan route:cache 2>/dev/null || true
        sudo -u www-data php artisan view:cache 2>/dev/null || true
        
        # Run migrations if needed
        log "Running database migrations..."
        sudo -u www-data php artisan migrate --force 2>/dev/null || warning "Migration failed or not needed"
    fi
    
    # Start services
    log "Starting services..."
    systemctl start php8.2-fpm 2>/dev/null || true
    systemctl start nginx 2>/dev/null || true
    
    # Test restoration
    log "Testing restoration..."
    sleep 5
    
    if curl -f -s http://localhost/health > /dev/null 2>&1; then
        log "✓ Application is responding after restoration"
    else
        warning "Application may not be responding correctly after restoration"
    fi
    
    log "✓ Post-restore tasks completed"
}

# Main restore function
main() {
    local backup_name=$1
    local restore_type=${2:-"full"}  # full, database, files
    
    if [ "$backup_name" = "list" ] || [ "$backup_name" = "--list" ]; then
        show_available_backups
        exit 0
    fi
    
    if [ -z "$backup_name" ]; then
        error "No backup name provided"
        echo "Usage: $0 <backup_name> [--type TYPE]"
        echo "Use 'list' to show available backups"
        exit 1
    fi
    
    # Check if running as root
    if [ "$EUID" -ne 0 ]; then
        error "This script must be run as root for restoration"
        exit 1
    fi
    
    log "==========================================="
    log "Starting Laravel Production Restore"
    log "Backup Name: $backup_name"
    log "Restore Type: $restore_type"
    log "==========================================="
    
    # Confirm restoration
    echo "⚠️  WARNING: This will overwrite existing data!"
    echo "Backup name: $backup_name"
    echo "Restore type: $restore_type"
    echo
    read -p "Are you sure you want to continue? (yes/no): " confirm
    
    if [ "$confirm" != "yes" ]; then
        log "Restoration cancelled by user"
        exit 0
    fi
    
    local success_count=0
    local total_count=0
    
    # Perform restoration based on type
    case $restore_type in
        "full")
            total_count=2
            restore_database "$backup_name" && ((success_count++))
            restore_application_files "$backup_name" && ((success_count++))
            ;;
        "database")
            total_count=1
            restore_database "$backup_name" && ((success_count++))
            ;;
        "files")
            total_count=1
            restore_application_files "$backup_name" && ((success_count++))
            ;;
        *)
            error "Invalid restore type: $restore_type"
            exit 1
            ;;
    esac
    
    # Post-restore tasks
    post_restore_tasks
    
    # Final status
    if [ $success_count -eq $total_count ]; then
        log "==========================================="
        log "✓ Restoration completed successfully!"
        log "Restored components: $success_count/$total_count"
        log "Backup used: $backup_name"
        log "==========================================="
        exit 0
    else
        error "==========================================="
        error "✗ Restoration completed with failures!"
        error "Restored components: $success_count/$total_count"
        error "Backup used: $backup_name"
        error "Check logs for details: $LOG_FILE"
        error "==========================================="
        exit 1
    fi
}

# Show help
show_help() {
    echo "Laravel Production Restore Script"
    echo "=================================="
    echo
    echo "Usage: $0 <backup_name> [--type TYPE]"
    echo
    echo "Parameters:"
    echo "  backup_name    - Name of the backup to restore"
    echo "  --type TYPE    - Type of restoration (default: full)"
    echo "                    full: database + files"
    echo "                    database: database only"
    echo "                    files: application files only"
    echo
    echo "Commands:"
    echo "  list          - List available backups"
    echo
    echo "Examples:"
    echo "  $0 list"
    echo "  $0 laravel-app_full_20241106_120000"
    echo "  $0 laravel-app_full_20241106_120000 --type database"
    echo
    echo "⚠️  WARNING: This script will overwrite existing data!"
    echo "   Make sure you have a backup of current state."
    echo
}

# Handle help request
if [ "${1:-}" = "help" ] || [ "${1:-}" = "--help" ] || [ "${1:-}" = "-h" ]; then
    show_help
    exit 0
fi

# Run main function
main "$@"