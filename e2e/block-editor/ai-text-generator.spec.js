const { test, expect } = require('@playwright/test');

test.describe('AI Text Generator Block - Block Editor Tests', () => {
  test.beforeEach(async ({ page }) => {
    // Login as editor (has block editor access)
    await page.goto('/wp-admin');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'admin');
    await page.click('#wp-submit');
    
    // Create new post
    await page.goto('/wp-admin/post-new.php');
    
    // Wait for block editor to load
    await page.waitForSelector('.block-editor-page');
    await expect(page.locator('.edit-post-header__toolbar')).toBeVisible();
  });

  test('should find AI Text Generator block in inserter', async ({ page }) => {
    // Open block inserter
    await page.click('.edit-post-header-toolbar__inserter-toggle');
    
    // Search for AI Text Generator block
    await page.fill('.block-editor-inserter__search input', 'AI Text Generator');
    
    // Verify block appears in search results
    const blockItem = page.locator('.block-editor-block-types-list__item:has-text("AI Text Generator")');
    await expect(blockItem).toBeVisible();
    await expect(blockItem.locator('.block-editor-block-types-list__item-icon')).toBeVisible();
    await expect(blockItem).toContainText('Generate content using AI');
  });

  test('should insert AI Text Generator block', async ({ page }) => {
    // Open block inserter
    await page.click('.edit-post-header-toolbar__inserter-toggle');
    await page.fill('.block-editor-inserter__search input', 'AI Text Generator');
    
    // Insert the block
    await page.click('.block-editor-block-types-list__item:has-text("AI Text Generator")');
    
    // Verify block is inserted
    await expect(page.locator('[data-type="wp-content-flow/ai-text-generator"]')).toBeVisible();
    
    // Verify block controls appear
    const blockControls = page.locator('.wp-content-flow-ai-generator');
    await expect(blockControls.locator('.prompt-input')).toBeVisible();
    await expect(blockControls.locator('.generate-button')).toBeVisible();
    await expect(blockControls.locator('.provider-select')).toBeVisible();
  });

  test('should configure AI provider in block settings', async ({ page }) => {
    // Insert AI Text Generator block
    await page.click('.edit-post-header-toolbar__inserter-toggle');
    await page.fill('.block-editor-inserter__search input', 'AI Text Generator');
    await page.click('.block-editor-block-types-list__item:has-text("AI Text Generator")');
    
    // Select block and check sidebar
    await page.click('[data-type="wp-content-flow/ai-text-generator"]');
    
    // Open block settings sidebar
    const settingsButton = page.locator('.edit-post-header__settings button[aria-label*="Settings"]');
    if (await settingsButton.isVisible()) {
      await settingsButton.click();
    }
    
    // Verify AI provider settings
    const sidebar = page.locator('.edit-post-sidebar');
    await expect(sidebar.locator('select[id*="provider"]')).toBeVisible();
    await expect(sidebar.locator('input[id*="temperature"]')).toBeVisible();
    await expect(sidebar.locator('input[id*="max_tokens"]')).toBeVisible();
    
    // Test provider selection
    await sidebar.locator('select[id*="provider"]').selectOption('anthropic');
    await expect(sidebar.locator('select[id*="provider"]')).toHaveValue('anthropic');
  });

  test('should generate content with prompt', async ({ page }) => {
    // Setup mock API response
    await page.route('**/wp-json/wp-content-flow/v1/ai/generate', async route => {
      await route.fulfill({
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            content: 'This is AI-generated content based on your prompt.',
            usage: { tokens: 25 },
            provider: 'openai'
          }
        })
      });
    });
    
    // Insert AI Text Generator block
    await page.click('.edit-post-header-toolbar__inserter-toggle');
    await page.fill('.block-editor-inserter__search input', 'AI Text Generator');
    await page.click('.block-editor-block-types-list__item:has-text("AI Text Generator")');
    
    // Fill in prompt
    const promptInput = page.locator('.wp-content-flow-ai-generator .prompt-input');
    await promptInput.fill('Write a short paragraph about WordPress development');
    
    // Generate content
    await page.click('.wp-content-flow-ai-generator .generate-button');
    
    // Wait for generation to complete
    await expect(page.locator('.wp-content-flow-ai-generator .loading')).toBeVisible();
    await expect(page.locator('.wp-content-flow-ai-generator .loading')).not.toBeVisible({ timeout: 10000 });
    
    // Verify generated content appears
    const contentArea = page.locator('.wp-content-flow-ai-generator .generated-content');
    await expect(contentArea).toContainText('This is AI-generated content');
    
    // Verify accept/reject buttons
    await expect(page.locator('.wp-content-flow-ai-generator .accept-button')).toBeVisible();
    await expect(page.locator('.wp-content-flow-ai-generator .reject-button')).toBeVisible();
  });

  test('should accept generated content', async ({ page }) => {
    // Setup mock API response
    await page.route('**/wp-json/wp-content-flow/v1/ai/generate', async route => {
      await route.fulfill({
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            content: 'Accepted AI-generated content for WordPress.',
            usage: { tokens: 20 },
            provider: 'openai'
          }
        })
      });
    });
    
    // Insert block and generate content
    await page.click('.edit-post-header-toolbar__inserter-toggle');
    await page.fill('.block-editor-inserter__search input', 'AI Text Generator');
    await page.click('.block-editor-block-types-list__item:has-text("AI Text Generator")');
    
    await page.fill('.wp-content-flow-ai-generator .prompt-input', 'Test prompt');
    await page.click('.wp-content-flow-ai-generator .generate-button');
    await expect(page.locator('.wp-content-flow-ai-generator .loading')).not.toBeVisible({ timeout: 10000 });
    
    // Accept the generated content
    await page.click('.wp-content-flow-ai-generator .accept-button');
    
    // Verify content is converted to paragraph block
    await expect(page.locator('[data-type="core/paragraph"]')).toContainText('Accepted AI-generated content');
    
    // Verify AI generator block is removed
    await expect(page.locator('[data-type="wp-content-flow/ai-text-generator"]')).not.toBeVisible();
  });

  test('should reject generated content', async ({ page }) => {
    // Setup mock API response
    await page.route('**/wp-json/wp-content-flow/v1/ai/generate', async route => {
      await route.fulfill({
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            content: 'Content to be rejected.',
            usage: { tokens: 15 },
            provider: 'openai'
          }
        })
      });
    });
    
    // Insert block and generate content
    await page.click('.edit-post-header-toolbar__inserter-toggle');
    await page.fill('.block-editor-inserter__search input', 'AI Text Generator');
    await page.click('.block-editor-block-types-list__item:has-text("AI Text Generator")');
    
    await page.fill('.wp-content-flow-ai-generator .prompt-input', 'Test prompt');
    await page.click('.wp-content-flow-ai-generator .generate-button');
    await expect(page.locator('.wp-content-flow-ai-generator .loading')).not.toBeVisible({ timeout: 10000 });
    
    // Reject the generated content
    await page.click('.wp-content-flow-ai-generator .reject-button');
    
    // Verify content is cleared but block remains
    await expect(page.locator('.wp-content-flow-ai-generator .generated-content')).toBeEmpty();
    await expect(page.locator('[data-type="wp-content-flow/ai-text-generator"]')).toBeVisible();
    
    // Verify prompt field is cleared for retry
    await expect(page.locator('.wp-content-flow-ai-generator .prompt-input')).toHaveValue('');
  });

  test('should handle API errors gracefully', async ({ page }) => {
    // Setup mock API error response
    await page.route('**/wp-json/wp-content-flow/v1/ai/generate', async route => {
      await route.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({
          success: false,
          data: { message: 'API key not configured' }
        })
      });
    });
    
    // Insert block and attempt generation
    await page.click('.edit-post-header-toolbar__inserter-toggle');
    await page.fill('.block-editor-inserter__search input', 'AI Text Generator');
    await page.click('.block-editor-block-types-list__item:has-text("AI Text Generator")');
    
    await page.fill('.wp-content-flow-ai-generator .prompt-input', 'Test prompt');
    await page.click('.wp-content-flow-ai-generator .generate-button');
    
    // Verify error message appears
    await expect(page.locator('.wp-content-flow-ai-generator .error-message')).toContainText('API key not configured');
    
    // Verify loading state is cleared
    await expect(page.locator('.wp-content-flow-ai-generator .loading')).not.toBeVisible();
    
    // Verify generate button is re-enabled
    await expect(page.locator('.wp-content-flow-ai-generator .generate-button')).toBeEnabled();
  });

  test('should save and restore block state', async ({ page }) => {
    // Insert AI Text Generator block
    await page.click('.edit-post-header-toolbar__inserter-toggle');
    await page.fill('.block-editor-inserter__search input', 'AI Text Generator');
    await page.click('.block-editor-block-types-list__item:has-text("AI Text Generator")');
    
    // Configure block settings
    await page.fill('.wp-content-flow-ai-generator .prompt-input', 'Persistent prompt text');
    await page.selectOption('.wp-content-flow-ai-generator .provider-select', 'anthropic');
    
    // Save post draft
    await page.click('.editor-post-save-draft');
    await expect(page.locator('.editor-post-saved-state')).toContainText('Saved');
    
    // Reload page
    await page.reload();
    await page.waitForSelector('.block-editor-page');
    
    // Verify block state is restored
    await expect(page.locator('[data-type="wp-content-flow/ai-text-generator"]')).toBeVisible();
    await expect(page.locator('.wp-content-flow-ai-generator .prompt-input')).toHaveValue('Persistent prompt text');
    await expect(page.locator('.wp-content-flow-ai-generator .provider-select')).toHaveValue('anthropic');
  });

  test('should display token usage information', async ({ page }) => {
    // Setup mock API response with usage data
    await page.route('**/wp-json/wp-content-flow/v1/ai/generate', async route => {
      await route.fulfill({
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            content: 'Generated content with token tracking.',
            usage: { 
              prompt_tokens: 15,
              completion_tokens: 25,
              total_tokens: 40 
            },
            provider: 'openai'
          }
        })
      });
    });
    
    // Insert block and generate content
    await page.click('.edit-post-header-toolbar__inserter-toggle');
    await page.fill('.block-editor-inserter__search input', 'AI Text Generator');
    await page.click('.block-editor-block-types-list__item:has-text("AI Text Generator")');
    
    await page.fill('.wp-content-flow-ai-generator .prompt-input', 'Track tokens');
    await page.click('.wp-content-flow-ai-generator .generate-button');
    await expect(page.locator('.wp-content-flow-ai-generator .loading')).not.toBeVisible({ timeout: 10000 });
    
    // Verify token usage is displayed
    const usageInfo = page.locator('.wp-content-flow-ai-generator .usage-info');
    await expect(usageInfo).toContainText('40 tokens');
    await expect(usageInfo).toContainText('OpenAI');
  });
});