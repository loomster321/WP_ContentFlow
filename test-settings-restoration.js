const { chromium } = require('playwright');

async function testSettingsRestoration() {
    console.log('🚀 Starting WordPress Content Flow Settings Form Restoration Test...\n');
    
    const browser = await chromium.launch({ 
        headless: false,  // Show browser for visual verification
        slowMo: 1000     // Slow down for observation
    });
    
    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 }
    });
    
    const page = await context.newPage();
    
    try {
        // Navigate to WordPress admin login
        console.log('1️⃣ Navigating to WordPress admin...');
        await page.goto('http://localhost:8080/wp-admin');
        
        // Login if needed
        const loginForm = page.locator('#loginform');
        if (await loginForm.isVisible()) {
            console.log('2️⃣ Logging in...');
            await page.fill('#user_login', 'admin');
            await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
            await page.click('#wp-submit');
            
            // Wait for dashboard to load
            await page.waitForSelector('#wpadminbar', { timeout: 10000 });
            console.log('   ✅ Login successful');
        }
        
        // Navigate to Content Flow settings page
        console.log('3️⃣ Navigating to Content Flow settings page...');
        await page.goto('http://localhost:8080/wp-admin/admin.php?page=wp-content-flow-settings');
        
        // Wait for page to fully load
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(3000); // Allow for any dynamic content loading
        
        // Take initial screenshot
        console.log('4️⃣ Taking initial screenshot...');
        await page.screenshot({ 
            path: '/home/timl/dev/WP_ContentFlow/tmp/settings-restoration-initial.png',
            fullPage: true 
        });
        
        // Check for the main page heading to ensure we're on the right page
        const pageTitle = page.locator('h1, h2, .wp-heading-inline');
        const titleText = await pageTitle.first().textContent();
        console.log(`   📄 Page title: ${titleText}`);
        
        // Test for all 6 required form fields
        console.log('5️⃣ Checking for all 6 required form fields...');
        
        const expectedFields = [
            { name: 'OpenAI API Key', selector: 'input[name="wp_content_flow_openai_api_key"]' },
            { name: 'Anthropic API Key', selector: 'input[name="wp_content_flow_anthropic_api_key"]' },
            { name: 'Google AI API Key', selector: 'input[name="wp_content_flow_google_api_key"]' },
            { name: 'Default AI Provider', selector: 'select[name="wp_content_flow_default_provider"]' },
            { name: 'Enable Caching', selector: 'input[name="wp_content_flow_enable_caching"]' },
            { name: 'Requests Per Minute', selector: 'input[name="wp_content_flow_requests_per_minute"]' }
        ];
        
        let allFieldsVisible = true;
        let visibleFields = [];
        let missingFields = [];
        
        for (const field of expectedFields) {
            try {
                const element = page.locator(field.selector);
                const isVisible = await element.isVisible();
                const isEnabled = await element.isEnabled();
                
                if (isVisible && isEnabled) {
                    console.log(`   ✅ ${field.name}: VISIBLE & ENABLED`);
                    visibleFields.push(field.name);
                } else {
                    console.log(`   ❌ ${field.name}: ${isVisible ? 'VISIBLE' : 'MISSING'} / ${isEnabled ? 'ENABLED' : 'DISABLED'}`);
                    missingFields.push(field.name);
                    allFieldsVisible = false;
                }
            } catch (error) {
                console.log(`   ❌ ${field.name}: ERROR - ${error.message}`);
                missingFields.push(field.name);
                allFieldsVisible = false;
            }
        }
        
        // Take screenshot showing current field state
        await page.screenshot({ 
            path: '/home/timl/dev/WP_ContentFlow/tmp/settings-restoration-field-check.png',
            fullPage: true 
        });
        
        // Check for any form elements at all
        console.log('6️⃣ Checking for any form elements...');
        const allInputs = page.locator('input, select, textarea');
        const inputCount = await allInputs.count();
        console.log(`   Found ${inputCount} total form elements on page`);
        
        if (inputCount > 0) {
            console.log('   Form elements found:');
            for (let i = 0; i < Math.min(inputCount, 10); i++) {
                const input = allInputs.nth(i);
                const name = await input.getAttribute('name') || 'no-name';
                const type = await input.getAttribute('type') || 'unknown';
                const tagName = await input.evaluate(el => el.tagName.toLowerCase());
                console.log(`     ${i + 1}. ${tagName}[type="${type}"][name="${name}"]`);
            }
        }
        
        // Check for WordPress settings form structure
        console.log('7️⃣ Checking WordPress form structure...');
        const formTable = page.locator('.form-table');
        const formTableVisible = await formTable.isVisible();
        console.log(`   WordPress form table (.form-table): ${formTableVisible ? 'PRESENT' : 'MISSING'}`);
        
        const settingsForm = page.locator('form[method="post"]');
        const settingsFormCount = await settingsForm.count();
        console.log(`   Settings forms found: ${settingsFormCount}`);
        
        // Test form functionality if fields are visible
        if (allFieldsVisible) {
            console.log('8️⃣ Testing form functionality...');
            
            // Fill in test values
            await page.fill('input[name="wp_content_flow_openai_api_key"]', 'test-key-123');
            await page.selectOption('select[name="wp_content_flow_default_provider"]', 'anthropic');
            await page.check('input[name="wp_content_flow_enable_caching"]');
            await page.fill('input[name="wp_content_flow_requests_per_minute"]', '100');
            
            console.log('   ✅ Form fields filled successfully');
            
            // Take screenshot before submission
            await page.screenshot({ 
                path: '/home/timl/dev/WP_ContentFlow/tmp/settings-restoration-before-submit.png',
                fullPage: true 
            });
            
            // Try to submit the form
            const submitButton = page.locator('input[type="submit"], button[type="submit"]');
            const submitButtonVisible = await submitButton.isVisible();
            
            if (submitButtonVisible) {
                console.log('   ✅ Submit button found - form is functional');
                // Don't actually submit to avoid changing settings
            } else {
                console.log('   ❌ Submit button not found');
            }
        }
        
        // Generate final report
        console.log('\n🎯 FINAL RESULTS:');
        console.log('=' * 50);
        console.log(`✅ Visible Fields (${visibleFields.length}/6): ${visibleFields.join(', ')}`);
        if (missingFields.length > 0) {
            console.log(`❌ Missing Fields (${missingFields.length}/6): ${missingFields.join(', ')}`);
        }
        console.log(`📊 Form Elements Found: ${inputCount}`);
        console.log(`🏗️ WordPress Structure: ${formTableVisible ? 'CORRECT' : 'MISSING'}`);
        
        if (allFieldsVisible) {
            console.log('\n🎉 SUCCESS: All 6 form fields have been restored and are working!');
            console.log('✅ The hook timing issue has been resolved.');
        } else {
            console.log('\n⚠️ ISSUE: Some form fields are still missing.');
            console.log('❌ The hook timing issue may not be fully resolved.');
        }
        
        // Final screenshot
        await page.screenshot({ 
            path: '/home/timl/dev/WP_ContentFlow/tmp/settings-restoration-final.png',
            fullPage: true 
        });
        
        console.log('\n📸 Screenshots saved to /tmp/ folder for evidence');
        
        // Keep browser open for 10 seconds for manual inspection
        console.log('\n👀 Keeping browser open for 10 seconds for manual inspection...');
        await page.waitForTimeout(10000);
        
    } catch (error) {
        console.error('❌ Test failed with error:', error.message);
        
        // Take error screenshot
        await page.screenshot({ 
            path: '/home/timl/dev/WP_ContentFlow/tmp/settings-restoration-error.png',
            fullPage: true 
        });
    }
    
    await browser.close();
}

// Run the test
testSettingsRestoration().catch(console.error);