const { test, expect } = require('@playwright/test');

test.describe('WP Content Flow Settings - Final Critical Persistence Test', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to WordPress admin login
    await page.goto('http://localhost:8080/wp-admin/');
    
    // Login with admin credentials
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
    await page.click('#wp-submit');
    
    // Wait for admin dashboard to load
    await page.waitForSelector('#wpbody');
  });

  test('CRITICAL: Settings persist after save and page reload - Original User Issue', async ({ page }) => {
    console.log('🔍 Starting CRITICAL persistence test for user-reported issue...');
    
    // Step 1: Navigate to settings page
    console.log('📍 Step 1: Navigating to WP Content Flow settings page...');
    await page.goto('http://localhost:8080/wp-admin/admin.php?page=wp-content-flow-settings');
    await page.waitForSelector('h1:has-text("WP Content Flow Settings")', { timeout: 10000 });
    
    // Step 2: Record initial state
    console.log('📋 Step 2: Recording initial settings state...');
    
    // Wait for form to be fully loaded
    await page.waitForSelector('select[name="default_provider"]', { timeout: 10000 });
    await page.waitForSelector('input[name="enable_caching"]', { timeout: 5000 });
    
    const initialProvider = await page.selectOption('select[name="default_provider"]', []);
    const initialCaching = await page.isChecked('input[name="enable_caching"]');
    
    console.log(`📊 Initial state - Provider: ${initialProvider}, Caching: ${initialCaching}`);
    
    // Take screenshot of initial state
    await page.screenshot({ 
      path: '/home/timl/dev/WP_ContentFlow/tmp/01-initial-settings-state.png',
      fullPage: true 
    });
    
    // Step 3: Change provider to OpenAI (the critical test)
    console.log('🔄 Step 3: Changing Default AI Provider to OpenAI...');
    await page.selectOption('select[name="default_provider"]', 'openai');
    
    // Verify the selection was made
    const selectedProvider = await page.inputValue('select[name="default_provider"]');
    console.log(`✅ Provider changed to: ${selectedProvider}`);
    expect(selectedProvider).toBe('openai');
    
    // Step 4: Toggle caching setting
    console.log('🔄 Step 4: Toggling caching setting...');
    const newCachingState = !initialCaching;
    await page.setChecked('input[name="enable_caching"]', newCachingState);
    
    // Verify the change
    const currentCaching = await page.isChecked('input[name="enable_caching"]');
    console.log(`✅ Caching changed to: ${currentCaching}`);
    expect(currentCaching).toBe(newCachingState);
    
    // Take screenshot before save
    await page.screenshot({ 
      path: '/home/timl/dev/WP_ContentFlow/tmp/02-before-save-changes.png',
      fullPage: true 
    });
    
    // Step 5: Click Save Settings (THE CRITICAL MOMENT)
    console.log('💾 Step 5: Clicking Save Settings button...');
    
    // Listen for the form submission
    const responsePromise = page.waitForResponse(response => 
      response.url().includes('admin.php?page=wp-content-flow-settings') &&
      response.request().method() === 'POST'
    );
    
    await page.click('input[type="submit"][name="submit"]');
    
    // Wait for the response
    const response = await responsePromise;
    console.log(`📡 Form submission response: ${response.status()}`);
    
    // Step 6: Wait for success message or page reload
    console.log('⏳ Step 6: Waiting for save confirmation...');
    
    try {
      // Look for WordPress admin success message
      await page.waitForSelector('.notice-success, .updated', { timeout: 5000 });
      console.log('✅ Success message detected');
    } catch (e) {
      console.log('⚠️ No success message found, continuing...');
    }
    
    // Take screenshot after save
    await page.screenshot({ 
      path: '/home/timl/dev/WP_ContentFlow/tmp/03-after-save-response.png',
      fullPage: true 
    });
    
    // Step 7: CRITICAL TEST - Reload the page (F5 equivalent)
    console.log('🔄 Step 7: CRITICAL TEST - Reloading page to test persistence...');
    await page.reload({ waitUntil: 'networkidle' });
    
    // Wait for form to load again
    await page.waitForSelector('select[name="default_provider"]', { timeout: 10000 });
    await page.waitForSelector('input[name="enable_caching"]', { timeout: 5000 });
    
    // Step 8: VERIFY PERSISTENCE (This is the user's exact issue)
    console.log('🔍 Step 8: VERIFYING SETTINGS PERSISTENCE...');
    
    const persistedProvider = await page.inputValue('select[name="default_provider"]');
    const persistedCaching = await page.isChecked('input[name="enable_caching"]');
    
    console.log(`🔍 After reload - Provider: ${persistedProvider}, Caching: ${persistedCaching}`);
    console.log(`🎯 Expected - Provider: openai, Caching: ${newCachingState}`);
    
    // Take final screenshot
    await page.screenshot({ 
      path: '/home/timl/dev/WP_ContentFlow/tmp/04-final-persistence-test.png',
      fullPage: true 
    });
    
    // THE CRITICAL ASSERTIONS - This is exactly what the user reported was failing
    console.log('🎯 CRITICAL ASSERTIONS:');
    
    // Test 1: Provider should NOT revert to "first setting"
    console.log(`Test 1: Provider persistence - Expected: openai, Got: ${persistedProvider}`);
    expect(persistedProvider).toBe('openai');
    
    // Test 2: Caching should maintain user's selection
    console.log(`Test 2: Caching persistence - Expected: ${newCachingState}, Got: ${persistedCaching}`);
    expect(persistedCaching).toBe(newCachingState);
    
    console.log('🎉 SUCCESS: Settings persistence test PASSED!');
    console.log('✅ Original user issue is RESOLVED');
    console.log('✅ Settings no longer revert after save and reload');
  });

  test('Additional verification: Multiple provider changes persist', async ({ page }) => {
    console.log('🔍 Additional test: Multiple provider changes...');
    
    await page.goto('http://localhost:8080/wp-admin/admin.php?page=wp-content-flow-settings');
    await page.waitForSelector('select[name="default_provider"]', { timeout: 10000 });
    
    // Test changing to different providers
    const providers = ['openai', 'anthropic', 'google'];
    
    for (const provider of providers) {
      console.log(`🔄 Testing provider: ${provider}`);
      
      // Change provider
      await page.selectOption('select[name="default_provider"]', provider);
      
      // Save
      await page.click('input[type="submit"][name="submit"]');
      
      // Wait a moment for save
      await page.waitForTimeout(2000);
      
      // Reload
      await page.reload({ waitUntil: 'networkidle' });
      await page.waitForSelector('select[name="default_provider"]', { timeout: 10000 });
      
      // Verify persistence
      const persistedProvider = await page.inputValue('select[name="default_provider"]');
      console.log(`✅ ${provider} persisted as: ${persistedProvider}`);
      expect(persistedProvider).toBe(provider);
    }
    
    console.log('🎉 Multiple provider changes test PASSED!');
  });
});