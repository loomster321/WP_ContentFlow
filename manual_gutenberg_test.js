/**
 * Manual Gutenberg Integration Test
 * 
 * This is a simplified test that can be run directly with Node.js to verify
 * the Gutenberg AI Chat Panel integration without requiring full Playwright setup.
 * 
 * Tests WordPress admin access, plugin loading, and JavaScript functionality.
 */

const http = require('http');
const https = require('https');
const { URL } = require('url');

class WordPressTestRunner {
  constructor() {
    this.baseUrl = 'http://localhost:8080';
    this.adminCredentials = {
      username: 'admin',
      password: '!3cTXkh)9iDHhV5o*N'
    };
    this.sessionCookies = '';
    this.results = [];
  }

  async log(message, type = 'info') {
    const timestamp = new Date().toISOString();
    const prefix = type === 'error' ? '❌' : type === 'success' ? '✅' : '🔍';
    console.log(`${prefix} [${timestamp}] ${message}`);
    
    this.results.push({
      timestamp,
      type,
      message
    });
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
          'User-Agent': 'WordPress-Test-Runner/1.0',
          'Cookie': this.sessionCookies,
          ...options.headers
        }
      };

      if (options.body) {
        requestOptions.headers['Content-Type'] = 'application/x-www-form-urlencoded';
        requestOptions.headers['Content-Length'] = Buffer.byteLength(options.body);
      }

      const req = http.request(requestOptions, (res) => {
        let data = '';
        
        // Capture set-cookie headers
        if (res.headers['set-cookie']) {
          this.sessionCookies = res.headers['set-cookie'].join('; ');
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

  async testWordPressAccess() {
    await this.log('Testing WordPress homepage access...');
    
    try {
      const response = await this.makeRequest('/');
      
      if (response.statusCode === 200 && response.body.includes('WordPress')) {
        await this.log('WordPress homepage accessible', 'success');
        return true;
      } else {
        await this.log(`Unexpected response: ${response.statusCode}`, 'error');
        return false;
      }
    } catch (error) {
      await this.log(`WordPress access failed: ${error.message}`, 'error');
      return false;
    }
  }

  async testAdminLogin() {
    await this.log('Testing WordPress admin login...');
    
    try {
      // Get login page
      const loginPage = await this.makeRequest('/wp-admin/');
      
      if (loginPage.statusCode !== 200) {
        await this.log(`Login page not accessible: ${loginPage.statusCode}`, 'error');
        return false;
      }

      // Check if already logged in
      if (loginPage.body.includes('Dashboard') && !loginPage.body.includes('wp-login.php')) {
        await this.log('Already logged into WordPress admin', 'success');
        return true;
      }

      // Extract login form data
      const loginFormMatch = loginPage.body.match(/wp-login\.php[^"]*"/);
      if (!loginFormMatch) {
        await this.log('Login form not found', 'error');
        return false;
      }

      // Perform login
      const loginData = `log=${encodeURIComponent(this.adminCredentials.username)}&pwd=${encodeURIComponent(this.adminCredentials.password)}&wp-submit=Log+In&testcookie=1`;
      
      const loginResponse = await this.makeRequest('/wp-login.php', {
        method: 'POST',
        body: loginData,
        headers: {
          'Referer': `${this.baseUrl}/wp-admin/`
        }
      });

      // Check if login was successful (should redirect to dashboard)
      if (loginResponse.statusCode === 302 && loginResponse.headers.location?.includes('wp-admin')) {
        await this.log('WordPress admin login successful', 'success');
        return true;
      } else {
        await this.log(`Login failed: ${loginResponse.statusCode}`, 'error');
        return false;
      }
    } catch (error) {
      await this.log(`Admin login failed: ${error.message}`, 'error');
      return false;
    }
  }

  async testPluginStatus() {
    await this.log('Testing plugin activation status...');
    
    try {
      const pluginsPage = await this.makeRequest('/wp-admin/plugins.php');
      
      if (pluginsPage.statusCode !== 200) {
        await this.log(`Plugins page not accessible: ${pluginsPage.statusCode}`, 'error');
        return false;
      }

      // Check if WP Content Flow plugin is listed and active
      const pluginActive = pluginsPage.body.includes('WordPress AI Content Flow') && 
                          !pluginsPage.body.includes('activate-plugin');
      
      if (pluginActive) {
        await this.log('WP Content Flow plugin is active', 'success');
        return true;
      } else {
        await this.log('WP Content Flow plugin not found or not active', 'error');
        
        // Try to find plugin in the list
        if (pluginsPage.body.includes('WordPress AI Content Flow')) {
          await this.log('Plugin found but appears inactive', 'error');
        } else {
          await this.log('Plugin not found in plugins list', 'error');
        }
        return false;
      }
    } catch (error) {
      await this.log(`Plugin status check failed: ${error.message}`, 'error');
      return false;
    }
  }

  async testPostEditorAccess() {
    await this.log('Testing post editor access...');
    
    try {
      const editorResponse = await this.makeRequest('/wp-admin/post-new.php');
      
      if (editorResponse.statusCode !== 200) {
        await this.log(`Post editor not accessible: ${editorResponse.statusCode}`, 'error');
        return false;
      }

      // Check for Gutenberg editor elements
      const hasGutenberg = editorResponse.body.includes('block-editor') || 
                          editorResponse.body.includes('edit-post');
      
      if (hasGutenberg) {
        await this.log('Gutenberg post editor loaded', 'success');
      } else {
        await this.log('Gutenberg editor not detected', 'error');
      }

      // Check for our plugin's JavaScript
      const hasPluginJS = editorResponse.body.includes('wp-content-flow') && 
                         editorResponse.body.includes('blocks.js');
      
      if (hasPluginJS) {
        await this.log('Plugin JavaScript file is enqueued', 'success');
      } else {
        await this.log('Plugin JavaScript not found in editor', 'error');
      }

      // Check for WordPress dependencies
      const hasWPDependencies = editorResponse.body.includes('wp-plugins') &&
                               editorResponse.body.includes('wp-edit-post') &&
                               editorResponse.body.includes('wp-components');
      
      if (hasWPDependencies) {
        await this.log('WordPress dependencies are loaded', 'success');
      } else {
        await this.log('WordPress dependencies missing', 'error');
      }

      return hasGutenberg && hasPluginJS && hasWPDependencies;
    } catch (error) {
      await this.log(`Post editor test failed: ${error.message}`, 'error');
      return false;
    }
  }

  async testRestApiEndpoints() {
    await this.log('Testing REST API endpoints...');
    
    try {
      // Test status endpoint (should be public)
      const statusResponse = await this.makeRequest('/wp-json/wp-content-flow/v1/status');
      
      if (statusResponse.statusCode === 200) {
        await this.log('REST API status endpoint accessible', 'success');
        
        try {
          const statusData = JSON.parse(statusResponse.body);
          await this.log(`API version: ${statusData.version}`, 'info');
          await this.log(`Available endpoints: ${statusData.endpoints?.join(', ')}`, 'info');
          
          if (statusData.ai_providers) {
            await this.log(`AI providers configured: ${Object.keys(statusData.ai_providers).join(', ')}`, 'info');
          }
        } catch (parseError) {
          await this.log('Status response not valid JSON', 'error');
        }
      } else if (statusResponse.statusCode === 404) {
        await this.log('REST API endpoint not found - plugin may not be properly activated', 'error');
        return false;
      } else {
        await this.log(`Status endpoint returned ${statusResponse.statusCode}`, 'error');
      }

      // Test workflows endpoint (requires authentication)
      const workflowsResponse = await this.makeRequest('/wp-json/wp-content-flow/v1/workflows');
      
      if (workflowsResponse.statusCode === 401) {
        await this.log('Workflows endpoint requires authentication (expected)', 'success');
      } else if (workflowsResponse.statusCode === 200) {
        await this.log('Workflows endpoint accessible', 'success');
      } else {
        await this.log(`Workflows endpoint returned ${workflowsResponse.statusCode}`, 'error');
      }

      return statusResponse.statusCode === 200;
    } catch (error) {
      await this.log(`REST API test failed: ${error.message}`, 'error');
      return false;
    }
  }

  async runAllTests() {
    console.log('🚀 Starting WordPress AI Content Flow Integration Tests\n');
    
    const testResults = {
      wordpressAccess: await this.testWordPressAccess(),
      adminLogin: await this.testAdminLogin(),
      pluginStatus: await this.testPluginStatus(),
      postEditor: await this.testPostEditorAccess(),
      restApi: await this.testRestApiEndpoints()
    };

    console.log('\n📊 TEST RESULTS SUMMARY:');
    console.log('================================');
    
    let passedTests = 0;
    let totalTests = 0;
    
    for (const [testName, result] of Object.entries(testResults)) {
      totalTests++;
      const status = result ? '✅ PASS' : '❌ FAIL';
      console.log(`${testName}: ${status}`);
      if (result) passedTests++;
    }
    
    console.log(`\nTotal: ${passedTests}/${totalTests} tests passed`);
    
    if (passedTests === totalTests) {
      console.log('🎉 All tests passed! Gutenberg integration should be working.');
    } else {
      console.log('⚠️  Some tests failed. Please check the detailed logs above.');
    }

    // Generate recommendations
    console.log('\n🔧 RECOMMENDATIONS:');
    if (!testResults.wordpressAccess) {
      console.log('- Check if WordPress Docker container is running');
    }
    if (!testResults.adminLogin) {
      console.log('- Verify admin credentials are correct');
    }
    if (!testResults.pluginStatus) {
      console.log('- Activate the WP Content Flow plugin in WordPress admin');
    }
    if (!testResults.postEditor) {
      console.log('- Check if plugin JavaScript files are properly enqueued');
      console.log('- Verify WordPress dependencies are loading correctly');
    }
    if (!testResults.restApi) {
      console.log('- Check if REST API is enabled in WordPress');
      console.log('- Verify plugin registers REST endpoints correctly');
    }

    return testResults;
  }
}

// Run the tests
if (require.main === module) {
  const runner = new WordPressTestRunner();
  runner.runAllTests()
    .then(results => {
      process.exit(Object.values(results).every(r => r) ? 0 : 1);
    })
    .catch(error => {
      console.error('❌ Test runner failed:', error);
      process.exit(1);
    });
}

module.exports = WordPressTestRunner;