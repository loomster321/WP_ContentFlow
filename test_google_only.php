<?php
/**
 * Test only Google AI to verify the fix
 */

// Load WordPress
require_once '/var/www/html/wp-config.php';
require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/providers/class-simple-provider.php';

echo "Testing Google AI Only\n";
echo "======================\n\n";

$settings = get_option('wp_content_flow_settings', array());
$google_key = $settings['google_api_key'] ?? '';

if (empty($google_key)) {
    echo "✗ No Google API key configured\n";
    exit(1);
}

echo "Google API key: Set (length: " . strlen($google_key) . ")\n\n";

try {
    $provider = new WP_Content_Flow_Simple_Provider('google', $google_key);
    
    // Test connection
    echo "1. Testing connection... ";
    $connection = $provider->test_connection();
    
    if (is_wp_error($connection)) {
        echo "✗ Failed: " . $connection->get_error_message() . "\n";
        exit(1);
    }
    echo "✓ Success\n";
    
    // Test content generation
    echo "2. Testing content generation... ";
    $result = $provider->generate_content("Write a short sentence about AI.", array(
        'max_tokens' => 50,
        'temperature' => 0.7
    ));
    
    if (is_wp_error($result)) {
        echo "✗ Failed: " . $result->get_error_message() . "\n";
        exit(1);
    }
    
    echo "✓ Success\n";
    echo "   Content: " . $result['content'] . "\n";
    echo "   Tokens: " . ($result['tokens_used'] ?? 'Unknown') . "\n\n";
    
    // Test content improvement
    echo "3. Testing content improvement... ";
    $improvement = $provider->improve_content("AI helps writers.", 'clarity', array(
        'max_tokens' => 50,
        'temperature' => 0.7
    ));
    
    if (is_wp_error($improvement)) {
        echo "✗ Failed: " . $improvement->get_error_message() . "\n";
        exit(1);
    }
    
    echo "✓ Success\n";
    echo "   Improved: " . $improvement[0]['content'] . "\n";
    
    echo "\n✓ Google AI is working correctly!\n";
    
} catch (Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "\n";
}
?>