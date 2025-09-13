const { test, expect } = require('@playwright/test');

test.describe('Issue #5 - WordPress Post Editor Crashes on Save', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to WordPress admin
    await page.goto('http://localhost:8080/wp-admin');
    
    // Login if needed
    const loginForm = await page.locator('#loginform').count();
    if (loginForm > 0) {
      await page.fill('#user_login', 'admin');
      await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
      await page.click('#wp-submit');
      await page.waitForURL('**/wp-admin/**');
    }
  });

  test('Reproduce editor crash when saving post with AI-generated content', async ({ page }) => {
    console.log('Starting Issue #5 reproduction test...');
    
    // Step 1: Navigate to Posts → Add New
    await page.goto('http://localhost:8080/wp-admin/post-new.php');
    await page.waitForSelector('.block-editor-page', { timeout: 10000 });
    console.log('✓ Navigated to new post editor');
    
    // Step 2: Check if AI Text Generator block is available
    const inserterButton = page.locator('button[aria-label="Toggle block inserter"]').first();
    await inserterButton.click();
    await page.waitForTimeout(1000);
    
    // Search for AI Text Generator block
    const searchInput = page.locator('.block-editor-inserter__search input[type="search"]');
    await searchInput.fill('ai text generator');
    await page.waitForTimeout(500);
    
    // Check if block exists
    const aiBlockExists = await page.locator('.block-editor-block-types-list__item-title:has-text("AI Text Generator")').count();
    
    if (aiBlockExists === 0) {
      console.log('⚠️ AI Text Generator block not found - checking if plugin is active');
      
      // Close inserter
      await page.keyboard.press('Escape');
      
      // Navigate to plugins page to check status
      await page.goto('http://localhost:8080/wp-admin/plugins.php');
      const pluginRow = page.locator('tr[data-plugin="wp-content-flow/wp-content-flow.php"]');
      const isActive = await pluginRow.locator('.deactivate').count() > 0;
      
      if (!isActive) {
        console.log('❌ Plugin is not active - cannot reproduce issue without active plugin');
        throw new Error('WP Content Flow plugin is not active');
      }
      
      console.log('⚠️ Plugin is active but block not registered - this might be the issue');
      
      // Return to post editor
      await page.goto('http://localhost:8080/wp-admin/post-new.php');
      await page.waitForSelector('.block-editor-page');
    } else {
      console.log('✓ AI Text Generator block found');
      
      // Step 3: Add the AI Text Generator block
      await page.locator('.block-editor-block-types-list__item-title:has-text("AI Text Generator")').click();
      await page.waitForTimeout(1000);
      console.log('✓ AI Text Generator block added to post');
      
      // Step 4: Check for block interface and generate content
      const blockContainer = page.locator('[data-type="content-flow/ai-text-generator"]').first();
      const generateButton = blockContainer.locator('button:has-text("Generate"), button:has-text("Generate Content")').first();
      
      if (await generateButton.count() > 0) {
        console.log('✓ Generate button found - attempting content generation');
        
        // Monitor console for errors
        page.on('console', msg => {
          if (msg.type() === 'error') {
            console.log('Console Error:', msg.text());
          }
        });
        
        // Monitor for page errors
        page.on('pageerror', error => {
          console.log('Page Error:', error.message);
        });
        
        // Click generate button
        await generateButton.click();
        console.log('✓ Clicked generate button');
        
        // Wait for content generation or error
        await page.waitForTimeout(3000);
        
        // Check if content was generated
        const blockContent = await blockContainer.locator('.block-editor-rich-text__editable, [contenteditable="true"]').first();
        const hasContent = await blockContent.count() > 0;
        
        if (hasContent) {
          const contentText = await blockContent.textContent();
          console.log('✓ Content generated:', contentText?.substring(0, 50) + '...');
        } else {
          console.log('⚠️ No content generated - block might not be functioning');
        }
      } else {
        console.log('⚠️ Generate button not found - block UI might be broken');
        
        // Try to add some manual content
        const editableArea = blockContainer.locator('[contenteditable="true"]').first();
        if (await editableArea.count() > 0) {
          await editableArea.fill('Test content for save attempt');
          console.log('✓ Added manual test content');
        }
      }
    }
    
    // Step 5: Add post title
    await page.fill('[aria-label="Add title"]', 'Test Post for Issue #5');
    console.log('✓ Added post title');
    
    // Step 6: Attempt to save as draft
    console.log('Attempting to save post as draft...');
    
    // Check for the error dialog before save
    const errorDialogBefore = await page.locator('.editor-error-boundary').count();
    console.log(`Error dialogs before save: ${errorDialogBefore}`);
    
    // Try to save
    const saveDraftButton = page.locator('button:has-text("Save draft")').first();
    const publishButton = page.locator('button:has-text("Publish")').first();
    
    let saveButton;
    if (await saveDraftButton.count() > 0) {
      saveButton = saveDraftButton;
      console.log('Using "Save draft" button');
    } else if (await publishButton.count() > 0) {
      saveButton = publishButton;
      console.log('Using "Publish" button (no draft option available)');
    } else {
      console.log('❌ No save button found');
      throw new Error('No save button available');
    }
    
    // Click save and wait for response
    await Promise.all([
      saveButton.click(),
      page.waitForResponse(response => 
        response.url().includes('/wp-json/wp/v2/posts') || 
        response.url().includes('post.php'),
        { timeout: 10000 }
      ).catch(() => console.log('Save request timeout or different endpoint used'))
    ]);
    
    // Step 7: Check for editor crash
    await page.waitForTimeout(2000);
    
    // Check for the error dialog matching the screenshot
    const errorDialog = page.locator('.editor-error-boundary, .components-modal__content:has-text("The editor has encountered an unexpected error")');
    const hasError = await errorDialog.count() > 0;
    
    // Also check for the specific error text from screenshot
    const unexpectedErrorText = await page.locator('text="The editor has encountered an unexpected error"').count() > 0;
    const copyPostTextButton = await page.locator('button:has-text("Copy Post Text")').count() > 0;
    const copyErrorButton = await page.locator('button:has-text("Copy Error")').count() > 0;
    
    // Take screenshot for evidence
    await page.screenshot({ 
      path: '/home/timl/dev/WP_ContentFlow/e2e/issue-5-reproduction-result.png',
      fullPage: true 
    });
    
    if (hasError || unexpectedErrorText || (copyPostTextButton && copyErrorButton)) {
      console.log('✅ ISSUE REPRODUCED: Editor crashed with "unexpected error"');
      console.log(`- Error dialog present: ${hasError}`);
      console.log(`- Unexpected error text: ${unexpectedErrorText}`);
      console.log(`- Copy Post Text button: ${copyPostTextButton}`);
      console.log(`- Copy Error button: ${copyErrorButton}`);
      
      // Try to copy the error details
      if (copyErrorButton) {
        const copyErrorBtn = page.locator('button:has-text("Copy Error")').first();
        await copyErrorBtn.click();
        console.log('Clicked "Copy Error" button to get stack trace');
      }
      
      // Check browser console for errors
      const consoleErrors = [];
      page.on('console', msg => {
        if (msg.type() === 'error') {
          consoleErrors.push(msg.text());
        }
      });
      
      if (consoleErrors.length > 0) {
        console.log('Browser console errors:', consoleErrors);
      }
      
      return true; // Issue reproduced
    } else {
      console.log('❌ ISSUE NOT REPRODUCED: Editor did not crash');
      console.log('Post appears to have saved successfully or different error occurred');
      
      // Check if post was actually saved
      const savedMessage = await page.locator('.components-snackbar__content:has-text("Draft saved")').count() > 0;
      const publishedMessage = await page.locator('.components-snackbar__content:has-text("Post published")').count() > 0;
      
      if (savedMessage || publishedMessage) {
        console.log('✓ Post saved successfully without error');
      }
      
      return false; // Issue not reproduced
    }
  });
});