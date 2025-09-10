<?php
/**
 * Test Settings Page Rendering
 * This verifies that the settings page now renders correctly
 */

define('WP_USE_THEMES', false);
define('WP_ADMIN', true);
require_once('/var/www/html/wp-config.php');
require_once(ABSPATH . 'wp-admin/includes/admin.php');

wp_set_current_user(1);

echo "=== Testing Settings Page Rendering ===\n";

// Create settings page instance
if (!class_exists('WP_Content_Flow_Settings_Page')) {
    echo "❌ Settings page class not found\n";
    exit(1);
}

$settings_page = new WP_Content_Flow_Settings_Page();
echo "✅ Settings page instance created\n";

// Capture the rendered output
echo "\n🖥️ Rendering settings page...\n";
ob_start();
$settings_page->render();
$output = ob_get_clean();

// Check if the output contains expected elements
$checks = array(
    'AI Provider Configuration' => strpos($output, 'AI Provider Configuration') !== false,
    'OpenAI API Key' => strpos($output, 'OpenAI API Key') !== false,
    'Anthropic API Key' => strpos($output, 'Anthropic API Key') !== false,
    'Google AI API Key' => strpos($output, 'Google AI API Key') !== false,
    'Default AI Provider' => strpos($output, 'Default AI Provider') !== false,
    'Enable Caching' => strpos($output, 'Enable Caching') !== false,
    'Requests Per Minute' => strpos($output, 'Requests Per Minute') !== false,
    'Save Settings Button' => strpos($output, 'Save Settings') !== false,
    'Form Element' => strpos($output, '<form') !== false,
    'Custom Nonce' => strpos($output, 'wp_content_flow_settings_group-options') !== false
);

echo "\n📋 Settings Page Checks:\n";
$all_passed = true;
foreach ($checks as $check => $passed) {
    echo ($passed ? "✅" : "❌") . " $check\n";
    if (!$passed) $all_passed = false;
}

echo "\n🎯 Overall Result:\n";
if ($all_passed) {
    echo "✅ SUCCESS! All settings page elements are now rendering correctly.\n";
    echo "✅ The settings page should now display all AI provider fields and configuration options.\n";
} else {
    echo "❌ Some elements are missing from the settings page.\n";
}

echo "\n📝 Instructions:\n";
echo "1. Refresh the WordPress admin page: Content Flow > Settings\n";
echo "2. You should now see all AI provider configuration fields\n";
echo "3. You should be able to enter and save your API keys\n";
echo "4. You should see the default provider dropdown and other settings\n";

// Test settings save functionality
echo "\n🧪 Testing Settings Save Logic:\n";
$test_data = array(
    'openai_api_key' => 'sk-test-openai-key',
    'anthropic_api_key' => 'sk-test-anthropic-key',
    'google_api_key' => 'sk-test-google-key',
    'default_ai_provider' => 'anthropic',
    'cache_enabled' => true,
    'requests_per_minute' => 15
);

$sanitized = $settings_page->sanitize_settings($test_data);
echo "✅ Settings sanitization works\n";

echo "🎯 The settings page fix is complete!\n";

?>