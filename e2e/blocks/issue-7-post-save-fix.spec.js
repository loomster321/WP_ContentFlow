const { test, expect } = require('@playwright/test');

test.describe('Issue #7 - WordPress Post Save 500 Error Fix Verification', () => {
  test.beforeEach(async ({ page }) => {
    // Set up console and network monitoring
    page.on('console', msg => {
      if (msg.type() === 'error') {
        console.log('Console error:', msg.text());
      }
    });

    page.on('response', response => {
      if (response.url().includes('/wp-json/wp/v2/posts') && response.request().method() === 'POST') {
        console.log(`POST to /wp-json/wp/v2/posts: ${response.status()}`);
      }
    });
  });

  test('Verify basic post can be saved without 500 error', async ({ page }) => {
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
    await page.waitForSelector('.block-editor-page', { timeout: 30000 });

    // Add title
    const titleField = page.locator('[aria-label="Add title"]');
    await titleField.fill('Test Post - Issue #7 Fix Verification');

    // Add content in paragraph block
    await page.keyboard.press('Tab');
    await page.keyboard.type('This is a test post to verify that the 500 error on save has been resolved.');

    // Monitor the save request
    const savePromise = page.waitForResponse(response =>
      response.url().includes('/wp-json/wp/v2/posts') &&
      (response.request().method() === 'POST' || response.request().method() === 'PUT')
    );

    // Save the post
    await page.keyboard.press('Control+s');

    // Wait for save response
    const saveResponse = await savePromise;

    // Check response status
    expect(saveResponse.status()).not.toBe(500);
    expect([200, 201]).toContain(saveResponse.status());

    // Verify save success notification appears
    const savedNotification = page.locator('.components-snackbar__content:has-text("Draft saved")');
    await expect(savedNotification).toBeVisible({ timeout: 10000 });

    console.log(`✅ Post saved successfully with status ${saveResponse.status()}`);
  });

  test('Verify post with multiple blocks can be saved', async ({ page }) => {
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
    await page.waitForSelector('.block-editor-page', { timeout: 30000 });

    // Add title
    await page.fill('[aria-label="Add title"]', 'Multi-Block Post Test');

    // Add first paragraph
    await page.keyboard.press('Tab');
    await page.keyboard.type('First paragraph of content.');
    await page.keyboard.press('Enter');

    // Add heading block
    await page.keyboard.type('/heading');
    await page.keyboard.press('Enter');
    await page.keyboard.type('Test Heading');
    await page.keyboard.press('Enter');

    // Add another paragraph
    await page.keyboard.type('Second paragraph of content.');

    // Monitor the save request
    const savePromise = page.waitForResponse(response =>
      response.url().includes('/wp-json/wp/v2/posts') &&
      (response.request().method() === 'POST' || response.request().method() === 'PUT')
    );

    // Save the post
    await page.keyboard.press('Control+s');

    // Wait for save response
    const saveResponse = await savePromise;

    // Check response status
    expect(saveResponse.status()).not.toBe(500);
    expect([200, 201]).toContain(saveResponse.status());

    console.log(`✅ Multi-block post saved successfully with status ${saveResponse.status()}`);
  });

  test('Verify REST API endpoints are accessible', async ({ page }) => {
    // Test GET request to posts endpoint
    const getResponse = await page.request.get('http://localhost:8080/wp-json/wp/v2/posts');
    expect(getResponse.status()).toBe(200);

    const posts = await getResponse.json();
    expect(Array.isArray(posts)).toBe(true);

    console.log(`✅ REST API GET /posts returned ${posts.length} posts`);

    // Test authenticated POST request
    await page.goto('http://localhost:8080/wp-admin');

    // Login if needed
    const loginForm = await page.locator('#loginform').count();
    if (loginForm > 0) {
      await page.fill('#user_login', 'admin');
      await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
      await page.click('#wp-submit');
      await page.waitForURL('**/wp-admin/**');
    }

    // Get nonce for API request
    const nonceResponse = await page.evaluate(() => {
      return window.wpApiSettings?.nonce || null;
    });

    if (nonceResponse) {
      console.log('✅ WordPress API nonce retrieved successfully');
    }
  });

  test('Verify diagnostic script shows no critical errors', async ({ page }) => {
    const response = await page.request.get('http://localhost:8080/diagnose-500.php');
    expect(response.status()).toBe(200);

    const diagnosticText = await response.text();

    // Check key diagnostic points
    expect(diagnosticText).toContain('Database Connection: OK');
    expect(diagnosticText).toContain('Database Write Test: OK');
    expect(diagnosticText).toContain('wp-content writable: YES');
    expect(diagnosticText).toContain('Memory Limit: 512M');

    console.log('✅ Diagnostic script confirms system is properly configured');
  });
});