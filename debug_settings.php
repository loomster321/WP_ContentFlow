<?php
/**
 * Debug script to check WordPress Settings API status
 * Place this in WordPress root directory and access via browser
 */

// Basic WordPress bootstrap
define('WP_USE_THEMES', false);
require_once(__DIR__ . '/wp-blog-header.php');

// Force admin context
if (!is_admin()) {
    require_once(ABSPATH . 'wp-admin/admin.php');
}

?><!DOCTYPE html>
<html>
<head>
    <title>WordPress Settings API Debug</title>
    <style>
        body { font-family: monospace; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
        h2 { border-bottom: 2px solid #ccc; }
    </style>
</head>
<body>
    <h1>WordPress Settings API Debug</h1>
    
    <?php
    echo "<h2>1. WordPress Environment</h2>";
    echo "<p class='info'>WordPress Version: " . get_bloginfo('version') . "</p>";
    echo "<p class='info'>Admin URL: " . admin_url() . "</p>";
    echo "<p class='info'>Plugin Dir: " . WP_PLUGIN_DIR . "</p>";
    echo "<p class='info'>Is Admin: " . (is_admin() ? 'Yes' : 'No') . "</p>";
    
    echo "<h2>2. Plugin Status</h2>";
    
    // Check if plugin directory exists
    $plugin_dir = WP_PLUGIN_DIR . '/wp-content-flow';
    if (is_dir($plugin_dir)) {
        echo "<p class='success'>✅ Plugin directory exists: {$plugin_dir}</p>";
        
        $plugin_file = $plugin_dir . '/wp-content-flow.php';
        if (file_exists($plugin_file)) {
            echo "<p class='success'>✅ Plugin main file exists</p>";
        } else {
            echo "<p class='error'>❌ Plugin main file not found</p>";
        }
        
        // Check if plugin is active
        if (is_plugin_active('wp-content-flow/wp-content-flow.php')) {
            echo "<p class='success'>✅ Plugin is active</p>";
        } else {
            echo "<p class='warning'>⚠️  Plugin is not active - attempting to activate...</p>";
            
            // Try to activate the plugin
            $result = activate_plugin('wp-content-flow/wp-content-flow.php');
            if (is_wp_error($result)) {
                echo "<p class='error'>❌ Plugin activation failed: " . $result->get_error_message() . "</p>";
            } else {
                echo "<p class='success'>✅ Plugin activated successfully</p>";
            }
        }
    } else {
        echo "<p class='error'>❌ Plugin directory not found: {$plugin_dir}</p>";
    }
    
    echo "<h2>3. Settings Class Status</h2>";
    
    // Force load the plugin to check class availability
    if (file_exists($plugin_dir . '/wp-content-flow.php')) {
        include_once($plugin_dir . '/wp-content-flow.php');
    }
    
    if (class_exists('WP_Content_Flow_Settings_Page')) {
        echo "<p class='success'>✅ Settings class is available</p>";
        
        // Instantiate the settings class
        $settings_page = new WP_Content_Flow_Settings_Page();
        echo "<p class='success'>✅ Settings class instantiated</p>";
        
        // Trigger admin_init to force registration
        do_action('admin_init');
        echo "<p class='info'>🔄 admin_init action triggered</p>";
        
    } else {
        echo "<p class='error'>❌ Settings class not available</p>";
        echo "<p class='info'>Available classes: " . implode(', ', get_declared_classes()) . "</p>";
    }
    
    echo "<h2>4. WordPress Settings API Status</h2>";
    
    global $allowed_options;
    $settings_group = 'wp_content_flow_settings_group';
    $option_name = 'wp_content_flow_settings';
    
    echo "<p class='info'>Settings Group: {$settings_group}</p>";
    echo "<p class='info'>Option Name: {$option_name}</p>";
    
    if (isset($allowed_options[$settings_group])) {
        echo "<p class='success'>✅ Settings group is registered in \$allowed_options</p>";
        
        echo "<p class='info'>Registered options in group:</p>";
        echo "<ul>";
        foreach ($allowed_options[$settings_group] as $option) {
            echo "<li>{$option}</li>";
        }
        echo "</ul>";
        
        if (in_array($option_name, $allowed_options[$settings_group])) {
            echo "<p class='success'>✅ Our option is in the allowed list</p>";
        } else {
            echo "<p class='error'>❌ Our option is NOT in the allowed list</p>";
        }
        
    } else {
        echo "<p class='error'>❌ Settings group is NOT registered</p>";
        echo "<p class='info'>Available groups: " . implode(', ', array_keys($allowed_options)) . "</p>";
    }
    
    echo "<h2>5. Current Settings</h2>";
    
    $current_settings = get_option($option_name, array());
    if (!empty($current_settings)) {
        echo "<p class='success'>✅ Settings exist in database</p>";
        echo "<pre>" . print_r($current_settings, true) . "</pre>";
    } else {
        echo "<p class='warning'>⚠️  No settings found in database</p>";
        
        // Create default settings for testing
        $default_settings = array(
            'default_ai_provider' => 'openai',
            'cache_enabled' => true,
            'openai_api_key' => '',
            'anthropic_api_key' => '',
            'google_api_key' => '',
            'requests_per_minute' => 10
        );
        
        update_option($option_name, $default_settings);
        echo "<p class='info'>🔄 Default settings created</p>";
    }
    
    echo "<h2>6. Settings Save Test</h2>";
    
    // Simulate a settings change
    $test_settings = get_option($option_name, array());
    $original_provider = isset($test_settings['default_ai_provider']) ? $test_settings['default_ai_provider'] : 'openai';
    $new_provider = ($original_provider === 'openai') ? 'anthropic' : 'openai';
    
    $test_settings['default_ai_provider'] = $new_provider;
    $test_settings['cache_enabled'] = !$test_settings['cache_enabled'];
    $test_settings['openai_api_key'] = 'sk-test-key-for-validation';
    
    echo "<p class='info'>Changing provider from '{$original_provider}' to '{$new_provider}'</p>";
    
    // Save the test settings
    $save_result = update_option($option_name, $test_settings);
    echo "<p class='info'>update_option() result: " . ($save_result ? 'true' : 'false') . "</p>";
    
    // Verify the save
    $verified_settings = get_option($option_name, array());
    $save_successful = ($verified_settings['default_ai_provider'] === $new_provider);
    
    if ($save_successful) {
        echo "<p class='success'>✅ Settings save test PASSED</p>";
    } else {
        echo "<p class='error'>❌ Settings save test FAILED</p>";
        echo "<p class='error'>Expected provider: {$new_provider}, Got: " . $verified_settings['default_ai_provider'] . "</p>";
    }
    
    echo "<h2>7. Summary</h2>";
    
    $issues = array();
    
    if (!is_plugin_active('wp-content-flow/wp-content-flow.php')) {
        $issues[] = "Plugin is not active";
    }
    
    if (!class_exists('WP_Content_Flow_Settings_Page')) {
        $issues[] = "Settings class not available";
    }
    
    if (!isset($allowed_options[$settings_group])) {
        $issues[] = "Settings group not registered";
    } elseif (!in_array($option_name, $allowed_options[$settings_group])) {
        $issues[] = "Option not in allowed list";
    }
    
    if (!$save_successful) {
        $issues[] = "Settings save test failed";
    }
    
    if (empty($issues)) {
        echo "<p class='success'>🎉 ALL TESTS PASSED - WordPress Settings API fix is working!</p>";
    } else {
        echo "<p class='error'>❌ Issues detected:</p>";
        echo "<ul>";
        foreach ($issues as $issue) {
            echo "<li class='error'>{$issue}</li>";
        }
        echo "</ul>";
    }
    
    echo "<h2>8. Manual Test Link</h2>";
    
    if (is_plugin_active('wp-content-flow/wp-content-flow.php')) {
        $settings_url = admin_url('admin.php?page=wp-content-flow-settings');
        echo "<p class='info'>📝 <a href='{$settings_url}' target='_blank'>Open Settings Page for Manual Test</a></p>";
    } else {
        echo "<p class='warning'>⚠️  Plugin needs to be activated first</p>";
    }
    ?>
</body>
</html>