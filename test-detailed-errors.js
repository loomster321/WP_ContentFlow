const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false });
  const context = await browser.newContext();
  const page = await context.newPage();
  
  // Track failed requests
  const failedRequests = [];
  
  page.on('requestfailed', request => {
    failedRequests.push({
      url: request.url(),
      failure: request.failure()
    });
  });
  
  page.on('response', response => {
    if (response.status() >= 400) {
      console.log(`❌ ${response.status()} - ${response.url()}`);
    }
  });
  
  console.log('🔐 Logging into WordPress...');
  await page.goto('http://localhost:8080/wp-admin/');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
  await page.click('#wp-submit');
  await page.waitForSelector('#wpadminbar', { timeout: 15000 });
  
  console.log('📝 Opening post editor...');
  await page.goto('http://localhost:8080/wp-admin/post-new.php');
  await page.waitForSelector('.edit-post-visual-editor', { timeout: 30000 });
  await page.waitForTimeout(3000);
  
  console.log('🌐 Clicking AI Content Flow sidebar...');
  const sidebarButton = page.locator('button[aria-label*="AI Content Flow"], button[aria-label*="AI Chat"]').first();
  
  if (await sidebarButton.isVisible()) {
    await sidebarButton.click();
    await page.waitForTimeout(2000);
  }
  
  console.log('\n📊 Failed Requests:');
  failedRequests.forEach(req => {
    console.log(`  - ${req.url}`);
    console.log(`    Failure: ${req.failure?.errorText}`);
  });
  
  console.log('\nKeeping browser open for 5 seconds...');
  await page.waitForTimeout(5000);
  
  await browser.close();
})();