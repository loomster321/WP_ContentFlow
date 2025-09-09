const { chromium } = require('@playwright/test');

/**
 * Global Setup for WordPress AI Content Flow Plugin E2E Tests
 * 
 * Prepares WordPress environment and plugin for testing
 * Creates test data, user accounts, and verifies plugin activation
 */
async function globalSetup() {
  console.log('🚀 Starting WordPress AI Content Flow Plugin E2E Setup...');
  
  try {
    // Launch browser for setup tasks
    const browser = await chromium.launch();
    const context = await browser.newContext();
    const page = await context.newPage();

    // Wait for WordPress to be ready
    console.log('⏳ Waiting for WordPress to be ready...');
    await page.goto('http://localhost:8080/wp-admin/', { 
      waitUntil: 'networkidle',
      timeout: 60000 
    });

    // Handle WordPress installation if needed
    if (page.url().includes('wp-admin/install.php')) {
      console.log('📦 Setting up WordPress installation...');
      await setupWordPressInstallation(page);
    }

    // Login as admin
    console.log('🔐 Logging in as administrator...');
    await loginAsAdmin(page);

    // Verify plugin is installed and activate if needed
    console.log('🔌 Verifying plugin installation...');
    await verifyAndActivatePlugin(page);

    // Create test users with different roles
    console.log('👥 Creating test users...');
    await createTestUsers(page);

    // Create test content and workflows
    console.log('📝 Setting up test data...');
    await setupTestData(page);

    // Verify API endpoints are working
    console.log('🔗 Testing API endpoints...');
    await verifyApiEndpoints(page);

    await browser.close();
    console.log('✅ WordPress AI Content Flow Plugin E2E Setup completed successfully!');
    
  } catch (error) {
    console.error('❌ Global setup failed:', error);
    throw error;
  }
}

/**
 * Setup WordPress installation
 */
async function setupWordPressInstallation(page) {
  try {
    // Fill in site information
    await page.fill('#weblog_title', 'WP Content Flow Test Site');
    await page.fill('#user_name', 'admin');
    await page.fill('#pass1', 'admin123!@#');
    await page.fill('#pass2', 'admin123!@#');
    await page.fill('#admin_email', 'admin@wpcontentflow.test');
    
    // Uncheck search engine visibility for testing
    await page.uncheck('#blog_public');
    
    // Submit installation
    await page.click('#submit');
    
    // Wait for installation completion
    await page.waitForSelector('.step', { timeout: 30000 });
    
  } catch (error) {
    console.error('WordPress installation setup failed:', error);
    throw error;
  }
}

/**
 * Login as WordPress administrator
 */
async function loginAsAdmin(page) {
  try {
    // Navigate to login if not already there
    if (!page.url().includes('wp-login.php')) {
      await page.goto('http://localhost:8080/wp-admin/', { waitUntil: 'networkidle' });
    }

    // Fill login form
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'admin123!@#');
    await page.click('#wp-submit');
    
    // Wait for dashboard
    await page.waitForSelector('#wpadminbar', { timeout: 15000 });
    
  } catch (error) {
    console.error('Admin login failed:', error);
    throw error;
  }
}

/**
 * Verify and activate the AI Content Flow plugin
 */
async function verifyAndActivatePlugin(page) {
  try {
    // Navigate to plugins page
    await page.goto('http://localhost:8080/wp-admin/plugins.php', { waitUntil: 'networkidle' });
    
    // Look for our plugin
    const pluginRow = page.locator('[data-slug="wp-content-flow"]').first();
    
    if (await pluginRow.count() > 0) {
      // Check if plugin is active
      const isActive = await pluginRow.locator('.active').count() > 0;
      
      if (!isActive) {
        // Activate the plugin
        await pluginRow.locator('.activate a').click();
        await page.waitForSelector('.notice-success', { timeout: 10000 });
        console.log('✅ Plugin activated successfully');
      } else {
        console.log('✅ Plugin is already active');
      }
    } else {
      throw new Error('AI Content Flow plugin not found in plugins list');
    }
    
    // Verify plugin menu appears
    await page.waitForSelector('[href="admin.php?page=wp-content-flow"]', { timeout: 5000 });
    
  } catch (error) {
    console.error('Plugin verification/activation failed:', error);
    throw error;
  }
}

/**
 * Create test users with different capabilities
 */
async function createTestUsers(page) {
  const testUsers = [
    { username: 'editor_test', email: 'editor@test.com', role: 'editor' },
    { username: 'author_test', email: 'author@test.com', role: 'author' },
    { username: 'contributor_test', email: 'contributor@test.com', role: 'contributor' }
  ];

  try {
    for (const user of testUsers) {
      await page.goto('http://localhost:8080/wp-admin/user-new.php', { waitUntil: 'networkidle' });
      
      // Fill user form
      await page.fill('#user_login', user.username);
      await page.fill('#email', user.email);
      await page.fill('#pass1', 'testpass123!@#');
      await page.fill('#pass2', 'testpass123!@#');
      await page.selectOption('#role', user.role);
      
      // Submit form
      await page.click('#createusersub');
      
      // Wait for success message
      await page.waitForSelector('.notice-success', { timeout: 10000 });
      console.log(`✅ Created ${user.role} test user: ${user.username}`);
    }
  } catch (error) {
    console.error('Test user creation failed:', error);
    throw error;
  }
}

/**
 * Setup test data (workflows, posts, etc.)
 */
async function setupTestData(page) {
  try {
    // Navigate to plugin settings
    await page.goto('http://localhost:8080/wp-admin/admin.php?page=wp-content-flow-settings', { 
      waitUntil: 'networkidle' 
    });
    
    // Setup basic plugin configuration
    if (await page.locator('#ai_provider').count() > 0) {
      await page.selectOption('#ai_provider', 'openai');
      
      // Add test API key (mock for testing)
      await page.fill('[name="wp_content_flow_openai_api_key"]', 'sk-test-mock-api-key-for-e2e-testing');
      
      // Save settings
      await page.click('input[type="submit"]');
      await page.waitForSelector('.notice-success', { timeout: 10000 });
      console.log('✅ Plugin settings configured');
    }
    
    // Create a test workflow
    await page.goto('http://localhost:8080/wp-admin/admin.php?page=wp-content-flow-workflows', { 
      waitUntil: 'networkidle' 
    });
    
    // Create test posts for content generation testing
    await createTestPosts(page);
    
  } catch (error) {
    console.error('Test data setup failed:', error);
    throw error;
  }
}

/**
 * Create test posts
 */
async function createTestPosts(page) {
  const testPosts = [
    { title: 'E2E Test Post 1', content: 'This is a test post for E2E testing.' },
    { title: 'E2E Test Post 2', content: 'Another test post for workflow testing.' }
  ];

  try {
    for (const post of testPosts) {
      await page.goto('http://localhost:8080/wp-admin/post-new.php', { waitUntil: 'networkidle' });
      
      // Fill post title
      await page.fill('[name="post_title"]', post.title);
      
      // Wait for Gutenberg editor to load
      await page.waitForSelector('.block-editor-writing-flow', { timeout: 15000 });
      
      // Add content to Gutenberg editor
      await page.click('.block-editor-default-block-appender__content');
      await page.type('.block-editor-rich-text__editable', post.content);
      
      // Save as draft
      await page.click('.editor-post-save-draft');
      await page.waitForSelector('.is-saved', { timeout: 10000 });
      
      console.log(`✅ Created test post: ${post.title}`);
    }
  } catch (error) {
    console.error('Test post creation failed:', error);
    throw error;
  }
}

/**
 * Verify API endpoints are responding
 */
async function verifyApiEndpoints(page) {
  const apiEndpoints = [
    '/wp-json/wp-content-flow/v1/workflows',
    '/wp-json/wp-content-flow/v1/ai/generate',
    '/wp-json/wp-content-flow/v1/settings'
  ];

  try {
    for (const endpoint of apiEndpoints) {
      const response = await page.request.get(`http://localhost:8080${endpoint}`);
      
      if (response.ok() || response.status() === 401) {
        // 401 is acceptable for protected endpoints without proper auth
        console.log(`✅ API endpoint responding: ${endpoint}`);
      } else {
        console.warn(`⚠️  API endpoint issue: ${endpoint} (Status: ${response.status()})`);
      }
    }
  } catch (error) {
    console.error('API endpoint verification failed:', error);
    // Don't throw error here as API might not be fully initialized yet
  }
}

module.exports = globalSetup;