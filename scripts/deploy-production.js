#!/usr/bin/env node

const { execSync } = require('child_process');
const readline = require('readline');

const rl = readline.createInterface({
  input: process.stdin,
  output: process.stdout
});

console.log('🚨 PRODUCTION DEPLOYMENT 🚨\n');
console.log('This will deploy to LIVE site!');

rl.question('Type "DEPLOY" to confirm: ', (answer) => {
  if (answer !== 'DEPLOY') {
    console.log('❌ Deployment cancelled.');
    rl.close();
    process.exit(0);
  }
  
  rl.close();
  deployToProduction();
});

function deployToProduction() {
  const PROD_SERVER = 'production';  // SSH config alias
  const PROD_PATH = '/var/www/production';
  const LOCAL_THEME = './cms/wp-content/themes/custom-theme';
  const LOCAL_PLUGINS = './cms/wp-content/mu-plugins';
  
  try {
    // 1. Build assets
    console.log('\n📦 Building production assets...');
    execSync('npm run build:production', { stdio: 'inherit' });
    
    // 2. Create pre-deployment backup ← NEU!
    console.log('\n💾 Creating pre-deployment backup...');
    execSync(`ssh ${PROD_SERVER} "/path/to/scripts/pre-deploy-backup.sh production"`, {
      stdio: 'inherit'
    });
    
    // 3. Sync theme
    console.log('\n📤 Syncing theme to production...');
    execSync(`rsync -avz --delete ${LOCAL_THEME}/ ${PROD_SERVER}:${PROD_PATH}/wp-content/themes/custom-theme/`, {
      stdio: 'inherit'
    });
    
    // 4. Sync plugins
    console.log('\n📤 Syncing plugins to production...');
    execSync(`rsync -avz --delete ${LOCAL_PLUGINS}/ ${PROD_SERVER}:${PROD_PATH}/wp-content/mu-plugins/`, {
      stdio: 'inherit'
    });
    
    // 5. Clear cache
    console.log('\n🧹 Clearing cache on production...');
    execSync(`ssh ${PROD_SERVER} "cd ${PROD_PATH} && wp cache flush"`, {
      stdio: 'inherit'
    });
    
    console.log('\n✅ Production deployment complete!');
    console.log('🌐 View: https://your-domain.com\n');
    
  } catch (error) {
    console.error('\n❌ Deployment failed:', error.message);
    console.log('\n🔄 To rollback, run:');
    console.log('   ssh production');
    console.log('   /path/to/scripts/restore.sh production [backup-name]\n');
    process.exit(1);
  }
}