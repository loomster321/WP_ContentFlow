const { chromium } = require('playwright');

(async () => {
    console.log('🔍 Detailed Test of Post Save Functionality');
    console.log('==========================================\n');

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();

    // Capture console and network errors
    const errors = [];
    page.on('console', msg => {
        if (msg.type() === 'error') {
            errors.push(msg.text());
        }
    });

    page.on('response', response => {
        if (response.url().includes('/wp-json/wp/v2/posts')) {
            console.log(`API Response: ${response.url()} - Status: ${response.status()}`);
            if (response.status() >= 400) {
                response.text().then(body => {
                    console.log('Error body:', body.substring(0, 200));
                });
            }
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
        console.log('   ✅ Logged in\n');

        // Create new post
        console.log('2. Creating new post...');
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        await page.waitForTimeout(5000); // Give editor time to load

        // Check if editor loaded
        const editorLoaded = await page.locator('.block-editor-page, .edit-post-layout').isVisible().catch(() => false);
        console.log(`   Editor loaded: ${editorLoaded ? 'YES' : 'NO'}`);

        // Try to find title field
        const titleSelectors = [
            'h1[aria-label="Add title"]',
            '.editor-post-title__input',
            '[data-testid="post-title"]',
            '.wp-block-post-title'
        ];

        let titleFound = false;
        for (const selector of titleSelectors) {
            const element = page.locator(selector);
            if (await element.isVisible().catch(() => false)) {
                console.log(`   Found title with selector: ${selector}`);
                await element.fill('Test Post');
                titleFound = true;
                break;
            }
        }

        if (!titleFound) {
            console.log('   ⚠️  Could not find title field');
            console.log('   Page HTML preview:', await page.content().then(html => html.substring(0, 500)));
        }

        // Attempt save
        console.log('\n3. Attempting to save...');

        let saveResponse = null;
        const responsePromise = page.waitForResponse(
            response => response.url().includes('/wp-json/wp/v2/posts'),
            { timeout: 10000 }
        ).catch(err => {
            console.log('   No save response detected:', err.message);
            return null;
        });

        await page.keyboard.press('Control+s');
        console.log('   Pressed Ctrl+S, waiting for response...');

        saveResponse = await responsePromise;

        if (saveResponse) {
            const status = saveResponse.status();
            const body = await saveResponse.text();

            console.log(`\n   Response Status: ${status}`);
            if (status !== 200 && status !== 201) {
                console.log(`   Response Body: ${body.substring(0, 300)}`);
            }

            if (status === 500) {
                console.log('\n   ❌ 500 ERROR - Server error occurred');
            } else if (status === 200 || status === 201) {
                console.log('\n   ✅ SUCCESS - Post saved!');
            }
        } else {
            console.log('\n   ⚠️  No API response captured');
        }

        // Check for console errors
        if (errors.length > 0) {
            console.log('\n4. Console errors detected:');
            errors.forEach(err => console.log(`   - ${err}`));
        }

    } catch (error) {
        console.error('\n❌ Test error:', error.message);
    } finally {
        await browser.close();
        console.log('\n==========================================');
    }
})();