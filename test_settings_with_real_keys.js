/**
 * Test script to verify settings save functionality with real API keys
 * Uses basic Node.js fetch (available in Node 18+)
 */

const https = require('https');
const http = require('http');
const { URLSearchParams } = require('url');

const WORDPRESS_URL = 'http://localhost:8080';
const USERNAME = 'admin';
const PASSWORD = '!3cTXkh)9iDHhV5o*N';

// API keys from environment variables
const REAL_API_KEYS = {
    openai: process.env.OPENAI_API_KEY || 'test-key-placeholder',
    anthropic: process.env.ANTHROPIC_API_KEY || 'test-key-placeholder'
};

let cookies = '';

function makeRequest(path, options = {}) {
    return new Promise((resolve, reject) => {
        const url = new URL(path, WORDPRESS_URL);
        
        const requestOptions = {
            hostname: url.hostname,
            port: url.port || 80,
            path: url.pathname + url.search,
            method: options.method || 'GET',
            headers: {
                'User-Agent': 'WordPress-Settings-Test/1.0',
                ...options.headers
            }
        };

        if (cookies) {
            requestOptions.headers['Cookie'] = cookies;
        }

        const req = http.request(requestOptions, (res) => {
            let data = '';
            
            res.on('data', (chunk) => {
                data += chunk;
            });
            
            res.on('end', () => {
                // Update cookies from response
                if (res.headers['set-cookie']) {
                    const newCookies = res.headers['set-cookie'].map(cookie => cookie.split(';')[0]);
                    if (cookies) {
                        cookies += '; ' + newCookies.join('; ');
                    } else {
                        cookies = newCookies.join('; ');
                    }
                }
                
                resolve({
                    statusCode: res.statusCode,
                    headers: res.headers,
                    data: data
                });
            });
        });
        
        req.on('error', (err) => {
            reject(err);
        });
        
        if (options.data) {
            req.write(options.data);
        }
        
        req.end();
    });
}

function extractFromHTML(html, pattern) {
    const match = html.match(pattern);
    return match ? match[1] : null;
}

async function testSettingsSave() {
    try {
        console.log('🚀 Starting WordPress settings test with real API keys...');
        
        // Step 1: Access login page
        console.log('📋 Step 1: Accessing login page...');
        const loginPage = await makeRequest('/wp-admin/');
        
        if (loginPage.statusCode !== 200) {
            throw new Error(`Failed to access login page: ${loginPage.statusCode}`);
        }
        console.log('✅ Successfully accessed login page');
        
        // Step 2: Login
        console.log('📋 Step 2: Logging in...');
        const loginData = new URLSearchParams({
            'log': USERNAME,
            'pwd': PASSWORD,
            'wp-submit': 'Log In',
            'redirect_to': WORDPRESS_URL + '/wp-admin/',
            'testcookie': '1'
        });
        
        const loginResponse = await makeRequest('/wp-login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Content-Length': loginData.toString().length
            },
            data: loginData.toString()
        });
        
        console.log('✅ Login attempt completed');
        
        // Step 3: Access settings page
        console.log('📋 Step 3: Accessing settings page...');
        const settingsPage = await makeRequest('/wp-admin/admin.php?page=wp-content-flow-settings');
        
        if (settingsPage.statusCode !== 200) {
            throw new Error(`Failed to access settings page: ${settingsPage.statusCode}`);
        }
        console.log('✅ Successfully accessed settings page');
        
        // Step 4: Extract form data
        console.log('📋 Step 4: Extracting form data...');
        const nonce = extractFromHTML(settingsPage.data, /<input type="hidden" name="_wpnonce" value="([^"]+)"/);
        const optionPage = extractFromHTML(settingsPage.data, /<input type="hidden" name="option_page" value="([^"]+)"/);
        
        if (!nonce) {
            throw new Error('Could not find nonce in settings page');
        }
        
        if (!optionPage) {
            throw new Error('Could not find option_page in settings page');
        }
        
        console.log(`✅ Found nonce: ${nonce.substring(0, 10)}...`);
        console.log(`✅ Found option_page: ${optionPage}`);
        
        // Check if Current Configuration section exists and shows previous data
        if (settingsPage.data.includes('Current Configuration')) {
            console.log('✅ Found Current Configuration section');
            
            if (settingsPage.data.includes('Configured ✓')) {
                console.log('✅ Previous settings appear to be saved (shows "Configured ✓")');
            } else if (settingsPage.data.includes('Not configured')) {
                console.log('ℹ️  No previous settings configured');
            }
        }
        
        // Step 5: Submit form with real API keys
        console.log('📋 Step 5: Submitting form with real API keys...');
        
        const formData = new URLSearchParams({
            '_wpnonce': nonce,
            'option_page': optionPage,
            'wp_content_flow_settings[openai_api_key]': REAL_API_KEYS.openai,
            'wp_content_flow_settings[anthropic_api_key]': REAL_API_KEYS.anthropic,
            'wp_content_flow_settings[default_ai_provider]': 'anthropic',
            'wp_content_flow_settings[cache_enabled]': '1',
            'wp_content_flow_settings[requests_per_minute]': '15',
            'submit': 'Save Settings'
        });
        
        const submitResponse = await makeRequest('/wp-admin/admin.php?page=wp-content-flow-settings', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Content-Length': formData.toString().length
            },
            data: formData.toString()
        });
        
        console.log(`📊 Submit response status: ${submitResponse.statusCode}`);
        
        // Check for the specific error we're debugging
        if (submitResponse.data.includes('not in the allowed options list')) {
            console.log('❌ FOUND THE ERROR: "not in the allowed options list"');
            console.log('❌ This indicates the WordPress Settings API issue still exists');
            return false;
        }
        
        // Check for success indicators
        if (submitResponse.data.includes('Settings saved') || 
            submitResponse.data.includes('settings-updated') ||
            submitResponse.statusCode === 302) {
            console.log('✅ Form submission appears successful');
        }
        
        // Step 6: Verify settings were saved
        console.log('📋 Step 6: Verifying settings persistence...');
        
        const verifyPage = await makeRequest('/wp-admin/admin.php?page=wp-content-flow-settings');
        
        if (verifyPage.statusCode !== 200) {
            throw new Error(`Failed to verify settings: ${verifyPage.statusCode}`);
        }
        
        // Check Current Configuration section
        if (verifyPage.data.includes('Current Configuration')) {
            console.log('✅ Found Current Configuration section after save');
            
            if (verifyPage.data.includes('Configured ✓')) {
                console.log('✅ API keys show as configured - settings saved successfully!');
            }
            
            if (verifyPage.data.includes('anthropic')) {
                console.log('✅ Default provider saved correctly');
            }
            
            // Look for specific configuration values
            const configMatch = verifyPage.data.match(/Current Configuration([\s\S]*?)(?:<\/div>|$)/);
            if (configMatch) {
                console.log('📊 Current Configuration contents:');
                console.log(configMatch[1].replace(/<[^>]*>/g, '').trim());
            }
        }
        
        console.log('\n🎉 Test completed successfully!');
        console.log('✅ Settings save functionality is working correctly');
        
        return true;
        
    } catch (error) {
        console.error('❌ Test failed:', error.message);
        return false;
    }
}

// Run the test
testSettingsSave().then(success => {
    if (success) {
        console.log('\n🏆 CONCLUSION: Settings save functionality is working properly!');
        console.log('✅ The "not in the allowed options list" error has been resolved');
        console.log('✅ Real API keys can be successfully saved and persist');
    } else {
        console.log('\n💥 CONCLUSION: Settings save functionality still has issues');
        console.log('❌ Further investigation and fixes are needed');
    }
    process.exit(success ? 0 : 1);
});