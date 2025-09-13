const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();
    
    // Capture console errors
    page.on('console', msg => {
        if (msg.type() === 'error') {
            console.log('Console Error:', msg.text());
        }
    });
    
    // Capture page errors
    page.on('pageerror', error => {
        console.log('Page Error:', error.message);
    });
    
    // Login
    console.log('Logging in...');
    await page.goto('http://localhost:8080/wp-login.php');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
    await page.click('#wp-submit');
    await page.waitForURL('**/wp-admin/**');
    console.log('✅ Logged in');
    
    // Go to post editor
    console.log('\nNavigating to post editor...');
    const response = await page.goto('http://localhost:8080/wp-admin/post-new.php', { waitUntil: 'networkidle' });
    console.log(`Page status: ${response.status()}`);
    
    // Check if page loaded correctly
    const pageContent = await page.content();
    if (pageContent.includes('fatal error') || pageContent.includes('Fatal error')) {
        console.log('❌ FATAL ERROR detected on page!');
        const errorMatch = pageContent.match(/Fatal error:.*?<\/b>/);
        if (errorMatch) {
            console.log('Error:', errorMatch[0]);
        }
    } else {
        console.log('✅ No fatal errors detected on page load');
    }
    
    // Check if editor loaded
    await page.waitForTimeout(3000);
    
    const titleField = page.locator('[aria-label="Add title"], .editor-post-title__input, h1[contenteditable="true"]').first();
    const editorPresent = await titleField.isVisible().catch(() => false);
    
    if (editorPresent) {
        console.log('✅ Editor loaded successfully');
        
        // Try to type in title
        await titleField.click();
        await page.keyboard.type('Test Title');
        console.log('✅ Can type in title field');
        
        // Check for blocks
        const blockInserter = page.locator('[aria-label="Add block"], [aria-label="Toggle block inserter"]').first();
        if (await blockInserter.isVisible()) {
            console.log('✅ Block inserter available');
        } else {
            console.log('⚠️ Block inserter not found');
        }
    } else {
        console.log('❌ Editor did not load properly');
        
        // Check page title
        const pageTitle = await page.title();
        console.log('Page title:', pageTitle);
        
        // Check for specific error elements
        const errorNotice = page.locator('.notice-error, .error-message, #message.error').first();
        if (await errorNotice.isVisible()) {
            const errorText = await errorNotice.textContent();
            console.log('Error notice:', errorText);
        }
    }
    
    await browser.close();
})();