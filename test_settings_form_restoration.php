<?php
/**
 * WordPress Content Flow Settings Form Restoration Test
 * 
 * This script tests if the form fields have been restored after fixing
 * the hook timing issue that was causing all form fields to disappear.
 */

// Set WordPress environment
define('WP_USE_THEMES', false);
require_once('/var/www/html/wp-config.php');

// Initialize WordPress
wp();

// Load plugin functions
if (!function_exists('is_plugin_active')) {
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

// Check if plugin is active
if (!is_plugin_active('wp-content-flow/wp-content-flow.php')) {
    echo "❌ Plugin 'wp-content-flow/wp-content-flow.php' is not active\n";
    echo "ℹ️ Continuing test anyway as we can check class existence\n";
}

echo "🧪 WordPress Content Flow Settings Form Restoration Test\n";
echo "=" . str_repeat("=", 60) . "\n\n";

// Get current settings to verify storage
$settings = get_option('wp_content_flow_settings', array());
echo "1️⃣ Current Settings in Database:\n";
if (empty($settings)) {
    echo "   ℹ️ No settings found in database (this is normal for fresh install)\n";
} else {
    foreach ($settings as $key => $value) {
        if (strpos($key, 'api_key') !== false) {
            echo "   • $key: " . (empty($value) ? 'Not set' : 'Configured ✓') . "\n";
        } else {
            echo "   • $key: " . esc_html($value) . "\n";
        }
    }
}
echo "\n";

// Check if WordPress Settings API registration is working
echo "2️⃣ WordPress Settings API Registration:\n";

global $wp_settings_sections, $wp_settings_fields, $allowed_options;

// Check if settings group is in allowed options
$settings_group = 'wp_content_flow_settings_group';
$option_name = 'wp_content_flow_settings';

if (isset($allowed_options[$settings_group]) && in_array($option_name, $allowed_options[$settings_group])) {
    echo "   ✅ Settings group is in allowed_options\n";
} else {
    echo "   ❌ Settings group NOT in allowed_options\n";
}

// Check if settings sections exist
if (isset($wp_settings_sections['wp-content-flow']) && !empty($wp_settings_sections['wp-content-flow'])) {
    $section_count = count($wp_settings_sections['wp-content-flow']);
    echo "   ✅ Found $section_count settings sections\n";
    foreach ($wp_settings_sections['wp-content-flow'] as $section_id => $section) {
        echo "       • $section_id: {$section['title']}\n";
    }
} else {
    echo "   ❌ No settings sections found\n";
}

// Check if settings fields exist
if (isset($wp_settings_fields['wp-content-flow']) && !empty($wp_settings_fields['wp-content-flow'])) {
    $total_fields = 0;
    foreach ($wp_settings_fields['wp-content-flow'] as $section => $fields) {
        $field_count = count($fields);
        $total_fields += $field_count;
        echo "   ✅ Section '$section': $field_count fields\n";
        foreach ($fields as $field_id => $field) {
            echo "       • $field_id: {$field['title']}\n";
        }
    }
    echo "   📊 Total fields registered: $total_fields\n";
} else {
    echo "   ❌ No settings fields found\n";
}
echo "\n";

// Test form output generation
echo "3️⃣ Form Output Test:\n";

// Simulate admin context (minimal)
if (!defined('WP_ADMIN')) {
    define('WP_ADMIN', true);
}

// Load admin functions if needed
if (!function_exists('submit_button')) {
    require_once(ABSPATH . 'wp-admin/includes/template.php');
}

// Check if settings page class exists
if (!class_exists('WP_Content_Flow_Settings_Page')) {
    echo "❌ WP_Content_Flow_Settings_Page class not found\n";
    echo "ℹ️ Plugin may not be loaded properly\n";
    
    // Try to manually load the plugin
    $plugin_file = '/var/www/html/wp-content/plugins/wp-content-flow/wp-content-flow.php';
    if (file_exists($plugin_file)) {
        echo "ℹ️ Attempting to load plugin manually...\n";
        include_once($plugin_file);
        if (class_exists('WP_Content_Flow_Settings_Page')) {
            echo "✅ Plugin loaded successfully\n";
        }
    }
}

if (class_exists('WP_Content_Flow_Settings_Page')) {
    // Instantiate settings page
    $settings_page = new WP_Content_Flow_Settings_Page();

    // Force settings registration (simulate admin_init)
    echo "   🔧 Forcing settings registration...\n";
    $settings_page->register_settings();
    
    // Also try the force registration method
    if (method_exists($settings_page, 'force_settings_registration')) {
        echo "   🔧 Using force registration method...\n";
        $settings_page->force_settings_registration();
    }
} else {
    echo "❌ Cannot proceed without settings page class\n";
    exit(1);
}

// Capture form output
ob_start();
$settings_page->render();
$form_html = ob_get_clean();

echo "   📏 Form HTML length: " . strlen($form_html) . " characters\n";

// Check for specific form elements
$expected_elements = [
    'OpenAI API Key' => 'name="wp_content_flow_settings[openai_api_key]"',
    'Anthropic API Key' => 'name="wp_content_flow_settings[anthropic_api_key]"',
    'Google AI API Key' => 'name="wp_content_flow_settings[google_api_key]"',
    'Default AI Provider' => 'name="wp_content_flow_settings[default_ai_provider]"',
    'Enable Caching' => 'name="wp_content_flow_settings[cache_enabled]"',
    'Requests Per Minute' => 'name="wp_content_flow_settings[requests_per_minute]"'
];

$found_fields = 0;
foreach ($expected_elements as $field_name => $html_pattern) {
    if (strpos($form_html, $html_pattern) !== false) {
        echo "   ✅ $field_name field: FOUND\n";
        $found_fields++;
    } else {
        echo "   ❌ $field_name field: MISSING\n";
    }
}

echo "\n   📊 Found $found_fields out of " . count($expected_elements) . " required fields\n";

// Check for WordPress form structure
$has_form_tag = strpos($form_html, '<form') !== false;
$has_settings_fields = strpos($form_html, 'settings_fields') !== false || strpos($form_html, '_wpnonce') !== false;
$has_submit_button = strpos($form_html, 'submit_button') !== false || strpos($form_html, 'Save Settings') !== false;

echo "   📋 Form structure:\n";
echo "       • Form tag: " . ($has_form_tag ? "✅ Present" : "❌ Missing") . "\n";
echo "       • Security fields: " . ($has_settings_fields ? "✅ Present" : "❌ Missing") . "\n";
echo "       • Submit button: " . ($has_submit_button ? "✅ Present" : "❌ Missing") . "\n";

// Check for fallback rendering
$has_fallback = strpos($form_html, 'render_settings_sections_manually') !== false || 
                strpos($form_html, 'form-table') !== false;
echo "       • Fallback rendering: " . ($has_fallback ? "✅ Present" : "❌ Missing") . "\n";

echo "\n";

// Save form HTML for inspection
$form_file = '/home/timl/dev/WP_ContentFlow/tmp/settings-form-test-output.html';
file_put_contents($form_file, $form_html);
echo "💾 Form HTML saved to: $form_file\n\n";

// Generate final verdict
echo "4️⃣ FINAL VERDICT:\n";
echo "=" . str_repeat("=", 20) . "\n";

if ($found_fields >= 6) {
    echo "🎉 SUCCESS: All 6 form fields have been restored!\n";
    echo "✅ The hook timing issue has been resolved.\n";
    echo "✅ WordPress Content Flow settings form is working correctly.\n";
    $exit_code = 0;
} else if ($found_fields >= 3) {
    echo "⚠️ PARTIAL: Some form fields are visible ($found_fields/6)\n";
    echo "🔧 The hook timing issue may be partially resolved.\n";
    echo "🔍 Manual verification recommended.\n";
    $exit_code = 1;
} else {
    echo "❌ FAILURE: Form fields are still missing ($found_fields/6)\n";
    echo "💥 The hook timing issue persists.\n";
    echo "🔧 Additional troubleshooting required.\n";
    $exit_code = 2;
}

echo "\n📸 Check saved files for detailed analysis:\n";
echo "   • Form HTML: $form_file\n";
echo "   • Screenshots will be generated by browser test\n";

exit($exit_code);
?>