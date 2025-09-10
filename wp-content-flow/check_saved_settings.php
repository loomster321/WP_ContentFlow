<?php
/**
 * Check what settings are currently saved in WordPress
 */

define('WP_USE_THEMES', false);
require_once('/var/www/html/wp-config.php');

echo "=== Current WordPress Settings ===\n";

$settings = get_option('wp_content_flow_settings', array());

if (empty($settings)) {
    echo "❌ No settings found\n";
} else {
    echo "✅ Settings found:\n";
    foreach ($settings as $key => $value) {
        if (strpos($key, 'api_key') !== false) {
            echo "   $key: " . (empty($value) ? 'Not set' : 'SET (' . substr($value, 0, 10) . '...)') . "\n";
        } else {
            echo "   $key: $value\n";
        }
    }
}

echo "\n=== WordPress Database Check ===\n";
global $wpdb;
$result = $wpdb->get_row(
    "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'wp_content_flow_settings'"
);

if ($result) {
    $data = maybe_unserialize($result->option_value);
    echo "✅ Raw database data found:\n";
    echo "   OpenAI key: " . (isset($data['openai_api_key']) && !empty($data['openai_api_key']) ? 'SET' : 'NOT SET') . "\n";
    echo "   Anthropic key: " . (isset($data['anthropic_api_key']) && !empty($data['anthropic_api_key']) ? 'SET' : 'NOT SET') . "\n";
    echo "   Default provider: " . (isset($data['default_ai_provider']) ? $data['default_ai_provider'] : 'NOT SET') . "\n";
} else {
    echo "❌ No database record found\n";
}

?>