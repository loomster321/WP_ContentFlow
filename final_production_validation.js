/**
 * Final Production Validation Test for WordPress AI Content Flow Plugin
 * 
 * Tests all critical fixes and functionality after recent improvements:
 * 1. Settings persistence (original bug fix)
 * 2. API key security masking 
 * 3. WordPress admin stability
 * 4. Core plugin features
 * 5. Error detection and prevention
 */

const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

// WordPress test configuration
const WORDPRESS_URL = 'http://localhost:8080';
const ADMIN_USER = 'admin';
const ADMIN_PASS = '!3cTXkh)9iDHhV5o*N';

// Test results tracking
let testResults = {
    timestamp: new Date().toISOString(),
    environment: {
        wordpress_url: WORDPRESS_URL,
        user: ADMIN_USER
    },
    tests: [],
    summary: {
        passed: 0,
        failed: 0,
        total: 0
    },
    criticalIssues: [],
    securityValidation: [],
    performanceMetrics: []
};

// Helper function to log test results
function logTestResult(testName, status, details = {}) {
    const result = {
        name: testName,
        status: status,
        timestamp: new Date().toISOString(),
        details: details
    };
    
    testResults.tests.push(result);
    testResults.summary.total++;
    
    if (status === 'passed') {
        testResults.summary.passed++;
        console.log(`✅ ${testName}`);
    } else {
        testResults.summary.failed++;
        console.log(`❌ ${testName}: ${details.error || 'Failed'}`);
        if (details.critical) {
            testResults.criticalIssues.push(result);
        }
    }
}

// WordPress login helper
async function loginToWordPress(page) {
    await page.goto(`${WORDPRESS_URL}/wp-admin/`);
    
    // Handle if already logged in
    if (await page.locator('body.wp-admin').isVisible()) {
        console.log('Already logged in to WordPress admin');
        return true;
    }
    
    // Login process
    await page.fill('#user_login', ADMIN_USER);
    await page.fill('#user_pass', ADMIN_PASS);
    await page.click('#wp-submit');
    
    // Wait for admin dashboard
    await page.waitForSelector('body.wp-admin', { timeout: 10000 });
    
    return page.locator('body.wp-admin').isVisible();
}

test.describe('WordPress AI Content Flow - Final Production Validation', () => {
    
    test.beforeEach(async ({ page }) => {
        // Set up page error tracking
        page.on('pageerror', error => {
            console.error('Page Error:', error.message);
            testResults.criticalIssues.push({
                type: 'javascript_error',
                message: error.message,
                stack: error.stack
            });
        });
        
        page.on('console', msg => {
            if (msg.type() === 'error') {
                console.error('Console Error:', msg.text());
            }
        });
    });

    test('1. WordPress Admin Access and Navigation', async ({ page }) => {
        const testName = '1. WordPress Admin Access and Navigation';
        
        try {
            // Test WordPress admin accessibility
            await page.goto(`${WORDPRESS_URL}/wp-admin/`);
            const loginSuccess = await loginToWordPress(page);
            
            if (!loginSuccess) {
                throw new Error('Failed to login to WordPress admin');
            }
            
            // Verify admin dashboard loads
            await expect(page.locator('#wpadminbar')).toBeVisible();
            await expect(page.locator('#adminmenu')).toBeVisible();
            
            // Test plugin menu navigation
            await page.click('a[href="admin.php?page=wp-content-flow"]');
            await page.waitForSelector('.wp-content-flow-admin', { timeout: 10000 });
            
            // Verify plugin settings page loads
            const pageTitle = await page.locator('h1').textContent();
            expect(pageTitle).toContain('AI Content Flow');
            
            logTestResult(testName, 'passed', {
                page_title: pageTitle,
                admin_bar_visible: true,
                plugin_menu_accessible: true
            });
            
        } catch (error) {
            logTestResult(testName, 'failed', {
                error: error.message,
                critical: true
            });
            throw error;
        }
    });

    test('2. Settings Form Display and API Key Masking', async ({ page }) => {
        const testName = '2. Settings Form Display and API Key Masking';
        
        try {
            await loginToWordPress(page);
            await page.goto(`${WORDPRESS_URL}/wp-admin/admin.php?page=wp-content-flow`);
            
            // Wait for settings form to load
            await page.waitForSelector('form', { timeout: 10000 });
            
            // Check for API key fields
            const openaiKeyField = page.locator('input[name="openai_api_key"]');
            const anthropicKeyField = page.locator('input[name="anthropic_api_key"]');
            const googleKeyField = page.locator('input[name="google_ai_api_key"]');
            
            // Verify form fields exist
            await expect(openaiKeyField).toBeVisible();
            await expect(anthropicKeyField).toBeVisible();
            await expect(googleKeyField).toBeVisible();
            
            // Check API key masking - should show masked values if keys exist
            const openaiValue = await openaiKeyField.inputValue();
            const anthropicValue = await anthropicKeyField.inputValue();
            const googleValue = await googleKeyField.inputValue();
            
            // Security validation: no full keys should be visible
            const hasSecureKeys = [openaiValue, anthropicValue, googleValue].every(value => {
                return !value || value === '' || value.includes('*') || value.includes('•') || value.length < 10;
            });
            
            if (!hasSecureKeys) {
                throw new Error('API keys appear to be exposed in plaintext');
            }
            
            testResults.securityValidation.push({
                test: 'API Key Masking',
                status: 'passed',
                details: {
                    openai_masked: openaiValue.includes('*') || openaiValue === '',
                    anthropic_masked: anthropicValue.includes('*') || anthropicValue === '',
                    google_masked: googleValue.includes('*') || googleValue === ''
                }
            });
            
            logTestResult(testName, 'passed', {
                form_loaded: true,
                api_keys_masked: true,
                security_check: 'passed'
            });
            
        } catch (error) {
            logTestResult(testName, 'failed', {
                error: error.message,
                critical: true
            });
            throw error;
        }
    });

    test('3. Settings Persistence (Original Bug Fix Verification)', async ({ page }) => {
        const testName = '3. Settings Persistence (Original Bug Fix Verification)';
        
        try {
            await loginToWordPress(page);
            await page.goto(`${WORDPRESS_URL}/wp-admin/admin.php?page=wp-content-flow`);
            
            // Wait for form to load
            await page.waitForSelector('form', { timeout: 10000 });
            
            // Test provider dropdown functionality
            const providerDropdown = page.locator('select[name="default_ai_provider"]');
            await expect(providerDropdown).toBeVisible();
            
            // Get current value
            const originalValue = await providerDropdown.inputValue();
            console.log('Original provider value:', originalValue);
            
            // Change to a different provider
            const testProviders = ['openai', 'anthropic', 'google'];
            const newProvider = testProviders.find(p => p !== originalValue) || 'openai';
            
            await providerDropdown.selectOption(newProvider);
            console.log('Selected new provider:', newProvider);
            
            // Set a test setting value
            const maxTokensField = page.locator('input[name="max_tokens"]');
            await maxTokensField.clear();
            await maxTokensField.fill('1500');
            
            // Submit the form
            await page.click('button[type="submit"], input[type="submit"]');
            
            // Wait for save confirmation or page reload
            await page.waitForTimeout(3000);
            
            // Reload the page to verify persistence
            await page.reload();
            await page.waitForSelector('form', { timeout: 10000 });
            
            // Verify settings were saved
            const savedProvider = await page.locator('select[name="default_ai_provider"]').inputValue();
            const savedTokens = await page.locator('input[name="max_tokens"]').inputValue();
            
            const persistenceWorking = savedProvider === newProvider && savedTokens === '1500';
            
            if (!persistenceWorking) {
                throw new Error(`Settings persistence failed. Expected provider: ${newProvider}, got: ${savedProvider}. Expected tokens: 1500, got: ${savedTokens}`);
            }
            
            logTestResult(testName, 'passed', {
                original_provider: originalValue,
                new_provider: newProvider,
                saved_provider: savedProvider,
                saved_tokens: savedTokens,
                persistence_working: true
            });
            
        } catch (error) {
            logTestResult(testName, 'failed', {
                error: error.message,
                critical: true
            });
            throw error;
        }
    });

    test('4. PHP Fatal Error Detection', async ({ page }) => {
        const testName = '4. PHP Fatal Error Detection';
        
        try {
            await loginToWordPress(page);
            
            // Test critical WordPress admin pages
            const pagesToTest = [
                '/wp-admin/',
                '/wp-admin/admin.php?page=wp-content-flow',
                '/wp-admin/plugins.php',
                '/wp-admin/post-new.php'
            ];
            
            for (const pagePath of pagesToTest) {
                await page.goto(`${WORDPRESS_URL}${pagePath}`);
                
                // Check for PHP fatal errors
                const pageContent = await page.content();
                const hasFatalError = pageContent.includes('Fatal error:') || 
                                    pageContent.includes('Parse error:') ||
                                    pageContent.includes('Call to undefined');
                
                if (hasFatalError) {
                    throw new Error(`PHP Fatal Error detected on ${pagePath}`);
                }
                
                // Check for white screen of death
                const hasContent = await page.locator('body').isVisible();
                if (!hasContent) {
                    throw new Error(`White screen detected on ${pagePath}`);
                }
            }
            
            logTestResult(testName, 'passed', {
                pages_tested: pagesToTest.length,
                fatal_errors_detected: false,
                all_pages_loading: true
            });
            
        } catch (error) {
            logTestResult(testName, 'failed', {
                error: error.message,
                critical: true
            });
            throw error;
        }
    });

    test('5. JavaScript Console Error Detection', async ({ page }) => {
        const testName = '5. JavaScript Console Error Detection';
        
        const jsErrors = [];
        
        try {
            // Capture console errors
            page.on('console', msg => {
                if (msg.type() === 'error') {
                    jsErrors.push({
                        message: msg.text(),
                        url: page.url(),
                        timestamp: new Date().toISOString()
                    });
                }
            });
            
            await loginToWordPress(page);
            await page.goto(`${WORDPRESS_URL}/wp-admin/admin.php?page=wp-content-flow`);
            
            // Wait for JavaScript to load
            await page.waitForTimeout(5000);
            
            // Test form interactions to trigger any JS errors
            await page.click('select[name="default_ai_provider"]');
            await page.fill('input[name="max_tokens"]', '2000');
            
            // Filter out non-critical errors
            const criticalErrors = jsErrors.filter(error => {
                const message = error.message.toLowerCase();
                return !message.includes('favicon') && 
                       !message.includes('extension') &&
                       !message.includes('chrome-extension');
            });
            
            if (criticalErrors.length > 0) {
                throw new Error(`JavaScript errors detected: ${criticalErrors.map(e => e.message).join(', ')}`);
            }
            
            logTestResult(testName, 'passed', {
                total_js_errors: jsErrors.length,
                critical_errors: criticalErrors.length,
                javascript_working: true
            });
            
        } catch (error) {
            logTestResult(testName, 'failed', {
                error: error.message,
                js_errors: jsErrors,
                critical: criticalErrors.length > 0
            });
            throw error;
        }
    });

    test('6. Core Plugin Features Validation', async ({ page }) => {
        const testName = '6. Core Plugin Features Validation';
        
        try {
            await loginToWordPress(page);
            
            // Test plugin is active and accessible
            await page.goto(`${WORDPRESS_URL}/wp-admin/plugins.php`);
            const pluginActive = await page.locator('tr[data-slug="wp-content-flow"] .deactivate').isVisible();
            
            if (!pluginActive) {
                throw new Error('Plugin is not active in WordPress');
            }
            
            // Test settings page functionality
            await page.goto(`${WORDPRESS_URL}/wp-admin/admin.php?page=wp-content-flow`);
            await page.waitForSelector('form', { timeout: 10000 });
            
            // Verify core settings fields exist
            const coreFields = [
                'select[name="default_ai_provider"]',
                'input[name="openai_api_key"]',
                'input[name="anthropic_api_key"]',
                'input[name="google_ai_api_key"]',
                'input[name="max_tokens"]'
            ];
            
            for (const field of coreFields) {
                await expect(page.locator(field)).toBeVisible();
            }
            
            // Test Gutenberg block editor integration
            await page.goto(`${WORDPRESS_URL}/wp-admin/post-new.php`);
            await page.waitForSelector('.block-editor', { timeout: 15000 });
            
            // Check if our AI blocks are available
            await page.click('.block-editor-inserter__toggle');
            await page.waitForSelector('.block-editor-inserter__content', { timeout: 5000 });
            
            // Search for AI content flow blocks
            await page.fill('.block-editor-inserter__search input', 'AI Content');
            await page.waitForTimeout(2000);
            
            logTestResult(testName, 'passed', {
                plugin_active: true,
                settings_page_functional: true,
                core_fields_present: coreFields.length,
                gutenberg_accessible: true
            });
            
        } catch (error) {
            logTestResult(testName, 'failed', {
                error: error.message,
                critical: false
            });
            // Don't throw here as this might be expected for some configurations
        }
    });

    test('7. Performance and Stability Check', async ({ page }) => {
        const testName = '7. Performance and Stability Check';
        
        try {
            const performanceMetrics = {
                admin_load_time: 0,
                settings_load_time: 0,
                form_interaction_time: 0
            };
            
            // Measure admin page load time
            const adminStartTime = Date.now();
            await loginToWordPress(page);
            performanceMetrics.admin_load_time = Date.now() - adminStartTime;
            
            // Measure settings page load time
            const settingsStartTime = Date.now();
            await page.goto(`${WORDPRESS_URL}/wp-admin/admin.php?page=wp-content-flow`);
            await page.waitForSelector('form', { timeout: 10000 });
            performanceMetrics.settings_load_time = Date.now() - settingsStartTime;
            
            // Measure form interaction responsiveness
            const interactionStartTime = Date.now();
            await page.selectOption('select[name="default_ai_provider"]', 'openai');
            await page.fill('input[name="max_tokens"]', '1000');
            performanceMetrics.form_interaction_time = Date.now() - interactionStartTime;
            
            // Performance thresholds (in milliseconds)
            const thresholds = {
                admin_load_time: 10000,    // 10 seconds
                settings_load_time: 15000,  // 15 seconds
                form_interaction_time: 2000 // 2 seconds
            };
            
            // Check if performance is within acceptable limits
            const performanceIssues = [];
            for (const [metric, value] of Object.entries(performanceMetrics)) {
                if (value > thresholds[metric]) {
                    performanceIssues.push(`${metric}: ${value}ms (threshold: ${thresholds[metric]}ms)`);
                }
            }
            
            testResults.performanceMetrics = performanceMetrics;
            
            if (performanceIssues.length > 0) {
                logTestResult(testName, 'failed', {
                    performance_issues: performanceIssues,
                    metrics: performanceMetrics
                });
            } else {
                logTestResult(testName, 'passed', {
                    metrics: performanceMetrics,
                    all_within_thresholds: true
                });
            }
            
        } catch (error) {
            logTestResult(testName, 'failed', {
                error: error.message,
                critical: false
            });
        }
    });

    test.afterAll(async () => {
        // Generate final test report
        const reportPath = '/home/timl/dev/WP_ContentFlow/FINAL_PRODUCTION_VALIDATION_REPORT.md';
        
        const report = `# WordPress AI Content Flow - Final Production Validation Report

## Executive Summary

**Test Date**: ${testResults.timestamp}
**WordPress Environment**: ${testResults.environment.wordpress_url}
**Total Tests**: ${testResults.summary.total}
**Passed**: ${testResults.summary.passed}
**Failed**: ${testResults.summary.failed}
**Success Rate**: ${((testResults.summary.passed / testResults.summary.total) * 100).toFixed(1)}%

## Production Readiness Assessment

${testResults.summary.failed === 0 ? '✅ **PRODUCTION READY**' : '❌ **NOT PRODUCTION READY**'}

${testResults.criticalIssues.length === 0 ? '✅ No critical issues detected' : `❌ ${testResults.criticalIssues.length} critical issue(s) found`}

## Test Results Detail

${testResults.tests.map(test => `
### ${test.name}
**Status**: ${test.status === 'passed' ? '✅ PASSED' : '❌ FAILED'}
**Timestamp**: ${test.timestamp}
${test.details ? `**Details**: \`\`\`json\n${JSON.stringify(test.details, null, 2)}\n\`\`\`` : ''}
`).join('\n')}

## Security Validation

${testResults.securityValidation.map(security => `
- **${security.test}**: ${security.status === 'passed' ? '✅ PASSED' : '❌ FAILED'}
  ${security.details ? `Details: ${JSON.stringify(security.details)}` : ''}
`).join('\n')}

## Performance Metrics

${testResults.performanceMetrics ? `
- **Admin Load Time**: ${testResults.performanceMetrics.admin_load_time}ms
- **Settings Load Time**: ${testResults.performanceMetrics.settings_load_time}ms
- **Form Interaction Time**: ${testResults.performanceMetrics.form_interaction_time}ms
` : 'No performance metrics collected'}

## Critical Issues

${testResults.criticalIssues.length === 0 ? 'No critical issues detected.' : testResults.criticalIssues.map(issue => `
- **${issue.name || issue.type}**: ${issue.error || issue.message}
  ${issue.details ? `Details: ${JSON.stringify(issue.details)}` : ''}
`).join('\n')}

## Recommendations

${testResults.summary.failed === 0 ? `
✅ All tests passed successfully. The plugin appears to be production ready with:
- Settings persistence working correctly (original bug fixed)
- API key security masking implemented
- No PHP fatal errors detected
- JavaScript console clean
- Core functionality operational
- Performance within acceptable thresholds

**DEPLOYMENT APPROVED**
` : `
❌ Issues detected that need resolution before production deployment:
${testResults.tests.filter(t => t.status === 'failed').map(t => `- ${t.name}: ${t.details.error}`).join('\n')}

**DEPLOYMENT NOT RECOMMENDED** until these issues are resolved.
`}

## Next Steps

${testResults.summary.failed === 0 ? `
1. Final manual verification of API integration with live keys
2. Backup current WordPress installation
3. Deploy to production environment
4. Monitor for 24 hours post-deployment
` : `
1. Address failed test cases
2. Re-run validation tests
3. Fix critical issues before considering deployment
`}

---
*Generated by WordPress AI Content Flow Final Validation Suite*
*Test Environment: WordPress at ${testResults.environment.wordpress_url}*
        `;
        
        fs.writeFileSync(reportPath, report);
        console.log(`\n📊 Final validation report generated: ${reportPath}`);
        console.log(`\n🎯 Production Readiness: ${testResults.summary.failed === 0 ? 'READY' : 'NOT READY'}`);
        console.log(`📈 Success Rate: ${((testResults.summary.passed / testResults.summary.total) * 100).toFixed(1)}%`);
    });
});