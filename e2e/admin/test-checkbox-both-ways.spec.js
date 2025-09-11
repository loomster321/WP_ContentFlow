const { test, expect } = require('@playwright/test');

test.describe('Settings Checkbox Both Ways', () => {
  test('cache checkbox toggles both directions correctly', async ({ page }) => {
    // Login to WordPress admin
    await page.goto('http://localhost:8080/wp-admin/');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
    await page.click('#wp-submit');
    await page.waitForSelector('#wpadminbar', { timeout: 15000 });
    
    // Navigate to plugin settings
    await page.goto('http://localhost:8080/wp-admin/admin.php?page=wp-content-flow-settings');
    await page.waitForLoadState('networkidle');
    
    const cacheCheckbox = page.locator('input[name="wp_content_flow_settings[cache_enabled]"]');
    
    // Test 1: If checked, uncheck it
    let initialState = await cacheCheckbox.isChecked();
    console.log('Initial state:', initialState);
    
    if (initialState) {
      await cacheCheckbox.uncheck();
      console.log('Unchecked the checkbox');
    } else {
      await cacheCheckbox.check();
      console.log('Checked the checkbox');
    }
    
    await page.click('#wp-content-flow-submit-btn');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    
    let afterSave1 = await cacheCheckbox.isChecked();
    console.log('After first save:', afterSave1);
    expect(afterSave1).toBe(!initialState);
    
    // Test 2: Toggle it back
    if (afterSave1) {
      await cacheCheckbox.uncheck();
      console.log('Unchecked the checkbox (second toggle)');
    } else {
      await cacheCheckbox.check();
      console.log('Checked the checkbox (second toggle)');
    }
    
    await page.click('#wp-content-flow-submit-btn');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    
    let afterSave2 = await cacheCheckbox.isChecked();
    console.log('After second save:', afterSave2);
    expect(afterSave2).toBe(initialState);
    
    console.log('✅ Checkbox toggles correctly in both directions');
  });
});