const axios = require('axios');
const cheerio = require('cheerio');

/**
 * Manual Settings Save Test using Node.js
 * 
 * This script will directly test the WordPress admin settings save functionality
 */

const WORDPRESS_URL = 'http://localhost:8080';
const USERNAME = 'admin';
const PASSWORD = '!3cTXkh)9iDHhV5o*N';

// Create axios instance with cookie jar simulation
const axiosInstance = axios.create({
    baseURL: WORDPRESS_URL,
    withCredentials: true,
    timeout: 10000,
    maxRedirects: 5
});

let cookies = '';

async function testSettingsSave() {
    try {
        console.log('🚀 Starting manual settings save test...');
        
        // Step 1: Access login page
        console.log('📋 Step 1: Accessing login page...');
        const loginPageResponse = await axiosInstance.get('/wp-admin/');
        
        if (loginPageResponse.status !== 200) {
            throw new Error(`Failed to access login page: ${loginPageResponse.status}`);
        }
        console.log('✅ Successfully accessed login page');
        
        // Extract cookies from login page
        const setCookieHeader = loginPageResponse.headers['set-cookie'];
        if (setCookieHeader) {
            cookies = setCookieHeader.map(cookie => cookie.split(';')[0]).join('; ');
        }
        
        // Step 2: Login
        console.log('📋 Step 2: Logging in...');
        const loginData = new URLSearchParams({
            'log': USERNAME,
            'pwd': PASSWORD,
            'wp-submit': 'Log In',
            'redirect_to': WORDPRESS_URL + '/wp-admin/',
            'testcookie': '1'
        });
        
        const loginResponse = await axiosInstance.post('/wp-login.php', loginData, {
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Cookie': cookies
            }
        });
        
        // Update cookies after login
        const loginSetCookieHeader = loginResponse.headers['set-cookie'];
        if (loginSetCookieHeader) {
            cookies += '; ' + loginSetCookieHeader.map(cookie => cookie.split(';')[0]).join('; ');
        }
        
        console.log('✅ Successfully logged in');
        
        // Step 3: Access settings page
        console.log('📋 Step 3: Accessing settings page...');
        const settingsResponse = await axiosInstance.get('/wp-admin/admin.php?page=wp-content-flow-settings', {
            headers: {
                'Cookie': cookies
            }
        });
        
        if (settingsResponse.status !== 200) {
            throw new Error(`Failed to access settings page: ${settingsResponse.status}`);
        }
        console.log('✅ Successfully accessed settings page');
        
        // Step 4: Parse the form to get nonce and other hidden fields
        console.log('📋 Step 4: Parsing form data...');
        const $ = cheerio.load(settingsResponse.data);
        
        const nonceField = $('input[name="_wpnonce"]');
        const optionPageField = $('input[name="option_page"]');
        const formAction = $('form').attr('action');
        
        if (nonceField.length === 0) {
            throw new Error('Nonce field not found in form');
        }
        
        if (optionPageField.length === 0) {
            throw new Error('Option page field not found in form');
        }
        
        const nonce = nonceField.val();
        const optionPage = optionPageField.val();
        
        console.log(`✅ Found nonce: ${nonce.substring(0, 10)}...`);
        console.log(`✅ Found option_page: ${optionPage}`);
        console.log(`✅ Form action: ${formAction}`);
        
        // Step 5: Submit the settings form
        console.log('📋 Step 5: Submitting settings form...');
        
        const formData = new URLSearchParams({
            '_wpnonce': nonce,
            'option_page': optionPage,
            'wp_content_flow_settings[openai_api_key]': process.env.OPENAI_API_KEY || 'test-key-placeholder',
            'wp_content_flow_settings[anthropic_api_key]': process.env.ANTHROPIC_API_KEY || 'test-key-placeholder',
            'wp_content_flow_settings[default_ai_provider]': 'anthropic',
            'wp_content_flow_settings[cache_enabled]': '1',
            'wp_content_flow_settings[requests_per_minute]': '15',
            'submit': 'Save Settings'
        });
        
        const submitResponse = await axiosInstance.post(formAction || '/wp-admin/admin.php?page=wp-content-flow-settings', formData, {
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Cookie': cookies
            },
            maxRedirects: 0,
            validateStatus: function (status) {
                return status >= 200 && status < 400; // Accept redirects
            }
        });
        
        console.log(`📊 Submit response status: ${submitResponse.status}`);
        
        if (submitResponse.status >= 300 && submitResponse.status < 400) {
            console.log('✅ Form submission resulted in redirect (likely success)');
            console.log(`📊 Redirect location: ${submitResponse.headers.location}`);
        }
        
        // Check response content for errors
        if (submitResponse.data.includes('not in the allowed options list')) {
            console.log('❌ Found "not in the allowed options list" error');
            console.log('🔍 This indicates the WordPress Settings API issue');
        } else if (submitResponse.data.includes('Settings saved') || submitResponse.data.includes('settings-updated')) {
            console.log('✅ Found success indicators in response');
        }
        
        // Step 6: Check if settings were actually saved
        console.log('📋 Step 6: Verifying settings were saved...');
        
        const verifyResponse = await axiosInstance.get('/wp-admin/admin.php?page=wp-content-flow-settings', {
            headers: {
                'Cookie': cookies
            }
        });
        
        if (verifyResponse.status !== 200) {
            throw new Error(`Failed to verify settings: ${verifyResponse.status}`);
        }
        
        const verifyPage = cheerio.load(verifyResponse.data);
        const configSection = verifyPage('.wp-content-flow-info');
        
        if (configSection.length > 0) {
            const configText = configSection.text();
            
            if (configText.includes('Configured ✓')) {
                console.log('✅ Settings appear to be saved - API keys show as configured');
            } else {
                console.log('❌ Settings may not be saved - no "Configured" status found');
            }
            
            if (configText.includes('anthropic')) {
                console.log('✅ Default provider appears to be saved correctly');
            } else {
                console.log('❌ Default provider may not be saved correctly');
            }
        } else {
            console.log('⚠️  Could not find Current Configuration section');
        }
        
        // Check form field values
        const openaiField = verifyPage('input[name="wp_content_flow_settings[openai_api_key]"]');
        const anthropicField = verifyPage('input[name="wp_content_flow_settings[anthropic_api_key]"]');
        const providerSelect = verifyPage('select[name="wp_content_flow_settings[default_ai_provider]"]');
        
        console.log('📊 Field values after submission:');
        console.log(`   OpenAI field: ${openaiField.val() ? 'Has value' : 'Empty'}`);
        console.log(`   Anthropic field: ${anthropicField.val() ? 'Has value' : 'Empty'}`);
        console.log(`   Provider select: ${providerSelect.val()}`);
        
        console.log('\n🏁 Manual test completed!');
        
    } catch (error) {
        console.error('❌ Test failed:', error.message);
        if (error.response) {
            console.error('Response status:', error.response.status);
            console.error('Response headers:', error.response.headers);
            if (error.response.data) {
                console.error('Response data (first 500 chars):', error.response.data.substring(0, 500));
            }
        }
        process.exit(1);
    }
}

// Install cheerio if not available, then run test
testSettingsSave();