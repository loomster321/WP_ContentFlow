<?php
/**
 * Debug script to test settings save functionality directly
 */

// WordPress loading simulation for debugging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Simulate the POST data that would be sent by the form
$test_post_data = array(
    'option_page' => 'wp_content_flow_settings_group',
    '_wpnonce' => 'test_nonce',
    'wp_content_flow_settings' => array(
        'default_ai_provider' => 'google',
        'cache_enabled' => '1',
        'requests_per_minute' => '25',
        'openai_api_key' => 'test-openai-key',
        'anthropic_api_key' => 'test-anthropic-key'
    )
);

echo "=== WP Content Flow Settings Debug Test ===\n\n";

echo "1. Test POST data structure:\n";
print_r($test_post_data);

echo "\n2. URL-encoded form data (what curl sends):\n";
$form_data = http_build_query($test_post_data);
echo $form_data . "\n";

echo "\n3. URL-decoded back:\n";
parse_str($form_data, $decoded);
print_r($decoded);

// Test the sanitization function
echo "\n4. Testing sanitization logic:\n";

function sanitize_settings($input) {
    $sanitized = array();
    
    // Sanitize API keys
    if (isset($input['openai_api_key'])) {
        $sanitized['openai_api_key'] = trim($input['openai_api_key']);
    }
    
    if (isset($input['anthropic_api_key'])) {
        $sanitized['anthropic_api_key'] = trim($input['anthropic_api_key']);
    }
    
    if (isset($input['google_api_key'])) {
        $sanitized['google_api_key'] = trim($input['google_api_key']);
    }
    
    // Sanitize configuration settings
    if (isset($input['default_ai_provider'])) {
        $allowed_providers = array('openai', 'anthropic', 'google');
        $sanitized['default_ai_provider'] = in_array($input['default_ai_provider'], $allowed_providers) 
            ? $input['default_ai_provider'] : 'openai';
    }
    
    if (isset($input['cache_enabled'])) {
        $sanitized['cache_enabled'] = (bool) $input['cache_enabled'];
    }
    
    if (isset($input['requests_per_minute'])) {
        $sanitized['requests_per_minute'] = abs((int) $input['requests_per_minute']);
        if ($sanitized['requests_per_minute'] < 1) {
            $sanitized['requests_per_minute'] = 10;
        }
    }
    
    return $sanitized;
}

$sanitized = sanitize_settings($test_post_data['wp_content_flow_settings']);
echo "Original data:\n";
print_r($test_post_data['wp_content_flow_settings']);
echo "\nSanitized data:\n";
print_r($sanitized);

// Test the nonce verification logic
echo "\n5. Form field extraction test:\n";

// Simulate what happens when curl submits the form
function extract_form_field($form_data, $field_name) {
    $pattern = '/name="' . preg_quote($field_name, '/') . '"[^>]*value="([^"]*)"[^>]*(?:selected|checked)?/';
    if (preg_match($pattern, $form_data, $matches)) {
        return $matches[1];
    }
    
    // Also try selected option format
    $pattern = '/name="' . preg_quote($field_name, '/') . '".*?<option[^>]+value="([^"]*)"[^>]*selected/s';
    if (preg_match($pattern, $form_data, $matches)) {
        return $matches[1];
    }
    
    return null;
}

// Test expected form field names
$expected_fields = array(
    'wp_content_flow_settings[default_ai_provider]',
    'wp_content_flow_settings[cache_enabled]', 
    'wp_content_flow_settings[requests_per_minute]',
    '_wpnonce',
    'option_page'
);

foreach ($expected_fields as $field) {
    echo "Field: $field\n";
    echo "  URL encoded: " . urlencode($field) . "\n";
    echo "  PHP array equivalent: " . str_replace(array('[', ']'), array("['", "']"), $field) . "\n";
}

echo "\n6. WordPress option update simulation:\n";

// Simulate the WordPress update_option function
$current_settings = array(
    'default_ai_provider' => 'anthropic',
    'cache_enabled' => true,
    'requests_per_minute' => 10,
    'openai_api_key' => '',
    'anthropic_api_key' => 'existing-anthropic-key'
);

echo "Current settings (simulated database):\n";
print_r($current_settings);

echo "\nNew settings to save:\n";
print_r($sanitized);

// Simulate update_option behavior
$settings_changed = ($current_settings !== $sanitized);
echo "\nSettings changed: " . ($settings_changed ? 'YES' : 'NO') . "\n";

if ($settings_changed) {
    echo "Simulated save successful!\n";
    $final_settings = $sanitized;
} else {
    echo "No changes detected, update_option would return false\n";
    $final_settings = $current_settings;
}

echo "\nFinal settings:\n";
print_r($final_settings);

// Check if provider changed specifically
echo "\n7. Provider change analysis:\n";
echo "Old provider: " . $current_settings['default_ai_provider'] . "\n";
echo "New provider: " . $sanitized['default_ai_provider'] . "\n";
echo "Provider changed: " . ($current_settings['default_ai_provider'] !== $sanitized['default_ai_provider'] ? 'YES' : 'NO') . "\n";

echo "\n=== END DEBUG TEST ===\n";
?>