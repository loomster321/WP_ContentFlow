<?php
/**
 * Test Final Settings Fix
 */

define('WP_USE_THEMES', false);
define('WP_ADMIN', true);
require_once('/var/www/html/wp-config.php');
require_once(ABSPATH . 'wp-admin/includes/admin.php');

wp_set_current_user(1);

echo "=== Testing Final Settings Fix ===\n";

// Create settings page instance (this should trigger early registration)
$settings_page = new WP_Content_Flow_Settings_Page();
echo "✅ Settings page instance created\n";

// Trigger the init hook (this should run ensure_settings_registration)
do_action('init');
echo "✅ init hook triggered\n";

// Trigger admin_init hook (this should run register_settings)
do_action('admin_init');
echo "✅ admin_init hook triggered\n";

// Check results
global $allowed_options;

echo "\n📊 Final Status Check:\n";

if (isset($allowed_options['wp_content_flow_settings_group'])) {
    echo "✅ SUCCESS! Settings group is registered in allowed options\n";
    echo "   Registered options:\n";
    foreach ($allowed_options['wp_content_flow_settings_group'] as $option) {
        echo "   - $option\n";
    }
} else {
    echo "❌ Settings group still not found\n";
    echo "Available option groups:\n";
    if (isset($allowed_options)) {
        foreach (array_keys($allowed_options) as $group) {
            echo "   - $group\n";
        }
    }
}

echo "\n🎯 **Result:**\n";
if (isset($allowed_options['wp_content_flow_settings_group'])) {
    echo "✅ The settings save error should now be FIXED!\n";
    echo "✅ You can now save your API keys in the WordPress admin.\n";
} else {
    echo "❌ Settings registration still has issues.\n";
}

echo "\n📝 **Instructions:**\n";
echo "1. Go to your WordPress admin: http://localhost:8080/wp-admin\n";
echo "2. Navigate to Content Flow > Settings\n";
echo "3. Enter your API keys and click Save Settings\n";
echo "4. The error should no longer appear!\n";

?>