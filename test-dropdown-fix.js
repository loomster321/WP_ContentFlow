const { chromium } = require('playwright');

async function testDropdownFix() {
    console.log('Starting comprehensive test of WordPress Content Flow dropdown fix...\n');
    
    const browser = await chromium.launch({ headless: false, slowMo: 1000 });
    const page = await browser.newPage();
    
    // Enable console logging from the page
    page.on('console', msg => {
        console.log(`[BROWSER CONSOLE] ${msg.type()}: ${msg.text()}`);
    });
    
    // Enable error logging
    page.on('pageerror', error => {
        console.log(`[BROWSER ERROR] ${error.message}`);
    });

    try {
        console.log('Step 1: Navigating to WordPress admin login...');
        await page.goto('http://localhost:8080/wp-admin');
        
        // Login
        console.log('Step 2: Logging in with admin credentials...');
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
        await page.click('#wp-submit');
        
        // Wait for dashboard to load
        await page.waitForSelector('.wrap', { timeout: 10000 });
        console.log('✓ Successfully logged into WordPress admin\n');
        
        // Navigate to plugin settings
        console.log('Step 3: Navigating to WP Content Flow settings page...');
        await page.goto('http://localhost:8080/wp-admin/admin.php?page=wp-content-flow-settings');
        
        // Wait for page to load completely
        await page.waitForSelector('[name="default_ai_provider"]', { timeout: 10000 });
        console.log('✓ Settings page loaded successfully\n');
        
        // Wait a moment for JavaScript to execute
        await page.waitForTimeout(2000);
        
        console.log('Step 4: Checking initial dropdown state and debug messages...');
        
        // Get the current dropdown value
        const currentDropdownValue = await page.inputValue('[name="default_ai_provider"]');
        console.log(`Current dropdown value: "${currentDropdownValue}"`);
        
        // Look for database value display
        const databaseValueElement = await page.locator('text=/Current database value/').first();
        const databaseValueText = await databaseValueElement.textContent().catch(() => 'Not found');
        console.log(`Database value display: "${databaseValueText}"`);
        
        // Check if dropdown is styled (has the debug styling)
        const dropdownStyles = await page.getAttribute('[name="default_ai_provider"]', 'style');
        console.log(`Dropdown styling: "${dropdownStyles}"`);
        
        // Get all available options
        const options = await page.$$eval('[name="default_ai_provider"] option', options => 
            options.map(opt => ({ value: opt.value, text: opt.textContent, selected: opt.selected }))
        );
        console.log('Available dropdown options:', options);
        
        console.log('\nStep 5: Testing dropdown value persistence...');
        
        // Find an option different from current selection
        const currentOption = options.find(opt => opt.selected);
        const differentOption = options.find(opt => opt.value !== currentDropdownValue && opt.value !== '');
        
        if (!differentOption) {
            console.log('⚠️  WARNING: Could not find a different option to test with');
        } else {
            console.log(`Changing from "${currentDropdownValue}" to "${differentOption.value}"`);
            
            // Change the dropdown value
            await page.selectOption('[name="default_ai_provider"]', differentOption.value);
            
            // Verify the change was applied
            const newDropdownValue = await page.inputValue('[name="default_ai_provider"]');
            console.log(`Dropdown value after change: "${newDropdownValue}"`);
            
            // Save the settings
            console.log('Saving settings...');
            await page.click('input[type="submit"]');
            
            // Wait for save confirmation or page reload
            await page.waitForTimeout(3000);
            
            console.log('\nStep 6: Verifying persistence after save...');
            
            // Check if we're still on settings page or need to navigate back
            const currentUrl = page.url();
            if (!currentUrl.includes('wp-content-flow-settings')) {
                await page.goto('http://localhost:8080/wp-admin/admin.php?page=wp-content-flow-settings');
                await page.waitForSelector('[name="default_ai_provider"]', { timeout: 10000 });
                await page.waitForTimeout(2000);
            }
            
            // Check the dropdown value after save
            const savedDropdownValue = await page.inputValue('[name="default_ai_provider"]');
            console.log(`Dropdown value after save: "${savedDropdownValue}"`);
            
            // Check database value display after save
            const newDatabaseValueElement = await page.locator('text=/Current database value/').first();
            const newDatabaseValueText = await newDatabaseValueElement.textContent().catch(() => 'Not found');
            console.log(`Database value display after save: "${newDatabaseValueText}"`);
            
            console.log('\nStep 7: Testing persistence after page reload...');
            
            // Reload the page
            await page.reload();
            await page.waitForSelector('[name="default_ai_provider"]', { timeout: 10000 });
            await page.waitForTimeout(2000);
            
            // Check dropdown value after reload
            const reloadedDropdownValue = await page.inputValue('[name="default_ai_provider"]');
            console.log(`Dropdown value after reload: "${reloadedDropdownValue}"`);
            
            // Check database value display after reload
            const reloadDatabaseValueElement = await page.locator('text=/Current database value/').first();
            const reloadDatabaseValueText = await reloadDatabaseValueElement.textContent().catch(() => 'Not found');
            console.log(`Database value display after reload: "${reloadDatabaseValueText}"`);
            
            // Test Results Summary
            console.log('\n=== TEST RESULTS SUMMARY ===');
            console.log(`Initial dropdown value: "${currentDropdownValue}"`);
            console.log(`Changed to: "${differentOption.value}"`);
            console.log(`Value after save: "${savedDropdownValue}"`);
            console.log(`Value after reload: "${reloadedDropdownValue}"`);
            
            const saveWorked = savedDropdownValue === differentOption.value;
            const persistenceWorked = reloadedDropdownValue === differentOption.value;
            
            console.log(`✓ Save functionality: ${saveWorked ? 'WORKING' : 'FAILED'}`);
            console.log(`✓ Persistence after reload: ${persistenceWorked ? 'WORKING' : 'FAILED'}`);
            
            if (saveWorked && persistenceWorked) {
                console.log('🎉 DROPDOWN FIX IS WORKING CORRECTLY!');
            } else {
                console.log('❌ DROPDOWN FIX STILL HAS ISSUES');
            }
        }
        
        // Take a screenshot for visual verification
        await page.screenshot({ path: '/home/timl/dev/WP_ContentFlow/tmp/dropdown-fix-test-result.png', fullPage: true });
        console.log('\n📷 Screenshot saved to tmp/dropdown-fix-test-result.png');
        
    } catch (error) {
        console.error('Error during testing:', error.message);
        await page.screenshot({ path: '/home/timl/dev/WP_ContentFlow/tmp/dropdown-fix-error.png', fullPage: true });
    } finally {
        await browser.close();
    }
}

testDropdownFix().catch(console.error);