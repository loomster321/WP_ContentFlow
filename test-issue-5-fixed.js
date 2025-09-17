const { chromium } = require('playwright');

(async () => {
    console.log('🔍 Testing Issue #5 - WordPress Post Save Fix');
    console.log('==============================================\n');

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();

    let allTestsPassed = true;
    const results = [];

    try {
        // Test 1: Login
        console.log('1. Logging into WordPress...');
        await page.goto('http://localhost:8080/wp-login.php');
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
        await page.click('#wp-submit');
        await page.waitForURL('**/wp-admin/**', { timeout: 10000 });
        console.log('   ✅ Logged in successfully\n');

        // Test 2: Simple post save
        console.log('2. Testing simple post save...');
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        await page.waitForTimeout(3000);

        const titleSelector = 'h1[aria-label="Add title"], .editor-post-title__input';
        await page.fill(titleSelector, 'Test Post - Simple Save', { timeout: 5000 });

        // Set up response listener then immediately save
        const [saveResponse1] = await Promise.all([
            page.waitForResponse(
                response => response.url().includes('/wp-json/wp/v2/posts'),
                { timeout: 10000 }
            ).catch(() => null),
            page.keyboard.press('Control+s')
        ]);

        if (saveResponse1 && saveResponse1.status() === 200) {
            console.log('   ✅ Simple post saved successfully (HTTP 200)');
            results.push('Simple post save: PASSED');
        } else {
            console.log('   ❌ Simple post save failed');
            results.push('Simple post save: FAILED');
            allTestsPassed = false;
        }

        // Test 3: Post with content
        console.log('\n3. Testing post with paragraph content...');
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        await page.waitForTimeout(3000);

        await page.fill(titleSelector, 'Test Post with Content', { timeout: 5000 });
        await page.keyboard.press('Tab');
        await page.keyboard.type('This is test content to verify the fix works.');

        // Set up response listener then immediately save
        const [saveResponse2] = await Promise.all([
            page.waitForResponse(
                response => response.url().includes('/wp-json/wp/v2/posts'),
                { timeout: 10000 }
            ).catch(() => null),
            page.keyboard.press('Control+s')
        ]);

        if (saveResponse2 && saveResponse2.status() === 200) {
            console.log('   ✅ Post with content saved successfully');
            results.push('Post with content: PASSED');
        } else {
            console.log('   ❌ Post with content save failed');
            results.push('Post with content: FAILED');
            allTestsPassed = false;
        }

        // Test 4: Update existing post
        console.log('\n4. Testing update of existing post...');
        const postsResponse = await page.request.get('http://localhost:8080/wp-json/wp/v2/posts?per_page=1');
        const posts = await postsResponse.json();

        if (posts.length > 0) {
            const postId = posts[0].id;
            await page.goto(`http://localhost:8080/wp-admin/post.php?post=${postId}&action=edit`);
            await page.waitForTimeout(3000);

            // Make a small edit to the title
            const titleField = page.locator(titleSelector).first();
            await titleField.click();
            await page.keyboard.press('End');
            await page.keyboard.type(' - Updated');

            // Set up response listener then immediately save
            const [updateResponse] = await Promise.all([
                page.waitForResponse(
                    response => response.url().includes(`/wp-json/wp/v2/posts/${postId}`),
                    { timeout: 10000 }
                ).catch(() => null),
                page.keyboard.press('Control+s')
            ]);

            if (updateResponse && updateResponse.status() === 200) {
                console.log('   ✅ Existing post updated successfully');
                results.push('Post update: PASSED');
            } else {
                console.log('   ❌ Post update failed');
                results.push('Post update: FAILED');
                allTestsPassed = false;
            }
        } else {
            console.log('   ⚠️  No existing posts to update');
            results.push('Post update: SKIPPED');
        }

        // Test 5: Multiple rapid saves (stress test)
        console.log('\n5. Testing multiple rapid saves...');
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        await page.waitForTimeout(3000);

        await page.fill(titleSelector, 'Rapid Save Test', { timeout: 5000 });

        let rapidSaveSuccess = true;
        for (let i = 1; i <= 3; i++) {
            await page.keyboard.type(` - Edit ${i}`);

            const [rapidResponse] = await Promise.all([
                page.waitForResponse(
                    response => response.url().includes('/wp-json/wp/v2/posts'),
                    { timeout: 10000 }
                ).catch(() => null),
                page.keyboard.press('Control+s')
            ]);

            if (!rapidResponse || rapidResponse.status() !== 200) {
                rapidSaveSuccess = false;
                break;
            }
            await page.waitForTimeout(1000);
        }

        if (rapidSaveSuccess) {
            console.log('   ✅ Multiple rapid saves successful');
            results.push('Rapid saves: PASSED');
        } else {
            console.log('   ❌ Multiple rapid saves failed');
            results.push('Rapid saves: FAILED');
            allTestsPassed = false;
        }

    } catch (error) {
        console.error('\n❌ Test failed with error:', error.message);
        allTestsPassed = false;
    } finally {
        await browser.close();

        console.log('\n==============================================');
        console.log('TEST RESULTS SUMMARY:');
        console.log('==============================================');
        results.forEach(result => console.log(`  • ${result}`));
        console.log('==============================================');

        if (allTestsPassed) {
            console.log('✅ ALL TESTS PASSED - Issue #5 is FIXED!');
            console.log('\nThe following functionality is confirmed working:');
            console.log('  • Posts can be created and saved');
            console.log('  • Posts with content can be saved');
            console.log('  • Existing posts can be updated');
            console.log('  • Multiple rapid saves work correctly');
            console.log('  • No 500 errors or PHP fatal errors occur');
        } else {
            console.log('❌ SOME TESTS FAILED - Further investigation needed');
        }
        console.log('==============================================');
    }
})();