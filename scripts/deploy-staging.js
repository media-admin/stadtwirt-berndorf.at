#!/usr/bin/env node

/**
 * Deploy to Staging Server
 */

const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

console.log('🚀 Starting Staging Deployment...\n');

// Configuration
const STAGING_SERVER = 'user@staging.your-domain.com';
const STAGING_PATH = '/var/www/staging';
const LOCAL_THEME = './cms/wp-content/themes/custom-theme';
const LOCAL_PLUGINS = './cms/wp-content/mu-plugins';

try {
  // 1. Build assets
  console.log('📦 Building assets...');
  execSync('npm run build:staging', { stdio: 'inherit' });
  
  // 2. Sync theme
  console.log('\n📤 Syncing theme to staging...');
  execSync(`rsync -avz --delete ${LOCAL_THEME}/ ${STAGING_SERVER}:${STAGING_PATH}/wp-content/themes/custom-theme/`, {
    stdio: 'inherit'
  });
  
  // 3. Sync plugins
  console.log('\n📤 Syncing plugins to staging...');
  execSync(`rsync -avz --delete ${LOCAL_PLUGINS}/ ${STAGING_SERVER}:${STAGING_PATH}/wp-content/mu-plugins/`, {
    stdio: 'inherit'
  });
  
  // 4. Clear cache on staging
  console.log('\n🧹 Clearing cache on staging...');
  execSync(`ssh ${STAGING_SERVER} "cd ${STAGING_PATH} && wp cache flush"`, {
    stdio: 'inherit'
  });
  
  console.log('\n✅ Staging deployment complete!');
  console.log('🌐 View: https://staging.your-domain.com\n');
  
} catch (error) {
  console.error('\n❌ Deployment failed:', error.message);
  process.exit(1);
}