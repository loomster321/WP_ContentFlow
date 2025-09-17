const { chromium } = require('playwright');

(async () => {
    console.log('Testing WordPress API Response Capture');
    console.log('=========================================');

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();

    // Capture ALL network requests
    const apiCalls = [];
    page.on('response', response => {
        const url = response.url();
        if (url.includes('/wp-json/') || url.includes('/wp-admin/')) {
            apiCalls.push({
                url: url,
                status: response.status(),
                method: response.request().method()
            });
        }
    });

    try {
        // Login
        console.log('1. Logging in...');
        await page.goto('http://localhost:8080/wp-login.php');
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
        await page.click('#wp-submit');
        await page.waitForURL('**/wp-admin/**', { timeout: 10000 });
        console.log('   Logged in');

        // Create new post
        console.log('2. Creating new post...');
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        await page.waitForTimeout(3000);

        // Fill title
        await page.fill('h1[aria-label="Add title"], .editor-post-title__input', 'API Test Post');

        // Save with keyboard shortcut
        console.log('3. Saving post...');
        await page.keyboard.press('Control+s');
        await page.waitForTimeout(3000);

        // Display all API calls
        console.log('4. API Calls Made:');
        let postCallCount = 0;
        apiCalls.forEach((call) => {
            if (call.url.includes('/wp-json/wp/v2/posts')) {
                postCallCount++;
                const urlPart = call.url.substring(call.url.indexOf('/wp-json/'));
                console.log('   ' + postCallCount + '. ' + call.method + ' ' + urlPart + ' - Status: ' + call.status);
            }
        });

        // Check for success message
        const savedMessage = await page.locator('.components-snackbar__content, .editor-post-saved-state').textContent().catch(() => '');
        if (savedMessage) {
            console.log('5. Editor message: ' + savedMessage);
        }

        // Get the post ID if available
        const postId = await page.evaluate(() => {
            const editor = window.wp && window.wp.data && window.wp.data.select('core/editor');
            return editor ? editor.getCurrentPostId() : null;
        }).catch(() => null);

        if (postId) {
            console.log('SUCCESS - Post created with ID: ' + postId);
        } else {
            console.log('Could not retrieve post ID from editor');
        }

    } catch (error) {
        console.error('Test error:', error.message);
    } finally {
        await browser.close();
        console.log('=========================================');
    }
})();
