<?php
/**
 * Clean Plain Text API Keys from Database
 * 
 * This script removes plain text API keys while preserving encrypted versions
 * Run inside WordPress container: docker exec -it wp_contentflow-wordpress-1 php /var/www/html/wp-content/plugins/wp-content-flow/../../cleanup-plain-keys.php
 */

// Load WordPress
define('WP_USE_THEMES', false);
require_once('/var/www/html/wp-load.php');

echo "WordPress AI Content Flow - Plain Text Key Cleanup\n";
echo "==================================================\n\n";

// Get current settings
$option_name = 'wp_content_flow_settings';
$settings = get_option($option_name, array());

echo "Current settings keys:\n";
foreach ($settings as $key => $value) {
    if (strpos($key, 'api_key') !== false) {
        if (strpos($key, 'encrypted') !== false) {
            echo "  ✓ $key (encrypted, length: " . strlen($value) . ")\n";
        } else {
            echo "  ⚠️  $key (PLAIN TEXT - WILL BE REMOVED)\n";
        }
    } else {
        echo "  - $key\n";
    }
}

// Remove plain text keys
$cleaned = $settings;
$removed = array();

// Remove any plain text API keys
$plain_key_patterns = array(
    'openai_api_key',
    'anthropic_api_key', 
    'google_api_key',
    'google_ai_api_key'
);

foreach ($plain_key_patterns as $pattern) {
    if (isset($cleaned[$pattern])) {
        $removed[] = $pattern;
        unset($cleaned[$pattern]);
    }
}

if (count($removed) > 0) {
    echo "\nRemoving plain text keys:\n";
    foreach ($removed as $key) {
        echo "  ❌ Removing: $key\n";
    }
    
    // Update the option
    update_option($option_name, $cleaned);
    
    echo "\n✅ Plain text keys removed from database!\n";
    
    // Verify the update
    $updated = get_option($option_name);
    echo "\nUpdated settings keys:\n";
    foreach ($updated as $key => $value) {
        if (strpos($key, 'api_key') !== false) {
            echo "  ✓ $key (encrypted)\n";
        } else {
            echo "  - $key\n";
        }
    }
} else {
    echo "\n✅ No plain text keys found - database is secure!\n";
}

echo "\nDone!\n";