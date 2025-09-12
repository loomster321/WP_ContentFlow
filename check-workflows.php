<?php
// Load WordPress
require_once dirname(__FILE__) . '/wp-load.php';

global $wpdb;
$table_name = $wpdb->prefix . 'ai_workflows';

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

if (!$table_exists) {
    echo "❌ Table $table_name does not exist!\n";
    exit;
}

// Count workflows
$count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
echo "📊 Workflows in database: $count\n\n";

if ($count > 0) {
    echo "Available workflows:\n";
    $workflows = $wpdb->get_results("SELECT id, name, ai_provider, status FROM $table_name");
    foreach ($workflows as $workflow) {
        echo "  [{$workflow->id}] {$workflow->name} ({$workflow->ai_provider}) - {$workflow->status}\n";
    }
} else {
    echo "⚠️ No workflows found in database!\n";
    echo "\nTrying to seed default workflows...\n";
    
    // Try to seed workflows
    require_once dirname(__FILE__) . '/wp-content-flow/includes/database/seed-workflows.php';
    $result = wp_content_flow_seed_default_workflows();
    
    if ($result) {
        $new_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        echo "✅ Successfully seeded $new_count workflows!\n";
        
        $workflows = $wpdb->get_results("SELECT id, name, ai_provider, status FROM $table_name");
        foreach ($workflows as $workflow) {
            echo "  [{$workflow->id}] {$workflow->name} ({$workflow->ai_provider}) - {$workflow->status}\n";
        }
    } else {
        echo "❌ Failed to seed workflows\n";
    }
}

// Test REST API endpoint
echo "\n📡 Testing REST API endpoint...\n";
$current_user = wp_get_current_user();
if ($current_user->ID > 0) {
    echo "Current user: {$current_user->user_login} (ID: {$current_user->ID})\n";
    echo "Can edit posts: " . (current_user_can('edit_posts') ? 'Yes' : 'No') . "\n";
} else {
    echo "No user logged in (running from CLI)\n";
}