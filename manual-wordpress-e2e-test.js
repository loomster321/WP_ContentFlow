const { chromium } = require('playwright');

/**
 * Manual WordPress E2E Test Suite for WP Content Flow Plugin
 * 
 * This comprehensive test validates:
 * 1. WordPress admin access and plugin functionality
 * 2. Settings persistence (the critical user-reported bug)
 * 3. Gutenberg block integration 
 * 4. AI provider functionality
 * 5. Complete user workflows
 */

class WordPressE2ETester {
    constructor() {
        this.browser = null;
        this.page = null;
        this.context = null;
        this.baseURL = 'http://localhost:8080';
        this.adminUser = 'admin';
        this.adminPassword = '!3cTXkh)9iDHhV5o*N';
        this.testResults = {
            passed: 0,
            failed: 0,
            errors: [],
            details: []
        };
    }

    async setup() {
        console.log('🚀 Starting WordPress E2E Test Suite...');
        
        // Launch browser with WordPress-optimized settings
        this.browser = await chromium.launch({
            headless: false, // Show browser for debugging
            slowMo: 500,     // Slow down for WordPress
            args: [
                '--disable-web-security',
                '--disable-features=VizDisplayCompositor',
                '--no-sandbox'
            ]
        });

        this.context = await this.browser.newContext({
            viewport: { width: 1440, height: 900 },
            // WordPress admin needs larger viewport
            ignoreHTTPSErrors: true,
            // Accept self-signed certificates
        });

        this.page = await this.context.newPage();
        
        // Set longer timeouts for WordPress
        this.page.setDefaultTimeout(30000);
        this.page.setDefaultNavigationTimeout(45000);

        // Enable console logging
        this.page.on('console', msg => {
            if (msg.type() === 'error') {
                console.log('❌ Browser console error:', msg.text());
            }
        });

        // Track failed requests
        this.page.on('requestfailed', req => {
            console.log('❌ Request failed:', req.url());
        });
    }

    async teardown() {
        if (this.browser) {
            await this.browser.close();
        }
    }

    async logTest(testName, status, details = '') {
        const result = status === 'PASS' ? '✅' : '❌';
        console.log(`${result} ${testName}: ${status}${details ? ' - ' + details : ''}`);
        
        if (status === 'PASS') {
            this.testResults.passed++;
        } else {
            this.testResults.failed++;
            this.testResults.errors.push(`${testName}: ${details}`);
        }
        
        this.testResults.details.push({
            test: testName,
            status,
            details,
            timestamp: new Date().toISOString()
        });
    }

    async loginToWordPress() {
        try {
            console.log('🔑 Logging into WordPress admin...');
            
            await this.page.goto(`${this.baseURL}/wp-admin`);
            
            // Check if already logged in
            if (await this.page.locator('#wpadminbar').isVisible({ timeout: 5000 }).catch(() => false)) {
                await this.logTest('WordPress Login', 'PASS', 'Already logged in');
                return true;
            }

            // Wait for login form
            await this.page.waitForSelector('#loginform', { timeout: 10000 });
            
            // Fill login credentials
            await this.page.fill('#user_login', this.adminUser);
            await this.page.fill('#user_pass', this.adminPassword);
            
            // Submit login
            await this.page.click('#wp-submit');
            
            // Wait for admin bar to confirm successful login
            await this.page.waitForSelector('#wpadminbar', { timeout: 15000 });
            
            await this.logTest('WordPress Login', 'PASS', 'Successfully logged in');
            return true;
            
        } catch (error) {
            await this.logTest('WordPress Login', 'FAIL', `Login failed: ${error.message}`);
            return false;
        }
    }

    async testPluginAccess() {
        try {
            console.log('🔌 Testing plugin access...');
            
            // Navigate to plugins page
            await this.page.goto(`${this.baseURL}/wp-admin/plugins.php`);
            await this.page.waitForLoadState('domcontentloaded');
            
            // Look for WP Content Flow plugin
            const pluginFound = await this.page.locator('tr[data-slug="wp-content-flow"], tr:has-text("WP Content Flow"), tr:has-text("Content Flow")').isVisible({ timeout: 5000 }).catch(() => false);
            
            if (pluginFound) {
                await this.logTest('Plugin Visibility', 'PASS', 'WP Content Flow plugin found in plugins list');
            } else {
                await this.logTest('Plugin Visibility', 'FAIL', 'WP Content Flow plugin not found in plugins list');
            }
            
            // Check if plugin is active
            const activePlugin = await this.page.locator('tr[data-slug="wp-content-flow"] .activate, tr:has-text("WP Content Flow") .deactivate, tr:has-text("Content Flow") .deactivate').isVisible({ timeout: 5000 }).catch(() => false);
            
            if (activePlugin) {
                await this.logTest('Plugin Status', 'PASS', 'Plugin appears to be active');
            } else {
                await this.logTest('Plugin Status', 'FAIL', 'Plugin activation status unclear');
            }
            
            return pluginFound;
            
        } catch (error) {
            await this.logTest('Plugin Access', 'FAIL', `Plugin access test failed: ${error.message}`);
            return false;
        }
    }

    async testSettingsPageAccess() {
        try {
            console.log('⚙️ Testing settings page access...');
            
            // Try multiple possible settings page URLs
            const possibleUrls = [
                `${this.baseURL}/wp-admin/admin.php?page=wp-content-flow-settings`,
                `${this.baseURL}/wp-admin/admin.php?page=wp-content-flow`,
                `${this.baseURL}/wp-admin/admin.php?page=content-flow-settings`,
                `${this.baseURL}/wp-admin/options-general.php?page=wp-content-flow`
            ];

            let settingsPageFound = false;
            let workingUrl = '';

            for (const url of possibleUrls) {
                try {
                    await this.page.goto(url);
                    await this.page.waitForLoadState('domcontentloaded');
                    
                    // Check if we're on a settings page (not a 404 or error)
                    const hasSettingsContent = await this.page.locator('form, .wrap h1, #wpbody-content').isVisible({ timeout: 3000 }).catch(() => false);
                    const notFoundPage = await this.page.locator('.wp-die-message, h1:has-text("Not Found")').isVisible({ timeout: 1000 }).catch(() => false);
                    
                    if (hasSettingsContent && !notFoundPage) {
                        settingsPageFound = true;
                        workingUrl = url;
                        break;
                    }
                } catch (e) {
                    // Try next URL
                    continue;
                }
            }

            if (settingsPageFound) {
                await this.logTest('Settings Page Access', 'PASS', `Found settings page at: ${workingUrl}`);
                return workingUrl;
            } else {
                await this.logTest('Settings Page Access', 'FAIL', 'Could not locate plugin settings page');
                return null;
            }
            
        } catch (error) {
            await this.logTest('Settings Page Access', 'FAIL', `Settings page test failed: ${error.message}`);
            return null;
        }
    }

    async testSettingsPersistence(settingsUrl) {
        if (!settingsUrl) {
            await this.logTest('Settings Persistence', 'SKIP', 'No settings page URL available');
            return false;
        }

        try {
            console.log('💾 Testing settings persistence (the critical bug)...');
            
            await this.page.goto(settingsUrl);
            await this.page.waitForLoadState('domcontentloaded');
            
            // Look for default provider dropdown
            const dropdownSelectors = [
                'select[name="default_provider"]',
                'select[name="wp_content_flow_default_provider"]', 
                'select[name="wcf_default_provider"]',
                'select#default_provider',
                'select:has(option:text("OpenAI")):has(option:text("Anthropic"))',
                'select:has(option[value="openai"]):has(option[value="anthropic"])'
            ];

            let dropdown = null;
            let workingSelector = '';

            for (const selector of dropdownSelectors) {
                dropdown = await this.page.locator(selector).first();
                if (await dropdown.isVisible({ timeout: 2000 }).catch(() => false)) {
                    workingSelector = selector;
                    break;
                }
            }

            if (!dropdown || !await dropdown.isVisible().catch(() => false)) {
                await this.logTest('Settings Persistence', 'FAIL', 'Could not find default provider dropdown');
                return false;
            }

            await this.logTest('Dropdown Detection', 'PASS', `Found dropdown with selector: ${workingSelector}`);

            // Get all available options
            const options = await dropdown.locator('option').allTextContents();
            console.log('📋 Available provider options:', options);

            if (options.length < 2) {
                await this.logTest('Settings Persistence', 'FAIL', 'Dropdown has insufficient options for testing');
                return false;
            }

            // Record initial value
            const initialValue = await dropdown.inputValue();
            console.log('📝 Initial dropdown value:', initialValue);

            // Select different option (try second option)
            const secondOptionValue = await dropdown.locator('option').nth(1).getAttribute('value');
            console.log('🔄 Changing to option:', secondOptionValue);
            
            await dropdown.selectOption(secondOptionValue);
            
            // Verify the change was applied
            const changedValue = await dropdown.inputValue();
            if (changedValue !== secondOptionValue) {
                await this.logTest('Settings Persistence', 'FAIL', `Dropdown change not applied. Expected: ${secondOptionValue}, Got: ${changedValue}`);
                return false;
            }

            // Look for and click save button
            const saveButtonSelectors = [
                'input[type="submit"][value*="Save"]',
                'button[type="submit"]:has-text("Save")',
                'input[name="submit"]',
                '#submit',
                '.button-primary'
            ];

            let saveButton = null;
            for (const selector of saveButtonSelectors) {
                saveButton = this.page.locator(selector).first();
                if (await saveButton.isVisible({ timeout: 2000 }).catch(() => false)) {
                    break;
                }
            }

            if (!saveButton || !await saveButton.isVisible().catch(() => false)) {
                await this.logTest('Settings Persistence', 'FAIL', 'Could not find save button');
                return false;
            }

            // Click save and wait for response
            console.log('💾 Clicking save button...');
            await saveButton.click();
            
            // Wait for page to reload or show success message
            await this.page.waitForTimeout(3000);
            
            // Check for success message
            const successSelectors = [
                '.notice-success',
                '.updated',
                '.notice:has-text("saved")',
                '.notice:has-text("Settings")'
            ];

            let successFound = false;
            for (const selector of successSelectors) {
                if (await this.page.locator(selector).isVisible({ timeout: 2000 }).catch(() => false)) {
                    successFound = true;
                    break;
                }
            }

            if (successFound) {
                await this.logTest('Settings Save', 'PASS', 'Save success message appeared');
            } else {
                console.log('⚠️ No obvious success message found, checking persistence anyway...');
            }

            // Check if dropdown retained the changed value
            const finalValue = await dropdown.inputValue();
            console.log('🔍 Final dropdown value after save:', finalValue);

            if (finalValue === secondOptionValue) {
                await this.logTest('Settings Persistence', 'PASS', `Settings persisted correctly. Value: ${finalValue}`);
                return true;
            } else {
                await this.logTest('Settings Persistence', 'FAIL', `Settings not persisted! Expected: ${secondOptionValue}, Got: ${finalValue}`);
                return false;
            }
            
        } catch (error) {
            await this.logTest('Settings Persistence', 'FAIL', `Settings persistence test failed: ${error.message}`);
            return false;
        }
    }

    async testGutenbergBlockIntegration() {
        try {
            console.log('📝 Testing Gutenberg block integration...');
            
            // Navigate to create new post
            await this.page.goto(`${this.baseURL}/wp-admin/post-new.php`);
            await this.page.waitForLoadState('domcontentloaded');
            
            // Wait for Gutenberg editor to load
            await this.page.waitForSelector('.block-editor, .edit-post-visual-editor', { timeout: 15000 });
            
            // Look for the block inserter
            const blockInserter = this.page.locator('.block-editor-inserter__toggle, .edit-post-header-toolbar__inserter-toggle');
            
            if (await blockInserter.isVisible({ timeout: 5000 }).catch(() => false)) {
                await blockInserter.click();
                await this.page.waitForTimeout(2000);
                
                // Search for AI Content Flow blocks
                const searchSelectors = [
                    '.block-editor-inserter__search input',
                    '.components-search-control__input'
                ];

                let searchInput = null;
                for (const selector of searchSelectors) {
                    searchInput = this.page.locator(selector).first();
                    if (await searchInput.isVisible({ timeout: 2000 }).catch(() => false)) {
                        break;
                    }
                }

                if (searchInput && await searchInput.isVisible().catch(() => false)) {
                    await searchInput.fill('AI');
                    await this.page.waitForTimeout(1000);
                    
                    // Look for AI-related blocks
                    const aiBlockFound = await this.page.locator('.block-editor-block-types-list__item:has-text("AI"), .editor-block-list-item-title:has-text("AI")').isVisible({ timeout: 5000 }).catch(() => false);
                    
                    if (aiBlockFound) {
                        await this.logTest('Gutenberg Block Integration', 'PASS', 'AI-related blocks found in block inserter');
                    } else {
                        await this.logTest('Gutenberg Block Integration', 'FAIL', 'No AI blocks found in block inserter');
                    }
                } else {
                    await this.logTest('Gutenberg Block Integration', 'FAIL', 'Could not find block inserter search');
                }
            } else {
                await this.logTest('Gutenberg Block Integration', 'FAIL', 'Block inserter not found');
            }
            
        } catch (error) {
            await this.logTest('Gutenberg Block Integration', 'FAIL', `Block integration test failed: ${error.message}`);
        }
    }

    async testAPIKeySecurityIssue(settingsUrl) {
        if (!settingsUrl) {
            await this.logTest('API Key Security', 'SKIP', 'No settings page URL available');
            return;
        }

        try {
            console.log('🔒 Testing API key security issue...');
            
            await this.page.goto(settingsUrl);
            await this.page.waitForLoadState('domcontentloaded');
            
            // Look for API key fields
            const apiKeySelectors = [
                'input[name*="api_key"]',
                'input[name*="openai"]',
                'input[name*="anthropic"]',
                'input[name*="google"]',
                'input[type="password"]',
                'input[type="text"][name*="key"]'
            ];

            let foundPlaintextKeys = false;
            let secureFields = 0;
            let insecureFields = 0;

            for (const selector of apiKeySelectors) {
                const fields = await this.page.locator(selector).all();
                
                for (const field of fields) {
                    if (await field.isVisible({ timeout: 1000 }).catch(() => false)) {
                        const type = await field.getAttribute('type');
                        const name = await field.getAttribute('name') || 'unknown';
                        
                        if (type === 'password') {
                            secureFields++;
                            console.log(`✅ Secure field found: ${name} (type: password)`);
                        } else if (type === 'text' && name.includes('key')) {
                            insecureFields++;
                            foundPlaintextKeys = true;
                            console.log(`❌ Insecure field found: ${name} (type: text)`);
                        }
                    }
                }
            }

            if (foundPlaintextKeys) {
                await this.logTest('API Key Security', 'FAIL', `Found ${insecureFields} API keys in plain text fields`);
            } else if (secureFields > 0) {
                await this.logTest('API Key Security', 'PASS', `All ${secureFields} API key fields are properly secured`);
            } else {
                await this.logTest('API Key Security', 'INFO', 'No API key fields found to test');
            }
            
        } catch (error) {
            await this.logTest('API Key Security', 'FAIL', `API key security test failed: ${error.message}`);
        }
    }

    async testCompleteUserWorkflow() {
        try {
            console.log('🎯 Testing complete user workflow...');
            
            // This is a high-level workflow test
            let workflowSteps = 0;
            let completedSteps = 0;
            
            // Step 1: Admin access
            workflowSteps++;
            if (await this.page.locator('#wpadminbar').isVisible({ timeout: 5000 }).catch(() => false)) {
                completedSteps++;
                console.log('✅ Step 1: Admin access confirmed');
            }
            
            // Step 2: Plugin management access
            workflowSteps++;
            await this.page.goto(`${this.baseURL}/wp-admin/plugins.php`);
            if (await this.page.locator('tr:has-text("Content Flow"), tr[data-slug*="content-flow"]').isVisible({ timeout: 5000 }).catch(() => false)) {
                completedSteps++;
                console.log('✅ Step 2: Plugin management access confirmed');
            }
            
            // Step 3: Settings configuration
            workflowSteps++;
            const settingsUrl = await this.testSettingsPageAccess();
            if (settingsUrl) {
                completedSteps++;
                console.log('✅ Step 3: Settings configuration access confirmed');
            }
            
            // Step 4: Content editor access
            workflowSteps++;
            await this.page.goto(`${this.baseURL}/wp-admin/post-new.php`);
            if (await this.page.locator('.block-editor, .edit-post-visual-editor').isVisible({ timeout: 10000 }).catch(() => false)) {
                completedSteps++;
                console.log('✅ Step 4: Content editor access confirmed');
            }
            
            const workflowScore = Math.round((completedSteps / workflowSteps) * 100);
            
            if (workflowScore >= 75) {
                await this.logTest('Complete User Workflow', 'PASS', `${completedSteps}/${workflowSteps} workflow steps completed (${workflowScore}%)`);
            } else {
                await this.logTest('Complete User Workflow', 'FAIL', `Only ${completedSteps}/${workflowSteps} workflow steps completed (${workflowScore}%)`);
            }
            
        } catch (error) {
            await this.logTest('Complete User Workflow', 'FAIL', `Workflow test failed: ${error.message}`);
        }
    }

    async generateReport() {
        console.log('\n' + '='.repeat(80));
        console.log('📊 COMPREHENSIVE WORDPRESS E2E TEST RESULTS');
        console.log('='.repeat(80));
        
        console.log(`🏃 Total Tests: ${this.testResults.passed + this.testResults.failed}`);
        console.log(`✅ Passed: ${this.testResults.passed}`);
        console.log(`❌ Failed: ${this.testResults.failed}`);
        console.log(`📈 Success Rate: ${Math.round((this.testResults.passed / (this.testResults.passed + this.testResults.failed)) * 100)}%`);
        
        if (this.testResults.errors.length > 0) {
            console.log('\n🚨 CRITICAL ISSUES FOUND:');
            this.testResults.errors.forEach((error, index) => {
                console.log(`${index + 1}. ${error}`);
            });
        }
        
        console.log('\n📋 DETAILED TEST RESULTS:');
        this.testResults.details.forEach(detail => {
            const icon = detail.status === 'PASS' ? '✅' : detail.status === 'SKIP' ? '⏭️' : '❌';
            console.log(`${icon} ${detail.test}: ${detail.status}${detail.details ? ' - ' + detail.details : ''}`);
        });
        
        console.log('\n🎯 PRODUCTION READINESS ASSESSMENT:');
        
        const criticalTests = this.testResults.details.filter(d => 
            d.test.includes('Login') || 
            d.test.includes('Settings Persistence') || 
            d.test.includes('Plugin')
        );
        
        const passedCritical = criticalTests.filter(t => t.status === 'PASS').length;
        const totalCritical = criticalTests.length;
        
        if (passedCritical === totalCritical) {
            console.log('🟢 READY FOR PRODUCTION - All critical tests passing');
        } else if (passedCritical >= totalCritical * 0.75) {
            console.log('🟡 MOSTLY READY - Some minor issues to address');
        } else {
            console.log('🔴 NOT READY FOR PRODUCTION - Critical issues need fixing');
        }
        
        console.log('='.repeat(80));
        
        return this.testResults;
    }

    async runFullTestSuite() {
        await this.setup();
        
        try {
            // Core WordPress functionality
            const loginSuccess = await this.loginToWordPress();
            if (!loginSuccess) {
                console.log('❌ Cannot proceed with tests - login failed');
                return;
            }
            
            // Plugin-specific tests
            await this.testPluginAccess();
            const settingsUrl = await this.testSettingsPageAccess();
            await this.testSettingsPersistence(settingsUrl);
            await this.testAPIKeySecurityIssue(settingsUrl);
            
            // Content creation workflow
            await this.testGutenbergBlockIntegration();
            await this.testCompleteUserWorkflow();
            
        } catch (error) {
            console.log('💥 Test suite encountered fatal error:', error.message);
            await this.logTest('Test Suite', 'FAIL', `Fatal error: ${error.message}`);
        } finally {
            await this.generateReport();
            await this.teardown();
        }
    }
}

// Run the test suite
(async () => {
    const tester = new WordPressE2ETester();
    await tester.runFullTestSuite();
})().catch(console.error);