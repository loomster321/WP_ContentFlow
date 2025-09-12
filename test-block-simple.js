const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false });
  const context = await browser.newContext();
  const page = await context.newPage();
  
  // Enable console logging
  page.on('console', msg => {
    console.log(`[${msg.type()}] ${msg.text()}`);
  });
  
  console.log('🔧 Simple Block Test');
  console.log('====================\n');
  
  // Login
  console.log('Logging in...');
  await page.goto('http://localhost:8080/wp-admin/');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
  await page.click('#wp-submit');
  await page.waitForSelector('#wpadminbar', { timeout: 15000 });
  
  // Open post editor
  console.log('Opening editor...');
  await page.goto('http://localhost:8080/wp-admin/post-new.php');
  await page.waitForSelector('.edit-post-visual-editor', { timeout: 30000 });
  await page.waitForTimeout(3000);
  
  // Add block
  console.log('Adding AI block...');
  await page.keyboard.press('/');
  await page.waitForTimeout(500);
  await page.keyboard.type('AI Text');
  await page.waitForTimeout(1000);
  await page.keyboard.press('Enter');
  await page.waitForTimeout(2000);
  
  // Check if block is added
  const block = page.locator('.wp-content-flow-ai-text-generator').first();
  if (await block.isVisible()) {
    console.log('✅ Block added successfully');
    
    // Click on the block to select it
    await block.click();
    await page.waitForTimeout(1000);
    
    // Check if sidebar opens
    const sidebar = page.locator('.interface-complementary-area').first();
    if (await sidebar.isVisible()) {
      console.log('✅ Sidebar is visible');
      
      // Look for any select elements in the sidebar
      const selects = await page.locator('.components-panel select').count();
      console.log(`Found ${selects} select elements in sidebar`);
      
      // Look for workflow text
      const workflowLabel = page.locator('.components-panel :text("Workflow")').first();
      if (await workflowLabel.isVisible()) {
        console.log('✅ Workflow label found in sidebar');
      } else {
        console.log('❌ No workflow label in sidebar');
      }
      
      // Check for any panel bodies
      const panels = await page.locator('.components-panel__body').count();
      console.log(`Found ${panels} panel bodies in sidebar`);
    } else {
      console.log('❌ Sidebar not visible');
    }
    
    // Check block content
    const placeholder = await block.locator('.placeholder-text').first();
    if (await placeholder.isVisible()) {
      const text = await placeholder.textContent();
      console.log(`Placeholder text: "${text}"`);
    }
    
    // Check if wpContentFlow is available
    const hasWpContentFlow = await page.evaluate(() => {
      return typeof window.wpContentFlow !== 'undefined';
    });
    console.log(`wpContentFlow available: ${hasWpContentFlow}`);
    
    if (hasWpContentFlow) {
      const workflows = await page.evaluate(() => {
        return window.wpContentFlow.defaultWorkflows || [];
      });
      console.log(`Default workflows: ${workflows.length}`);
      if (workflows.length > 0) {
        console.log('First workflow:', workflows[0].name);
      }
    }
  } else {
    console.log('❌ Block not found');
  }
  
  await page.waitForTimeout(5000);
  await browser.close();
})();