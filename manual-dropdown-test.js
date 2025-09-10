const puppeteer = require('puppeteer');

async function testDropdownPersistenceFix() {
    console.log('🔧 Testing WordPress Content Flow Dropdown Persistence Fix');
    console.log('=======================================================\n');

    let browser;
    try {
        browser = await puppeteer.launch({ 
            headless: false, 
            defaultViewport: null,
            args: ['--start-maximized'],
            slowMo: 500 
        });
        
        const page = await browser.newPage();
        
        // Capture console messages from the browser
        const consoleMessages = [];
        page.on('console', msg => {
            const message = `[${msg.type().toUpperCase()}] ${msg.text()}`;
            consoleMessages.push(message);
            console.log('🌐 ' + message);
        });
        
        // Capture page errors
        page.on('pageerror', error => {
            console.log('❌ PAGE ERROR:', error.message);
        });

        console.log('📋 Step 1: Navigating to WordPress admin...');
        await page.goto('http://localhost:8080/wp-admin');
        
        // Login
        console.log('🔐 Step 2: Logging in...');
        await page.type('#user_login', 'admin');
        await page.type('#user_pass', '!3cTXkh)9iDHhV5o*N');
        await page.click('#wp-submit');
        await page.waitForNavigation();
        
        console.log('✅ Login successful');
        
        // Navigate to settings
        console.log('⚙️  Step 3: Navigating to WP Content Flow settings...');
        await page.goto('http://localhost:8080/wp-admin/admin.php?page=wp-content-flow-settings');
        await page.waitForTimeout(3000); // Wait for JavaScript to execute
        
        console.log('📊 Step 4: Analyzing dropdown state...');
        
        // Check for dropdown element
        const dropdownExists = await page.$('[name="wp_content_flow_settings[default_ai_provider]"]');
        if (!dropdownExists) {
            throw new Error('Dropdown element not found!');
        }
        
        // Get initial dropdown value
        const initialValue = await page.$eval('[name="wp_content_flow_settings[default_ai_provider]"]', el => el.value);
        console.log(`📋 Initial dropdown value: "${initialValue}"`);
        
        // Check if dropdown has debug styling
        const dropdownStyles = await page.$eval('[name="wp_content_flow_settings[default_ai_provider]"]', el => el.style.cssText);
        console.log(`🎨 Dropdown debug styling: ${dropdownStyles || 'None'}`);
        const hasDebugStyling = dropdownStyles.includes('border') || dropdownStyles.includes('background');
        console.log(`✅ Debug styling applied: ${hasDebugStyling ? 'YES' : 'NO'}`);
        
        // Look for database value display
        const databaseValueDisplay = await page.evaluate(() => {
            const elements = Array.from(document.querySelectorAll('*'));
            const element = elements.find(el => el.textContent.includes('Current database value:'));
            return element ? element.textContent.trim() : null;
        });
        
        if (databaseValueDisplay) {
            console.log(`📀 Database value display: "${databaseValueDisplay}"`);
            const match = databaseValueDisplay.match(/Current database value:\s*(.+)/);
            const databaseValue = match ? match[1].trim() : 'unknown';
            console.log(`📊 Extracted database value: "${databaseValue}"`);
            
            const valuesMatch = initialValue === databaseValue;
            console.log(`🔍 Dropdown matches database: ${valuesMatch ? '✅ YES' : '❌ NO'}`);
        } else {
            console.log('⚠️  Database value display not found');
        }
        
        // Get available options
        const options = await page.$$eval('[name="wp_content_flow_settings[default_ai_provider]"] option', options => 
            options.map(opt => ({ value: opt.value, text: opt.textContent.trim(), selected: opt.selected }))
        );
        console.log('📋 Available options:', options);
        
        // Find different option to test with
        const differentOption = options.find(opt => opt.value !== initialValue && opt.value !== '');
        
        if (!differentOption) {
            console.log('⚠️  No different option available for testing persistence');
            return;
        }
        
        console.log(`🔄 Step 5: Testing dropdown change (${initialValue} → ${differentOption.value})`);
        
        // Change dropdown value
        await page.select('[name="wp_content_flow_settings[default_ai_provider]"]', differentOption.value);
        
        // Verify change
        const changedValue = await page.$eval('[name="wp_content_flow_settings[default_ai_provider]"]', el => el.value);
        console.log(`📋 Value after change: "${changedValue}"`);
        
        // Save settings
        console.log('💾 Step 6: Saving settings...');
        await page.click('#wp-content-flow-submit-btn');
        await page.waitForTimeout(5000); // Wait for save to complete
        
        console.log('🔄 Step 7: Checking persistence after save...');
        
        // Check if we need to navigate back to settings page
        const currentUrl = page.url();
        if (!currentUrl.includes('wp-content-flow-settings')) {
            await page.goto('http://localhost:8080/wp-admin/admin.php?page=wp-content-flow-settings');
        }
        
        await page.waitForTimeout(3000); // Wait for page load and JavaScript
        
        // Check dropdown value after save
        const savedValue = await page.$eval('[name="wp_content_flow_settings[default_ai_provider]"]', el => el.value);
        console.log(`💾 Value after save: "${savedValue}"`);
        
        // Check database value display after save
        const newDatabaseDisplay = await page.evaluate(() => {
            const elements = Array.from(document.querySelectorAll('*'));
            const element = elements.find(el => el.textContent.includes('Current database value:'));
            return element ? element.textContent.trim() : null;
        });
        
        if (newDatabaseDisplay) {
            console.log(`📀 Database display after save: "${newDatabaseDisplay}"`);
        }
        
        console.log('🔄 Step 8: Testing persistence after page reload...');
        
        // Reload page
        await page.reload();
        await page.waitForTimeout(3000);
        
        // Check dropdown value after reload
        const reloadedValue = await page.$eval('[name="wp_content_flow_settings[default_ai_provider]"]', el => el.value);
        console.log(`🔄 Value after reload: "${reloadedValue}"`);
        
        // Final database value display check
        const finalDatabaseDisplay = await page.evaluate(() => {
            const elements = Array.from(document.querySelectorAll('*'));
            const element = elements.find(el => el.textContent.includes('Current database value:'));
            return element ? element.textContent.trim() : null;
        });
        
        if (finalDatabaseDisplay) {
            console.log(`📀 Final database display: "${finalDatabaseDisplay}"`);
        }
        
        // Test Results Summary
        console.log('\n🏆 TEST RESULTS SUMMARY');
        console.log('========================');
        console.log(`Initial value: "${initialValue}"`);
        console.log(`Changed to: "${differentOption.value}"`);
        console.log(`After save: "${savedValue}"`);
        console.log(`After reload: "${reloadedValue}"`);
        
        const saveWorked = savedValue === differentOption.value;
        const persistenceWorked = reloadedValue === differentOption.value;
        
        console.log(`\n📊 RESULTS:`);
        console.log(`✅ Save functionality: ${saveWorked ? 'WORKING' : 'FAILED'}`);
        console.log(`✅ Persistence after reload: ${persistenceWorked ? 'WORKING' : 'FAILED'}`);
        console.log(`🎨 Debug styling present: ${hasDebugStyling ? 'YES' : 'NO'}`);
        console.log(`📀 Database value display: ${databaseValueDisplay ? 'PRESENT' : 'MISSING'}`);
        
        if (saveWorked && persistenceWorked) {
            console.log('\n🎉 DROPDOWN FIX IS WORKING CORRECTLY!');
        } else {
            console.log('\n❌ DROPDOWN FIX STILL HAS ISSUES');
        }
        
        // Take final screenshot
        await page.screenshot({ path: '/home/timl/dev/WP_ContentFlow/tmp/dropdown-test-final.png', fullPage: true });
        console.log('📷 Screenshot saved to tmp/dropdown-test-final.png');
        
        console.log('\n📝 CONSOLE MESSAGES CAPTURED:');
        console.log('===============================');
        consoleMessages.forEach((msg, index) => {
            console.log(`${index + 1}. ${msg}`);
        });
        
    } catch (error) {
        console.error('❌ Test failed:', error.message);
        if (browser) {
            const pages = await browser.pages();
            if (pages[0]) {
                await pages[0].screenshot({ path: '/home/timl/dev/WP_ContentFlow/tmp/dropdown-test-error.png', fullPage: true });
                console.log('📷 Error screenshot saved to tmp/dropdown-test-error.png');
            }
        }
    } finally {
        if (browser) {
            await browser.close();
        }
    }
}

// Check if puppeteer is available, otherwise provide instructions
(async () => {
    try {
        await testDropdownPersistenceFix();
    } catch (error) {
        if (error.message.includes("Cannot find module 'puppeteer'")) {
            console.log('❌ Puppeteer not found. Install with: npm install puppeteer');
            console.log('📋 Manual testing instructions:');
            console.log('1. Open http://localhost:8080/wp-admin in your browser');
            console.log('2. Login with admin / !3cTXkh)9iDHhV5o*N');
            console.log('3. Go to http://localhost:8080/wp-admin/admin.php?page=wp-content-flow-settings');
            console.log('4. Open browser console (F12)');
            console.log('5. Look for messages starting with "WP Content Flow:"');
            console.log('6. Check if dropdown has blue border/background');
            console.log('7. Check if "Current database value" shows below dropdown');
            console.log('8. Change dropdown selection and save');
            console.log('9. Reload page and verify selection persists');
        } else {
            console.error('❌ Error:', error.message);
        }
    }
})();