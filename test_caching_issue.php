<?php
/**
 * Caching Issue Test for Dropdown Persistence
 * 
 * This script tests for WordPress caching issues that might cause
 * the form to show stale values.
 */

// WordPress bootstrap
require_once('/var/www/html/wp-config.php');

echo "=== WP Content Flow Caching Issue Test ===\n\n";

// Test 1: Multiple reads without cache clearing
echo "1. Multiple reads (checking for cache consistency):\n";
for ($i = 1; $i <= 3; $i++) {
    $settings = get_option('wp_content_flow_settings', array());
    $provider = isset($settings['default_ai_provider']) ? $settings['default_ai_provider'] : 'NOT SET';
    echo "   Read $i: '$provider'\n";
}

// Test 2: Check WordPress cache status
echo "\n2. WordPress cache status:\n";
$cache_enabled = wp_using_ext_object_cache();
echo "   External object cache enabled: " . ($cache_enabled ? 'YES' : 'NO') . "\n";

$alloptions = wp_cache_get('alloptions', 'options');
if ($alloptions && isset($alloptions['wp_content_flow_settings'])) {
    echo "   Option in alloptions cache: YES\n";
    $cached_data = maybe_unserialize($alloptions['wp_content_flow_settings']);
    $cached_provider = isset($cached_data['default_ai_provider']) ? $cached_data['default_ai_provider'] : 'NOT SET';
    echo "   Cached provider value: '$cached_provider'\n";
} else {
    echo "   Option in alloptions cache: NO\n";
}

// Test 3: Force cache clear and read
echo "\n3. Force cache clear and re-read:\n";
wp_cache_delete('wp_content_flow_settings', 'options');
wp_cache_delete('alloptions', 'options');
$fresh_settings = get_option('wp_content_flow_settings', array());
$fresh_provider = isset($fresh_settings['default_ai_provider']) ? $fresh_settings['default_ai_provider'] : 'NOT SET';
echo "   After cache clear: '$fresh_provider'\n";

// Test 4: Direct database vs cached read comparison
echo "\n4. Database vs Cache comparison:\n";

// Direct database read
global $wpdb;
$db_row = $wpdb->get_row("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'wp_content_flow_settings'");
$db_data = $db_row ? maybe_unserialize($db_row->option_value) : array();
$db_provider = isset($db_data['default_ai_provider']) ? $db_data['default_ai_provider'] : 'NOT SET';
echo "   Database direct: '$db_provider'\n";

// WordPress get_option (potentially cached)
$wp_settings = get_option('wp_content_flow_settings', array());
$wp_provider = isset($wp_settings['default_ai_provider']) ? $wp_settings['default_ai_provider'] : 'NOT SET';
echo "   WordPress get_option: '$wp_provider'\n";

echo "   Values match: " . ($db_provider === $wp_provider ? 'YES' : 'NO') . "\n";

// Test 5: Test potential autoload issue
echo "\n5. Autoload setting test:\n";
$autoload_row = $wpdb->get_row("SELECT autoload FROM {$wpdb->options} WHERE option_name = 'wp_content_flow_settings'");
$autoload = $autoload_row ? $autoload_row->autoload : 'unknown';
echo "   Autoload setting: '$autoload'\n";

// Test 6: Test for multiple option entries (shouldn't happen but let's check)
echo "\n6. Multiple option entries check:\n";
$all_rows = $wpdb->get_results("SELECT option_id, option_value FROM {$wpdb->options} WHERE option_name = 'wp_content_flow_settings'");
echo "   Number of entries: " . count($all_rows) . "\n";
if (count($all_rows) > 1) {
    echo "   WARNING: Multiple entries found!\n";
    foreach ($all_rows as $row) {
        $data = maybe_unserialize($row->option_value);
        $provider = isset($data['default_ai_provider']) ? $data['default_ai_provider'] : 'NOT SET';
        echo "   Entry {$row->option_id}: '$provider'\n";
    }
}

// Test 7: Simulate form rendering scenario
echo "\n7. Form rendering simulation:\n";

// This simulates what happens when the settings page loads
function simulate_form_rendering() {
    // Clear any transients that might interfere
    delete_transient('wp_content_flow_settings_saved');
    
    // Get settings exactly as the form would
    $option_name = 'wp_content_flow_settings';
    $settings = get_option($option_name, array());
    $value = isset($settings['default_ai_provider']) ? $settings['default_ai_provider'] : 'openai';
    
    return $value;
}

$form_value = simulate_form_rendering();
echo "   Form would show: '$form_value'\n";

// Test 8: Test with WordPress admin context simulation
echo "\n8. Admin context simulation:\n";

// Set up admin context
if (!defined('WP_ADMIN')) {
    define('WP_ADMIN', true);
}

// Simulate loading in admin
set_current_screen('admin_page_wp-content-flow-settings');
$admin_settings = get_option('wp_content_flow_settings', array());
$admin_provider = isset($admin_settings['default_ai_provider']) ? $admin_settings['default_ai_provider'] : 'NOT SET';
echo "   In admin context: '$admin_provider'\n";

echo "\n=== Caching Test Complete ===\n";

// Recommendations
echo "\nRECOMMENDATIONS:\n";
if ($db_provider !== $wp_provider) {
    echo "- ISSUE CONFIRMED: Database and WordPress cache have different values\n";
    echo "- SOLUTION: Add explicit cache clearing in settings save process\n";
    echo "- SOLUTION: Use wp_cache_delete() after update_option()\n";
} else {
    echo "- Cache and database values match\n";
    echo "- Issue may be in form rendering timing or JavaScript interference\n";
}

echo "- Consider adding debugging to the actual settings page render\n";
echo "- Test form rendering immediately after page load vs after user interaction\n";
?>