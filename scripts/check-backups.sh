#!/bin/bash

###############################################
# Backup Health Check
# 
# Verifies backups are running correctly
###############################################

BACKUP_DIR="/var/backups/wordpress"
ENVIRONMENT="${1:-production}"
ALERT_WEBHOOK="${SLACK_WEBHOOK}"

echo "🔍 Checking backup health for ${ENVIRONMENT}..."

# Check last backup age
LATEST_BACKUP=$(ls -1t "${BACKUP_DIR}" | grep "backup-${ENVIRONMENT}-" | head -1)

if [ -z "$LATEST_BACKUP" ]; then
    echo "❌ No backups found!"
    
    # Send alert
    if [ -n "$ALERT_WEBHOOK" ]; then
        curl -X POST "$ALERT_WEBHOOK" \
            -H 'Content-Type: application/json' \
            -d "{\"text\":\"⚠️ No backups found for ${ENVIRONMENT}!\"}"
    fi
    
    exit 1
fi

# Get backup age
BACKUP_TIME=$(echo "$LATEST_BACKUP" | grep -oE '[0-9]{8}-[0-9]{6}')
BACKUP_TIMESTAMP=$(date -d "${BACKUP_TIME:0:8} ${BACKUP_TIME:9:2}:${BACKUP_TIME:11:2}:${BACKUP_TIME:13:2}" +%s 2>/dev/null || echo "0")
CURRENT_TIMESTAMP=$(date +%s)
AGE_HOURS=$(( ($CURRENT_TIMESTAMP - $BACKUP_TIMESTAMP) / 3600 ))

echo "📅 Latest backup: ${LATEST_BACKUP}"
echo "⏰ Age: ${AGE_HOURS} hours"

# Alert if backup is older than 26 hours (should be daily)
if [ "$AGE_HOURS" -gt 26 ]; then
    echo "⚠️  Backup is older than 26 hours!"
    
    if [ -n "$ALERT_WEBHOOK" ]; then
        curl -X POST "$ALERT_WEBHOOK" \
            -H 'Content-Type: application/json' \
            -d "{\"text\":\"⚠️ ${ENVIRONMENT} backup is ${AGE_HOURS}h old (last: ${LATEST_BACKUP})\"}"
    fi
    
    exit 1
fi

# Check backup integrity
BACKUP_PATH="${BACKUP_DIR}/${LATEST_BACKUP}"

if [ ! -f "${BACKUP_PATH}/database.sql.gz" ]; then
    echo "❌ Database backup missing!"
    exit 1
fi

if [ ! -f "${BACKUP_PATH}/wp-content.tar.gz" ]; then
    echo "❌ Files backup missing!"
    exit 1
fi

# Test database backup
echo "🔍 Testing database backup integrity..."
gunzip -t "${BACKUP_PATH}/database.sql.gz"

if [ $? -eq 0 ]; then
    echo "✅ Database backup is valid"
else
    echo "❌ Database backup is corrupted!"
    exit 1
fi

# Test files backup
echo "🔍 Testing files backup integrity..."
tar -tzf "${BACKUP_PATH}/wp-content.tar.gz" > /dev/null

if [ $? -eq 0 ]; then
    echo "✅ Files backup is valid"
else
    echo "❌ Files backup is corrupted!"
    exit 1
fi

echo ""
echo "✅ All backup checks passed!"
echo ""
echo "📊 Backup stats:"
echo "   Database: $(du -h ${BACKUP_PATH}/database.sql.gz | cut -f1)"
echo "   Files: $(du -h ${BACKUP_PATH}/wp-content.tar.gz | cut -f1)"
echo "   Total: $(du -sh ${BACKUP_PATH} | cut -f1)"