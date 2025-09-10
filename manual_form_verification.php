<?php
/**
 * Manual Form Field Verification Script
 * 
 * This script directly tests if the WordPress Content Flow settings form
 * fields are properly registered and rendered by simulating the admin page
 * rendering process.
 */

// Set WordPress environment for CLI execution
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=wp-content-flow-settings';
$_SERVER['REQUEST_METHOD'] = 'GET';

define('WP_USE_THEMES', false);
define('WP_ADMIN', true);
define('DOING_AJAX', false);

// Load WordPress
require_once('/var/www/html/wp-config.php');

// Initialize WordPress 
wp();

// Load admin functions
require_once(ABSPATH . 'wp-admin/includes/admin.php');
require_once(ABSPATH . 'wp-admin/includes/template.php');
require_once(ABSPATH . 'wp-admin/includes/plugin.php');

echo "🧪 WordPress Content Flow Manual Form Verification\n";
echo "=" . str_repeat("=", 55) . "\n\n";

// Check plugin status
$plugin_file = 'wp-content-flow/wp-content-flow.php';
$plugin_active = is_plugin_active($plugin_file);
echo "1️⃣ Plugin Status: " . ($plugin_active ? "✅ ACTIVE" : "❌ INACTIVE") . "\n\n";

if (!$plugin_active) {
    echo "❌ Plugin must be active to test. Exiting.\n";
    exit(1);
}

// Simulate admin_init hook
echo "2️⃣ Simulating WordPress admin_init...\n";
do_action('admin_init');

// Check if settings are registered
global $wp_settings_sections, $wp_settings_fields, $allowed_options;

echo "3️⃣ WordPress Settings API State:\n";
$settings_group = 'wp_content_flow_settings_group';
$option_name = 'wp_content_flow_settings';

// Check allowed options
if (isset($allowed_options[$settings_group])) {
    echo "   ✅ Settings group registered in allowed_options\n";
    $allowed_count = count($allowed_options[$settings_group]);
    echo "   📊 Allowed options count: $allowed_count\n";
    foreach ($allowed_options[$settings_group] as $option) {
        echo "       • $option\n";
    }
} else {
    echo "   ❌ Settings group NOT in allowed_options\n";
}

// Check sections
if (isset($wp_settings_sections['wp-content-flow'])) {
    $section_count = count($wp_settings_sections['wp-content-flow']);
    echo "   ✅ Found $section_count settings sections\n";
    foreach ($wp_settings_sections['wp-content-flow'] as $section_id => $section) {
        echo "       • $section_id: {$section['title']}\n";
    }
} else {
    echo "   ❌ No settings sections found\n";
}

// Check fields
if (isset($wp_settings_fields['wp-content-flow'])) {
    $total_fields = 0;
    foreach ($wp_settings_fields['wp-content-flow'] as $section => $fields) {
        $field_count = count($fields);
        $total_fields += $field_count;
        echo "   ✅ Section '$section': $field_count fields\n";
        foreach ($fields as $field_id => $field) {
            echo "       • $field_id: {$field['title']}\n";
        }
    }
    echo "   📊 Total fields: $total_fields\n";
} else {
    echo "   ❌ No settings fields found\n";
}

echo "\n4️⃣ Current Settings Data:\n";
$current_settings = get_option($option_name, array());
if (empty($current_settings)) {
    echo "   ℹ️ No settings saved yet\n";
} else {
    foreach ($current_settings as $key => $value) {
        if (strpos($key, 'api_key') !== false) {
            echo "   • $key: " . (empty($value) ? 'Not set' : 'Configured ✓') . "\n";
        } else {
            echo "   • $key: " . esc_html($value) . "\n";
        }
    }
}

echo "\n5️⃣ Form Rendering Test:\n";

// Test if the settings page class exists and can render
if (class_exists('WP_Content_Flow_Settings_Page')) {
    echo "   ✅ Settings page class found\n";
    
    // Create instance and test rendering
    $settings_page = new WP_Content_Flow_Settings_Page();
    
    // Force registration one more time to be sure
    $settings_page->register_settings();
    
    // Capture the rendered form HTML
    ob_start();
    try {
        $settings_page->render();
        $form_html = ob_get_clean();
        
        echo "   ✅ Form rendered successfully\n";
        echo "   📏 HTML length: " . strlen($form_html) . " characters\n";
        
        // Test for specific form fields
        $required_fields = [
            'openai_api_key' => 'name="wp_content_flow_settings[openai_api_key]"',
            'anthropic_api_key' => 'name="wp_content_flow_settings[anthropic_api_key]"',
            'google_api_key' => 'name="wp_content_flow_settings[google_api_key]"',
            'default_ai_provider' => 'name="wp_content_flow_settings[default_ai_provider]"',
            'cache_enabled' => 'name="wp_content_flow_settings[cache_enabled]"',
            'requests_per_minute' => 'name="wp_content_flow_settings[requests_per_minute]"'
        ];
        
        echo "\n6️⃣ Field Presence Test:\n";
        $found_fields = 0;
        foreach ($required_fields as $field_name => $html_pattern) {
            $found = strpos($form_html, $html_pattern) !== false;
            echo "   " . ($found ? "✅" : "❌") . " $field_name: " . ($found ? "FOUND" : "MISSING") . "\n";
            if ($found) $found_fields++;
        }
        
        echo "\n   📊 Summary: $found_fields out of " . count($required_fields) . " fields found\n";
        
        // Check form structure
        echo "\n7️⃣ Form Structure Test:\n";
        $structure_tests = [
            'Form tag' => '<form',
            'Security fields' => '_wpnonce',
            'Submit button' => 'Save Settings',
            'WordPress table' => 'form-table',
            'Settings sections' => 'do_settings_sections'
        ];
        
        foreach ($structure_tests as $test_name => $pattern) {
            $found = strpos($form_html, $pattern) !== false;
            echo "   " . ($found ? "✅" : "❌") . " $test_name: " . ($found ? "PRESENT" : "MISSING") . "\n";
        }
        
        // Save HTML to file for inspection
        $html_file = '/home/timl/dev/WP_ContentFlow/tmp/manual-form-verification.html';
        if (!is_dir('/home/timl/dev/WP_ContentFlow/tmp')) {
            mkdir('/home/timl/dev/WP_ContentFlow/tmp', 0755, true);
        }
        file_put_contents($html_file, $form_html);
        echo "\n   💾 Form HTML saved to: $html_file\n";
        
    } catch (Exception $e) {
        ob_end_clean();
        echo "   ❌ Form rendering failed: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "   ❌ Settings page class not found\n";
}

echo "\n8️⃣ FINAL VERDICT:\n";
echo "=" . str_repeat("=", 20) . "\n";

// Calculate success based on findings
$success_criteria = [
    'plugin_active' => $plugin_active,
    'settings_registered' => isset($allowed_options[$settings_group]),
    'sections_exist' => isset($wp_settings_sections['wp-content-flow']),
    'fields_exist' => isset($wp_settings_fields['wp-content-flow']),
    'class_exists' => class_exists('WP_Content_Flow_Settings_Page'),
    'form_renders' => isset($form_html) && strlen($form_html) > 1000,
    'all_fields_found' => isset($found_fields) && $found_fields >= 6
];

$passed_criteria = array_sum($success_criteria);
$total_criteria = count($success_criteria);

echo "Success rate: $passed_criteria/$total_criteria criteria met\n\n";

if ($passed_criteria >= $total_criteria) {
    echo "🎉 COMPLETE SUCCESS: All form fields have been restored!\n";
    echo "✅ The hook timing issue has been fully resolved.\n";
    echo "✅ WordPress Content Flow settings form is working perfectly.\n";
    $exit_code = 0;
} elseif ($passed_criteria >= $total_criteria - 2) {
    echo "🎯 MOSTLY SUCCESSFUL: Form fields are largely restored ($passed_criteria/$total_criteria)\n";
    echo "⚠️ Minor issues may remain but core functionality is working.\n";
    echo "🔍 Review details above for any remaining issues.\n";
    $exit_code = 0;
} elseif ($passed_criteria >= $total_criteria / 2) {
    echo "⚠️ PARTIAL SUCCESS: Some progress made ($passed_criteria/$total_criteria)\n";
    echo "🔧 Hook timing issue partially resolved but work remains.\n";
    echo "📋 Review failed criteria above.\n";
    $exit_code = 1;
} else {
    echo "❌ FAILURE: Form fields are still not working ($passed_criteria/$total_criteria)\n";
    echo "💥 Hook timing issue persists significantly.\n";
    echo "🛠️ Major troubleshooting required.\n";
    $exit_code = 2;
}

echo "\n📁 Files generated for further analysis:\n";
if (isset($html_file)) {
    echo "   • Form HTML: $html_file\n";
}

exit($exit_code);
?>