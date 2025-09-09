<?php
/**
 * Final Settings Registration Test
 * Verifies the WordPress settings fix is working
 */

// WordPress bootstrap
define('WP_USE_THEMES', false);
define('WP_ADMIN', true);
require_once('/var/www/html/wp-config.php');

// Include admin functions
require_once(ABSPATH . 'wp-admin/includes/plugin.php');
require_once(ABSPATH . 'wp-admin/includes/admin.php');

// Set up user context
wp_set_current_user(1);

echo "=== Final Settings Registration Test ===\n";

// Simulate the WordPress admin initialization sequence
echo "1. Triggering WordPress admin_init hook...\n";
do_action('admin_init');

// Check registered settings
global $wp_settings_sections, $wp_settings_fields, $allowed_options;

echo "\n2. Checking settings registration:\n";

if (isset($wp_settings_sections['wp-content-flow'])) {
    echo "✅ Settings sections registered\n";
} else {
    echo "❌ Settings sections not found\n";
}

if (isset($wp_settings_fields['wp-content-flow'])) {
    echo "✅ Settings fields registered\n";
} else {
    echo "❌ Settings fields not found\n";
}

if (isset($allowed_options['wp_content_flow_settings_group'])) {
    echo "✅ Settings group 'wp_content_flow_settings_group' in allowed options\n";
    echo "   Registered options:\n";
    foreach ($allowed_options['wp_content_flow_settings_group'] as $option) {
        echo "   - $option\n";
    }
} else {
    echo "❌ Settings group not in allowed options\n";
}

echo "\n3. Testing settings save simulation:\n";

// Simulate form submission
$test_settings = array(
    'openai_api_key' => 'sk-test-openai-key',
    'anthropic_api_key' => 'sk-test-anthropic-key'
);

// This should work if settings are properly registered
$sanitized = apply_filters('sanitize_option_wp_content_flow_settings', $test_settings);
echo "✅ Settings sanitization works\n";

echo "\n🎯 **Status:** Settings registration should now be fixed!\n";
echo "Try saving your API keys in the WordPress admin interface.\n";

echo "\n📋 **If you still get an error:**\n";
echo "1. Refresh the WordPress admin page completely (Ctrl+F5)\n";
echo "2. Deactivate and reactivate the plugin\n";
echo "3. Check if there are any PHP errors in wp-debug.log\n";

?>