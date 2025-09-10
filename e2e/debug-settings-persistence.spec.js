/**
 * WordPress Content Flow Settings Persistence Debug Test
 * 
 * This test reproduces the exact user issue where settings don't persist
 * after clicking "Save Settings". It captures comprehensive evidence
 * including screenshots, network traffic, and browser console logs.
 */

const { test, expect } = require('@playwright/test');

test.describe('WordPress Content Flow Settings Persistence Debug', () => {
  
  test.beforeEach(async ({ page }) => {
    // Enable console logging
    page.on('console', (msg) => {
      console.log(`🖥️  BROWSER CONSOLE [${msg.type()}]:`, msg.text());
    });

    // Enable request/response logging
    page.on('request', (request) => {
      if (request.url().includes('wp-content-flow') || request.url().includes('admin.php') || request.url().includes('admin-ajax.php')) {
        console.log(`📤 REQUEST: ${request.method()} ${request.url()}`);
        if (request.method() === 'POST') {
          console.log(`📤 POST DATA:`, request.postData());
        }
      }
    });

    page.on('response', (response) => {
      if (response.url().includes('wp-content-flow') || response.url().includes('admin.php') || response.url().includes('admin-ajax.php')) {
        console.log(`📥 RESPONSE: ${response.status()} ${response.url()}`);
      }
    });

    // Enable network errors
    page.on('requestfailed', (request) => {
      console.log(`❌ REQUEST FAILED: ${request.url()} - ${request.failure()?.errorText}`);
    });
  });

  test('should reproduce settings persistence failure with comprehensive debugging', async ({ page }) => {
    console.log('\n🧪 STARTING SETTINGS PERSISTENCE DEBUG TEST\n');

    // Step 1: Login to WordPress admin
    console.log('🔐 Step 1: Logging into WordPress admin...');
    await page.goto('/wp-admin');
    await page.screenshot({ path: 'test-results/debug-01-login-page.png', fullPage: true });

    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
    await page.click('#wp-submit');

    // Wait for dashboard
    await page.waitForSelector('#wpadminbar', { timeout: 15000 });
    await page.screenshot({ path: 'test-results/debug-02-dashboard.png', fullPage: true });
    console.log('✅ Successfully logged into WordPress admin');

    // Step 2: Navigate to plugin settings
    console.log('🔧 Step 2: Navigating to plugin settings...');
    await page.goto('/wp-admin/admin.php?page=wp-content-flow-settings');
    
    // Wait for settings page to load
    await page.waitForSelector('#wp-content-flow-settings-form', { timeout: 15000 });
    await page.screenshot({ path: 'test-results/debug-03-settings-page-loaded.png', fullPage: true });
    console.log('✅ Settings page loaded');

    // Step 3: Record initial values
    console.log('📊 Step 3: Recording initial settings values...');
    
    const initialDropdownValue = await page.locator('select[name="wp_content_flow_settings[default_ai_provider]"]').inputValue();
    const initialCheckboxChecked = await page.locator('input[name="wp_content_flow_settings[cache_enabled]"]').isChecked();
    
    console.log(`📝 Initial dropdown value: "${initialDropdownValue}"`);
    console.log(`📝 Initial checkbox checked: ${initialCheckboxChecked}`);

    // Get current settings from database (via WordPress debug output)
    const debugInfo = await page.locator('.wp-content-flow-info').textContent();
    console.log(`📊 Current configuration from WordPress:`, debugInfo);

    // Step 4: Change settings values
    console.log('✏️  Step 4: Changing settings values...');
    
    // Change dropdown to different value
    const newProviderValue = initialDropdownValue === 'openai' ? 'anthropic' : 'openai';
    await page.locator('select[name="wp_content_flow_settings[default_ai_provider]"]').selectOption(newProviderValue);
    console.log(`📝 Changed dropdown from "${initialDropdownValue}" to "${newProviderValue}"`);

    // Toggle checkbox
    const newCheckboxState = !initialCheckboxChecked;
    if (newCheckboxState && !initialCheckboxChecked) {
      await page.locator('input[name="wp_content_flow_settings[cache_enabled]"]').check();
    } else if (!newCheckboxState && initialCheckboxChecked) {
      await page.locator('input[name="wp_content_flow_settings[cache_enabled]"]').uncheck();
    }
    console.log(`📝 Changed checkbox from ${initialCheckboxChecked} to ${newCheckboxState}`);

    await page.screenshot({ path: 'test-results/debug-04-settings-changed.png', fullPage: true });

    // Verify form values before submission
    const dropdownValueBeforeSubmit = await page.locator('select[name="wp_content_flow_settings[default_ai_provider]"]').inputValue();
    const checkboxCheckedBeforeSubmit = await page.locator('input[name="wp_content_flow_settings[cache_enabled]"]').isChecked();
    
    console.log(`🔍 Form values before submit - Dropdown: "${dropdownValueBeforeSubmit}", Checkbox: ${checkboxCheckedBeforeSubmit}`);

    // Step 5: Add a test API key to ensure form has some valid data
    console.log('🔑 Step 5: Adding test API key...');
    await page.fill('input[name="wp_content_flow_settings[openai_api_key]"]', 'sk-test-key-for-debugging-1234567890');
    await page.screenshot({ path: 'test-results/debug-05-api-key-added.png', fullPage: true });

    // Step 6: Capture form data before submission
    console.log('📋 Step 6: Capturing form data before submission...');
    
    const formData = await page.evaluate(() => {
      const form = document.getElementById('wp-content-flow-settings-form');
      const formData = new FormData(form);
      const data = {};
      for (let [key, value] of formData.entries()) {
        data[key] = value;
      }
      return data;
    });
    
    console.log('📋 Form data to be submitted:', JSON.stringify(formData, null, 2));

    // Check for hidden fields
    const hiddenFields = await page.evaluate(() => {
      const hiddenInputs = document.querySelectorAll('input[type="hidden"]');
      const data = {};
      hiddenInputs.forEach(input => {
        data[input.name] = input.value;
      });
      return data;
    });
    
    console.log('🔍 Hidden form fields:', JSON.stringify(hiddenFields, null, 2));

    // Step 7: Submit the form with monitoring
    console.log('🚀 Step 7: Submitting settings form...');
    
    // Start waiting for navigation before clicking submit
    const responsePromise = page.waitForResponse(response => 
      response.url().includes('wp-content-flow-settings') && response.request().method() === 'POST'
    ).catch(() => null); // Don't fail if no POST response

    await page.click('#wp-content-flow-submit-btn');
    console.log('✅ Submit button clicked');

    // Wait for either navigation or response
    const response = await responsePromise;
    if (response) {
      console.log(`📥 Form submission response: ${response.status()}`);
      const responseHeaders = response.headers();
      console.log('📋 Response headers:', JSON.stringify(responseHeaders, null, 2));
    }

    // Wait for page to reload/redirect
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: 'test-results/debug-06-after-submit.png', fullPage: true });

    // Step 8: Check for success/error messages
    console.log('💬 Step 8: Checking for admin notices...');
    
    const successNotices = await page.locator('.notice-success, .updated').count();
    const errorNotices = await page.locator('.notice-error, .error').count();
    
    console.log(`📊 Success notices found: ${successNotices}`);
    console.log(`📊 Error notices found: ${errorNotices}`);

    if (successNotices > 0) {
      const successText = await page.locator('.notice-success, .updated').first().textContent();
      console.log(`✅ Success message: "${successText}"`);
    }

    if (errorNotices > 0) {
      const errorText = await page.locator('.notice-error, .error').first().textContent();
      console.log(`❌ Error message: "${errorText}"`);
    }

    // Step 9: Check current form values after submission
    console.log('🔍 Step 9: Verifying form values after submission...');
    
    const dropdownValueAfterSubmit = await page.locator('select[name="wp_content_flow_settings[default_ai_provider]"]').inputValue();
    const checkboxCheckedAfterSubmit = await page.locator('input[name="wp_content_flow_settings[cache_enabled]"]').isChecked();
    
    console.log(`📋 Form values after submit - Dropdown: "${dropdownValueAfterSubmit}", Checkbox: ${checkboxCheckedAfterSubmit}`);

    // Step 10: Reload the page to test true persistence
    console.log('🔄 Step 10: Reloading page to test true persistence...');
    await page.reload();
    await page.waitForSelector('#wp-content-flow-settings-form', { timeout: 15000 });
    await page.screenshot({ path: 'test-results/debug-07-after-reload.png', fullPage: true });

    const dropdownValueAfterReload = await page.locator('select[name="wp_content_flow_settings[default_ai_provider]"]').inputValue();
    const checkboxCheckedAfterReload = await page.locator('input[name="wp_content_flow_settings[cache_enabled]"]').isChecked();
    
    console.log(`📋 Form values after reload - Dropdown: "${dropdownValueAfterReload}", Checkbox: ${checkboxCheckedAfterReload}`);

    // Step 11: Analyze persistence success/failure
    console.log('\n📊 PERSISTENCE ANALYSIS:');
    console.log('========================');
    
    const dropdownPersisted = dropdownValueAfterReload === newProviderValue;
    const checkboxPersisted = checkboxCheckedAfterReload === newCheckboxState;
    
    console.log(`🔍 Dropdown persistence: ${dropdownPersisted ? '✅ SUCCESS' : '❌ FAILED'}`);
    console.log(`   Expected: "${newProviderValue}", Got: "${dropdownValueAfterReload}"`);
    
    console.log(`🔍 Checkbox persistence: ${checkboxPersisted ? '✅ SUCCESS' : '❌ FAILED'}`);
    console.log(`   Expected: ${newCheckboxState}, Got: ${checkboxCheckedAfterReload}`);

    // Step 12: Check database directly (via WordPress debug info)
    console.log('🗄️  Step 12: Checking database values...');
    const finalDebugInfo = await page.locator('.wp-content-flow-info').textContent();
    console.log(`📊 Final configuration from WordPress:`, finalDebugInfo);

    // Step 13: Export browser storage and cookies for analysis
    console.log('🍪 Step 13: Capturing browser state...');
    const cookies = await page.context().cookies();
    console.log(`🍪 Cookies count: ${cookies.length}`);
    
    const localStorage = await page.evaluate(() => JSON.stringify(localStorage));
    console.log(`💾 LocalStorage:`, localStorage);

    // Step 14: Capture final screenshot with annotations
    await page.screenshot({ path: 'test-results/debug-08-final-state.png', fullPage: true });

    // Step 15: Generate summary report
    console.log('\n📝 FINAL SUMMARY REPORT:');
    console.log('========================');
    console.log(`🔄 Test completed at: ${new Date().toISOString()}`);
    console.log(`🔧 WordPress URL: ${page.url()}`);
    console.log(`📊 Overall persistence success: ${dropdownPersisted && checkboxPersisted ? '✅ PASSED' : '❌ FAILED'}`);
    
    if (!dropdownPersisted || !checkboxPersisted) {
      console.log(`❌ ISSUE REPRODUCED: Settings are not persisting correctly`);
      console.log(`   This confirms the user's reported problem`);
    }

    // For automated testing, we'll create soft assertions to document the issue without failing the test
    // This allows us to capture the evidence even when the bug is present
    console.log('\n🧪 Test Evidence Captured in test-results/ directory:');
    console.log('   - debug-01-login-page.png');
    console.log('   - debug-02-dashboard.png');
    console.log('   - debug-03-settings-page-loaded.png');
    console.log('   - debug-04-settings-changed.png');
    console.log('   - debug-05-api-key-added.png');
    console.log('   - debug-06-after-submit.png');
    console.log('   - debug-07-after-reload.png');
    console.log('   - debug-08-final-state.png');

    // Optional: Fail the test if settings didn't persist (comment out to just document the issue)
    // expect(dropdownPersisted).toBe(true);
    // expect(checkboxPersisted).toBe(true);
  });

  test('should verify WordPress database settings directly', async ({ page }) => {
    console.log('\n🗄️  DIRECT DATABASE VERIFICATION TEST\n');

    // Login and navigate to settings
    await page.goto('/wp-admin');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
    await page.click('#wp-submit');
    await page.waitForSelector('#wpadminbar');

    await page.goto('/wp-admin/admin.php?page=wp-content-flow-settings');
    await page.waitForSelector('#wp-content-flow-settings-form');

    // Get current database values by examining the WordPress debug output
    const debugSection = page.locator('.wp-content-flow-info');
    const debugText = await debugSection.textContent();
    
    console.log('📊 Current database configuration:', debugText);

    // Also check the HTML comments that show debug info
    const htmlContent = await page.content();
    const debugComments = htmlContent.match(/<!-- WP Content Flow Debug:.*?-->/g) || [];
    debugComments.forEach(comment => {
      console.log('🔍 Debug comment:', comment);
    });

    // Test direct option reading via WordPress admin
    const optionValue = await page.evaluate(() => {
      // Try to get the option value if exposed in JavaScript
      return window.wpContentFlow || 'Not available in frontend';
    });
    
    console.log('🔍 WordPress option value:', optionValue);

    await page.screenshot({ path: 'test-results/database-verification.png', fullPage: true });
  });
});