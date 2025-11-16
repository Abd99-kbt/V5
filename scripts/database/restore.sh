#!/bin/bash

# ===============================================
# Production Database Restore Script
# Supports MySQL and PostgreSQL
# ===============================================

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR/../..")"
BACKUP_DIR="$PROJECT_ROOT/storage/backups/database"
LOG_FILE="$PROJECT_ROOT/storage/logs/restore.log"

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

# Function to log messages
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Function to show usage
usage() {
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  -f, --file BACKUP_FILE    Specific backup file to restore"
    echo "  -d, --database DATABASE   Database name (default: $DB_DATABASE)"
    echo "  -h, --host HOST          Database host (default: $DB_HOST)"
    echo "  -p, --port PORT          Database port (default: $DB_PORT)"
    echo "  -u, --user USERNAME      Database username (default: $DB_USERNAME)"
    echo "  --force                  Skip confirmation prompts"
    echo "  --list                   List available backups"
    echo "  --verify-only            Only verify backup integrity without restoring"
    echo "  --help                   Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 --list"
    echo "  $0 -f mysql_backup_laravel_20251106_040000.sql.gz"
    echo "  $0 -f postgres_backup_laravel_20251106_040000.sql.gz --verify-only"
}

# Function to list available backups
list_backups() {
    log "📋 Available backups:"
    echo ""
    
    if [ ! -d "$BACKUP_DIR" ]; then
        log "❌ Backup directory not found: $BACKUP_DIR"
        return 1
    fi
    
    local count=0
    for file in "$BACKUP_DIR"/*.sql.gz "$BACKUP_DIR"/*.sql "$BACKUP_DIR"/*.tar.gz; do
        if [ -f "$file" ]; then
            local filename=$(basename "$file")
            local filesize=$(du -h "$file" | cut -f1)
            local filedate=$(date -r "$file" '+%Y-%m-%d %H:%M:%S')
            
            echo "[$((++count))] $filename"
            echo "    📦 Size: $filesize"
            echo "    📅 Date: $filedate"
            echo ""
        fi
    done
    
    if [ $count -eq 0 ]; then
        log "❌ No backup files found in $BACKUP_DIR"
        return 1
    fi
}

# Function to verify backup integrity
verify_backup() {
    local backup_file="$1"
    
    log "🔍 Verifying backup integrity: $backup_file"
    
    # Check if file exists
    if [ ! -f "$backup_file" ]; then
        log "❌ Backup file not found: $backup_file"
        return 1
    fi
    
    # Check file size
    local file_size=$(stat -f%z "$backup_file" 2>/dev/null || stat -c%s "$backup_file" 2>/dev/null || echo "0")
    if [ "$file_size" -eq 0 ]; then
        log "❌ Backup file is empty: $backup_file"
        return 1
    fi
    
    # Verify compressed file integrity
    if [[ "$backup_file" == *.gz ]]; then
        if ! gunzip -t "$backup_file" 2>/dev/null; then
            log "❌ Compressed backup file is corrupted: $backup_file"
            return 1
        fi
    fi
    
    # Test database connection
    case "$DB_CONNECTION" in
        mysql|mariadb)
            if [ -n "$DB_PASSWORD" ]; then
                mysqladmin --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME" --password="$DB_PASSWORD" ping >/dev/null 2>&1 || {
                    log "❌ Cannot connect to MySQL database"
                    return 1
                }
            else
                mysqladmin --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME" ping >/dev/null 2>&1 || {
                    log "❌ Cannot connect to MySQL database"
                    return 1
                }
            fi
            ;;
        pgsql|postgres|postgresql)
            if [ -n "$DB_PASSWORD" ]; then
                PGPASSWORD="$DB_PASSWORD" psql --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USERNAME" -d postgres -c "SELECT 1" >/dev/null 2>&1 || {
                    log "❌ Cannot connect to PostgreSQL database"
                    return 1
                }
            else
                psql --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USERNAME" -d postgres -c "SELECT 1" >/dev/null 2>&1 || {
                    log "❌ Cannot connect to PostgreSQL database"
                    return 1
                }
            fi
            ;;
    esac
    
    log "✅ Backup integrity verification passed"
    return 0
}

# Function to restore MySQL
restore_mysql() {
    local backup_file="$1"
    local temp_file="/tmp/restore_mysql_$$.sql"
    
    log "🔄 Starting MySQL restore..."
    log "Backup file: $backup_file"
    log "Target database: $DB_DATABASE"
    
    # Extract compressed file if needed
    if [[ "$backup_file" == *.gz ]]; then
        log "📦 Extracting compressed backup..."
        if ! gunzip -c "$backup_file" > "$temp_file"; then
            log "❌ Failed to extract backup file"
            return 1
        fi
    else
        cp "$backup_file" "$temp_file"
    fi
    
    # Create database if it doesn't exist
    log "🏗️  Ensuring database exists..."
    if [ -n "$DB_PASSWORD" ]; then
        mysql --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME" --password="$DB_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS \`$DB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || {
            log "❌ Failed to create database"
            rm -f "$temp_file"
            return 1
        }
    else
        mysql --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME" -e "CREATE DATABASE IF NOT EXISTS \`$DB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || {
            log "❌ Failed to create database"
            rm -f "$temp_file"
            return 1
        }
    fi
    
    # Restore database
    log "📥 Restoring database..."
    if [ -n "$DB_PASSWORD" ]; then
        mysql --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME" --password="$DB_PASSWORD" \
              --database="$DB_DATABASE" < "$temp_file"
    else
        mysql --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME" \
              --database="$DB_DATABASE" < "$temp_file"
    fi
    
    if [ $? -eq 0 ]; then
        log "✅ MySQL restore completed successfully"
        
        # Run integrity checks
        log "🔍 Running database integrity checks..."
        if [ -n "$DB_PASSWORD" ]; then
            mysqlcheck --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME" --password="$DB_PASSWORD" \
                      --optimize --check --auto-repair "$DB_DATABASE"
        else
            mysqlcheck --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME" \
                      --optimize --check --auto-repair "$DB_DATABASE"
        fi
    else
        log "❌ MySQL restore failed"
        rm -f "$temp_file"
        return 1
    fi
    
    # Clean up
    rm -f "$temp_file"
}

# Function to restore PostgreSQL
restore_postgresql() {
    local backup_file="$1"
    local temp_file="/tmp/restore_postgres_$$.sql"
    
    log "🔄 Starting PostgreSQL restore..."
    log "Backup file: $backup_file"
    log "Target database: $DB_DATABASE"
    
    # Extract compressed file if needed
    if [[ "$backup_file" == *.gz ]]; then
        log "📦 Extracting compressed backup..."
        if ! gunzip -c "$backup_file" > "$temp_file"; then
            log "❌ Failed to extract backup file"
            return 1
        fi
    else
        cp "$backup_file" "$temp_file"
    fi
    
    # Drop and recreate database
    log "🏗️  Recreating database..."
    if [ -n "$DB_PASSWORD" ]; then
        PGPASSWORD="$DB_PASSWORD" psql --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USERNAME" -d postgres -c "DROP DATABASE IF EXISTS $DB_DATABASE;" 2>/dev/null
        PGPASSWORD="$DB_PASSWORD" psql --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USERNAME" -d postgres -c "CREATE DATABASE $DB_DATABASE WITH ENCODING 'UTF8' LC_COLLATE='en_US.utf8' LC_CTYPE='en_US.utf8';" 2>/dev/null || {
            log "❌ Failed to create database"
            rm -f "$temp_file"
            return 1
        }
    else
        psql --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USERNAME" -d postgres -c "DROP DATABASE IF EXISTS $DB_DATABASE;" 2>/dev/null
        psql --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USERNAME" -d postgres -c "CREATE DATABASE $DB_DATABASE WITH ENCODING 'UTF8' LC_COLLATE='en_US.utf8' LC_CTYPE='en_US.utf8';" 2>/dev/null || {
            log "❌ Failed to create database"
            rm -f "$temp_file"
            return 1
        }
    fi
    
    # Restore database
    log "📥 Restoring database..."
    if [ -n "$DB_PASSWORD" ]; then
        PGPASSWORD="$DB_PASSWORD" psql --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USERNAME" \
             --dbname="$DB_DATABASE" --file="$temp_file" --verbose
    else
        psql --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USERNAME" \
             --dbname="$DB_DATABASE" --file="$temp_file" --verbose
    fi
    
    if [ $? -eq 0 ]; then
        log "✅ PostgreSQL restore completed successfully"
        
        # Update sequence values
        log "🔄 Updating sequence values..."
        if [ -n "$DB_PASSWORD" ]; then
            PGPASSWORD="$DB_PASSWORD" psql --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USERNAME" \
                 --dbname="$DB_DATABASE" -c "SELECT setval(pg_get_serial_sequence('users', 'id'), (SELECT MAX(id) FROM users));" 2>/dev/null
            PGPASSWORD="$DB_PASSWORD" psql --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USERNAME" \
                 --dbname="$DB_DATABASE" -c "SELECT setval(pg_get_serial_sequence('orders', 'id'), (SELECT MAX(id) FROM orders));" 2>/dev/null
        else
            psql --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USERNAME" \
                 --dbname="$DB_DATABASE" -c "SELECT setval(pg_get_serial_sequence('users', 'id'), (SELECT MAX(id) FROM users));" 2>/dev/null
            psql --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USERNAME" \
                 --dbname="$DB_DATABASE" -c "SELECT setval(pg_get_serial_sequence('orders', 'id'), (SELECT MAX(id) FROM orders));" 2>/dev/null
        fi
    else
        log "❌ PostgreSQL restore failed"
        rm -f "$temp_file"
        return 1
    fi
    
    # Clean up
    rm -f "$temp_file"
}

# Function to restore application files
restore_application() {
    local backup_file="$1"
    
    log "🔄 Starting application files restore..."
    log "Backup file: $backup_file"
    
    # Create backup of current application
    local current_backup="$PROJECT_ROOT/storage/backups/app_current_$(date +%Y%m%d_%H%M%S).tar.gz"
    log "💾 Creating backup of current application..."
    tar -czf "$current_backup" \
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
    
    # Restore application files
    log "📥 Restoring application files..."
    if tar -xzf "$backup_file" -C "$PROJECT_ROOT" --strip-components=0; then
        log "✅ Application files restore completed successfully"
        log "💾 Current application backed up to: $current_backup"
        
        # Fix permissions
        chmod -R 755 "$PROJECT_ROOT/storage"
        chmod -R 644 "$PROJECT_ROOT/.env"*
        
        log "🔧 File permissions fixed"
    else
        log "❌ Application files restore failed"
        return 1
    fi
}

# Function to confirm restore
confirm_restore() {
    if [ "$FORCE_RESTORE" = "true" ]; then
        return 0
    fi
    
    echo ""
    echo "⚠️  WARNING: This will replace the current database with the backup!"
    echo "📂 Backup file: $1"
    echo "🗄️  Database: $DB_DATABASE"
    echo "📅 Backup date: $(date -r "$1" '+%Y-%m-%d %H:%M:%S')"
    echo ""
    read -p "Are you sure you want to continue? (yes/no): " -r
    if [[ ! $REPLY =~ ^[Yy]es$ ]]; then
        log "🚫 Restore cancelled by user"
        exit 0
    fi
}

# Parse command line arguments
BACKUP_FILE=""
VERIFY_ONLY=false
FORCE_RESTORE=false

while [[ $# -gt 0 ]]; do
    case $1 in
        -f|--file)
            BACKUP_FILE="$2"
            shift 2
            ;;
        -d|--database)
            DB_DATABASE="$2"
            shift 2
            ;;
        -h|--host)
            DB_HOST="$2"
            shift 2
            ;;
        -p|--port)
            DB_PORT="$2"
            shift 2
            ;;
        -u|--user)
            DB_USERNAME="$2"
            shift 2
            ;;
        --force)
            FORCE_RESTORE="true"
            shift
            ;;
        --list)
            list_backups
            exit 0
            ;;
        --verify-only)
            VERIFY_ONLY="true"
            shift
            ;;
        --help)
            usage
            exit 0
            ;;
        *)
            echo "❌ Unknown option: $1"
            usage
            exit 1
            ;;
    esac
done

# Main execution
main() {
    log "🚀 Starting database restore process..."
    log "Connection type: $DB_CONNECTION"
    
    # Create log directory
    mkdir -p "$(dirname "$LOG_FILE")"
    
    # If backup file not specified, show list
    if [ -z "$BACKUP_FILE" ]; then
        echo ""
        echo "📋 Available backup files:"
        list_backups
        echo ""
        read -p "Enter backup file name (or full path): " BACKUP_FILE
    fi
    
    # Resolve backup file path
    if [[ "$BACKUP_FILE" != /* ]]; then
        BACKUP_FILE="$BACKUP_DIR/$BACKUP_FILE"
    fi
    
    # Verify backup integrity
    if ! verify_backup "$BACKUP_FILE"; then
        log "❌ Backup verification failed"
        exit 1
    fi
    
    if [ "$VERIFY_ONLY" = "true" ]; then
        log "✅ Backup verification completed (restore skipped)"
        exit 0
    fi
    
    # Confirm restore
    confirm_restore "$BACKUP_FILE"
    
    # Start restore process
    case "$BACKUP_FILE" in
        *.tar.gz)
            # Application files backup
            restore_application "$BACKUP_FILE"
            ;;
        *.sql.gz|*.sql)
            # Database backup
            case "$DB_CONNECTION" in
                mysql|mariadb)
                    restore_mysql "$BACKUP_FILE"
                    ;;
                pgsql|postgres|postgresql)
                    restore_postgresql "$BACKUP_FILE"
                    ;;
                *)
                    log "❌ Unsupported database connection type: $DB_CONNECTION"
                    exit 1
                    ;;
            esac
            ;;
        *)
            log "❌ Unsupported backup file format: $BACKUP_FILE"
            exit 1
            ;;
    esac
    
    log "🎉 Restore process completed successfully!"
    log "📁 Backup file: $BACKUP_FILE"
}

# Run main function
main "$@"