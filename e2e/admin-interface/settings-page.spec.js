/**
 * E2E Tests for WP Content Flow Settings Page
 * 
 * Tests complete user workflow for settings save functionality:
 * - Navigate to settings page
 * - Fill form fields
 * - Submit form
 * - Verify success message
 * - Verify data persistence
 * 
 * @package WP_Content_Flow E2E Tests
 */

const { test, expect } = require('@playwright/test');

// WordPress admin credentials
const ADMIN_USER = 'admin';
const ADMIN_PASSWORD = '!3cTXkh)9iDHhV5o*N';

// Test data for settings
const TEST_SETTINGS = {
    openai_api_key: 'sk-test-openai-key-1234567890abcdef',
    anthropic_api_key: 'sk-ant-test-anthropic-key-1234567890abcdef', 
    google_api_key: 'AIza-test-google-key-1234567890abcdef',
    default_ai_provider: 'anthropic',
    cache_enabled: true,
    requests_per_minute: 15
};

/**
 * WordPress admin login helper
 */
async function loginAsAdmin(page) {
    await page.goto('/wp-admin');
    
    // Check if already logged in
    const isLoggedIn = await page.locator('body.wp-admin').isVisible().catch(() => false);
    if (isLoggedIn) {
        return;
    }
    
    // Fill login form
    await page.fill('#user_login', ADMIN_USER);
    await page.fill('#user_pass', ADMIN_PASSWORD);
    await page.click('#wp-submit');
    
    // Wait for admin dashboard
    await page.waitForSelector('body.wp-admin', { timeout: 15000 });
}

/**
 * Navigate to Content Flow settings page
 */
async function navigateToSettings(page) {
    await page.goto('/wp-admin/admin.php?page=wp-content-flow-settings');
    
    // Wait for settings page to load
    await page.waitForSelector('h1:has-text("WP Content Flow Settings")', { timeout: 10000 });
}

/**
 * Fill settings form with test data
 */
async function fillSettingsForm(page, settings) {
    // Fill API key fields
    await page.fill('input[name="wp_content_flow_settings[openai_api_key]"]', settings.openai_api_key);
    await page.fill('input[name="wp_content_flow_settings[anthropic_api_key]"]', settings.anthropic_api_key);
    await page.fill('input[name="wp_content_flow_settings[google_api_key]"]', settings.google_api_key);
    
    // Select default provider
    await page.selectOption('select[name="wp_content_flow_settings[default_ai_provider]"]', settings.default_ai_provider);
    
    // Set cache enabled checkbox
    const cacheCheckbox = page.locator('input[name="wp_content_flow_settings[cache_enabled]"]');
    if (settings.cache_enabled) {
        await cacheCheckbox.check();
    } else {
        await cacheCheckbox.uncheck();
    }
    
    // Set requests per minute
    await page.fill('input[name="wp_content_flow_settings[requests_per_minute]"]', settings.requests_per_minute.toString());
}

/**
 * Verify form fields contain expected values
 */
async function verifyFormValues(page, settings) {
    // Check API key fields (password fields don't show values, but check if they're not empty)
    const openaiField = page.locator('input[name="wp_content_flow_settings[openai_api_key]"]');
    const anthropicField = page.locator('input[name="wp_content_flow_settings[anthropic_api_key]"]');
    const googleField = page.locator('input[name="wp_content_flow_settings[google_api_key]"]');
    
    // For password fields, we check if they have the expected value
    await expect(openaiField).toHaveValue(settings.openai_api_key);
    await expect(anthropicField).toHaveValue(settings.anthropic_api_key);
    await expect(googleField).toHaveValue(settings.google_api_key);
    
    // Check dropdown selection
    const providerSelect = page.locator('select[name="wp_content_flow_settings[default_ai_provider]"]');
    await expect(providerSelect).toHaveValue(settings.default_ai_provider);
    
    // Check checkbox state
    const cacheCheckbox = page.locator('input[name="wp_content_flow_settings[cache_enabled]"]');
    if (settings.cache_enabled) {
        await expect(cacheCheckbox).toBeChecked();
    } else {
        await expect(cacheCheckbox).not.toBeChecked();
    }
    
    // Check requests per minute field
    const requestsField = page.locator('input[name="wp_content_flow_settings[requests_per_minute]"]');
    await expect(requestsField).toHaveValue(settings.requests_per_minute.toString());
}

test.describe('WP Content Flow Settings Page', () => {
    
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
    });
    
    test('should display settings page correctly', async ({ page }) => {
        await navigateToSettings(page);
        
        // Check page title
        await expect(page.locator('h1')).toContainText('WP Content Flow Settings');
        
        // Check form exists
        await expect(page.locator('form')).toBeVisible();
        
        // Check all required fields exist
        await expect(page.locator('input[name="wp_content_flow_settings[openai_api_key]"]')).toBeVisible();
        await expect(page.locator('input[name="wp_content_flow_settings[anthropic_api_key]"]')).toBeVisible(); 
        await expect(page.locator('input[name="wp_content_flow_settings[google_api_key]"]')).toBeVisible();
        await expect(page.locator('select[name="wp_content_flow_settings[default_ai_provider]"]')).toBeVisible();
        await expect(page.locator('input[name="wp_content_flow_settings[cache_enabled]"]')).toBeVisible();
        await expect(page.locator('input[name="wp_content_flow_settings[requests_per_minute]"]')).toBeVisible();
        
        // Check submit button exists
        await expect(page.locator('input[type="submit"][value="Save Settings"]')).toBeVisible();
    });
    
    test('should save settings successfully', async ({ page }) => {
        await navigateToSettings(page);
        
        // Fill the form with test data
        await fillSettingsForm(page, TEST_SETTINGS);
        
        // Take screenshot before submitting
        await page.screenshot({ path: 'test-results/settings-before-submit.png', fullPage: true });
        
        // Submit the form
        await page.click('input[type="submit"][value="Save Settings"]');
        
        // Wait for page to redirect/reload after form submission
        await page.waitForLoadState('networkidle');
        
        // Take screenshot after submitting  
        await page.screenshot({ path: 'test-results/settings-after-submit.png', fullPage: true });
        
        // Check for success message
        const successMessage = page.locator('.notice-success, .updated, .settings-error-settings_updated');
        await expect(successMessage).toBeVisible({ timeout: 5000 });
        await expect(successMessage).toContainText(/settings saved/i);
        
        // Verify we're still on the settings page
        await expect(page.locator('h1')).toContainText('WP Content Flow Settings');
        
        // Verify form values are preserved after save
        await verifyFormValues(page, TEST_SETTINGS);
    });
    
    test('should persist settings after page reload', async ({ page }) => {
        await navigateToSettings(page);
        
        // Fill and save settings first
        await fillSettingsForm(page, TEST_SETTINGS);
        await page.click('input[type="submit"][value="Save Settings"]');
        await page.waitForLoadState('networkidle');
        
        // Reload the page
        await page.reload();
        await page.waitForSelector('h1:has-text("WP Content Flow Settings")');
        
        // Verify settings are still there
        await verifyFormValues(page, TEST_SETTINGS);
        
        // Check the "Current Configuration" section shows saved values
        const configSection = page.locator('.wp-content-flow-info');
        await expect(configSection).toBeVisible();
        
        // Check that API keys show as configured (not the actual keys for security)
        await expect(configSection).toContainText('Configured ✓');
        await expect(configSection).toContainText(TEST_SETTINGS.default_ai_provider);
        await expect(configSection).toContainText(TEST_SETTINGS.requests_per_minute.toString());
    });
    
    test('should validate required fields', async ({ page }) => {
        await navigateToSettings(page);
        
        // Try to save with empty fields
        await page.click('input[type="submit"][value="Save Settings"]');
        await page.waitForLoadState('networkidle');
        
        // Settings should still save (empty values are allowed), but check form behavior
        await expect(page.locator('h1')).toContainText('WP Content Flow Settings');
    });
    
    test('should handle different AI provider selections', async ({ page }) => {
        await navigateToSettings(page);
        
        const providers = ['openai', 'anthropic', 'google'];
        
        for (const provider of providers) {
            // Select provider
            await page.selectOption('select[name="wp_content_flow_settings[default_ai_provider]"]', provider);
            
            // Fill minimal required data
            const settings = {
                ...TEST_SETTINGS,
                default_ai_provider: provider
            };
            
            await fillSettingsForm(page, settings);
            
            // Save settings
            await page.click('input[type="submit"][value="Save Settings"]');
            await page.waitForLoadState('networkidle');
            
            // Verify success
            const successMessage = page.locator('.notice-success, .updated, .settings-error-settings_updated');
            await expect(successMessage).toBeVisible({ timeout: 5000 });
            
            // Verify provider selection is saved
            const providerSelect = page.locator('select[name="wp_content_flow_settings[default_ai_provider]"]');
            await expect(providerSelect).toHaveValue(provider);
        }
    });
    
    test('should handle cache setting toggle', async ({ page }) => {
        await navigateToSettings(page);
        
        // Test enabling cache
        const cacheCheckbox = page.locator('input[name="wp_content_flow_settings[cache_enabled]"]');
        await cacheCheckbox.check();
        await fillSettingsForm(page, { ...TEST_SETTINGS, cache_enabled: true });
        
        await page.click('input[type="submit"][value="Save Settings"]');
        await page.waitForLoadState('networkidle');
        
        await expect(cacheCheckbox).toBeChecked();
        
        // Test disabling cache  
        await cacheCheckbox.uncheck();
        await fillSettingsForm(page, { ...TEST_SETTINGS, cache_enabled: false });
        
        await page.click('input[type="submit"][value="Save Settings"]');
        await page.waitForLoadState('networkidle');
        
        await expect(cacheCheckbox).not.toBeChecked();
    });
    
    test('should validate requests per minute field', async ({ page }) => {
        await navigateToSettings(page);
        
        const requestsField = page.locator('input[name="wp_content_flow_settings[requests_per_minute]"]');
        
        // Test valid values
        const validValues = [1, 10, 50, 100];
        for (const value of validValues) {
            await requestsField.fill(value.toString());
            await fillSettingsForm(page, { ...TEST_SETTINGS, requests_per_minute: value });
            
            await page.click('input[type="submit"][value="Save Settings"]');
            await page.waitForLoadState('networkidle');
            
            await expect(requestsField).toHaveValue(value.toString());
        }
        
        // Test zero value (should be converted to 1 or default)
        await requestsField.fill('0');
        await fillSettingsForm(page, { ...TEST_SETTINGS, requests_per_minute: 0 });
        
        await page.click('input[type="submit"][value="Save Settings"]');
        await page.waitForLoadState('networkidle');
        
        // Should not remain as 0 (sanitized to minimum value)
        const finalValue = await requestsField.inputValue();
        expect(parseInt(finalValue)).toBeGreaterThan(0);
    });
    
    test('should display current configuration correctly', async ({ page }) => {
        await navigateToSettings(page);
        
        // Save some settings first
        await fillSettingsForm(page, TEST_SETTINGS);
        await page.click('input[type="submit"][value="Save Settings"]');
        await page.waitForLoadState('networkidle');
        
        // Check current configuration section
        const configSection = page.locator('.wp-content-flow-info');
        await expect(configSection).toBeVisible();
        await expect(configSection).toContainText('Current Configuration');
        
        // Should show configured status for API keys
        await expect(configSection).toContainText('Configured ✓');
        
        // Should show actual values for non-sensitive settings
        await expect(configSection).toContainText(TEST_SETTINGS.default_ai_provider);
        await expect(configSection).toContainText(TEST_SETTINGS.requests_per_minute.toString());
    });
    
    // Test to specifically debug the save issue
    test('should debug form submission process', async ({ page }) => {
        await navigateToSettings(page);
        
        // Enable request interception to monitor form submission
        const requests = [];
        page.on('request', request => {
            requests.push({
                url: request.url(),
                method: request.method(),
                postData: request.postData()
            });
        });
        
        const responses = [];
        page.on('response', response => {
            responses.push({
                url: response.url(),
                status: response.status(),
                statusText: response.statusText()
            });
        });
        
        // Fill form
        await fillSettingsForm(page, TEST_SETTINGS);
        
        // Check form action and method
        const form = page.locator('form');
        const formAction = await form.getAttribute('action');
        const formMethod = await form.getAttribute('method');
        
        console.log('Form action:', formAction);
        console.log('Form method:', formMethod);
        
        // Check nonce field exists
        const nonceField = page.locator('input[name="_wpnonce"]');
        await expect(nonceField).toBeVisible();
        const nonceValue = await nonceField.getAttribute('value');
        console.log('Nonce exists:', !!nonceValue);
        
        // Check option_page field exists  
        const optionPageField = page.locator('input[name="option_page"]');
        await expect(optionPageField).toBeVisible();
        const optionPageValue = await optionPageField.getAttribute('value');
        console.log('Option page value:', optionPageValue);
        
        // Submit form and capture network activity
        await page.click('input[type="submit"][value="Save Settings"]');
        await page.waitForLoadState('networkidle');
        
        // Log network activity for debugging
        console.log('Requests made during form submission:');
        requests.forEach(req => {
            console.log(`${req.method} ${req.url}`);
            if (req.postData) {
                console.log('POST data:', req.postData.substring(0, 500)); // First 500 chars
            }
        });
        
        console.log('Responses received:');
        responses.forEach(resp => {
            console.log(`${resp.status} ${resp.url}`);
        });
        
        // Check current page URL after submission
        const currentUrl = page.url();
        console.log('Current URL after submission:', currentUrl);
        
        // Look for any error messages or success indicators
        const allText = await page.textContent('body');
        const hasSuccess = allText.includes('success') || allText.includes('saved') || allText.includes('updated');
        const hasError = allText.includes('error') || allText.includes('failed') || allText.includes('Error');
        
        console.log('Page contains success indicators:', hasSuccess);
        console.log('Page contains error indicators:', hasError);
        
        // Take screenshot for debugging
        await page.screenshot({ path: 'test-results/debug-form-submission.png', fullPage: true });
    });
});