<?php
/**
 * Debug WordPress Hooks and Settings Registration
 */

// WordPress bootstrap with admin context
define('WP_USE_THEMES', false);
define('WP_ADMIN', true);
require_once('/var/www/html/wp-config.php');
require_once(ABSPATH . 'wp-admin/includes/admin.php');

wp_set_current_user(1);

echo "=== Debug Hooks and Settings Registration ===\n";

// Add debug hooks to see what's being called
add_action('admin_init', function() {
    echo "🔄 admin_init hook is firing\n";
}, 1);

// Check if our settings page class is hooking into admin_init
$settings_page = new WP_Content_Flow_Settings_Page();

// Check the registered hooks
global $wp_filter;
if (isset($wp_filter['admin_init'])) {
    echo "\n📋 Hooks registered for admin_init:\n";
    foreach ($wp_filter['admin_init']->callbacks as $priority => $callbacks) {
        echo "   Priority $priority:\n";
        foreach ($callbacks as $callback) {
            if (is_array($callback['function'])) {
                $class = is_object($callback['function'][0]) ? get_class($callback['function'][0]) : $callback['function'][0];
                echo "     - {$class}::{$callback['function'][1]}\n";
            } else {
                echo "     - {$callback['function']}\n";
            }
        }
    }
}

echo "\n🔄 Triggering admin_init...\n";
do_action('admin_init');

echo "\n📊 Checking final results:\n";
global $wp_settings_sections, $wp_settings_fields, $allowed_options;

if (isset($allowed_options['wp_content_flow_settings_group'])) {
    echo "✅ Settings registered successfully!\n";
} else {
    echo "❌ Settings still not registered\n";
    
    // Let's manually call the method to see if it works
    echo "\n🔧 Manual registration test:\n";
    $settings_page->register_settings();
    
    if (isset($allowed_options['wp_content_flow_settings_group'])) {
        echo "✅ Manual registration works!\n";
        echo "❌ The issue is with the admin_init hook binding\n";
    } else {
        echo "❌ Manual registration also fails\n";
    }
}

?>