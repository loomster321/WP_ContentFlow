<?php
/**
 * Debug REST API Registration
 * 
 * This script helps debug why the REST API endpoints are not working
 * by testing WordPress's REST API registration directly.
 */

// Include WordPress
define('ABSPATH', '/var/www/html/');
require_once ABSPATH . 'wp-config.php';
require_once ABSPATH . 'wp-includes/wp-db.php';
require_once ABSPATH . 'wp-includes/pluggable.php';

// WordPress bootstrap
require_once ABSPATH . 'wp-settings.php';

echo "🔍 WordPress REST API Debug Report\n";
echo "===================================\n\n";

// Test basic WordPress functionality
echo "WordPress Version: " . get_bloginfo('version') . "\n";
echo "Site URL: " . get_site_url() . "\n";
echo "REST API Base: " . rest_url() . "\n\n";

// Check if plugin is active
$active_plugins = get_option('active_plugins', array());
$plugin_active = false;

foreach ($active_plugins as $plugin) {
    if (strpos($plugin, 'wp-content-flow') !== false) {
        $plugin_active = true;
        echo "✅ Plugin is active: $plugin\n";
        break;
    }
}

if (!$plugin_active) {
    echo "❌ WP Content Flow plugin is not active\n";
    echo "Active plugins:\n";
    foreach ($active_plugins as $plugin) {
        echo "  - $plugin\n";
    }
    exit(1);
}

// Check if REST API is enabled
if (!function_exists('rest_url')) {
    echo "❌ WordPress REST API is not available\n";
    exit(1);
}

echo "✅ WordPress REST API is available\n";

// Test basic REST API
$rest_server = rest_get_server();
echo "✅ REST Server initialized\n";

// Check available namespaces
$namespaces = $rest_server->get_namespaces();
echo "\nRegistered REST namespaces:\n";
foreach ($namespaces as $namespace) {
    echo "  - $namespace\n";
    
    if ($namespace === 'wp-content-flow/v1') {
        echo "    ✅ Our namespace is registered!\n";
    }
}

// Check if our namespace exists
$our_namespace_exists = in_array('wp-content-flow/v1', $namespaces);

if (!$our_namespace_exists) {
    echo "\n❌ Our namespace 'wp-content-flow/v1' is NOT registered\n";
    
    // Check if our REST API class exists
    if (class_exists('WP_Content_Flow_REST_API')) {
        echo "✅ WP_Content_Flow_REST_API class exists\n";
        
        // Try to manually initialize
        echo "🔧 Attempting manual initialization...\n";
        
        // Check if rest_api_init has been called
        $current_hook = current_action();
        echo "Current hook: $current_hook\n";
        
        // Manually trigger rest_api_init to see what happens
        do_action('rest_api_init');
        
        // Check again
        $namespaces_after = rest_get_server()->get_namespaces();
        $our_namespace_after = in_array('wp-content-flow/v1', $namespaces_after);
        
        if ($our_namespace_after) {
            echo "✅ Namespace registered after manual init\n";
        } else {
            echo "❌ Still not registered after manual init\n";
        }
        
    } else {
        echo "❌ WP_Content_Flow_REST_API class does not exist\n";
        
        // Check if the main plugin class exists
        if (class_exists('WP_Content_Flow')) {
            echo "✅ WP_Content_Flow main class exists\n";
        } else {
            echo "❌ WP_Content_Flow main class does not exist\n";
        }
    }
} else {
    echo "\n✅ Our namespace is properly registered\n";
    
    // Get routes for our namespace
    $routes = $rest_server->get_routes();
    echo "\nRegistered routes in our namespace:\n";
    
    foreach ($routes as $route => $handlers) {
        if (strpos($route, '/wp-content-flow/v1/') === 0) {
            echo "  - $route\n";
            
            foreach ($handlers as $handler) {
                $methods = $handler['methods'] ?? [];
                if (is_array($methods)) {
                    echo "    Methods: " . implode(', ', array_keys($methods)) . "\n";
                } else {
                    echo "    Methods: $methods\n";
                }
            }
        }
    }
}

// Check file existence
$plugin_dir = WP_PLUGIN_DIR . '/wp-content-flow/';
$rest_api_file = $plugin_dir . 'includes/api/class-rest-api.php';
$main_file = $plugin_dir . 'wp-content-flow.php';

echo "\nFile existence check:\n";
echo "Plugin directory: " . ($plugin_dir ? "✅" : "❌") . " $plugin_dir\n";
echo "Main plugin file: " . (file_exists($main_file) ? "✅" : "❌") . " $main_file\n";
echo "REST API file: " . (file_exists($rest_api_file) ? "✅" : "❌") . " $rest_api_file\n";

// Check if includes are working
if (file_exists($rest_api_file)) {
    echo "\n🔧 Testing direct inclusion...\n";
    
    // Check if constants are defined
    echo "WP_CONTENT_FLOW_PLUGIN_DIR defined: " . (defined('WP_CONTENT_FLOW_PLUGIN_DIR') ? "✅" : "❌") . "\n";
    
    if (!defined('WP_CONTENT_FLOW_PLUGIN_DIR')) {
        define('WP_CONTENT_FLOW_PLUGIN_DIR', $plugin_dir);
    }
    
    // Try to include the REST API file
    try {
        require_once $rest_api_file;
        echo "✅ REST API file included successfully\n";
        
        if (class_exists('WP_Content_Flow_REST_API')) {
            echo "✅ REST API class is available\n";
            
            // Try to instantiate
            $rest_api_instance = new WP_Content_Flow_REST_API();
            echo "✅ REST API instance created\n";
            
            // Manually call register_rest_routes
            $rest_api_instance->register_rest_routes();
            echo "✅ register_rest_routes called manually\n";
            
            // Check namespaces again
            $final_namespaces = rest_get_server()->get_namespaces();
            if (in_array('wp-content-flow/v1', $final_namespaces)) {
                echo "✅ Namespace NOW registered!\n";
                
                // Test the status endpoint
                $request = new WP_REST_Request('GET', '/wp-content-flow/v1/status');
                $response = rest_do_request($request);
                
                if ($response->is_error()) {
                    echo "❌ Status endpoint error: " . $response->get_error_message() . "\n";
                } else {
                    echo "✅ Status endpoint works!\n";
                    echo "Response: " . json_encode($response->get_data()) . "\n";
                }
            } else {
                echo "❌ Namespace still not registered\n";
            }
        } else {
            echo "❌ REST API class still not available after include\n";
        }
    } catch (Exception $e) {
        echo "❌ Error including REST API file: " . $e->getMessage() . "\n";
    }
}

echo "\n🏁 Debug complete\n";
?>