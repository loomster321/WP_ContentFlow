const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();
    
    // Track network requests and responses
    page.on('response', response => {
        if (response.url().includes('/wp-json/wp/v2/posts') && response.status() === 500) {
            console.log('500 ERROR DETECTED:');
            console.log('URL:', response.url());
            console.log('Status:', response.status());
            response.text().then(body => {
                console.log('Response Body:', body);
                try {
                    const json = JSON.parse(body);
                    console.log('Parsed Error:', JSON.stringify(json, null, 2));
                } catch (e) {
                    console.log('Raw Response:', body);
                }
            });
        }
    });
    
    // Login
    await page.goto('http://localhost:8080/wp-login.php');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
    await page.click('#wp-submit');
    await page.waitForURL('**/wp-admin/**');
    
    // Create new post
    await page.goto('http://localhost:8080/wp-admin/post-new.php');
    await page.waitForTimeout(2000);
    
    // Add title
    await page.fill('[aria-label="Add title"]', 'Test Post for Issue #5');
    
    // Add AI Text Generator block
    const insertButton = page.locator('[aria-label="Toggle block inserter"], [aria-label="Add block"]').first();
    await insertButton.click();
    await page.waitForTimeout(500);
    
    const searchInput = page.locator('[placeholder="Search"]');
    await searchInput.fill('AI Text');
    await page.waitForTimeout(500);
    
    const aiBlock = page.locator('button:has-text("AI Text Generator")').first();
    await aiBlock.click();
    await page.waitForTimeout(1000);
    
    // Type some content in the block
    const blockContent = page.locator('.wp-content-flow-ai-generated-content, .wp-content-flow-placeholder').first();
    if (await blockContent.isVisible()) {
        await blockContent.click();
        await page.keyboard.type('Test content for debugging 500 error');
    }
    
    // Try to save
    console.log('Attempting to save post...');
    await page.keyboard.press('Control+s');
    
    // Wait for the response
    await page.waitForTimeout(5000);
    
    await browser.close();
})();