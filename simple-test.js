const { chromium } = require('playwright');

(async () => {
  try {
    console.log('Starting simple test...');
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    
    // Navigate to login
    await page.goto('http://localhost:8080/wp-admin/');
    
    // Login
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
    await page.click('#wp-submit');
    await page.waitForSelector('.wrap', { timeout: 10000 });
    
    console.log('Logged in, checking plugin status...');
    
    // Go to plugins page to see if the plugin is active
    await page.goto('http://localhost:8080/wp-admin/plugins.php');
    await page.waitForSelector('.plugins');
    
    const pluginRow = await page.$('tr[data-slug="wp-content-flow"]');
    if (pluginRow) {
      const isActive = await pluginRow.$('.activate');
      if (isActive) {
        console.log('Plugin is INACTIVE');
      } else {
        console.log('Plugin is ACTIVE');
      }
    } else {
      console.log('Plugin not found');
    }
    
    await browser.close();
    
  } catch (error) {
    console.error('Error:', error.message);
  }
})();
