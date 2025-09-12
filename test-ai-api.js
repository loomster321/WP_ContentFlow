const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();

  console.log('🔐 Logging into WordPress...');
  await page.goto('http://localhost:8080/wp-admin/');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
  await page.click('#wp-submit');
  await page.waitForSelector('#wpadminbar', { timeout: 15000 });
  console.log('✅ Logged in successfully');

  // Test 1: Basic REST API test endpoint
  console.log('\n📡 Testing REST API...');
  const testResponse = await page.evaluate(async () => {
    const response = await fetch('/wp-json/wp-content-flow/v1/test');
    return await response.json();
  });
  console.log('✅ REST API test endpoint:', testResponse.message);

  // Get the nonce from the admin page
  console.log('\n🔑 Getting WordPress nonce...');
  const nonce = await page.evaluate(() => {
    // Try multiple methods to find the nonce
    // Method 1: Look for wpApiSettings
    if (window.wpApiSettings && window.wpApiSettings.nonce) {
      return window.wpApiSettings.nonce;
    }
    // Method 2: Look in inline scripts
    const scripts = Array.from(document.querySelectorAll('script'));
    for (const script of scripts) {
      const match = script.textContent.match(/"nonce"\s*:\s*"([^"]+)"/);
      if (match) return match[1];
    }
    // Method 3: Create a new nonce via AJAX
    if (window.ajaxurl) {
      return 'fallback';
    }
    return null;
  });
  
  if (nonce) {
    console.log('✅ Got nonce:', nonce.substring(0, 10) + '...');
  } else {
    console.log('⚠️ Could not get nonce, trying without it');
  }

  // Test 2: AI generation endpoint (will fail without real API key)
  console.log('\n🤖 Testing AI generation endpoint...');
  const aiResponse = await page.evaluate(async (nonce) => {
    try {
      const headers = {
        'Content-Type': 'application/json',
      };
      
      if (nonce && nonce !== 'fallback') {
        headers['X-WP-Nonce'] = nonce;
      }

      const response = await fetch('/wp-json/wp-content-flow/v1/ai/generate', {
        method: 'POST',
        credentials: 'same-origin',
        headers: headers,
        body: JSON.stringify({
          prompt: 'Write exactly 3 sentences about WordPress plugins',
          provider: 'openai',
          model: 'gpt-3.5-turbo'
        })
      });
      
      const data = await response.json();
      return { status: response.status, data };
    } catch (error) {
      return { error: error.message };
    }
  }, nonce);

  console.log('Response status:', aiResponse.status);
  
  if (aiResponse.status === 200) {
    console.log('✅ AI generation successful!');
    if (typeof aiResponse.data === 'string') {
      console.log('Response is a string (length=' + aiResponse.data.length + ')');
      if (aiResponse.data.includes('Mock')) {
        console.log('✅ Mock response detected');
      }
      console.log('First 200 chars:', aiResponse.data.substring(0, 200));
    } else {
      console.log('Response data:', JSON.stringify(aiResponse.data, null, 2));
      console.log('Generated content:', aiResponse.data.suggested_content || aiResponse.data.content);
    }
  } else if (aiResponse.status === 500 || aiResponse.status === 503) {
    console.log('⚠️ AI generation failed (expected without real API key)');
    console.log('Error:', aiResponse.data.message || aiResponse.data.code);
    
    // Check if it's because OpenAI is using mock
    if (aiResponse.data.message && aiResponse.data.message.includes('Mock')) {
      console.log('ℹ️ OpenAI provider is using mock implementation');
    }
  } else if (aiResponse.status === 403) {
    console.log('❌ Authentication issue:', aiResponse.data.message);
  } else {
    console.log('❌ Unexpected response:', aiResponse);
  }

  // Test 3: Check settings page
  console.log('\n⚙️ Checking settings page...');
  await page.goto('http://localhost:8080/wp-admin/admin.php?page=wp-content-flow-settings');
  await page.waitForLoadState('networkidle');
  
  const hasOpenAIField = await page.locator('input[name="wp_content_flow_settings[openai_api_key]"]').isVisible();
  if (hasOpenAIField) {
    console.log('✅ OpenAI API key field found in settings');
    const fieldValue = await page.locator('input[name="wp_content_flow_settings[openai_api_key]"]').inputValue();
    if (fieldValue) {
      console.log('✅ OpenAI API key is configured (masked)');
    } else {
      console.log('⚠️ OpenAI API key is not configured');
    }
  }

  await browser.close();
  console.log('\n✨ Test complete!');
})();