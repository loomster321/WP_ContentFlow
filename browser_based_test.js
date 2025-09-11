/**
 * Browser-based WordPress Integration Test
 * 
 * Uses puppeteer-like approach to test the actual user interface
 * and identify why the globe icon/AI Chat functionality isn't working.
 */

const http = require('http');
const { URL } = require('url');

class BrowserBasedTestRunner {
  constructor() {
    this.baseUrl = 'http://localhost:8080';
    this.adminCredentials = {
      username: 'admin',
      password: '!3cTXkh)9iDHhV5o*N'
    };
    this.sessionCookies = '';
    this.wpNonce = '';
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
          'Upgrade-Insecure-Requests': '1',
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
        
        // Capture cookies
        if (res.headers['set-cookie']) {
          const cookies = res.headers['set-cookie'].map(cookie => cookie.split(';')[0]).join('; ');
          this.sessionCookies = this.sessionCookies ? this.sessionCookies + '; ' + cookies : cookies;
        }

        res.on('data', chunk => data += chunk);
        res.on('end', () => {
          resolve({
            statusCode: res.statusCode,
            headers: res.headers,
            body: data,
            location: res.headers.location
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
      
      if (response.statusCode >= 300 && response.statusCode < 400 && response.location) {
        currentPath = response.location.startsWith('http') ? new URL(response.location).pathname : response.location;
        redirectCount++;
        await this.log(`Redirect ${redirectCount}: ${currentPath}`);
      } else {
        break;
      }
    }

    return response;
  }

  async performWordPressLogin() {
    await this.log('Attempting WordPress login with session handling...');
    
    try {
      // Get login page and extract nonce
      const loginPage = await this.makeRequest('/wp-login.php');
      
      if (loginPage.statusCode !== 200) {
        await this.log(`Login page not accessible: ${loginPage.statusCode}`, 'error');
        return false;
      }

      // Look for login nonce or other hidden fields
      const nonceMatch = loginPage.body.match(/name=["\']_wpnonce["\'][^>]*value=["\']([^"\']+)["\']/) ||
                        loginPage.body.match(/value=["\']([^"\']+)["\'][^>]*name=["\']_wpnonce["\']/) ||
                        loginPage.body.match(/name=["\']log["\'][^>]*value=["\']([^"\']*)["\']/) ||
                        loginPage.body.match(/wp-login\.php[?&]action=login/);

      // Prepare login data
      const loginData = new URLSearchParams({
        log: this.adminCredentials.username,
        pwd: this.adminCredentials.password,
        'wp-submit': 'Log In',
        redirect_to: '/wp-admin/',
        testcookie: '1'
      }).toString();

      await this.log('Submitting login form...');
      
      const loginResponse = await this.makeRequest('/wp-login.php', {
        method: 'POST',
        body: loginData,
        headers: {
          'Referer': `${this.baseUrl}/wp-login.php`,
          'Origin': this.baseUrl
        }
      });

      // Follow redirect after login
      if (loginResponse.statusCode === 302 && loginResponse.location) {
        await this.log('Login redirect detected, following...');
        const dashboardResponse = await this.followRedirects(loginResponse.location);
        
        if (dashboardResponse.body.includes('Dashboard') || dashboardResponse.body.includes('wp-admin-bar')) {
          await this.log('WordPress login successful!', 'success');
          return true;
        }
      }

      // Check if login was successful by looking for admin elements
      if (loginResponse.body.includes('Dashboard') || loginResponse.body.includes('wp-admin-bar')) {
        await this.log('WordPress login successful (direct)!', 'success');
        return true;
      } else {
        await this.log('Login may have failed - checking response...', 'warning');
        
        // Check for error messages
        if (loginResponse.body.includes('ERROR') || loginResponse.body.includes('Invalid')) {
          await this.log('Login credentials rejected', 'error');
        } else {
          await this.log('Unexpected login response', 'warning');
        }
        return false;
      }
    } catch (error) {
      await this.log(`Login attempt failed: ${error.message}`, 'error');
      return false;
    }
  }

  async checkPluginActivation() {
    await this.log('Checking plugin activation status...');
    
    try {
      const pluginsResponse = await this.followRedirects('/wp-admin/plugins.php');
      
      if (pluginsResponse.statusCode !== 200) {
        await this.log(`Cannot access plugins page: ${pluginsResponse.statusCode}`, 'error');
        return false;
      }

      // Look for our plugin
      const hasPlugin = pluginsResponse.body.includes('WordPress AI Content Flow') ||
                       pluginsResponse.body.includes('wp-content-flow');
      
      if (!hasPlugin) {
        await this.log('WP Content Flow plugin not found in plugins list', 'error');
        return false;
      }

      // Check if it's active (active plugins don't have "Activate" link)
      const pluginSection = pluginsResponse.body.match(/WordPress AI Content Flow[\s\S]*?<\/tr>/);
      
      if (pluginSection) {
        const isActive = !pluginSection[0].includes('>Activate<');
        const isInactive = pluginSection[0].includes('>Activate<');
        
        if (isActive) {
          await this.log('WP Content Flow plugin is ACTIVE', 'success');
          return true;
        } else {
          await this.log('WP Content Flow plugin is INACTIVE', 'error');
          
          // Try to activate it
          await this.log('Attempting to activate plugin...');
          return await this.activatePlugin();
        }
      } else {
        await this.log('Could not determine plugin status', 'warning');
        return false;
      }
    } catch (error) {
      await this.log(`Plugin check failed: ${error.message}`, 'error');
      return false;
    }
  }

  async activatePlugin() {
    try {
      // Look for activation link
      const pluginsResponse = await this.makeRequest('/wp-admin/plugins.php');
      const activateMatch = pluginsResponse.body.match(/href=["\']([^"\']*activate[^"\']*wp-content-flow[^"\']*)["\']/) ||
                           pluginsResponse.body.match(/href=["\']([^"\']*wp-content-flow[^"\']*activate[^"\']*)["\']/) ||
                           pluginsResponse.body.match(/plugins\.php\?action=activate&plugin=wp-content-flow[^"']*/);
      
      if (activateMatch) {
        const activateUrl = activateMatch[1] || activateMatch[0];
        await this.log(`Activating plugin: ${activateUrl}`);
        
        const activateResponse = await this.followRedirects(activateUrl);
        
        if (activateResponse.body.includes('Plugin activated') || 
            !activateResponse.body.includes('Activate')) {
          await this.log('Plugin activated successfully!', 'success');
          return true;
        } else {
          await this.log('Plugin activation may have failed', 'error');
          return false;
        }
      } else {
        await this.log('Could not find plugin activation link', 'error');
        return false;
      }
    } catch (error) {
      await this.log(`Plugin activation failed: ${error.message}`, 'error');
      return false;
    }
  }

  async analyzePostEditor() {
    await this.log('Analyzing post editor for plugin integration...');
    
    try {
      const editorResponse = await this.followRedirects('/wp-admin/post-new.php');
      
      if (editorResponse.statusCode !== 200) {
        await this.log(`Cannot access post editor: ${editorResponse.statusCode}`, 'error');
        return false;
      }

      const analysis = {
        hasGutenberg: false,
        hasPluginJS: false,
        hasWordPressDeps: false,
        pluginScripts: [],
        jsErrors: [],
        issues: []
      };

      // Check for Gutenberg
      if (editorResponse.body.includes('block-editor') || 
          editorResponse.body.includes('edit-post') ||
          editorResponse.body.includes('wp-block')) {
        analysis.hasGutenberg = true;
        await this.log('✓ Gutenberg block editor detected', 'success');
      } else {
        analysis.issues.push('Gutenberg block editor not detected');
        await this.log('✗ Gutenberg block editor not found', 'error');
      }

      // Check for our plugin's JavaScript
      const scriptMatches = editorResponse.body.match(/<script[^>]*src=["\']([^"\']*wp-content-flow[^"\']*)["\'][^>]*>/g);
      if (scriptMatches) {
        scriptMatches.forEach(match => {
          const srcMatch = match.match(/src=["\']([^"\']+)["\']/);
          if (srcMatch) {
            analysis.pluginScripts.push(srcMatch[1]);
          }
        });
        analysis.hasPluginJS = true;
        await this.log(`✓ Plugin JavaScript found: ${analysis.pluginScripts.join(', ')}`, 'success');
      } else {
        analysis.issues.push('Plugin JavaScript not enqueued');
        await this.log('✗ Plugin JavaScript files not found', 'error');
      }

      // Check for WordPress dependencies
      const wpDeps = ['wp-plugins', 'wp-edit-post', 'wp-components', 'wp-element', 'wp-blocks'];
      const foundDeps = [];
      
      for (const dep of wpDeps) {
        if (editorResponse.body.includes(dep)) {
          foundDeps.push(dep);
        }
      }
      
      if (foundDeps.length >= 4) {
        analysis.hasWordPressDeps = true;
        await this.log(`✓ WordPress dependencies loaded: ${foundDeps.join(', ')}`, 'success');
      } else {
        analysis.issues.push(`Missing WordPress dependencies: ${wpDeps.filter(d => !foundDeps.includes(d)).join(', ')}`);
        await this.log(`✗ Missing WordPress dependencies: ${wpDeps.filter(d => !foundDeps.includes(d)).join(', ')}`, 'error');
      }

      // Look for plugin-specific localization
      if (editorResponse.body.includes('wpContentFlow')) {
        await this.log('✓ Plugin localization data found', 'success');
      } else {
        analysis.issues.push('Plugin localization data missing');
        await this.log('✗ Plugin localization data not found', 'warning');
      }

      // Look for console error patterns in inline scripts
      const inlineScripts = editorResponse.body.match(/<script[^>]*>[\s\S]*?<\/script>/g) || [];
      for (const script of inlineScripts) {
        if (script.includes('console.error') || script.includes('throw')) {
          analysis.jsErrors.push('Potential JavaScript error in inline script');
        }
      }

      await this.log('\n📋 POST EDITOR ANALYSIS:');
      await this.log(`Gutenberg: ${analysis.hasGutenberg ? '✓' : '✗'}`);
      await this.log(`Plugin JS: ${analysis.hasPluginJS ? '✓' : '✗'}`);
      await this.log(`WP Dependencies: ${analysis.hasWordPressDeps ? '✓' : '✗'}`);
      
      if (analysis.issues.length > 0) {
        await this.log('\n⚠️ Issues found:');
        analysis.issues.forEach(issue => this.log(`  - ${issue}`, 'warning'));
      }

      return analysis;
    } catch (error) {
      await this.log(`Editor analysis failed: ${error.message}`, 'error');
      return false;
    }
  }

  async testRestApiDirectly() {
    await this.log('Testing REST API endpoints directly...');
    
    try {
      const endpoints = [
        '/wp-json/wp-content-flow/v1/status',
        '/wp-json/wp-content-flow/v1/workflows',
        '/wp-json/'
      ];

      for (const endpoint of endpoints) {
        const response = await this.makeRequest(endpoint);
        
        await this.log(`${endpoint}: ${response.statusCode} ${this.getStatusText(response.statusCode)}`);
        
        if (response.statusCode === 200) {
          try {
            const data = JSON.parse(response.body);
            if (endpoint.includes('status')) {
              await this.log(`  API Version: ${data.version || 'unknown'}`);
              await this.log(`  Endpoints: ${data.endpoints?.join(', ') || 'none'}`);
            }
          } catch (e) {
            // Not JSON, that's okay
          }
        }
      }
    } catch (error) {
      await this.log(`REST API test failed: ${error.message}`, 'error');
    }
  }

  getStatusText(statusCode) {
    const codes = {
      200: 'OK',
      404: 'Not Found',
      401: 'Unauthorized',
      403: 'Forbidden',
      500: 'Internal Server Error'
    };
    return codes[statusCode] || 'Unknown';
  }

  async runDiagnostics() {
    console.log('🚀 Starting Comprehensive WordPress AI Content Flow Diagnostics\n');
    
    const results = {
      login: await this.performWordPressLogin(),
      plugin: false,
      editor: false,
      api: false
    };

    if (results.login) {
      results.plugin = await this.checkPluginActivation();
      
      if (results.plugin) {
        results.editor = await this.analyzePostEditor();
        results.api = await this.testRestApiDirectly();
      }
    }

    console.log('\n📊 DIAGNOSTIC RESULTS:');
    console.log('========================');
    console.log(`WordPress Login: ${results.login ? '✅' : '❌'}`);
    console.log(`Plugin Active: ${results.plugin ? '✅' : '❌'}`);
    console.log(`Editor Integration: ${results.editor && results.editor.hasPluginJS ? '✅' : '❌'}`);
    console.log(`REST API: ${results.api ? '✅' : '❌'}`);

    if (results.login && results.plugin && results.editor) {
      console.log('\n🎯 KEY FINDINGS:');
      
      if (results.editor.hasGutenberg && results.editor.hasPluginJS && results.editor.hasWordPressDeps) {
        console.log('✅ All required components are loaded');
        console.log('🔍 The issue may be in the JavaScript runtime or plugin registration');
        console.log('💡 Recommendation: Check browser console for JavaScript errors when using the editor');
      } else {
        console.log('❌ Missing critical components:');
        if (!results.editor.hasGutenberg) console.log('  - Gutenberg editor not detected');
        if (!results.editor.hasPluginJS) console.log('  - Plugin JavaScript not enqueued');
        if (!results.editor.hasWordPressDeps) console.log('  - WordPress dependencies missing');
      }
    }

    return results;
  }
}

// Run diagnostics
if (require.main === module) {
  const runner = new BrowserBasedTestRunner();
  runner.runDiagnostics()
    .then(results => {
      console.log('\n🏁 Diagnostics complete');
    })
    .catch(error => {
      console.error('❌ Diagnostics failed:', error);
    });
}

module.exports = BrowserBasedTestRunner;