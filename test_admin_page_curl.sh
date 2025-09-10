#!/bin/bash

echo "🧪 WordPress Content Flow Admin Page Test via cURL"
echo "=================================================="

# WordPress login credentials
ADMIN_USER="admin"
ADMIN_PASS="!3cTXkh)9iDHhV5o*N"
WP_URL="http://localhost:8080"
COOKIE_JAR="/tmp/wp_cookies.txt"

# Clean up old cookies
rm -f "$COOKIE_JAR"

echo "1️⃣ Logging into WordPress admin..."

# Get login page and extract nonce
LOGIN_PAGE=$(curl -s -c "$COOKIE_JAR" "$WP_URL/wp-admin/")
LOGIN_NONCE=$(echo "$LOGIN_PAGE" | grep -o 'name="_wpnonce"[^>]*value="[^"]*"' | sed 's/.*value="\([^"]*\)".*/\1/')

if [ -z "$LOGIN_NONCE" ]; then
    echo "❌ Could not extract login nonce"
    exit 1
fi

echo "   ✅ Login nonce extracted: ${LOGIN_NONCE:0:10}..."

# Perform login
LOGIN_RESPONSE=$(curl -s -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
    -d "log=$ADMIN_USER" \
    -d "pwd=$ADMIN_PASS" \
    -d "_wpnonce=$LOGIN_NONCE" \
    -d "wp-submit=Log In" \
    -d "redirect_to=$WP_URL/wp-admin/" \
    -d "testcookie=1" \
    "$WP_URL/wp-login.php")

# Check if login was successful
if echo "$LOGIN_RESPONSE" | grep -q "dashboard" || echo "$LOGIN_RESPONSE" | grep -q "wp-admin-bar"; then
    echo "   ✅ Login successful"
else
    echo "   ❌ Login failed"
    echo "   Response snippet: $(echo "$LOGIN_RESPONSE" | head -c 200)..."
    exit 1
fi

echo "2️⃣ Fetching Content Flow settings page..."

# Fetch the settings page
SETTINGS_PAGE=$(curl -s -b "$COOKIE_JAR" "$WP_URL/wp-admin/admin.php?page=wp-content-flow-settings")

# Save the page for inspection
SETTINGS_FILE="/home/timl/dev/WP_ContentFlow/tmp/settings-page-output.html"
echo "$SETTINGS_PAGE" > "$SETTINGS_FILE"

echo "   💾 Settings page saved to: $SETTINGS_FILE"
echo "   📏 Page size: $(echo "$SETTINGS_PAGE" | wc -c) characters"

echo "3️⃣ Analyzing form fields..."

# Check for the 6 required form fields
declare -a EXPECTED_FIELDS=(
    "wp_content_flow_settings\[openai_api_key\]"
    "wp_content_flow_settings\[anthropic_api_key\]"
    "wp_content_flow_settings\[google_api_key\]"
    "wp_content_flow_settings\[default_ai_provider\]"
    "wp_content_flow_settings\[cache_enabled\]"
    "wp_content_flow_settings\[requests_per_minute\]"
)

FOUND_FIELDS=0
for field in "${EXPECTED_FIELDS[@]}"; do
    if echo "$SETTINGS_PAGE" | grep -q "name=\"$field\""; then
        echo "   ✅ $(echo $field | sed 's/.*\[\([^]]*\)\].*/\1/') field: FOUND"
        ((FOUND_FIELDS++))
    else
        echo "   ❌ $(echo $field | sed 's/.*\[\([^]]*\)\].*/\1/') field: MISSING"
    fi
done

echo "   📊 Found $FOUND_FIELDS out of 6 required fields"

echo "4️⃣ Checking page structure..."

# Check for basic WordPress admin structure
if echo "$SETTINGS_PAGE" | grep -q "wp-admin"; then
    echo "   ✅ WordPress admin structure: Present"
else
    echo "   ❌ WordPress admin structure: Missing"
fi

# Check for form tag
if echo "$SETTINGS_PAGE" | grep -q "<form"; then
    echo "   ✅ Form tag: Present"
else
    echo "   ❌ Form tag: Missing"
fi

# Check for settings page title
if echo "$SETTINGS_PAGE" | grep -q "WP Content Flow Settings\|Content Flow"; then
    echo "   ✅ Settings page title: Present"
else
    echo "   ❌ Settings page title: Missing"
fi

# Check for WordPress settings fields (nonce, etc.)
if echo "$SETTINGS_PAGE" | grep -q "_wpnonce\|settings_fields"; then
    echo "   ✅ WordPress security fields: Present"
else
    echo "   ❌ WordPress security fields: Missing"
fi

# Check for submit button
if echo "$SETTINGS_PAGE" | grep -q "Save Settings\|submit"; then
    echo "   ✅ Submit button: Present"
else
    echo "   ❌ Submit button: Missing"
fi

echo "5️⃣ Error checking..."

# Check for PHP errors
if echo "$SETTINGS_PAGE" | grep -q "Fatal error\|Parse error\|Warning.*wp-content-flow"; then
    echo "   ⚠️ PHP errors detected:"
    echo "$SETTINGS_PAGE" | grep -E "Fatal error|Parse error|Warning.*wp-content-flow" | head -3
else
    echo "   ✅ No obvious PHP errors detected"
fi

# Check for WordPress admin notices
if echo "$SETTINGS_PAGE" | grep -q "notice-error\|error"; then
    echo "   ⚠️ WordPress error notices detected"
else
    echo "   ✅ No WordPress error notices"
fi

echo "6️⃣ FINAL RESULTS:"
echo "=================="

if [ $FOUND_FIELDS -eq 6 ]; then
    echo "🎉 SUCCESS: All 6 form fields are present!"
    echo "✅ The hook timing issue has been resolved."
    echo "✅ WordPress Content Flow settings form is working correctly."
    EXIT_CODE=0
elif [ $FOUND_FIELDS -ge 3 ]; then
    echo "⚠️ PARTIAL: Some form fields are present ($FOUND_FIELDS/6)"
    echo "🔧 The hook timing issue may be partially resolved."
    echo "🔍 Manual verification recommended."
    EXIT_CODE=1
else
    echo "❌ FAILURE: Form fields are missing or not visible ($FOUND_FIELDS/6)"
    echo "💥 The hook timing issue persists."
    echo "🔧 Additional troubleshooting required."
    EXIT_CODE=2
fi

echo ""
echo "📁 Generated files for analysis:"
echo "  • Settings page HTML: $SETTINGS_FILE"
echo "  • Cookies: $COOKIE_JAR"

# Clean up
rm -f "$COOKIE_JAR"

exit $EXIT_CODE