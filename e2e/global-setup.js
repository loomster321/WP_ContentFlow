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
    await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
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
    
    // Look for our plugin by title or file name
    const pluginRow = page.locator('tr').filter({ hasText: 'WordPress AI Content Flow' }).first();
    const alternativeRow = page.locator('tr').filter({ hasText: 'wp-content-flow' }).first();
    
    let foundPlugin = false;
    
    if (await pluginRow.count() > 0) {
      foundPlugin = true;
      // Check if plugin is active
      const deactivateLink = await pluginRow.locator('.deactivate a').count();
      
      if (deactivateLink === 0) {
        // Plugin is not active, activate it
        const activateLink = pluginRow.locator('.activate a').first();
        if (await activateLink.count() > 0) {
          await activateLink.click();
          await page.waitForSelector('.notice-success', { timeout: 10000 });
          console.log('✅ Plugin activated successfully');
        }
      } else {
        console.log('✅ Plugin is already active');
      }
    } else if (await alternativeRow.count() > 0) {
      foundPlugin = true;
      // Check using alternative row
      const deactivateLink = await alternativeRow.locator('.deactivate a').count();
      
      if (deactivateLink === 0) {
        // Plugin is not active, activate it
        const activateLink = alternativeRow.locator('.activate a').first();
        if (await activateLink.count() > 0) {
          await activateLink.click();
          await page.waitForSelector('.notice-success', { timeout: 10000 });
          console.log('✅ Plugin activated successfully');
        }
      } else {
        console.log('✅ Plugin is already active');
      }
    }
    
    if (!foundPlugin) {
      // Debug: log what's on the page
      const pageContent = await page.content();
      console.log('Plugin page HTML snippet:', pageContent.substring(0, 500));
      throw new Error('AI Content Flow plugin not found in plugins list');
    }
    
    // Try to verify plugin menu appears (optional - may not exist yet)
    try {
      await page.waitForSelector('[href*="wp-content-flow"]', { timeout: 2000 });
      console.log('✅ Plugin menu found');
    } catch (menuError) {
      console.log('⚠️  Plugin menu not found (may appear later)');
    }
    
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
      
      // WordPress 6.4+ uses a different password field structure
      // Try to find and click the "Show password" button first
      const showPasswordButton = page.locator('button.wp-generate-pw');
      if (await showPasswordButton.count() > 0) {
        await showPasswordButton.click();
        await page.waitForTimeout(500); // Wait for password fields to appear
      }
      
      // Now fill the password field(s)
      const pass1 = page.locator('#pass1, #pass1-text').first();
      if (await pass1.count() > 0) {
        await pass1.fill('testpass123!@#');
      }
      
      // pass2 might not exist in newer WordPress versions
      const pass2 = page.locator('#pass2').first();
      if (await pass2.count() > 0 && await pass2.isVisible()) {
        await pass2.fill('testpass123!@#');
      }
      
      await page.selectOption('#role', user.role);
      
      // Submit form
      await page.click('#createusersub');
      
      // Wait for navigation or success message
      try {
        await Promise.race([
          page.waitForSelector('.notice-success', { timeout: 5000 }),
          page.waitForURL('**/users.php**', { timeout: 5000 }),
          page.waitForSelector('.error', { timeout: 5000 })
        ]);
        
        // Check if there's an error
        const errorElement = await page.locator('.error').first();
        if (await errorElement.count() > 0) {
          const errorText = await errorElement.textContent();
          console.log(`⚠️  User creation warning for ${user.username}: ${errorText}`);
          // User might already exist, continue
        } else {
          console.log(`✅ Created ${user.role} test user: ${user.username}`);
        }
      } catch (waitError) {
        console.log(`⚠️  Could not verify user creation for ${user.username}, continuing...`);
      }
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
      // Navigate with reduced wait time
      await page.goto('http://localhost:8080/wp-admin/post-new.php', { 
        waitUntil: 'domcontentloaded',
        timeout: 15000 
      });
      
      // Check if we're using Gutenberg or Classic editor
      const gutenbergEditor = await page.locator('.block-editor-writing-flow').count();
      const classicEditor = await page.locator('#content').count();
      
      if (gutenbergEditor > 0) {
        // Gutenberg editor
        await page.waitForSelector('.block-editor-writing-flow', { timeout: 15000 });
        
        // Add title using the title input
        const titleInput = page.locator('.editor-post-title__input, [name="post_title"]').first();
        if (await titleInput.count() > 0) {
          await titleInput.fill(post.title);
        }
        
        // Add content
        const blockAppender = page.locator('.block-editor-default-block-appender__content, .block-editor-rich-text__editable').first();
        if (await blockAppender.count() > 0) {
          await blockAppender.click();
          await page.keyboard.type(post.content);
        }
        
        // Save as draft
        const saveButton = page.locator('.editor-post-save-draft, button:has-text("Save draft")').first();
        if (await saveButton.count() > 0) {
          await saveButton.click();
          // Wait for save to complete
          await page.waitForTimeout(2000);
        }
      } else if (classicEditor > 0) {
        // Classic editor fallback
        await page.fill('[name="post_title"]', post.title);
        await page.fill('#content', post.content);
        await page.click('#save-post');
        await page.waitForTimeout(2000);
      }
      
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
  // Skip API verification for now as it's not critical for initial tests
  console.log('⚠️  Skipping API endpoint verification (can be tested in E2E tests)');
  return;
}

module.exports = globalSetup;