const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false });
  const context = await browser.newContext();
  const page = await context.newPage();
  
  // Capture console messages
  const consoleMessages = [];
  page.on('console', msg => {
    const text = msg.text();
    consoleMessages.push({ type: msg.type(), text });
    if (msg.type() === 'error') {
      console.log('🔴 JavaScript Error:', text);
    }
  });
  
  console.log('🔧 Checking for JavaScript Errors');
  console.log('===================================\n');
  
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
  
  // Try to interact with the block
  const block = page.locator('.wp-content-flow-ai-text-generator').first();
  if (await block.isVisible()) {
    // Select workflow
    const workflowSelect = block.locator('select').first();
    await workflowSelect.selectOption({ index: 1 });
    await page.waitForTimeout(1000);
    
    // Enter prompt
    const promptField = block.locator('textarea').first();
    await promptField.fill('Test prompt');
    await page.waitForTimeout(500);
    
    // Try to click Generate button
    const generateButton = block.locator('button:has-text("Generate Content")').first();
    if (await generateButton.isVisible()) {
      console.log('Clicking Generate Content button...');
      await generateButton.click();
      await page.waitForTimeout(3000);
    }
  }
  
  // Check for any errors
  console.log('\n📊 Console Summary:');
  const errors = consoleMessages.filter(m => m.type === 'error');
  const warnings = consoleMessages.filter(m => m.type === 'warning');
  
  console.log(`   Errors: ${errors.length}`);
  console.log(`   Warnings: ${warnings.length}`);
  
  if (errors.length > 0) {
    console.log('\n❌ JavaScript Errors Found:');
    errors.forEach((err, i) => {
      console.log(`   ${i + 1}. ${err.text}`);
    });
  } else {
    console.log('\n✅ No JavaScript errors detected');
  }
  
  // Check if wpContentFlow is available
  const globalCheck = await page.evaluate(() => {
    return {
      hasWp: typeof wp !== 'undefined',
      hasWpData: typeof wp !== 'undefined' && typeof wp.data !== 'undefined',
      hasWpContentFlow: typeof window.wpContentFlow !== 'undefined',
      hasApiFetch: typeof wp !== 'undefined' && typeof wp.apiFetch !== 'undefined'
    };
  });
  
  console.log('\n🔍 Global Objects Check:');
  console.log(`   wp: ${globalCheck.hasWp ? '✅' : '❌'}`);
  console.log(`   wp.data: ${globalCheck.hasWpData ? '✅' : '❌'}`);
  console.log(`   wp.apiFetch: ${globalCheck.hasApiFetch ? '✅' : '❌'}`);
  console.log(`   wpContentFlow: ${globalCheck.hasWpContentFlow ? '✅' : '❌'}`);
  
  await page.waitForTimeout(3000);
  await browser.close();
})();