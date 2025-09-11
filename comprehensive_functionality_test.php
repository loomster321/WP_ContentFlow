<?php
/**
 * Comprehensive Functionality Test for WP Content Flow Plugin
 * Run this inside WordPress to test all functionality
 */

// Check if we're running inside WordPress
if (!defined('ABSPATH')) {
    // Try to bootstrap WordPress
    $wp_load_paths = [
        dirname(__FILE__) . '/wp-load.php',
        dirname(__FILE__) . '/../wp-load.php',
        dirname(__FILE__) . '/../../wp-load.php',
        '/var/www/html/wp-load.php'
    ];
    
    $wp_loaded = false;
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $wp_loaded = true;
            break;
        }
    }
    
    if (!$wp_loaded) {
        die("WordPress not found. Please run this script inside WordPress or place it in the WordPress root directory.\n");
    }
}

echo "<h1>WP Content Flow - Comprehensive Functionality Test</h1>\n";
echo "<style>body { font-family: monospace; } .pass { color: green; } .fail { color: red; } .warning { color: orange; }</style>\n";

/**
 * Test results tracking
 */
$test_results = [];
$total_tests = 0;
$passed_tests = 0;

function test_result($test_name, $result, $details = '') {
    global $test_results, $total_tests, $passed_tests;
    $total_tests++;
    if ($result) {
        $passed_tests++;
        $status = '<span class="pass">PASS</span>';
    } else {
        $status = '<span class="fail">FAIL</span>';
    }
    echo "<div>{$status} {$test_name}: {$details}</div>\n";
    $test_results[$test_name] = ['result' => $result, 'details' => $details];
}

echo "<h2>1. Plugin Activation and Basic Setup</h2>\n";

// Test 1: Check if plugin is activated
$active_plugins = get_option('active_plugins', []);
$plugin_active = in_array('wp-content-flow/wp-content-flow.php', $active_plugins);
test_result('Plugin Activation', $plugin_active, $plugin_active ? 'Plugin is active' : 'Plugin is NOT active');

// Test 2: Check plugin constants
$constants_exist = defined('WP_CONTENT_FLOW_VERSION') && defined('WP_CONTENT_FLOW_PLUGIN_DIR');
test_result('Plugin Constants', $constants_exist, $constants_exist ? 'Constants defined: ' . WP_CONTENT_FLOW_VERSION : 'Constants missing');

// Test 3: Check plugin class exists
$main_class_exists = class_exists('WP_Content_Flow');
test_result('Main Plugin Class', $main_class_exists, $main_class_exists ? 'WP_Content_Flow class found' : 'WP_Content_Flow class NOT found');

echo "<h2>2. Database Tables</h2>\n";

global $wpdb;

// Test 4: Check database tables
$tables_to_check = [
    'wp_ai_workflows',
    'wp_ai_suggestions', 
    'wp_ai_content_history'
];

$table_results = [];
foreach ($tables_to_check as $table) {
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
    $table_results[$table] = $table_exists;
    test_result("Table: {$table}", $table_exists, $table_exists ? 'Table exists' : 'Table MISSING');
}

$all_tables_exist = array_reduce($table_results, function($carry, $item) { return $carry && $item; }, true);

echo "<h2>3. WordPress Integration</h2>\n";

// Test 5: Check admin menu
$admin_menu_registered = class_exists('WP_Content_Flow_Admin_Menu');
test_result('Admin Menu Class', $admin_menu_registered, $admin_menu_registered ? 'Admin menu class exists' : 'Admin menu class MISSING');

// Test 6: Check settings page
$settings_page_exists = class_exists('WP_Content_Flow_Settings_Page');
test_result('Settings Page Class', $settings_page_exists, $settings_page_exists ? 'Settings page class exists' : 'Settings page class MISSING');

// Test 7: Check plugin options
$plugin_settings = get_option('wp_content_flow_settings', false);
$settings_exist = $plugin_settings !== false;
test_result('Plugin Settings', $settings_exist, $settings_exist ? 'Settings found: ' . json_encode(array_keys($plugin_settings)) : 'No settings found');

echo "<h2>4. AI Core and Providers</h2>\n";

// Test 8: Check AI Core class
$ai_core_exists = class_exists('WP_Content_Flow_AI_Core');
test_result('AI Core Class', $ai_core_exists, $ai_core_exists ? 'AI Core class found' : 'AI Core class MISSING');

// Test 9: Check AI providers
$providers = [
    'WP_Content_Flow_OpenAI_Provider',
    'WP_Content_Flow_Anthropic_Provider'
];

foreach ($providers as $provider) {
    $provider_exists = class_exists($provider);
    test_result("Provider: {$provider}", $provider_exists, $provider_exists ? 'Provider class found' : 'Provider class MISSING');
}

echo "<h2>5. REST API Endpoints</h2>\n";

// Test 10: Check REST API class
$rest_api_exists = class_exists('WP_Content_Flow_REST_API');
test_result('REST API Class', $rest_api_exists, $rest_api_exists ? 'REST API class found' : 'REST API class MISSING');

// Test 11: Check if REST routes are registered
$rest_routes = rest_get_server()->get_routes();
$content_flow_routes = array_filter(array_keys($rest_routes), function($route) {
    return strpos($route, '/wp-content-flow/') !== false;
});

$routes_registered = !empty($content_flow_routes);
test_result('REST Routes', $routes_registered, $routes_registered ? 'Found routes: ' . implode(', ', $content_flow_routes) : 'No WP Content Flow routes found');

echo "<h2>6. Block Editor Integration</h2>\n";

// Test 12: Check if block assets are enqueued
$block_assets_exist = file_exists(WP_CONTENT_FLOW_PLUGIN_DIR . 'assets/js/blocks.js');
test_result('Block Assets File', $block_assets_exist, $block_assets_exist ? 'blocks.js file exists' : 'blocks.js file MISSING');

// Test 13: Check block assets size (should be compiled, not just imports)
if ($block_assets_exist) {
    $block_file_size = filesize(WP_CONTENT_FLOW_PLUGIN_DIR . 'assets/js/blocks.js');
    $size_acceptable = $block_file_size > 1000; // Should be larger if properly compiled
    test_result('Block Assets Size', $size_acceptable, "File size: {$block_file_size} bytes" . ($size_acceptable ? '' : ' - Too small, likely uncompiled'));
}

// Test 14: Check CSS files
$css_files = [
    'assets/css/editor.css',
    'assets/css/frontend.css'
];

foreach ($css_files as $css_file) {
    $css_exists = file_exists(WP_CONTENT_FLOW_PLUGIN_DIR . $css_file);
    test_result("CSS File: {$css_file}", $css_exists, $css_exists ? 'File exists' : 'File MISSING');
}

echo "<h2>7. Functionality Tests</h2>\n";

// Test 15: Test AI Core initialization
if ($ai_core_exists) {
    try {
        $ai_initialized = method_exists('WP_Content_Flow_AI_Core', 'init');
        test_result('AI Core Init Method', $ai_initialized, $ai_initialized ? 'Init method exists' : 'Init method MISSING');
    } catch (Exception $e) {
        test_result('AI Core Init', false, 'Error: ' . $e->getMessage());
    }
}

// Test 16: Check if settings can be saved
if ($settings_exist && is_array($plugin_settings)) {
    $has_ai_keys = isset($plugin_settings['openai_api_key']) || isset($plugin_settings['anthropic_api_key']);
    test_result('AI API Keys', $has_ai_keys, $has_ai_keys ? 'API keys are configured' : 'No API keys found');
}

echo "<h2>8. File Structure Verification</h2>\n";

// Test 17: Check critical files exist
$critical_files = [
    'includes/class-ai-core.php',
    'includes/class-workflow-engine.php', 
    'includes/class-content-manager.php',
    'includes/api/class-rest-api.php',
    'includes/admin/class-admin-menu.php'
];

foreach ($critical_files as $file) {
    $file_exists = file_exists(WP_CONTENT_FLOW_PLUGIN_DIR . $file);
    test_result("File: {$file}", $file_exists, $file_exists ? 'File exists' : 'File MISSING');
}

echo "<h2>Summary</h2>\n";

echo "<div><strong>Total Tests: {$total_tests}</strong></div>\n";
echo "<div><strong>Passed: <span class='pass'>{$passed_tests}</span></strong></div>\n";
echo "<div><strong>Failed: <span class='fail'>" . ($total_tests - $passed_tests) . "</span></strong></div>\n";
echo "<div><strong>Success Rate: " . round(($passed_tests / $total_tests) * 100, 1) . "%</strong></div>\n";

echo "<h2>Key Issues Identified</h2>\n";

$issues = [];

if (!$plugin_active) {
    $issues[] = "Plugin is not activated in WordPress";
}

if (!$all_tables_exist) {
    $issues[] = "Database tables are missing - plugin activation may have failed";
}

if (!$routes_registered) {
    $issues[] = "REST API routes are not registered - blocks won't work";
}

if ($block_assets_exist && filesize(WP_CONTENT_FLOW_PLUGIN_DIR . 'assets/js/blocks.js') < 1000) {
    $issues[] = "Block assets file is too small - likely contains uncompiled ES6 imports";
}

if (!empty($issues)) {
    echo "<ul>\n";
    foreach ($issues as $issue) {
        echo "<li class='fail'>{$issue}</li>\n";
    }
    echo "</ul>\n";
} else {
    echo "<div class='pass'>No critical issues identified!</div>\n";
}

echo "<h2>Recommendations</h2>\n";

$recommendations = [];

if (!$routes_registered) {
    $recommendations[] = "Check REST API registration in includes/api/class-rest-api.php";
}

if ($block_assets_exist && filesize(WP_CONTENT_FLOW_PLUGIN_DIR . 'assets/js/blocks.js') < 1000) {
    $recommendations[] = "Compile JavaScript files using the build process (npm run build)";
}

if (!empty($recommendations)) {
    echo "<ul>\n";
    foreach ($recommendations as $rec) {
        echo "<li class='warning'>{$rec}</li>\n";
    }
    echo "</ul>\n";
}

echo "<hr>\n";
echo "<small>Test completed at " . date('Y-m-d H:i:s') . "</small>\n";
?>