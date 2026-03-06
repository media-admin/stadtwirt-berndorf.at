# Monitoring & Error Tracking

## 📊 Dashboards

### Sentry
- **URL:** https://sentry.io/organizations/your-org/projects/your-project/
- **Purpose:** Error tracking & performance monitoring
- **Alerts:** Email + Slack on new issues

### UptimeRobot
- **URL:** https://uptimerobot.com/dashboard
- **Monitors:**
  - Production: https://your-domain.com (5 min interval)
  - Staging: https://staging.your-domain.com (15 min interval)
- **Alerts:** Email + SMS on downtime

### Better Stack Logs
- **URL:** https://logs.betterstack.com
- **Purpose:** Centralized log aggregation
- **Retention:** 30 days

## 🔔 Notifications

### Slack Channels
- `#deployments` - Deployment notifications
- `#errors` - Critical errors
- `#monitoring` - Uptime alerts

### Alert Thresholds
- **Error Rate:** > 10 errors/minute
- **Page Load:** > 3 seconds
- **Database Query:** > 1 second
- **Memory Usage:** > 80% of limit
- **Uptime:** < 99.5%

## 🔍 Debugging

### Check Logs
```bash
# Production
ssh production "tail -100 /var/www/production/wp-content/debug.log"

# Staging
ssh staging "tail -100 /var/www/staging/wp-content/debug.log"
```

### Check Sentry
1. Go to Sentry dashboard
2. Filter by environment (staging/production)
3. Check error frequency and affected users

### Check Server Resources
```bash
ssh production "top -b -n 1 | head -20"
ssh production "df -h"
```

## 📈 Weekly Review

Every Monday:
- [ ] Review Sentry error trends
- [ ] Check uptime statistics
- [ ] Review slow query logs
- [ ] Check memory usage trends
- [ ] Update this document if needed