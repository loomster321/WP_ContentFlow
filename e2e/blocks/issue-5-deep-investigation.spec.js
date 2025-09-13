const { test, expect } = require('@playwright/test');

test.describe('Issue #5 - Deep Investigation of Save Errors', () => {
  test('Investigate save failures and console errors', async ({ page }) => {
    // Capture all console messages
    const consoleMessages = [];
    page.on('console', msg => {
      consoleMessages.push({
        type: msg.type(),
        text: msg.text(),
        location: msg.location()
      });
    });

    // Capture network failures
    const networkErrors = [];
    page.on('requestfailed', request => {
      networkErrors.push({
        url: request.url(),
        failure: request.failure()
      });
    });

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

    // Navigate to new post
    await page.goto('http://localhost:8080/wp-admin/post-new.php');
    await page.waitForSelector('.block-editor-page');

    // Add title
    await page.fill('[aria-label="Add title"]', 'Test Save Error Investigation');

    // Add AI Text Generator block
    const inserterButton = page.locator('button[aria-label="Toggle block inserter"]').first();
    await inserterButton.click();
    await page.waitForTimeout(500);
    
    const searchInput = page.locator('.block-editor-inserter__search input[type="search"]');
    await searchInput.fill('ai text generator');
    await page.waitForTimeout(500);
    
    const aiBlock = page.locator('.block-editor-block-types-list__item-title:has-text("AI Text Generator")');
    if (await aiBlock.count() > 0) {
      await aiBlock.click();
      console.log('✓ AI block added');
    } else {
      // Add a paragraph block instead
      await searchInput.clear();
      await searchInput.fill('paragraph');
      await page.locator('.block-editor-block-types-list__item-title:has-text("Paragraph")').first().click();
      await page.keyboard.type('Test content');
      console.log('✓ Paragraph block added (AI block not available)');
    }

    // Monitor save request
    const savePromise = page.waitForResponse(response => 
      response.url().includes('/wp-json/wp/v2/posts') || 
      response.url().includes('autosave'),
      { timeout: 10000 }
    );

    // Try to save
    const saveDraftButton = page.locator('button:has-text("Save draft")').first();
    await saveDraftButton.click();
    
    try {
      const response = await savePromise;
      console.log('Save response status:', response.status());
      console.log('Save response URL:', response.url());
      
      if (!response.ok()) {
        const responseBody = await response.text();
        console.log('Error response body:', responseBody);
      }
    } catch (error) {
      console.log('Save request failed or timed out:', error.message);
    }

    // Wait for any error messages
    await page.waitForTimeout(2000);

    // Check for various error indicators
    const updateFailed = await page.locator('text="Updating failed"').count();
    const saveFailed = await page.locator('text="Saving failed"').count();
    const unexpectedError = await page.locator('text="unexpected error"').count();
    
    console.log('\n=== ERROR INDICATORS ===');
    console.log('Updating failed:', updateFailed > 0);
    console.log('Saving failed:', saveFailed > 0);
    console.log('Unexpected error:', unexpectedError > 0);

    console.log('\n=== CONSOLE ERRORS ===');
    const errors = consoleMessages.filter(m => m.type === 'error');
    errors.forEach(err => {
      console.log(`Error: ${err.text}`);
      if (err.location) {
        console.log(`  at ${err.location.url}:${err.location.lineNumber}`);
      }
    });

    console.log('\n=== NETWORK ERRORS ===');
    networkErrors.forEach(err => {
      console.log(`Failed: ${err.url}`);
      console.log(`  Reason: ${err.failure?.errorText}`);
    });

    // Take final screenshot
    await page.screenshot({ 
      path: '/home/timl/dev/WP_ContentFlow/e2e/issue-5-investigation.png',
      fullPage: true 
    });

    // Return findings
    return {
      hasUpdateError: updateFailed > 0,
      hasSaveError: saveFailed > 0,
      hasUnexpectedError: unexpectedError > 0,
      consoleErrorCount: errors.length,
      networkErrorCount: networkErrors.length
    };
  });
});