<?php
/**
 * Test script for WP Content Flow API endpoints
 * 
 * This script tests the AI generation and improvement endpoints
 * to ensure they work correctly with the settings integration.
 */

// Load WordPress
require_once __DIR__ . '/wp-config.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

// Set up the request environment
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/wp-json/wp-content-flow/v1/ai/generate';

echo "WP Content Flow API Endpoints Test\n";
echo "==================================\n\n";

// Test 1: Check if API endpoints are registered
echo "Test 1: Checking API endpoint registration...\n";

$rest_server = rest_get_server();
$routes = $rest_server->get_routes();

$ai_generate_exists = isset($routes['/wp-content-flow/v1/ai/generate']);
$ai_improve_exists = isset($routes['/wp-content-flow/v1/ai/improve']);

echo "- /ai/generate endpoint: " . ($ai_generate_exists ? "✓ Registered" : "✗ Not registered") . "\n";
echo "- /ai/improve endpoint: " . ($ai_improve_exists ? "✓ Registered" : "✗ Not registered") . "\n";

if (!$ai_generate_exists || !$ai_improve_exists) {
    echo "\nERROR: API endpoints not properly registered!\n";
    echo "Please check that the REST API controller is being loaded.\n\n";
}

// Test 2: Check plugin settings
echo "\nTest 2: Checking plugin settings...\n";

$settings = get_option('wp_content_flow_settings', array());

echo "- Settings found: " . (empty($settings) ? "✗ No settings" : "✓ Settings exist") . "\n";
echo "- OpenAI API key: " . (empty($settings['openai_api_key']) ? "✗ Not configured" : "✓ Configured") . "\n";
echo "- Anthropic API key: " . (empty($settings['anthropic_api_key']) ? "✗ Not configured" : "✓ Configured") . "\n";
echo "- Google API key: " . (empty($settings['google_api_key']) ? "✗ Not configured" : "✓ Configured") . "\n";
echo "- Default provider: " . ($settings['default_ai_provider'] ?? 'Not set') . "\n";
echo "- Cache enabled: " . (isset($settings['cache_enabled']) ? ($settings['cache_enabled'] ? 'Yes' : 'No') : 'Not set') . "\n";
echo "- Requests per minute: " . ($settings['requests_per_minute'] ?? 'Not set') . "\n";

// Test 3: Test API status endpoint
echo "\nTest 3: Testing API status endpoint...\n";

$request = new WP_REST_Request('GET', '/wp-content-flow/v1/status');
$response = $rest_server->dispatch($request);

if ($response->is_error()) {
    echo "✗ Status endpoint failed: " . $response->as_error()->get_error_message() . "\n";
} else {
    $data = $response->get_data();
    echo "✓ Status endpoint working\n";
    echo "  - Version: " . ($data['version'] ?? 'Unknown') . "\n";
    echo "  - Namespace: " . ($data['namespace'] ?? 'Unknown') . "\n";
    echo "  - Status: " . ($data['status'] ?? 'Unknown') . "\n";
}

// Test 4: Test generate endpoint (without making actual API calls)
echo "\nTest 4: Testing generate endpoint validation...\n";

// Test with missing prompt
$request = new WP_REST_Request('POST', '/wp-content-flow/v1/ai/generate');
$request->set_header('content-type', 'application/json');

// Set up a test user with appropriate capabilities
wp_set_current_user(1); // Assume user ID 1 is admin

$response = $rest_server->dispatch($request);

if ($response->is_error()) {
    $error = $response->as_error();
    if ($error->get_error_code() === 'rest_missing_callback_param') {
        echo "✓ Validation working - prompt is required\n";
    } else {
        echo "✗ Unexpected error: " . $error->get_error_message() . "\n";
    }
} else {
    echo "✗ Validation failed - should require prompt\n";
}

// Test with valid prompt but no API keys (should fail gracefully)
$request->set_param('prompt', 'Test prompt for content generation');

$response = $rest_server->dispatch($request);

if ($response->is_error()) {
    $error = $response->as_error();
    if ($error->get_error_code() === 'rest_no_provider') {
        echo "✓ Provider validation working - no API keys configured\n";
    } else {
        echo "? Error (expected if no API keys): " . $error->get_error_message() . "\n";
    }
} else {
    echo "✓ Generate endpoint accepting requests\n";
}

// Test 5: Test improve endpoint validation
echo "\nTest 5: Testing improve endpoint validation...\n";

$request = new WP_REST_Request('POST', '/wp-content-flow/v1/ai/improve');
$request->set_header('content-type', 'application/json');

$response = $rest_server->dispatch($request);

if ($response->is_error()) {
    $error = $response->as_error();
    if ($error->get_error_code() === 'rest_missing_callback_param') {
        echo "✓ Validation working - content is required\n";
    } else {
        echo "✗ Unexpected error: " . $error->get_error_message() . "\n";
    }
} else {
    echo "✗ Validation failed - should require content\n";
}

// Test with valid content
$request->set_param('content', 'This is some test content that needs improvement.');
$request->set_param('improvement_type', 'clarity');

$response = $rest_server->dispatch($request);

if ($response->is_error()) {
    $error = $response->as_error();
    if ($error->get_error_code() === 'rest_no_provider') {
        echo "✓ Provider validation working - no API keys configured\n";
    } else {
        echo "? Error (expected if no API keys): " . $error->get_error_message() . "\n";
    }
} else {
    echo "✓ Improve endpoint accepting requests\n";
}

echo "\nTest Summary\n";
echo "============\n";
echo "The API endpoints are " . ($ai_generate_exists && $ai_improve_exists ? "properly registered" : "NOT properly registered") . "\n";

if (empty($settings)) {
    echo "⚠ WARNING: No plugin settings found. Please configure at least one AI provider.\n";
} else {
    $has_api_key = !empty($settings['openai_api_key']) || !empty($settings['anthropic_api_key']) || !empty($settings['google_api_key']);
    if (!$has_api_key) {
        echo "⚠ WARNING: No AI provider API keys configured. The endpoints will return errors.\n";
    } else {
        echo "✓ At least one AI provider is configured.\n";
    }
}

echo "\nTo test with real API calls:\n";
echo "1. Configure AI provider API keys in WordPress admin\n";
echo "2. Make POST requests to:\n";
echo "   - /wp-json/wp-content-flow/v1/ai/generate\n";
echo "   - /wp-json/wp-content-flow/v1/ai/improve\n";
echo "3. Include proper authentication headers (X-WP-Nonce)\n";
echo "\nTest completed.\n";