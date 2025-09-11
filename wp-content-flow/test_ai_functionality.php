<?php
/**
 * Test script for WP Content Flow AI functionality with real API calls
 * 
 * This script tests the actual AI generation using configured API keys.
 */

// Load WordPress
require_once '/var/www/html/wp-config.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

echo "WP Content Flow AI Functionality Test\n";
echo "====================================\n\n";

// Set up admin user for authentication
wp_set_current_user(1);

// Test 1: Load Simple Provider class
echo "Test 1: Loading Simple Provider class...\n";

require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/providers/class-simple-provider.php';

if (class_exists('WP_Content_Flow_Simple_Provider')) {
    echo "✓ Simple Provider class loaded\n";
} else {
    echo "✗ Simple Provider class not found\n";
    exit(1);
}

// Test 2: Get plugin settings
echo "\nTest 2: Loading plugin settings...\n";

$settings = get_option('wp_content_flow_settings', array());

if (empty($settings)) {
    echo "✗ No settings found\n";
    exit(1);
}

echo "✓ Settings loaded\n";
echo "- Default provider: " . ($settings['default_ai_provider'] ?? 'Not set') . "\n";

// Test 3: Initialize provider with settings
echo "\nTest 3: Initializing AI provider...\n";

$default_provider = $settings['default_ai_provider'] ?? 'openai';
$api_key_field = $default_provider . '_api_key';
$api_key = $settings[$api_key_field] ?? '';

if (empty($api_key)) {
    echo "✗ No API key found for provider: $default_provider\n";
    exit(1);
}

echo "✓ Using provider: $default_provider\n";
echo "✓ API key found (length: " . strlen($api_key) . ")\n";

// Create provider instance
$provider = new WP_Content_Flow_Simple_Provider($default_provider, $api_key);

// Test 4: Test connection
echo "\nTest 4: Testing provider connection...\n";

$connection_test = $provider->test_connection();

if (is_wp_error($connection_test)) {
    echo "✗ Connection test failed: " . $connection_test->get_error_message() . "\n";
    
    // Show more detailed error for debugging
    $error_data = $connection_test->get_error_data();
    if ($error_data) {
        echo "Error data: " . print_r($error_data, true) . "\n";
    }
} else {
    echo "✓ Provider connection successful\n";
}

// Test 5: Generate content
echo "\nTest 5: Testing content generation...\n";

$test_prompt = "Write a short paragraph about the benefits of AI in content creation.";

$generation_result = $provider->generate_content($test_prompt, array(
    'max_tokens' => 100,
    'temperature' => 0.7
));

if (is_wp_error($generation_result)) {
    echo "✗ Content generation failed: " . $generation_result->get_error_message() . "\n";
} else {
    echo "✓ Content generation successful\n";
    echo "Generated content: " . substr($generation_result['content'], 0, 100) . "...\n";
    echo "Tokens used: " . ($generation_result['tokens_used'] ?? 'Unknown') . "\n";
    echo "Confidence score: " . ($generation_result['confidence_score'] ?? 'Unknown') . "\n";
}

// Test 6: Improve content
echo "\nTest 6: Testing content improvement...\n";

$test_content = "AI is good for writing. It can help people write better. This is useful.";

$improvement_result = $provider->improve_content($test_content, 'clarity', array(
    'max_tokens' => 100,
    'temperature' => 0.7
));

if (is_wp_error($improvement_result)) {
    echo "✗ Content improvement failed: " . $improvement_result->get_error_message() . "\n";
} else {
    echo "✓ Content improvement successful\n";
    echo "Original: $test_content\n";
    echo "Improved: " . substr($improvement_result[0]['content'], 0, 100) . "...\n";
}

echo "\nTest Summary\n";
echo "============\n";

if (is_wp_error($connection_test)) {
    echo "⚠ WARNING: Provider connection failed. Check API key and network connectivity.\n";
} elseif (is_wp_error($generation_result) || is_wp_error($improvement_result)) {
    echo "⚠ WARNING: Some API calls failed. Check logs for details.\n";
} else {
    echo "✓ All AI functionality tests passed successfully!\n";
    echo "✓ The WordPress plugin is connected to AI providers and working correctly.\n";
}

echo "\nTo test in Gutenberg:\n";
echo "1. Go to WordPress admin: http://localhost:8080/wp-admin\n";
echo "2. Create a new post/page\n";
echo "3. Add the 'AI Text Generator' block\n";
echo "4. Enter a prompt and click 'Generate Content'\n";

echo "\nTest completed.\n";