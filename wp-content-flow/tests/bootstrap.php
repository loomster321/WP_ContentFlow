<?php
/**
 * PHPUnit Bootstrap for WordPress AI Content Flow Plugin
 */

// Define test constants
define( 'WP_CONTENT_FLOW_TESTS_DIR', __DIR__ );
define( 'WP_CONTENT_FLOW_PLUGIN_DIR', dirname( __DIR__ ) );

// WordPress test environment
if ( ! defined( 'WP_TESTS_DIR' ) ) {
    define( 'WP_TESTS_DIR', '/tmp/wordpress-tests-lib' );
}

// WordPress core directory  
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', '/var/www/html/' );
}

// Load WordPress test environment
if ( file_exists( WP_TESTS_DIR . '/includes/functions.php' ) ) {
    require_once WP_TESTS_DIR . '/includes/functions.php';
} else {
    // Fallback for Docker environment
    require_once ABSPATH . 'wp-includes/functions.php';
}

// Load plugin before WordPress initializes
function _manually_load_plugin() {
    require WP_CONTENT_FLOW_PLUGIN_DIR . '/wp-content-flow.php';
}

if ( function_exists( 'tests_add_filter' ) ) {
    tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );
    
    // Start the WordPress testing environment
    require WP_TESTS_DIR . '/includes/bootstrap.php';
} else {
    // Manual plugin loading for development environment
    _manually_load_plugin();
    
    // Mock WordPress functions if not available
    if ( ! function_exists( 'wp_create_nonce' ) ) {
        function wp_create_nonce( $action = -1 ) {
            return 'test-nonce';
        }
    }
    
    if ( ! function_exists( 'current_user_can' ) ) {
        function current_user_can( $capability ) {
            return true; // Allow all capabilities in tests
        }
    }
    
    if ( ! function_exists( 'get_current_user_id' ) ) {
        function get_current_user_id() {
            return 1; // Mock admin user
        }
    }
    
    if ( ! function_exists( 'rest_url' ) ) {
        function rest_url( $path = '', $scheme = 'rest' ) {
            return 'http://localhost:8080/wp-json/' . ltrim( $path, '/' );
        }
    }
}

// Load Composer autoloader
if ( file_exists( WP_CONTENT_FLOW_PLUGIN_DIR . '/vendor/autoload.php' ) ) {
    require WP_CONTENT_FLOW_PLUGIN_DIR . '/vendor/autoload.php';
}

// Test utilities
require_once WP_CONTENT_FLOW_TESTS_DIR . '/includes/class-test-case.php';
require_once WP_CONTENT_FLOW_TESTS_DIR . '/includes/class-mock-ai-provider.php';

// Set up test database for contract tests
if ( defined( 'DB_NAME' ) && DB_NAME === 'wordpress_test' ) {
    // Ensure test database exists and is clean
    global $wpdb;
    
    if ( $wpdb ) {
        // Clean up any existing test data
        $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type LIKE 'ai_%'" );
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%wp_content_flow%'" );
    }
}