#!/bin/bash

# Setup daily backup cron job

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_SCRIPT="$SCRIPT_DIR/backup.sh"

# Add cron job (daily at 2 AM)
(crontab -l 2>/dev/null; echo "0 2 * * * $BACKUP_SCRIPT >> $SCRIPT_DIR/backup.log 2>&1") | crontab -

echo "✓ Cron job setup complete"
echo "Backups will run daily at 2:00 AM"