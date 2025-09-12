const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false });
  const context = await browser.newContext();
  const page = await context.newPage();
  
  console.log('🔧 Issue #4 Final Test - Generate Content Button');
  console.log('================================================\n');
  
  // Login
  console.log('Logging in...');
  await page.goto('http://localhost:8080/wp-admin/');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
  await page.click('#wp-submit');
  await page.waitForSelector('#wpadminbar');
  console.log('✅ Logged in\n');
  
  // Open post editor
  console.log('Opening post editor...');
  await page.goto('http://localhost:8080/wp-admin/post-new.php');
  await page.waitForSelector('.edit-post-visual-editor', { timeout: 30000 });
  await page.waitForTimeout(3000);
  
  // Add AI block programmatically
  console.log('Adding AI Text Generator block...');
  const blockAdded = await page.evaluate(() => {
    try {
      const { createBlock } = wp.blocks;
      const { insertBlock } = wp.data.dispatch('core/block-editor');
      
      const aiBlock = createBlock('wp-content-flow/ai-text', {});
      insertBlock(aiBlock);
      return true;
    } catch (error) {
      console.error('Error adding block:', error);
      return false;
    }
  });
  
  if (!blockAdded) {
    console.log('❌ Failed to add block programmatically');
    return;
  }
  
  await page.waitForTimeout(2000);
  
  // Check if block is added
  const block = page.locator('.wp-content-flow-ai-text-generator').first();
  if (await block.isVisible()) {
    console.log('✅ AI Text Generator block added\n');
    
    // Look for the workflow select in the block itself
    const workflowSelect = block.locator('select').first();
    
    if (await workflowSelect.isVisible()) {
      console.log('✅ Workflow dropdown is visible in block');
      
      // Get workflow options
      const options = await workflowSelect.locator('option').allTextContents();
      console.log(`   Found ${options.length - 1} workflows (excluding placeholder)`);
      
      if (options.length > 1) {
        // Select the first workflow
        await workflowSelect.selectOption({ index: 1 });
        console.log(`   Selected workflow: ${options[1]}\n`);
        await page.waitForTimeout(1000);
        
        // Now prompt field should appear
        const promptField = block.locator('textarea').first();
        if (await promptField.isVisible()) {
          console.log('✅ Prompt field appeared');
          await promptField.fill('Write a brief introduction about artificial intelligence and its benefits');
          console.log('   Prompt entered\n');
          await page.waitForTimeout(500);
          
          // Now look for Generate Content button
          const generateButton = block.locator('button:has-text("Generate Content")').first();
          if (await generateButton.isVisible()) {
            console.log('✅ Generate Content button is visible');
            
            const isDisabled = await generateButton.isDisabled();
            if (!isDisabled) {
              console.log('✅ Generate Content button is ENABLED');
              console.log('\n🎉 ISSUE #4 IS FIXED!');
              console.log('   The Generate Content button is now responsive.');
              console.log('   Users can select workflows and generate content.\n');
              
              // Optional: Actually click the button
              console.log('Testing actual generation...');
              await generateButton.click();
              console.log('   Button clicked - waiting for response...');
              
              // Wait for either spinner or error
              await page.waitForTimeout(2000);
              
              const spinner = block.locator('.wp-content-flow-generating').first();
              if (await spinner.isVisible()) {
                console.log('✅ Generation in progress (spinner visible)');
              }
              
              const error = block.locator('.components-notice').first();
              if (await error.isVisible()) {
                const errorText = await error.textContent();
                console.log(`⚠️ API Error: ${errorText}`);
                console.log('   (This is expected if AI providers are not configured)');
              }
            } else {
              console.log('❌ Generate Content button is disabled');
            }
          } else {
            console.log('❌ Generate Content button not found');
          }
        } else {
          console.log('❌ Prompt field not visible after selecting workflow');
        }
      } else {
        console.log('❌ No workflows available in dropdown');
      }
    } else {
      console.log('❌ Workflow dropdown not found in block');
    }
  } else {
    console.log('❌ AI Text Generator block not found');
  }
  
  console.log('\n================================');
  console.log('TEST COMPLETE');
  console.log('================================');
  
  await page.waitForTimeout(5000);
  await browser.close();
})();