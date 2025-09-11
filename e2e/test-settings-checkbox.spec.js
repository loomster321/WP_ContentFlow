const { test, expect } = require('@playwright/test');

test.describe('Settings Cache Checkbox Persistence', () => {
  test('cache enabled checkbox should persist after save', async ({ page }) => {
    // Login to WordPress admin
    await page.goto('http://localhost:8080/wp-admin/');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
    await page.click('#wp-submit');
    
    // Wait for dashboard
    await page.waitForSelector('#wpadminbar', { timeout: 15000 });
    
    // Navigate to plugin settings
    await page.goto('http://localhost:8080/wp-admin/admin.php?page=wp-content-flow-settings');
    await page.waitForLoadState('networkidle');
    
    // Check initial state of cache checkbox
    const cacheCheckbox = page.locator('input[name="wp_content_flow_settings[cache_enabled]"]');
    const initialState = await cacheCheckbox.isChecked();
    console.log('Initial cache checkbox state:', initialState);
    
    // Toggle the checkbox
    if (initialState) {
      await cacheCheckbox.uncheck();
      console.log('Unchecked the cache checkbox');
    } else {
      await cacheCheckbox.check();
      console.log('Checked the cache checkbox');
    }
    
    // Save settings
    await page.click('#wp-content-flow-submit-btn');
    console.log('Clicked save button');
    
    // Wait for page reload
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    // Check if checkbox state persisted
    const newState = await cacheCheckbox.isChecked();
    console.log('Cache checkbox state after save:', newState);
    
    // Verify the state changed and persisted
    expect(newState).toBe(!initialState);
    
    // Reload page to double-check persistence
    await page.reload();
    await page.waitForLoadState('networkidle');
    
    const reloadedState = await cacheCheckbox.isChecked();
    console.log('Cache checkbox state after reload:', reloadedState);
    
    // Should still be in the new state
    expect(reloadedState).toBe(!initialState);
  });
});