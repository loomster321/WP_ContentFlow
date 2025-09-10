/**
 * Comprehensive E2E tests for WordPress Content Flow plugin settings save functionality
 * 
 * Tests the complete workflow of changing settings, clicking save, and verifying persistence
 */

const { test, expect } = require('@playwright/test');

// WordPress admin credentials
const ADMIN_URL = 'http://localhost:8080/wp-admin';
const LOGIN_URL = 'http://localhost:8080/wp-admin/wp-login.php';
const SETTINGS_URL = 'http://localhost:8080/wp-admin/admin.php?page=wp-content-flow-settings';
const USERNAME = 'admin';
const PASSWORD = '!3cTXkh)9iDHhV5o*N';

test.describe('WP Content Flow Settings Save Functionality', () => {
  let context;
  let page;
  
  test.beforeAll(async ({ browser }) => {
    // Create persistent context to maintain login state
    context = await browser.newContext({
      // Enable network monitoring
      recordVideo: {
        dir: 'test-results/',
        size: { width: 1280, height: 720 }
      }
    });
    
    page = await context.newPage();
    
    // Enable console logging
    page.on('console', msg => {
      console.log(`[BROWSER] ${msg.type()}: ${msg.text()}`);
    });
    
    // Monitor network requests
    page.on('request', request => {
      if (request.url().includes('wp-content-flow-settings') || request.method() === 'POST') {
        console.log(`[NETWORK] ${request.method()} ${request.url()}`);
      }
    });
    
    page.on('response', response => {
      if (response.url().includes('wp-content-flow-settings') || response.status() >= 400) {
        console.log(`[RESPONSE] ${response.status()} ${response.url()}`);
      }
    });
    
    // Login to WordPress admin
    await page.goto(LOGIN_URL);
    await page.fill('#user_login', USERNAME);
    await page.fill('#user_pass', PASSWORD);
    await page.click('#wp-submit');
    
    // Wait for admin dashboard to load
    await page.waitForURL('**/wp-admin/**');
    await expect(page.locator('body.wp-admin')).toBeVisible();
  });

  test.afterAll(async () => {
    await context.close();
  });

  test('should load settings page correctly', async () => {
    await page.goto(SETTINGS_URL);
    
    // Wait for page to load completely
    await expect(page.locator('h1')).toContainText('WP Content Flow Settings');
    
    // Verify form elements are present
    await expect(page.locator('#wp-content-flow-settings-form')).toBeVisible();
    await expect(page.locator('#default-ai-provider-dropdown')).toBeVisible();
    await expect(page.locator('input[name="wp_content_flow_settings[cache_enabled]"]')).toBeVisible();
    await expect(page.locator('#wp-content-flow-submit-btn')).toBeVisible();
    
    // Take screenshot of initial state
    await page.screenshot({ 
      path: 'test-results/01-settings-page-loaded.png',
      fullPage: true 
    });
  });

  test('should show current database values correctly', async () => {
    await page.goto(SETTINGS_URL);
    
    // Check for debug information in HTML comments
    const htmlContent = await page.content();
    console.log('Page HTML includes debug info:', htmlContent.includes('WP Content Flow Debug'));
    
    // Get current values from the form
    const currentProvider = await page.locator('#default-ai-provider-dropdown').inputValue();
    const currentCacheStatus = await page.locator('input[name="wp_content_flow_settings[cache_enabled]"]').isChecked();
    const currentRateLimit = await page.locator('input[name="wp_content_flow_settings[requests_per_minute]"]').inputValue();
    
    console.log('Current form values:', {
      provider: currentProvider,
      cache: currentCacheStatus,
      rateLimit: currentRateLimit
    });
    
    // Verify that database value is displayed in description
    const providerDescription = await page.locator('select#default-ai-provider-dropdown + p.description').textContent();
    console.log('Provider description text:', providerDescription);
    
    expect(providerDescription).toContain('Current database value:');
  });

  test('should change default AI provider and verify persistence', async () => {
    await page.goto(SETTINGS_URL);
    
    // Get initial provider value
    const initialProvider = await page.locator('#default-ai-provider-dropdown').inputValue();
    console.log('Initial provider:', initialProvider);
    
    // Determine target provider (different from current)
    const targetProvider = initialProvider === 'openai' ? 'anthropic' : 'openai';
    console.log('Target provider:', targetProvider);
    
    // Take before screenshot
    await page.screenshot({ 
      path: 'test-results/02-before-provider-change.png',
      fullPage: true 
    });
    
    // Change the provider dropdown
    await page.locator('#default-ai-provider-dropdown').selectOption(targetProvider);
    
    // Verify the change was applied to the dropdown
    const newDropdownValue = await page.locator('#default-ai-provider-dropdown').inputValue();
    expect(newDropdownValue).toBe(targetProvider);
    console.log('Dropdown value after change:', newDropdownValue);
    
    // Wait for any JavaScript to process the change
    await page.waitForTimeout(500);
    
    // Monitor network requests during form submission
    const networkRequests = [];
    page.on('request', req => networkRequests.push({
      url: req.url(),
      method: req.method(),
      postData: req.postData()
    }));
    
    const networkResponses = [];
    page.on('response', resp => networkResponses.push({
      url: resp.url(),
      status: resp.status(),
      headers: resp.headers()
    }));
    
    // Click Save Settings button
    console.log('Clicking Save Settings button...');
    await page.click('#wp-content-flow-submit-btn');
    
    // Wait for form submission to complete (should redirect or show success)
    try {
      // Wait for either success message or page reload
      await Promise.race([
        page.waitForSelector('.notice-success', { timeout: 10000 }),
        page.waitForURL('**/wp-content-flow-settings*', { timeout: 10000 }),
        page.waitForTimeout(5000)
      ]);
    } catch (e) {
      console.log('Timeout waiting for success indication, continuing...');
    }
    
    // Take after screenshot
    await page.screenshot({ 
      path: 'test-results/03-after-provider-save.png',
      fullPage: true 
    });
    
    // Log network activity
    console.log('Network requests during save:', networkRequests.filter(req => 
      req.method === 'POST' || req.url.includes('wp-content-flow-settings')
    ));
    
    console.log('Network responses during save:', networkResponses.filter(resp => 
      resp.status >= 300 || resp.url.includes('wp-content-flow-settings')
    ));
    
    // Check for success message
    const successMessages = await page.locator('.notice-success, .updated').count();
    const errorMessages = await page.locator('.notice-error, .error').count();
    
    console.log('Success messages found:', successMessages);
    console.log('Error messages found:', errorMessages);
    
    if (successMessages > 0) {
      const successText = await page.locator('.notice-success, .updated').first().textContent();
      console.log('Success message:', successText);
    }
    
    if (errorMessages > 0) {
      const errorText = await page.locator('.notice-error, .error').first().textContent();
      console.log('Error message:', errorText);
    }
    
    // Verify dropdown still shows the new value (not reverted)
    const savedDropdownValue = await page.locator('#default-ai-provider-dropdown').inputValue();
    console.log('Dropdown value after save:', savedDropdownValue);
    
    if (savedDropdownValue !== targetProvider) {
      console.error('❌ ISSUE FOUND: Dropdown reverted from', targetProvider, 'to', savedDropdownValue);
      await page.screenshot({ 
        path: 'test-results/04-dropdown-reverted-issue.png',
        fullPage: true 
      });
    }
    
    // Reload the page to test persistence
    console.log('Reloading page to test persistence...');
    await page.reload();
    await page.waitForSelector('#default-ai-provider-dropdown');
    
    // Check value after reload
    const reloadedDropdownValue = await page.locator('#default-ai-provider-dropdown').inputValue();
    console.log('Dropdown value after page reload:', reloadedDropdownValue);
    
    // Take final screenshot
    await page.screenshot({ 
      path: 'test-results/05-after-page-reload.png',
      fullPage: true 
    });
    
    // Verify persistence
    if (reloadedDropdownValue !== targetProvider) {
      console.error('❌ PERSISTENCE ISSUE: Value after reload is', reloadedDropdownValue, 'but should be', targetProvider);
    } else {
      console.log('✅ Provider change persisted correctly');
    }
    
    expect(reloadedDropdownValue).toBe(targetProvider);
  });

  test('should change cache setting and verify persistence', async () => {
    await page.goto(SETTINGS_URL);
    
    // Get initial cache setting
    const initialCacheEnabled = await page.locator('input[name="wp_content_flow_settings[cache_enabled]"]').isChecked();
    console.log('Initial cache enabled:', initialCacheEnabled);
    
    // Toggle cache setting
    const cacheCheckbox = page.locator('input[name="wp_content_flow_settings[cache_enabled]"]');
    await cacheCheckbox.click();
    
    // Verify the change
    const newCacheEnabled = await cacheCheckbox.isChecked();
    console.log('Cache enabled after toggle:', newCacheEnabled);
    expect(newCacheEnabled).toBe(!initialCacheEnabled);
    
    // Take before save screenshot
    await page.screenshot({ 
      path: 'test-results/06-before-cache-save.png',
      fullPage: true 
    });
    
    // Save settings
    await page.click('#wp-content-flow-submit-btn');
    
    // Wait for save completion
    try {
      await Promise.race([
        page.waitForSelector('.notice-success', { timeout: 10000 }),
        page.waitForURL('**/wp-content-flow-settings*', { timeout: 10000 }),
        page.waitForTimeout(5000)
      ]);
    } catch (e) {
      console.log('Timeout waiting for save completion');
    }
    
    // Verify checkbox state after save
    const savedCacheEnabled = await page.locator('input[name="wp_content_flow_settings[cache_enabled]"]').isChecked();
    console.log('Cache enabled after save:', savedCacheEnabled);
    
    // Reload and test persistence
    await page.reload();
    await page.waitForSelector('input[name="wp_content_flow_settings[cache_enabled]"]');
    
    const persistedCacheEnabled = await page.locator('input[name="wp_content_flow_settings[cache_enabled]"]').isChecked();
    console.log('Cache enabled after reload:', persistedCacheEnabled);
    
    // Take final screenshot
    await page.screenshot({ 
      path: 'test-results/07-cache-after-reload.png',
      fullPage: true 
    });
    
    // Verify persistence
    if (persistedCacheEnabled !== newCacheEnabled) {
      console.error('❌ CACHE PERSISTENCE ISSUE: Expected', newCacheEnabled, 'but got', persistedCacheEnabled);
    } else {
      console.log('✅ Cache setting persisted correctly');
    }
    
    expect(persistedCacheEnabled).toBe(newCacheEnabled);
  });

  test('should change multiple settings simultaneously and verify all persist', async () => {
    await page.goto(SETTINGS_URL);
    
    // Get initial values
    const initialProvider = await page.locator('#default-ai-provider-dropdown').inputValue();
    const initialCache = await page.locator('input[name="wp_content_flow_settings[cache_enabled]"]').isChecked();
    const initialRateLimit = await page.locator('input[name="wp_content_flow_settings[requests_per_minute]"]').inputValue();
    
    console.log('Initial values:', { 
      provider: initialProvider, 
      cache: initialCache, 
      rateLimit: initialRateLimit 
    });
    
    // Change multiple settings
    const targetProvider = initialProvider === 'openai' ? 'google' : 'openai';
    const targetCache = !initialCache;
    const targetRateLimit = '25';
    
    // Make changes
    await page.locator('#default-ai-provider-dropdown').selectOption(targetProvider);
    
    if (initialCache) {
      await page.locator('input[name="wp_content_flow_settings[cache_enabled]"]').uncheck();
    } else {
      await page.locator('input[name="wp_content_flow_settings[cache_enabled]"]').check();
    }
    
    await page.locator('input[name="wp_content_flow_settings[requests_per_minute]"]').fill(targetRateLimit);
    
    // Verify changes before save
    const changedProvider = await page.locator('#default-ai-provider-dropdown').inputValue();
    const changedCache = await page.locator('input[name="wp_content_flow_settings[cache_enabled]"]').isChecked();
    const changedRateLimit = await page.locator('input[name="wp_content_flow_settings[requests_per_minute]"]').inputValue();
    
    console.log('Values after changes:', { 
      provider: changedProvider, 
      cache: changedCache, 
      rateLimit: changedRateLimit 
    });
    
    // Take screenshot before save
    await page.screenshot({ 
      path: 'test-results/08-before-multiple-save.png',
      fullPage: true 
    });
    
    // Save settings
    await page.click('#wp-content-flow-submit-btn');
    
    // Wait for save completion
    try {
      await Promise.race([
        page.waitForSelector('.notice-success', { timeout: 10000 }),
        page.waitForURL('**/wp-content-flow-settings*', { timeout: 10000 }),
        page.waitForTimeout(5000)
      ]);
    } catch (e) {
      console.log('Timeout waiting for multiple save completion');
    }
    
    // Verify values immediately after save
    const savedProvider = await page.locator('#default-ai-provider-dropdown').inputValue();
    const savedCache = await page.locator('input[name="wp_content_flow_settings[cache_enabled]"]').isChecked();
    const savedRateLimit = await page.locator('input[name="wp_content_flow_settings[requests_per_minute]"]').inputValue();
    
    console.log('Values immediately after save:', { 
      provider: savedProvider, 
      cache: savedCache, 
      rateLimit: savedRateLimit 
    });
    
    // Reload page and check persistence
    await page.reload();
    await page.waitForSelector('#default-ai-provider-dropdown');
    
    const persistedProvider = await page.locator('#default-ai-provider-dropdown').inputValue();
    const persistedCache = await page.locator('input[name="wp_content_flow_settings[cache_enabled]"]').isChecked();
    const persistedRateLimit = await page.locator('input[name="wp_content_flow_settings[requests_per_minute]"]').inputValue();
    
    console.log('Values after reload:', { 
      provider: persistedProvider, 
      cache: persistedCache, 
      rateLimit: persistedRateLimit 
    });
    
    // Take final screenshot
    await page.screenshot({ 
      path: 'test-results/09-multiple-after-reload.png',
      fullPage: true 
    });
    
    // Verify all changes persisted
    const issues = [];
    if (persistedProvider !== targetProvider) {
      issues.push(`Provider: expected ${targetProvider}, got ${persistedProvider}`);
    }
    if (persistedCache !== targetCache) {
      issues.push(`Cache: expected ${targetCache}, got ${persistedCache}`);
    }
    if (persistedRateLimit !== targetRateLimit) {
      issues.push(`Rate limit: expected ${targetRateLimit}, got ${persistedRateLimit}`);
    }
    
    if (issues.length > 0) {
      console.error('❌ MULTIPLE SETTINGS PERSISTENCE ISSUES:', issues);
    } else {
      console.log('✅ All multiple settings persisted correctly');
    }
    
    expect(issues.length).toBe(0);
  });

  test('should capture detailed browser console and network activity during save', async () => {
    await page.goto(SETTINGS_URL);
    
    // Capture console logs
    const consoleMessages = [];
    page.on('console', msg => {
      consoleMessages.push({
        type: msg.type(),
        text: msg.text(),
        timestamp: Date.now()
      });
    });
    
    // Capture network activity
    const networkActivity = [];
    page.on('request', req => {
      networkActivity.push({
        type: 'request',
        method: req.method(),
        url: req.url(),
        headers: req.headers(),
        postData: req.postData(),
        timestamp: Date.now()
      });
    });
    
    page.on('response', resp => {
      networkActivity.push({
        type: 'response',
        status: resp.status(),
        url: resp.url(),
        headers: resp.headers(),
        timestamp: Date.now()
      });
    });
    
    // Make a change and save
    const currentProvider = await page.locator('#default-ai-provider-dropdown').inputValue();
    const newProvider = currentProvider === 'openai' ? 'anthropic' : 'openai';
    
    await page.locator('#default-ai-provider-dropdown').selectOption(newProvider);
    
    // Clear previous activity
    consoleMessages.length = 0;
    networkActivity.length = 0;
    
    // Click save and wait
    const saveStartTime = Date.now();
    await page.click('#wp-content-flow-submit-btn');
    
    // Wait for save process to complete
    await page.waitForTimeout(8000);
    
    const saveEndTime = Date.now();
    
    // Filter activity during save
    const saveConsoleMessages = consoleMessages.filter(msg => 
      msg.timestamp >= saveStartTime && msg.timestamp <= saveEndTime
    );
    
    const saveNetworkActivity = networkActivity.filter(activity => 
      activity.timestamp >= saveStartTime && activity.timestamp <= saveEndTime
    );
    
    console.log('\n=== CONSOLE MESSAGES DURING SAVE ===');
    saveConsoleMessages.forEach((msg, index) => {
      console.log(`${index + 1}. [${msg.type}] ${msg.text}`);
    });
    
    console.log('\n=== NETWORK ACTIVITY DURING SAVE ===');
    saveNetworkActivity.forEach((activity, index) => {
      if (activity.type === 'request') {
        console.log(`${index + 1}. [REQUEST] ${activity.method} ${activity.url}`);
        if (activity.postData) {
          console.log(`    POST Data: ${activity.postData.substring(0, 200)}...`);
        }
      } else {
        console.log(`${index + 1}. [RESPONSE] ${activity.status} ${activity.url}`);
      }
    });
    
    // Check for specific error patterns
    const errorMessages = saveConsoleMessages.filter(msg => 
      msg.type === 'error' || msg.text.toLowerCase().includes('error')
    );
    
    const failedRequests = saveNetworkActivity.filter(activity => 
      activity.type === 'response' && activity.status >= 400
    );
    
    if (errorMessages.length > 0) {
      console.log('\n❌ JAVASCRIPT ERRORS FOUND:');
      errorMessages.forEach(err => console.log(`- ${err.text}`));
    }
    
    if (failedRequests.length > 0) {
      console.log('\n❌ FAILED NETWORK REQUESTS:');
      failedRequests.forEach(req => console.log(`- ${req.status} ${req.url}`));
    }
    
    // Take final screenshot
    await page.screenshot({ 
      path: 'test-results/10-console-network-analysis.png',
      fullPage: true 
    });
  });

  test('should test form submission with API key validation', async () => {
    await page.goto(SETTINGS_URL);
    
    // Clear all API keys first
    await page.fill('input[name="wp_content_flow_settings[openai_api_key]"]', '');
    await page.fill('input[name="wp_content_flow_settings[anthropic_api_key]"]', '');
    await page.fill('input[name="wp_content_flow_settings[google_api_key]"]', '');
    
    // Try to save without any API keys
    await page.click('#wp-content-flow-submit-btn');
    
    // Check if validation prevented submission
    const alertText = await page.evaluate(() => {
      return window.lastAlertMessage || 'no alert';
    });
    
    console.log('Alert message for empty API keys:', alertText);
    
    // Add at least one API key
    await page.fill('input[name="wp_content_flow_settings[openai_api_key]"]', 'test-key-12345');
    
    // Now try to save
    await page.click('#wp-content-flow-submit-btn');
    
    // Wait for form processing
    await page.waitForTimeout(5000);
    
    // Take screenshot of result
    await page.screenshot({ 
      path: 'test-results/11-api-key-validation.png',
      fullPage: true 
    });
  });

});