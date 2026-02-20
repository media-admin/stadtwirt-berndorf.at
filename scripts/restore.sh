#!/bin/bash

###############################################
# WordPress Restore Script
###############################################

set -e

# Check arguments
if [ $# -lt 2 ]; then
    echo "Usage: ./restore.sh [environment] [backup-name]"
    echo ""
    echo "Example:"
    echo "  ./restore.sh production backup-production-20260209-120000"
    echo ""
    echo "Available backups:"
    ls -1 /var/backups/wordpress/ | grep backup- | tail -10
    exit 1
fi

ENVIRONMENT="$1"
BACKUP_NAME="$2"
BACKUP_DIR="/var/backups/wordpress"
BACKUP_PATH="${BACKUP_DIR}/${BACKUP_NAME}"

# Load environment config
if [ "$ENVIRONMENT" = "production" ]; then
    WP_PATH="/var/www/production"
elif [ "$ENVIRONMENT" = "staging" ]; then
    WP_PATH="/var/www/staging"
else
    echo "❌ Invalid environment: $ENVIRONMENT"
    exit 1
fi

# Check if backup exists
if [ ! -d "$BACKUP_PATH" ]; then
    echo "❌ Backup not found: $BACKUP_PATH"
    echo ""
    echo "Available backups:"
    ls -1 "${BACKUP_DIR}" | grep "backup-${ENVIRONMENT}-"
    exit 1
fi

echo "⚠️  WARNING: This will OVERWRITE current ${ENVIRONMENT} data!"
echo ""
echo "Backup to restore: ${BACKUP_NAME}"
echo "Target: ${WP_PATH}"
echo ""
read -p "Type 'RESTORE' to confirm: " CONFIRM

if [ "$CONFIRM" != "RESTORE" ]; then
    echo "❌ Restore cancelled"
    exit 0
fi

echo ""
echo "🔄 Starting restore..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

###############################################
# 1. Create safety backup
###############################################

echo "💾 Creating safety backup of current state..."
SAFETY_BACKUP="${BACKUP_DIR}/safety-backup-$(date +%Y%m%d-%H%M%S)"
mkdir -p "${SAFETY_BACKUP}"

cd "${WP_PATH}"
wp db export "${SAFETY_BACKUP}/database.sql" --allow-root --quiet

echo "✅ Safety backup created: ${SAFETY_BACKUP}"

###############################################
# 2. Restore Database
###############################################

echo ""
echo "💾 Restoring database..."

cd "${WP_PATH}"

# Drop all tables
wp db reset --yes --allow-root --quiet

# Import backup
gunzip -c "${BACKUP_PATH}/database.sql.gz" | wp db import - --allow-root

echo "✅ Database restored"

###############################################
# 3. Restore Files
###############################################

echo ""
echo "📦 Restoring files..."

# Backup current uploads (just in case)
if [ -d "${WP_PATH}/wp-content/uploads" ]; then
    mv "${WP_PATH}/wp-content/uploads" "${SAFETY_BACKUP}/uploads-backup"
fi

# Extract backup
tar -xzf "${BACKUP_PATH}/wp-content.tar.gz" -C "${WP_PATH}"

echo "✅ Files restored"

###############################################
# 4. Fix Permissions
###############################################

echo ""
echo "🔐 Fixing permissions..."

chown -R www-data:www-data "${WP_PATH}/wp-content"
chmod -R 755 "${WP_PATH}/wp-content"

echo "✅ Permissions fixed"

###############################################
# 5. Clear Cache
###############################################

echo ""
echo "🧹 Clearing cache..."

cd "${WP_PATH}"
wp cache flush --allow-root --quiet

echo "✅ Cache cleared"

###############################################
# Summary
###############################################

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ Restore completed successfully!"
echo ""
echo "📊 Restored from: ${BACKUP_NAME}"
echo "🔒 Safety backup: ${SAFETY_BACKUP}"
echo ""
echo "⚠️  Remember to:"
echo "   1. Test the site thoroughly"
echo "   2. Check error logs"
echo "   3. Verify user access"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"