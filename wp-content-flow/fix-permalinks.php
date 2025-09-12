<?php
/**
 * Fix WordPress permalinks
 */

// Load WordPress
require_once '/var/www/html/wp-load.php';

// Get current permalink structure
$current = get_option('permalink_structure');
echo "Current permalink structure: " . ($current ?: '(plain)') . "\n";

// Set pretty permalinks (required for REST API)
update_option('permalink_structure', '/%postname%/');
echo "Updated permalink structure to: /%postname%/\n";

// Flush rewrite rules
flush_rewrite_rules();
echo "Flushed rewrite rules\n";

// Verify
$new = get_option('permalink_structure');
echo "New permalink structure: " . $new . "\n";

echo "\nNow try: curl http://localhost:8080/wp-json/wp-content-flow/v1/test\n";