const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false });
  const context = await browser.newContext();
  const page = await context.newPage();
  
  console.log('🔧 Testing Button Click');
  console.log('========================\n');
  
  // Login
  await page.goto('http://localhost:8080/wp-admin/');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
  await page.click('#wp-submit');
  await page.waitForSelector('#wpadminbar');
  
  // Open post editor
  await page.goto('http://localhost:8080/wp-admin/post-new.php');
  await page.waitForSelector('.edit-post-visual-editor', { timeout: 30000 });
  await page.waitForTimeout(3000);
  
  // Add AI block
  await page.evaluate(() => {
    const { createBlock } = wp.blocks;
    const { insertBlock } = wp.data.dispatch('core/block-editor');
    const aiBlock = createBlock('wp-content-flow/ai-text', {});
    insertBlock(aiBlock);
  });
  
  await page.waitForTimeout(2000);
  
  // Find the block
  const block = page.locator('.wp-content-flow-ai-text-generator').first();
  
  // Select workflow
  const workflowSelect = block.locator('select').first();
  await workflowSelect.selectOption({ index: 1 });
  await page.waitForTimeout(1000);
  
  // Enter prompt
  const promptField = block.locator('textarea').first();
  await promptField.fill('Test prompt for AI generation');
  await page.waitForTimeout(500);
  
  // Check button state before clicking
  const button = block.locator('button:has-text("Generate Content")').first();
  const buttonExists = await button.count() > 0;
  const buttonVisible = buttonExists && await button.isVisible();
  const buttonEnabled = buttonVisible && !await button.isDisabled();
  
  console.log(`Button exists: ${buttonExists}`);
  console.log(`Button visible: ${buttonVisible}`);
  console.log(`Button enabled: ${buttonEnabled}`);
  
  if (buttonEnabled) {
    // Set up network monitoring
    const requests = [];
    page.on('request', request => {
      if (request.url().includes('wp-content-flow')) {
        requests.push({
          url: request.url(),
          method: request.method(),
          headers: request.headers()
        });
        console.log(`📡 Request: ${request.method()} ${request.url()}`);
      }
    });
    
    // Test the click directly
    console.log('\nClicking button...');
    await button.click();
    console.log('Button clicked!');
    
    // Wait a bit for any requests
    await page.waitForTimeout(3000);
    
    console.log(`\nTotal API requests made: ${requests.length}`);
    
    // Check if spinner appears
    const spinner = block.locator('.wp-content-flow-generating').first();
    const spinnerVisible = await spinner.isVisible();
    console.log(`Spinner visible: ${spinnerVisible}`);
    
    // Check for any error messages
    const notice = block.locator('.components-notice').first();
    if (await notice.isVisible()) {
      const message = await notice.textContent();
      console.log(`Notice message: ${message}`);
    }
    
    // Check if content was generated
    const generatedContent = block.locator('.wp-content-flow-generated-content').first();
    const contentVisible = await generatedContent.isVisible();
    console.log(`Generated content visible: ${contentVisible}`);
  } else {
    console.log('❌ Button not enabled - cannot test click');
  }
  
  await page.waitForTimeout(3000);
  await browser.close();
})();