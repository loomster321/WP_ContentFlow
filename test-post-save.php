<?php
/**
 * Simple test script to verify post saving works
 */

// Load WordPress
require_once('wp-load.php');

// Set up as admin user
wp_set_current_user(1);

echo "Testing WordPress Post Save (Issue #7)\n";
echo "=======================================\n\n";

// Test 1: Create a simple post
$post_data = array(
    'post_title'    => 'Test Post - Issue #7 Fix - ' . date('Y-m-d H:i:s'),
    'post_content'  => 'This is a test post to verify that the 500 error has been resolved.',
    'post_status'   => 'draft',
    'post_author'   => 1,
    'post_type'     => 'post'
);

echo "Creating test post...\n";
$post_id = wp_insert_post($post_data, true);

if (is_wp_error($post_id)) {
    echo "❌ FAILED: " . $post_id->get_error_message() . "\n";
    exit(1);
} else {
    echo "✅ SUCCESS: Post created with ID: " . $post_id . "\n";
}

// Test 2: Update the post
echo "\nUpdating test post...\n";
$update_data = array(
    'ID'           => $post_id,
    'post_content' => 'Updated content - The 500 error fix is working!'
);

$update_result = wp_update_post($update_data, true);

if (is_wp_error($update_result)) {
    echo "❌ FAILED: " . $update_result->get_error_message() . "\n";
    exit(1);
} else {
    echo "✅ SUCCESS: Post updated successfully\n";
}

// Test 3: Retrieve the post
echo "\nRetrieving test post...\n";
$retrieved_post = get_post($post_id);

if ($retrieved_post) {
    echo "✅ SUCCESS: Post retrieved\n";
    echo "   Title: " . $retrieved_post->post_title . "\n";
    echo "   Status: " . $retrieved_post->post_status . "\n";
} else {
    echo "❌ FAILED: Could not retrieve post\n";
    exit(1);
}

// Clean up
echo "\nCleaning up test post...\n";
$delete_result = wp_delete_post($post_id, true);
if ($delete_result) {
    echo "✅ SUCCESS: Test post deleted\n";
} else {
    echo "⚠️  WARNING: Could not delete test post\n";
}

echo "\n=======================================\n";
echo "✅ All tests passed! Post saving is working.\n";
echo "Issue #7 appears to be resolved.\n";
?>