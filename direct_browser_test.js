const { chromium } = require('playwright');

async function runDirectBrowserTest() {
  let browser;
  try {
    console.log('🚀 Starting Direct Browser Test for WordPress AI Content Flow Plugin');
    console.log('🌍 Target URL: http://localhost:8080');
    
    // Launch browser
    browser = await chromium.launch({ 
      headless: false, // Run in headed mode so we can see what's happening
      slowMo: 1000    // Slow down actions for visibility
    });
    
    const context = await browser.newContext();
    const page = await context.newPage();
    
    // 1. Test WordPress Admin Access
    console.log('\n📋 TEST 1: WordPress Admin Access');
    await page.goto('http://localhost:8080/wp-admin/');
    
    // Login
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
    await page.click('#wp-submit');
    
    // Wait for dashboard
    await page.waitForSelector('#wpadminbar', { timeout: 15000 });
    console.log('✅ Successfully logged into WordPress admin');
    
    // Take screenshot of admin dashboard
    await page.screenshot({ path: '/home/timl/dev/WP_ContentFlow/tmp/admin-dashboard.png' });
    
    // 2. Test Plugin Status
    console.log('\n📋 TEST 2: Plugin Status');
    await page.goto('http://localhost:8080/wp-admin/plugins.php');
    
    // Look for our plugin
    const pluginExists = await page.locator('[data-slug*="wp-content-flow"]').count();
    if (pluginExists > 0) {
      console.log('✅ Plugin found in plugins list');
      await page.screenshot({ path: '/home/timl/dev/WP_ContentFlow/tmp/plugin-status.png' });
      
      const pluginActive = await page.locator('[data-slug*="wp-content-flow"] .active').count();
      console.log(pluginActive > 0 ? '✅ Plugin is active' : '❌ Plugin is not active');
    } else {
      console.log('❌ Plugin not found in plugins list');
      await page.screenshot({ path: '/home/timl/dev/WP_ContentFlow/tmp/no-plugin-found.png' });
    }
    
    // 3. Test Plugin Menu
    console.log('\n📋 TEST 3: Plugin Menu Access');
    await page.goto('http://localhost:8080/wp-admin/');
    
    const pluginMenus = await page.locator('a[href*="wp-content-flow"]').count();
    if (pluginMenus > 0) {
      console.log('✅ Plugin menu(s) found in admin');
      console.log(`   Found ${pluginMenus} menu items`);
      
      // Try to click the first menu item
      await page.locator('a[href*="wp-content-flow"]').first().click();
      await page.waitForTimeout(3000);
      await page.screenshot({ path: '/home/timl/dev/WP_ContentFlow/tmp/plugin-admin-page.png' });
      console.log('✅ Plugin admin page loaded');
    } else {
      console.log('❌ No plugin menus found');
      await page.screenshot({ path: '/home/timl/dev/WP_ContentFlow/tmp/no-plugin-menus.png' });
    }
    
    // 4. Test Post Editor Integration
    console.log('\n📋 TEST 4: Gutenberg Editor Integration');
    await page.goto('http://localhost:8080/wp-admin/post-new.php');
    
    // Wait for Gutenberg to load
    await page.waitForSelector('.block-editor-writing-flow', { timeout: 20000 });
    console.log('✅ Gutenberg editor loaded');
    
    // Add a title
    await page.fill('[name="post_title"]', 'AI Content Test Post');
    
    // Take screenshot of editor
    await page.screenshot({ path: '/home/timl/dev/WP_ContentFlow/tmp/gutenberg-editor.png' });
    
    // 5. Test Block Inserter
    console.log('\n📋 TEST 5: Block Inserter and AI Features');
    
    // Open block inserter
    await page.click('.block-editor-inserter__toggle');
    await page.waitForSelector('.block-editor-inserter__menu', { timeout: 10000 });
    console.log('✅ Block inserter opened');
    
    // Search for AI-related blocks
    await page.fill('.block-editor-inserter__search input', 'AI');
    await page.waitForTimeout(2000);
    
    const aiBlocks = await page.locator('.block-editor-block-types-list__item').count();
    console.log(`📊 Found ${aiBlocks} blocks matching 'AI'`);
    
    await page.screenshot({ path: '/home/timl/dev/WP_ContentFlow/tmp/block-inserter-ai-search.png' });
    
    // Check for specific AI Text Generator block
    const aiTextBlock = await page.locator('.block-editor-block-types-list__item:has-text("AI Text Generator")').count();
    if (aiTextBlock > 0) {
      console.log('✅ AI Text Generator block found');
      await page.locator('.block-editor-block-types-list__item:has-text("AI Text Generator")').click();
      await page.waitForTimeout(2000);
      await page.screenshot({ path: '/home/timl/dev/WP_ContentFlow/tmp/ai-block-inserted.png' });
    } else {
      console.log('❌ AI Text Generator block not found');
    }
    
    // 6. Test AI Chat Feature (Globe Icon Investigation)
    console.log('\n📋 TEST 6: AI Chat Feature Investigation (Globe Icon)');
    
    // Look for globe icon or similar elements
    const globeIcons = await page.locator('svg[class*="globe"], .dashicons-admin-site-alt3, [class*="globe"]').count();
    console.log(`📊 Found ${globeIcons} potential globe icons`);
    
    // Check editor toolbar for additional buttons
    const toolbarButtons = await page.locator('.edit-post-header__toolbar button').count();
    console.log(`📊 Found ${toolbarButtons} toolbar buttons`);
    
    await page.screenshot({ path: '/home/timl/dev/WP_ContentFlow/tmp/editor-toolbar.png' });
    
    // Check for any custom AI-related buttons or panels
    const aiChatElements = await page.locator('[class*="ai-chat"], [class*="content-flow"], .wp-content-flow').count();
    console.log(`📊 Found ${aiChatElements} AI/ContentFlow related elements`);
    
    if (aiChatElements > 0) {
      console.log('✅ Found AI-related elements');
      const elements = await page.locator('[class*="ai-chat"], [class*="content-flow"], .wp-content-flow').all();
      for (let i = 0; i < Math.min(elements.length, 3); i++) {
        const element = elements[i];
        const isVisible = await element.isVisible();
        const className = await element.getAttribute('class');
        console.log(`   Element ${i + 1}: ${className} (visible: ${isVisible})`);
      }
    }
    
    // 7. Test Console for JavaScript Errors
    console.log('\n📋 TEST 7: JavaScript Console Check');
    
    const consoleLogs = [];
    page.on('console', msg => {
      consoleLogs.push(`${msg.type()}: ${msg.text()}`);
    });
    
    page.on('pageerror', error => {
      console.log('❌ JavaScript Error:', error.message);
    });
    
    // Reload page to capture any console output
    await page.reload();
    await page.waitForSelector('.block-editor-writing-flow', { timeout: 15000 });
    await page.waitForTimeout(3000);
    
    // Print console logs
    const errorLogs = consoleLogs.filter(log => log.includes('error') || log.includes('Error'));
    const warningLogs = consoleLogs.filter(log => log.includes('warning') || log.includes('Warning'));
    
    console.log(`📊 Console: ${errorLogs.length} errors, ${warningLogs.length} warnings`);
    if (errorLogs.length > 0) {
      console.log('❌ JavaScript Errors:');
      errorLogs.slice(0, 5).forEach(log => console.log(`   ${log}`));
    }
    
    // 8. Test REST API Endpoints
    console.log('\n📋 TEST 8: REST API Endpoints');
    
    try {
      const apiResponse = await page.request.get('http://localhost:8080/wp-json/wp-content-flow/v1/workflows');
      console.log(`📊 Workflows API: ${apiResponse.status()} ${apiResponse.statusText()}`);
      
      const settingsResponse = await page.request.get('http://localhost:8080/wp-json/wp-content-flow/v1/settings');
      console.log(`📊 Settings API: ${settingsResponse.status()} ${settingsResponse.statusText()}`);
      
    } catch (error) {
      console.log('❌ API Request Error:', error.message);
    }
    
    console.log('\n🎉 Direct Browser Test Completed!');
    console.log('📁 Screenshots saved to tmp/ directory');
    
    // Keep browser open for manual inspection
    console.log('\n⏳ Browser will remain open for 30 seconds for manual inspection...');
    await page.waitForTimeout(30000);
    
  } catch (error) {
    console.error('❌ Test Error:', error);
  } finally {
    if (browser) {
      await browser.close();
    }
  }
}

// Run the test
runDirectBrowserTest().catch(console.error);