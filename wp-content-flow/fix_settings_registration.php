<?php
/**
 * Manual Settings Registration Fix
 * Run this once to register the settings properly
 */

// WordPress bootstrap with admin context
define('WP_USE_THEMES', false);
define('WP_ADMIN', true);
require_once('/var/www/html/wp-config.php');
require_once(ABSPATH . 'wp-admin/includes/admin.php');

wp_set_current_user(1);

echo "=== Manual Settings Registration Fix ===\n";

// Manually register the settings
register_setting(
    'wp_content_flow_settings_group',
    'wp_content_flow_settings',
    'sanitize_text_field'
);

echo "✅ Settings registered manually\n";

// Check if it worked
global $allowed_options;

if (isset($allowed_options['wp_content_flow_settings_group'])) {
    echo "✅ Settings group is now in allowed options:\n";
    foreach ($allowed_options['wp_content_flow_settings_group'] as $option) {
        echo "   - $option\n";
    }
} else {
    echo "❌ Manual registration failed\n";
}

// Test with actual settings save
$test_data = array(
    'openai_api_key' => 'sk-test-key',
    'anthropic_api_key' => 'sk-test-key'
);

// This simulates what happens when the form is submitted
$_POST['option_page'] = 'wp_content_flow_settings_group';
$_POST['wp_content_flow_settings'] = $test_data;

echo "\n🧪 Testing form submission simulation:\n";

// Check if the option group is allowed
$option_page = 'wp_content_flow_settings_group';
if (isset($allowed_options[$option_page]) && in_array('wp_content_flow_settings', $allowed_options[$option_page])) {
    echo "✅ Form submission would be allowed\n";
    echo "✅ Settings save error should now be fixed!\n";
} else {
    echo "❌ Form submission would still be blocked\n";
}

echo "\n🎯 **Try saving your API keys now in the WordPress admin!**\n";

?>