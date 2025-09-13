const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();
    
    // Track network requests and responses
    page.on('response', response => {
        if (response.url().includes('/wp-json/wp/v2/posts')) {
            console.log(`Response: ${response.url()} - Status: ${response.status()}`);
            if (response.status() === 500) {
                response.text().then(body => {
                    console.log('500 ERROR Body:', body);
                });
            }
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
    await page.fill('[aria-label="Add title"]', 'Block Attribute Test');
    
    // Add the block via code editor to inject specific attributes
    await page.click('[aria-label="Settings"], [aria-label="Options"]');
    await page.waitForTimeout(500);
    await page.click('button:has-text("Code editor")');
    await page.waitForTimeout(1000);
    
    // Insert block with isGenerating attribute (if it exists in old code)
    const codeEditor = page.locator('.editor-post-text-editor');
    await codeEditor.fill(`<!-- wp:wp-content-flow/ai-text {"content":"Test content with attributes","workflowId":1,"prompt":"Test prompt"} /-->`);
    
    // Switch back to visual editor
    await page.click('button:has-text("Exit code editor")');
    await page.waitForTimeout(2000);
    
    // Try to save
    console.log('Attempting to save post with block attributes...');
    await page.keyboard.press('Control+s');
    
    // Wait for the response
    await page.waitForTimeout(5000);
    
    // Check if save succeeded
    const savedMessage = page.locator('.components-snackbar:has-text("saved"), .components-snackbar:has-text("published")');
    if (await savedMessage.isVisible()) {
        console.log('✅ Post saved successfully!');
    } else {
        const errorMessage = page.locator('.components-notice:has-text("failed"), .components-notice:has-text("error")');
        if (await errorMessage.isVisible()) {
            console.log('❌ Save failed with error');
            const errorText = await errorMessage.textContent();
            console.log('Error message:', errorText);
        }
    }
    
    await browser.close();
})();