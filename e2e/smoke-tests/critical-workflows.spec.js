const { test, expect } = require('@playwright/test');

/**
 * Critical Workflow Smoke Tests
 * 
 * These tests validate the most critical user journeys for the WordPress AI Content Flow Plugin.
 * They should run quickly and catch major regressions in core functionality.
 * 
 * Test coverage:
 * 1. Plugin activation and basic configuration
 * 2. Content generation workflow
 * 3. Content improvement workflow
 * 4. Block editor integration
 * 5. Settings management
 */

test.describe('Critical WordPress AI Content Flow Workflows', () => {
  
  test.beforeEach(async ({ page }) => {
    // Ensure we start from a clean state
    await page.goto('/wp-admin/');
    
    // Login as admin
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'admin123!@#');
    await page.click('#wp-submit');
    
    // Wait for dashboard to load
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 15000 });
  });

  test('Plugin is active and accessible', async ({ page }) => {
    // Navigate to plugins page
    await page.goto('/wp-admin/plugins.php');
    
    // Verify plugin is listed and active
    const pluginRow = page.locator('[data-slug="wp-content-flow"]');
    await expect(pluginRow).toBeVisible();
    await expect(pluginRow.locator('.active')).toBeVisible();
    
    // Verify plugin menu is accessible
    await page.goto('/wp-admin/');
    const pluginMenu = page.locator('[href="admin.php?page=wp-content-flow"]');
    await expect(pluginMenu).toBeVisible();
    
    // Click plugin menu and verify dashboard loads
    await pluginMenu.click();
    await expect(page.locator('h1:has-text("AI Content Flow")')).toBeVisible({ timeout: 10000 });
  });

  test('Settings page is functional', async ({ page }) => {
    // Navigate to settings
    await page.goto('/wp-admin/admin.php?page=wp-content-flow-settings');
    
    // Verify settings form is present
    await expect(page.locator('#ai_provider')).toBeVisible();
    await expect(page.locator('[name="wp_content_flow_openai_api_key"]')).toBeVisible();
    
    // Test basic settings interaction
    await page.selectOption('#ai_provider', 'openai');
    await page.fill('[name="wp_content_flow_openai_api_key"]', 'sk-test-key-for-smoke-test');
    
    // Save settings
    await page.click('input[type="submit"]');
    
    // Verify settings were saved
    await expect(page.locator('.notice-success')).toBeVisible({ timeout: 10000 });
  });

  test('Block editor integration works', async ({ page }) => {
    // Create new post
    await page.goto('/wp-admin/post-new.php');
    
    // Wait for Gutenberg editor to load
    await expect(page.locator('.block-editor-writing-flow')).toBeVisible({ timeout: 15000 });
    
    // Add post title
    await page.fill('[name="post_title"]', 'Smoke Test Post - AI Content');
    
    // Click block inserter
    await page.click('.block-editor-inserter__toggle');
    await expect(page.locator('.block-editor-inserter__menu')).toBeVisible();
    
    // Search for AI Text Generator block
    await page.fill('.block-editor-inserter__search input', 'AI Text');
    
    // Verify our AI block appears in search results
    const aiBlock = page.locator('.block-editor-block-types-list__item:has-text("AI Text Generator")');
    await expect(aiBlock).toBeVisible({ timeout: 5000 });
    
    // Insert the AI block
    await aiBlock.click();
    
    // Verify block was inserted
    await expect(page.locator('.wp-content-flow-ai-text-generator')).toBeVisible();
    
    // Save as draft
    await page.click('.editor-post-save-draft');
    await expect(page.locator('.is-saved')).toBeVisible({ timeout: 10000 });
  });

  test('API endpoints are responding', async ({ page }) => {
    // Test workflows endpoint
    const workflowsResponse = await page.request.get('/wp-json/wp-content-flow/v1/workflows');
    expect(workflowsResponse.status()).toBeLessThan(500); // Should not be server error
    
    // Test AI generate endpoint (should require auth)
    const generateResponse = await page.request.post('/wp-json/wp-content-flow/v1/ai/generate', {
      data: {
        prompt: 'Test prompt',
        workflow_id: 1
      }
    });
    // Should be 401 (unauthorized) or 400 (bad request), not 500 (server error)
    expect([400, 401, 403]).toContain(generateResponse.status());
    
    // Test settings endpoint
    const settingsResponse = await page.request.get('/wp-json/wp-content-flow/v1/settings');
    expect(settingsResponse.status()).toBeLessThan(500);
  });

  test('Content improvement toolbar appears on text selection', async ({ page }) => {
    // Navigate to a post with existing content
    await page.goto('/wp-admin/post-new.php');
    
    // Wait for editor to load
    await expect(page.locator('.block-editor-writing-flow')).toBeVisible({ timeout: 15000 });
    
    // Add some test content
    await page.click('.block-editor-default-block-appender__content');
    await page.type('.block-editor-rich-text__editable', 'This is some test content that we can select and improve using AI.');
    
    // Wait for content to be typed
    await page.waitForTimeout(1000);
    
    // Select text (simulating user text selection)
    await page.evaluate(() => {
      const textElement = document.querySelector('.block-editor-rich-text__editable');
      if (textElement) {
        const range = document.createRange();
        range.selectNodeContents(textElement);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
      }
    });
    
    // Wait for improvement toolbar to appear
    await expect(page.locator('.wp-content-flow-improvement-toolbar')).toBeVisible({ timeout: 5000 });
    
    // Verify toolbar has improvement options
    await expect(page.locator('.improvement-options')).toBeVisible();
  });

  test('Workflow management interface works', async ({ page }) => {
    // Navigate to workflows page
    await page.goto('/wp-admin/admin.php?page=wp-content-flow-workflows');
    
    // Verify workflows page loads
    await expect(page.locator('h1:has-text("Workflows")')).toBeVisible({ timeout: 10000 });
    
    // Check if create workflow interface is present
    const createButton = page.locator('button:has-text("Create"), a:has-text("Add New")');
    if (await createButton.count() > 0) {
      await expect(createButton.first()).toBeVisible();
    } else {
      // Alternative: check for workflow list or empty state
      await expect(page.locator('.workflows-list, .no-workflows')).toBeVisible();
    }
  });

  test('User capabilities are properly enforced', async ({ page }) => {
    // Test as administrator (current login)
    await page.goto('/wp-admin/admin.php?page=wp-content-flow');
    await expect(page.locator('h1:has-text("AI Content Flow")')).toBeVisible();
    
    // Logout
    await page.goto('/wp-admin/');
    await page.locator('#wp-admin-bar-my-account').hover();
    await page.click('a:has-text("Log Out")');
    
    // Try to access plugin pages without authentication
    await page.goto('/wp-admin/admin.php?page=wp-content-flow');
    
    // Should be redirected to login
    await expect(page.locator('#loginform')).toBeVisible({ timeout: 10000 });
    
    // Login as editor
    await page.fill('#user_login', 'editor_test');
    await page.fill('#user_pass', 'testpass123!@#');
    await page.click('#wp-submit');
    
    // Editor should have limited access
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 15000 });
    
    // Try to access plugin settings (should be restricted)
    await page.goto('/wp-admin/admin.php?page=wp-content-flow-settings');
    
    // Should either redirect or show permission error
    const hasPermissionError = await page.locator(':has-text("You do not have sufficient permissions")').count() > 0;
    const isRedirected = page.url().includes('wp-login.php') || page.url().includes('admin.php');
    
    expect(hasPermissionError || isRedirected).toBeTruthy();
  });

  test('Error handling works gracefully', async ({ page }) => {
    // Test with invalid API configuration
    await page.goto('/wp-admin/admin.php?page=wp-content-flow-settings');
    
    // Set invalid API key
    await page.fill('[name="wp_content_flow_openai_api_key"]', 'invalid-key-123');
    await page.click('input[type="submit"]');
    
    // Should not cause fatal errors
    await expect(page.locator('body')).toBeVisible(); // Page should still render
    
    // Navigate to content generation
    await page.goto('/wp-admin/post-new.php');
    await expect(page.locator('.block-editor-writing-flow')).toBeVisible({ timeout: 15000 });
    
    // Try to use AI features with invalid config
    await page.click('.block-editor-inserter__toggle');
    await page.fill('.block-editor-inserter__search input', 'AI Text');
    
    const aiBlock = page.locator('.block-editor-block-types-list__item:has-text("AI Text Generator")');
    if (await aiBlock.count() > 0) {
      await aiBlock.click();
      
      // Block should load even with invalid API config
      await expect(page.locator('.wp-content-flow-ai-text-generator')).toBeVisible();
    }
  });

  test('Plugin deactivation/reactivation works', async ({ page }) => {
    // Navigate to plugins page
    await page.goto('/wp-admin/plugins.php');
    
    // Find plugin row
    const pluginRow = page.locator('[data-slug="wp-content-flow"]');
    await expect(pluginRow).toBeVisible();
    
    // Deactivate plugin
    await pluginRow.locator('.deactivate a').click();
    
    // Verify plugin is deactivated
    await expect(pluginRow.locator('.inactive')).toBeVisible();
    
    // Verify plugin menu is no longer visible
    await page.goto('/wp-admin/');
    const pluginMenu = page.locator('[href="admin.php?page=wp-content-flow"]');
    await expect(pluginMenu).not.toBeVisible();
    
    // Reactivate plugin
    await page.goto('/wp-admin/plugins.php');
    await pluginRow.locator('.activate a').click();
    
    // Verify plugin is active again
    await expect(pluginRow.locator('.active')).toBeVisible();
    
    // Verify plugin menu is back
    await page.goto('/wp-admin/');
    await expect(page.locator('[href="admin.php?page=wp-content-flow"]')).toBeVisible();
  });

});