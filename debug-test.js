const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false });
  const context = await browser.newContext();
  const page = await context.newPage();

  // Navigate to WordPress admin
  await page.goto('http://localhost:8080/wp-admin');
  
  // Login
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
  await page.click('#wp-submit');

  // Wait for dashboard
  await page.waitForSelector('.wrap');
  
  console.log('Logged in successfully');

  // Check if plugins are active
  await page.goto('http://localhost:8080/wp-admin/plugins.php');
  await page.waitForSelector('.plugins');
  
  console.log('Plugin page loaded');

  // Try to create a new post
  await page.goto('http://localhost:8080/wp-admin/post-new.php');
  await page.waitForSelector('.editor-canvas');
  
  console.log('Editor loaded');
  
  // Try to save the post
  await page.keyboard.press('Control+s');
  
  // Wait and check for any error messages
  await page.waitForTimeout(3000);
  
  const errorElement = await page.$('.notice-error, .components-notice__content');
  if (errorElement) {
    const errorText = await errorElement.textContent();
    console.log('Error found:', errorText);
  } else {
    console.log('No error messages found');
  }
  
  await browser.close();
})();
