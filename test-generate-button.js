const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false });
  const context = await browser.newContext();
  const page = await context.newPage();
  
  // Enable console logging
  page.on('console', msg => {
    if (msg.type() === 'error') {
      console.log('🔴 Console Error:', msg.text());
    }
  });
  
  console.log('🔧 Testing Generate Content Button (Issue #4)');
  console.log('=============================================\n');
  
  // Login to WordPress
  console.log('🔐 Logging into WordPress...');
  await page.goto('http://localhost:8080/wp-admin/');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
  await page.click('#wp-submit');
  await page.waitForSelector('#wpadminbar', { timeout: 15000 });
  console.log('✅ Logged in successfully\n');
  
  // Navigate to post editor
  console.log('📝 Opening post editor...');
  await page.goto('http://localhost:8080/wp-admin/post-new.php');
  await page.waitForSelector('.edit-post-visual-editor', { timeout: 30000 });
  await page.waitForTimeout(3000);
  
  // Add AI Text Generator block
  console.log('🎨 Adding AI Text Generator block...');
  const inserterButton = page.locator('button[aria-label="Toggle block inserter"]').first();
  if (await inserterButton.isVisible()) {
    await inserterButton.click();
    await page.waitForTimeout(1000);
    
    // Search for AI block
    await page.fill('input[placeholder="Search"]', 'AI Text');
    await page.waitForTimeout(500);
    
    // Click on the AI Text Generator block
    const aiBlock = page.locator('button:has-text("AI Text Generator")').first();
    if (await aiBlock.isVisible()) {
      await aiBlock.click();
      console.log('✅ AI Text Generator block added\n');
    }
  }
  
  await page.waitForTimeout(2000);
  
  // Test 1: Check if block is rendered
  console.log('📋 Test 1: Checking if block is rendered...');
  const blockContent = page.locator('.wp-content-flow-ai-text-generator').first();
  if (await blockContent.isVisible()) {
    console.log('✅ Block is rendered correctly');
  } else {
    console.log('❌ Block not visible');
  }
  
  // Click on the block to select it
  await blockContent.click();
  await page.waitForTimeout(1000);
  
  // Test 2: Check workflow dropdown in sidebar
  console.log('\n📋 Test 2: Checking workflow dropdown...');
  const workflowSelect = page.locator('select').filter({ hasText: /Select a workflow/ }).first();
  
  if (await workflowSelect.isVisible()) {
    console.log('✅ Workflow dropdown found');
    
    // Get the options
    const options = await workflowSelect.locator('option').allTextContents();
    console.log(`   - Available workflows: ${options.length - 1} (excluding placeholder)`);
    
    if (options.length > 1) {
      console.log('✅ Workflows are loaded!');
      for (let i = 1; i < options.length && i < 4; i++) {
        console.log(`   - ${options[i]}`);
      }
      
      // Select first workflow
      await workflowSelect.selectOption({ index: 1 });
      console.log('✅ Selected first workflow');
      await page.waitForTimeout(1000);
    } else {
      console.log('❌ No workflows in dropdown');
    }
  } else {
    console.log('❌ Workflow dropdown not found');
  }
  
  // Test 3: Check if prompt field appears after selecting workflow
  console.log('\n📋 Test 3: Checking prompt field...');
  const promptField = page.locator('textarea[placeholder*="prompt"]').first();
  
  if (await promptField.isVisible()) {
    console.log('✅ Prompt field is visible');
    await promptField.fill('Write a short introduction about artificial intelligence');
    console.log('✅ Prompt entered');
    await page.waitForTimeout(500);
  } else {
    console.log('❌ Prompt field not visible');
  }
  
  // Test 4: Check Generate Content button
  console.log('\n📋 Test 4: Testing Generate Content button...');
  
  // First check in the main block area
  let generateButton = page.locator('button:has-text("Generate Content")').first();
  
  if (await generateButton.isVisible()) {
    console.log('✅ Generate Content button is visible');
    
    // Check if button is enabled
    const isDisabled = await generateButton.isDisabled();
    if (!isDisabled) {
      console.log('✅ Generate Content button is enabled');
      
      // Try clicking the button
      console.log('🖱️ Clicking Generate Content button...');
      
      // Set up response listener
      const responsePromise = page.waitForResponse(
        response => response.url().includes('/wp-content-flow/v1/ai/generate'),
        { timeout: 5000 }
      ).catch(() => null);
      
      await generateButton.click();
      console.log('✅ Button clicked');
      
      // Wait for response or timeout
      const response = await responsePromise;
      
      if (response) {
        console.log('✅ API request was made!');
        const status = response.status();
        console.log(`   - Response status: ${status}`);
        
        if (status === 200) {
          console.log('✅ Content generation successful!');
        } else {
          const body = await response.text();
          console.log(`   - Response: ${body.substring(0, 200)}`);
        }
      } else {
        console.log('⚠️ No API request detected after button click');
        
        // Check for validation errors
        const notices = await page.locator('.components-notice').allTextContents();
        if (notices.length > 0) {
          console.log('📌 Validation messages found:');
          notices.forEach(notice => console.log(`   - ${notice}`));
        }
        
        // Check if there's a loading spinner
        const spinner = page.locator('.wp-content-flow-generating').first();
        if (await spinner.isVisible()) {
          console.log('✅ Loading spinner appeared - generation in progress');
        }
      }
    } else {
      console.log('❌ Generate Content button is disabled');
      
      // Check for validation errors
      const validationNotice = page.locator('.components-notice__content').first();
      if (await validationNotice.isVisible()) {
        const message = await validationNotice.textContent();
        console.log(`   - Validation message: ${message}`);
      }
    }
  } else {
    console.log('❌ Generate Content button not found');
    
    // Check if placeholder text is shown instead
    const placeholder = page.locator('.placeholder-text').first();
    if (await placeholder.isVisible()) {
      const text = await placeholder.textContent();
      console.log(`   - Placeholder shown: ${text}`);
    }
  }
  
  // Test 5: Check browser console for JavaScript errors
  console.log('\n📋 Test 5: Checking for JavaScript errors...');
  await page.evaluate(() => {
    console.log('Test console log from Playwright');
  });
  
  console.log('\n================================');
  console.log('📊 ISSUE #4 DIAGNOSIS');
  console.log('================================');
  
  // Final diagnosis
  const workflowSelected = await workflowSelect.evaluate(el => el.value) !== '0';
  const promptEntered = await promptField.evaluate(el => el.value.length > 0);
  const buttonVisible = await generateButton.isVisible();
  const buttonEnabled = buttonVisible && !(await generateButton.isDisabled());
  
  if (workflowSelected && promptEntered && buttonVisible && buttonEnabled) {
    console.log('✅ All conditions met - button should work');
    console.log('⚠️ If button doesn\'t respond, check:');
    console.log('   1. JavaScript console for errors');
    console.log('   2. Network tab for failed API calls');
    console.log('   3. WordPress REST API authentication');
  } else {
    console.log('❌ Issues found:');
    if (!workflowSelected) console.log('   - No workflow selected');
    if (!promptEntered) console.log('   - No prompt entered');
    if (!buttonVisible) console.log('   - Button not visible');
    if (!buttonEnabled) console.log('   - Button is disabled');
  }
  
  await page.waitForTimeout(5000);
  await browser.close();
})();