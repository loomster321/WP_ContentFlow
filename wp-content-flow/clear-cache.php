<?php
/**
 * Clear AI response cache or disable caching
 */

// Load WordPress
require_once '/var/www/html/wp-load.php';

// Get current settings
$settings = get_option('wp_content_flow_settings', array());
echo "Current cache setting: " . ($settings['cache_enabled'] ? 'ENABLED' : 'DISABLED') . "\n";

// Option 1: Clear all transients (cache)
global $wpdb;
$sql = "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wp_content_flow_ai_%' OR option_name LIKE '_transient_timeout_wp_content_flow_ai_%'";
$deleted = $wpdb->query($sql);
echo "Cleared $deleted cache entries\n";

// Option 2: Disable cache temporarily
$settings['cache_enabled'] = false;
update_option('wp_content_flow_settings', $settings);
echo "Cache disabled\n";

echo "\nNow the plugin will make fresh API calls.\n";