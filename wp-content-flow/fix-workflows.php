<?php
/**
 * Fix script to manually seed workflows
 * Run this file to seed default workflows if they're missing
 * 
 * Usage: wp eval-file wp-content-flow/fix-workflows.php
 */

// Load WordPress
if (!defined('ABSPATH')) {
    $wp_load = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
    if (file_exists($wp_load)) {
        require_once $wp_load;
    } else {
        die('WordPress not found');
    }
}

// Load the seed file
require_once dirname(__FILE__) . '/includes/database/seed-workflows.php';

echo "WP Content Flow: Starting workflow fix...\n";

// Check current workflow count
global $wpdb;
$table_name = $wpdb->prefix . 'ai_workflows';
$current_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

echo "Current workflow count: {$current_count}\n";

if ($current_count == 0) {
    echo "No workflows found. Seeding defaults...\n";
    
    // Seed the workflows
    $result = wp_content_flow_seed_default_workflows();
    
    if ($result) {
        $new_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        echo "✅ Success! Seeded {$new_count} default workflows.\n";
    } else {
        echo "❌ Failed to seed workflows. Check error logs.\n";
    }
} else {
    echo "Workflows already exist. Use --force to reset.\n";
    
    // Check if force flag is set
    if (isset($argv[1]) && $argv[1] == '--force') {
        echo "Force flag detected. Resetting workflows...\n";
        wp_content_flow_reset_workflows();
        $new_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        echo "Reset complete. Now have {$new_count} workflows.\n";
    }
}

// List current workflows
echo "\nCurrent workflows:\n";
$workflows = $wpdb->get_results("SELECT id, name, ai_provider, status FROM {$table_name}");
foreach ($workflows as $workflow) {
    echo "  - [{$workflow->id}] {$workflow->name} ({$workflow->ai_provider}) - {$workflow->status}\n";
}

echo "\nWorkflow fix complete!\n";