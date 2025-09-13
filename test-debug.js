const { chromium } = require('playwright');

(async () => {
  try {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();
    
    // Navigate to login page
    await page.goto('http://localhost:8080/wp-admin/');
    
    // Check if already logged in or need to log in
    const loginForm = await page.$('#loginform');
    if (loginForm) {
      console.log('Logging in...');
      await page.fill('#user_login', 'admin');
      await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
      await page.click('#wp-submit');
      await page.waitForSelector('.wrap', { timeout: 10000 });
    }
    
    console.log('Logged in successfully');
    
    // Navigate to new post
    await page.goto('http://localhost:8080/wp-admin/post-new.php');
    console.log('Navigating to post editor...');
    
    // Wait for editor to load
    await page.waitForSelector('.wp-block-post-title', { timeout: 15000 });
    console.log('Editor loaded successfully');
    
    // Add some content
    await page.fill('.wp-block-post-title', 'Test Post Title');
    
    // Try to save the post
    console.log('Attempting to save post...');
    await page.keyboard.press('Control+s');
    
    // Wait for save completion
    await page.waitForTimeout(3000);
    
    // Check for success or error messages
    const saveMessage = await page.$('.editor-post-saved-state');
    if (saveMessage) {
      const saveText = await saveMessage.textContent();
      console.log('Save status:', saveText);
    }
    
    const errorNotice = await page.$('.components-notice.is-error, .notice-error');
    if (errorNotice) {
      const errorText = await errorNotice.textContent();
      console.log('ERROR FOUND:', errorText);
    } else {
      console.log('No errors detected during save');
    }
    
    await browser.close();
    console.log('Test completed');
    
  } catch (error) {
    console.error('Test failed:', error.message);
  }
})();
