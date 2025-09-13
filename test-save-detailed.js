const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();
    
    let errorDetails = null;
    
    // Capture console errors
    page.on('console', msg => {
        if (msg.type() === 'error') {
            console.log('Console Error:', msg.text());
        }
    });
    
    // Track network requests and responses
    page.on('response', async response => {
        if (response.url().includes('/wp-json/wp/v2/posts')) {
            console.log(`\n📡 API Request: ${response.url()}`);
            console.log(`   Status: ${response.status()}`);
            
            if (response.status() === 500) {
                errorDetails = {
                    url: response.url(),
                    status: response.status(),
                    body: await response.text()
                };
            }
        }
    });
    
    // Login
    console.log('🔐 Logging into WordPress...');
    await page.goto('http://localhost:8080/wp-login.php');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
    await page.click('#wp-submit');
    await page.waitForURL('**/wp-admin/**');
    console.log('✅ Logged in successfully');
    
    // Create new post
    console.log('\n📝 Creating new post...');
    await page.goto('http://localhost:8080/wp-admin/post-new.php');
    await page.waitForTimeout(3000);
    
    // Add title
    await page.fill('[aria-label="Add title"]', `Test Post ${Date.now()}`);
    console.log('✅ Added post title');
    
    // Add a regular paragraph first
    console.log('\n📄 Adding regular paragraph...');
    await page.keyboard.type('This is a regular paragraph before the AI block.');
    await page.keyboard.press('Enter');
    await page.waitForTimeout(500);
    
    // Try to save WITHOUT AI block
    console.log('\n💾 Testing save WITHOUT AI block...');
    await page.keyboard.press('Control+s');
    await page.waitForTimeout(3000);
    
    if (!errorDetails) {
        console.log('✅ Save WITHOUT AI block succeeded');
    } else {
        console.log('❌ Save WITHOUT AI block failed:', errorDetails);
        await browser.close();
        return;
    }
    
    // Now add AI Text Generator block
    console.log('\n🤖 Adding AI Text Generator block...');
    const insertButton = page.locator('[aria-label="Toggle block inserter"], [aria-label="Add block"]').first();
    await insertButton.click();
    await page.waitForTimeout(500);
    
    const searchInput = page.locator('[placeholder="Search"]');
    await searchInput.fill('AI Text');
    await page.waitForTimeout(500);
    
    const aiBlock = page.locator('button:has-text("AI Text Generator")').first();
    if (await aiBlock.isVisible()) {
        await aiBlock.click();
        console.log('✅ AI Text Generator block added');
        await page.waitForTimeout(2000);
    } else {
        console.log('❌ AI Text Generator block not found');
        await browser.close();
        return;
    }
    
    // Try to save WITH AI block (empty)
    console.log('\n💾 Testing save WITH empty AI block...');
    errorDetails = null;
    await page.keyboard.press('Control+s');
    await page.waitForTimeout(3000);
    
    if (!errorDetails) {
        console.log('✅ Save WITH empty AI block succeeded');
    } else {
        console.log('❌ Save WITH empty AI block failed');
        console.log('Error details:', JSON.stringify(errorDetails, null, 2));
    }
    
    // Now add content to the AI block
    console.log('\n✏️ Adding content to AI block...');
    const blockContent = page.locator('.wp-content-flow-ai-generated-content, .wp-content-flow-placeholder').first();
    if (await blockContent.isVisible()) {
        await blockContent.click();
        await page.keyboard.type('This is test content in the AI block');
        console.log('✅ Added content to AI block');
    }
    
    // Try to save WITH AI block content
    console.log('\n💾 Testing save WITH AI block content...');
    errorDetails = null;
    await page.keyboard.press('Control+s');
    await page.waitForTimeout(3000);
    
    if (!errorDetails) {
        console.log('✅ Save WITH AI block content succeeded');
    } else {
        console.log('❌ Save WITH AI block content failed');
        console.log('Error details:', JSON.stringify(errorDetails, null, 2));
    }
    
    await browser.close();
    
    // Summary
    console.log('\n' + '='.repeat(50));
    console.log('TEST SUMMARY');
    console.log('='.repeat(50));
    if (errorDetails) {
        console.log('❌ 500 ERROR DETECTED');
        console.log('Failing scenario: Save with AI Text Generator block');
    } else {
        console.log('✅ ALL SAVES SUCCEEDED');
        console.log('No 500 errors detected');
    }
})();