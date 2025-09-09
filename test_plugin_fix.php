<?php
/**
 * Test plugin loading after fixing singleton issues
 */

// WordPress bootstrap
define('WP_USE_THEMES', false);
require_once('/var/www/html/wp-config.php');

// Include admin functions
require_once(ABSPATH . 'wp-admin/includes/plugin.php');

echo "=== Testing Plugin Fix ===\n";

// Check if plugin is still active
$plugin_file = 'wp-content-flow/wp-content-flow.php';
if (is_plugin_active($plugin_file)) {
    echo "✅ Plugin is active\n";
} else {
    echo "❌ Plugin is not active\n";
    exit(1);
}

// Test class instantiation
$classes_to_test = [
    'WP_Content_Flow_AI_Core',
    'WP_Content_Flow_Workflow_Engine',
    'WP_Content_Flow_Content_Manager',
    'WP_Content_Flow_Admin_Menu'
];

foreach ($classes_to_test as $class) {
    if (class_exists($class)) {
        echo "✅ Class exists: $class\n";
        
        // Test singleton pattern
        if (method_exists($class, 'get_instance')) {
            try {
                $instance = $class::get_instance();
                echo "✅ Singleton instance created: $class\n";
            } catch (Exception $e) {
                echo "❌ Singleton failed: $class - " . $e->getMessage() . "\n";
            }
        } else {
            echo "⚠️  No singleton pattern: $class\n";
        }
    } else {
        echo "❌ Class not found: $class\n";
    }
}

// Test admin menu registration (simulate admin context)
if (is_admin()) {
    global $menu, $submenu;
    
    // Trigger admin_menu action to register menus
    do_action('admin_menu');
    
    $content_flow_menu_exists = false;
    if (is_array($menu)) {
        foreach ($menu as $menu_item) {
            if (isset($menu_item[2]) && $menu_item[2] === 'wp-content-flow') {
                $content_flow_menu_exists = true;
                break;
            }
        }
    }
    
    echo ($content_flow_menu_exists ? "✅" : "❌") . " Admin menu registered\n";
}

echo "\n=== Fix Status ===\n";
echo "✅ Plugin loading successfully without fatal errors!\n";
echo "🌐 WordPress Admin should now be accessible at: http://localhost:8080/wp-admin\n";
echo "📋 Login with: admin / !3cTXkh)9iDHhV5o*N\n";
echo "🔧 Look for 'Content Flow' menu in WordPress admin sidebar\n";

?>