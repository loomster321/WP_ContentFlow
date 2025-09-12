const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false });
  const context = await browser.newContext();
  const page = await context.newPage();
  
  console.log('🔧 Testing Block Registration');
  console.log('=============================\n');
  
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
  
  // Check if our block is registered
  const blockRegistered = await page.evaluate(() => {
    if (typeof wp !== 'undefined' && wp.blocks) {
      const block = wp.blocks.getBlockType('wp-content-flow/ai-text');
      return block !== undefined;
    }
    return false;
  });
  
  console.log(`Block 'wp-content-flow/ai-text' registered: ${blockRegistered ? '✅ YES' : '❌ NO'}`);
  
  // List all registered blocks with 'ai' in the name
  const aiBlocks = await page.evaluate(() => {
    if (typeof wp !== 'undefined' && wp.blocks) {
      const allBlocks = wp.blocks.getBlockTypes();
      return allBlocks
        .filter(b => b.name.toLowerCase().includes('ai') || b.title.toLowerCase().includes('ai'))
        .map(b => ({ name: b.name, title: b.title }));
    }
    return [];
  });
  
  console.log(`\nAI-related blocks found: ${aiBlocks.length}`);
  aiBlocks.forEach(b => {
    console.log(`  - ${b.name}: "${b.title}"`);
  });
  
  // Check if wpContentFlow is available
  const hasWpContentFlow = await page.evaluate(() => {
    return typeof window.wpContentFlow !== 'undefined';
  });
  
  console.log(`\nwpContentFlow object available: ${hasWpContentFlow ? '✅ YES' : '❌ NO'}`);
  
  if (hasWpContentFlow) {
    const contentFlowData = await page.evaluate(() => {
      return {
        hasWorkflows: window.wpContentFlow.defaultWorkflows ? window.wpContentFlow.defaultWorkflows.length : 0,
        hasNonce: !!window.wpContentFlow.nonce,
        hasApiUrl: !!window.wpContentFlow.apiUrl
      };
    });
    
    console.log(`  - Default workflows: ${contentFlowData.hasWorkflows}`);
    console.log(`  - Nonce present: ${contentFlowData.hasNonce ? '✅' : '❌'}`);
    console.log(`  - API URL present: ${contentFlowData.hasApiUrl ? '✅' : '❌'}`);
  }
  
  // Try to manually add the block
  if (blockRegistered) {
    console.log('\nTrying to add block programmatically...');
    
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
    
    console.log(`Block added programmatically: ${blockAdded ? '✅ SUCCESS' : '❌ FAILED'}`);
    
    await page.waitForTimeout(2000);
    
    // Check if block is visible
    const blockVisible = await page.locator('.wp-content-flow-ai-text-generator').first().isVisible();
    console.log(`Block visible in editor: ${blockVisible ? '✅ YES' : '❌ NO'}`);
    
    if (blockVisible) {
      // Check for workflow select
      const selectVisible = await page.locator('.wp-content-flow-ai-text-generator select').first().isVisible();
      console.log(`Workflow select visible: ${selectVisible ? '✅ YES' : '❌ NO'}`);
    }
  }
  
  await page.waitForTimeout(5000);
  await browser.close();
})();