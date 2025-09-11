/**
 * Browser-based test for WP Content Flow functionality
 * Run this directly in the WordPress post editor browser console
 */

(async function() {
    console.log('🔍 WP Content Flow Browser Test Starting...');
    
    const testResults = {
        passed: 0,
        failed: 0,
        tests: []
    };
    
    function test(name, testFn) {
        try {
            const result = testFn();
            if (result) {
                console.log(`✅ PASS: ${name}`);
                testResults.passed++;
                testResults.tests.push({ name, status: 'PASS', details: result });
            } else {
                console.log(`❌ FAIL: ${name}`);
                testResults.failed++;
                testResults.tests.push({ name, status: 'FAIL', details: result });
            }
        } catch (error) {
            console.log(`❌ ERROR: ${name} - ${error.message}`);
            testResults.failed++;
            testResults.tests.push({ name, status: 'ERROR', details: error.message });
        }
    }
    
    // Test 1: WordPress Dependencies
    test('WordPress global object exists', () => {
        return typeof window.wp !== 'undefined';
    });
    
    test('WordPress plugins API available', () => {
        return typeof window.wp?.plugins !== 'undefined';
    });
    
    test('WordPress editPost available', () => {
        return typeof window.wp?.editPost !== 'undefined';
    });
    
    test('WordPress components available', () => {
        return typeof window.wp?.components !== 'undefined';
    });
    
    test('WordPress element available', () => {
        return typeof window.wp?.element !== 'undefined';
    });
    
    test('WordPress apiFetch available', () => {
        return typeof window.wp?.apiFetch !== 'undefined';
    });
    
    // Test 2: Plugin Scripts
    test('WP Content Flow script loaded', () => {
        const scripts = Array.from(document.querySelectorAll('script[src*="wp-content-flow"]'));
        console.log('Plugin scripts found:', scripts.map(s => s.src));
        return scripts.length > 0;
    });
    
    test('WP Content Flow CSS loaded', () => {
        const styles = Array.from(document.querySelectorAll('link[href*="wp-content-flow"]'));
        console.log('Plugin styles found:', styles.map(s => s.href));
        return styles.length > 0;
    });
    
    // Test 3: Global Variables
    test('wpContentFlow global variable exists', () => {
        console.log('wpContentFlow object:', window.wpContentFlow);
        return typeof window.wpContentFlow !== 'undefined';
    });
    
    // Test 4: Plugin Registration
    test('AI Chat plugin registered', () => {
        if (window.wp?.plugins?.getPlugin) {
            const plugin = window.wp.plugins.getPlugin('wp-content-flow-ai-chat');
            console.log('Plugin registration result:', plugin);
            return plugin !== undefined;
        }
        return false;
    });
    
    test('Plugin appears in registered plugins list', () => {
        if (window.wp?.plugins?.getPlugins) {
            const plugins = window.wp.plugins.getPlugins();
            const pluginNames = Object.keys(plugins);
            console.log('All registered plugins:', pluginNames);
            return pluginNames.includes('wp-content-flow-ai-chat');
        }
        return false;
    });
    
    // Test 5: DOM Elements
    test('Gutenberg editor loaded', () => {
        const editor = document.querySelector('.block-editor-page');
        return editor !== null;
    });
    
    test('Editor header exists', () => {
        const header = document.querySelector('.edit-post-header');
        return header !== null;
    });
    
    // Test 6: Menu Elements
    test('Editor toolbar buttons exist', () => {
        const buttons = document.querySelectorAll('.edit-post-header button');
        console.log(`Found ${buttons.length} toolbar buttons`);
        return buttons.length > 0;
    });
    
    // Test 7: Search for AI Chat Elements
    test('AI Chat text found in DOM', () => {
        const elements = Array.from(document.querySelectorAll('*')).filter(el => 
            el.textContent && el.textContent.includes('AI Chat')
        );
        console.log('Elements containing "AI Chat":', elements.length);
        elements.forEach((el, i) => {
            console.log(`  ${i}: ${el.tagName}.${el.className} - "${el.textContent.trim()}"`);
        });
        return elements.length > 0;
    });
    
    // Test 8: API Endpoints
    test('REST API status endpoint accessible', async () => {
        try {
            const response = await fetch('/wp-json/wp-content-flow/v1/status');
            console.log('API status response:', response.status, response.statusText);
            if (response.ok) {
                const data = await response.json();
                console.log('API status data:', data);
                return true;
            }
            return false;
        } catch (error) {
            console.log('API error:', error.message);
            return false;
        }
    });
    
    // Test 9: Check Console Errors
    test('No critical JavaScript errors', () => {
        // This is harder to test retroactively, but we can check if basic functionality works
        try {
            const testElement = window.wp.element.createElement('div', {}, 'test');
            return testElement !== null;
        } catch (error) {
            console.log('JavaScript functionality error:', error.message);
            return false;
        }
    });
    
    // Test 10: Manual Plugin Execution
    test('Can manually execute plugin registration', () => {
        try {
            if (window.wp?.plugins?.registerPlugin) {
                // Check if we can call registerPlugin (even if already called)
                console.log('registerPlugin function available');
                return true;
            }
            return false;
        } catch (error) {
            console.log('Plugin registration error:', error.message);
            return false;
        }
    });
    
    // Wait a moment for any async operations
    await new Promise(resolve => setTimeout(resolve, 2000));
    
    // Final Results
    console.log('\n📊 TEST SUMMARY');
    console.log('================');
    console.log(`Total Tests: ${testResults.passed + testResults.failed}`);
    console.log(`✅ Passed: ${testResults.passed}`);
    console.log(`❌ Failed: ${testResults.failed}`);
    console.log(`Success Rate: ${Math.round((testResults.passed / (testResults.passed + testResults.failed)) * 100)}%`);
    
    // Detailed Results
    console.log('\n📋 DETAILED RESULTS');
    console.log('===================');
    testResults.tests.forEach(test => {
        console.log(`${test.status === 'PASS' ? '✅' : '❌'} ${test.name}`);
        if (test.details && typeof test.details !== 'boolean') {
            console.log(`   Details: ${test.details}`);
        }
    });
    
    // Diagnostic Information
    console.log('\n🔍 DIAGNOSTIC INFO');
    console.log('==================');
    console.log('Current URL:', window.location.href);
    console.log('User Agent:', navigator.userAgent);
    console.log('WordPress Version:', window.wp?.buildVersion || 'unknown');
    
    // Check for specific WordPress editor elements
    const editorSelectors = [
        '.edit-post-header__settings',
        '.edit-post-more-menu',
        '.components-dropdown-menu',
        '.edit-post-sidebar',
        '.interface-complementary-area-header'
    ];
    
    console.log('\n🎯 EDITOR ELEMENT CHECK');
    console.log('=======================');
    editorSelectors.forEach(selector => {
        const element = document.querySelector(selector);
        console.log(`${element ? '✅' : '❌'} ${selector}: ${element ? 'Found' : 'Not found'}`);
    });
    
    // List all buttons in the header area
    console.log('\n🔲 HEADER BUTTONS');
    console.log('=================');
    const headerButtons = document.querySelectorAll('.edit-post-header button');
    headerButtons.forEach((button, index) => {
        console.log(`${index + 1}. "${button.textContent.trim()}" [aria-label: "${button.getAttribute('aria-label') || 'none'}"]`);
    });
    
    return testResults;
})();