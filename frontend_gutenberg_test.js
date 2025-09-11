/**
 * Frontend Gutenberg Integration Test
 * 
 * Tests the actual user interface functionality that the user reported issues with.
 * Focuses on the "globe icon" and AI Chat panel integration.
 */

const http = require('http');
const { URL } = require('url');

class FrontendGutenbergTester {
  constructor() {
    this.baseUrl = 'http://localhost:8080';
    this.sessionCookies = '';
  }

  async log(message, type = 'info') {
    const timestamp = new Date().toISOString().split('T')[1].split('.')[0];
    const prefix = type === 'error' ? '❌' : type === 'success' ? '✅' : type === 'warning' ? '⚠️' : '🔍';
    console.log(`${prefix} [${timestamp}] ${message}`);
  }

  async makeRequest(path, options = {}) {
    return new Promise((resolve, reject) => {
      const url = new URL(path, this.baseUrl);
      const requestOptions = {
        hostname: url.hostname,
        port: url.port,
        path: url.pathname + url.search,
        method: options.method || 'GET',
        headers: {
          'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
          'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
          'Accept-Language': 'en-US,en;q=0.5',
          'Connection': 'keep-alive',
          'Cookie': this.sessionCookies,
          ...options.headers
        }
      };

      if (options.body) {
        requestOptions.headers['Content-Type'] = options.contentType || 'application/x-www-form-urlencoded';
        requestOptions.headers['Content-Length'] = Buffer.byteLength(options.body);
      }

      const req = http.request(requestOptions, (res) => {
        let data = '';
        
        if (res.headers['set-cookie']) {
          const cookies = res.headers['set-cookie'].map(cookie => cookie.split(';')[0]).join('; ');
          this.sessionCookies = this.sessionCookies ? this.sessionCookies + '; ' + cookies : cookies;
        }

        res.on('data', chunk => data += chunk);
        res.on('end', () => {
          resolve({
            statusCode: res.statusCode,
            headers: res.headers,
            body: data
          });
        });
      });

      req.on('error', reject);
      
      if (options.body) {
        req.write(options.body);
      }
      
      req.end();
    });
  }

  async followRedirects(path, maxRedirects = 5) {
    let currentPath = path;
    let redirectCount = 0;
    let response;

    while (redirectCount < maxRedirects) {
      response = await this.makeRequest(currentPath);
      
      if (response.statusCode >= 300 && response.statusCode < 400 && response.headers.location) {
        currentPath = response.headers.location.startsWith('http') ? new URL(response.headers.location).pathname : response.headers.location;
        redirectCount++;
      } else {
        break;
      }
    }

    return response;
  }

  async performLogin() {
    const loginData = new URLSearchParams({
      log: 'admin',
      pwd: '!3cTXkh)9iDHhV5o*N',
      'wp-submit': 'Log In',
      redirect_to: '/wp-admin/',
      testcookie: '1'
    }).toString();

    const loginResponse = await this.makeRequest('/wp-login.php', {
      method: 'POST',
      body: loginData,
      headers: {
        'Referer': `${this.baseUrl}/wp-login.php`,
        'Origin': this.baseUrl
      }
    });

    if (loginResponse.statusCode === 302) {
      await this.followRedirects(loginResponse.headers.location);
      return true;
    }
    
    return loginResponse.body.includes('Dashboard') || loginResponse.body.includes('wp-admin-bar');
  }

  async testCorrectRestApiUrls() {
    await this.log('Testing REST API with correct WordPress URLs...');
    
    // WordPress uses index.php?rest_route= format when pretty permalinks aren't enabled
    const endpoints = [
      '/index.php?rest_route=/wp-content-flow/v1/status',
      '/?rest_route=/wp-content-flow/v1/status',
      '/wp-json/wp-content-flow/v1/status'
    ];

    for (const endpoint of endpoints) {
      try {
        const response = await this.makeRequest(endpoint);
        await this.log(`${endpoint}: ${response.statusCode}`);
        
        if (response.statusCode === 200) {
          try {
            const data = JSON.parse(response.body);
            await this.log(`✅ Working endpoint found!`, 'success');
            await this.log(`  API Version: ${data.version}`);
            await this.log(`  Status: ${data.status}`);
            await this.log(`  AI Providers: ${Object.keys(data.ai_providers || {}).join(', ')}`);
            return endpoint;
          } catch (e) {
            await this.log(`  Response not JSON: ${response.body.substring(0, 100)}...`);
          }
        }
      } catch (error) {
        await this.log(`  Error: ${error.message}`, 'error');
      }
    }
    
    return null;
  }

  async analyzeEditorInterfaceElements() {
    await this.log('Analyzing Gutenberg editor interface for AI Chat elements...');
    
    const editorResponse = await this.followRedirects('/wp-admin/post-new.php');
    
    if (editorResponse.statusCode !== 200) {
      await this.log('Cannot access post editor', 'error');
      return false;
    }

    const analysis = {
      hasEditPostHeader: false,
      hasPluginSidebar: false,
      hasAiChatElements: false,
      menuButtons: [],
      pluginRegistration: false,
      jsConsoleLog: false,
      wpContentFlowGlobal: false
    };

    // Check for editor structure
    if (editorResponse.body.includes('edit-post-header')) {
      analysis.hasEditPostHeader = true;
      await this.log('✓ Edit post header found');
    }

    // Look for plugin sidebar registration code
    if (editorResponse.body.includes('PluginSidebar') || 
        editorResponse.body.includes('registerPlugin')) {
      analysis.hasPluginSidebar = true;
      await this.log('✓ Plugin sidebar code detected');
    }

    // Look for AI Chat specific elements
    const aiChatPatterns = [
      'AI Chat',
      'wp-content-flow-ai-chat',
      'admin-site-alt3',
      'AI Content Flow'
    ];

    for (const pattern of aiChatPatterns) {
      if (editorResponse.body.includes(pattern)) {
        analysis.hasAiChatElements = true;
        await this.log(`✓ Found AI Chat pattern: ${pattern}`);
      }
    }

    // Check for plugin registration console log
    if (editorResponse.body.includes('WP Content Flow: AI Chat panel registered successfully')) {
      analysis.jsConsoleLog = true;
      await this.log('✓ Plugin registration console log found');
    }

    // Check for wpContentFlow global
    if (editorResponse.body.includes('wpContentFlow')) {
      analysis.wpContentFlowGlobal = true;
      await this.log('✓ wpContentFlow global variable found');
    }

    // Look for button elements in header
    const buttonPattern = /<button[^>]*([^<]*(?:Options|Settings|Menu|more)[^<]*)<\/button>/gi;
    const buttonMatches = editorResponse.body.match(buttonPattern) || [];
    
    await this.log(`Found ${buttonMatches.length} potential menu buttons`);

    // Look for specific WordPress editor elements that should contain our plugin
    const editorElements = [
      'edit-post-header__settings',
      'edit-post-more-menu',
      'components-dropdown-menu',
      'interface-more-menu-dropdown'
    ];

    for (const element of editorElements) {
      if (editorResponse.body.includes(element)) {
        await this.log(`✓ Found editor element: ${element}`);
      }
    }

    // Extract JavaScript that would run on page load
    const scriptTags = editorResponse.body.match(/<script[^>]*>[\s\S]*?<\/script>/g) || [];
    let hasPluginInitialization = false;
    
    for (const script of scriptTags) {
      if (script.includes('wp-content-flow') || 
          script.includes('registerPlugin') ||
          script.includes('AI Chat')) {
        hasPluginInitialization = true;
        await this.log('✓ Plugin initialization code found in inline script');
        break;
      }
    }

    analysis.pluginRegistration = hasPluginInitialization;

    return analysis;
  }

  async testAiChatPanelAccessibility() {
    await this.log('Testing AI Chat panel accessibility patterns...');
    
    const editorResponse = await this.followRedirects('/wp-admin/post-new.php');
    
    // Look for patterns that indicate where the AI Chat option should appear
    const menuPatterns = [
      // Plugin sidebar menu item pattern
      /PluginSidebarMoreMenuItem[^}]*target:\s*['"](wp-content-flow-ai-chat)['"]/, 
      // Menu text pattern
      /text=["']AI Chat["']/,
      // Icon pattern
      /icon:\s*["']admin-site-alt3["']/,
      // Plugin registration pattern
      /registerPlugin\(\s*["']wp-content-flow-ai-chat["']/
    ];

    let foundPatterns = [];
    
    for (let i = 0; i < menuPatterns.length; i++) {
      const pattern = menuPatterns[i];
      const match = editorResponse.body.match(pattern);
      
      if (match) {
        foundPatterns.push({
          pattern: i + 1,
          match: match[0],
          context: match.input.substring(Math.max(0, match.index - 50), match.index + match[0].length + 50)
        });
        await this.log(`✓ Found menu pattern ${i + 1}: ${match[0]}`, 'success');
      }
    }

    if (foundPatterns.length === 0) {
      await this.log('❌ No AI Chat menu patterns found in editor HTML', 'error');
      
      // Check if the JavaScript file is actually loading
      if (editorResponse.body.includes('blocks.js')) {
        await this.log('⚠️ blocks.js is referenced but AI Chat patterns not found', 'warning');
        await this.log('This suggests the JavaScript may not be executing properly');
      }
    } else {
      await this.log(`✅ Found ${foundPatterns.length} AI Chat patterns`, 'success');
    }

    return foundPatterns;
  }

  async generateUserExperienceReport() {
    await this.log('Generating User Experience Report for AI Chat functionality...');
    
    console.log('\n🎯 USER EXPERIENCE ANALYSIS');
    console.log('============================');
    
    const loginSuccess = await this.performLogin();
    if (!loginSuccess) {
      console.log('❌ Cannot test - login failed');
      return;
    }

    console.log('✅ WordPress admin access: SUCCESS\n');

    // Test REST API accessibility
    console.log('🔍 REST API Accessibility:');
    const workingEndpoint = await this.testCorrectRestApiUrls();
    
    if (workingEndpoint) {
      console.log(`✅ REST API accessible at: ${workingEndpoint}\n`);
    } else {
      console.log('❌ REST API not accessible from frontend\n');
    }

    // Test editor interface
    console.log('🔍 Gutenberg Editor Interface:');
    const editorAnalysis = await this.analyzeEditorInterfaceElements();
    
    console.log(`Edit Post Header: ${editorAnalysis.hasEditPostHeader ? '✅' : '❌'}`);
    console.log(`Plugin Sidebar Code: ${editorAnalysis.hasPluginSidebar ? '✅' : '❌'}`);
    console.log(`AI Chat Elements: ${editorAnalysis.hasAiChatElements ? '✅' : '❌'}`);
    console.log(`Plugin Registration: ${editorAnalysis.pluginRegistration ? '✅' : '❌'}`);
    console.log(`Console Logging: ${editorAnalysis.jsConsoleLog ? '✅' : '❌'}`);
    console.log(`Global Variables: ${editorAnalysis.wpContentFlowGlobal ? '✅' : '❌'}\n`);

    // Test menu accessibility
    console.log('🔍 AI Chat Menu Accessibility:');
    const menuPatterns = await this.testAiChatPanelAccessibility();
    
    if (menuPatterns.length > 0) {
      console.log('✅ AI Chat should be visible in editor menu');
      console.log('Patterns found:', menuPatterns.length);
    } else {
      console.log('❌ AI Chat menu patterns not found');
    }

    console.log('\n📋 DIAGNOSIS:');
    
    if (workingEndpoint && editorAnalysis.hasAiChatElements && menuPatterns.length > 0) {
      console.log('✅ ALL COMPONENTS DETECTED');
      console.log('💡 The AI Chat functionality should be working.');
      console.log('🔍 If user still reports issues:');
      console.log('   - Check for JavaScript errors in browser console');
      console.log('   - Verify browser extensions aren\'t blocking functionality');
      console.log('   - Clear browser cache and WordPress caches');
    } else {
      console.log('❌ MISSING COMPONENTS DETECTED');
      
      if (!workingEndpoint) {
        console.log('   - REST API endpoints not accessible from frontend');
      }
      
      if (!editorAnalysis.hasAiChatElements) {
        console.log('   - AI Chat elements not found in editor HTML');
        console.log('   - Plugin JavaScript may not be loading or executing');
      }
      
      if (menuPatterns.length === 0) {
        console.log('   - Menu registration patterns not detected');
        console.log('   - Plugin sidebar may not be properly registered');
      }
    }

    console.log('\n🔧 RECOMMENDATIONS:');
    
    if (!editorAnalysis.hasAiChatElements || menuPatterns.length === 0) {
      console.log('1. Check if plugin JavaScript is loading without errors');
      console.log('2. Verify WordPress dependencies (wp.plugins, wp.editPost) are available');
      console.log('3. Check browser console for JavaScript errors when loading post editor');
      console.log('4. Ensure plugin is properly activated and files exist');
    }
    
    if (!workingEndpoint) {
      console.log('5. Verify REST API permalink settings in WordPress');
      console.log('6. Check if .htaccess is properly configured for REST API');
    }

    console.log('\n🎬 NEXT STEPS FOR USER:');
    console.log('1. Open post editor (wp-admin/post-new.php)');
    console.log('2. Open browser Developer Tools (F12)');
    console.log('3. Check Console tab for any JavaScript errors');
    console.log('4. Look for "WP Content Flow: AI Chat panel registered successfully" message');
    console.log('5. Check if "AI Chat" option appears in the editor more menu (three dots)');
    
    return {
      loginSuccess,
      apiWorking: !!workingEndpoint,
      editorReady: editorAnalysis.hasAiChatElements,
      menuRegistered: menuPatterns.length > 0
    };
  }
}

// Run the frontend test
if (require.main === module) {
  const tester = new FrontendGutenbergTester();
  tester.generateUserExperienceReport()
    .then(results => {
      console.log('\n🏁 Frontend testing complete');
    })
    .catch(error => {
      console.error('❌ Frontend test failed:', error);
    });
}

module.exports = FrontendGutenbergTester;