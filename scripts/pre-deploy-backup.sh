#!/bin/bash

###############################################
# Pre-Deployment Backup
# 
# Creates backup before deployment
###############################################

set -e

ENVIRONMENT="${1:-production}"

echo "🔄 Creating pre-deployment backup for ${ENVIRONMENT}..."

# Run backup script
/path/to/scripts/backup.sh "${ENVIRONMENT}"

# Tag backup as pre-deployment
LATEST_BACKUP=$(ls -1t /var/backups/wordpress/ | grep "backup-${ENVIRONMENT}-" | head -1)

if [ -n "$LATEST_BACKUP" ]; then
    echo "pre-deployment" > "/var/backups/wordpress/${LATEST_BACKUP}/deployment-tag.txt"
    echo "✅ Pre-deployment backup tagged: ${LATEST_BACKUP}"
fi

exit 0