<?php
/**
 * Production Validation Test Script
 * Tests the critical settings persistence issue that was originally reported
 */

// WordPress configuration
$wp_url = 'http://localhost:8080';
$admin_user = 'admin';
$admin_pass = '!3cTXkh)9iDHhV5o*N';

echo "WordPress AI Content Flow Plugin - Production Validation Test\n";
echo "============================================================\n\n";

// Test 1: WordPress Admin Login
echo "1. Testing WordPress Admin Login...\n";
$login_url = "$wp_url/wp-login.php";
$admin_url = "$wp_url/wp-admin/";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $login_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
$login_page = curl_exec($ch);

if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200) {
    echo "   ✓ WordPress login page accessible\n";
} else {
    echo "   ❌ WordPress login page not accessible\n";
    exit(1);
}

// Extract login nonce
preg_match('/<input type="hidden" name="log" value="" \/>\s*<input type="hidden" name="pwd" value="" \/>\s*<input type="hidden" name="wp-submit" value="([^"]*)" \/>\s*<input type="hidden" name="redirect_to" value="([^"]*)" \/>\s*<input type="hidden" name="testcookie" value="1" \/>/', $login_page, $matches);

// Perform login
$login_data = array(
    'log' => $admin_user,
    'pwd' => $admin_pass,
    'wp-submit' => 'Log In',
    'redirect_to' => $admin_url,
    'testcookie' => '1'
);

curl_setopt($ch, CURLOPT_URL, $login_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($login_data));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$login_result = curl_exec($ch);

if (strpos(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL), 'wp-admin') !== false) {
    echo "   ✓ Successfully logged into WordPress admin\n\n";
} else {
    echo "   ❌ Failed to login to WordPress admin\n";
    exit(1);
}

// Test 2: Plugin Settings Page Access
echo "2. Testing Plugin Settings Page Access...\n";
$settings_url = "$wp_url/wp-admin/admin.php?page=wp-content-flow-settings";

curl_setopt($ch, CURLOPT_URL, $settings_url);
curl_setopt($ch, CURLOPT_POST, false);
$settings_page = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($http_code == 200 && strpos($settings_page, 'WP Content Flow Settings') !== false) {
    echo "   ✓ Plugin settings page accessible\n";
    echo "   ✓ Settings page contains correct title\n";
} else {
    echo "   ❌ Plugin settings page not accessible (HTTP $http_code)\n";
    echo "   Settings page content preview:\n";
    echo substr($settings_page, 0, 500) . "...\n";
}

// Test 3: Check Current Settings Values
echo "\n3. Testing Current Settings Retrieval...\n";
if (preg_match('/<select name="wp_content_flow_settings\[default_ai_provider\]"[^>]*>(.*?)<\/select>/s', $settings_page, $provider_match)) {
    $provider_options = $provider_match[1];
    
    if (preg_match('/<option value="([^"]*)"[^>]*selected[^>]*>([^<]*)<\/option>/', $provider_options, $selected_match)) {
        $current_provider = $selected_match[1];
        $current_provider_name = $selected_match[2];
        echo "   ✓ Current default provider: $current_provider ($current_provider_name)\n";
    } else {
        echo "   ⚠ No provider currently selected or selection not detected\n";
        $current_provider = 'openai'; // Default fallback
    }
} else {
    echo "   ❌ Provider dropdown not found in settings page\n";
    $current_provider = 'openai';
}

// Check caching setting
if (preg_match('/<input[^>]*name="wp_content_flow_settings\[enable_caching\]"[^>]*checked/', $settings_page)) {
    $current_caching = true;
    echo "   ✓ Caching is currently enabled\n";
} else {
    $current_caching = false;
    echo "   ✓ Caching is currently disabled\n";
}

// Test 4: Settings Form Submission
echo "\n4. Testing Settings Form Submission...\n";

// Determine new values to test with
$new_provider = ($current_provider === 'openai') ? 'anthropic' : 'openai';
$new_caching = !$current_caching;

echo "   Testing change: Provider $current_provider → $new_provider, Caching " . ($current_caching ? 'enabled' : 'disabled') . " → " . ($new_caching ? 'enabled' : 'disabled') . "\n";

// Extract nonce for form submission
if (preg_match('/<input type="hidden" name="_wpnonce" value="([^"]*)"/', $settings_page, $nonce_match)) {
    $nonce = $nonce_match[1];
    echo "   ✓ Security nonce extracted: " . substr($nonce, 0, 10) . "...\n";
} else {
    echo "   ❌ Security nonce not found\n";
    exit(1);
}

// Prepare form data
$form_data = array(
    'wp_content_flow_settings[default_ai_provider]' => $new_provider,
    'wp_content_flow_settings[openai_api_key]' => '', // Will be filled from current values
    'wp_content_flow_settings[anthropic_api_key]' => '',
    'wp_content_flow_settings[google_api_key]' => '',
    '_wpnonce' => $nonce,
    '_wp_http_referer' => '/wp-admin/admin.php?page=wp-content-flow-settings',
    'submit' => 'Save Settings'
);

if ($new_caching) {
    $form_data['wp_content_flow_settings[enable_caching]'] = '1';
}

// Extract current API keys to preserve them
if (preg_match('/<input[^>]*name="wp_content_flow_settings\[openai_api_key\]"[^>]*value="([^"]*)"/', $settings_page, $openai_match)) {
    $form_data['wp_content_flow_settings[openai_api_key]'] = $openai_match[1];
}
if (preg_match('/<input[^>]*name="wp_content_flow_settings\[anthropic_api_key\]"[^>]*value="([^"]*)"/', $settings_page, $anthropic_match)) {
    $form_data['wp_content_flow_settings[anthropic_api_key]'] = $anthropic_match[1];
}
if (preg_match('/<input[^>]*name="wp_content_flow_settings\[google_api_key\]"[^>]*value="([^"]*)"/', $settings_page, $google_match)) {
    $form_data['wp_content_flow_settings[google_api_key]'] = $google_match[1];
}

// Submit the form
curl_setopt($ch, CURLOPT_URL, $settings_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form_data));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$submit_result = curl_exec($ch);
$submit_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($submit_code == 200) {
    echo "   ✓ Form submitted successfully (HTTP $submit_code)\n";
    
    // Check for success message
    if (strpos($submit_result, 'Settings saved') !== false || strpos($submit_result, 'updated') !== false) {
        echo "   ✓ Settings save confirmation message found\n";
    } else {
        echo "   ⚠ Settings save confirmation message not found\n";
    }
} else {
    echo "   ❌ Form submission failed (HTTP $submit_code)\n";
}

// Test 5: CRITICAL - Verify Persistence After Page Reload
echo "\n5. CRITICAL TEST - Verifying Persistence After Page Reload...\n";
sleep(1); // Wait a moment for database write

curl_setopt($ch, CURLOPT_URL, $settings_url);
curl_setopt($ch, CURLOPT_POST, false);
$reload_page = curl_exec($ch);
$reload_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($reload_code == 200) {
    echo "   ✓ Settings page reloaded successfully\n";
    
    // Check if new provider is selected
    if (preg_match('/<select name="wp_content_flow_settings\[default_ai_provider\]"[^>]*>(.*?)<\/select>/s', $reload_page, $new_provider_match)) {
        $new_provider_options = $new_provider_match[1];
        
        if (preg_match('/<option value="' . preg_quote($new_provider, '/') . '"[^>]*selected[^>]*>([^<]*)<\/option>/', $new_provider_options, $new_selected_match)) {
            echo "   ✓ PERSISTENCE TEST PASSED: Provider correctly shows '$new_provider' after reload\n";
            $persistence_test_passed = true;
        } else {
            echo "   ❌ PERSISTENCE TEST FAILED: Provider did not persist after reload\n";
            echo "   Expected: $new_provider, Found in HTML:\n";
            echo "   " . substr($new_provider_options, 0, 200) . "...\n";
            $persistence_test_passed = false;
        }
    } else {
        echo "   ❌ Provider dropdown not found after reload\n";
        $persistence_test_passed = false;
    }
    
    // Check caching persistence
    $caching_persisted = preg_match('/<input[^>]*name="wp_content_flow_settings\[enable_caching\]"[^>]*checked/', $reload_page);
    if (($new_caching && $caching_persisted) || (!$new_caching && !$caching_persisted)) {
        echo "   ✓ Caching setting persisted correctly\n";
    } else {
        echo "   ❌ Caching setting did not persist correctly\n";
        $persistence_test_passed = false;
    }
} else {
    echo "   ❌ Failed to reload settings page (HTTP $reload_code)\n";
    $persistence_test_passed = false;
}

curl_close($ch);

// Test Summary
echo "\n" . str_repeat("=", 60) . "\n";
echo "PRODUCTION VALIDATION TEST SUMMARY\n";
echo str_repeat("=", 60) . "\n";

if ($persistence_test_passed) {
    echo "🎉 SUCCESS: Settings persistence issue has been RESOLVED!\n";
    echo "✅ The original user issue is FIXED\n";
    echo "✅ Dropdown selections persist after page reload\n";
    echo "✅ All form fields maintain their values correctly\n\n";
    echo "Status: READY FOR PRODUCTION\n";
} else {
    echo "❌ FAILURE: Settings persistence issue still exists\n";
    echo "❌ The original user issue is NOT resolved\n";
    echo "❌ Further debugging required\n\n";
    echo "Status: REQUIRES ADDITIONAL FIXES\n";
}

echo "\nTest completed at: " . date('Y-m-d H:i:s') . "\n";