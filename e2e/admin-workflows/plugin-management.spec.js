const { test, expect } = require('@playwright/test');

test.describe('Plugin Management - Admin Tests', () => {
  test.beforeEach(async ({ page }) => {
    // Login as administrator
    await page.goto('/wp-admin');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'admin');
    await page.click('#wp-submit');
    await page.waitForURL('/wp-admin/index.php');
  });

  test('should activate plugin successfully', async ({ page }) => {
    await page.goto('/wp-admin/plugins.php');
    
    // Find the WP Content Flow plugin
    const pluginRow = page.locator('tr[data-slug="wp-content-flow"]');
    await expect(pluginRow).toBeVisible();
    
    // Check if plugin is inactive and activate it
    const activateLink = pluginRow.locator('a[aria-label*="Activate"]');
    if (await activateLink.isVisible()) {
      await activateLink.click();
      
      // Verify activation success
      await expect(page.locator('.notice-success')).toContainText('Plugin activated');
      await expect(pluginRow.locator('a[aria-label*="Deactivate"]')).toBeVisible();
    }
  });

  test('should display plugin settings page', async ({ page }) => {
    await page.goto('/wp-admin/options-general.php?page=wp-content-flow');
    
    // Verify settings page loads
    await expect(page.locator('h1')).toContainText('WP Content Flow Settings');
    
    // Check for AI provider settings
    await expect(page.locator('#openai_api_key')).toBeVisible();
    await expect(page.locator('#anthropic_api_key')).toBeVisible();
    await expect(page.locator('#default_provider')).toBeVisible();
    
    // Check for workflow settings
    await expect(page.locator('#auto_save_workflows')).toBeVisible();
    await expect(page.locator('#enable_content_suggestions')).toBeVisible();
  });

  test('should save API settings', async ({ page }) => {
    await page.goto('/wp-admin/options-general.php?page=wp-content-flow');
    
    // Fill in test API key (dummy for testing)
    await page.fill('#openai_api_key', 'sk-test-key-for-testing');
    await page.selectOption('#default_provider', 'openai');
    await page.check('#auto_save_workflows');
    
    // Save settings
    await page.click('#submit');
    
    // Verify save success
    await expect(page.locator('.notice-updated')).toContainText('Settings saved');
    
    // Verify values persist
    await expect(page.locator('#openai_api_key')).toHaveValue('sk-test-key-for-testing');
    await expect(page.locator('#default_provider')).toHaveValue('openai');
    await expect(page.locator('#auto_save_workflows')).toBeChecked();
  });

  test('should display plugin documentation link', async ({ page }) => {
    await page.goto('/wp-admin/plugins.php');
    
    const pluginRow = page.locator('tr[data-slug="wp-content-flow"]');
    await expect(pluginRow.locator('a[href*="github.com"]')).toBeVisible();
  });

  test('should handle deactivation gracefully', async ({ page }) => {
    await page.goto('/wp-admin/plugins.php');
    
    const pluginRow = page.locator('tr[data-slug="wp-content-flow"]');
    const deactivateLink = pluginRow.locator('a[aria-label*="Deactivate"]');
    
    if (await deactivateLink.isVisible()) {
      await deactivateLink.click();
      
      // Verify deactivation
      await expect(page.locator('.notice-success')).toContainText('Plugin deactivated');
      await expect(pluginRow.locator('a[aria-label*="Activate"]')).toBeVisible();
      
      // Verify plugin data persists (settings should remain)
      await page.goto('/wp-admin/options-general.php?page=wp-content-flow');
      await expect(page.locator('.wrap')).toContainText('plugin is not active');
    }
  });

  test('should display admin menu items when active', async ({ page }) => {
    // Ensure plugin is active
    await page.goto('/wp-admin/plugins.php');
    const pluginRow = page.locator('tr[data-slug="wp-content-flow"]');
    const activateLink = pluginRow.locator('a[aria-label*="Activate"]');
    
    if (await activateLink.isVisible()) {
      await activateLink.click();
      await page.waitForURL('/wp-admin/plugins.php');
    }
    
    // Check admin menu
    await page.goto('/wp-admin/');
    const adminMenu = page.locator('#adminmenu');
    
    // Verify Content Flow menu appears
    await expect(adminMenu.locator('a[href*="wp-content-flow"]')).toBeVisible();
    
    // Check submenu items
    const contentFlowMenu = adminMenu.locator('li:has(a[href*="wp-content-flow"])');
    await contentFlowMenu.hover();
    
    await expect(contentFlowMenu.locator('a[href*="workflows"]')).toBeVisible();
    await expect(contentFlowMenu.locator('a[href*="suggestions"]')).toBeVisible();
    await expect(contentFlowMenu.locator('a[href*="settings"]')).toBeVisible();
  });

  test('should validate API key format', async ({ page }) => {
    await page.goto('/wp-admin/options-general.php?page=wp-content-flow');
    
    // Try invalid API key format
    await page.fill('#openai_api_key', 'invalid-key');
    await page.click('#submit');
    
    // Should show validation error
    await expect(page.locator('.notice-error')).toContainText('Invalid API key format');
    
    // Try valid format
    await page.fill('#openai_api_key', 'sk-1234567890abcdef1234567890abcdef1234567890abcdef');
    await page.click('#submit');
    
    // Should save successfully
    await expect(page.locator('.notice-updated')).toContainText('Settings saved');
  });

  test('should handle capability restrictions', async ({ page }) => {
    // Test with editor role (limited capabilities)
    await page.goto('/wp-login.php?action=logout');
    await page.click('a[href*="logout"]');
    
    // Login as editor
    await page.goto('/wp-admin');
    await page.fill('#user_login', 'editor');
    await page.fill('#user_pass', 'editor');
    await page.click('#wp-submit');
    
    // Editor should not see plugin management
    await page.goto('/wp-admin/plugins.php');
    await expect(page.locator('body')).toContainText('Sorry, you are not allowed');
    
    // But should see content flow features
    await page.goto('/wp-admin/');
    const adminMenu = page.locator('#adminmenu');
    await expect(adminMenu.locator('a[href*="wp-content-flow"]')).toBeVisible();
  });
});