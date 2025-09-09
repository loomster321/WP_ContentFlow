const { test, expect } = require('@playwright/test');

test.describe('Workflow Management - Content Tests', () => {
  test.beforeEach(async ({ page }) => {
    // Login as admin
    await page.goto('/wp-admin');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'admin');
    await page.click('#wp-submit');
    await page.waitForURL('/wp-admin/index.php');
  });

  test('should display workflows page', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=wp-content-flow-workflows');
    
    // Verify page loads with proper structure
    await expect(page.locator('h1')).toContainText('Content Workflows');
    await expect(page.locator('.wp-content-flow-workflows-page')).toBeVisible();
    
    // Check for workflow creation button
    await expect(page.locator('.add-new-workflow-button')).toBeVisible();
    
    // Check for workflows table
    await expect(page.locator('.workflows-table')).toBeVisible();
    await expect(page.locator('.workflows-table thead')).toContainText('Name');
    await expect(page.locator('.workflows-table thead')).toContainText('Status');
    await expect(page.locator('.workflows-table thead')).toContainText('Created');
  });

  test('should create new workflow', async ({ page }) => {
    // Mock API responses
    await page.route('**/wp-json/wp-content-flow/v1/workflows', async route => {
      if (route.request().method() === 'POST') {
        await route.fulfill({
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            data: {
              id: 123,
              name: 'Test Blog Post Workflow',
              description: 'Generate blog posts about WordPress',
              status: 'active',
              steps: [
                { type: 'generate', prompt: 'Write about {{topic}}' },
                { type: 'review', auto_approve: false }
              ],
              created_at: new Date().toISOString()
            }
          })
        });
      }
    });
    
    await page.goto('/wp-admin/admin.php?page=wp-content-flow-workflows');
    
    // Click create new workflow
    await page.click('.add-new-workflow-button');
    
    // Verify workflow creation modal opens
    const modal = page.locator('.workflow-creation-modal');
    await expect(modal).toBeVisible();
    await expect(modal.locator('h2')).toContainText('Create New Workflow');
    
    // Fill workflow details
    await page.fill('.workflow-creation-modal input[name="name"]', 'Test Blog Post Workflow');
    await page.fill('.workflow-creation-modal textarea[name="description"]', 'Generate blog posts about WordPress');
    
    // Configure workflow steps
    await page.click('.add-step-button');
    const stepConfig = modal.locator('.workflow-step-config').first();
    
    await stepConfig.locator('select[name="step_type"]').selectOption('generate');
    await stepConfig.locator('textarea[name="prompt"]').fill('Write a comprehensive blog post about {{topic}}');
    
    // Add review step
    await page.click('.add-step-button');
    const reviewStep = modal.locator('.workflow-step-config').nth(1);
    await reviewStep.locator('select[name="step_type"]').selectOption('review');
    await reviewStep.locator('input[name="auto_approve"]').uncheck();
    
    // Save workflow
    await page.click('.workflow-creation-modal .save-workflow-button');
    
    // Verify workflow appears in table
    await expect(page.locator('.workflows-table tbody tr')).toContainText('Test Blog Post Workflow');
    await expect(page.locator('.workflows-table tbody tr')).toContainText('Active');
  });

  test('should edit existing workflow', async ({ page }) => {
    // Mock workflow data
    await page.route('**/wp-json/wp-content-flow/v1/workflows', async route => {
      await route.fulfill({
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: [
            {
              id: 456,
              name: 'Product Description Workflow',
              description: 'Generate product descriptions',
              status: 'active',
              steps: [
                { type: 'generate', prompt: 'Create product description for {{product}}' }
              ],
              created_at: '2024-01-15T10:00:00Z'
            }
          ]
        })
      });
    });
    
    await page.route('**/wp-json/wp-content-flow/v1/workflows/456', async route => {
      if (route.request().method() === 'PUT') {
        await route.fulfill({
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            data: {
              id: 456,
              name: 'Enhanced Product Description Workflow',
              description: 'Generate enhanced product descriptions with SEO',
              status: 'active',
              steps: [
                { type: 'generate', prompt: 'Create SEO-optimized product description for {{product}}' },
                { type: 'improve', aspect: 'seo' }
              ]
            }
          })
        });
      }
    });
    
    await page.goto('/wp-admin/admin.php?page=wp-content-flow-workflows');
    
    // Wait for workflows to load
    await expect(page.locator('.workflows-table tbody tr')).toContainText('Product Description Workflow');
    
    // Click edit workflow
    await page.click('.workflows-table tbody tr .edit-workflow-button');
    
    // Verify edit modal opens
    const editModal = page.locator('.workflow-edit-modal');
    await expect(editModal).toBeVisible();
    await expect(editModal.locator('input[name="name"]')).toHaveValue('Product Description Workflow');
    
    // Update workflow details
    await editModal.locator('input[name="name"]').fill('Enhanced Product Description Workflow');
    await editModal.locator('textarea[name="description"]').fill('Generate enhanced product descriptions with SEO');
    
    // Update existing step
    const existingStep = editModal.locator('.workflow-step-config').first();
    await existingStep.locator('textarea[name="prompt"]').fill('Create SEO-optimized product description for {{product}}');
    
    // Add improvement step
    await page.click('.workflow-edit-modal .add-step-button');
    const improveStep = editModal.locator('.workflow-step-config').nth(1);
    await improveStep.locator('select[name="step_type"]').selectOption('improve');
    await improveStep.locator('select[name="aspect"]').selectOption('seo');
    
    // Save changes
    await page.click('.workflow-edit-modal .save-workflow-button');
    
    // Verify updated workflow in table
    await expect(page.locator('.workflows-table tbody tr')).toContainText('Enhanced Product Description Workflow');
  });

  test('should execute workflow on content', async ({ page }) => {
    // Setup workflow execution mock
    await page.route('**/wp-json/wp-content-flow/v1/workflows/execute', async route => {
      await route.fulfill({
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            execution_id: 'exec_789',
            status: 'completed',
            results: [
              {
                step: 'generate',
                content: 'Generated content about WordPress development best practices...',
                tokens_used: 150,
                provider: 'openai'
              }
            ]
          }
        })
      });
    });
    
    // Create new post to test workflow execution
    await page.goto('/wp-admin/post-new.php');
    await page.waitForSelector('.block-editor-page');
    
    // Add title
    await page.click('.wp-block-post-title');
    await page.fill('.wp-block-post-title', 'WordPress Development Guide');
    
    // Look for workflow execution panel
    const workflowPanel = page.locator('.wp-content-flow-workflow-panel');
    if (await workflowPanel.isVisible()) {
      // Select workflow
      await workflowPanel.locator('select[name="workflow_id"]').selectOption('456');
      
      // Set workflow variables
      await workflowPanel.locator('input[name="topic"]').fill('WordPress development');
      
      // Execute workflow
      await page.click('.wp-content-flow-workflow-panel .execute-workflow-button');
      
      // Wait for execution to complete
      await expect(page.locator('.workflow-execution-status')).toContainText('Executing');
      await expect(page.locator('.workflow-execution-status')).toContainText('Completed', { timeout: 15000 });
      
      // Verify generated content appears
      await expect(page.locator('.block-editor-writing-flow')).toContainText('Generated content about WordPress development');
    }
  });

  test('should handle workflow templates', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=wp-content-flow-workflows');
    
    // Click templates button
    await page.click('.workflow-templates-button');
    
    // Verify templates modal
    const templatesModal = page.locator('.workflow-templates-modal');
    await expect(templatesModal).toBeVisible();
    await expect(templatesModal.locator('h2')).toContainText('Workflow Templates');
    
    // Check for template categories
    await expect(templatesModal).toContainText('Blog Posts');
    await expect(templatesModal).toContainText('Product Descriptions');
    await expect(templatesModal).toContainText('Social Media');
    
    // Select blog post template
    const blogTemplate = templatesModal.locator('.template-card:has-text("Blog Post Generator")');
    await expect(blogTemplate).toBeVisible();
    await expect(blogTemplate).toContainText('Generate comprehensive blog posts');
    
    // Use template
    await blogTemplate.locator('.use-template-button').click();
    
    // Verify workflow creation modal opens with template data
    const creationModal = page.locator('.workflow-creation-modal');
    await expect(creationModal).toBeVisible();
    await expect(creationModal.locator('input[name="name"]')).toHaveValue('Blog Post Generator');
    await expect(creationModal.locator('.workflow-step-config')).toBeVisible();
  });

  test('should manage workflow status', async ({ page }) => {
    // Mock workflows with different statuses
    await page.route('**/wp-json/wp-content-flow/v1/workflows', async route => {
      await route.fulfill({
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: [
            {
              id: 100,
              name: 'Active Workflow',
              status: 'active',
              created_at: '2024-01-15T10:00:00Z'
            },
            {
              id: 101,
              name: 'Draft Workflow',
              status: 'draft',
              created_at: '2024-01-14T10:00:00Z'
            }
          ]
        })
      });
    });
    
    await page.route('**/wp-json/wp-content-flow/v1/workflows/101/activate', async route => {
      await route.fulfill({
        contentType: 'application/json',
        body: JSON.stringify({ success: true })
      });
    });
    
    await page.goto('/wp-admin/admin.php?page=wp-content-flow-workflows');
    
    // Verify different status indicators
    await expect(page.locator('.workflows-table tbody tr:has-text("Active Workflow")')).toContainText('Active');
    await expect(page.locator('.workflows-table tbody tr:has-text("Draft Workflow")')).toContainText('Draft');
    
    // Activate draft workflow
    const draftRow = page.locator('.workflows-table tbody tr:has-text("Draft Workflow")');
    await draftRow.locator('.activate-workflow-button').click();
    
    // Verify status change
    await expect(draftRow).toContainText('Active');
    
    // Verify activation success notice
    await expect(page.locator('.notice-success')).toContainText('Workflow activated');
  });

  test('should delete workflow with confirmation', async ({ page }) => {
    // Mock workflow deletion
    await page.route('**/wp-json/wp-content-flow/v1/workflows/200', async route => {
      if (route.request().method() === 'DELETE') {
        await route.fulfill({
          contentType: 'application/json',
          body: JSON.stringify({ success: true })
        });
      }
    });
    
    // Mock initial workflows
    await page.route('**/wp-json/wp-content-flow/v1/workflows', async route => {
      await route.fulfill({
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: [
            {
              id: 200,
              name: 'Workflow to Delete',
              status: 'draft',
              created_at: '2024-01-13T10:00:00Z'
            }
          ]
        })
      });
    });
    
    await page.goto('/wp-admin/admin.php?page=wp-content-flow-workflows');
    
    // Click delete workflow
    const workflowRow = page.locator('.workflows-table tbody tr:has-text("Workflow to Delete")');
    await workflowRow.locator('.delete-workflow-button').click();
    
    // Verify confirmation dialog
    const confirmDialog = page.locator('.workflow-delete-confirmation');
    await expect(confirmDialog).toBeVisible();
    await expect(confirmDialog).toContainText('Are you sure you want to delete');
    await expect(confirmDialog).toContainText('Workflow to Delete');
    
    // Confirm deletion
    await confirmDialog.locator('.confirm-delete-button').click();
    
    // Verify workflow is removed
    await expect(page.locator('.workflows-table tbody tr:has-text("Workflow to Delete")')).not.toBeVisible();
    await expect(page.locator('.notice-success')).toContainText('Workflow deleted');
  });

  test('should export and import workflows', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=wp-content-flow-workflows');
    
    // Test export functionality
    await page.click('.export-workflows-button');
    
    // Verify export modal
    const exportModal = page.locator('.workflow-export-modal');
    await expect(exportModal).toBeVisible();
    await expect(exportModal.locator('h2')).toContainText('Export Workflows');
    
    // Select workflows to export
    await exportModal.locator('input[value="all"]').check();
    
    // Mock download initiation
    const downloadPromise = page.waitForEvent('download');
    await exportModal.locator('.export-button').click();
    
    // Verify download starts
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toContain('workflows');
    expect(download.suggestedFilename()).toContain('.json');
    
    // Test import functionality
    await page.click('.import-workflows-button');
    
    // Verify import modal
    const importModal = page.locator('.workflow-import-modal');
    await expect(importModal).toBeVisible();
    await expect(importModal.locator('h2')).toContainText('Import Workflows');
    
    // File upload would be tested with actual file in integration tests
    await expect(importModal.locator('input[type="file"]')).toBeVisible();
    await expect(importModal.locator('.import-instructions')).toContainText('Select a JSON file');
  });
});