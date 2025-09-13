const { chromium } = require('playwright');

(async () => {
  try {
    console.log('Testing post save with minimal plugin...');
    const browser = await chromium.launch({ headless: false, slowMo: 1000 });
    const page = await browser.newPage();
    
    // Login
    await page.goto('http://localhost:8080/wp-admin/');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
    await page.click('#wp-submit');
    await page.waitForSelector('.wrap');
    
    console.log('Navigating to post editor...');
    
    // Go to new post
    await page.goto('http://localhost:8080/wp-admin/post-new.php');
    
    // Wait a bit longer for the editor
    await page.waitForTimeout(5000);
    
    // Check if classic editor or block editor
    const hasClassicEditor = await page.$('#title');
    const hasBlockEditor = await page.$('.wp-block-post-title');
    
    if (hasClassicEditor) {
      console.log('Using classic editor');
      await page.fill('#title', 'Test Post Title Classic');
      await page.fill('#content', 'Test content for classic editor');
      
      // Try to save
      await page.click('#publish');
      await page.waitForTimeout(3000);
      
    } else if (hasBlockEditor) {
      console.log('Using block editor');
      await page.fill('.wp-block-post-title', 'Test Post Block Editor');
      
      // Try to save
      await page.keyboard.press('Control+s');
      await page.waitForTimeout(3000);
      
    } else {
      console.log('Editor not detected, checking page content...');
      const pageText = await page.textContent('body');
      console.log('Page contains:', pageText.substring(0, 200));
    }
    
    // Check for any error messages
    const errorElements = await page.$$('.notice-error, .components-notice.is-error');
    if (errorElements.length > 0) {
      for (const errorEl of errorElements) {
        const errorText = await errorEl.textContent();
        console.log('ERROR:', errorText.trim());
      }
    } else {
      console.log('No error messages found');
    }
    
    // Check for success messages
    const successElements = await page.$$('.notice-success, .components-notice.is-success');
    if (successElements.length > 0) {
      for (const successEl of successElements) {
        const successText = await successEl.textContent();
        console.log('SUCCESS:', successText.trim());
      }
    }
    
    await page.waitForTimeout(5000);
    await browser.close();
    
  } catch (error) {
    console.error('Test failed:', error.message);
  }
})();
