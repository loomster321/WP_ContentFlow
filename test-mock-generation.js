const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false });
  const context = await browser.newContext();
  const page = await context.newPage();
  
  console.log('🔧 Testing AI Generation with Mock Mode');
  console.log('========================================\n');
  
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
  
  // Add AI block programmatically
  console.log('Adding AI Text Generator block...');
  await page.evaluate(() => {
    const { createBlock } = wp.blocks;
    const { insertBlock } = wp.data.dispatch('core/block-editor');
    const aiBlock = createBlock('wp-content-flow/ai-text', {});
    insertBlock(aiBlock);
  });
  
  await page.waitForTimeout(2000);
  
  // Find the block
  const block = page.locator('.wp-content-flow-ai-text-generator').first();
  if (await block.isVisible()) {
    console.log('✅ Block added successfully\n');
    
    // Select workflow
    const workflowSelect = block.locator('select').first();
    await workflowSelect.selectOption({ index: 1 });
    console.log('✅ Workflow selected\n');
    await page.waitForTimeout(1000);
    
    // Enter prompt
    const promptField = block.locator('textarea').first();
    await promptField.fill('Write a brief introduction about artificial intelligence and its benefits');
    console.log('✅ Prompt entered\n');
    await page.waitForTimeout(500);
    
    // Set up response listener
    const responsePromise = page.waitForResponse(
      response => response.url().includes('/wp-content-flow/v1/ai/generate'),
      { timeout: 10000 }
    ).catch(() => null);
    
    // Click Generate button
    const generateButton = block.locator('button:has-text("Generate Content")').first();
    console.log('Clicking Generate Content button...');
    await generateButton.click();
    
    // Wait for response
    const response = await responsePromise;
    
    if (response) {
      const status = response.status();
      console.log(`\n📡 API Response Status: ${status}`);
      
      if (status === 200) {
        const data = await response.json();
        console.log('✅ Content generated successfully!');
        console.log(`   Provider: ${data.provider_used || 'unknown'}`);
        console.log(`   Confidence: ${Math.round((data.confidence_score || 0) * 100)}%`);
        console.log(`   Is Mock: ${data.is_mock ? 'Yes (Demo Mode)' : 'No'}`);
        
        // Wait for content to appear
        await page.waitForTimeout(2000);
        
        // Check if content is displayed
        const generatedContent = block.locator('.wp-content-flow-generated-content').first();
        if (await generatedContent.isVisible()) {
          console.log('✅ Generated content is displayed in the block');
          
          // Get first few words of content
          const contentPreview = await generatedContent.evaluate(el => {
            const text = el.textContent || '';
            return text.substring(0, 100) + (text.length > 100 ? '...' : '');
          });
          console.log(`   Preview: "${contentPreview}"`);
        }
        
        console.log('\n🎉 SUCCESS! AI content generation is working!');
        console.log('   The plugin is using mock responses when no API keys are configured.');
        console.log('   Users can test the functionality without needing real API keys.');
      } else {
        const errorData = await response.text();
        console.log(`❌ API returned error: ${errorData.substring(0, 200)}`);
      }
    } else {
      console.log('❌ No API response received');
      
      // Check for errors in the UI
      const notice = block.locator('.components-notice').first();
      if (await notice.isVisible()) {
        const message = await notice.textContent();
        console.log(`UI Error: ${message}`);
      }
    }
  } else {
    console.log('❌ Block not found');
  }
  
  await page.waitForTimeout(5000);
  await browser.close();
})();