<?php
/**
 * Configure plugin settings with test API keys
 */

// WordPress bootstrap
define('WP_USE_THEMES', false);
require_once('/var/www/html/wp-config.php');

echo "=== Configuring WP Content Flow Settings ===\n";

// Sample settings configuration
$settings = array(
    'openai_api_key' => 'sk-test-key-for-development', // Demo key
    'anthropic_api_key' => 'test-anthropic-key', // Demo key
    'default_ai_provider' => 'openai',
    'max_tokens' => 1500,
    'temperature' => 0.7,
    'enable_auto_suggestions' => 1,
);

// Update settings
$updated = update_option('wp_content_flow_settings', $settings);

if ($updated) {
    echo "✅ Settings configured successfully!\n";
} else {
    echo "ℹ️  Settings may have been already configured.\n";
}

// Verify settings
$saved_settings = get_option('wp_content_flow_settings', array());
echo "\n=== Current Settings ===\n";
foreach ($saved_settings as $key => $value) {
    if (strpos($key, 'api_key') !== false) {
        echo "$key: " . (empty($value) ? 'NOT SET' : 'SET (****)') . "\n";
    } else {
        echo "$key: $value\n";
    }
}

echo "\n=== Configuration Complete ===\n";
echo "You can now:\n";
echo "1. Access WordPress admin: http://localhost:8080/wp-admin\n";
echo "2. Go to Content Flow > Settings to modify API keys\n";
echo "3. Test the AI Text Generator block in the block editor\n";
echo "4. Create a new post and add the AI Text Generator block\n";

?>