<?php
/**
 * Debug script to check WordPress settings
 */

// Load WordPress
require_once '/var/www/html/wp-config.php';

echo "WordPress Settings Debug\n";
echo "========================\n\n";

// Get the settings from WordPress database
$settings = get_option('wp_content_flow_settings', array());

echo "Current WordPress settings:\n";
print_r($settings);

echo "\nEnvironment variables (if loaded):\n";
echo "OPENAI_API_KEY: " . (getenv('OPENAI_API_KEY') ?: 'Not set') . "\n";
echo "ANTHROPIC_API_KEY: " . (getenv('ANTHROPIC_API_KEY') ?: 'Not set') . "\n";
echo "GOOGLE_API_KEY: " . (getenv('GOOGLE_API_KEY') ?: 'Not set') . "\n";

echo "\nDirect .env file check:\n";
$env_file = '/var/www/html/.env';
if (file_exists($env_file)) {
    echo "✓ .env file exists\n";
    $env_content = file_get_contents($env_file);
    $lines = explode("\n", $env_content);
    foreach ($lines as $line) {
        if (strpos($line, '_API_KEY=') !== false) {
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                echo $parts[0] . ": " . (strlen($parts[1]) > 0 ? "Set (length: " . strlen($parts[1]) . ")" : "Empty") . "\n";
            }
        }
    }
} else {
    echo "✗ .env file not found at $env_file\n";
}

echo "\nSettings page class check:\n";
require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/admin/class-settings-page.php';

if (class_exists('WP_Content_Flow_Settings_Page')) {
    echo "✓ Settings page class loaded\n";
    
    $settings_page = WP_Content_Flow_Settings_Page::get_instance();
    $defaults = $settings_page->get_default_settings();
    
    echo "Default settings:\n";
    print_r($defaults);
} else {
    echo "✗ Settings page class not found\n";
}
?>