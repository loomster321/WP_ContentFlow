<?php
/**
 * Verify $allowed_options in actual WordPress admin request
 * Access this via: http://localhost:8080/verify_allowed_options.php
 */

// WordPress bootstrap with admin context
define('WP_ADMIN', true);
require_once(__DIR__ . '/wp-config.php');

// Output as plain text
header('Content-Type: text/plain');

echo "WordPress Settings API Registration Verification\n";
echo "==============================================\n\n";

echo "Environment:\n";
echo "- is_admin(): " . (is_admin() ? 'YES' : 'NO') . "\n";
echo "- WP_ADMIN: " . (defined('WP_ADMIN') && WP_ADMIN ? 'YES' : 'NO') . "\n";
echo "- WordPress Version: " . get_bloginfo('version') . "\n\n";

// Trigger admin_init to ensure settings are registered
echo "Triggering admin_init action...\n";
do_action('admin_init');

echo "Checking global \$allowed_options...\n";
global $allowed_options;

if (is_array($allowed_options)) {
    echo "✅ \$allowed_options is initialized (" . count($allowed_options) . " groups)\n";
    
    $settings_group = 'wp_content_flow_settings_group';
    if (isset($allowed_options[$settings_group])) {
        echo "✅ Found our settings group: '$settings_group'\n";
        echo "✅ Allowed options: " . implode(', ', $allowed_options[$settings_group]) . "\n";
        
        if (in_array('wp_content_flow_settings', $allowed_options[$settings_group])) {
            echo "✅ Our option 'wp_content_flow_settings' is in the allowed list\n";
            echo "\n🎉 WORDPRESS SETTINGS API REGISTRATION: WORKING!\n";
        } else {
            echo "❌ Our option 'wp_content_flow_settings' is NOT in the allowed list\n";
        }
    } else {
        echo "❌ Our settings group '$settings_group' not found\n";
        echo "Available groups: " . implode(', ', array_keys($allowed_options)) . "\n";
    }
} else {
    echo "❌ \$allowed_options is not properly initialized\n";
    echo "Type: " . gettype($allowed_options) . "\n";
}

echo "\n";
echo "Current plugin settings test:\n";
$current_settings = get_option('wp_content_flow_settings', array());
if (!empty($current_settings)) {
    echo "✅ Settings exist in database\n";
    foreach ($current_settings as $key => $value) {
        $display = is_bool($value) ? ($value ? 'true' : 'false') : $value;
        if (strpos($key, 'api_key') !== false && !empty($value)) {
            $display = '[configured]';
        }
        echo "  $key: $display\n";
    }
} else {
    echo "⚠️  No settings found in database\n";
}
?>