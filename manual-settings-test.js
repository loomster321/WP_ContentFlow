/**
 * Manual Browser Test for WordPress Content Flow Settings Save Functionality
 * 
 * This script uses Puppeteer to test the settings save workflow
 */

const puppeteer = require('puppeteer');

const WORDPRESS_URL = 'http://localhost:8080';
const LOGIN_URL = `${WORDPRESS_URL}/wp-admin/wp-login.php`;
const SETTINGS_URL = `${WORDPRESS_URL}/wp-admin/admin.php?page=wp-content-flow-settings`;
const USERNAME = 'admin';
const PASSWORD = '!3cTXkh)9iDHhV5o*N';

async function runSettingsTest() {
    let browser;
    
    try {
        console.log('🚀 Starting WordPress Content Flow Settings Test');
        
        // Launch browser
        browser = await puppeteer.launch({ 
            headless: false, 
            slowMo: 100,
            args: ['--no-sandbox', '--disable-setuid-sandbox'],
            devtools: true
        });
        
        const page = await browser.newPage();
        
        // Enable console logging from the page
        page.on('console', msg => console.log(`[BROWSER] ${msg.type()}: ${msg.text()}`));
        
        // Monitor network requests
        await page.setRequestInterception(true);
        page.on('request', request => {
            if (request.url().includes('wp-content-flow-settings') || request.method() === 'POST') {
                console.log(`[NETWORK] ${request.method()} ${request.url()}`);
                if (request.postData()) {
                    console.log(`[POST DATA] ${request.postData().substring(0, 200)}...`);
                }
            }
            request.continue();
        });
        
        page.on('response', response => {
            if (response.url().includes('wp-content-flow-settings') || response.status() >= 400) {
                console.log(`[RESPONSE] ${response.status()} ${response.url()}`);
            }
        });
        
        // Login to WordPress
        console.log('📝 Logging into WordPress admin');
        await page.goto(LOGIN_URL, { waitUntil: 'networkidle2' });
        
        await page.type('#user_login', USERNAME);
        await page.type('#user_pass', PASSWORD);
        await page.click('#wp-submit');
        
        // Wait for admin dashboard
        await page.waitForNavigation({ waitUntil: 'networkidle2' });
        console.log('✅ Successfully logged in');
        
        // Navigate to settings page
        console.log('🔧 Navigating to WP Content Flow settings page');
        await page.goto(SETTINGS_URL, { waitUntil: 'networkidle2' });
        
        // Take screenshot of initial state
        await page.screenshot({ path: 'test-results/01-initial-settings.png', fullPage: true });
        
        // Wait for form to load
        await page.waitForSelector('#wp-content-flow-settings-form');
        await page.waitForSelector('#default-ai-provider-dropdown');
        console.log('✅ Settings form loaded');
        
        // Check current dropdown value
        const initialProvider = await page.$eval('#default-ai-provider-dropdown', el => el.value);
        console.log(`📊 Initial provider value: ${initialProvider}`);
        
        // Get all form values initially
        const initialValues = await page.evaluate(() => {
            const form = document.querySelector('#wp-content-flow-settings-form');
            const formData = new FormData(form);
            const values = {};
            for (let [key, value] of formData.entries()) {
                values[key] = value;
            }
            return values;
        });
        console.log('📊 Initial form values:', initialValues);
        
        // Change the provider dropdown to a different value
        const targetProvider = initialProvider === 'openai' ? 'anthropic' : 'openai';
        console.log(`🔄 Changing provider from ${initialProvider} to ${targetProvider}`);
        
        await page.select('#default-ai-provider-dropdown', targetProvider);
        
        // Verify the change was applied
        const changedProvider = await page.$eval('#default-ai-provider-dropdown', el => el.value);
        console.log(`✅ Dropdown changed to: ${changedProvider}`);
        
        // Wait a moment for any JavaScript to process
        await page.waitForTimeout(500);
        
        // Take screenshot before save
        await page.screenshot({ path: 'test-results/02-before-save.png', fullPage: true });
        
        // Monitor for success/error messages
        const messagePromise = page.evaluate(() => {
            return new Promise((resolve) => {
                const observer = new MutationObserver((mutations) => {
                    mutations.forEach((mutation) => {
                        mutation.addedNodes.forEach((node) => {
                            if (node.nodeType === 1 && node.classList && 
                                (node.classList.contains('notice') || 
                                 node.classList.contains('updated') || 
                                 node.classList.contains('error'))) {
                                resolve({
                                    type: node.classList.contains('notice-success') || node.classList.contains('updated') ? 'success' : 'error',
                                    text: node.textContent.trim()
                                });
                            }
                        });
                    });
                });
                
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
                
                // Also resolve after 10 seconds if no message appears
                setTimeout(() => resolve({ type: 'timeout', text: 'No message appeared' }), 10000);
            });
        });
        
        // Click the Save Settings button
        console.log('💾 Clicking Save Settings button');
        const saveButton = await page.$('#wp-content-flow-submit-btn');
        await saveButton.click();
        
        // Wait for the page to process the form submission
        try {
            await Promise.race([
                page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 15000 }),
                page.waitForTimeout(8000)
            ]);
        } catch (e) {
            console.log('⚠️ Navigation/timeout occurred during save');
        }
        
        // Take screenshot after save
        await page.screenshot({ path: 'test-results/03-after-save.png', fullPage: true });
        
        // Check for success/error messages
        const messages = await page.$$eval('.notice, .updated, .error', elements => 
            elements.map(el => ({
                type: el.classList.contains('notice-success') || el.classList.contains('updated') ? 'success' : 'error',
                text: el.textContent.trim()
            }))
        );
        
        console.log('📨 Messages found:', messages);
        
        // Check dropdown value after save
        const savedProvider = await page.$eval('#default-ai-provider-dropdown', el => el.value);
        console.log(`📊 Provider value after save: ${savedProvider}`);
        
        if (savedProvider !== targetProvider) {
            console.error(`❌ ISSUE FOUND: Dropdown reverted from ${targetProvider} to ${savedProvider}`);
        } else {
            console.log('✅ Dropdown value maintained after save');
        }
        
        // Test persistence by reloading the page
        console.log('🔄 Reloading page to test persistence...');
        await page.reload({ waitUntil: 'networkidle2' });
        
        // Wait for form to load again
        await page.waitForSelector('#default-ai-provider-dropdown');
        
        // Check value after reload
        const persistedProvider = await page.$eval('#default-ai-provider-dropdown', el => el.value);
        console.log(`📊 Provider value after page reload: ${persistedProvider}`);
        
        // Take final screenshot
        await page.screenshot({ path: 'test-results/04-after-reload.png', fullPage: true });
        
        // Check database value display
        const dbValueText = await page.$eval('#default-ai-provider-dropdown + p.description', el => el.textContent);
        console.log('🗄️ Database value display:', dbValueText);
        
        // Test results
        console.log('\n' + '='.repeat(60));
        console.log('📊 TEST RESULTS');
        console.log('='.repeat(60));
        console.log(`Initial provider: ${initialProvider}`);
        console.log(`Target provider: ${targetProvider}`);
        console.log(`After save: ${savedProvider}`);
        console.log(`After reload: ${persistedProvider}`);
        console.log(`Success messages: ${messages.filter(m => m.type === 'success').length}`);
        console.log(`Error messages: ${messages.filter(m => m.type === 'error').length}`);
        
        if (savedProvider !== targetProvider) {
            console.log('❌ SAVE ISSUE: Dropdown reverted immediately after save');
        }
        
        if (persistedProvider !== targetProvider) {
            console.log('❌ PERSISTENCE ISSUE: Value not saved to database');
        }
        
        if (savedProvider === targetProvider && persistedProvider === targetProvider) {
            console.log('✅ ALL TESTS PASSED: Settings save and persistence working correctly');
        }
        
        // Wait for user to inspect results
        console.log('\n⏳ Waiting 30 seconds for inspection. Browser will remain open...');
        await page.waitForTimeout(30000);
        
    } catch (error) {
        console.error('❌ Test failed:', error);
    } finally {
        if (browser) {
            await browser.close();
        }
    }
}

// Check if puppeteer is available, if not provide instructions
async function checkDependencies() {
    try {
        require('puppeteer');
        return true;
    } catch (error) {
        console.log('❌ Puppeteer not found. Please install it with:');
        console.log('npm install puppeteer');
        return false;
    }
}

async function main() {
    if (await checkDependencies()) {
        await runSettingsTest();
    }
}

main().catch(console.error);