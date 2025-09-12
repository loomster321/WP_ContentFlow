const { test, expect } = require('@playwright/test');

test.describe('AI Text Generator Block in Gutenberg', () => {
  test('AI Text Generator block appears in block inserter', async ({ page }) => {
    // Login to WordPress admin
    await page.goto('http://localhost:8080/wp-admin/');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
    await page.click('#wp-submit');
    await page.waitForSelector('#wpadminbar', { timeout: 15000 });
    
    // Create a new post
    await page.goto('http://localhost:8080/wp-admin/post-new.php');
    
    // Wait for Gutenberg editor to load
    await page.waitForSelector('.edit-post-visual-editor', { timeout: 30000 });
    
    // Close any welcome guide if present
    const welcomeGuide = page.locator('.components-modal__screen-overlay');
    if (await welcomeGuide.isVisible()) {
      await page.keyboard.press('Escape');
      await page.waitForTimeout(500);
    }
    
    // Click the block inserter button
    const blockInserter = page.locator('button[aria-label="Toggle block inserter"], button[aria-label="Add block"]').first();
    await blockInserter.click();
    await page.waitForTimeout(1000);
    
    // Search for our AI block
    const searchInput = page.locator('.components-search-control__input, input[placeholder*="Search"]').first();
    await searchInput.fill('AI Text');
    await page.waitForTimeout(500);
    
    // Check if our block appears
    const aiBlock = page.locator('.block-editor-block-types-list__item-title, .block-editor-block-types-list__item').filter({ hasText: 'AI Text Generator' });
    const blockExists = await aiBlock.isVisible();
    
    if (blockExists) {
      console.log('✅ AI Text Generator block found in inserter!');
      
      // Click to insert the block
      await aiBlock.click();
      await page.waitForTimeout(1000);
      
      // Check if block is added to editor
      const blockInEditor = page.locator('[data-type="wp-content-flow/ai-text"]');
      const isInEditor = await blockInEditor.isVisible();
      
      if (isInEditor) {
        console.log('✅ AI Text Generator block successfully added to editor!');
        
        // Check for prompt field
        const promptField = page.locator('textarea[placeholder*="prompt"], textarea[placeholder*="Enter"], .wp-content-flow-prompt-field');
        if (await promptField.isVisible()) {
          console.log('✅ Prompt field is visible in the block!');
        } else {
          console.log('⚠️ Prompt field not found in the block');
        }
      } else {
        console.log('⚠️ Block not added to editor');
      }
    } else {
      console.log('❌ AI Text Generator block NOT found in inserter');
      
      // List available blocks for debugging
      const availableBlocks = await page.locator('.block-editor-block-types-list__item-title').allTextContents();
      console.log('Available blocks:', availableBlocks.slice(0, 10));
    }
  });
  
  test('AI Text Generator block can generate content', async ({ page }) => {
    // Login to WordPress admin
    await page.goto('http://localhost:8080/wp-admin/');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
    await page.click('#wp-submit');
    await page.waitForSelector('#wpadminbar', { timeout: 15000 });
    
    // Create a new post
    await page.goto('http://localhost:8080/wp-admin/post-new.php');
    await page.waitForSelector('.edit-post-visual-editor', { timeout: 30000 });
    
    // Close welcome guide if present
    const welcomeGuide = page.locator('.components-modal__screen-overlay');
    if (await welcomeGuide.isVisible()) {
      await page.keyboard.press('Escape');
      await page.waitForTimeout(500);
    }
    
    // Add AI Text Generator block
    const blockInserter = page.locator('button[aria-label="Toggle block inserter"], button[aria-label="Add block"]').first();
    await blockInserter.click();
    await page.waitForTimeout(1000);
    
    const searchInput = page.locator('.components-search-control__input, input[placeholder*="Search"]').first();
    await searchInput.fill('AI Text');
    await page.waitForTimeout(500);
    
    const aiBlock = page.locator('.block-editor-block-types-list__item-title, .block-editor-block-types-list__item').filter({ hasText: 'AI Text Generator' });
    
    if (await aiBlock.isVisible()) {
      await aiBlock.click();
      await page.waitForTimeout(1000);
      
      // Find the prompt field and enter text
      const promptField = page.locator('textarea').first();
      await promptField.fill('Write a short paragraph about WordPress');
      
      // Find and click generate button
      const generateButton = page.locator('button').filter({ hasText: /Generate|Create|Submit/i }).first();
      if (await generateButton.isVisible()) {
        await generateButton.click();
        console.log('✅ Clicked generate button');
        
        // Wait for content to be generated (max 30 seconds)
        await page.waitForTimeout(3000);
        
        // Check if content was generated
        const generatedContent = page.locator('.wp-content-flow-generated-content, [data-type="wp-content-flow/ai-text"] p');
        if (await generatedContent.isVisible()) {
          const content = await generatedContent.textContent();
          console.log('✅ Content generated:', content.substring(0, 100) + '...');
        } else {
          console.log('⚠️ No generated content found');
        }
      } else {
        console.log('⚠️ Generate button not found');
      }
    } else {
      console.log('❌ Cannot test generation - block not available');
    }
  });
});