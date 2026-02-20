#!/bin/bash

###############################################
# WordPress Backup Script
# 
# Creates backup of database and files
# Uploads to S3-compatible storage
###############################################

set -e  # Exit on error

# Configuration
ENVIRONMENT="${1:-production}"
BACKUP_DIR="/var/backups/wordpress"
RETENTION_DAYS=30

# Load environment-specific config
if [ "$ENVIRONMENT" = "production" ]; then
    WP_PATH="/var/www/production"
    DB_NAME="production_database"
    S3_BUCKET="s3://your-backup-bucket/production"
elif [ "$ENVIRONMENT" = "staging" ]; then
    WP_PATH="/var/www/staging"
    DB_NAME="staging_database"
    S3_BUCKET="s3://your-backup-bucket/staging"
else
    echo "❌ Invalid environment: $ENVIRONMENT"
    echo "Usage: ./backup.sh [production|staging]"
    exit 1
fi

# Create timestamp
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
BACKUP_NAME="backup-${ENVIRONMENT}-${TIMESTAMP}"
BACKUP_PATH="${BACKUP_DIR}/${BACKUP_NAME}"

echo "🔄 Starting backup: ${BACKUP_NAME}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Create backup directory
mkdir -p "${BACKUP_PATH}"
mkdir -p "${BACKUP_DIR}/logs"

# Log file
LOG_FILE="${BACKUP_DIR}/logs/backup-${TIMESTAMP}.log"
exec > >(tee -a "${LOG_FILE}") 2>&1

echo "📅 Date: $(date)"
echo "🌍 Environment: ${ENVIRONMENT}"
echo "📁 Path: ${WP_PATH}"
echo ""

###############################################
# 1. Database Backup
###############################################

echo "💾 Backing up database..."

cd "${WP_PATH}"

# Export database using WP-CLI
wp db export "${BACKUP_PATH}/database.sql" \
    --allow-root \
    --quiet

# Compress database
gzip "${BACKUP_PATH}/database.sql"

echo "✅ Database backup complete: $(du -h ${BACKUP_PATH}/database.sql.gz | cut -f1)"

###############################################
# 2. Files Backup
###############################################

echo ""
echo "📦 Backing up files..."

# Backup wp-content (excluding cache)
tar -czf "${BACKUP_PATH}/wp-content.tar.gz" \
    -C "${WP_PATH}" \
    --exclude='wp-content/cache' \
    --exclude='wp-content/upgrade' \
    --exclude='wp-content/backups' \
    --exclude='wp-content/ai1wm-backups' \
    wp-content

echo "✅ Files backup complete: $(du -h ${BACKUP_PATH}/wp-content.tar.gz | cut -f1)"

###############################################
# 3. Create manifest
###############################################

echo ""
echo "📝 Creating manifest..."

cat > "${BACKUP_PATH}/manifest.json" << EOF
{
  "timestamp": "${TIMESTAMP}",
  "date": "$(date -Iseconds)",
  "environment": "${ENVIRONMENT}",
  "wordpress_version": "$(wp core version --allow-root)",
  "database": {
    "name": "${DB_NAME}",
    "file": "database.sql.gz",
    "size": "$(stat -f%z ${BACKUP_PATH}/database.sql.gz 2>/dev/null || stat -c%s ${BACKUP_PATH}/database.sql.gz)"
  },
  "files": {
    "file": "wp-content.tar.gz",
    "size": "$(stat -f%z ${BACKUP_PATH}/wp-content.tar.gz 2>/dev/null || stat -c%s ${BACKUP_PATH}/wp-content.tar.gz)"
  },
  "total_size": "$(du -sb ${BACKUP_PATH} | cut -f1)"
}
EOF

echo "✅ Manifest created"

###############################################
# 4. Upload to S3
###############################################

echo ""
echo "☁️  Uploading to S3..."

# Check if AWS CLI is available
if command -v aws &> /dev/null; then
    # Create single archive
    cd "${BACKUP_DIR}"
    tar -czf "${BACKUP_NAME}.tar.gz" "${BACKUP_NAME}"
    
    # Upload to S3
    aws s3 cp "${BACKUP_NAME}.tar.gz" "${S3_BUCKET}/${BACKUP_NAME}.tar.gz" \
        --storage-class STANDARD_IA
    
    echo "✅ Upload complete: ${S3_BUCKET}/${BACKUP_NAME}.tar.gz"
    
    # Remove local archive (keep unpacked for quick restore)
    rm "${BACKUP_NAME}.tar.gz"
else
    echo "⚠️  AWS CLI not found - skipping S3 upload"
    echo "   Install: https://aws.amazon.com/cli/"
fi

###############################################
# 5. Cleanup old backups
###############################################

echo ""
echo "🧹 Cleaning up old backups (>${RETENTION_DAYS} days)..."

# Delete local backups older than retention period
find "${BACKUP_DIR}" -maxdepth 1 -type d -name "backup-${ENVIRONMENT}-*" -mtime +${RETENTION_DAYS} -exec rm -rf {} \;

# Delete old S3 backups
if command -v aws &> /dev/null; then
    CUTOFF_DATE=$(date -d "${RETENTION_DAYS} days ago" +%Y%m%d 2>/dev/null || date -v-${RETENTION_DAYS}d +%Y%m%d)
    
    aws s3 ls "${S3_BUCKET}/" | while read -r line; do
        BACKUP_FILE=$(echo "$line" | awk '{print $4}')
        BACKUP_DATE=$(echo "$BACKUP_FILE" | grep -oE '[0-9]{8}')
        
        if [ -n "$BACKUP_DATE" ] && [ "$BACKUP_DATE" -lt "$CUTOFF_DATE" ]; then
            echo "   Deleting old backup: ${BACKUP_FILE}"
            aws s3 rm "${S3_BUCKET}/${BACKUP_FILE}"
        fi
    done
fi

echo "✅ Cleanup complete"

###############################################
# 6. Summary
###############################################

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ Backup completed successfully!"
echo ""
echo "📊 Summary:"
echo "   Location: ${BACKUP_PATH}"
echo "   Database: $(du -h ${BACKUP_PATH}/database.sql.gz | cut -f1)"
echo "   Files: $(du -h ${BACKUP_PATH}/wp-content.tar.gz | cut -f1)"
echo "   Total: $(du -sh ${BACKUP_PATH} | cut -f1)"
echo ""
echo "📝 Log: ${LOG_FILE}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"