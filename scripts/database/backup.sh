#!/bin/bash

# ===============================================
# Production Database Backup Script
# Supports MySQL and PostgreSQL
# ===============================================

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR/../..")"
BACKUP_DIR="$PROJECT_ROOT/storage/backups/database"
LOG_FILE="$PROJECT_ROOT/storage/logs/backup.log"
DATE=$(date +"%Y%m%d_%H%M%S")

# Load environment variables
if [ -f "$PROJECT_ROOT/.env" ]; then
    export $(grep -v '^#' "$PROJECT_ROOT/.env" | xargs)
elif [ -f "$PROJECT_ROOT/.env.production" ]; then
    export $(grep -v '^#' "$PROJECT_ROOT/.env.production" | xargs)
else
    echo "❌ No .env file found"
    exit 1
fi

# Database configuration
DB_CONNECTION=${DB_CONNECTION:-mysql}
DB_HOST=${DB_HOST:-127.0.0.1}
DB_PORT=${DB_PORT:-3306}
DB_DATABASE=${DB_DATABASE:-laravel}
DB_USERNAME=${DB_USERNAME:-root}
DB_PASSWORD=${DB_PASSWORD}

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Function to log messages
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Function to backup MySQL
backup_mysql() {
    local backup_file="$BACKUP_DIR/mysql_backup_${DB_DATABASE}_${DATE}.sql"
    local compressed_file="$backup_file.gz"
    
    log "Starting MySQL backup..."
    log "Database: $DB_DATABASE"
    log "Host: $DB_HOST:$DB_PORT"
    
    # Create MySQL backup with compression
    if [ -n "$DB_PASSWORD" ]; then
        mysqldump --host="$DB_HOST" --port="$DB_PORT" \
                  --user="$DB_USERNAME" --password="$DB_PASSWORD" \
                  --single-transaction --routines --triggers \
                  --events --hex-blob --compress \
                  "$DB_DATABASE" | gzip > "$compressed_file"
    else
        mysqldump --host="$DB_HOST" --port="$DB_PORT" \
                  --user="$DB_USERNAME" \
                  --single-transaction --routines --triggers \
                  --events --hex-blob --compress \
                  "$DB_DATABASE" | gzip > "$compressed_file"
    fi
    
    if [ $? -eq 0 ]; then
        local file_size=$(du -h "$compressed_file" | cut -f1)
        log "✅ MySQL backup completed successfully"
        log "📦 File: $compressed_file ($file_size)"
        
        # Verify backup integrity
        if gunzip -t "$compressed_file"; then
            log "✅ Backup integrity verified"
        else
            log "❌ Backup integrity check failed"
            exit 1
        fi
        
        # Keep only last 7 days of backups
        find "$BACKUP_DIR" -name "mysql_backup_*.sql.gz" -mtime +7 -delete
        log "🧹 Cleaned old backups (kept last 7 days)"
        
    else
        log "❌ MySQL backup failed"
        exit 1
    fi
}

# Function to backup PostgreSQL
backup_postgresql() {
    local backup_file="$BACKUP_DIR/postgres_backup_${DB_DATABASE}_${DATE}.sql"
    local compressed_file="$backup_file.gz"
    
    log "Starting PostgreSQL backup..."
    log "Database: $DB_DATABASE"
    log "Host: $DB_HOST:$DB_PORT"
    
    # Create PostgreSQL backup with compression
    if [ -n "$DB_PASSWORD" ]; then
        PGPASSWORD="$DB_PASSWORD" pg_dump \
            --host="$DB_HOST" --port="$DB_PORT" \
            --username="$DB_USERNAME" \
            --verbose --format=custom --compress=9 \
            "$DB_DATABASE" > "$backup_file"
    else
        pg_dump \
            --host="$DB_HOST" --port="$DB_PORT" \
            --username="$DB_USERNAME" \
            --verbose --format=custom --compress=9 \
            "$DB_DATABASE" > "$backup_file"
    fi
    
    if [ $? -eq 0 ]; then
        local file_size=$(du -h "$backup_file" | cut -f1)
        log "✅ PostgreSQL backup completed successfully"
        log "📦 File: $backup_file ($file_size)"
        
        # Compress the backup
        gzip "$backup_file"
        local compressed_size=$(du -h "$compressed_file" | cut -f1)
        log "📦 Compressed file: $compressed_file ($compressed_size)"
        
        # Keep only last 7 days of backups
        find "$BACKUP_DIR" -name "postgres_backup_*.sql*" -mtime +7 -delete
        log "🧹 Cleaned old backups (kept last 7 days)"
        
    else
        log "❌ PostgreSQL backup failed"
        exit 1
    fi
}

# Function to create application backup
backup_application() {
    local app_backup="$BACKUP_DIR/app_files_${DATE}.tar.gz"
    
    log "Starting application files backup..."
    
    # Backup important application files
    tar -czf "$app_backup" \
        --exclude='storage/app/private/*' \
        --exclude='storage/framework/cache/*' \
        --exclude='storage/framework/sessions/*' \
        --exclude='storage/framework/views/*' \
        --exclude='storage/logs/*' \
        --exclude='node_modules/*' \
        --exclude='vendor/*' \
        --exclude='bootstrap/cache/*' \
        -C "$PROJECT_ROOT" \
        app config database resources routes public storage .env* \
        composer.json package.json phpunit.xml README.md
    
    if [ $? -eq 0 ]; then
        local file_size=$(du -h "$app_backup" | cut -f1)
        log "✅ Application backup completed successfully"
        log "📦 File: $app_backup ($file_size)"
        
        # Keep only last 7 days of app backups
        find "$BACKUP_DIR" -name "app_files_*.tar.gz" -mtime +7 -delete
        log "🧹 Cleaned old app backups (kept last 7 days)"
        
    else
        log "❌ Application backup failed"
        exit 1
    fi
}

# Function to verify disk space
verify_disk_space() {
    local required_space_mb=1000  # 1GB required
    local available_space=$(df "$BACKUP_DIR" | awk 'NR==2 {print $4}')
    
    if [ "$available_space" -lt "$((required_space_mb * 1024))" ]; then
        log "❌ Insufficient disk space. Available: ${available_space}KB, Required: ${required_space_mb}MB"
        exit 1
    else
        local available_mb=$((available_space / 1024))
        log "✅ Sufficient disk space available: ${available_mb}MB"
    fi
}

# Function to send notification
send_notification() {
    local status=$1
    local message=$2
    
    # Send notification if webhook URL is configured
    if [ -n "$BACKUP_WEBHOOK_URL" ]; then
        curl -X POST "$BACKUP_WEBHOOK_URL" \
             -H "Content-Type: application/json" \
             -d "{\"status\":\"$status\",\"message\":\"$message\",\"timestamp\":\"$(date -Iseconds)\"}" \
             -s >/dev/null
    fi
    
    # Send email notification if configured
    if [ -n "$BACKUP_EMAIL" ] && command -v mail >/dev/null 2>&1; then
        echo "$message" | mail -s "Database Backup $status" "$BACKUP_EMAIL"
    fi
}

# Main execution
main() {
    log "🚀 Starting database backup process..."
    log "Connection type: $DB_CONNECTION"
    
    # Verify prerequisites
    verify_disk_space
    
    # Create backups based on database type
    case "$DB_CONNECTION" in
        mysql|mariadb)
            # Check if mysqldump is available
            if ! command -v mysqldump >/dev/null 2>&1; then
                log "❌ mysqldump not found. Please install MySQL client tools."
                send_notification "failed" "mysqldump not available"
                exit 1
            fi
            backup_mysql
            ;;
        pgsql|postgres|postgresql)
            # Check if pg_dump is available
            if ! command -v pg_dump >/dev/null 2>&1; then
                log "❌ pg_dump not found. Please install PostgreSQL client tools."
                send_notification "failed" "pg_dump not available"
                exit 1
            fi
            backup_postgresql
            ;;
        *)
            log "❌ Unsupported database connection type: $DB_CONNECTION"
            send_notification "failed" "Unsupported database type: $DB_CONNECTION"
            exit 1
            ;;
    esac
    
    # Always backup application files
    backup_application
    
    # Create backup manifest
    local manifest_file="$BACKUP_DIR/backup_manifest_${DATE}.json"
    cat > "$manifest_file" << EOF
{
    "timestamp": "$(date -Iseconds)",
    "database": {
        "connection": "$DB_CONNECTION",
        "database": "$DB_DATABASE",
        "host": "$DB_HOST",
        "port": "$DB_PORT"
    },
    "files": [
EOF
    
    # Add backup files to manifest
    local file_count=0
    for file in "$BACKUP_DIR"/*_${DATE}.*; do
        if [ -f "$file" ]; then
            if [ $file_count -gt 0 ]; then
                echo "," >> "$manifest_file"
            fi
            local filename=$(basename "$file")
            local filesize=$(stat -f%z "$file" 2>/dev/null || stat -c%s "$file" 2>/dev/null || echo "0")
            echo "        {\"name\": \"$filename\", \"size\": $filesize, \"type\": \"$([ "$filename" = "app_files_"*"_"*".tar.gz" ] && echo "application" || echo "database")\"}" >> "$manifest_file"
            ((file_count++))
        fi
    done
    
    cat >> "$manifest_file" << EOF

    ],
    "status": "success"
}
EOF
    
    log "📋 Backup manifest created: $manifest_file"
    
    # Send success notification
    send_notification "success" "Database and application backup completed successfully"
    
    log "🎉 Backup process completed successfully!"
    log "📁 All backup files are stored in: $BACKUP_DIR"
}

# Error handling
trap 'log "❌ Backup process failed with exit code $?"; send_notification "failed" "Backup process failed with exit code $?"; exit 1' ERR

# Run main function
main "$@"