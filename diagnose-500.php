<?php
/**
 * WordPress 500 Error Diagnostic Script
 * Purpose: Identify the root cause of post save 500 errors in Docker environment
 * Usage: Copy to WordPress root and access via browser
 */

// Load WordPress
require_once('wp-load.php');

// Start output
header('Content-Type: text/plain');
echo "===========================================\n";
echo "WordPress 500 Error Diagnostic Report\n";
echo "===========================================\n\n";

// 1. PHP Configuration Check
echo "1. PHP CONFIGURATION\n";
echo "--------------------\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n";
echo "Max Execution Time: " . ini_get('max_execution_time') . "\n";
echo "Post Max Size: " . ini_get('post_max_size') . "\n";
echo "Upload Max Filesize: " . ini_get('upload_max_filesize') . "\n";
echo "Error Reporting: " . error_reporting() . "\n";
echo "Display Errors: " . ini_get('display_errors') . "\n\n";

// 2. WordPress Configuration
echo "2. WORDPRESS CONFIGURATION\n";
echo "--------------------------\n";
echo "WordPress Version: " . get_bloginfo('version') . "\n";
echo "Site URL: " . get_site_url() . "\n";
echo "Home URL: " . get_home_url() . "\n";
echo "WP_DEBUG: " . (defined('WP_DEBUG') ? (WP_DEBUG ? 'true' : 'false') : 'not defined') . "\n";
echo "WP_DEBUG_LOG: " . (defined('WP_DEBUG_LOG') ? (WP_DEBUG_LOG ? 'true' : 'false') : 'not defined') . "\n";
echo "WP_MEMORY_LIMIT: " . (defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : 'not defined') . "\n\n";

// 3. Database Connection Test
echo "3. DATABASE CONNECTION\n";
echo "----------------------\n";
global $wpdb;
$db_test = $wpdb->get_var("SELECT 1");
echo "Database Connection: " . ($db_test == 1 ? "OK" : "FAILED") . "\n";
echo "Database Name: " . DB_NAME . "\n";
echo "Database User: " . DB_USER . "\n";
echo "Database Host: " . DB_HOST . "\n";

// Test write permissions
$test_time = current_time('mysql');
$result = $wpdb->insert(
    $wpdb->options,
    array(
        'option_name' => '_test_write_' . time(),
        'option_value' => 'test',
        'autoload' => 'no'
    )
);
echo "Database Write Test: " . ($result ? "OK" : "FAILED - " . $wpdb->last_error) . "\n";

// Clean up test entry
if ($result) {
    $wpdb->delete($wpdb->options, array('option_name' => '_test_write_' . time()));
}
echo "\n";

// 4. File System Permissions
echo "4. FILE SYSTEM PERMISSIONS\n";
echo "--------------------------\n";
$wp_content = WP_CONTENT_DIR;
echo "wp-content writable: " . (is_writable($wp_content) ? "YES" : "NO") . "\n";
echo "wp-content/uploads exists: " . (is_dir($wp_content . '/uploads') ? "YES" : "NO") . "\n";
if (is_dir($wp_content . '/uploads')) {
    echo "wp-content/uploads writable: " . (is_writable($wp_content . '/uploads') ? "YES" : "NO") . "\n";
}
echo "wp-content/plugins writable: " . (is_writable($wp_content . '/plugins') ? "YES" : "NO") . "\n";
echo "\n";

// 5. REST API Test
echo "5. REST API STATUS\n";
echo "------------------\n";
$rest_url = get_rest_url(null, 'wp/v2/posts');
echo "REST API URL: " . $rest_url . "\n";

// Check if REST API is enabled
$rest_enabled = get_option('rest_api_enabled', 1);
echo "REST API Enabled: " . ($rest_enabled ? "YES" : "NO") . "\n";

// Test REST API endpoint
$response = wp_remote_get($rest_url, array(
    'timeout' => 10,
    'sslverify' => false
));

if (is_wp_error($response)) {
    echo "REST API GET Test: FAILED - " . $response->get_error_message() . "\n";
} else {
    $status_code = wp_remote_retrieve_response_code($response);
    echo "REST API GET Test: " . ($status_code == 200 ? "OK" : "FAILED - HTTP " . $status_code) . "\n";
}
echo "\n";

// 6. User Capabilities
echo "6. USER CAPABILITIES\n";
echo "--------------------\n";
$current_user = wp_get_current_user();
if ($current_user->ID) {
    echo "Current User: " . $current_user->user_login . " (ID: " . $current_user->ID . ")\n";
    echo "Can Edit Posts: " . (current_user_can('edit_posts') ? "YES" : "NO") . "\n";
    echo "Can Publish Posts: " . (current_user_can('publish_posts') ? "YES" : "NO") . "\n";
    echo "Is Administrator: " . (current_user_can('manage_options') ? "YES" : "NO") . "\n";
} else {
    echo "No user logged in\n";
}
echo "\n";

// 7. Active Plugins
echo "7. ACTIVE PLUGINS\n";
echo "-----------------\n";
$active_plugins = get_option('active_plugins');
if (empty($active_plugins)) {
    echo "No active plugins\n";
} else {
    foreach ($active_plugins as $plugin) {
        echo "- " . $plugin . "\n";
    }
}
echo "\n";

// 8. Error Log Check
echo "8. RECENT ERROR LOGS\n";
echo "--------------------\n";
$debug_log = WP_CONTENT_DIR . '/debug.log';
if (file_exists($debug_log)) {
    $log_contents = file_get_contents($debug_log);
    $lines = explode("\n", $log_contents);
    $recent_lines = array_slice($lines, -10);
    echo "Last 10 lines from debug.log:\n";
    foreach ($recent_lines as $line) {
        if (!empty($line)) {
            echo $line . "\n";
        }
    }
} else {
    echo "No debug.log file found\n";
}
echo "\n";

// 9. Test Post Creation
echo "9. POST CREATION TEST\n";
echo "---------------------\n";
if ($current_user->ID && current_user_can('edit_posts')) {
    $post_data = array(
        'post_title'    => 'Diagnostic Test Post ' . time(),
        'post_content'  => 'This is a test post created by the diagnostic script.',
        'post_status'   => 'draft',
        'post_author'   => $current_user->ID,
        'post_type'     => 'post'
    );

    $post_id = wp_insert_post($post_data, true);

    if (is_wp_error($post_id)) {
        echo "Post Creation: FAILED - " . $post_id->get_error_message() . "\n";
    } else {
        echo "Post Creation: SUCCESS - Post ID: " . $post_id . "\n";
        // Clean up test post
        wp_delete_post($post_id, true);
        echo "Test post deleted\n";
    }
} else {
    echo "Cannot test - user not logged in or lacks permissions\n";
}
echo "\n";

// 10. Apache/Server Info
echo "10. SERVER INFORMATION\n";
echo "----------------------\n";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
echo "PHP SAPI: " . php_sapi_name() . "\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script Name: " . $_SERVER['SCRIPT_NAME'] . "\n";

// Check mod_rewrite
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    echo "mod_rewrite: " . (in_array('mod_rewrite', $modules) ? "ENABLED" : "DISABLED") . "\n";
}
echo "\n";

echo "===========================================\n";
echo "End of Diagnostic Report\n";
echo "===========================================\n";
?>