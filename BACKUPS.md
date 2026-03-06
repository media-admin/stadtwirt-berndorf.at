# Backup & Restore

## 📦 Backup Strategy

### Automated Backups
- **Daily:** 2 AM (Production), 3 AM (Staging)
- **Pre-Deployment:** Before every production deploy
- **Retention:** 30 days local, 30 days S3
- **Storage:** Local + AWS S3 (Standard-IA)

### What's Backed Up
- Database (complete SQL dump)
- wp-content/ (themes, plugins, uploads)
- Manifest (metadata, versions)

### What's NOT Backed Up
- WordPress core files
- Cache files
- Temporary files

## 🔄 Creating Manual Backup

### Production
```bash
ssh production
sudo /path/to/scripts/backup.sh production
```

### Staging
```bash
ssh staging
sudo /path/to/scripts/backup.sh staging
```

## 🔙 Restoring from Backup

### 1. List Available Backups
```bash
ssh production
ls -lh /var/backups/wordpress/ | grep backup-production-
```

### 2. Restore
```bash
sudo /path/to/scripts/restore.sh production backup-production-20260209-120000
```

**⚠️ WARNING:** This will overwrite current data!

### 3. Verify
```bash
# Check site
curl -I https://your-domain.com

# Check WordPress version
cd /var/www/production
wp core version

# Check database
wp db check
```

## ☁️ S3 Backups

### List S3 Backups
```bash
aws s3 ls s3://your-backup-bucket/production/
```

### Download from S3
```bash
aws s3 cp s3://your-backup-bucket/production/backup-production-20260209-120000.tar.gz /tmp/

cd /tmp
tar -xzf backup-production-20260209-120000.tar.gz
```

### Restore from S3
```bash
# Download and extract
aws s3 cp s3://your-backup-bucket/production/backup-production-20260209-120000.tar.gz /var/backups/wordpress/
cd /var/backups/wordpress
tar -xzf backup-production-20260209-120000.tar.gz

# Restore
sudo /path/to/scripts/restore.sh production backup-production-20260209-120000
```

## 🔍 Monitoring

### Check Backup Health
```bash
ssh production
sudo /path/to/scripts/check-backups.sh production
```

### View Backup Logs
```bash
tail -50 /var/log/backup-production.log
```

### Backup Alerts
Configured in Slack channel: `#monitoring`

## 📊 Backup Sizes

Typical backup sizes:
- Database: ~50 MB (compressed)
- Files: ~500 MB (compressed)
- Total: ~550 MB per backup
- Monthly: ~16 GB (30 days × 550 MB)

## 🆘 Emergency Procedures

### Quick Restore (Last Backup)
```bash
ssh production
LATEST=$(ls -1t /var/backups/wordpress/ | grep backup-production- | head -1)
sudo /path/to/scripts/restore.sh production $LATEST
```

### Restore to Different Server
```bash
# On source server
cd /var/backups/wordpress
tar -czf emergency-backup.tar.gz backup-production-XXXXXXXX-XXXXXX

# Transfer to new server
scp emergency-backup.tar.gz newserver:/tmp/

# On new server
cd /tmp
tar -xzf emergency-backup.tar.gz
sudo /path/to/scripts/restore.sh production backup-production-XXXXXXXX-XXXXXX
```

## 📅 Maintenance

### Monthly Tasks
- [ ] Verify S3 backups exist
- [ ] Test restore procedure
- [ ] Review backup sizes
- [ ] Check disk space
- [ ] Update retention policy if needed

### Quarterly Tasks
- [ ] Full disaster recovery test
- [ ] Review and update scripts
- [ ] Audit backup integrity