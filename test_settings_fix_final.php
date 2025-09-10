<?php
/**
 * Test the Settings API fix
 * 
 * This script will test if our WordPress Settings API registration is working properly.
 */

// Basic check - make sure we can include the WordPress environment
if (!file_exists('wp-config.php')) {
    echo "Error: This script must be run from the WordPress root directory\n";
    exit(1);
}

// Include WordPress
require_once 'wp-config.php';
require_once ABSPATH . 'wp-settings.php';

// Make sure we're in admin context
if (!is_admin()) {
    $_SERVER['SCRIPT_NAME'] = '/wp-admin/admin.php';
    define('WP_ADMIN', true);
}

echo "=== WP Content Flow Settings API Fix Test ===\n\n";

// Load the plugin files manually
$plugin_dir = '/home/timl/dev/WP_ContentFlow/wp-content-flow/';
require_once $plugin_dir . 'includes/admin/class-settings-page.php';
require_once $plugin_dir . 'includes/admin/class-admin-menu.php';

echo "1. Plugin classes loaded successfully\n";

// Test if functions are available
$functions_check = array(
    'register_setting' => function_exists('register_setting'),
    'add_settings_section' => function_exists('add_settings_section'),
    'add_settings_field' => function_exists('add_settings_field'),
    'do_settings_sections' => function_exists('do_settings_sections'),
    'settings_fields' => function_exists('settings_fields')
);

echo "2. WordPress Settings API function availability:\n";
foreach ($functions_check as $func => $available) {
    echo "   - {$func}: " . ($available ? "✓ Available" : "✗ Missing") . "\n";
}

if (in_array(false, $functions_check)) {
    echo "\nError: Required WordPress Settings API functions are not available\n";
    exit(1);
}

// Simulate admin_init environment
echo "\n3. Simulating admin_init hook environment...\n";

// Clear any existing hooks to ensure clean test
global $wp_filter;
if (isset($wp_filter['admin_init'])) {
    unset($wp_filter['admin_init']);
    $wp_filter['admin_init'] = new WP_Hook();
}

// Create admin menu instance (this will register the admin_init hook)
echo "   - Creating admin menu instance...\n";
$admin_menu = WP_Content_Flow_Admin_Menu::get_instance();

// Trigger admin_init manually
echo "   - Triggering admin_init hook...\n";
do_action('admin_init');

// Check global state after registration
global $wp_settings_sections, $wp_settings_fields, $allowed_options;

echo "\n4. Checking WordPress Settings API registration:\n";

// Check sections
if (isset($wp_settings_sections['wp-content-flow'])) {
    echo "   ✓ Sections registered for 'wp-content-flow': " . count($wp_settings_sections['wp-content-flow']) . " sections\n";
    foreach ($wp_settings_sections['wp-content-flow'] as $section_id => $section) {
        echo "     - {$section_id}: {$section['title']}\n";
    }
} else {
    echo "   ✗ NO sections found for 'wp-content-flow'\n";
}

// Check fields
if (isset($wp_settings_fields['wp-content-flow'])) {
    echo "   ✓ Fields registered for 'wp-content-flow'\n";
    $total_fields = 0;
    foreach ($wp_settings_fields['wp-content-flow'] as $section_id => $fields) {
        echo "     - Section '{$section_id}': " . count($fields) . " fields\n";
        $total_fields += count($fields);
        foreach ($fields as $field_id => $field) {
            echo "       - {$field_id}: {$field['title']}\n";
        }
    }
    echo "     Total fields: {$total_fields}\n";
} else {
    echo "   ✗ NO fields found for 'wp-content-flow'\n";
}

// Check allowed_options
if (isset($allowed_options['wp_content_flow_settings_group'])) {
    echo "   ✓ Settings group 'wp_content_flow_settings_group' in allowed_options\n";
    foreach ($allowed_options['wp_content_flow_settings_group'] as $option) {
        echo "     - {$option}\n";
    }
} else {
    echo "   ✗ Settings group 'wp_content_flow_settings_group' NOT in allowed_options\n";
}

// Test do_settings_sections output
echo "\n5. Testing do_settings_sections() output:\n";
ob_start();
do_settings_sections('wp-content-flow');
$sections_output = ob_get_clean();

if (empty($sections_output)) {
    echo "   ✗ do_settings_sections('wp-content-flow') produces NO output\n";
} else {
    echo "   ✓ do_settings_sections('wp-content-flow') produces output (" . strlen($sections_output) . " characters)\n";
    // Show a snippet of the output
    $preview = substr(strip_tags($sections_output), 0, 200);
    echo "     Preview: " . trim($preview) . "...\n";
}

// Test settings_fields output
echo "\n6. Testing settings_fields() output:\n";
ob_start();
settings_fields('wp_content_flow_settings_group');
$fields_output = ob_get_clean();

if (empty($fields_output)) {
    echo "   ✗ settings_fields('wp_content_flow_settings_group') produces NO output\n";
} else {
    echo "   ✓ settings_fields('wp_content_flow_settings_group') produces output (" . strlen($fields_output) . " characters)\n";
}

// Final assessment
echo "\n=== FINAL ASSESSMENT ===\n";

$sections_ok = isset($wp_settings_sections['wp-content-flow']) && !empty($wp_settings_sections['wp-content-flow']);
$fields_ok = isset($wp_settings_fields['wp-content-flow']) && !empty($wp_settings_fields['wp-content-flow']);
$options_ok = isset($allowed_options['wp_content_flow_settings_group']);
$output_ok = !empty($sections_output);

if ($sections_ok && $fields_ok && $options_ok && $output_ok) {
    echo "✓ ALL TESTS PASSED - WordPress Settings API registration is working correctly!\n";
    echo "  The settings form fields should now appear on the settings page.\n";
} else {
    echo "✗ SOME TESTS FAILED - Issues detected:\n";
    if (!$sections_ok) echo "  - Sections not registered properly\n";
    if (!$fields_ok) echo "  - Fields not registered properly\n";
    if (!$options_ok) echo "  - Settings group not in allowed_options\n";
    if (!$output_ok) echo "  - do_settings_sections produces no output\n";
}

echo "\n=== Test Complete ===\n";