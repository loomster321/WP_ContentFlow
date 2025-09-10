const { test, expect } = require('@playwright/test');

test.describe('WordPress Content Flow - Dropdown Persistence Fix Test', () => {
  test.beforeEach(async ({ page }) => {
    // Enable console logging
    page.on('console', msg => {
      console.log(`[BROWSER CONSOLE] ${msg.type()}: ${msg.text()}`);
    });
    
    page.on('pageerror', error => {
      console.log(`[BROWSER ERROR] ${error.message}`);
    });

    // Login to WordPress admin
    await page.goto('http://localhost:8080/wp-admin');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
    await page.click('#wp-submit');
    await page.waitForSelector('.wrap');
  });

  test('should show debug messages and persist dropdown selection', async ({ page }) => {
    console.log('\n=== TESTING DROPDOWN PERSISTENCE FIX ===\n');
    
    // Navigate to settings page
    console.log('Step 1: Navigating to settings page...');
    await page.goto('http://localhost:8080/wp-admin/admin.php?page=wp-content-flow-settings');
    
    // Wait for page to load and JavaScript to execute
    await page.waitForSelector('[name="default_ai_provider"]');
    await page.waitForTimeout(2000);
    
    console.log('Step 2: Checking initial state...');
    
    // Get initial dropdown value
    const initialValue = await page.inputValue('[name="default_ai_provider"]');
    console.log(`Initial dropdown value: "${initialValue}"`);
    
    // Check for database value display
    const databaseValueLocator = page.locator('text=/Current database value/');
    const databaseValueExists = await databaseValueLocator.count() > 0;
    
    if (databaseValueExists) {
      const databaseValueText = await databaseValueLocator.textContent();
      console.log(`Database value display: "${databaseValueText}"`);
      
      // Extract the actual value from the text
      const match = databaseValueText.match(/Current database value:\s*(.+)/);
      const databaseValue = match ? match[1].trim() : 'unknown';
      console.log(`Extracted database value: "${databaseValue}"`);
      
      // Test if dropdown matches database value
      const valuesMatch = initialValue === databaseValue;
      console.log(`✓ Dropdown matches database: ${valuesMatch ? 'YES' : 'NO'}`);
    } else {
      console.log('⚠️  Database value display not found');
    }
    
    // Check dropdown styling (should have debug styling)
    const dropdownElement = page.locator('[name="default_ai_provider"]');
    const hasDebugStyling = await dropdownElement.evaluate(el => {
      const style = el.getAttribute('style');
      return style && (style.includes('border') || style.includes('background'));
    });
    console.log(`✓ Dropdown has debug styling: ${hasDebugStyling ? 'YES' : 'NO'}`);
    
    console.log('\nStep 3: Testing dropdown change and persistence...');
    
    // Get all available options
    const options = await page.$$eval('[name="default_ai_provider"] option', options => 
      options.map(opt => ({ value: opt.value, text: opt.textContent.trim(), selected: opt.selected }))
    );
    console.log('Available options:', options);
    
    // Find a different option to test with
    const differentOption = options.find(opt => opt.value !== initialValue && opt.value !== '');
    
    if (differentOption) {
      console.log(`Changing from "${initialValue}" to "${differentOption.value}"`);
      
      // Change dropdown value
      await page.selectOption('[name="default_ai_provider"]', differentOption.value);
      
      // Verify change was applied
      const changedValue = await page.inputValue('[name="default_ai_provider"]');
      console.log(`Value after change: "${changedValue}"`);
      
      // Save settings
      console.log('Saving settings...');
      await page.click('input[type="submit"]');
      
      // Wait for save to complete
      await page.waitForTimeout(3000);
      
      // Navigate back to settings if needed
      if (!page.url().includes('wp-content-flow-settings')) {
        await page.goto('http://localhost:8080/wp-admin/admin.php?page=wp-content-flow-settings');
        await page.waitForSelector('[name="default_ai_provider"]');
        await page.waitForTimeout(2000);
      }
      
      // Check value after save
      const savedValue = await page.inputValue('[name="default_ai_provider"]');
      console.log(`Value after save: "${savedValue}"`);
      
      // Test persistence after reload
      console.log('Testing persistence after page reload...');
      await page.reload();
      await page.waitForSelector('[name="default_ai_provider"]');
      await page.waitForTimeout(2000);
      
      const reloadedValue = await page.inputValue('[name="default_ai_provider"]');
      console.log(`Value after reload: "${reloadedValue}"`);
      
      // Check database value display after reload
      const newDatabaseValueLocator = page.locator('text=/Current database value/');
      const newDatabaseValueExists = await newDatabaseValueLocator.count() > 0;
      
      if (newDatabaseValueExists) {
        const newDatabaseValueText = await newDatabaseValueLocator.textContent();
        console.log(`Database value display after reload: "${newDatabaseValueText}"`);
      }
      
      console.log('\n=== TEST RESULTS ===');
      console.log(`Initial value: "${initialValue}"`);
      console.log(`Changed to: "${differentOption.value}"`);
      console.log(`After save: "${savedValue}"`);
      console.log(`After reload: "${reloadedValue}"`);
      
      const saveWorked = savedValue === differentOption.value;
      const persistenceWorked = reloadedValue === differentOption.value;
      
      console.log(`✓ Save functionality: ${saveWorked ? 'WORKING' : 'FAILED'}`);
      console.log(`✓ Persistence: ${persistenceWorked ? 'WORKING' : 'FAILED'}`);
      
      if (saveWorked && persistenceWorked) {
        console.log('🎉 DROPDOWN FIX IS WORKING!');
      } else {
        console.log('❌ DROPDOWN FIX NEEDS MORE WORK');
      }
      
      // Assert for test framework
      expect(saveWorked).toBe(true);
      expect(persistenceWorked).toBe(true);
      
    } else {
      console.log('⚠️  No different option available to test persistence');
    }
    
    // Take screenshot
    await page.screenshot({ 
      path: '/home/timl/dev/WP_ContentFlow/tmp/dropdown-test-result.png', 
      fullPage: true 
    });
  });
});