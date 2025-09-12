const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false });
  const context = await browser.newContext();
  const page = await context.newPage();
  
  console.log('🔧 FINAL TEST - AI Content Generation');
  console.log('=====================================\n');
  
  // Login
  await page.goto('http://localhost:8080/wp-admin/');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
  await page.click('#wp-submit');
  await page.waitForSelector('#wpadminbar');
  console.log('✅ Logged in\n');
  
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
  console.log('✅ AI Text Generator block added\n');
  
  // Configure block
  const block = page.locator('.wp-content-flow-ai-text-generator').first();
  
  // Select workflow
  const workflowSelect = block.locator('select').first();
  await workflowSelect.selectOption({ index: 1 });
  console.log('✅ Workflow selected\n');
  
  // Enter prompt
  const promptField = block.locator('textarea').first();
  await promptField.fill('Write a brief introduction about artificial intelligence and its benefits');
  console.log('✅ Prompt entered\n');
  
  // Monitor response
  page.on('response', async response => {
    if (response.url().includes('/wp-content-flow/v1/ai/generate')) {
      console.log(`\n📡 API Response received:`);
      console.log(`   Status: ${response.status()}`);
      
      if (response.status() === 200) {
        try {
          const data = await response.json();
          console.log(`   Success: Content generated`);
          console.log(`   Provider: ${data.provider_used || 'unknown'}`);
          console.log(`   Is Mock: ${data.is_mock ? 'Yes' : 'No'}`);
        } catch (e) {
          console.log(`   Could not parse response`);
        }
      }
    }
  });
  
  // Click Generate button
  const button = block.locator('button:has-text("Generate Content")').first();
  console.log('Clicking Generate Content button...');
  await button.click();
  
  // Wait for spinner or content
  await page.waitForTimeout(5000);
  
  // Check results
  console.log('\n📊 Final Status:');
  
  const spinner = block.locator('.wp-content-flow-generating').first();
  const spinnerVisible = await spinner.isVisible();
  console.log(`   Loading spinner: ${spinnerVisible ? 'Still visible (generating)' : 'Hidden'}`);
  
  const generatedContent = block.locator('.wp-content-flow-generated-content').first();
  const contentVisible = await generatedContent.isVisible();
  console.log(`   Generated content: ${contentVisible ? '✅ VISIBLE' : '❌ Not visible'}`);
  
  if (contentVisible) {
    const contentText = await generatedContent.locator('.content-display').first().textContent();
    const preview = contentText.substring(0, 150) + (contentText.length > 150 ? '...' : '');
    console.log(`   Content preview: "${preview}"`);
    
    console.log('\n🎉 SUCCESS! AI Content Generation is working!');
    console.log('   The plugin successfully generates content using mock responses.');
    console.log('   Users can test the full workflow without API keys.');
  } else {
    // Check for errors
    const errorNotice = block.locator('.components-notice').first();
    if (await errorNotice.isVisible()) {
      const errorText = await errorNotice.textContent();
      console.log(`   Error message: ${errorText}`);
    }
  }
  
  await page.waitForTimeout(5000);
  await browser.close();
})();