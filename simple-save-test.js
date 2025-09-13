const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();
    
    let saveStatus = null;
    
    // Track network responses
    page.on('response', response => {
        if (response.url().includes('/wp-json/wp/v2/posts')) {
            saveStatus = response.status();
            console.log(`Save API Response: ${saveStatus}`);
        }
    });
    
    // Login
    console.log('Logging in...');
    await page.goto('http://localhost:8080/wp-login.php');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
    await page.click('#wp-submit');
    await page.waitForURL('**/wp-admin/**');
    
    // Create new post
    console.log('Creating new post...');
    await page.goto('http://localhost:8080/wp-admin/post-new.php');
    await page.waitForTimeout(3000);
    
    // Wait for the editor to load
    console.log('Waiting for editor...');
    await page.waitForTimeout(3000);
    
    // Try to add content and save
    console.log('Attempting to save...');
    await page.keyboard.press('Control+s');
    
    // Wait for response
    await page.waitForTimeout(5000);
    
    // Report result
    if (saveStatus === 200 || saveStatus === 201) {
        console.log('✅ SAVE SUCCESSFUL! Issue #5 is FIXED!');
    } else if (saveStatus === 500) {
        console.log('❌ 500 ERROR still occurs - Issue #5 NOT fixed');
    } else if (saveStatus) {
        console.log(`⚠️ Unexpected status: ${saveStatus}`);
    } else {
        console.log('⚠️ No save response detected');
    }
    
    await browser.close();
})();