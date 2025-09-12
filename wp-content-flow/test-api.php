<?php
/**
 * Test REST API registration
 */

// Load WordPress
require_once '/var/www/html/wp-load.php';

// Check if our plugin is active
$active_plugins = get_option('active_plugins', array());
echo "Active plugins:\n";
print_r($active_plugins);

// Check if our REST API is registered
$server = rest_get_server();
$routes = $server->get_routes();

echo "\n\nREST routes containing 'wp-content-flow':\n";
foreach ($routes as $route => $handlers) {
    if (strpos($route, 'wp-content-flow') !== false) {
        echo "  - $route\n";
    }
}

// Try to manually trigger rest_api_init
echo "\n\nManually triggering rest_api_init...\n";
do_action('rest_api_init');

// Check again
$routes = $server->get_routes();
echo "\nAfter manual trigger - REST routes containing 'wp-content-flow':\n";
foreach ($routes as $route => $handlers) {
    if (strpos($route, 'wp-content-flow') !== false) {
        echo "  - $route\n";
    }
}

// Check if the class exists and is loaded
echo "\n\nClass checks:\n";
echo "WP_Content_Flow_REST_API exists: " . (class_exists('WP_Content_Flow_REST_API') ? 'YES' : 'NO') . "\n";

// Try to instantiate directly
if (class_exists('WP_Content_Flow_REST_API')) {
    echo "Creating new instance...\n";
    $api = new WP_Content_Flow_REST_API();
    
    // Force registration
    echo "Forcing registration...\n";
    $api->register_rest_routes();
    
    // Check one more time
    $routes = $server->get_routes();
    echo "\nAfter forced registration - REST routes containing 'wp-content-flow':\n";
    foreach ($routes as $route => $handlers) {
        if (strpos($route, 'wp-content-flow') !== false) {
            echo "  - $route\n";
        }
    }
}