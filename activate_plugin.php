<?php
/**
 * Activate WP Content Flow Plugin
 */

define('ABSPATH', '/var/www/html/');
require_once ABSPATH . 'wp-config.php';
require_once ABSPATH . 'wp-settings.php';

echo "🔧 Activating WP Content Flow Plugin...\n";

// Check if plugin exists
$plugin_file = 'wp-content-flow/wp-content-flow.php';
$plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;

if (!file_exists($plugin_path)) {
    echo "❌ Plugin file not found: $plugin_path\n";
    exit(1);
}

echo "✅ Plugin file exists: $plugin_path\n";

// Get current active plugins
$active_plugins = get_option('active_plugins', array());
echo "Currently active plugins: " . count($active_plugins) . "\n";

// Check if already active
if (in_array($plugin_file, $active_plugins)) {
    echo "✅ Plugin is already active!\n";
} else {
    echo "🔧 Plugin is inactive, activating...\n";
    
    // Add to active plugins
    $active_plugins[] = $plugin_file;
    
    // Update option
    $result = update_option('active_plugins', $active_plugins);
    
    if ($result) {
        echo "✅ Plugin activated successfully!\n";
        
        // Verify activation
        $updated_active = get_option('active_plugins', array());
        if (in_array($plugin_file, $updated_active)) {
            echo "✅ Activation verified in database\n";
        } else {
            echo "❌ Activation not verified in database\n";
        }
    } else {
        echo "❌ Failed to update active_plugins option\n";
    }
}

// Try to load the plugin to check for errors
echo "\n🔍 Testing plugin loading...\n";
try {
    include_once $plugin_path;
    echo "✅ Plugin file loaded without fatal errors\n";
} catch (Exception $e) {
    echo "❌ Plugin loading error: " . $e->getMessage() . "\n";
}

echo "\n🏁 Plugin activation complete\n";
?>