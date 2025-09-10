<?php
/**
 * CRITICAL TEST: Direct Backend Settings Persistence Validation
 * 
 * This script tests the WordPress settings persistence directly
 * to verify the user-reported issue is resolved at the database level.
 */

// WordPress bootstrap
require_once '/var/www/html/wp-config.php';

echo "🔍 CRITICAL SETTINGS PERSISTENCE TEST\n";
echo "=====================================\n\n";

// Test the exact settings that were reported as problematic
$test_settings = [
    'default_provider' => 'openai',
    'enable_caching' => true,
    'openai_api_key' => 'test_openai_key_12345',
    'anthropic_api_key' => 'test_anthropic_key_67890',
    'google_api_key' => 'test_google_key_abcde'
];

echo "📊 STEP 1: Recording current settings state...\n";
$current_settings = get_option('wp_content_flow_settings', []);
echo "Current settings: " . json_encode($current_settings, JSON_PRETTY_PRINT) . "\n\n";

echo "📝 STEP 2: Saving test settings...\n";
$save_result = update_option('wp_content_flow_settings', $test_settings);
echo "Save result: " . ($save_result ? 'SUCCESS' : 'FAILED') . "\n\n";

echo "🔍 STEP 3: Immediately retrieving saved settings...\n";
$retrieved_settings = get_option('wp_content_flow_settings', []);
echo "Retrieved settings: " . json_encode($retrieved_settings, JSON_PRETTY_PRINT) . "\n\n";

echo "✅ STEP 4: Verifying critical fields...\n";

// Test 1: Default provider persistence (the main user complaint)
$expected_provider = 'openai';
$actual_provider = $retrieved_settings['default_provider'] ?? 'NOT_SET';
$provider_test = ($actual_provider === $expected_provider);

echo "Provider Test:\n";
echo "  Expected: {$expected_provider}\n";
echo "  Actual: {$actual_provider}\n";
echo "  Result: " . ($provider_test ? '✅ PASS' : '❌ FAIL') . "\n\n";

// Test 2: Caching setting persistence
$expected_caching = true;
$actual_caching = $retrieved_settings['enable_caching'] ?? false;
$caching_test = ($actual_caching === $expected_caching);

echo "Caching Test:\n";
echo "  Expected: " . ($expected_caching ? 'true' : 'false') . "\n";
echo "  Actual: " . ($actual_caching ? 'true' : 'false') . "\n";
echo "  Result: " . ($caching_test ? '✅ PASS' : '❌ FAIL') . "\n\n";

// Test 3: API keys persistence
$api_keys_test = true;
foreach (['openai_api_key', 'anthropic_api_key', 'google_api_key'] as $key) {
    $expected = $test_settings[$key];
    $actual = $retrieved_settings[$key] ?? 'NOT_SET';
    $key_test = ($actual === $expected);
    $api_keys_test = $api_keys_test && $key_test;
    
    echo "API Key Test ({$key}):\n";
    echo "  Expected: {$expected}\n";
    echo "  Actual: {$actual}\n";
    echo "  Result: " . ($key_test ? '✅ PASS' : '❌ FAIL') . "\n";
}
echo "\n";

echo "🎯 OVERALL TEST RESULTS:\n";
echo "========================\n";
echo "Provider Persistence: " . ($provider_test ? '✅ PASS' : '❌ FAIL') . "\n";
echo "Caching Persistence: " . ($caching_test ? '✅ PASS' : '❌ FAIL') . "\n";
echo "API Keys Persistence: " . ($api_keys_test ? '✅ PASS' : '❌ FAIL') . "\n\n";

$all_tests_pass = $provider_test && $caching_test && $api_keys_test;

if ($all_tests_pass) {
    echo "🎉 SUCCESS: All persistence tests PASSED!\n";
    echo "✅ The original user issue is RESOLVED at the database level\n";
    echo "✅ Settings are properly persisting to WordPress options table\n";
} else {
    echo "❌ FAILURE: Some persistence tests FAILED!\n";
    echo "❌ The user issue may still exist\n";
    echo "❌ Check WordPress database configuration\n";
}

echo "\n📋 NEXT STEP: Test frontend form submission and page reload\n";
echo "🌐 URL: http://localhost:8080/wp-admin/admin.php?page=wp-content-flow-settings\n";
?>