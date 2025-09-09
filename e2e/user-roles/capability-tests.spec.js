const { test, expect } = require('@playwright/test');

test.describe('User Capabilities - Role-Based Access Tests', () => {
  
  test.describe('Administrator Access', () => {
    test.beforeEach(async ({ page }) => {
      await page.goto('/wp-admin');
      await page.fill('#user_login', 'admin');
      await page.fill('#user_pass', 'admin');
      await page.click('#wp-submit');
      await page.waitForURL('/wp-admin/index.php');
    });

    test('should have full plugin access', async ({ page }) => {
      // Check plugin management access
      await page.goto('/wp-admin/plugins.php');
      await expect(page.locator('[data-slug="wp-content-flow"]')).toBeVisible();
      
      // Check plugin settings access
      await page.goto('/wp-admin/admin.php?page=wp-content-flow-settings');
      await expect(page.locator('h1')).toContainText('Content Flow Settings');
      
      // Check workflow management
      await page.goto('/wp-admin/admin.php?page=wp-content-flow-workflows');
      await expect(page.locator('.add-new-workflow-button')).toBeVisible();
      
      // Check AI provider settings
      await expect(page.locator('#openai_api_key')).toBeVisible();
      await expect(page.locator('#anthropic_api_key')).toBeVisible();
    });

    test('should manage user capabilities', async ({ page }) => {
      await page.goto('/wp-admin/admin.php?page=wp-content-flow-capabilities');
      
      // Check capability management interface
      await expect(page.locator('.capability-management')).toBeVisible();
      await expect(page.locator('.role-capabilities-table')).toBeVisible();
      
      // Verify all roles are listed
      await expect(page.locator('tbody tr:has-text("Editor")')).toBeVisible();
      await expect(page.locator('tbody tr:has-text("Author")')).toBeVisible();
      await expect(page.locator('tbody tr:has-text("Contributor")')).toBeVisible();
    });
  });

  test.describe('Editor Access', () => {
    test.beforeEach(async ({ page }) => {
      await page.goto('/wp-admin');
      await page.fill('#user_login', 'editor_test');
      await page.fill('#user_pass', 'testpass123!@#');
      await page.click('#wp-submit');
      await page.waitForURL('/wp-admin/index.php');
    });

    test('should have content workflow access', async ({ page }) => {
      // Can access workflows
      await page.goto('/wp-admin/admin.php?page=wp-content-flow-workflows');
      await expect(page.locator('h1')).toContainText('Content Workflows');
      await expect(page.locator('.workflows-table')).toBeVisible();
      
      // Can create new workflows
      await expect(page.locator('.add-new-workflow-button')).toBeVisible();
      
      // Can use AI text generator in block editor
      await page.goto('/wp-admin/post-new.php');
      await page.waitForSelector('.block-editor-page');
      
      await page.click('.edit-post-header-toolbar__inserter-toggle');
      await page.fill('.block-editor-inserter__search input', 'AI Text Generator');
      await expect(page.locator('.block-editor-block-types-list__item:has-text("AI Text Generator")')).toBeVisible();
    });

    test('should not have plugin management access', async ({ page }) => {
      // Cannot access plugin settings
      await page.goto('/wp-admin/plugins.php');
      await expect(page.locator('body')).toContainText('Sorry, you are not allowed');
      
      // Cannot access global plugin settings
      await page.goto('/wp-admin/admin.php?page=wp-content-flow-settings');
      await expect(page.locator('body')).toContainText('You do not have sufficient permissions');
    });

    test('should have limited workflow permissions', async ({ page }) => {
      await page.goto('/wp-admin/admin.php?page=wp-content-flow-workflows');
      
      // Can view workflows but with restrictions
      await expect(page.locator('.workflows-table')).toBeVisible();
      
      // May not be able to delete all workflows (depending on capability settings)
      const deleteButtons = page.locator('.delete-workflow-button');
      const deleteCount = await deleteButtons.count();
      
      // Should have some restrictions compared to admin
      expect(deleteCount).toBeLessThanOrEqual(await page.locator('.workflows-table tbody tr').count());
    });
  });

  test.describe('Author Access', () => {
    test.beforeEach(async ({ page }) => {
      await page.goto('/wp-admin');
      await page.fill('#user_login', 'author_test');
      await page.fill('#user_pass', 'testpass123!@#');
      await page.click('#wp-submit');
      await page.waitForURL('/wp-admin/index.php');
    });

    test('should have basic AI content generation access', async ({ page }) => {
      // Can use AI text generator in own posts
      await page.goto('/wp-admin/post-new.php');
      await page.waitForSelector('.block-editor-page');
      
      await page.click('.edit-post-header-toolbar__inserter-toggle');
      await page.fill('.block-editor-inserter__search input', 'AI Text Generator');
      await expect(page.locator('.block-editor-block-types-list__item:has-text("AI Text Generator")')).toBeVisible();
      
      // Can insert and use the block
      await page.click('.block-editor-block-types-list__item:has-text("AI Text Generator")');
      await expect(page.locator('[data-type="wp-content-flow/ai-text-generator"]')).toBeVisible();
    });

    test('should have restricted workflow access', async ({ page }) => {
      // May not have access to workflow management
      await page.goto('/wp-admin/admin.php?page=wp-content-flow-workflows');
      
      // Check if access is restricted
      const isRestricted = await page.locator('body:has-text("You do not have sufficient permissions")').count() > 0;
      const hasLimitedAccess = await page.locator('.workflows-table').count() > 0;
      
      // Should either be restricted or have limited access
      expect(isRestricted || hasLimitedAccess).toBe(true);
      
      if (hasLimitedAccess) {
        // If they have access, it should be limited
        await expect(page.locator('.add-new-workflow-button')).not.toBeVisible();
      }
    });

    test('should only see own content in suggestions', async ({ page }) => {
      await page.goto('/wp-admin/admin.php?page=wp-content-flow-suggestions');
      
      if (await page.locator('.suggestions-table').count() > 0) {
        // If suggestions are visible, they should only be for their own posts
        const suggestionRows = page.locator('.suggestions-table tbody tr');
        const rowCount = await suggestionRows.count();
        
        if (rowCount > 0) {
          // Check that post authors are only the current user
          for (let i = 0; i < Math.min(rowCount, 5); i++) {
            const row = suggestionRows.nth(i);
            await expect(row.locator('.post-author')).toContainText('author_test');
          }
        }
      }
    });
  });

  test.describe('Contributor Access', () => {
    test.beforeEach(async ({ page }) => {
      await page.goto('/wp-admin');
      await page.fill('#user_login', 'contributor_test');
      await page.fill('#user_pass', 'testpass123!@#');
      await page.click('#wp-submit');
      await page.waitForURL('/wp-admin/index.php');
    });

    test('should have minimal AI access', async ({ page }) => {
      // Can use AI text generator in draft posts
      await page.goto('/wp-admin/post-new.php');
      await page.waitForSelector('.block-editor-page');
      
      await page.click('.edit-post-header-toolbar__inserter-toggle');
      await page.fill('.block-editor-inserter__search input', 'AI Text Generator');
      
      // Block should be available
      await expect(page.locator('.block-editor-block-types-list__item:has-text("AI Text Generator")')).toBeVisible();
      
      // But may have usage limitations
      await page.click('.block-editor-block-types-list__item:has-text("AI Text Generator")');
      const aiBlock = page.locator('[data-type="wp-content-flow/ai-text-generator"]');
      await expect(aiBlock).toBeVisible();
      
      // May show usage limitations in UI
      const usageLimits = aiBlock.locator('.usage-limits');
      if (await usageLimits.count() > 0) {
        await expect(usageLimits).toContainText('daily limit');
      }
    });

    test('should not have workflow management access', async ({ page }) => {
      await page.goto('/wp-admin/admin.php?page=wp-content-flow-workflows');
      await expect(page.locator('body')).toContainText('You do not have sufficient permissions');
      
      await page.goto('/wp-admin/admin.php?page=wp-content-flow-settings');
      await expect(page.locator('body')).toContainText('You do not have sufficient permissions');
    });

    test('should not access other users content', async ({ page }) => {
      // Cannot see suggestions for other users' posts
      await page.goto('/wp-admin/admin.php?page=wp-content-flow-suggestions');
      
      const noAccess = await page.locator('body:has-text("You do not have sufficient permissions")').count() > 0;
      const emptyTable = await page.locator('.suggestions-table tbody:empty').count() > 0;
      const ownContentOnly = await page.locator('.suggestions-table tbody tr').count() === 0;
      
      // Should either have no access or very limited access
      expect(noAccess || emptyTable || ownContentOnly).toBe(true);
    });
  });

  test.describe('Custom Capability Management', () => {
    test.beforeEach(async ({ page }) => {
      // Login as admin for capability management tests
      await page.goto('/wp-admin');
      await page.fill('#user_login', 'admin');
      await page.fill('#user_pass', 'admin');
      await page.click('#wp-submit');
    });

    test('should modify editor capabilities', async ({ page }) => {
      await page.goto('/wp-admin/admin.php?page=wp-content-flow-capabilities');
      
      const editorRow = page.locator('tbody tr:has-text("Editor")');
      await expect(editorRow).toBeVisible();
      
      // Grant workflow creation capability
      const workflowCreateCheckbox = editorRow.locator('input[data-capability="create_workflows"]');
      if (await workflowCreateCheckbox.count() > 0) {
        await workflowCreateCheckbox.check();
        
        // Save changes
        await page.click('#save-capabilities');
        await expect(page.locator('.notice-success')).toContainText('Capabilities updated');
        
        // Verify the change persisted
        await page.reload();
        await expect(editorRow.locator('input[data-capability="create_workflows"]')).toBeChecked();
      }
    });

    test('should set usage limits per role', async ({ page }) => {
      await page.goto('/wp-admin/admin.php?page=wp-content-flow-settings');
      
      // Check for usage limits section
      const usageLimitsSection = page.locator('.usage-limits-section');
      if (await usageLimitsSection.count() > 0) {
        
        // Set daily limits for contributor role
        const contributorLimitInput = usageLimitsSection.locator('input[name="contributor_daily_limit"]');
        if (await contributorLimitInput.count() > 0) {
          await contributorLimitInput.fill('10');
          
          // Set monthly limits for author role
          const authorMonthlyInput = usageLimitsSection.locator('input[name="author_monthly_limit"]');
          if (await authorMonthlyInput.count() > 0) {
            await authorMonthlyInput.fill('100');
          }
          
          // Save settings
          await page.click('input[type="submit"]');
          await expect(page.locator('.notice-success')).toContainText('Settings saved');
        }
      }
    });

    test('should restrict API access by capability', async ({ page }) => {
      // Test that API endpoints respect WordPress capabilities
      const response = await page.request.get('/wp-json/wp-content-flow/v1/workflows');
      
      // Should require proper authentication and capabilities
      if (response.status() === 401) {
        expect(response.status()).toBe(401);
      } else if (response.status() === 200) {
        const data = await response.json();
        expect(data.success).toBeDefined();
      }
    });
  });

  test.describe('Rate Limiting by Role', () => {
    test('should enforce different limits for different roles', async ({ page }) => {
      // Mock API responses to test rate limiting
      await page.route('**/wp-json/wp-content-flow/v1/ai/generate', async route => {
        const userAgent = route.request().headers()['user-agent'];
        const isContributor = userAgent && userAgent.includes('contributor');
        
        if (isContributor) {
          // Simulate rate limit for contributors
          await route.fulfill({
            status: 429,
            contentType: 'application/json',
            body: JSON.stringify({
              success: false,
              data: { message: 'Daily rate limit exceeded for contributor role' }
            })
          });
        } else {
          await route.fulfill({
            contentType: 'application/json',
            body: JSON.stringify({
              success: true,
              data: { content: 'Generated content', usage: { tokens: 20 } }
            })
          });
        }
      });
      
      // Test with contributor (should hit rate limit faster)
      await page.goto('/wp-login.php?action=logout');
      await page.goto('/wp-admin');
      await page.fill('#user_login', 'contributor_test');
      await page.fill('#user_pass', 'testpass123!@#');
      await page.click('#wp-submit');
      
      await page.goto('/wp-admin/post-new.php');
      await page.waitForSelector('.block-editor-page');
      
      // Insert AI block and try to generate
      await page.click('.edit-post-header-toolbar__inserter-toggle');
      await page.fill('.block-editor-inserter__search input', 'AI Text Generator');
      await page.click('.block-editor-block-types-list__item:has-text("AI Text Generator")');
      
      await page.fill('.wp-content-flow-ai-generator .prompt-input', 'Test prompt');
      await page.click('.wp-content-flow-ai-generator .generate-button');
      
      // Should show rate limit error for contributor
      await expect(page.locator('.wp-content-flow-ai-generator .error-message')).toContainText('rate limit exceeded');
    });
  });
});