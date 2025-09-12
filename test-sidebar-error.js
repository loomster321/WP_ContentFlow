const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false });
  const context = await browser.newContext();
  const page = await context.newPage();
  
  // Capture console messages
  const consoleErrors = [];
  page.on('console', msg => {
    if (msg.type() === 'error') {
      consoleErrors.push(msg.text());
      console.log('❌ Console Error:', msg.text());
    }
  });
  
  // Capture page errors
  page.on('pageerror', error => {
    console.log('❌ Page Error:', error.message);
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
  
  // Wait a bit for everything to load
  await page.waitForTimeout(3000);
  
  console.log('🌐 Looking for AI Content Flow sidebar button...');
  
  // Try to find and click the globe icon
  const sidebarButton = page.locator('button[aria-label*="AI Content Flow"], button[aria-label*="AI Chat"], .components-button:has-text("AI"), button:has([class*="dashicons-admin-site"])').first();
  
  if (await sidebarButton.isVisible()) {
    console.log('✅ Found sidebar button, clicking...');
    await sidebarButton.click();
    await page.waitForTimeout(2000);
    
    // Check if sidebar opened
    const sidebar = page.locator('[class*="interface-complementary-area"], [class*="plugin-sidebar"]');
    if (await sidebar.isVisible()) {
      console.log('✅ Sidebar opened successfully!');
    } else {
      console.log('❌ Sidebar did not open');
    }
  } else {
    console.log('❌ Could not find AI Content Flow sidebar button');
    
    // Try to find it in the plugins menu
    const pluginsButton = page.locator('button[aria-label="Plugins"]');
    if (await pluginsButton.isVisible()) {
      await pluginsButton.click();
      await page.waitForTimeout(1000);
      
      const aiMenuItem = page.locator('[role="menuitem"]:has-text("AI")').first();
      if (await aiMenuItem.isVisible()) {
        console.log('✅ Found AI menu item in plugins dropdown');
        await aiMenuItem.click();
        await page.waitForTimeout(2000);
      }
    }
  }
  
  console.log('\n📊 Console errors captured:', consoleErrors.length);
  consoleErrors.forEach((error, i) => {
    console.log(`Error ${i + 1}:`, error);
  });
  
  // Keep browser open for manual inspection
  console.log('\n✨ Browser will stay open for 10 seconds for inspection...');
  await page.waitForTimeout(10000);
  
  await browser.close();
})();