const { chromium } = require('playwright');

(async () => {
  try {
    console.log('Testing plugin loading...');
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    
    // Navigate to login
    await page.goto('http://localhost:8080/wp-admin/');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
    await page.click('#wp-submit');
    await page.waitForSelector('.wrap');
    
    // Navigate to plugins page
    await page.goto('http://localhost:8080/wp-admin/plugins.php');
    
    // Get all plugin names to see what's there
    const pluginNames = await page.$$eval('.plugin-title strong', elements => {
      return elements.map(el => el.textContent);
    });
    
    console.log('Found plugins:', pluginNames);
    
    // Check if our plugin exists by name
    const hasOurPlugin = pluginNames.some(name => name.includes('WordPress AI Content Flow'));
    console.log('Our plugin found:', hasOurPlugin);
    
    if (!hasOurPlugin) {
      // Check for any error notices on the plugins page
      const errorNotices = await page.$$eval('.notice-error', elements => {
        return elements.map(el => el.textContent.trim());
      });
      
      if (errorNotices.length > 0) {
        console.log('Error notices found:', errorNotices);
      } else {
        console.log('No error notices found on plugins page');
      }
    }
    
    await browser.close();
    
  } catch (error) {
    console.error('Error:', error.message);
  }
})();
