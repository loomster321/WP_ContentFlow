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
  
  console.log('🔐 Logging into WordPress...');
  await page.goto('http://localhost:8080/wp-admin/');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
  await page.click('#wp-submit');
  await page.waitForSelector('#wpadminbar', { timeout: 15000 });
  
  console.log('\n=== TEST 1: Audit Trail Link ===');
  // Look for Content Flow menu
  const contentFlowMenu = page.locator('#adminmenu a:has-text("Content Flow")').first();
  if (await contentFlowMenu.isVisible()) {
    console.log('✅ Found Content Flow menu');
    await contentFlowMenu.hover();
    await page.waitForTimeout(500);
    
    // Look for Audit Trail submenu
    const auditTrailLink = page.locator('#adminmenu a:has-text("Audit Trail")').first();
    if (await auditTrailLink.isVisible()) {
      console.log('✅ Found Audit Trail link, clicking...');
      await auditTrailLink.click();
      await page.waitForTimeout(2000);
      
      // Check if we got a 404
      const pageTitle = await page.title();
      const pageContent = await page.locator('body').textContent();
      
      if (pageContent.includes('Page Not Found') || pageContent.includes('404')) {
        console.log('❌ Audit Trail page shows 404 error');
      } else if (pageContent.includes('Audit Trail') || pageContent.includes('audit')) {
        console.log('✅ Audit Trail page loaded successfully!');
      } else {
        console.log('⚠️ Unexpected page content');
      }
    } else {
      console.log('❌ Audit Trail link not found in submenu');
    }
  } else {
    console.log('❌ Content Flow menu not found');
  }
  
  console.log('\n=== TEST 2: Editor Sidebar ===');
  await page.goto('http://localhost:8080/wp-admin/post-new.php');
  await page.waitForSelector('.edit-post-visual-editor', { timeout: 30000 });
  await page.waitForTimeout(3000);
  
  // Clear previous errors
  const errorsBefore = consoleErrors.length;
  
  // Try to find and click the globe icon
  const sidebarButton = page.locator('button[aria-label*="AI Content Flow"], button[aria-label*="AI Chat"], .components-button:has-text("AI")').first();
  
  if (await sidebarButton.isVisible()) {
    console.log('✅ Found AI Content Flow sidebar button, clicking...');
    await sidebarButton.click();
    await page.waitForTimeout(2000);
    
    // Check if sidebar opened without errors
    const sidebar = page.locator('[class*="interface-complementary-area"], [class*="plugin-sidebar"], .components-panel:has-text("AI Content")').first();
    const newErrors = consoleErrors.length - errorsBefore;
    
    if (await sidebar.isVisible() && newErrors === 0) {
      console.log('✅ Sidebar opened successfully without errors!');
      
      // Check if tabs are visible
      const generateTab = page.locator('button:has-text("Generate")').first();
      const improveTab = page.locator('button:has-text("Improve")').first();
      const settingsTab = page.locator('button:has-text("Settings")').first();
      
      if (await generateTab.isVisible() && await improveTab.isVisible() && await settingsTab.isVisible()) {
        console.log('✅ All tabs (Generate, Improve, Settings) are visible!');
      } else {
        console.log('⚠️ Some tabs are missing');
      }
    } else if (newErrors > 0) {
      console.log('❌ Sidebar triggered JavaScript errors');
    } else {
      console.log('❌ Sidebar did not open');
    }
  } else {
    console.log('❌ Could not find AI Content Flow sidebar button');
  }
  
  console.log('\n=== SUMMARY ===');
  console.log('Total console errors:', consoleErrors.length);
  if (consoleErrors.length > 0) {
    console.log('Errors:', consoleErrors);
  }
  
  console.log('\nTests complete! Browser will close in 5 seconds...');
  await page.waitForTimeout(5000);
  
  await browser.close();
})();