/**
 * WordPress AI Content Flow - Gutenberg AI Chat Panel Integration Test
 * 
 * This test specifically addresses user-reported issues with the globe icon
 * and AI Chat functionality not working properly in the post editor.
 * 
 * Tests:
 * - WordPress admin login and post editor access
 * - JavaScript loading and dependencies
 * - Plugin sidebar registration
 * - AI Chat panel visibility and functionality
 * - Form interactions and API calls
 * - Error handling and user feedback
 */

const { test, expect } = require('@playwright/test');

test.describe('Gutenberg AI Chat Panel Integration', () => {
  let page;
  let adminContext;

  test.beforeAll(async ({ browser }) => {
    // Create admin context for all tests
    adminContext = await browser.newContext({
      viewport: { width: 1440, height: 900 },
      ignoreHTTPSErrors: true
    });
    
    page = await adminContext.newPage();
    
    // Enable console logging to capture JavaScript errors
    page.on('console', msg => {
      if (msg.type() === 'error') {
        console.log(`🚫 CONSOLE ERROR: ${msg.text()}`);
      } else if (msg.text().includes('WP Content Flow')) {
        console.log(`✅ PLUGIN LOG: ${msg.text()}`);
      }
    });
    
    // Capture network failures
    page.on('response', response => {
      if (response.status() >= 400) {
        console.log(`❌ NETWORK ERROR: ${response.status()} ${response.url()}`);
      }
    });
  });

  test.afterAll(async () => {
    await adminContext?.close();
  });

  test('should login to WordPress admin successfully', async () => {
    console.log('🔍 Testing WordPress admin login...');
    
    await page.goto('/wp-admin');
    
    // Check if already logged in
    const isLoggedIn = await page.locator('.wp-admin').isVisible().catch(() => false);
    
    if (!isLoggedIn) {
      await page.fill('#user_login', 'admin');
      await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
      await page.click('#wp-submit');
      
      // Wait for dashboard to load
      await page.waitForSelector('.wp-admin', { timeout: 10000 });
    }
    
    // Verify we're in WordPress admin
    await expect(page.locator('body.wp-admin')).toBeVisible();
    console.log('✅ Successfully logged into WordPress admin');
  });

  test('should verify plugin is activated', async () => {
    console.log('🔍 Checking if WP Content Flow plugin is activated...');
    
    await page.goto('/wp-admin/plugins.php');
    await page.waitForLoadState('networkidle');
    
    // Look for our plugin in the active plugins list
    const pluginRow = page.locator('tr[data-plugin*="wp-content-flow/wp-content-flow.php"], tr:has-text("WordPress AI Content Flow")');
    await expect(pluginRow).toBeVisible({ timeout: 5000 });
    
    // Verify it shows as active (should not have "Activate" link)
    const activateLink = pluginRow.locator('a:has-text("Activate")');
    await expect(activateLink).not.toBeVisible();
    
    console.log('✅ WP Content Flow plugin is activated');
  });

  test('should access post editor and check JavaScript loading', async () => {
    console.log('🔍 Testing post editor access and JavaScript dependencies...');
    
    // Navigate to new post
    await page.goto('/wp-admin/post-new.php');
    await page.waitForLoadState('networkidle');
    
    // Wait for Gutenberg editor to fully load
    await page.waitForSelector('.block-editor-page', { timeout: 15000 });
    await page.waitForSelector('.edit-post-header', { timeout: 10000 });
    
    // Check for WordPress global objects in console
    const wpObjects = await page.evaluate(() => {
      return {
        wp: typeof window.wp !== 'undefined',
        wpPlugins: typeof window.wp?.plugins !== 'undefined',
        wpEditPost: typeof window.wp?.editPost !== 'undefined',
        wpComponents: typeof window.wp?.components !== 'undefined',
        wpElement: typeof window.wp?.element !== 'undefined',
        wpBlocks: typeof window.wp?.blocks !== 'undefined',
        wpData: typeof window.wp?.data !== 'undefined',
        apiFetch: typeof window.wp?.apiFetch !== 'undefined'
      };
    });
    
    console.log('WordPress Dependencies:', wpObjects);
    
    // Verify all required WordPress dependencies are loaded
    expect(wpObjects.wp).toBe(true);
    expect(wpObjects.wpPlugins).toBe(true);
    expect(wpObjects.wpEditPost).toBe(true);
    expect(wpObjects.wpComponents).toBe(true);
    expect(wpObjects.wpElement).toBe(true);
    expect(wpObjects.wpBlocks).toBe(true);
    expect(wpObjects.wpData).toBe(true);
    expect(wpObjects.apiFetch).toBe(true);
    
    console.log('✅ All WordPress dependencies loaded successfully');
  });

  test('should check if plugin JavaScript is loaded', async () => {
    console.log('🔍 Checking if plugin JavaScript is loaded...');
    
    await page.goto('/wp-admin/post-new.php');
    await page.waitForLoadState('networkidle');
    await page.waitForSelector('.block-editor-page', { timeout: 15000 });
    
    // Check if our plugin's JavaScript file is loaded
    const pluginScript = await page.evaluate(() => {
      const scripts = Array.from(document.querySelectorAll('script[src*="wp-content-flow"]'));
      return scripts.map(script => ({
        src: script.src,
        loaded: true
      }));
    });
    
    console.log('Plugin Scripts Found:', pluginScript);
    expect(pluginScript.length).toBeGreaterThan(0);
    
    // Check if plugin registration console log appeared
    const hasPluginLog = await page.evaluate(() => {
      // Check if our plugin registered successfully
      return window.wp && window.wp.plugins && window.wp.plugins.getPlugin && 
             window.wp.plugins.getPlugin('wp-content-flow-ai-chat') !== undefined;
    });
    
    console.log('Plugin Registration Status:', hasPluginLog);
    console.log('✅ Plugin JavaScript loading verified');
  });

  test('should find AI Chat option in editor sidebar', async () => {
    console.log('🔍 Looking for AI Chat option in editor sidebar...');
    
    await page.goto('/wp-admin/post-new.php');
    await page.waitForLoadState('networkidle');
    await page.waitForSelector('.block-editor-page', { timeout: 15000 });
    
    // Wait for editor to be fully loaded
    await page.waitForTimeout(3000);
    
    // Look for the three-dots menu (settings/options menu) in the editor
    const settingsButton = page.locator('.edit-post-header .edit-post-header__settings button[aria-label*="Options"], .edit-post-header .edit-post-header__settings button[aria-label*="Settings"], .edit-post-header button[aria-label*="view menu"], .edit-post-header button:has([class*="settings"])');
    
    // Try multiple selectors for the settings/options menu
    const possibleSettingsSelectors = [
      '.edit-post-header__settings button[aria-expanded]',
      '.edit-post-header button[aria-label*="Options"]',
      '.edit-post-header button[aria-label*="Settings"]',
      '.edit-post-header button:has(.dashicon-admin-generic)',
      '.edit-post-header button:has(.dashicon-menu)',
      'button[aria-label*="view menu"]',
      '.edit-post-more-menu .components-dropdown-menu__toggle'
    ];
    
    let settingsMenuFound = false;
    
    for (const selector of possibleSettingsSelectors) {
      try {
        const element = page.locator(selector).first();
        if (await element.isVisible({ timeout: 2000 })) {
          console.log(`Found settings menu with selector: ${selector}`);
          await element.click();
          settingsMenuFound = true;
          break;
        }
      } catch (error) {
        console.log(`Selector ${selector} not found or not clickable`);
      }
    }
    
    if (!settingsMenuFound) {
      // Take a screenshot to see what the interface looks like
      await page.screenshot({ path: 'test-results/editor-interface.png', fullPage: true });
      console.log('📸 Screenshot saved to test-results/editor-interface.png');
      
      // List all buttons in the header to see what's available
      const headerButtons = await page.locator('.edit-post-header button').allTextContents();
      console.log('Available header buttons:', headerButtons);
      
      // Try to find any dropdown or menu buttons
      const dropdownButtons = await page.locator('button[aria-expanded], .components-dropdown button, .components-dropdown-menu button').count();
      console.log('Found dropdown buttons:', dropdownButtons);
    }
    
    // Look for AI Chat in the dropdown menu
    const aiChatOption = page.locator('text="AI Chat"').first();
    const aiChatVisible = await aiChatOption.isVisible({ timeout: 5000 }).catch(() => false);
    
    if (aiChatVisible) {
      console.log('✅ Found AI Chat option in menu');
    } else {
      console.log('❌ AI Chat option not found in dropdown menu');
      
      // Check if plugin is properly registered by examining the DOM
      const pluginElements = await page.evaluate(() => {
        return Array.from(document.querySelectorAll('*')).filter(el => 
          el.textContent && el.textContent.includes('AI Chat')
        ).map(el => ({
          tagName: el.tagName,
          className: el.className,
          textContent: el.textContent,
          visible: el.offsetParent !== null
        }));
      });
      
      console.log('Elements containing "AI Chat":', pluginElements);
    }
    
    console.log('🔍 Editor sidebar menu inspection completed');
  });

  test('should test AI Chat panel functionality if accessible', async () => {
    console.log('🔍 Testing AI Chat panel functionality...');
    
    await page.goto('/wp-admin/post-new.php');
    await page.waitForLoadState('networkidle');
    await page.waitForSelector('.block-editor-page', { timeout: 15000 });
    await page.waitForTimeout(3000);
    
    // Try to find and click AI Chat option
    let aiChatAccessible = false;
    
    // First try to find it in a dropdown menu
    const menuSelectors = [
      '.edit-post-header__settings button',
      '.edit-post-more-menu button',
      'button[aria-label*="Options"]',
      'button[aria-label*="Settings"]'
    ];
    
    for (const selector of menuSelectors) {
      try {
        const menuButton = page.locator(selector).first();
        if (await menuButton.isVisible({ timeout: 2000 })) {
          await menuButton.click();
          await page.waitForTimeout(1000);
          
          const aiChatOption = page.locator('text="AI Chat"');
          if (await aiChatOption.isVisible({ timeout: 2000 })) {
            await aiChatOption.click();
            aiChatAccessible = true;
            console.log('✅ Successfully clicked AI Chat option');
            break;
          }
        }
      } catch (error) {
        continue;
      }
    }
    
    if (aiChatAccessible) {
      // Test AI Chat panel functionality
      await page.waitForTimeout(2000);
      
      // Look for panel elements
      const panelElements = await page.evaluate(() => {
        const elements = [];
        
        // Look for workflow dropdown
        const workflowSelect = document.querySelector('select[aria-label*="Workflow"], select:has(option[value*="workflow"])');
        if (workflowSelect) elements.push({ type: 'workflow_select', found: true });
        
        // Look for prompt textarea
        const promptTextarea = document.querySelector('textarea[placeholder*="prompt"], textarea[placeholder*="content"]');
        if (promptTextarea) elements.push({ type: 'prompt_textarea', found: true });
        
        // Look for generate button
        const generateButton = document.querySelector('button:has-text("Generate"), button[aria-label*="Generate"]');
        if (generateButton) elements.push({ type: 'generate_button', found: true });
        
        return elements;
      });
      
      console.log('AI Chat panel elements found:', panelElements);
      
      if (panelElements.length > 0) {
        console.log('✅ AI Chat panel loaded successfully with form elements');
      } else {
        console.log('❌ AI Chat panel opened but form elements not found');
      }
      
    } else {
      console.log('❌ AI Chat option not accessible - panel functionality cannot be tested');
      
      // Capture current state for debugging
      const currentUrl = page.url();
      const pageTitle = await page.title();
      
      console.log('Debug Info:', {
        currentUrl,
        pageTitle,
        timestamp: new Date().toISOString()
      });
      
      // Take screenshot for debugging
      await page.screenshot({ 
        path: 'test-results/ai-chat-not-found.png', 
        fullPage: true 
      });
    }
  });

  test('should test REST API endpoints accessibility', async () => {
    console.log('🔍 Testing REST API endpoints...');
    
    await page.goto('/wp-admin/post-new.php');
    await page.waitForLoadState('networkidle');
    
    // Test if REST API endpoints are accessible
    const apiTests = await page.evaluate(async () => {
      const results = [];
      
      // Test status endpoint (should be public)
      try {
        const statusResponse = await fetch('/wp-json/wp-content-flow/v1/status');
        results.push({
          endpoint: 'status',
          status: statusResponse.status,
          accessible: statusResponse.ok,
          response: statusResponse.ok ? await statusResponse.json() : null
        });
      } catch (error) {
        results.push({
          endpoint: 'status',
          status: 'error',
          accessible: false,
          error: error.message
        });
      }
      
      // Test workflows endpoint (requires auth)
      try {
        const workflowsResponse = await fetch('/wp-json/wp-content-flow/v1/workflows', {
          headers: {
            'X-WP-Nonce': window.wpApiSettings?.nonce || ''
          }
        });
        results.push({
          endpoint: 'workflows',
          status: workflowsResponse.status,
          accessible: workflowsResponse.ok,
          response: workflowsResponse.ok ? await workflowsResponse.json() : null
        });
      } catch (error) {
        results.push({
          endpoint: 'workflows',
          status: 'error',
          accessible: false,
          error: error.message
        });
      }
      
      return results;
    });
    
    console.log('API Test Results:', apiTests);
    
    // Verify status endpoint works
    const statusTest = apiTests.find(test => test.endpoint === 'status');
    if (statusTest?.accessible) {
      console.log('✅ REST API status endpoint accessible');
      console.log('API Status Response:', statusTest.response);
    } else {
      console.log('❌ REST API status endpoint not accessible');
    }
    
    // Check workflows endpoint
    const workflowsTest = apiTests.find(test => test.endpoint === 'workflows');
    if (workflowsTest) {
      console.log(`Workflows endpoint status: ${workflowsTest.status}`);
    }
  });

  test('should generate comprehensive diagnostic report', async () => {
    console.log('🔍 Generating comprehensive diagnostic report...');
    
    await page.goto('/wp-admin/post-new.php');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);
    
    const diagnostics = await page.evaluate(() => {
      const report = {
        wordpress: {
          version: window.wp?.version || 'unknown',
          dependencies: {
            wp: typeof window.wp !== 'undefined',
            plugins: typeof window.wp?.plugins !== 'undefined',
            editPost: typeof window.wp?.editPost !== 'undefined',
            components: typeof window.wp?.components !== 'undefined',
            element: typeof window.wp?.element !== 'undefined',
            blocks: typeof window.wp?.blocks !== 'undefined',
            data: typeof window.wp?.data !== 'undefined',
            apiFetch: typeof window.wp?.apiFetch !== 'undefined'
          }
        },
        plugin: {
          scriptsLoaded: [],
          stylesLoaded: [],
          registeredPlugins: [],
          consoleErrors: [],
          globalVars: {}
        },
        dom: {
          editorElements: [],
          menuElements: [],
          panelElements: []
        }
      };
      
      // Check for plugin scripts
      document.querySelectorAll('script[src*="wp-content-flow"]').forEach(script => {
        report.plugin.scriptsLoaded.push(script.src);
      });
      
      // Check for plugin styles
      document.querySelectorAll('link[href*="wp-content-flow"]').forEach(link => {
        report.plugin.stylesLoaded.push(link.href);
      });
      
      // Check registered plugins
      if (window.wp?.plugins?.getPlugins) {
        report.plugin.registeredPlugins = Object.keys(window.wp.plugins.getPlugins());
      }
      
      // Check for plugin-specific global variables
      if (window.wpContentFlow) {
        report.plugin.globalVars.wpContentFlow = window.wpContentFlow;
      }
      
      // Check editor elements
      if (document.querySelector('.edit-post-header')) {
        report.dom.editorElements.push('header-found');
      }
      if (document.querySelector('.block-editor-page')) {
        report.dom.editorElements.push('editor-found');
      }
      
      // Check for menu elements
      document.querySelectorAll('.edit-post-header button').forEach((button, index) => {
        report.dom.menuElements.push({
          index,
          ariaLabel: button.getAttribute('aria-label'),
          className: button.className,
          textContent: button.textContent
        });
      });
      
      return report;
    });
    
    console.log('\n📊 DIAGNOSTIC REPORT:');
    console.log('================================');
    console.log('WordPress Dependencies:', diagnostics.wordpress.dependencies);
    console.log('Plugin Scripts Loaded:', diagnostics.plugin.scriptsLoaded);
    console.log('Plugin Styles Loaded:', diagnostics.plugin.stylesLoaded);
    console.log('Registered Plugins:', diagnostics.plugin.registeredPlugins);
    console.log('Global Variables:', diagnostics.plugin.globalVars);
    console.log('Editor Elements:', diagnostics.dom.editorElements);
    console.log('Menu Elements Count:', diagnostics.dom.menuElements.length);
    
    // Check if our specific plugin is registered
    const isPluginRegistered = diagnostics.plugin.registeredPlugins.includes('wp-content-flow-ai-chat');
    console.log('AI Chat Plugin Registered:', isPluginRegistered);
    
    // Save diagnostic report to file
    await page.screenshot({ 
      path: 'test-results/diagnostic-screenshot.png', 
      fullPage: true 
    });
    
    console.log('✅ Diagnostic report complete');
    console.log('📸 Screenshot saved to test-results/diagnostic-screenshot.png');
    
    // Summarize findings
    const summary = {
      wordpressDepsLoaded: Object.values(diagnostics.wordpress.dependencies).every(dep => dep === true),
      pluginScriptsLoaded: diagnostics.plugin.scriptsLoaded.length > 0,
      pluginRegistered: isPluginRegistered,
      editorLoaded: diagnostics.dom.editorElements.includes('editor-found')
    };
    
    console.log('\n🎯 SUMMARY:', summary);
    
    return summary;
  });
});