/**
 * WordPress Content Flow Plugin Settings API Fix Verification Test
 * 
 * This test verifies that the Settings API fix properly handles form submissions
 * and that settings persist correctly in the database.
 */

const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

class WordPressSettingsTest {
    constructor() {
        this.browser = null;
        this.page = null;
        this.baseUrl = 'http://localhost:8080';
        this.username = 'admin';
        this.password = '!3cTXkh)9iDHhV5o*N';
        this.settingsUrl = `${this.baseUrl}/wp-admin/admin.php?page=wp-content-flow-settings`;
        this.screenshotsDir = path.join(__dirname, 'tmp', 'settings_test_screenshots');
        this.testResults = {
            testStartTime: new Date().toISOString(),
            steps: [],
            success: false,
            errors: [],
            networkRequests: [],
            consoleMessages: []
        };
    }

    async init() {
        // Ensure screenshots directory exists
        if (!fs.existsSync(this.screenshotsDir)) {
            fs.mkdirSync(this.screenshotsDir, { recursive: true });
        }

        // Launch browser with detailed options
        this.browser = await puppeteer.launch({
            headless: false, // Run in visible mode for monitoring
            slowMo: 250, // Slow down actions for better visibility
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-web-security',
                '--disable-features=VizDisplayCompositor'
            ]
        });

        this.page = await this.browser.newPage();

        // Set viewport
        await this.page.setViewport({ width: 1280, height: 720 });

        // Monitor network requests
        this.page.on('request', (request) => {
            this.testResults.networkRequests.push({
                timestamp: new Date().toISOString(),
                method: request.method(),
                url: request.url(),
                postData: request.postData()
            });
            console.log(`[NETWORK] ${request.method()} ${request.url()}`);
        });

        // Monitor console messages
        this.page.on('console', (msg) => {
            const message = {
                timestamp: new Date().toISOString(),
                type: msg.type(),
                text: msg.text()
            };
            this.testResults.consoleMessages.push(message);
            console.log(`[CONSOLE] ${msg.type().toUpperCase()}: ${msg.text()}`);
        });

        // Monitor page errors
        this.page.on('pageerror', (error) => {
            this.testResults.errors.push({
                timestamp: new Date().toISOString(),
                type: 'pageerror',
                message: error.message,
                stack: error.stack
            });
            console.error(`[PAGE ERROR] ${error.message}`);
        });

        console.log('Browser initialized for Settings API fix testing');
    }

    async logStep(description, success = true, data = null) {
        const step = {
            timestamp: new Date().toISOString(),
            description,
            success,
            data
        };
        this.testResults.steps.push(step);
        console.log(`[STEP] ${success ? '✓' : '✗'} ${description}`);
        if (data) {
            console.log(`[DATA] ${JSON.stringify(data, null, 2)}`);
        }
    }

    async takeScreenshot(name) {
        const filename = `${Date.now()}_${name}.png`;
        const filepath = path.join(this.screenshotsDir, filename);
        await this.page.screenshot({ path: filepath, fullPage: true });
        console.log(`[SCREENSHOT] Saved: ${filepath}`);
        return filepath;
    }

    async loginToWordPress() {
        try {
            await this.page.goto(`${this.baseUrl}/wp-admin`, { waitUntil: 'networkidle2' });
            
            // Check if already logged in
            const currentUrl = this.page.url();
            if (currentUrl.includes('wp-admin') && !currentUrl.includes('wp-login.php')) {
                await this.logStep('Already logged in to WordPress admin');
                return true;
            }

            // Login
            await this.page.type('#user_login', this.username);
            await this.page.type('#user_pass', this.password);
            
            await this.takeScreenshot('01_login_form');
            
            await this.page.click('#wp-submit');
            await this.page.waitForNavigation({ waitUntil: 'networkidle2' });
            
            await this.takeScreenshot('02_after_login');
            
            const isLoggedIn = await this.page.$('.wp-admin') !== null;
            await this.logStep('Login to WordPress admin', isLoggedIn);
            
            return isLoggedIn;
        } catch (error) {
            await this.logStep('Login to WordPress admin', false, { error: error.message });
            throw error;
        }
    }

    async navigateToSettingsPage() {
        try {
            await this.page.goto(this.settingsUrl, { waitUntil: 'networkidle2' });
            
            // Wait for the settings form to be present
            await this.page.waitForSelector('#wp-content-flow-settings-form', { timeout: 10000 });
            
            await this.takeScreenshot('03_settings_page_loaded');
            
            // Get current settings values for comparison
            const currentSettings = await this.page.evaluate(() => {
                const form = document.getElementById('wp-content-flow-settings-form');
                if (!form) return null;
                
                return {
                    defaultProvider: document.querySelector('select[name*="default_ai_provider"]')?.value || null,
                    cacheEnabled: document.querySelector('input[name*="cache_enabled"]')?.checked || false,
                    formAction: form.getAttribute('action'),
                    optionPage: document.querySelector('input[name="option_page"]')?.value || null,
                    nonce: document.querySelector('input[name="_wpnonce"]')?.value || null
                };
            });
            
            await this.logStep('Navigate to settings page', true, currentSettings);
            return currentSettings;
        } catch (error) {
            await this.logStep('Navigate to settings page', false, { error: error.message });
            throw error;
        }
    }

    async verifyFormStructure() {
        try {
            const formStructure = await this.page.evaluate(() => {
                const form = document.getElementById('wp-content-flow-settings-form');
                if (!form) return { error: 'Form not found' };
                
                return {
                    action: form.getAttribute('action'),
                    method: form.getAttribute('method'),
                    hasOptionPage: !!document.querySelector('input[name="option_page"]'),
                    hasNonce: !!document.querySelector('input[name="_wpnonce"]'),
                    hasSettingsFields: !!document.querySelector('input[name="_wp_http_referer"]'),
                    providerDropdown: !!document.querySelector('select[name*="default_ai_provider"]'),
                    cacheCheckbox: !!document.querySelector('input[name*="cache_enabled"]'),
                    submitButton: !!document.querySelector('#wp-content-flow-submit-btn')
                };
            });
            
            const isValid = formStructure.action === 'options.php' && 
                           formStructure.method === 'post' &&
                           formStructure.hasOptionPage &&
                           formStructure.hasNonce;
            
            await this.logStep('Verify form structure for Settings API compliance', isValid, formStructure);
            return formStructure;
        } catch (error) {
            await this.logStep('Verify form structure for Settings API compliance', false, { error: error.message });
            throw error;
        }
    }

    async changeSettingsValues() {
        try {
            // Get initial values
            const initialValues = await this.page.evaluate(() => {
                return {
                    provider: document.querySelector('select[name*="default_ai_provider"]')?.value,
                    cache: document.querySelector('input[name*="cache_enabled"]')?.checked
                };
            });
            
            // Change default provider
            const providerOptions = ['openai', 'anthropic', 'google'];
            const currentProvider = initialValues.provider;
            const newProvider = providerOptions.find(p => p !== currentProvider) || 'anthropic';
            
            await this.page.select('select[name*="default_ai_provider"]', newProvider);
            
            // Toggle cache setting
            const cacheCheckbox = await this.page.$('input[name*="cache_enabled"]');
            if (cacheCheckbox) {
                await cacheCheckbox.click();
            }
            
            // Add some test API keys to ensure form validation passes
            await this.page.type('input[name*="openai_api_key"]', 'sk-test123456789');
            await this.page.type('input[name*="anthropic_api_key"]', 'sk-ant-test123456789');
            
            await this.takeScreenshot('04_settings_changed');
            
            const newValues = await this.page.evaluate(() => {
                return {
                    provider: document.querySelector('select[name*="default_ai_provider"]')?.value,
                    cache: document.querySelector('input[name*="cache_enabled"]')?.checked
                };
            });
            
            await this.logStep('Change settings values', true, {
                initial: initialValues,
                changed: newValues
            });
            
            return { initial: initialValues, changed: newValues };
        } catch (error) {
            await this.logStep('Change settings values', false, { error: error.message });
            throw error;
        }
    }

    async submitForm() {
        try {
            // Clear previous network requests for this specific test
            const previousRequestCount = this.testResults.networkRequests.length;
            
            // Click submit button
            await this.page.click('#wp-content-flow-submit-btn');
            
            // Wait for navigation or form submission completion
            await this.page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 15000 });
            
            await this.takeScreenshot('05_after_form_submission');
            
            // Check for success indicators
            const submissionResult = await this.page.evaluate(() => {
                const successMessage = document.querySelector('.notice-success, .updated');
                const errorMessage = document.querySelector('.notice-error, .error');
                const currentUrl = window.location.href;
                
                return {
                    url: currentUrl,
                    hasSuccessMessage: !!successMessage,
                    successText: successMessage?.textContent?.trim() || null,
                    hasErrorMessage: !!errorMessage,
                    errorText: errorMessage?.textContent?.trim() || null,
                    isOnSettingsPage: currentUrl.includes('wp-content-flow-settings')
                };
            });
            
            // Analyze network requests during submission
            const submissionRequests = this.testResults.networkRequests.slice(previousRequestCount);
            const optionsPhpRequest = submissionRequests.find(req => 
                req.url.includes('options.php') && req.method === 'POST'
            );
            
            const success = submissionResult.hasSuccessMessage && !!optionsPhpRequest;
            
            await this.logStep('Submit settings form', success, {
                submission: submissionResult,
                optionsPhpRequest: optionsPhpRequest ? {
                    url: optionsPhpRequest.url,
                    method: optionsPhpRequest.method,
                    hasPostData: !!optionsPhpRequest.postData
                } : null,
                totalRequestsDuringSubmission: submissionRequests.length
            });
            
            return { submissionResult, optionsPhpRequest };
        } catch (error) {
            await this.logStep('Submit settings form', false, { error: error.message });
            throw error;
        }
    }

    async verifyPersistence() {
        try {
            // Reload the page to verify settings persistence
            await this.page.reload({ waitUntil: 'networkidle2' });
            await this.page.waitForSelector('#wp-content-flow-settings-form', { timeout: 10000 });
            
            await this.takeScreenshot('06_after_page_reload');
            
            // Get settings values after reload
            const persistedValues = await this.page.evaluate(() => {
                return {
                    provider: document.querySelector('select[name*="default_ai_provider"]')?.value,
                    cache: document.querySelector('input[name*="cache_enabled"]')?.checked,
                    openaiKey: document.querySelector('input[name*="openai_api_key"]')?.value,
                    anthropicKey: document.querySelector('input[name*="anthropic_api_key"]')?.value
                };
            });
            
            // Check current configuration section
            const configDisplay = await this.page.evaluate(() => {
                const configSection = document.querySelector('.wp-content-flow-info');
                return configSection ? configSection.textContent : null;
            });
            
            await this.logStep('Verify settings persistence after page reload', true, {
                persistedValues,
                configDisplay: configDisplay?.substring(0, 200) + '...' // Truncate for readability
            });
            
            return persistedValues;
        } catch (error) {
            await this.logStep('Verify settings persistence after page reload', false, { error: error.message });
            throw error;
        }
    }

    async generateTestReport() {
        const report = {
            ...this.testResults,
            testEndTime: new Date().toISOString(),
            duration: new Date() - new Date(this.testResults.testStartTime),
            summary: {
                totalSteps: this.testResults.steps.length,
                successfulSteps: this.testResults.steps.filter(s => s.success).length,
                failedSteps: this.testResults.steps.filter(s => !s.success).length,
                totalNetworkRequests: this.testResults.networkRequests.length,
                totalConsoleMessages: this.testResults.consoleMessages.length,
                totalErrors: this.testResults.errors.length
            },
            criticalChecks: {
                formUsesOptionsPhp: this.testResults.steps.some(s => 
                    s.description.includes('form structure') && 
                    s.data?.action === 'options.php'
                ),
                settingsApiFieldsPresent: this.testResults.steps.some(s => 
                    s.description.includes('form structure') && 
                    s.data?.hasOptionPage && s.data?.hasNonce
                ),
                successfulSubmission: this.testResults.steps.some(s => 
                    s.description.includes('Submit settings form') && 
                    s.success && s.data?.optionsPhpRequest
                ),
                settingsPersist: this.testResults.steps.some(s => 
                    s.description.includes('persistence') && s.success
                )
            }
        };

        // Determine overall test success
        report.success = report.criticalChecks.formUsesOptionsPhp &&
                        report.criticalChecks.settingsApiFieldsPresent &&
                        report.criticalChecks.successfulSubmission &&
                        report.criticalChecks.settingsPersist;

        const reportPath = path.join(__dirname, 'tmp', 'settings_api_fix_test_report.json');
        fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
        
        console.log('\n=== SETTINGS API FIX TEST REPORT ===');
        console.log(`Overall Success: ${report.success ? '✓ PASS' : '✗ FAIL'}`);
        console.log(`Total Steps: ${report.summary.totalSteps}`);
        console.log(`Successful Steps: ${report.summary.successfulSteps}`);
        console.log(`Failed Steps: ${report.summary.failedSteps}`);
        console.log(`Duration: ${Math.round(report.duration / 1000)}s`);
        console.log('\nCritical Checks:');
        Object.entries(report.criticalChecks).forEach(([check, passed]) => {
            console.log(`  ${passed ? '✓' : '✗'} ${check}`);
        });
        console.log(`\nFull report saved to: ${reportPath}`);
        
        return report;
    }

    async cleanup() {
        if (this.browser) {
            await this.browser.close();
        }
    }

    async runTest() {
        try {
            await this.init();
            
            await this.loginToWordPress();
            const initialSettings = await this.navigateToSettingsPage();
            await this.verifyFormStructure();
            const changedValues = await this.changeSettingsValues();
            await this.submitForm();
            const persistedValues = await this.verifyPersistence();
            
            this.testResults.success = true;
            
        } catch (error) {
            console.error('Test failed with error:', error.message);
            this.testResults.errors.push({
                timestamp: new Date().toISOString(),
                type: 'test_failure',
                message: error.message,
                stack: error.stack
            });
            this.testResults.success = false;
        } finally {
            const report = await this.generateTestReport();
            await this.cleanup();
            return report;
        }
    }
}

// Export for use as module or run directly
if (require.main === module) {
    (async () => {
        const test = new WordPressSettingsTest();
        const report = await test.runTest();
        process.exit(report.success ? 0 : 1);
    })();
}

module.exports = WordPressSettingsTest;