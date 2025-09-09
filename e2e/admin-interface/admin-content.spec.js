const { test, expect } = require('@playwright/test');

/**
 * WordPress Admin Interface Content Tests
 * 
 * Tests that the Content Flow admin pages have proper content
 * and are not empty panels with just "Save Changes" buttons
 */

test.describe('WordPress AI Content Flow - Admin Interface Content', () => {
  
  test.beforeEach(async ({ page }) => {
    // Login to WordPress admin
    await page.goto('/wp-admin/');
    
    // Fill login form
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
    await page.click('#wp-submit');
    
    // Wait for admin dashboard to load
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 15000 });
  });
  
  test('Dashboard page has comprehensive content', async ({ page }) => {
    // Navigate to Content Flow dashboard
    await page.hover('li#menu-posts');
    await page.waitForSelector('#menu-wp-content-flow', { timeout: 10000 });
    await page.click('#menu-wp-content-flow a[href="admin.php?page=wp-content-flow"]');
    
    // Wait for page to load
    await page.waitForSelector('.wrap', { timeout: 10000 });
    
    // Check for page title
    await expect(page.locator('h1')).toContainText('WP Content Flow Dashboard');
    
    // Check for hero section with AI dashboard image
    await expect(page.locator('.wp-content-flow-hero')).toBeVisible();
    await expect(page.locator('.wp-content-flow-hero')).toContainText('Welcome to WordPress AI Content Flow');
    
    // Check that the AI dashboard image is displayed
    await expect(page.locator('.hero-image img')).toBeVisible();
    await expect(page.locator('.hero-image img')).toHaveAttribute('alt', 'AI Content Workflow Dashboard');
    
    // Check for plugin status widget
    await expect(page.locator('.postbox')).toContainText('Plugin Status');
    await expect(page.locator('.postbox')).toContainText('Active ✓');
    
    // Check for configuration widget
    await expect(page.locator('.postbox')).toContainText('Configuration');
    await expect(page.locator('.postbox')).toContainText('AI providers configured');
    
    // Check for quick actions widget
    await expect(page.locator('.postbox')).toContainText('Quick Actions');
    await expect(page.locator('.postbox')).toContainText('Create New Post with AI');
    
    // Check for getting started widget
    await expect(page.locator('.postbox')).toContainText('Getting Started');
    await expect(page.locator('.postbox')).toContainText('Configure your AI provider API keys');
    
    // Verify the page has substantial content (not just empty)
    const pageContent = await page.textContent('.wrap');
    expect(pageContent.length).toBeGreaterThan(500);
    
    // Take screenshot for documentation
    await page.screenshot({ path: 'test-results/dashboard-content.png', fullPage: true });
  });
  
  test('Settings page has form fields and configuration options', async ({ page }) => {
    // Navigate to Content Flow settings
    await page.hover('li#menu-posts');
    await page.waitForSelector('#menu-wp-content-flow', { timeout: 10000 });
    await page.click('#menu-wp-content-flow a[href="admin.php?page=wp-content-flow-settings"]');
    
    // Wait for page to load
    await page.waitForSelector('.wrap', { timeout: 10000 });
    
    // Check for page title
    await expect(page.locator('h1')).toContainText('WP Content Flow Settings');
    
    // Check for settings form
    await expect(page.locator('form')).toBeVisible();
    
    // Check for AI provider section
    await expect(page.locator('h3')).toContainText('AI Provider Configuration');
    
    // Check for OpenAI API key field
    const openaiField = page.locator('input[name="wp_content_flow_settings[openai_api_key]"]');
    await expect(openaiField).toBeVisible();
    
    // Check for Anthropic API key field
    const anthropicField = page.locator('input[name="wp_content_flow_settings[anthropic_api_key]"]');
    await expect(anthropicField).toBeVisible();
    
    // Check for submit button
    await expect(page.locator('input[type="submit"]')).toBeVisible();
    await expect(page.locator('input[type="submit"]')).toHaveValue('Save Settings');
    
    // Check for current configuration display
    await expect(page.locator('.wp-content-flow-info')).toBeVisible();
    await expect(page.locator('.wp-content-flow-info')).toContainText('Current Configuration');
    
    // Verify API key status is displayed
    await expect(page.locator('.wp-content-flow-info')).toContainText('Openai api key');
    await expect(page.locator('.wp-content-flow-info')).toContainText('Configured ✓');
    
    // Verify the page has substantial content (not just "Save Changes" button)
    const pageContent = await page.textContent('.wrap');
    expect(pageContent.length).toBeGreaterThan(300);
    
    // Verify it's NOT just empty with only a save button
    expect(pageContent).not.toMatch(/^[\s\S]*Save Changes[\s\S]*$/);
    
    // Take screenshot for documentation
    await page.screenshot({ path: 'test-results/settings-content.png', fullPage: true });
  });
  
  test('Settings form is functional', async ({ page }) => {
    // Navigate to settings page
    await page.hover('li#menu-posts');
    await page.waitForSelector('#menu-wp-content-flow', { timeout: 10000 });
    await page.click('#menu-wp-content-flow a[href="admin.php?page=wp-content-flow-settings"]');
    await page.waitForSelector('.wrap', { timeout: 10000 });
    
    // Test that we can interact with form fields
    const openaiField = page.locator('input[name="wp_content_flow_settings[openai_api_key]"]');
    await openaiField.fill('test-api-key-change');
    
    // Verify the value was set
    await expect(openaiField).toHaveValue('test-api-key-change');
    
    // Test form submission (without actually saving)
    await expect(page.locator('form')).toHaveAttribute('method', 'post');
    await expect(page.locator('form')).toHaveAttribute('action', 'options.php');
    
    // Check for WordPress nonce field
    await expect(page.locator('input[name="_wpnonce"]')).toBeVisible();
  });
  
  test('Menu structure is properly registered', async ({ page }) => {
    // Check that Content Flow menu exists in admin
    await expect(page.locator('#menu-wp-content-flow')).toBeVisible();
    
    // Check menu text
    await expect(page.locator('#menu-wp-content-flow .wp-menu-name')).toContainText('Content Flow');
    
    // Hover to show submenu
    await page.hover('#menu-wp-content-flow');
    await page.waitForTimeout(500);
    
    // Check for dashboard submenu item
    await expect(page.locator('#menu-wp-content-flow .wp-submenu a[href*="wp-content-flow"]')).toBeVisible();
    
    // Check for settings submenu item
    await expect(page.locator('#menu-wp-content-flow .wp-submenu a[href*="wp-content-flow-settings"]')).toBeVisible();
  });
  
  test('Admin interface handles errors gracefully', async ({ page }) => {
    // Navigate to dashboard and check for PHP errors
    await page.hover('li#menu-posts');
    await page.waitForSelector('#menu-wp-content-flow', { timeout: 10000 });
    await page.click('#menu-wp-content-flow a[href="admin.php?page=wp-content-flow"]');
    await page.waitForSelector('.wrap', { timeout: 10000 });
    
    // Check that no PHP errors are displayed
    const phpErrors = [
      'Fatal error:',
      'Parse error:',
      'Warning:',
      'Notice:',
      'Undefined'
    ];
    
    const pageText = await page.textContent('body');
    for (const errorType of phpErrors) {
      expect(pageText).not.toContain(errorType);
    }
    
    // Check that the page loaded successfully (no white screen of death)
    await expect(page.locator('.wrap')).toBeVisible();
  });
  
  test('Plugin provides helpful information for users', async ({ page }) => {
    // Go to dashboard
    await page.hover('li#menu-posts');
    await page.waitForSelector('#menu-wp-content-flow', { timeout: 10000 });
    await page.click('#menu-wp-content-flow a[href="admin.php?page=wp-content-flow"]');
    await page.waitForSelector('.wrap', { timeout: 10000 });
    
    // Check for helpful links and information
    await expect(page.locator('a')).toContainText('Configure Settings');
    await expect(page.locator('a')).toContainText('Create New Post with AI');
    
    // Check for version information
    const versionRegex = /Version:?\s*[\d.]+/i;
    const pageContent = await page.textContent('.wrap');
    expect(pageContent).toMatch(versionRegex);
    
    // Check for status indicators
    await expect(page.locator('.wrap')).toContainText('Active ✓');
    await expect(page.locator('.wrap')).toContainText('Initialized ✓');
  });
});

test.describe('WordPress AI Content Flow - Cross-browser Compatibility', () => {
  
  test('Admin interface works in different viewports', async ({ page }) => {
    // Test mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });
    
    // Login
    await page.goto('/wp-admin/');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
    await page.click('#wp-submit');
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 15000 });
    
    // Navigate to plugin dashboard
    await page.hover('li#menu-posts');
    await page.waitForSelector('#menu-wp-content-flow', { timeout: 10000 });
    await page.click('#menu-wp-content-flow a[href="admin.php?page=wp-content-flow"]');
    await page.waitForSelector('.wrap', { timeout: 10000 });
    
    // Verify content is still accessible on mobile
    await expect(page.locator('h1')).toContainText('WP Content Flow Dashboard');
    await expect(page.locator('.postbox')).toBeVisible();
    
    // Test tablet viewport
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.reload();
    await page.waitForSelector('.wrap', { timeout: 10000 });
    
    await expect(page.locator('h1')).toContainText('WP Content Flow Dashboard');
    await expect(page.locator('.postbox')).toBeVisible();
  });
});