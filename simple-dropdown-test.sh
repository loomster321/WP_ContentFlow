#!/bin/bash

echo "🔧 Testing WordPress Content Flow Dropdown Persistence Fix"
echo "=========================================================="

# Check if WordPress is running
echo "📋 Step 1: Checking if WordPress is accessible..."
if curl -s http://localhost:8080/wp-admin > /dev/null; then
    echo "✅ WordPress is running"
else
    echo "❌ WordPress is not accessible at localhost:8080"
    exit 1
fi

# Create a test PHP script to check the current dropdown state
cat > check_dropdown_state.php << 'EOF'
<?php
// Include WordPress
require_once('wp-content-flow/wp-content-flow.php');

// Simulate WordPress environment
define('ABSPATH', '/var/www/html/');

// Get the settings
$settings = get_option('wp_content_flow_settings', array());
$provider_value = isset($settings['default_ai_provider']) ? $settings['default_ai_provider'] : 'openai';

echo "Database Value Test Results:\n";
echo "===========================\n";
echo "Current provider in database: " . $provider_value . "\n";
echo "Settings array: " . print_r($settings, true) . "\n";

// Test the selected function behavior
foreach (['openai', 'anthropic', 'google'] as $provider) {
    $selected_attr = ($provider === $provider_value) ? ' selected="selected"' : '';
    echo "Provider: $provider - Selected attribute: '$selected_attr'\n";
}

// Check if fix JavaScript exists
echo "\nJavaScript Fix Check:\n";
echo "====================\n";

$settings_file = file_get_contents('wp-content-flow/includes/admin/class-settings-page.php');
if (strpos($settings_file, 'WP Content Flow: Expected provider value') !== false) {
    echo "✅ JavaScript debug logging found in settings file\n";
} else {
    echo "❌ JavaScript debug logging NOT found in settings file\n";
}

if (strpos($settings_file, 'providerDropdown.val(expectedValue)') !== false) {
    echo "✅ JavaScript dropdown fix found in settings file\n";
} else {
    echo "❌ JavaScript dropdown fix NOT found in settings file\n";
}

if (strpos($settings_file, 'Current database value') !== false) {
    echo "✅ Database value display found in settings file\n";
} else {
    echo "❌ Database value display NOT found in settings file\n";
}

if (strpos($settings_file, 'border.*2px solid.*#0073aa') !== false) {
    echo "✅ Debug styling found in settings file\n";
} else {
    echo "❌ Debug styling NOT found in settings file\n";
}
EOF

echo "📋 Step 2: Analyzing dropdown state..."
docker exec wp_contentflow-wordpress-1 php /var/www/html/check_dropdown_state.php

echo ""
echo "📋 Step 3: Manual testing instructions:"
echo "=======================================";
echo "1. Open http://localhost:8080/wp-admin"
echo "2. Login with: admin / !3cTXkh)9iDHhV5o*N"
echo "3. Navigate to: Content Flow > Settings"
echo "4. Open browser console (F12)"
echo "5. Look for these console messages:"
echo "   - 'WP Content Flow: Expected provider value: [value]'"
echo "   - 'WP Content Flow: Current dropdown value: [value]'"
echo "6. Check for blue border around dropdown (debug styling)"
echo "7. Check for 'Current database value: [value]' below dropdown"
echo "8. Change dropdown selection and save"
echo "9. Reload page and verify persistence"

echo ""
echo "📋 Step 4: Key success indicators:"
echo "================================="
echo "✅ Console messages appear showing expected vs current values"
echo "✅ Dropdown has blue border and light blue background"
echo "✅ 'Current database value: [value]' appears below dropdown"
echo "✅ Dropdown selection persists after save and reload"
echo "✅ Dropdown value matches the database value shown"

# Clean up test file
rm -f check_dropdown_state.php

echo ""
echo "🎯 EXPECTED BEHAVIOR AFTER FIX:"
echo "==============================="
echo "• Browser console shows debug messages on page load"
echo "• Dropdown has blue border (2px solid #0073aa) and light blue background"
echo "• Database value is displayed below dropdown as 'Current database value: [provider]'"
echo "• If dropdown doesn't match database, JavaScript forces it to correct value"
echo "• Dropdown selection persists after save and page reload"
echo "• No mismatch between dropdown display and database value"