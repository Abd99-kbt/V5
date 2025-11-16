#!/bin/bash

# Advanced Production Backup Script
# Creates comprehensive backups of Laravel application
# Usage: ./production-backup.sh [options]

set -euo pipefail

# Configuration
APP_NAME="laravel-app"
APP_PATH="/var/www/your-app"
BACKUP_DIR="/var/backups/${APP_NAME}"
RETENTION_DAYS=30
LOG_FILE="/var/log/production-backup-$(date +%Y%m%d_%H%M%S).log"
MYSQL_USER="app_user"
MYSQL_PASSWORD="strong_password"
MYSQL_DATABASE="production_app"
COMPRESSION_LEVEL=6
BACKUP_TYPE=${1:-"full"}  # full, database, files, config

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

# Create backup directory structure
setup_backup_dirs() {
    log "Setting up backup directory structure..."
    mkdir -p "${BACKUP_DIR}"/{database,files,config,logs,temp}
    chmod 750 "${BACKUP_DIR}"
}

# Generate backup filename
generate_backup_name() {
    local timestamp=$(date +%Y%m%d_%H%M%S)
    echo "${APP_NAME}_${BACKUP_TYPE}_${timestamp}"
}

# Database backup function
backup_database() {
    local backup_name=$1
    local db_backup_file="${BACKUP_DIR}/database/${backup_name}_database.sql"
    
    log "Starting database backup..."
    
    # Create database dump with comprehensive options
    mysqldump \
        --user="${MYSQL_USER}" \
        --password="${MYSQL_PASSWORD}" \
        --host=127.0.0.1 \
        --port=3306 \
        --single-transaction \
        --routines \
        --triggers \
        --events \
        --hex-blob \
        --add-drop-table \
        --add-drop-trigger \
        --set-gtid-purged=OFF \
        --lock-tables=false \
        "${MYSQL_DATABASE}" > "${db_backup_file}"
    
    # Compress the backup
    gzip -${COMPRESSION_LEVEL} "${db_backup_file}"
    
    # Verify backup integrity
    if gunzip -t "${db_backup_file}.gz"; then
        local backup_size=$(du -h "${db_backup_file}.gz" | cut -f1)
        log "✓ Database backup completed successfully - Size: $backup_size"
        
        # Generate checksum
        sha256sum "${db_backup_file}.gz" > "${db_backup_file}.gz.sha256"
        
        # Store metadata
        echo "Backup Date: $(date)" > "${BACKUP_DIR}/database/${backup_name}_metadata.txt"
        echo "Database: ${MYSQL_DATABASE}" >> "${BACKUP_DIR}/database/${backup_name}_metadata.txt"
        echo "Size: $backup_size" >> "${BACKUP_DIR}/database/${backup_name}_metadata.txt"
        echo "Compression: gzip level ${COMPRESSION_LEVEL}" >> "${BACKUP_DIR}/database/${backup_name}_metadata.txt"
        
        return 0
    else
        error "Database backup integrity check failed!"
        return 1
    fi
}

# Application files backup
backup_application_files() {
    local backup_name=$1
    local files_backup_dir="${BACKUP_DIR}/temp/files_${backup_name}"
    
    log "Starting application files backup..."
    
    # Create temporary backup directory
    mkdir -p "$files_backup_dir"
    
    # Create excludes file
    cat > "${files_backup_dir}/excludes.txt" << EOF
${APP_PATH}/storage/logs/*
${APP_PATH}/storage/framework/cache/*
${APP_PATH}/storage/framework/sessions/*
${APP_PATH}/storage/framework/views/*
${APP_PATH}/storage/app/private/*
${APP_PATH}/bootstrap/cache/*
${APP_PATH}/vendor/*
${APP_PATH}/node_modules/*
${APP_PATH}/.git/*
${APP_PATH}/tests/*
${APP_PATH}/.env
*.tmp
*.log
Thumbs.db
.DS_Store
EOF
    
    # Create the backup archive
    local backup_file="${BACKUP_DIR}/files/${backup_name}_files.tar.gz"
    tar \
        --exclude-from="${files_backup_dir}/excludes.txt" \
        --exclude="*.tmp" \
        --exclude="*.log" \
        --exclude="Thumbs.db" \
        --exclude=".DS_Store" \
        --exclude="*.cache" \
        --exclude="node_modules" \
        --exclude="vendor" \
        --exclude=".git" \
        --exclude="tests" \
        --exclude="storage/logs/*" \
        --exclude="storage/framework/*" \
        -czf "$backup_file" \
        -C "${APP_PATH}" .
    
    if [ -f "$backup_file" ]; then
        local backup_size=$(du -h "$backup_file" | cut -f1)
        log "✓ Application files backup completed - Size: $backup_size"
        
        # Generate checksum
        sha256sum "$backup_file" > "${backup_file}.sha256"
        
        # Clean up temp directory
        rm -rf "$files_backup_dir"
        
        return 0
    else
        error "Application files backup failed!"
        return 1
    fi
}

# Configuration backup
backup_configuration() {
    local backup_name=$1
    
    log "Starting configuration backup..."
    
    local config_backup_file="${BACKUP_DIR}/config/${backup_name}_config.tar.gz"
    
    # Backup various configuration files
    tar -czf "$config_backup_file" \
        -C "/etc/nginx" . \
        -C "/etc/php/8.2/fpm" . \
        -C "/etc/mysql" . \
        -C "/etc/redis" . \
        -C "/etc/ssl" . 2>/dev/null || warning "Some config files backup failed"
    
    if [ -f "$config_backup_file" ]; then
        local backup_size=$(du -h "$config_backup_file" | cut -f1)
        log "✓ Configuration backup completed - Size: $backup_size"
        
        # Generate checksum
        sha256sum "$config_backup_file" > "${config_backup_file}.sha256"
        
        return 0
    else
        warning "Configuration backup failed or empty"
        return 1
    fi
}

# Redis backup
backup_redis() {
    local backup_name=$1
    
    log "Starting Redis backup..."
    
    if command -v redis-cli >/dev/null 2>&1 && redis-cli ping >/dev/null 2>&1; then
        local redis_backup_file="${BACKUP_DIR}/database/${backup_name}_redis.rdb"
        
        # Create Redis backup
        redis-cli --rdb "$redis_backup_file"
        
        if [ -f "$redis_backup_file" ]; then
            # Compress Redis backup
            gzip -${COMPRESSION_LEVEL} "$redis_backup_file"
            
            local backup_size=$(du -h "${redis_backup_file}.gz" | cut -f1)
            log "✓ Redis backup completed - Size: $backup_size"
            
            # Generate checksum
            sha256sum "${redis_backup_file}.gz" > "${redis_backup_file}.gz.sha256"
            
            return 0
        else
            error "Redis backup failed!"
            return 1
        fi
    else
        warning "Redis not available or not running"
        return 1
    fi
}

# Log files backup (last 7 days)
backup_log_files() {
    local backup_name=$1
    
    log "Starting log files backup (last 7 days)..."
    
    local logs_backup_file="${BACKUP_DIR}/logs/${backup_name}_logs.tar.gz"
    
    # Backup recent log files
    tar -czf "$logs_backup_file" \
        $(find /var/log/nginx -name "*.log" -mtime -7 2>/dev/null | head -10) \
        $(find /var/log -name "*php*log*" -mtime -7 2>/dev/null | head -5) \
        $(find /var/log -name "*mysql*log*" -mtime -7 2>/dev/null | head -3) 2>/dev/null || true
    
    if [ -f "$logs_backup_file" ]; then
        local backup_size=$(du -h "$logs_backup_file" | cut -f1)
        log "✓ Log files backup completed - Size: $backup_size"
        
        # Generate checksum
        sha256sum "$logs_backup_file" > "${logs_backup_file}.sha256"
        
        return 0
    else
        warning "Log files backup failed or empty"
        return 1
    fi
}

# Clean up old backups
cleanup_old_backups() {
    log "Cleaning up old backups (older than ${RETENTION_DAYS} days)..."
    
    local deleted_count=0
    find "${BACKUP_DIR}" -type f -mtime +${RETENTION_DAYS} -print0 | while read -r -d '' file; do
        rm -f "$file"
        deleted_count=$((deleted_count + 1))
    done
    
    if [ $deleted_count -gt 0 ]; then
        log "✓ Deleted $deleted_count old backup files"
    else
        log "No old backup files found for deletion"
    fi
}

# Generate backup report
generate_backup_report() {
    local backup_name=$1
    local report_file="${BACKUP_DIR}/backup_report_${backup_name}.txt"
    local start_time=$(date)
    
    log "Generating backup report..."
    
    cat > "$report_file" << EOF
Production Backup Report
========================
Application: ${APP_NAME}
Backup Type: ${BACKUP_TYPE}
Backup Name: ${backup_name}
Started: ${start_time}
Completed: $(date)

Backup Components:
==================

1. Database Backup:
EOF

    # Add database backup info
    if [ -f "${BACKUP_DIR}/database/${backup_name}_database.sql.gz" ]; then
        local db_size=$(du -h "${BACKUP_DIR}/database/${backup_name}_database.sql.gz" | cut -f1)
        local db_checksum=$(sha256sum "${BACKUP_DIR}/database/${backup_name}_database.sql.gz" | cut -d' ' -f1)
        echo "   Status: ✓ Success" >> "$report_file"
        echo "   File: database/${backup_name}_database.sql.gz" >> "$report_file"
        echo "   Size: $db_size" >> "$report_file"
        echo "   SHA256: $db_checksum" >> "$report_file"
    else
        echo "   Status: ✗ Failed" >> "$report_file"
    fi

    cat >> "$report_file" << EOF

2. Application Files Backup:
EOF

    # Add files backup info
    if [ -f "${BACKUP_DIR}/files/${backup_name}_files.tar.gz" ]; then
        local files_size=$(du -h "${BACKUP_DIR}/files/${backup_name}_files.tar.gz" | cut -f1)
        local files_checksum=$(sha256sum "${BACKUP_DIR}/files/${backup_name}_files.tar.gz" | cut -d' ' -f1)
        echo "   Status: ✓ Success" >> "$report_file"
        echo "   File: files/${backup_name}_files.tar.gz" >> "$report_file"
        echo "   Size: $files_size" >> "$report_file"
        echo "   SHA256: $files_checksum" >> "$report_file"
    else
        echo "   Status: ✗ Failed" >> "$report_file"
    fi

    cat >> "$report_file" << EOF

3. Configuration Backup:
EOF

    # Add config backup info
    if [ -f "${BACKUP_DIR}/config/${backup_name}_config.tar.gz" ]; then
        local config_size=$(du -h "${BACKUP_DIR}/config/${backup_name}_config.tar.gz" | cut -f1)
        local config_checksum=$(sha256sum "${BACKUP_DIR}/config/${backup_name}_config.tar.gz" | cut -d' ' -f1)
        echo "   Status: ✓ Success" >> "$report_file"
        echo "   File: config/${backup_name}_config.tar.gz" >> "$report_file"
        echo "   Size: $config_size" >> "$report_file"
        echo "   SHA256: $config_checksum" >> "$report_file"
    else
        echo "   Status: ✗ Failed" >> "$report_file"
    fi

    cat >> "$report_file" << EOF

4. Redis Backup:
EOF

    # Add Redis backup info
    if [ -f "${BACKUP_DIR}/database/${backup_name}_redis.rdb.gz" ]; then
        local redis_size=$(du -h "${BACKUP_DIR}/database/${backup_name}_redis.rdb.gz" | cut -f1)
        local redis_checksum=$(sha256sum "${BACKUP_DIR}/database/${backup_name}_redis.rdb.gz" | cut -d' ' -f1)
        echo "   Status: ✓ Success" >> "$report_file"
        echo "   File: database/${backup_name}_redis.rdb.gz" >> "$report_file"
        echo "   Size: $redis_size" >> "$report_file"
        echo "   SHA256: $redis_checksum" >> "$report_file"
    else
        echo "   Status: ✗ Failed" >> "$report_file"
    fi

    # Total backup size
    local total_size=$(du -sh "${BACKUP_DIR}" | cut -f1)
    
    cat >> "$report_file" << EOF

Backup Summary:
===============
Total Backup Size: $total_size
Backup Location: ${BACKUP_DIR}
Retention Policy: ${RETENTION_DAYS} days
Compression Level: ${COMPRESSION_LEVEL}

Notes:
- Database: Full backup with all triggers and routines
- Files: Excluded logs, cache, vendor, node_modules, .git
- Config: Server configuration files
- Redis: Complete dataset snapshot
- All backups include SHA256 checksums for integrity verification

Generated: $(date)
EOF

    log "✓ Backup report generated: $report_file"
}

# Send notification (optional)
send_notification() {
    local backup_name=$1
    local status=$2
    
    if command -v mail >/dev/null 2>&1; then
        local subject="Laravel Backup ${status} - ${APP_NAME}"
        local body="Backup process ${status} for ${APP_NAME} at $(date)\n\nBackup Name: ${backup_name}\nType: ${BACKUP_TYPE}\nLocation: ${BACKUP_DIR}"
        
        echo -e "$body" | mail -s "$subject" admin@yourdomain.com 2>/dev/null || true
    fi
}

# Main backup execution
main() {
    log "==============================================="
    log "Starting Laravel Production Backup"
    log "Application: ${APP_NAME}"
    log "Backup Type: ${BACKUP_TYPE}"
    log "==============================================="
    
    # Validate backup type
    if [[ ! "$BACKUP_TYPE" =~ ^(full|database|files|config)$ ]]; then
        error "Invalid backup type: $BACKUP_TYPE"
        error "Valid types: full, database, files, config"
        exit 1
    fi
    
    # Setup
    setup_backup_dirs
    local backup_name=$(generate_backup_name)
    
    local success_count=0
    local total_count=0
    
    # Execute backup based on type
    case $BACKUP_TYPE in
        "full")
            total_count=5
            backup_database "$backup_name" && ((success_count++))
            backup_application_files "$backup_name" && ((success_count++))
            backup_configuration "$backup_name" && ((success_count++))
            backup_redis "$backup_name" && ((success_count++))
            backup_log_files "$backup_name" && ((success_count++))
            ;;
        "database")
            total_count=1
            backup_database "$backup_name" && ((success_count++))
            ;;
        "files")
            total_count=1
            backup_application_files "$backup_name" && ((success_count++))
            ;;
        "config")
            total_count=1
            backup_configuration "$backup_name" && ((success_count++))
            ;;
    esac
    
    # Cleanup old backups
    cleanup_old_backups
    
    # Generate report
    generate_backup_report "$backup_name"
    
    # Final status
    if [ $success_count -eq $total_count ]; then
        log "==============================================="
        log "✓ Backup completed successfully!"
        log "Components: $success_count/$total_count"
        log "Backup Name: $backup_name"
        log "Location: ${BACKUP_DIR}"
        log "==============================================="
        send_notification "$backup_name" "SUCCESS"
        exit 0
    else
        error "==============================================="
        error "✗ Backup completed with failures!"
        error "Components: $success_count/$total_count"
        error "Backup Name: $backup_name"
        error "Location: ${BACKUP_DIR}"
        error "==============================================="
        send_notification "$backup_name" "FAILED"
        exit 1
    fi
}

# Show help if requested
if [ "${1:-}" = "help" ] || [ "${1:-}" = "--help" ] || [ "${1:-}" = "-h" ]; then
    echo "Laravel Production Backup Script"
    echo "================================="
    echo
    echo "Usage: $0 [backup_type]"
    echo
    echo "Backup Types:"
    echo "  full     - Complete backup (database + files + config + redis + logs)"
    echo "  database - Database only"
    echo "  files    - Application files only"
    echo "  config   - Configuration files only"
    echo
    echo "Examples:"
    echo "  $0 full        # Full backup"
    echo "  $0 database    # Database backup only"
    echo "  $0 help        # Show this help"
    echo
    echo "Configuration:"
    echo "  APP_PATH: $APP_PATH"
    echo "  BACKUP_DIR: $BACKUP_DIR"
    echo "  RETENTION_DAYS: $RETENTION_DAYS"
    echo "  LOG_FILE: $LOG_FILE"
    echo
    exit 0
fi

# Run main function
main "$@"