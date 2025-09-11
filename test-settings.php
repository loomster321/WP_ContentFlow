#!/usr/bin/env php
<?php
/**
 * Test Settings Save Functionality
 * 
 * Run this script to test if settings are being saved properly
 */

// Test data
$test_settings = array(
    'openai_api_key' => 'sk-test-1234567890abcdef',
    'anthropic_api_key' => 'sk-ant-test-1234567890',
    'google_api_key' => 'AIzaTest1234567890',
    'default_ai_provider' => 'openai',
    'cache_enabled' => true,
    'requests_per_minute' => 30
);

echo "WordPress AI Content Flow - Settings Test\n";
echo "==========================================\n\n";

echo "Test settings to save:\n";
print_r($test_settings);

echo "\nTo test the settings save functionality:\n";
echo "1. Go to: http://localhost:8080/wp-admin/admin.php?page=wp-content-flow-settings\n";
echo "2. Enter these test values:\n";
echo "   - OpenAI API Key: " . $test_settings['openai_api_key'] . "\n";
echo "   - Anthropic API Key: " . $test_settings['anthropic_api_key'] . "\n";
echo "   - Google AI API Key: " . $test_settings['google_api_key'] . "\n";
echo "   - Default Provider: OpenAI\n";
echo "   - Enable Caching: Checked\n";
echo "   - Requests Per Minute: 30\n";
echo "3. Click 'Save Settings'\n";
echo "4. Check if the keys persist (should show masked versions like 'sk-t********************cdef')\n";
echo "\n";
echo "Watch the Docker logs for debugging output:\n";
echo "docker logs -f wp_contentflow-wordpress-1 2>&1 | grep 'WP Content Flow'\n";
echo "\n";
echo "Current issues being fixed:\n";
echo "- Settings were disappearing after save due to sanitization removing values\n";
echo "- Encryption/decryption may fail if WordPress salts are not consistent\n";
echo "- Display function was not showing masked keys properly\n";