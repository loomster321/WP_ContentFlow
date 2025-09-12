const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();
  
  // Capture console messages
  page.on('console', msg => {
    if (msg.type() === 'error') {
      console.log('❌ Console Error:', msg.text());
    }
  });
  
  // Capture page errors
  page.on('pageerror', error => {
    console.log('❌ Page Error:', error.message);
  });
  
  console.log('🔐 Logging into WordPress...');
  await page.goto('http://localhost:8080/wp-admin/');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
  await page.click('#wp-submit');
  await page.waitForSelector('#wpadminbar', { timeout: 15000 });
  
  console.log('📝 Opening post editor...');
  await page.goto('http://localhost:8080/wp-admin/post-new.php');
  
  // Wait for editor and check for our script
  await page.waitForSelector('.edit-post-visual-editor', { timeout: 30000 });
  
  // Check if our scripts are loaded
  const scriptsLoaded = await page.evaluate(() => {
    const scripts = Array.from(document.querySelectorAll('script[src*="wp-content-flow"]'));
    return scripts.map(s => {
      const src = s.src;
      const parts = src.split('/');
      return parts[parts.length - 1];
    });
  });
  
  console.log('📦 WP Content Flow scripts loaded:', scriptsLoaded);
  
  // Check if block is registered
  const blockInfo = await page.evaluate(() => {
    if (typeof wp !== 'undefined' && wp.blocks) {
      const blocks = wp.blocks.getBlockTypes();
      const aiBlocks = blocks.filter(b => b.name.includes('ai') || b.name.includes('content-flow'));
      return {
        totalBlocks: blocks.length,
        aiBlocks: aiBlocks.map(b => ({ name: b.name, title: b.title })),
        hasOurBlock: blocks.some(b => b.name === 'wp-content-flow/ai-text')
      };
    }
    return { error: 'wp.blocks not available' };
  });
  
  console.log('🔍 Block registration info:', blockInfo);
  
  // Check for wpContentFlow global
  const globalInfo = await page.evaluate(() => {
    return {
      hasWpContentFlow: typeof wpContentFlow !== 'undefined',
      wpContentFlow: typeof wpContentFlow !== 'undefined' ? wpContentFlow : null
    };
  });
  
  console.log('🌐 Global wpContentFlow:', globalInfo);
  
  await browser.close();
  console.log('✨ Check complete!');
})();