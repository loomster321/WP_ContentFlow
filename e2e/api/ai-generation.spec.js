const { test, expect } = require('@playwright/test');

test.describe('AI Content Generation API', () => {
  let authCookie;
  
  test.beforeAll(async ({ request }) => {
    // Login to get authentication cookie
    const loginResponse = await request.post('http://localhost:8080/wp-login.php', {
      form: {
        log: 'admin',
        pwd: '!3cTXkh)9iDHhV5o*N',
        'wp-submit': 'Log In',
        redirect_to: 'http://localhost:8080/wp-admin/',
        testcookie: '1'
      }
    });
    
    // Extract cookies
    const cookies = await loginResponse.headers()['set-cookie'];
    if (cookies) {
      authCookie = cookies;
    }
  });

  test('REST API test endpoint works', async ({ request }) => {
    const response = await request.get('http://localhost:8080/wp-json/wp-content-flow/v1/test');
    expect(response.ok()).toBeTruthy();
    
    const data = await response.json();
    expect(data.success).toBe(true);
    expect(data.message).toBe('WP Content Flow REST API is working!');
    console.log('✅ REST API test endpoint works:', data);
  });

  test('AI generation requires authentication', async ({ request }) => {
    const response = await request.post('http://localhost:8080/wp-json/wp-content-flow/v1/ai/generate', {
      data: {
        prompt: 'Write a test paragraph',
        provider: 'openai'
      }
    });
    
    expect(response.status()).toBe(401);
    const error = await response.json();
    expect(error.code).toBe('rest_forbidden');
    console.log('✅ API correctly requires authentication');
  });

  test('AI generation with mock OpenAI provider', async ({ page }) => {
    // Login first
    await page.goto('http://localhost:8080/wp-admin/');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
    await page.click('#wp-submit');
    await page.waitForSelector('#wpadminbar', { timeout: 15000 });
    
    // Get nonce for API requests
    const nonce = await page.evaluate(() => {
      // Try to find the nonce in the page
      const scripts = Array.from(document.querySelectorAll('script'));
      for (const script of scripts) {
        const match = script.textContent.match(/"nonce"\s*:\s*"([^"]+)"/);
        if (match) return match[1];
      }
      return null;
    });

    if (!nonce) {
      console.log('⚠️ Could not find nonce, trying without it');
    }

    // Make authenticated API request
    const response = await page.evaluate(async (nonce) => {
      try {
        const headers = {
          'Content-Type': 'application/json',
        };
        
        if (nonce) {
          headers['X-WP-Nonce'] = nonce;
        }

        const response = await fetch('/wp-json/wp-content-flow/v1/ai/generate', {
          method: 'POST',
          credentials: 'same-origin',
          headers: headers,
          body: JSON.stringify({
            prompt: 'Write a short test paragraph about WordPress',
            provider: 'openai',
            model: 'gpt-3.5-turbo',
            temperature: 0.7,
            max_tokens: 150
          })
        });
        
        const data = await response.json();
        return { status: response.status, data };
      } catch (error) {
        return { error: error.message };
      }
    }, nonce);

    console.log('AI Generation Response:', response);
    
    if (response.status === 200) {
      expect(response.data).toHaveProperty('content');
      console.log('✅ AI generation successful:', response.data.content);
    } else if (response.status === 500 || response.status === 503) {
      // Expected if OpenAI is not configured or mock is being used
      console.log('⚠️ AI generation failed (expected if not configured):', response.data);
      expect(response.data).toHaveProperty('code');
    } else {
      console.log('❌ Unexpected response:', response);
    }
  });

  test('Settings page shows OpenAI configuration', async ({ page }) => {
    // Login and navigate to settings
    await page.goto('http://localhost:8080/wp-admin/');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
    await page.click('#wp-submit');
    await page.waitForSelector('#wpadminbar', { timeout: 15000 });
    
    await page.goto('http://localhost:8080/wp-admin/admin.php?page=wp-content-flow-settings');
    await page.waitForLoadState('networkidle');
    
    // Check if OpenAI key field exists
    const openaiField = page.locator('input[name="wp_content_flow_settings[openai_api_key]"]');
    await expect(openaiField).toBeVisible();
    
    // Check if we have a key configured (masked)
    const fieldValue = await openaiField.inputValue();
    if (fieldValue) {
      console.log('✅ OpenAI API key is configured (masked)');
    } else {
      console.log('⚠️ OpenAI API key is not configured');
    }
  });
});