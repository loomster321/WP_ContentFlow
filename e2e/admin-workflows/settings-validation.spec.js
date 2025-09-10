/**
 * WordPress Admin Settings Validation E2E Test
 * 
 * This test validates that the settings page functionality works correctly
 * after the fixes have been applied. It fits into the existing admin-workflows
 * test suite structure.
 */

const { test, expect } = require('@playwright/test');

// Test configuration
const TEST_CONFIG = {
    adminUrl: 'http://localhost:8080/wp-admin',
    username: 'admin',
    password: '!3cTXkh)9iDHhV5o*N',
    settingsPage: '/wp-admin/admin.php?page=wp-content-flow-settings'
};

const TEST_SETTINGS = {
    openai_api_key: 'sk-test-key-12345',
    anthropic_api_key: 'sk-ant-test-key-67890',  
    google_api_key: 'AIza-test-key-abcdef',
    default_ai_provider: 'anthropic',
    requests_per_minute: 20
};

/**
 * Login helper
 */
async function loginToWordPress(page) {
    await page.goto(TEST_CONFIG.adminUrl);
    
    // Check if already logged in
    const loggedIn = await page.locator('.wp-admin').isVisible().catch(() => false);
    if (loggedIn) return;
    
    // Login
    await page.fill('#user_login', TEST_CONFIG.username);
    await page.fill('#user_pass', TEST_CONFIG.password);
    await page.click('#wp-submit');
    
    // Wait for dashboard
    await page.waitForSelector('.wp-admin', { timeout: 15000 });
}

test.describe('Settings Page Functionality', () => {
    
    test.beforeEach(async ({ page }) => {
        await loginToWordPress(page);
    });
    
    test('should display settings form correctly', async ({ page }) => {
        await page.goto(TEST_CONFIG.settingsPage);
        
        // Wait for page to load
        await page.waitForSelector('h1:has-text("WP Content Flow Settings")');
        
        // Verify form elements exist
        await expect(page.locator('form')).toBeVisible();
        await expect(page.locator('input[name="wp_content_flow_settings[openai_api_key]"]')).toBeVisible();
        await expect(page.locator('input[name="wp_content_flow_settings[anthropic_api_key]"]')).toBeVisible();
        await expect(page.locator('input[name="wp_content_flow_settings[google_api_key]"]')).toBeVisible();
        await expect(page.locator('select[name="wp_content_flow_settings[default_ai_provider]"]')).toBeVisible();
        await expect(page.locator('input[type="submit"]')).toBeVisible();
        
        // Check form has correct action URL
        const formAction = await page.locator('form').getAttribute('action');
        expect(formAction).toContain('wp-content-flow-settings');
    });
    
    test('should save settings successfully', async ({ page }) => {
        await page.goto(TEST_CONFIG.settingsPage);
        await page.waitForSelector('h1:has-text("WP Content Flow Settings")');
        
        // Fill form fields
        await page.fill('input[name="wp_content_flow_settings[openai_api_key]"]', TEST_SETTINGS.openai_api_key);
        await page.fill('input[name="wp_content_flow_settings[anthropic_api_key]"]', TEST_SETTINGS.anthropic_api_key);
        await page.fill('input[name="wp_content_flow_settings[google_api_key]"]', TEST_SETTINGS.google_api_key);
        await page.selectOption('select[name="wp_content_flow_settings[default_ai_provider]"]', TEST_SETTINGS.default_ai_provider);
        await page.fill('input[name="wp_content_flow_settings[requests_per_minute]"]', TEST_SETTINGS.requests_per_minute.toString());
        
        // Submit form
        await page.click('input[type="submit"]');
        
        // Wait for page to reload/redirect
        await page.waitForLoadState('networkidle');
        
        // Check for success message or URL parameter
        const currentUrl = page.url();
        const hasSuccessParam = currentUrl.includes('settings-updated=true');
        const hasSuccessMessage = await page.locator('.notice-success').isVisible().catch(() => false);
        
        // At least one success indicator should be present
        expect(hasSuccessParam || hasSuccessMessage).toBeTruthy();
        
        // Verify we're still on settings page
        await expect(page.locator('h1:has-text("WP Content Flow Settings")')).toBeVisible();
    });
    
    test('should persist settings after page reload', async ({ page }) => {
        await page.goto(TEST_CONFIG.settingsPage);
        await page.waitForSelector('h1:has-text("WP Content Flow Settings")');
        
        // Save settings first
        await page.fill('input[name="wp_content_flow_settings[openai_api_key]"]', TEST_SETTINGS.openai_api_key);
        await page.fill('input[name="wp_content_flow_settings[anthropic_api_key]"]', TEST_SETTINGS.anthropic_api_key);
        await page.selectOption('select[name="wp_content_flow_settings[default_ai_provider]"]', TEST_SETTINGS.default_ai_provider);
        await page.fill('input[name="wp_content_flow_settings[requests_per_minute]"]', TEST_SETTINGS.requests_per_minute.toString());
        
        await page.click('input[type="submit"]');
        await page.waitForLoadState('networkidle');
        
        // Reload page
        await page.reload();
        await page.waitForSelector('h1:has-text("WP Content Flow Settings")');
        
        // Check that values are preserved
        const openaiValue = await page.locator('input[name="wp_content_flow_settings[openai_api_key]"]').inputValue();
        const anthropicValue = await page.locator('input[name="wp_content_flow_settings[anthropic_api_key]"]').inputValue();
        const providerValue = await page.locator('select[name="wp_content_flow_settings[default_ai_provider]"]').inputValue();
        const requestsValue = await page.locator('input[name="wp_content_flow_settings[requests_per_minute]"]').inputValue();
        
        expect(openaiValue).toBe(TEST_SETTINGS.openai_api_key);
        expect(anthropicValue).toBe(TEST_SETTINGS.anthropic_api_key);  
        expect(providerValue).toBe(TEST_SETTINGS.default_ai_provider);
        expect(requestsValue).toBe(TEST_SETTINGS.requests_per_minute.toString());
        
        // Check Current Configuration section
        const configSection = page.locator('.wp-content-flow-info');
        await expect(configSection).toBeVisible();
        await expect(configSection).toContainText('Configured ✓');
    });
    
    test('should show form validation feedback', async ({ page }) => {
        await page.goto(TEST_CONFIG.settingsPage);
        await page.waitForSelector('h1:has-text("WP Content Flow Settings")');
        
        // Test requests per minute validation with invalid value
        await page.fill('input[name="wp_content_flow_settings[requests_per_minute]"]', '0');
        await page.click('input[type="submit"]');
        await page.waitForLoadState('networkidle');
        
        // Value should be corrected to minimum valid value
        const correctedValue = await page.locator('input[name="wp_content_flow_settings[requests_per_minute]"]').inputValue();
        expect(parseInt(correctedValue)).toBeGreaterThan(0);
    });
    
    test('should handle form submission correctly', async ({ page }) => {
        await page.goto(TEST_CONFIG.settingsPage);
        await page.waitForSelector('h1:has-text("WP Content Flow Settings")');
        
        // Monitor network requests
        const requests = [];
        page.on('request', request => {
            if (request.method() === 'POST') {
                requests.push({
                    url: request.url(),
                    headers: request.headers(),
                    postData: request.postData()
                });
            }
        });
        
        // Fill minimal form data
        await page.fill('input[name="wp_content_flow_settings[openai_api_key]"]', 'test-key');
        await page.selectOption('select[name="wp_content_flow_settings[default_ai_provider]"]', 'openai');
        
        // Submit form
        await page.click('input[type="submit"]');
        await page.waitForLoadState('networkidle');
        
        // Verify POST request was made
        expect(requests.length).toBeGreaterThan(0);
        
        const formSubmissionRequest = requests.find(req => 
            req.url.includes('wp-content-flow-settings') && req.postData
        );
        
        expect(formSubmissionRequest).toBeDefined();
        
        // Check that form data includes required fields
        if (formSubmissionRequest) {
            expect(formSubmissionRequest.postData).toContain('option_page');
            expect(formSubmissionRequest.postData).toContain('_wpnonce');
            expect(formSubmissionRequest.postData).toContain('wp_content_flow_settings');
        }
    });
});

// Additional test for debugging form issues
test.describe('Settings Form Debugging', () => {
    
    test.beforeEach(async ({ page }) => {
        await loginToWordPress(page);
    });
    
    test('should provide debugging information', async ({ page }) => {
        await page.goto(TEST_CONFIG.settingsPage);
        await page.waitForSelector('h1:has-text("WP Content Flow Settings")');
        
        // Enable console logging
        const consoleLogs = [];
        page.on('console', msg => {
            if (msg.type() === 'log') {
                consoleLogs.push(msg.text());
            }
        });
        
        // Fill and submit form
        await page.fill('input[name="wp_content_flow_settings[openai_api_key]"]', 'debug-test');
        
        // Take screenshot before submission
        await page.screenshot({ path: 'test-results/settings-before-submit.png', fullPage: true });
        
        await page.click('input[type="submit"]');
        await page.waitForLoadState('networkidle');
        
        // Take screenshot after submission
        await page.screenshot({ path: 'test-results/settings-after-submit.png', fullPage: true });
        
        // Log console messages for debugging
        console.log('Console messages during form submission:');
        consoleLogs.forEach(log => {
            console.log('  -', log);
        });
        
        // Log current URL and page content for debugging
        console.log('Current URL after submission:', page.url());
        
        // Check for any error messages
        const errorMessages = await page.locator('.notice-error, .error').count();
        console.log('Error messages found:', errorMessages);
        
        if (errorMessages > 0) {
            const errorText = await page.locator('.notice-error, .error').textContent();
            console.log('Error text:', errorText);
        }
        
        // The test passes if we get this far without timing out
        expect(true).toBeTruthy();
    });
});