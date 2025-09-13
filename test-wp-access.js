const { chromium } = require('playwright');

(async () => {
  try {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();
    
    // Enable console logging
    page.on('console', msg => console.log('BROWSER:', msg.text()));
    page.on('pageerror', error => console.log('PAGE ERROR:', error.message));
    
    console.log('Navigating to WordPress admin...');
    const response = await page.goto('http://localhost:8080/wp-admin/', { 
      waitUntil: 'domcontentloaded',
      timeout: 10000
    });
    
    console.log('Response status:', response.status());
    
    if (response.status() === 500) {
      console.log('500 Error detected!');
      const content = await page.content();
      console.log('Page content:', content.substring(0, 1000));
    }
    
    await browser.close();
  } catch (error) {
    console.error('Error:', error.message);
  }
})();
