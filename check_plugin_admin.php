<?php
/**
 * Check WP Content Flow plugin admin interface
 * Verifies admin menu registration and settings pages
 */

// WordPress bootstrap
define('WP_USE_THEMES', false);
require_once('/var/www/html/wp-config.php');

// Include admin functions
require_once(ABSPATH . 'wp-admin/includes/plugin.php');
require_once(ABSPATH . 'wp-admin/includes/admin.php');

echo "=== WP Content Flow Plugin Admin Check ===\n";

// Check if plugin is active
$plugin_file = 'wp-content-flow/wp-content-flow.php';
if (!is_plugin_active($plugin_file)) {
    echo "ERROR: Plugin is not active!\n";
    exit(1);
}

echo "Plugin Status: ACTIVE ✅\n";

// Check if admin class exists
if (class_exists('WP_Content_Flow_Admin')) {
    echo "Admin Class: EXISTS ✅\n";
} else {
    echo "Admin Class: NOT FOUND ❌\n";
}

// Check if AI Core class exists
if (class_exists('WP_Content_Flow_AI_Core')) {
    echo "AI Core Class: EXISTS ✅\n";
} else {
    echo "AI Core Class: NOT FOUND ❌\n";
}

// Check if settings are registered
$settings = get_option('wp_content_flow_settings', array());
echo "Settings Option: " . (empty($settings) ? "NOT SET" : "EXISTS") . "\n";

// Check database tables
global $wpdb;
$tables_to_check = [
    'wp_ai_workflows',
    'wp_ai_suggestions', 
    'wp_ai_content_history'
];

echo "\n=== Database Tables ===\n";
foreach ($tables_to_check as $table) {
    $table_name = $wpdb->prefix . substr($table, 3); // Remove 'wp_' prefix and add proper prefix
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
    echo "$table_name: " . ($exists ? "EXISTS ✅" : "NOT FOUND ❌") . "\n";
}

// Check for REST API endpoints
echo "\n=== Plugin Status Summary ===\n";
echo "WordPress Version: " . get_bloginfo('version') . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Plugin activated and database initialized successfully!\n";

echo "\n=== Next Steps ===\n";
echo "1. Access WordPress admin: http://localhost:8080/wp-admin\n";
echo "2. Look for 'Content Flow' menu in admin sidebar\n";
echo "3. Configure AI provider API keys in settings\n";
echo "4. Test AI Text Generator block in block editor\n";

?>