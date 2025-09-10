<?php
/**
 * Database Test for Dropdown Persistence Issue
 * 
 * This script directly tests the WordPress database to see what values
 * are actually saved and what the form should be displaying.
 */

// WordPress bootstrap
require_once('/var/www/html/wp-config.php');

echo "=== WP Content Flow Dropdown Database Test ===\n\n";

// Test 1: Check current saved settings
echo "1. Current settings in database:\n";
$settings = get_option('wp_content_flow_settings', array());
if (empty($settings)) {
    echo "   No settings found in database!\n";
} else {
    echo "   Settings found:\n";
    foreach ($settings as $key => $value) {
        if (strpos($key, 'api_key') !== false) {
            echo "   - $key: " . (empty($value) ? 'Empty' : '[SET - ' . strlen($value) . ' characters]') . "\n";
        } else {
            echo "   - $key: " . var_export($value, true) . "\n";
        }
    }
}

echo "\n";

// Test 2: Check specifically the default_ai_provider setting
echo "2. Default AI Provider setting:\n";
$default_provider = isset($settings['default_ai_provider']) ? $settings['default_ai_provider'] : 'NOT SET';
echo "   Database value: '$default_provider'\n";

// Test 3: Simulate what the form field should show
echo "\n3. Form field simulation:\n";
$form_value = isset($settings['default_ai_provider']) ? $settings['default_ai_provider'] : 'openai';
echo "   Form should default to: '$form_value'\n";

$providers = array(
    'openai' => 'OpenAI (GPT)',
    'anthropic' => 'Anthropic (Claude)', 
    'google' => 'Google AI (Gemini)'
);

echo "   HTML options should be:\n";
foreach ($providers as $value => $label) {
    $selected = selected($form_value, $value, false);
    echo "   <option value=\"$value\"$selected>$label</option>\n";
}

// Test 4: Test a manual save and read
echo "\n4. Testing manual save/read cycle:\n";

// Save a test value
$test_settings = $settings;
$test_settings['default_ai_provider'] = 'anthropic';
$save_result = update_option('wp_content_flow_settings', $test_settings);
echo "   Save result: " . ($save_result ? 'SUCCESS' : 'FAILED/UNCHANGED') . "\n";

// Read it back
$read_back = get_option('wp_content_flow_settings', array());
$read_provider = isset($read_back['default_ai_provider']) ? $read_back['default_ai_provider'] : 'NOT SET';
echo "   Read back value: '$read_provider'\n";
echo "   Save/read consistency: " . ($read_provider === 'anthropic' ? 'PASS' : 'FAIL') . "\n";

// Test 5: Check WordPress option directly in database
echo "\n5. Direct database query:\n";
global $wpdb;
$option_row = $wpdb->get_row("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'wp_content_flow_settings'");
if ($option_row) {
    $db_value = maybe_unserialize($option_row->option_value);
    echo "   Raw database value: " . print_r($db_value, true);
    if (isset($db_value['default_ai_provider'])) {
        echo "   default_ai_provider in raw DB: '" . $db_value['default_ai_provider'] . "'\n";
    }
} else {
    echo "   No option found in database!\n";
}

// Test 6: Check for potential caching issues
echo "\n6. Cache test:\n";
wp_cache_delete('wp_content_flow_settings', 'options');
$fresh_settings = get_option('wp_content_flow_settings', array());
$fresh_provider = isset($fresh_settings['default_ai_provider']) ? $fresh_settings['default_ai_provider'] : 'NOT SET';
echo "   After cache clear: '$fresh_provider'\n";

// Test 7: WordPress selected() function test
echo "\n7. WordPress selected() function test:\n";
$test_value = 'anthropic';
foreach ($providers as $value => $label) {
    $selected_attr = selected($test_value, $value, false);
    echo "   selected('$test_value', '$value', false) = '$selected_attr'\n";
}

echo "\n=== Test Complete ===\n";
echo "If database shows correct values but form doesn't, the issue is in form rendering.\n";
echo "If database shows wrong values, the issue is in save logic.\n";
?>