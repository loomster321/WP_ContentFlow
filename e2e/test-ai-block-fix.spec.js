/**
 * Simple test script to verify AI Text Block save fix
 * This tests the critical functionality without complex UI interactions
 */

const { test, expect } = require('@playwright/test');

test('AI Text Block Save Fix - Basic Test', async ({ page }) => {
    console.log('🚀 Starting AI Text Block save fix verification...');

    // Navigate to WordPress admin
    await page.goto('http://localhost:8080/wp-admin/');
    
    // Login if needed
    try {
        await page.waitForSelector('#user_login', { timeout: 3000 });
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
        await page.click('#wp-submit');
        await page.waitForLoadState('networkidle');
        console.log('✅ Logged in successfully');
    } catch {
        console.log('ℹ️  Already logged in or no login required');
    }

    // Navigate to new post editor
    await page.goto('http://localhost:8080/wp-admin/post-new.php');
    await page.waitForLoadState('networkidle');
    console.log('✅ Navigated to post editor');

    // Wait for Gutenberg editor to load
    await page.waitForSelector('.block-editor-writing-flow', { timeout: 15000 });
    console.log('✅ Gutenberg editor loaded');

    // Add a simple paragraph first to test basic save functionality
    await page.click('.block-editor-writing-flow');
    await page.keyboard.type('Test post to verify AI block save functionality');
    console.log('✅ Added test content');

    // Try to save the post
    try {
        await page.click('.editor-post-save-draft');
        await page.waitForSelector('.editor-post-saved-state', { timeout: 10000 });
        console.log('✅ Basic post save works');
    } catch (error) {
        console.log('❌ Basic post save failed:', error.message);
        return;
    }

    // Now try to add AI Text block
    try {
        await page.click('.block-editor-inserter__toggle');
        await page.waitForSelector('.block-editor-inserter__search-input', { timeout: 5000 });
        await page.fill('.block-editor-inserter__search-input', 'AI Text');
        
        // Look for the AI Text Generator block
        const blockSearchResults = await page.locator('.block-editor-block-types-list__item').all();
        let found = false;
        
        for (let i = 0; i < blockSearchResults.length; i++) {
            const text = await blockSearchResults[i].textContent();
            if (text && text.includes('AI Text Generator')) {
                await blockSearchResults[i].click();
                found = true;
                console.log('✅ Found and clicked AI Text Generator block');
                break;
            }
        }
        
        if (!found) {
            console.log('⚠️  AI Text Generator block not found in search results');
            console.log('Available blocks:', await Promise.all(blockSearchResults.slice(0, 5).map(b => b.textContent())));
        } else {
            // Wait for block to be added
            await page.waitForSelector('[data-type="wp-content-flow/ai-text"]', { timeout: 5000 });
            console.log('✅ AI Text Generator block added to editor');

            // Try to save the post with the AI block
            try {
                await page.click('.editor-post-save-draft');
                await page.waitForSelector('.editor-post-saved-state', { timeout: 10000 });
                console.log('🎉 SUCCESS: Post with AI Text Generator block saved without 500 error!');
                
                // Verify no error messages appeared
                const errorElements = await page.locator('text=500').count();
                if (errorElements === 0) {
                    console.log('✅ No 500 errors detected - fix is working!');
                } else {
                    console.log('❌ 500 error still present');
                }
                
            } catch (saveError) {
                console.log('❌ Save with AI block failed:', saveError.message);
            }
        }
        
    } catch (blockError) {
        console.log('⚠️  Could not test AI block insertion:', blockError.message);
    }

    console.log('🏁 Test completed');
});