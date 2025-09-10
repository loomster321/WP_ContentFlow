<?php
/**
 * Test Google API Key Save Functionality
 * This tests the specific Google API key field that the user cannot save
 */

define('WP_USE_THEMES', false);
define('WP_ADMIN', true);
require_once('/var/www/html/wp-config.php');
require_once(ABSPATH . 'wp-admin/includes/admin.php');

wp_set_current_user(1);

echo "=== Testing Google API Key Save Issue ===\n";

// Test 1: Check if Google API key field exists in settings page
echo "1. Checking if Google API key field is registered:\n";

$settings_page = new WP_Content_Flow_Settings_Page();

// Trigger admin_init to register settings
do_action('admin_init');

// Check if the render method exists
if (method_exists($settings_page, 'render_google_api_key_field')) {
    echo "   ✅ render_google_api_key_field method exists\n";
} else {
    echo "   ❌ render_google_api_key_field method NOT found\n";
}

// Test 2: Check current settings structure
echo "\n2. Checking current settings structure:\n";
$current_settings = get_option('wp_content_flow_settings', array());
echo "   Current settings keys: " . implode(', ', array_keys($current_settings)) . "\n";

if (isset($current_settings['google_api_key'])) {
    echo "   ✅ google_api_key field exists in settings\n";
    echo "   Value: " . (empty($current_settings['google_api_key']) ? 'EMPTY' : 'SET') . "\n";
} else {
    echo "   ❌ google_api_key field NOT found in current settings\n";
}

// Test 3: Simulate saving Google API key
echo "\n3. Testing Google API key save:\n";

// Simulate form submission with Google key
$_POST = array(
    'option_page' => 'wp_content_flow_settings_group',
    'wp_content_flow_settings' => array(
        'openai_api_key' => 'sk-proj-test-openai',
        'anthropic_api_key' => 'sk-ant-test-anthropic',
        'google_api_key' => 'AIzaSyTest_Google_API_Key_12345',
        'default_ai_provider' => 'google',
        'cache_enabled' => '1',
        'requests_per_minute' => '20'
    )
);

$_GET['page'] = 'wp-content-flow-settings';
$nonce = wp_create_nonce('wp_content_flow_settings_group-options');
$_POST['_wpnonce'] = $nonce;

echo "   Simulating save with Google API key: AIzaSyTest_Google_API_Key_12345\n";

// Get the sanitize method and test it directly
$settings_data = $_POST['wp_content_flow_settings'];
$sanitized = $settings_page->sanitize_settings($settings_data);

echo "   Sanitized data: " . json_encode($sanitized) . "\n";

if (isset($sanitized['google_api_key'])) {
    echo "   ✅ Google API key survived sanitization\n";
} else {
    echo "   ❌ Google API key was lost during sanitization\n";
}

// Test actual save
$save_result = update_option('wp_content_flow_settings', $sanitized);
echo "   Save result: " . ($save_result ? 'SUCCESS' : 'FAILED') . "\n";

// Verify the save
$saved_settings = get_option('wp_content_flow_settings', array());
if (isset($saved_settings['google_api_key']) && !empty($saved_settings['google_api_key'])) {
    echo "   ✅ Google API key successfully saved: " . $saved_settings['google_api_key'] . "\n";
} else {
    echo "   ❌ Google API key NOT saved or is empty\n";
}

// Test 4: Check if form renders Google field
echo "\n4. Testing form rendering:\n";
ob_start();
try {
    $settings_page->render_google_api_key_field();
    $google_field_html = ob_get_clean();
    
    if (strpos($google_field_html, 'google_api_key') !== false) {
        echo "   ✅ Google API key field renders correctly\n";
        echo "   Field HTML length: " . strlen($google_field_html) . " characters\n";
    } else {
        echo "   ❌ Google API key field does not render properly\n";
    }
} catch (Exception $e) {
    ob_end_clean();
    echo "   ❌ Error rendering Google API key field: " . $e->getMessage() . "\n";
}

// Test 5: Check WordPress Settings API registration
echo "\n5. Checking WordPress Settings API registration:\n";
global $wp_settings_fields;

if (isset($wp_settings_fields['wp-content-flow']['wp_content_flow_providers']['google_api_key'])) {
    echo "   ✅ Google API key field is registered in WordPress Settings API\n";
} else {
    echo "   ❌ Google API key field is NOT registered in WordPress Settings API\n";
    
    if (isset($wp_settings_fields['wp-content-flow'])) {
        echo "   Available fields in wp-content-flow:\n";
        foreach ($wp_settings_fields['wp-content-flow'] as $section => $fields) {
            echo "     Section: $section\n";
            foreach ($fields as $field_id => $field) {
                echo "       - $field_id\n";
            }
        }
    }
}

echo "\n🎯 **DIAGNOSIS:**\n";
if (isset($saved_settings['google_api_key']) && !empty($saved_settings['google_api_key'])) {
    echo "✅ Google API key save functionality IS working\n";
    echo "The issue may be in the WordPress admin interface display\n";
} else {
    echo "❌ Google API key save functionality is NOT working\n";
    echo "Need to investigate the sanitization or save process\n";
}

?>