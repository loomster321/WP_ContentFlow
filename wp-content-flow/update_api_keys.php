<?php
/**
 * Update WordPress settings with real API keys from .env file
 */

// Load WordPress
require_once '/var/www/html/wp-config.php';

echo "Updating API Keys from .env file\n";
echo "=================================\n\n";

// Load .env file
$env_file = '/var/www/html/.env';
if (!file_exists($env_file)) {
    echo "✗ .env file not found at $env_file\n";
    exit(1);
}

$env_vars = array();
$lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($lines as $line) {
    if (strpos($line, '=') !== false && !empty(trim($line))) {
        list($key, $value) = explode('=', $line, 2);
        $env_vars[trim($key)] = trim($value);
    }
}

echo "Loaded environment variables:\n";
foreach ($env_vars as $key => $value) {
    if (strpos($key, '_API_KEY') !== false) {
        echo "- $key: " . (strlen($value) > 0 ? "Set (length: " . strlen($value) . ")" : "Empty") . "\n";
    }
}

// Get current settings
$current_settings = get_option('wp_content_flow_settings', array());
echo "\nCurrent WordPress settings:\n";
print_r($current_settings);

// Update settings with real API keys
$updated_settings = $current_settings;

if (isset($env_vars['OPENAI_API_KEY']) && !empty($env_vars['OPENAI_API_KEY'])) {
    $updated_settings['openai_api_key'] = $env_vars['OPENAI_API_KEY'];
    echo "\n✓ Updated OpenAI API key\n";
}

if (isset($env_vars['ANTHROPIC_API_KEY']) && !empty($env_vars['ANTHROPIC_API_KEY'])) {
    $updated_settings['anthropic_api_key'] = $env_vars['ANTHROPIC_API_KEY'];
    echo "✓ Updated Anthropic API key\n";
}

if (isset($env_vars['GOOGLE_API_KEY']) && !empty($env_vars['GOOGLE_API_KEY'])) {
    $updated_settings['google_api_key'] = $env_vars['GOOGLE_API_KEY'];
    echo "✓ Updated Google API key\n";
}

// Save updated settings
$result = update_option('wp_content_flow_settings', $updated_settings);

if ($result) {
    echo "\n✓ Settings updated successfully in WordPress database\n";
} else {
    echo "\n✗ Failed to update settings (or no changes needed)\n";
}

// Verify the update
$verification_settings = get_option('wp_content_flow_settings', array());
echo "\nVerification - Updated WordPress settings:\n";

foreach ($verification_settings as $key => $value) {
    if (strpos($key, '_api_key') !== false) {
        echo "- $key: " . (strlen($value) > 0 ? "Set (length: " . strlen($value) . ")" : "Empty") . "\n";
    } else {
        echo "- $key: $value\n";
    }
}

echo "\nAPI keys have been updated. Ready to test AI functionality.\n";
?>