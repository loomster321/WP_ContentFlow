/**
 * Debug script to test post saving with AI block
 * This will help reproduce the Issue #5 - 500 error when saving posts
 */

const { chromium } = require('playwright');

async function debugPostSave() {
    console.log('🔍 Starting Issue #5 debug - Testing post save with AI block');

    const browser = await chromium.launch({
        headless: false,  // Run with visible browser to see what's happening
        slowMo: 1000      // Slow down actions to observe
    });

    try {
        const context = await browser.newContext({
            viewport: { width: 1280, height: 720 }
        });
        const page = await context.newPage();

        // Enable console logging
        page.on('console', msg => console.log('🌐 Browser:', msg.text()));
        page.on('pageerror', error => console.error('❌ Page Error:', error.message));

        console.log('🚀 Navigating to WordPress admin login...');
        await page.goto('http://localhost:8080/wp-admin');

        // Login
        console.log('🔐 Logging in...');
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
        await page.click('#wp-submit');

        // Wait for dashboard
        await page.waitForSelector('.wp-admin', { timeout: 10000 });
        console.log('✅ Login successful');

        // Navigate to new post
        console.log('📝 Creating new post...');
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        await page.waitForSelector('.edit-post-header', { timeout: 15000 });

        // Add title
        console.log('📝 Adding post title...');
        await page.fill('[placeholder="Add title"]', 'Issue #5 Debug Post');

        // Add the AI Content Flow block
        console.log('🤖 Adding AI Content Flow block...');
        await page.click('.editor-default-block-appender__content');
        await page.keyboard.type('/ai');
        await page.waitForSelector('.block-editor-inserter__menu', { timeout: 5000 });

        // Look for AI Text Generator block
        const aiBlockExists = await page.locator('text=AI Text Generator').count() > 0;
        if (aiBlockExists) {
            await page.click('text=AI Text Generator');
            console.log('✅ AI Text Generator block added');

            // Wait for block to be inserted
            await page.waitForSelector('.wp-content-flow-ai-text-generator', { timeout: 5000 });

            // Try to configure the block
            console.log('⚙️ Configuring AI block...');

            // Add some content to make the post non-empty
            await page.keyboard.press('Enter'); // Add new paragraph after AI block
            await page.keyboard.type('This is a test post to debug Issue #5 - the 500 error when saving posts with AI blocks.');

        } else {
            console.log('⚠️ AI Text Generator block not found, adding regular content...');
            await page.keyboard.type('This is a regular test post without AI blocks.');
        }

        // Try to save the post
        console.log('💾 Attempting to save post...');

        // Listen for network requests to catch the 500 error
        const failedRequests = [];
        page.on('response', response => {
            if (response.status() >= 400) {
                failedRequests.push({
                    url: response.url(),
                    status: response.status(),
                    statusText: response.statusText()
                });
                console.log(`❌ Failed request: ${response.status()} ${response.statusText()} - ${response.url()}`);
            }
        });

        // Click save draft
        await page.click('button[data-label="Save draft"]');

        // Wait a moment for the save attempt
        await page.waitForTimeout(5000);

        // Check for error messages
        const errorNotices = await page.locator('.components-notice.is-error').count();
        if (errorNotices > 0) {
            const errorText = await page.locator('.components-notice.is-error').first().textContent();
            console.log('❌ Error notice found:', errorText);
        }

        // Check for "Updating failed" message
        const updatingFailedExists = await page.locator('text=Updating failed').count() > 0;
        if (updatingFailedExists) {
            console.log('❌ "Updating failed" message detected - this is the Issue #5 symptom!');
        }

        // Report failed requests
        if (failedRequests.length > 0) {
            console.log('❌ Failed HTTP requests during save:');
            failedRequests.forEach(req => {
                console.log(`   ${req.status} ${req.statusText} - ${req.url}`);
            });
        } else {
            console.log('✅ No failed HTTP requests detected');
        }

        // Check if save was successful
        const saveSuccessful = await page.locator('text=Draft saved').count() > 0;
        if (saveSuccessful) {
            console.log('✅ Post saved successfully');
        } else {
            console.log('❌ Post save failed or unclear status');
        }

        // Keep browser open for 10 seconds to inspect
        console.log('🔍 Keeping browser open for inspection...');
        await page.waitForTimeout(10000);

    } catch (error) {
        console.error('❌ Test failed:', error.message);
    } finally {
        await browser.close();
    }
}

// Run the debug
debugPostSave().catch(console.error);