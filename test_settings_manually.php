<?php
/**
 * Manual Settings Save Test
 * 
 * This script will directly test the WordPress admin settings save functionality
 * without relying on Playwright
 */

// Simulate a direct POST request to the WordPress admin
$wordpress_url = 'http://localhost:8080';

// First, let's get the login form to get the nonce
$login_url = $wordpress_url . '/wp-admin/';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $login_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/wordpress_cookies.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/wordpress_cookies.txt');

$response = curl_exec($ch);

if ($response === false) {
    echo "❌ Failed to access WordPress admin\n";
    echo "Error: " . curl_error($ch) . "\n";
    exit(1);
}

echo "✅ Successfully accessed WordPress admin login page\n";

// Now let's login
$login_data = [
    'log' => 'admin',
    'pwd' => '!3cTXkh)9iDHhV5o*N',
    'wp-submit' => 'Log In',
    'redirect_to' => $wordpress_url . '/wp-admin/',
    'testcookie' => '1'
];

curl_setopt($ch, CURLOPT_URL, $wordpress_url . '/wp-login.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($login_data));

$login_response = curl_exec($ch);

if ($login_response === false) {
    echo "❌ Failed to login to WordPress\n";
    echo "Error: " . curl_error($ch) . "\n";
    exit(1);
}

echo "✅ Successfully logged into WordPress\n";

// Now let's access the settings page to get the nonce
$settings_url = $wordpress_url . '/wp-admin/admin.php?page=wp-content-flow-settings';

curl_setopt($ch, CURLOPT_URL, $settings_url);
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_POSTFIELDS, '');

$settings_page = curl_exec($ch);

if ($settings_page === false) {
    echo "❌ Failed to access settings page\n";
    echo "Error: " . curl_error($ch) . "\n";
    exit(1);
}

echo "✅ Successfully accessed settings page\n";

// Extract the nonce from the form
if (preg_match('/<input type="hidden" name="_wpnonce" value="([^"]+)"/', $settings_page, $matches)) {
    $nonce = $matches[1];
    echo "✅ Found nonce: " . substr($nonce, 0, 10) . "...\n";
} else {
    echo "❌ Could not find nonce in form\n";
    echo "Form HTML snippet:\n";
    // Look for form tag
    if (preg_match('/<form[^>]*>.*?<\/form>/s', $settings_page, $form_matches)) {
        echo substr($form_matches[0], 0, 500) . "...\n";
    }
    exit(1);
}

// Extract option_page value
if (preg_match('/<input type="hidden" name="option_page" value="([^"]+)"/', $settings_page, $matches)) {
    $option_page = $matches[1];
    echo "✅ Found option_page: $option_page\n";
} else {
    echo "❌ Could not find option_page in form\n";
    exit(1);
}

// Now let's submit the settings form
$test_settings = [
    '_wpnonce' => $nonce,
    'option_page' => $option_page,
    'wp_content_flow_settings' => [
        'openai_api_key' => $_ENV['OPENAI_API_KEY'] ?? 'test-key-placeholder',
        'anthropic_api_key' => $_ENV['ANTHROPIC_API_KEY'] ?? 'test-key-placeholder',
        'default_ai_provider' => 'anthropic',
        'cache_enabled' => '1',
        'requests_per_minute' => '15'
    ],
    'submit' => 'Save Settings'
];

echo "🚀 Submitting settings form...\n";

curl_setopt($ch, CURLOPT_URL, $settings_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($test_settings));
curl_setopt($ch, CURLOPT_HEADER, true);

$submit_response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$redirect_url = curl_getinfo($ch, CURLINFO_REDIRECT_URL);

echo "📊 Response HTTP Code: $http_code\n";
echo "📊 Redirect URL: $redirect_url\n";

if ($submit_response === false) {
    echo "❌ Failed to submit settings\n";
    echo "Error: " . curl_error($ch) . "\n";
    exit(1);
}

// Check if we got a redirect (which would indicate success)
if ($http_code >= 300 && $http_code < 400) {
    echo "✅ Form submission resulted in redirect (likely success)\n";
} else {
    echo "⚠️  No redirect occurred, checking response content\n";
}

// Extract response body (skip headers)
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$response_body = substr($submit_response, $header_size);

// Check for error messages
if (strpos($response_body, 'not in the allowed options list') !== false) {
    echo "❌ Found 'not in the allowed options list' error\n";
} else if (strpos($response_body, 'Settings saved') !== false || strpos($response_body, 'settings-updated') !== false) {
    echo "✅ Found success indicators\n";
} else {
    echo "⚠️  No clear success or error indicators found\n";
}

// Look for any error messages
if (preg_match('/<div[^>]*class="[^"]*error[^"]*"[^>]*>(.*?)<\/div>/s', $response_body, $error_matches)) {
    echo "❌ Found error message: " . strip_tags($error_matches[1]) . "\n";
}

// Now let's check if settings were actually saved by accessing the page again
echo "🔄 Checking if settings were saved...\n";

curl_setopt($ch, CURLOPT_URL, $settings_url);
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_POSTFIELDS, '');
curl_setopt($ch, CURLOPT_HEADER, false);

$check_response = curl_exec($ch);

if ($check_response === false) {
    echo "❌ Failed to check settings page after submission\n";
    exit(1);
}

// Look for the Current Configuration section
if (preg_match('/<div class="wp-content-flow-info">.*?<\/div>/s', $check_response, $config_matches)) {
    $config_section = $config_matches[0];
    
    if (strpos($config_section, 'Configured ✓') !== false) {
        echo "✅ Settings appear to be saved - API keys show as configured\n";
    } else {
        echo "❌ Settings may not be saved - no 'Configured' status found\n";
    }
    
    if (strpos($config_section, 'anthropic') !== false) {
        echo "✅ Default provider appears to be saved correctly\n";
    } else {
        echo "❌ Default provider may not be saved correctly\n";
    }
} else {
    echo "⚠️  Could not find Current Configuration section\n";
}

curl_close($ch);

echo "\n🏁 Test completed!\n";
?>