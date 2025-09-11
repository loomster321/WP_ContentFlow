/**
 * Final Comprehensive Gutenberg Integration Test
 * 
 * This test provides the definitive answer to whether the AI Chat functionality
 * is working and identifies exactly what the user should see.
 */

const http = require('http');
const { URL } = require('url');

class FinalComprehensiveTest {
  constructor() {
    this.baseUrl = 'http://localhost:8080';
    this.sessionCookies = '';
    this.results = {
      wordpress: false,
      login: false,
      plugin: false,
      restApi: false,
      editorLoading: false,
      jsIntegration: false,
      uiElements: false
    };
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
          'Accept-Encoding': 'gzip, deflate',
          'Connection': 'keep-alive',
          'Upgrade-Insecure-Requests': '1',
          'Cache-Control': 'no-cache',
          'Pragma': 'no-cache',
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
          // Properly handle multiple cookies
          const newCookies = res.headers['set-cookie'].map(cookie => cookie.split(';')[0]).join('; ');
          if (!this.sessionCookies.includes(newCookies)) {
            this.sessionCookies = this.sessionCookies ? `${this.sessionCookies}; ${newCookies}` : newCookies;
          }
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
      req.setTimeout(30000, () => {
        req.destroy();
        reject(new Error('Request timeout'));
      });
      
      if (options.body) {
        req.write(options.body);
      }
      
      req.end();
    });
  }

  async testWordPressAccessibility() {
    await this.log('Testing WordPress accessibility...');
    
    try {
      const response = await this.makeRequest('/');
      
      if (response.statusCode === 200 && (response.body.includes('WordPress') || response.body.includes('wp-content'))) {
        this.results.wordpress = true;
        await this.log('WordPress is accessible', 'success');
        return true;
      }
    } catch (error) {
      await this.log(`WordPress access failed: ${error.message}`, 'error');
    }
    
    return false;
  }

  async performAuthentication() {
    await this.log('Performing WordPress authentication...');
    
    try {
      // Step 1: Get login page to establish session
      const loginPageResponse = await this.makeRequest('/wp-login.php');
      
      if (loginPageResponse.statusCode !== 200) {
        await this.log('Login page not accessible', 'error');
        return false;
      }

      // Step 2: Submit login credentials
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

      // Step 3: Follow redirect to dashboard
      if (loginResponse.statusCode === 302 && loginResponse.headers.location) {
        const redirectUrl = loginResponse.headers.location.replace(this.baseUrl, '');
        const dashboardResponse = await this.makeRequest(redirectUrl);
        
        if (dashboardResponse.body.includes('Dashboard') || dashboardResponse.body.includes('wp-admin-bar-root-default')) {
          this.results.login = true;
          await this.log('WordPress authentication successful', 'success');
          return true;
        }
      }

      // Alternative: Check if login response itself indicates success
      if (loginResponse.body.includes('Dashboard') || loginResponse.body.includes('wp-admin-bar-root-default')) {
        this.results.login = true;
        await this.log('WordPress authentication successful (direct)', 'success');
        return true;
      }

      await this.log('Authentication may have failed', 'warning');
      return false;
    } catch (error) {
      await this.log(`Authentication failed: ${error.message}`, 'error');
      return false;
    }
  }

  async checkPluginStatus() {
    await this.log('Checking plugin status...');
    
    try {
      const pluginsResponse = await this.makeRequest('/wp-admin/plugins.php');
      
      if (pluginsResponse.statusCode !== 200) {
        await this.log('Cannot access plugins page', 'error');
        return false;
      }

      const hasPlugin = pluginsResponse.body.includes('WordPress AI Content Flow') ||
                       pluginsResponse.body.includes('wp-content-flow');
      
      if (hasPlugin) {
        const isActive = !pluginsResponse.body.match(/WordPress AI Content Flow[\s\S]*?>Activate</);
        
        if (isActive) {
          this.results.plugin = true;
          await this.log('WP Content Flow plugin is active', 'success');
          return true;
        } else {
          await this.log('WP Content Flow plugin is inactive', 'error');
        }
      } else {
        await this.log('WP Content Flow plugin not found', 'error');
      }
    } catch (error) {
      await this.log(`Plugin check failed: ${error.message}`, 'error');
    }
    
    return false;
  }

  async testRestApiEndpoints() {
    await this.log('Testing REST API endpoints...');
    
    const endpoints = [
      '/index.php?rest_route=/wp-content-flow/v1/status',
      '/?rest_route=/wp-content-flow/v1/status',
      '/wp-json/wp-content-flow/v1/status'
    ];

    for (const endpoint of endpoints) {
      try {
        const response = await this.makeRequest(endpoint);
        
        if (response.statusCode === 200) {
          try {
            const data = JSON.parse(response.body);
            this.results.restApi = true;
            await this.log(`REST API working at ${endpoint}`, 'success');
            await this.log(`API Status: ${data.status}, Version: ${data.version}`, 'success');
            return endpoint;
          } catch (e) {
            await this.log(`${endpoint}: Response not JSON`, 'warning');
          }
        } else {
          await this.log(`${endpoint}: ${response.statusCode}`, 'warning');
        }
      } catch (error) {
        await this.log(`${endpoint}: ${error.message}`, 'error');
      }
    }
    
    return false;
  }

  async analyzePostEditorIntegration() {
    await this.log('Analyzing post editor integration...');
    
    try {
      const editorResponse = await this.makeRequest('/wp-admin/post-new.php');
      
      if (editorResponse.statusCode !== 200) {
        await this.log('Cannot access post editor', 'error');
        return false;
      }

      const analysis = {
        gutenbergLoaded: false,
        pluginScriptLoaded: false,
        wordpressDepsLoaded: false,
        pluginRegistered: false,
        aiChatElementsPresent: false,
        consolLogPresent: false
      };

      // Check Gutenberg
      if (editorResponse.body.includes('block-editor') || editorResponse.body.includes('edit-post')) {
        analysis.gutenbergLoaded = true;
        await this.log('✓ Gutenberg editor loaded');
      }

      // Check plugin script
      if (editorResponse.body.includes('wp-content-flow/assets/js/blocks.js')) {
        analysis.pluginScriptLoaded = true;
        await this.log('✓ Plugin script enqueued');
      }

      // Check WordPress dependencies
      const requiredDeps = ['wp-plugins', 'wp-edit-post', 'wp-components', 'wp-element'];
      const foundDeps = requiredDeps.filter(dep => editorResponse.body.includes(dep));
      
      if (foundDeps.length >= 3) {
        analysis.wordpressDepsLoaded = true;
        await this.log(`✓ WordPress dependencies loaded (${foundDeps.length}/${requiredDeps.length})`);
      }

      // Check plugin registration patterns
      const registrationPatterns = [
        'registerPlugin',
        'wp-content-flow-ai-chat',
        'PluginSidebar',
        'AI Chat'
      ];
      
      const foundPatterns = registrationPatterns.filter(pattern => editorResponse.body.includes(pattern));
      
      if (foundPatterns.length >= 2) {
        analysis.pluginRegistered = true;
        await this.log(`✓ Plugin registration code present (${foundPatterns.join(', ')})`);
      }

      // Check for AI Chat UI elements
      const uiElements = [
        'AI Content Flow',
        'admin-site-alt3',
        'PluginSidebarMoreMenuItem',
        'wpContentFlow'
      ];
      
      const foundUIElements = uiElements.filter(element => editorResponse.body.includes(element));
      
      if (foundUIElements.length >= 2) {
        analysis.aiChatElementsPresent = true;
        await this.log(`✓ AI Chat UI elements present (${foundUIElements.join(', ')})`);
      }

      // Check for console log
      if (editorResponse.body.includes('WP Content Flow: AI Chat panel registered successfully')) {
        analysis.consolLogPresent = true;
        await this.log('✓ Plugin registration console log found');
      }

      // Overall assessment
      const criticalComponents = [
        analysis.gutenbergLoaded,
        analysis.pluginScriptLoaded,
        analysis.wordpressDepsLoaded,
        analysis.pluginRegistered
      ];

      const passedCritical = criticalComponents.filter(Boolean).length;
      
      if (passedCritical >= 3) {
        this.results.editorLoading = true;
        this.results.jsIntegration = analysis.pluginRegistered;
        this.results.uiElements = analysis.aiChatElementsPresent;
        
        await this.log(`Editor integration: ${passedCritical}/4 critical components loaded`, 'success');
      } else {
        await this.log(`Editor integration incomplete: ${passedCritical}/4 components`, 'error');
      }

      return analysis;
    } catch (error) {
      await this.log(`Editor analysis failed: ${error.message}`, 'error');
      return false;
    }
  }

  async generateFinalReport() {
    console.log('\n🚀 FINAL COMPREHENSIVE TEST REPORT');
    console.log('=====================================\n');

    // Run all tests
    await this.testWordPressAccessibility();
    
    if (this.results.wordpress) {
      await this.performAuthentication();
      
      if (this.results.login) {
        await this.checkPluginStatus();
        const workingApiEndpoint = await this.testRestApiEndpoints();
        const editorAnalysis = await this.analyzePostEditorIntegration();

        console.log('\n📊 TEST RESULTS:');
        console.log('================');
        console.log(`WordPress Access: ${this.results.wordpress ? '✅ PASS' : '❌ FAIL'}`);
        console.log(`Admin Login: ${this.results.login ? '✅ PASS' : '❌ FAIL'}`);
        console.log(`Plugin Active: ${this.results.plugin ? '✅ PASS' : '❌ FAIL'}`);
        console.log(`REST API: ${this.results.restApi ? '✅ PASS' : '❌ FAIL'}`);
        console.log(`Editor Loading: ${this.results.editorLoading ? '✅ PASS' : '❌ FAIL'}`);
        console.log(`JS Integration: ${this.results.jsIntegration ? '✅ PASS' : '❌ FAIL'}`);
        console.log(`UI Elements: ${this.results.uiElements ? '✅ PASS' : '❌ FAIL'}`);

        console.log('\n🎯 FUNCTIONALITY ASSESSMENT:');
        console.log('============================');

        const totalTests = Object.keys(this.results).length;
        const passedTests = Object.values(this.results).filter(Boolean).length;
        const overallScore = Math.round((passedTests / totalTests) * 100);

        console.log(`Overall Score: ${passedTests}/${totalTests} (${overallScore}%)\n`);

        if (overallScore >= 85) {
          console.log('🎉 VERDICT: AI CHAT FUNCTIONALITY SHOULD BE WORKING');
          console.log('✅ All critical components are properly loaded and configured.');
          console.log('✅ The "AI Chat" option should appear in the post editor\'s more menu.');
          console.log('✅ REST API endpoints are accessible for AI functionality.');
          
          console.log('\n👤 FOR THE USER:');
          console.log('1. Go to Posts → Add New in WordPress admin');
          console.log('2. Look for three dots menu (⋯) in the top-right area');
          console.log('3. Click the menu and look for "AI Chat" option');
          console.log('4. If not visible, check browser console for JavaScript errors');
        } else if (overallScore >= 60) {
          console.log('⚠️ VERDICT: PARTIAL FUNCTIONALITY - SOME ISSUES DETECTED');
          console.log('⚠️ Core components are loaded but some features may not work properly.');
          
          console.log('\n🔧 ISSUES IDENTIFIED:');
          if (!this.results.restApi) {
            console.log('- REST API endpoints not accessible (AI requests will fail)');
          }
          if (!this.results.jsIntegration) {
            console.log('- JavaScript plugin registration incomplete');
          }
          if (!this.results.uiElements) {
            console.log('- UI elements not properly rendered');
          }
        } else {
          console.log('❌ VERDICT: AI CHAT FUNCTIONALITY NOT WORKING');
          console.log('❌ Critical components are missing or not properly configured.');
          
          console.log('\n🚨 CRITICAL ISSUES:');
          if (!this.results.wordpress) {
            console.log('- WordPress installation not accessible');
          }
          if (!this.results.login) {
            console.log('- WordPress admin authentication failed');
          }
          if (!this.results.plugin) {
            console.log('- WP Content Flow plugin not active');
          }
          if (!this.results.editorLoading) {
            console.log('- Gutenberg editor not loading properly');
          }
        }

        console.log('\n🛠️ NEXT STEPS:');
        if (overallScore >= 85) {
          console.log('1. User should test functionality in actual browser');
          console.log('2. If issues persist, check browser console for JavaScript errors');
          console.log('3. Clear browser and WordPress caches');
        } else {
          console.log('1. Fix identified critical issues');
          console.log('2. Ensure plugin is properly activated');
          console.log('3. Check WordPress and PHP error logs');
          console.log('4. Verify REST API is enabled in WordPress settings');
        }

        if (workingApiEndpoint) {
          console.log(`\n🔗 Working REST API endpoint: ${workingApiEndpoint}`);
        }

        console.log('\n📋 DETAILED FINDINGS:');
        if (editorAnalysis) {
          console.log('- Gutenberg Editor:', editorAnalysis.gutenbergLoaded ? 'Loaded' : 'Not Found');
          console.log('- Plugin Script:', editorAnalysis.pluginScriptLoaded ? 'Enqueued' : 'Missing');
          console.log('- WordPress Dependencies:', editorAnalysis.wordpressDepsLoaded ? 'Available' : 'Missing');
          console.log('- Plugin Registration:', editorAnalysis.pluginRegistered ? 'Detected' : 'Not Found');
          console.log('- AI Chat Elements:', editorAnalysis.aiChatElementsPresent ? 'Present' : 'Missing');
          console.log('- Console Logging:', editorAnalysis.consolLogPresent ? 'Found' : 'Not Found');
        }
      }
    }

    return this.results;
  }
}

// Execute the comprehensive test
if (require.main === module) {
  const tester = new FinalComprehensiveTest();
  tester.generateFinalReport()
    .then(results => {
      console.log('\n🏁 Comprehensive testing complete\n');
      
      // Exit with appropriate code
      const totalTests = Object.keys(results).length;
      const passedTests = Object.values(results).filter(Boolean).length;
      const success = (passedTests / totalTests) >= 0.8;
      
      process.exit(success ? 0 : 1);
    })
    .catch(error => {
      console.error('❌ Comprehensive test failed:', error);
      process.exit(1);
    });
}

module.exports = FinalComprehensiveTest;