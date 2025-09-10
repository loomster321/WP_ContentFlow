/**
 * WordPress Content Flow - Settings Persistence Fix Verification
 * 
 * This test validates that the Settings API fix resolves the dropdown persistence issue.
 * 
 * CRITICAL BUG BEING TESTED:
 * "When I change the default provider and press Save settings, 
 * the default provider goes back to the first setting even after having changed it."
 * 
 * EXPECTED BEHAVIOR AFTER FIX:
 * - Form POSTs to /wp-admin/options.php (WordPress standard)
 * - Settings persist after save and page reload
 * - Success message appears after save
 */

const { test, expect } = require('@playwright/test');

test.describe('WordPress Settings API Fix - Persistence Verification', () => {
    
    test.beforeEach(async ({ page }) => {
        // Enable detailed console logging
        page.on('console', msg => console.log('BROWSER:', msg.text()));
        page.on('pageerror', error => console.log('PAGE ERROR:', error));
        
        // Login to WordPress admin
        await page.goto('http://localhost:8080/wp-admin/');
        
        // Login form
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
        await page.click('#wp-submit');
        
        // Wait for dashboard load
        await expect(page.locator('h1')).toContainText('Dashboard');
        console.log('✓ Successfully logged into WordPress admin');
    });

    test('CRITICAL: Verify dropdown persistence after Settings API fix', async ({ page }) => {
        console.log('🚀 Starting critical dropdown persistence test...');
        
        // Navigate to settings page
        await page.goto('http://localhost:8080/wp-admin/admin.php?page=wp-content-flow-settings');
        await page.waitForLoadState('networkidle');
        
        // Wait for settings form to load
        await expect(page.locator('#wp-content-flow-settings-form')).toBeVisible();
        console.log('✓ Settings form loaded successfully');
        
        // STEP 1: Record initial values
        const providerDropdown = page.locator('#default-ai-provider-dropdown');
        const cacheCheckbox = page.locator('input[name="wp_content_flow_settings[cache_enabled]"]');
        
        await expect(providerDropdown).toBeVisible();
        await expect(cacheCheckbox).toBeVisible();
        
        const initialProvider = await providerDropdown.inputValue();
        const initialCacheState = await cacheCheckbox.isChecked();
        
        console.log(`📊 INITIAL VALUES:`);
        console.log(`   Default Provider: "${initialProvider}"`);
        console.log(`   Cache Enabled: ${initialCacheState}`);
        
        // STEP 2: Change values to different settings
        let newProvider, newCacheState;
        
        // Change provider to something different
        if (initialProvider === 'openai') {
            newProvider = 'anthropic';
        } else if (initialProvider === 'anthropic') {
            newProvider = 'google';
        } else {
            newProvider = 'openai';
        }
        
        // Toggle cache state
        newCacheState = !initialCacheState;
        
        console.log(`🔄 CHANGING VALUES TO:`);
        console.log(`   Default Provider: "${initialProvider}" → "${newProvider}"`);
        console.log(`   Cache Enabled: ${initialCacheState} → ${newCacheState}`);
        
        // Change dropdown value
        await providerDropdown.selectOption(newProvider);
        await page.waitForTimeout(500); // Allow UI to update
        
        // Verify dropdown changed
        const changedProvider = await providerDropdown.inputValue();
        expect(changedProvider).toBe(newProvider);
        console.log(`✓ Dropdown changed successfully to: ${changedProvider}`);
        
        // Change checkbox state
        if (newCacheState !== await cacheCheckbox.isChecked()) {
            await cacheCheckbox.click();
        }
        
        // Verify checkbox changed
        const changedCacheState = await cacheCheckbox.isChecked();
        expect(changedCacheState).toBe(newCacheState);
        console.log(`✓ Checkbox changed successfully to: ${changedCacheState}`);
        
        // STEP 3: CRITICAL - Monitor network request for Settings API fix
        const saveRequestPromise = page.waitForRequest(request => 
            request.url().includes('/wp-admin/options.php') && 
            request.method() === 'POST'
        );
        
        const saveResponsePromise = page.waitForResponse(response => 
            response.url().includes('/wp-admin/options.php') &&
            response.request().method() === 'POST'
        );
        
        console.log('🔍 Monitoring network requests for Settings API...');
        
        // Click Save Settings button
        const saveButton = page.locator('#wp-content-flow-submit-btn');
        await expect(saveButton).toBeVisible();
        await expect(saveButton).toBeEnabled();
        
        console.log('💾 Clicking Save Settings button...');
        await saveButton.click();
        
        // STEP 4: Verify Settings API network request
        try {
            const saveRequest = await saveRequestPromise;
            const saveResponse = await saveResponsePromise;
            
            console.log('✅ CRITICAL SUCCESS: Form posted to WordPress Settings API');
            console.log(`   Request URL: ${saveRequest.url()}`);
            console.log(`   Request Method: ${saveRequest.method()}`);
            console.log(`   Response Status: ${saveResponse.status()}`);
            
            // Verify it's posting to options.php (WordPress Settings API)
            expect(saveRequest.url()).toContain('/wp-admin/options.php');
            expect(saveRequest.method()).toBe('POST');
            
            // Check for successful response
            expect(saveResponse.status()).toBe(302); // WordPress redirect after settings save
            
        } catch (error) {
            console.error('❌ CRITICAL FAILURE: Settings API request not detected');
            throw new Error(`Settings API request failed: ${error.message}`);
        }
        
        // STEP 5: Wait for redirect and verify success message
        await page.waitForLoadState('networkidle');
        
        // Look for WordPress settings updated parameter
        const currentUrl = page.url();
        if (currentUrl.includes('settings-updated=true')) {
            console.log('✅ WordPress settings-updated parameter detected');
        }
        
        // Look for success message
        const successMessages = [
            '.notice-success',
            '.updated',
            '[class*="success"]',
            'text=Settings saved successfully',
            'text=Settings updated',
        ];
        
        let foundSuccessMessage = false;
        for (const selector of successMessages) {
            try {
                const element = page.locator(selector);
                if (await element.count() > 0) {
                    const messageText = await element.textContent();
                    console.log(`✅ Success message found: "${messageText.trim()}"`);
                    foundSuccessMessage = true;
                    break;
                }
            } catch (e) {
                // Continue checking other selectors
            }
        }
        
        if (!foundSuccessMessage) {
            console.log('⚠️  No success message detected, but continuing with persistence test...');
        }
        
        // STEP 6: CRITICAL PERSISTENCE TEST - Reload page and verify values persist
        console.log('🔄 Reloading page to test persistence...');
        await page.reload();
        await page.waitForLoadState('networkidle');
        
        // Wait for form elements to load
        await expect(page.locator('#wp-content-flow-settings-form')).toBeVisible();
        await expect(providerDropdown).toBeVisible();
        await expect(cacheCheckbox).toBeVisible();
        
        // STEP 7: Verify persistence of changed values
        const persistedProvider = await providerDropdown.inputValue();
        const persistedCacheState = await cacheCheckbox.isChecked();
        
        console.log(`📊 AFTER RELOAD VALUES:`);
        console.log(`   Default Provider: "${persistedProvider}" (expected: "${newProvider}")`);
        console.log(`   Cache Enabled: ${persistedCacheState} (expected: ${newCacheState})`);
        
        // CRITICAL ASSERTIONS - The fix should make these pass
        expect(persistedProvider).toBe(newProvider);
        expect(persistedCacheState).toBe(newCacheState);
        
        console.log('🎉 SUCCESS: Settings persistence verified!');
        console.log('   ✅ Dropdown value persisted correctly');
        console.log('   ✅ Checkbox state persisted correctly');
        console.log('   ✅ Form posts to WordPress Settings API (/wp-admin/options.php)');
        console.log('   ✅ Settings API fix resolves the user-reported issue');
    });

    test('Verify Settings API form structure and security', async ({ page }) => {
        console.log('🔍 Verifying Settings API form structure...');
        
        // Navigate to settings page
        await page.goto('http://localhost:8080/wp-admin/admin.php?page=wp-content-flow-settings');
        await page.waitForLoadState('networkidle');
        
        const settingsForm = page.locator('#wp-content-flow-settings-form');
        await expect(settingsForm).toBeVisible();
        
        // Verify form uses correct action (WordPress Settings API)
        const formAction = await settingsForm.getAttribute('action');
        console.log(`Form action: ${formAction}`);
        expect(formAction).toBe('options.php');
        
        // Verify form method is POST
        const formMethod = await settingsForm.getAttribute('method');
        console.log(`Form method: ${formMethod}`);
        expect(formMethod).toBe('post');
        
        // Verify WordPress Settings API fields are present
        const optionPageField = page.locator('input[name="option_page"]');
        const nonceField = page.locator('input[name="_wpnonce"]');
        const refererField = page.locator('input[name="_wp_http_referer"]');
        
        await expect(optionPageField).toBeVisible();
        await expect(nonceField).toBeVisible();
        await expect(refererField).toBeVisible();
        
        const optionPageValue = await optionPageField.inputValue();
        const nonceValue = await nonceField.inputValue();
        
        console.log(`Option page value: ${optionPageValue}`);
        console.log(`Nonce present: ${!!nonceValue}`);
        
        expect(optionPageValue).toBe('wp_content_flow_settings_group');
        expect(nonceValue.length).toBeGreaterThan(0);
        
        console.log('✅ WordPress Settings API form structure verified');
    });

    test('Test multiple save cycles for regression prevention', async ({ page }) => {
        console.log('🔄 Testing multiple save cycles...');
        
        await page.goto('http://localhost:8080/wp-admin/admin.php?page=wp-content-flow-settings');
        await page.waitForLoadState('networkidle');
        
        const providerDropdown = page.locator('#default-ai-provider-dropdown');
        const saveButton = page.locator('#wp-content-flow-submit-btn');
        
        const providers = ['openai', 'anthropic', 'google'];
        
        for (let i = 0; i < providers.length; i++) {
            const targetProvider = providers[i];
            console.log(`🔄 Save cycle ${i + 1}: Setting provider to "${targetProvider}"`);
            
            // Change dropdown
            await providerDropdown.selectOption(targetProvider);
            await page.waitForTimeout(300);
            
            // Save and wait for Settings API request
            const saveRequestPromise = page.waitForRequest(request => 
                request.url().includes('/wp-admin/options.php') && 
                request.method() === 'POST'
            );
            
            await saveButton.click();
            await saveRequestPromise;
            await page.waitForLoadState('networkidle');
            
            // Verify persistence
            const persistedValue = await providerDropdown.inputValue();
            expect(persistedValue).toBe(targetProvider);
            
            console.log(`   ✅ Cycle ${i + 1}: "${targetProvider}" persisted correctly`);
        }
        
        console.log('🎉 Multiple save cycles completed successfully');
    });

    test('Capture detailed debug information', async ({ page }) => {
        console.log('🔍 Capturing debug information...');
        
        await page.goto('http://localhost:8080/wp-admin/admin.php?page=wp-content-flow-settings');
        await page.waitForLoadState('networkidle');
        
        // Capture HTML debug comments
        const pageContent = await page.content();
        const debugComments = pageContent.match(/<!-- WP Content Flow Debug: .*? -->/g);
        
        if (debugComments) {
            console.log('📊 Debug information found in HTML:');
            debugComments.forEach(comment => {
                console.log(`   ${comment}`);
            });
        }
        
        // Capture current configuration display
        const configSection = page.locator('.wp-content-flow-info');
        if (await configSection.count() > 0) {
            const configText = await configSection.textContent();
            console.log('📋 Current Configuration:');
            console.log(configText.trim());
        }
        
        // Capture any JavaScript console output
        const consoleLogs = [];
        page.on('console', msg => {
            if (msg.text().includes('WP Content Flow')) {
                consoleLogs.push(msg.text());
            }
        });
        
        // Trigger some JavaScript activity
        await page.locator('#default-ai-provider-dropdown').click();
        await page.waitForTimeout(1000);
        
        if (consoleLogs.length > 0) {
            console.log('💬 JavaScript debug output:');
            consoleLogs.forEach(log => console.log(`   ${log}`));
        }
    });
});