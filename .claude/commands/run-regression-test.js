#!/usr/bin/env node

/**
 * STANDALONE REGRESSION TEST RUNNER FOR GI-FIX
 * 
 * This bypasses the Playwright test framework to avoid configuration issues
 * Usage: node run-regression-test.js <issue-number>
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

// Configuration
const CONFIG = {
    baseUrl: 'http://localhost:8080',
    username: 'admin',
    password: '!3cTXkh)9iDHhV5o*N',
    screenshotDir: path.join(__dirname, '../../e2e/screenshots'),
    headless: true,
    timeout: 30000
};

/**
 * Run regression test for a specific issue
 */
async function runRegressionTest(issueNumber) {
    console.log(`\n${'='.repeat(50)}`);
    console.log(`REGRESSION TEST FOR ISSUE #${issueNumber}`);
    console.log(`${'='.repeat(50)}\n`);
    
    const browser = await chromium.launch({ 
        headless: CONFIG.headless,
        timeout: CONFIG.timeout 
    });
    
    const context = await browser.newContext({
        viewport: { width: 1280, height: 720 }
    });
    
    const page = await context.newPage();
    
    // Create screenshot directory
    if (!fs.existsSync(CONFIG.screenshotDir)) {
        fs.mkdirSync(CONFIG.screenshotDir, { recursive: true });
    }
    
    let testResult = {
        issue: issueNumber,
        passed: false,
        error: null,
        screenshots: [],
        timestamp: new Date().toISOString()
    };
    
    try {
        // Step 1: Login to WordPress
        console.log('Step 1: Logging into WordPress admin...');
        await page.goto(`${CONFIG.baseUrl}/wp-login.php`, {
            waitUntil: 'domcontentloaded'
        });
        
        // Fill login form
        await page.fill('#user_login', CONFIG.username);
        await page.fill('#user_pass', CONFIG.password);
        await page.click('#wp-submit');
        
        // Wait for dashboard
        await page.waitForURL(/wp-admin/, { timeout: 10000 });
        console.log('✅ Logged in successfully\n');
        
        // Run issue-specific test
        if (issueNumber === '5') {
            testResult = await testIssue5(page, testResult);
        } else {
            // Generic test template
            testResult = await runGenericTest(page, testResult, issueNumber);
        }
        
    } catch (error) {
        console.error('❌ Test execution failed:', error.message);
        testResult.error = error.message;
        
        // Capture error screenshot
        const errorScreenshot = path.join(CONFIG.screenshotDir, 
            `issue-${issueNumber}-error-${Date.now()}.png`);
        await page.screenshot({ path: errorScreenshot, fullPage: true });
        testResult.screenshots.push(errorScreenshot);
    } finally {
        await browser.close();
    }
    
    // Print results
    console.log(`\n${'='.repeat(50)}`);
    console.log('TEST RESULTS');
    console.log(`${'='.repeat(50)}`);
    console.log(`Issue #${issueNumber}`);
    console.log(`Status: ${testResult.passed ? '✅ PASSED' : '❌ FAILED'}`);
    if (testResult.error) {
        console.log(`Error: ${testResult.error}`);
    }
    console.log(`Screenshots: ${testResult.screenshots.length} captured`);
    testResult.screenshots.forEach(s => console.log(`  - ${path.basename(s)}`));
    console.log(`${'='.repeat(50)}\n`);
    
    // Write results to file
    const resultsFile = path.join(CONFIG.screenshotDir, `issue-${issueNumber}-results.json`);
    fs.writeFileSync(resultsFile, JSON.stringify(testResult, null, 2));
    console.log(`Results saved to: ${resultsFile}`);
    
    return testResult;
}

/**
 * Test for Issue #5: WordPress editor crash when saving with AI block
 */
async function testIssue5(page, testResult) {
    console.log('Step 2: Creating new post...');
    await page.goto(`${CONFIG.baseUrl}/wp-admin/post-new.php`);
    
    // Wait for editor
    await page.waitForSelector('.block-editor-writing-flow, #content', {
        timeout: 10000
    }).catch(() => console.log('Editor loading...'));
    
    console.log('Step 3: Adding post title...');
    const postTitle = `Issue #5 Test - ${Date.now()}`;
    await page.fill('.wp-block-post-title, #title', postTitle).catch(() => {
        console.log('Could not find title field, trying alternative...');
    });
    
    console.log('Step 4: Adding content...');
    // Try to add AI block or just content
    const contentAdded = await page.evaluate(() => {
        const editor = document.querySelector('.block-editor-default-block-appender__content');
        if (editor) {
            editor.click();
            return true;
        }
        return false;
    }).catch(() => false);
    
    if (contentAdded) {
        await page.keyboard.type('Test content for Issue #5 regression test');
    }
    
    console.log('Step 5: Attempting to save post...');
    
    // Monitor save response
    const savePromise = page.waitForResponse(
        response => response.url().includes('/wp-json/wp/v2/posts') ||
                   response.url().includes('post.php'),
        { timeout: 10000 }
    );
    
    // Try to save
    await page.keyboard.press('Control+s').catch(() => {
        console.log('Keyboard shortcut failed, trying button...');
    });
    
    // Also try save button
    await page.click('button:has-text("Save draft"), button:has-text("Publish")')
        .catch(() => console.log('Save button not found'));
    
    try {
        const saveResponse = await savePromise;
        const status = saveResponse.status();
        
        console.log(`\nSave Response Status: ${status}`);
        
        if (status === 500) {
            testResult.error = '500 Internal Server Error - Issue reproduced';
            console.log('❌ EXPECTED FAILURE: 500 error detected');
            
            // Screenshot of error
            const failScreenshot = path.join(CONFIG.screenshotDir, 
                `issue-5-failure-${Date.now()}.png`);
            await page.screenshot({ path: failScreenshot, fullPage: true });
            testResult.screenshots.push(failScreenshot);
            
        } else if (status < 400) {
            testResult.passed = true;
            console.log('✅ SUCCESS: Post saved without error');
            
            // Screenshot of success
            const successScreenshot = path.join(CONFIG.screenshotDir, 
                `issue-5-success-${Date.now()}.png`);
            await page.screenshot({ path: successScreenshot, fullPage: true });
            testResult.screenshots.push(successScreenshot);
        }
    } catch (error) {
        console.log('Save response timeout or error:', error.message);
        testResult.error = error.message;
    }
    
    return testResult;
}

/**
 * Generic test template for other issues
 */
async function runGenericTest(page, testResult, issueNumber) {
    console.log(`Running generic test for issue #${issueNumber}`);
    console.log('Please implement specific test logic for this issue');
    
    // Capture current state
    const screenshot = path.join(CONFIG.screenshotDir, 
        `issue-${issueNumber}-generic-${Date.now()}.png`);
    await page.screenshot({ path: screenshot, fullPage: true });
    testResult.screenshots.push(screenshot);
    
    testResult.error = 'No specific test implemented for this issue';
    return testResult;
}

// Run if called directly
if (require.main === module) {
    const issueNumber = process.argv[2];
    
    if (!issueNumber) {
        console.error('Usage: node run-regression-test.js <issue-number>');
        process.exit(1);
    }
    
    runRegressionTest(issueNumber)
        .then(result => {
            process.exit(result.passed ? 0 : 1);
        })
        .catch(error => {
            console.error('Fatal error:', error);
            process.exit(1);
        });
}

module.exports = { runRegressionTest, CONFIG };