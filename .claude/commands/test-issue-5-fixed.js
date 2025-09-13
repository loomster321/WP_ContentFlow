#!/usr/bin/env node

/**
 * Test for Issue #5 - AFTER FIX
 * This test verifies if the 500 error is resolved after applying the fix
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const CONFIG = {
    baseUrl: 'http://localhost:8080',
    username: 'admin',
    password: '!3cTXkh)9iDHhV5o*N',
    headless: false,
    screenshotDir: path.join(__dirname, '../../e2e/screenshots')
};

async function testIssue5AfterFix() {
    const browser = await chromium.launch({ headless: CONFIG.headless });
    const context = await browser.newContext();
    const page = await context.newPage();
    
    let testResult = {
        passed: false,
        error: null,
        networkErrors: [],
        consoleErrors: [],
        screenshots: []
    };
    
    // Monitor network for 500 errors
    page.on('response', response => {
        console.log(`  Response: ${response.status()} ${response.url().substring(0, 100)}`);
        if (response.status() >= 500) {
            const error = `500 ERROR: ${response.status()} on ${response.url()}`;
            console.error(`  ❌ ${error}`);
            testResult.networkErrors.push(error);
            testResult.error = error;
        }
    });
    
    // Monitor console errors
    page.on('console', msg => {
        if (msg.type() === 'error') {
            const text = msg.text();
            console.log(`  Console error: ${text.substring(0, 100)}`);
            testResult.consoleErrors.push(text);
            // Check for block validation errors
            if (text.includes('Block validation') || text.includes('isGenerating')) {
                testResult.error = `Block validation error: ${text}`;
            }
        }
    });
    
    try {
        console.log('\n1. Logging into WordPress...');
        await page.goto(`${CONFIG.baseUrl}/wp-login.php`);
        await page.fill('#user_login', CONFIG.username);
        await page.fill('#user_pass', CONFIG.password);
        await page.click('#wp-submit');
        await page.waitForURL('**/wp-admin/**', { timeout: 10000 });
        console.log('   ✅ Logged in');
        
        console.log('\n2. Creating new post...');
        await page.goto(`${CONFIG.baseUrl}/wp-admin/post-new.php`);
        await page.waitForTimeout(3000); // Let editor load
        
        // Take screenshot of editor
        const editorScreenshot = path.join(CONFIG.screenshotDir, 'issue-5-after-fix-editor.png');
        await page.screenshot({ path: editorScreenshot, fullPage: true });
        testResult.screenshots.push(editorScreenshot);
        console.log('   Screenshot saved: editor state');
        
        console.log('\n3. Adding content...');
        // Try to find the title field
        const titleSelectors = [
            'h1[aria-label="Add title"]',
            '.editor-post-title__input',
            '[placeholder="Add title"]',
            'h1.wp-block-post-title'
        ];
        
        for (const selector of titleSelectors) {
            try {
                await page.click(selector, { timeout: 2000 });
                await page.type(selector, 'Test Post After Fix');
                console.log(`   ✅ Title added using ${selector}`);
                break;
            } catch (e) {
                // Try next selector
            }
        }
        
        // Add some content
        await page.keyboard.press('Tab');
        await page.keyboard.type('This is test content after applying the fix.');
        
        console.log('\n4. Attempting to save...');
        // Try multiple save methods
        const saved = await attemptSave(page);
        
        if (!saved) {
            console.log('   Could not find save button, trying keyboard shortcut...');
            await page.keyboard.press('Control+S');
        }
        
        // Wait for save operation
        console.log('   Waiting for save response...');
        await page.waitForTimeout(5000);
        
        // Take screenshot after save attempt
        const afterSaveScreenshot = path.join(CONFIG.screenshotDir, 'issue-5-after-fix-saved.png');
        await page.screenshot({ path: afterSaveScreenshot, fullPage: true });
        testResult.screenshots.push(afterSaveScreenshot);
        
        // Check for success
        if (testResult.networkErrors.length === 0) {
            console.log('\n✅ SUCCESS: No 500 errors detected!');
            console.log('   The fix appears to have resolved the issue.');
            testResult.passed = true;
        } else {
            console.log('\n❌ FAILURE: 500 errors still occurring');
            console.log(`   Errors: ${testResult.networkErrors.join(', ')}`);
        }
        
        if (testResult.consoleErrors.length > 0) {
            console.log(`\n⚠️  Console errors detected: ${testResult.consoleErrors.length}`);
        }
        
    } catch (error) {
        console.error('\n❌ Test error:', error.message);
        testResult.error = error.message;
    } finally {
        await browser.close();
    }
    
    // Save results
    const resultsFile = path.join(CONFIG.screenshotDir, 'issue-5-after-fix-results.json');
    fs.writeFileSync(resultsFile, JSON.stringify(testResult, null, 2));
    console.log(`\nResults saved to: ${resultsFile}`);
    
    return testResult;
}

async function attemptSave(page) {
    const saveSelectors = [
        'button:has-text("Save draft")',
        'button:has-text("Save Draft")',
        'button:has-text("Publish")',
        'button:has-text("Update")',
        '.editor-post-save-draft',
        '.editor-post-publish-button',
        'button[aria-label="Save draft"]'
    ];
    
    for (const selector of saveSelectors) {
        try {
            const button = await page.locator(selector).first();
            if (await button.isVisible()) {
                await button.click();
                console.log(`   Clicked save using: ${selector}`);
                return true;
            }
        } catch (e) {
            // Try next selector
        }
    }
    return false;
}

// Run the test
console.log('=' .repeat(60));
console.log('ISSUE #5 - POST-FIX VERIFICATION TEST');
console.log('=' .repeat(60));
console.log('Testing if the 500 error is resolved after applying the fix...\n');

testIssue5AfterFix()
    .then(result => {
        if (result.passed) {
            console.log('\n' + '='.repeat(60));
            console.log('✅ FIX VERIFIED - Issue #5 is resolved!');
            console.log('='.repeat(60));
            process.exit(0);
        } else {
            console.log('\n' + '='.repeat(60));
            console.log('❌ FIX FAILED - Issue #5 still occurs');
            console.log('='.repeat(60));
            process.exit(1);
        }
    })
    .catch(error => {
        console.error('Fatal error:', error);
        process.exit(1);
    });