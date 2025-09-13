const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();
    
    // Login
    console.log('🔐 Logging into WordPress...');
    await page.goto('http://localhost:8080/wp-login.php');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
    await page.click('#wp-submit');
    await page.waitForURL('**/wp-admin/**');
    console.log('✅ Logged in');
    
    // Deactivate the plugin
    console.log('\n🔌 Deactivating wp-content-flow plugin...');
    await page.goto('http://localhost:8080/wp-admin/plugins.php');
    await page.waitForTimeout(2000);
    
    // Find and click deactivate link for our plugin
    const deactivateLink = page.locator('tr[data-plugin="wp-content-flow/wp-content-flow.php"] .deactivate a, a[href*="action=deactivate"][href*="wp-content-flow"]').first();
    if (await deactivateLink.isVisible()) {
        await deactivateLink.click();
        console.log('✅ Plugin deactivated');
        await page.waitForTimeout(2000);
    } else {
        console.log('⚠️ Plugin not found or already deactivated');
    }
    
    // Create new post WITHOUT plugin
    console.log('\n📝 Creating new post (plugin deactivated)...');
    await page.goto('http://localhost:8080/wp-admin/post-new.php');
    await page.waitForTimeout(2000);
    
    // Add title
    await page.fill('[aria-label="Add title"]', `Test Post Without Plugin ${Date.now()}`);
    
    // Add content
    await page.keyboard.type('This is test content without the plugin active.');
    await page.keyboard.press('Enter');
    await page.keyboard.type('Another paragraph here.');
    
    // Try to save
    console.log('\n💾 Testing save WITHOUT plugin...');
    let saveSuccess = false;
    
    page.on('response', response => {
        if (response.url().includes('/wp-json/wp/v2/posts')) {
            console.log(`   API Response: ${response.status()}`);
            if (response.status() === 200 || response.status() === 201) {
                saveSuccess = true;
            }
        }
    });
    
    await page.keyboard.press('Control+s');
    await page.waitForTimeout(3000);
    
    if (saveSuccess) {
        console.log('✅ Save WITHOUT plugin SUCCEEDED!');
        console.log('   This confirms the 500 error is caused by the plugin');
    } else {
        console.log('❌ Save WITHOUT plugin also failed');
        console.log('   The issue may be with WordPress itself, not the plugin');
    }
    
    // Re-activate the plugin
    console.log('\n🔌 Re-activating plugin...');
    await page.goto('http://localhost:8080/wp-admin/plugins.php');
    await page.waitForTimeout(2000);
    
    const activateLink = page.locator('tr[data-plugin="wp-content-flow/wp-content-flow.php"] .activate a, a[href*="action=activate"][href*="wp-content-flow"]').first();
    if (await activateLink.isVisible()) {
        await activateLink.click();
        console.log('✅ Plugin re-activated');
    }
    
    await browser.close();
})();