const { test, expect } = require('@playwright/test');

test.describe('REST API Integration Tests', () => {
  let authToken;
  
  test.beforeAll(async ({ browser }) => {
    // Get authentication token
    const context = await browser.newContext();
    const page = await context.newPage();
    
    await page.goto('/wp-admin');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'admin');
    await page.click('#wp-submit');
    
    // Extract nonce from page
    authToken = await page.evaluate(() => {
      return document.querySelector('meta[name="wp-content-flow-nonce"]')?.getAttribute('content') || 
             window.wpContentFlowNonce || 
             'test-nonce';
    });
    
    await context.close();
  });

  test('should authenticate API requests', async ({ request }) => {
    // Test authenticated request
    const response = await request.get('/wp-json/wp-content-flow/v1/ai/providers', {
      headers: {
        'X-WP-Nonce': authToken
      }
    });
    
    expect(response.status()).toBe(200);
    const data = await response.json();
    expect(data.success).toBe(true);
    expect(Array.isArray(data.data)).toBe(true);
  });

  test('should reject unauthenticated API requests', async ({ request }) => {
    const response = await request.post('/wp-json/wp-content-flow/v1/ai/generate', {
      data: {
        prompt: 'Test prompt',
        provider: 'openai'
      }
    });
    
    expect(response.status()).toBe(401);
    const data = await response.json();
    expect(data.success).toBe(false);
    expect(data.data.message).toContain('authentication');
  });

  test('should generate AI content via API', async ({ request }) => {
    const response = await request.post('/wp-json/wp-content-flow/v1/ai/generate', {
      headers: {
        'X-WP-Nonce': authToken,
        'Content-Type': 'application/json'
      },
      data: {
        prompt: 'Write a short paragraph about WordPress development',
        provider: 'openai',
        max_tokens: 100,
        temperature: 0.7
      }
    });
    
    expect(response.status()).toBe(200);
    const data = await response.json();
    expect(data.success).toBe(true);
    expect(data.data.content).toBeTruthy();
    expect(data.data.usage).toBeDefined();
    expect(data.data.provider).toBe('openai');
    expect(typeof data.data.usage.total_tokens).toBe('number');
  });

  test('should improve existing content via API', async ({ request }) => {
    const response = await request.post('/wp-json/wp-content-flow/v1/ai/improve', {
      headers: {
        'X-WP-Nonce': authToken,
        'Content-Type': 'application/json'
      },
      data: {
        content: 'WordPress is good for websites.',
        improvement_type: 'clarity',
        provider: 'anthropic'
      }
    });
    
    expect(response.status()).toBe(200);
    const data = await response.json();
    expect(data.success).toBe(true);
    expect(data.data.improved_content).toBeTruthy();
    expect(data.data.improvements).toBeDefined();
    expect(data.data.provider).toBe('anthropic');
    expect(data.data.improved_content.length).toBeGreaterThan('WordPress is good for websites.'.length);
  });

  test('should validate API input parameters', async ({ request }) => {
    // Test missing prompt
    let response = await request.post('/wp-json/wp-content-flow/v1/ai/generate', {
      headers: {
        'X-WP-Nonce': authToken,
        'Content-Type': 'application/json'
      },
      data: {
        provider: 'openai'
      }
    });
    
    expect(response.status()).toBe(400);
    let data = await response.json();
    expect(data.success).toBe(false);
    expect(data.data.message).toContain('prompt');
    
    // Test invalid provider
    response = await request.post('/wp-json/wp-content-flow/v1/ai/generate', {
      headers: {
        'X-WP-Nonce': authToken,
        'Content-Type': 'application/json'
      },
      data: {
        prompt: 'Test prompt',
        provider: 'invalid_provider'
      }
    });
    
    expect(response.status()).toBe(400);
    data = await response.json();
    expect(data.success).toBe(false);
    expect(data.data.message).toContain('provider');
    
    // Test invalid temperature
    response = await request.post('/wp-json/wp-content-flow/v1/ai/generate', {
      headers: {
        'X-WP-Nonce': authToken,
        'Content-Type': 'application/json'
      },
      data: {
        prompt: 'Test prompt',
        provider: 'openai',
        temperature: 2.5  // Should be 0-2
      }
    });
    
    expect(response.status()).toBe(400);
    data = await response.json();
    expect(data.success).toBe(false);
    expect(data.data.message).toContain('temperature');
  });

  test('should manage workflows via API', async ({ request }) => {
    // Create workflow
    let response = await request.post('/wp-json/wp-content-flow/v1/workflows', {
      headers: {
        'X-WP-Nonce': authToken,
        'Content-Type': 'application/json'
      },
      data: {
        name: 'API Test Workflow',
        description: 'Workflow created via API',
        steps: [
          {
            type: 'generate',
            prompt: 'Write about {{topic}}',
            provider: 'openai'
          }
        ]
      }
    });
    
    expect(response.status()).toBe(201);
    let data = await response.json();
    expect(data.success).toBe(true);
    expect(data.data.id).toBeDefined();
    expect(data.data.name).toBe('API Test Workflow');
    
    const workflowId = data.data.id;
    
    // Get workflow
    response = await request.get(`/wp-json/wp-content-flow/v1/workflows/${workflowId}`, {
      headers: {
        'X-WP-Nonce': authToken
      }
    });
    
    expect(response.status()).toBe(200);
    data = await response.json();
    expect(data.success).toBe(true);
    expect(data.data.name).toBe('API Test Workflow');
    
    // Update workflow
    response = await request.put(`/wp-json/wp-content-flow/v1/workflows/${workflowId}`, {
      headers: {
        'X-WP-Nonce': authToken,
        'Content-Type': 'application/json'
      },
      data: {
        name: 'Updated API Test Workflow',
        description: 'Updated description'
      }
    });
    
    expect(response.status()).toBe(200);
    data = await response.json();
    expect(data.success).toBe(true);
    expect(data.data.name).toBe('Updated API Test Workflow');
    
    // Delete workflow
    response = await request.delete(`/wp-json/wp-content-flow/v1/workflows/${workflowId}`, {
      headers: {
        'X-WP-Nonce': authToken
      }
    });
    
    expect(response.status()).toBe(200);
    data = await response.json();
    expect(data.success).toBe(true);
    
    // Verify workflow is deleted
    response = await request.get(`/wp-json/wp-content-flow/v1/workflows/${workflowId}`, {
      headers: {
        'X-WP-Nonce': authToken
      }
    });
    
    expect(response.status()).toBe(404);
  });

  test('should handle suggestions API', async ({ request }) => {
    // Create suggestion
    const response = await request.post('/wp-json/wp-content-flow/v1/suggestions', {
      headers: {
        'X-WP-Nonce': authToken,
        'Content-Type': 'application/json'
      },
      data: {
        content: 'Original content to improve',
        suggestion_type: 'grammar',
        post_id: 1,
        position: 0
      }
    });
    
    expect(response.status()).toBe(201);
    const data = await response.json();
    expect(data.success).toBe(true);
    expect(data.data.id).toBeDefined();
    expect(data.data.status).toBe('pending');
    
    const suggestionId = data.data.id;
    
    // Accept suggestion
    const acceptResponse = await request.post(`/wp-json/wp-content-flow/v1/suggestions/${suggestionId}/accept`, {
      headers: {
        'X-WP-Nonce': authToken
      }
    });
    
    expect(acceptResponse.status()).toBe(200);
    const acceptData = await acceptResponse.json();
    expect(acceptData.success).toBe(true);
    expect(acceptData.data.status).toBe('accepted');
  });

  test('should provide API usage statistics', async ({ request }) => {
    const response = await request.get('/wp-json/wp-content-flow/v1/usage/stats', {
      headers: {
        'X-WP-Nonce': authToken
      }
    });
    
    expect(response.status()).toBe(200);
    const data = await response.json();
    expect(data.success).toBe(true);
    expect(data.data.total_requests).toBeDefined();
    expect(data.data.tokens_used).toBeDefined();
    expect(data.data.requests_today).toBeDefined();
    expect(typeof data.data.total_requests).toBe('number');
    expect(typeof data.data.tokens_used).toBe('number');
  });

  test('should handle rate limiting', async ({ request }) => {
    // Mock rate limit exceeded scenario
    const responses = [];
    
    // Make multiple rapid requests
    for (let i = 0; i < 5; i++) {
      const promise = request.post('/wp-json/wp-content-flow/v1/ai/generate', {
        headers: {
          'X-WP-Nonce': authToken,
          'Content-Type': 'application/json'
        },
        data: {
          prompt: `Rate limit test ${i}`,
          provider: 'openai'
        }
      });
      responses.push(promise);
    }
    
    const results = await Promise.all(responses);
    
    // At least one should succeed, others might be rate limited
    const successCount = results.filter(r => r.status() === 200).length;
    const rateLimitedCount = results.filter(r => r.status() === 429).length;
    
    expect(successCount).toBeGreaterThan(0);
    
    if (rateLimitedCount > 0) {
      const rateLimitedResponse = results.find(r => r.status() === 429);
      const data = await rateLimitedResponse.json();
      expect(data.success).toBe(false);
      expect(data.data.message).toContain('rate limit');
    }
  });

  test('should handle concurrent API requests', async ({ request }) => {
    const concurrentRequests = 3;
    const promises = [];
    
    for (let i = 0; i < concurrentRequests; i++) {
      const promise = request.post('/wp-json/wp-content-flow/v1/ai/generate', {
        headers: {
          'X-WP-Nonce': authToken,
          'Content-Type': 'application/json'
        },
        data: {
          prompt: `Concurrent test ${i}`,
          provider: 'openai',
          max_tokens: 50
        }
      });
      promises.push(promise);
    }
    
    const responses = await Promise.all(promises);
    
    // All requests should succeed
    responses.forEach((response, index) => {
      expect(response.status()).toBe(200);
    });
    
    // Verify all responses have unique content
    const contents = await Promise.all(
      responses.map(response => response.json().then(data => data.data.content))
    );
    
    // Each response should be unique (AI should generate different content)
    const uniqueContents = new Set(contents);
    expect(uniqueContents.size).toBe(concurrentRequests);
  });

  test('should handle API errors gracefully', async ({ request }) => {
    // Test with invalid JSON
    let response = await request.post('/wp-json/wp-content-flow/v1/ai/generate', {
      headers: {
        'X-WP-Nonce': authToken,
        'Content-Type': 'application/json'
      },
      data: 'invalid json'
    });
    
    expect(response.status()).toBe(400);
    let data = await response.json();
    expect(data.success).toBe(false);
    expect(data.data.message).toContain('Invalid JSON');
    
    // Test with API key error simulation
    response = await request.post('/wp-json/wp-content-flow/v1/ai/generate', {
      headers: {
        'X-WP-Nonce': authToken,
        'Content-Type': 'application/json'
      },
      data: {
        prompt: 'Test with invalid key',
        provider: 'test_invalid_key'  // This would trigger API key error
      }
    });
    
    expect(response.status()).toBe(500);
    data = await response.json();
    expect(data.success).toBe(false);
    expect(data.data.message).toBeTruthy();
  });

  test('should provide API documentation endpoint', async ({ request }) => {
    const response = await request.get('/wp-json/wp-content-flow/v1/docs');
    
    expect(response.status()).toBe(200);
    const data = await response.json();
    expect(data.success).toBe(true);
    expect(data.data.endpoints).toBeDefined();
    expect(Array.isArray(data.data.endpoints)).toBe(true);
    expect(data.data.version).toBe('1.0.0');
    
    // Check for key endpoints
    const endpointPaths = data.data.endpoints.map(ep => ep.path);
    expect(endpointPaths).toContain('/ai/generate');
    expect(endpointPaths).toContain('/ai/improve');
    expect(endpointPaths).toContain('/workflows');
    expect(endpointPaths).toContain('/suggestions');
  });
});