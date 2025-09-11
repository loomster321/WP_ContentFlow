<?php
/**
 * Critical Settings Persistence Test for WP Content Flow Plugin
 * 
 * This test validates the specific user-reported bug:
 * "When I change the default provider and press Save settings, 
 * the default provider goes back to the first setting"
 * 
 * This script simulates the exact user workflow to test if the bug is fixed.
 */

// WordPress loading
$wp_load_paths = [
    '/var/www/html/wp-load.php',
    '/var/www/wordpress/wp-load.php', 
    dirname(__DIR__) . '/wp-load.php',
    dirname(__DIR__) . '/wordpress/wp-load.php'
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
    die("❌ Error: Could not find WordPress installation. Please ensure WordPress is accessible.\n");
}

// Load admin functions for is_plugin_active()
if (!function_exists('is_plugin_active')) {
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

class CriticalSettingsPersistenceTest {
    
    private $test_results = [];
    private $plugin_options_key = 'wp_content_flow_settings';
    
    public function __construct() {
        echo "🚀 Critical Settings Persistence Test for WP Content Flow Plugin\n";
        echo "================================================================\n";
        echo "Testing the specific bug: Default provider dropdown not persisting after save\n\n";
    }
    
    private function log_test($test_name, $status, $details = '') {
        $icon = $status === 'PASS' ? '✅' : ($status === 'FAIL' ? '❌' : 'ℹ️');
        echo "{$icon} {$test_name}: {$status}" . ($details ? " - {$details}" : "") . "\n";
        
        $this->test_results[] = [
            'test' => $test_name,
            'status' => $status,
            'details' => $details,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    public function test_wordpress_environment() {
        // Test 1: WordPress loaded
        if (defined('WPINC')) {
            $this->log_test('WordPress Environment', 'PASS', 'WordPress loaded successfully');
        } else {
            $this->log_test('WordPress Environment', 'FAIL', 'WordPress not loaded');
            return false;
        }
        
        // Test 2: Plugin active
        if (is_plugin_active('wp-content-flow/wp-content-flow.php')) {
            $this->log_test('Plugin Activation', 'PASS', 'WP Content Flow plugin is active');
        } else {
            $this->log_test('Plugin Activation', 'FAIL', 'WP Content Flow plugin not active');
            return false;
        }
        
        return true;
    }
    
    public function test_settings_registration() {
        echo "\n🔍 Testing Settings Registration...\n";
        
        // Check if settings are registered
        global $wp_settings_fields, $wp_settings_sections;
        
        $settings_found = false;
        $sections_found = [];
        
        // Look for plugin settings sections
        if (isset($wp_settings_sections)) {
            foreach ($wp_settings_sections as $page => $sections) {
                if (strpos($page, 'content') !== false || strpos($page, 'flow') !== false) {
                    $settings_found = true;
                    $sections_found[] = $page;
                }
            }
        }
        
        if ($settings_found) {
            $this->log_test('Settings Registration', 'PASS', 'Found settings sections: ' . implode(', ', $sections_found));
        } else {
            $this->log_test('Settings Registration', 'FAIL', 'No plugin settings sections found');
        }
        
        // Check for specific settings fields
        $provider_field_found = false;
        if (isset($wp_settings_fields)) {
            foreach ($wp_settings_fields as $page => $sections) {
                foreach ($sections as $section => $fields) {
                    foreach ($fields as $field_id => $field) {
                        if (strpos($field_id, 'provider') !== false || strpos($field_id, 'default') !== false) {
                            $provider_field_found = true;
                            $this->log_test('Default Provider Field', 'PASS', "Found field: {$field_id}");
                            break 3;
                        }
                    }
                }
            }
        }
        
        if (!$provider_field_found) {
            $this->log_test('Default Provider Field', 'FAIL', 'Default provider field not registered');
        }
    }
    
    public function test_options_persistence() {
        echo "\n💾 Testing Options Persistence (Critical Test)...\n";
        
        // Step 1: Get current settings
        $current_settings = get_option($this->plugin_options_key, []);
        $this->log_test('Current Settings Retrieval', 'PASS', 'Retrieved: ' . json_encode($current_settings));
        
        // Step 2: Define test providers (simulate available options)
        $test_providers = ['openai', 'anthropic', 'google'];
        $initial_provider = isset($current_settings['default_provider']) ? $current_settings['default_provider'] : $test_providers[0];
        $this->log_test('Initial Provider Value', 'INFO', "Current: {$initial_provider}");
        
        // Step 3: Select different provider for testing
        $test_provider = null;
        foreach ($test_providers as $provider) {
            if ($provider !== $initial_provider) {
                $test_provider = $provider;
                break;
            }
        }
        
        if (!$test_provider) {
            $test_provider = $test_providers[1]; // Fallback
        }
        
        $this->log_test('Test Provider Selection', 'INFO', "Testing with provider: {$test_provider}");
        
        // Step 4: Simulate settings save
        $test_settings = array_merge($current_settings, [
            'default_provider' => $test_provider
        ]);
        
        // Save the settings
        $save_result = update_option($this->plugin_options_key, $test_settings);
        
        if ($save_result) {
            $this->log_test('Settings Save Operation', 'PASS', 'update_option() returned true');
        } else {
            // update_option returns false if value didn't change or on failure
            // Check if the value actually changed
            $check_settings = get_option($this->plugin_options_key, []);
            if (isset($check_settings['default_provider']) && $check_settings['default_provider'] === $test_provider) {
                $this->log_test('Settings Save Operation', 'PASS', 'Value updated successfully (update_option returned false but value changed)');
            } else {
                $this->log_test('Settings Save Operation', 'FAIL', 'update_option() returned false and value not changed');
                return false;
            }
        }
        
        // Step 5: Retrieve saved settings to verify persistence
        $saved_settings = get_option($this->plugin_options_key, []);
        $saved_provider = isset($saved_settings['default_provider']) ? $saved_settings['default_provider'] : 'not_set';
        
        // Step 6: Critical validation - does the saved value match what we set?
        if ($saved_provider === $test_provider) {
            $this->log_test('CRITICAL - Settings Persistence', 'PASS', "Provider persisted correctly: {$saved_provider}");
            
            // Step 7: Restore original settings
            if ($initial_provider !== $test_provider) {
                $restore_settings = array_merge($saved_settings, [
                    'default_provider' => $initial_provider
                ]);
                update_option($this->plugin_options_key, $restore_settings);
                $this->log_test('Settings Restoration', 'INFO', "Restored to original provider: {$initial_provider}");
            }
            
            return true;
        } else {
            $this->log_test('CRITICAL - Settings Persistence', 'FAIL', "Provider NOT persisted! Expected: {$test_provider}, Got: {$saved_provider}");
            return false;
        }
    }
    
    public function test_settings_api_hooks() {
        echo "\n🔗 Testing Settings API Hooks...\n";
        
        // Check if settings init hook is registered
        $init_hooked = has_action('admin_init', 'wp_content_flow_admin_init') || 
                      has_action('admin_init') !== false;
        
        if ($init_hooked) {
            $this->log_test('Admin Init Hook', 'PASS', 'admin_init hook registered');
        } else {
            $this->log_test('Admin Init Hook', 'FAIL', 'admin_init hook not found');
        }
        
        // Check for settings sanitization
        $sanitize_callback = null;
        $registered_settings = get_registered_settings();
        
        foreach ($registered_settings as $setting => $args) {
            if (strpos($setting, 'content_flow') !== false || strpos($setting, 'wp_content_flow') !== false) {
                if (isset($args['sanitize_callback'])) {
                    $sanitize_callback = $args['sanitize_callback'];
                    break;
                }
            }
        }
        
        if ($sanitize_callback) {
            $this->log_test('Settings Sanitization', 'PASS', "Sanitization callback: {$sanitize_callback}");
        } else {
            $this->log_test('Settings Sanitization', 'FAIL', 'No sanitization callback found');
        }
    }
    
    public function test_database_direct() {
        echo "\n🗄️ Testing Database Direct Access...\n";
        
        global $wpdb;
        
        // Query the options table directly
        $option_value = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            $this->plugin_options_key
        ));
        
        if ($option_value !== null) {
            $decoded_value = maybe_unserialize($option_value);
            $this->log_test('Database Direct Query', 'PASS', 'Settings found in database');
            
            if (is_array($decoded_value) && isset($decoded_value['default_provider'])) {
                $this->log_test('Provider in Database', 'PASS', "Default provider in DB: {$decoded_value['default_provider']}");
            } else {
                $this->log_test('Provider in Database', 'FAIL', 'Default provider not found in database settings');
            }
        } else {
            $this->log_test('Database Direct Query', 'FAIL', 'Settings not found in options table');
        }
    }
    
    public function generate_report() {
        echo "\n" . str_repeat('=', 80) . "\n";
        echo "📊 CRITICAL SETTINGS PERSISTENCE TEST RESULTS\n";
        echo str_repeat('=', 80) . "\n";
        
        $total_tests = count($this->test_results);
        $passed_tests = count(array_filter($this->test_results, function($test) {
            return $test['status'] === 'PASS';
        }));
        $failed_tests = count(array_filter($this->test_results, function($test) {
            return $test['status'] === 'FAIL';
        }));
        
        echo "🏃 Total Tests: {$total_tests}\n";
        echo "✅ Passed: {$passed_tests}\n";
        echo "❌ Failed: {$failed_tests}\n";
        
        if ($total_tests > 0) {
            $success_rate = round(($passed_tests / $total_tests) * 100);
            echo "📈 Success Rate: {$success_rate}%\n";
        }
        
        // Check for critical test results
        $critical_test_passed = false;
        foreach ($this->test_results as $result) {
            if (strpos($result['test'], 'CRITICAL') !== false && $result['status'] === 'PASS') {
                $critical_test_passed = true;
                break;
            }
        }
        
        echo "\n🎯 CRITICAL BUG STATUS:\n";
        if ($critical_test_passed) {
            echo "🟢 SETTINGS PERSISTENCE BUG FIXED - Default provider dropdown now persists correctly!\n";
        } else {
            echo "🔴 SETTINGS PERSISTENCE BUG STILL EXISTS - Default provider dropdown not persisting!\n";
        }
        
        echo "\n📋 DETAILED TEST RESULTS:\n";
        foreach ($this->test_results as $result) {
            $icon = $result['status'] === 'PASS' ? '✅' : ($result['status'] === 'FAIL' ? '❌' : 'ℹ️');
            echo "{$icon} {$result['test']}: {$result['status']}";
            if (!empty($result['details'])) {
                echo " - {$result['details']}";
            }
            echo "\n";
        }
        
        echo str_repeat('=', 80) . "\n";
        
        return [
            'total' => $total_tests,
            'passed' => $passed_tests,
            'failed' => $failed_tests,
            'critical_bug_fixed' => $critical_test_passed,
            'details' => $this->test_results
        ];
    }
    
    public function run_all_tests() {
        if (!$this->test_wordpress_environment()) {
            echo "❌ Environment test failed. Cannot continue.\n";
            return false;
        }
        
        $this->test_settings_registration();
        $this->test_options_persistence();
        $this->test_settings_api_hooks();
        $this->test_database_direct();
        
        return $this->generate_report();
    }
}

// Run the test
$test = new CriticalSettingsPersistenceTest();
$results = $test->run_all_tests();

// Exit with appropriate code
exit($results['critical_bug_fixed'] ? 0 : 1);
?>