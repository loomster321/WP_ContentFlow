<?php
/**
 * Test script to verify settings saving functionality
 */

// Get WordPress credentials from cookies
$cookies_file = '/home/timl/dev/WP_ContentFlow/cookies.txt';
$wp_admin_url = 'http://localhost:8080/wp-admin/options.php';

// Initialize curl
$ch = curl_init();

// Get nonce first
curl_setopt_array($ch, [
    CURLOPT_URL => 'http://localhost:8080/wp-admin/admin.php?page=wp-content-flow-settings',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => $cookies_file,
    CURLOPT_COOKIEJAR => $cookies_file,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 30
]);

$settings_page = curl_exec($ch);

// Extract nonce from the page
preg_match('/name="_wpnonce"[^>]*value="([^"]*)"/', $settings_page, $matches);
$nonce = isset($matches[1]) ? $matches[1] : '';

echo "Nonce found: " . ($nonce ? 'YES' : 'NO') . "\n";

if ($nonce) {
    // Now submit the form with a test API key
    $post_data = [
        'option_page' => 'wp_content_flow_settings',
        'action' => 'update',
        '_wpnonce' => $nonce,
        '_wp_http_referer' => '/wp-admin/admin.php?page=wp-content-flow-settings',
        'wp_content_flow_settings[ai_provider]' => 'openai',
        'wp_content_flow_settings[openai_api_key]' => 'sk-test-key-for-validation-12345',
        'wp_content_flow_settings[cache_enabled]' => '1',
        'wp_content_flow_settings[cache_duration]' => '1800',
        'wp_content_flow_settings[rate_limit_enabled]' => '1',
        'wp_content_flow_settings[requests_per_minute]' => '10',
        'wp_content_flow_settings[requests_per_hour]' => '100',
        'wp_content_flow_settings[daily_token_limit]' => '50000'
    ];
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $wp_admin_url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($post_data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEFILE => $cookies_file,
        CURLOPT_COOKIEJAR => $cookies_file,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    echo "HTTP Code: $http_code\n";
    
    if (strpos($result, 'updated') !== false || strpos($result, 'Settings saved') !== false) {
        echo "Settings appear to have been saved successfully\n";
    } else {
        echo "Settings save may have failed\n";
        echo "Response preview: " . substr(strip_tags($result), 0, 200) . "\n";
    }
}

curl_close($ch);

// Now check if the key was saved in the database
echo "\nChecking database...\n";
$db_cmd = 'docker exec wp_contentflow-db-1 mysql -u wordpress -pwordpress wordpress -e "SELECT option_value FROM wp_options WHERE option_name = \'wp_content_flow_settings\';" 2>/dev/null';
$db_result = shell_exec($db_cmd);
echo "Database result:\n$db_result\n";
?>