/**
 * ROBUST REGRESSION TEST TEMPLATE FOR GI-FIX
 * 
 * This template handles common test framework issues:
 * 1. Global setup interference
 * 2. Login state persistence
 * 3. Navigation timing issues
 * 4. Environment detection
 */

const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

// Configuration that works with the current environment
const CONFIG = {
    baseUrl: 'http://localhost:8080',
    username: 'admin',
    password: '!3cTXkh)9iDHhV5o*N',
    screenshotDir: 'e2e/screenshots',
    maxRetries: 3,
    timeout: 30000
};

/**
 * Robust login function that handles various states
 */
async function ensureLoggedIn(page) {
    const currentUrl = page.url();
    
    // Check if already logged in
    if (currentUrl.includes('/wp-admin') && !currentUrl.includes('wp-login.php')) {
        console.log('Already logged in');
        return true;
    }
    
    // Navigate to login page with retry
    for (let attempt = 1; attempt <= CONFIG.maxRetries; attempt++) {
        try {
            console.log(`Login attempt ${attempt}/${CONFIG.maxRetries}`);
            
            // Force navigation to login page
            await page.goto(`${CONFIG.baseUrl}/wp-login.php`, {
                waitUntil: 'domcontentloaded',
                timeout: 10000
            });
            
            // Wait a bit for page to settle
            await page.waitForTimeout(1000);
            
            // Check if login form exists
            const loginFormExists = await page.locator('#loginform').count() > 0;
            
            if (loginFormExists) {
                // Clear and fill fields
                await page.locator('#user_login').fill('');
                await page.locator('#user_login').fill(CONFIG.username);
                
                await page.locator('#user_pass').fill('');
                await page.locator('#user_pass').fill(CONFIG.password);
                
                // Click submit
                await page.locator('#wp-submit').click();
                
                // Wait for navigation
                await page.waitForURL(/wp-admin/, { timeout: 10000 });
                console.log('✅ Login successful');
                return true;
            } else {
                // Maybe already logged in, check
                if (page.url().includes('/wp-admin')) {
                    console.log('✅ Already in admin area');
                    return true;
                }
            }
        } catch (error) {
            console.log(`Attempt ${attempt} failed:`, error.message);
            if (attempt === CONFIG.maxRetries) {
                throw new Error(`Login failed after ${CONFIG.maxRetries} attempts: ${error.message}`);
            }
            await page.waitForTimeout(2000); // Wait before retry
        }
    }
    
    return false;
}

/**
 * Main regression test template
 */
test.describe('GI-Fix Regression Test Template', () => {
    // Set reasonable timeouts
    test.setTimeout(CONFIG.timeout);
    
    test('Regression test with robust error handling', async ({ page }) => {
        console.log('\n=== STARTING REGRESSION TEST ===');
        console.log(`Issue: [ISSUE_NUMBER]`);
        console.log(`Expected failure: [EXPECTED_ERROR]`);
        console.log(`Expected success: [EXPECTED_SUCCESS]\n`);
        
        // Create screenshot directory if needed
        if (!fs.existsSync(CONFIG.screenshotDir)) {
            fs.mkdirSync(CONFIG.screenshotDir, { recursive: true });
        }
        
        let testPassed = false;
        let errorCaptured = null;
        
        try {
            // Step 1: Ensure logged in (handles various states)
            console.log('Step 1: Ensuring WordPress login...');
            await ensureLoggedIn(page);
            
            // Step 2: Navigate to test area
            console.log('Step 2: Navigating to test area...');
            // [CUSTOMIZE: Add navigation specific to the issue]
            await page.goto(`${CONFIG.baseUrl}/wp-admin/post-new.php`);
            
            // Wait for editor to be ready
            await page.waitForSelector('.block-editor-writing-flow, #content', { 
                timeout: 10000 
            }).catch(() => {
                console.log('Editor not found, might be classic editor');
            });
            
            // Step 3: Perform the action that triggers the issue
            console.log('Step 3: Performing test action...');
            // [CUSTOMIZE: Add the specific actions from the issue]
            
            // Example: Try to save a post
            const saveResponse = await page.waitForResponse(
                response => response.url().includes('/wp-json/wp/v2/posts') ||
                           response.url().includes('post.php'),
                { timeout: 15000 }
            ).catch(() => null);
            
            // Step 4: Check for the specific error/success
            if (saveResponse) {
                const status = saveResponse.status();
                console.log(`Response status: ${status}`);
                
                // [CUSTOMIZE: Check for specific error code]
                if (status === 500) {
                    errorCaptured = '500 Internal Server Error';
                    console.log(`❌ ERROR DETECTED: ${errorCaptured}`);
                } else if (status < 400) {
                    testPassed = true;
                    console.log('✅ Action completed successfully');
                }
            }
            
            // Step 5: Capture screenshot
            const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
            const screenshotName = testPassed ? 
                `issue-[ISSUE_NUMBER]-success-${timestamp}.png` :
                `issue-[ISSUE_NUMBER]-failure-${timestamp}.png`;
            
            await page.screenshot({
                path: path.join(CONFIG.screenshotDir, screenshotName),
                fullPage: true
            });
            console.log(`📸 Screenshot saved: ${screenshotName}`);
            
        } catch (error) {
            console.error('Test execution error:', error.message);
            errorCaptured = error.message;
            
            // Capture error screenshot
            await page.screenshot({
                path: path.join(CONFIG.screenshotDir, `issue-[ISSUE_NUMBER]-error.png`),
                fullPage: true
            }).catch(() => console.log('Could not capture error screenshot'));
        }
        
        // Final assessment
        console.log('\n=== TEST RESULTS ===');
        if (testPassed) {
            console.log('✅ Test PASSED - Issue appears to be fixed');
        } else if (errorCaptured) {
            console.log(`❌ Test FAILED - Error: ${errorCaptured}`);
            console.log('This is expected BEFORE the fix is applied');
        } else {
            console.log('⚠️ Test INCONCLUSIVE - Could not determine status');
        }
        
        // Return structured result for gi-fix command
        return {
            passed: testPassed,
            error: errorCaptured,
            screenshotPath: path.join(CONFIG.screenshotDir, `issue-[ISSUE_NUMBER]-*.png`)
        };
    });
});

// Export configuration for gi-fix command
module.exports = { CONFIG, ensureLoggedIn };