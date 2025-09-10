#!/bin/bash

echo "🔧 Testing WordPress Settings API Registration via cURL..."
echo "================================================"

# WordPress login credentials
WP_URL="http://localhost:8080"
USERNAME="admin"
PASSWORD="!3cTXkh)9iDHhV5o*N"

# Create a temporary cookies file
COOKIE_JAR=$(mktemp)
echo "Using cookie jar: $COOKIE_JAR"

echo
echo "📝 Step 1: Logging into WordPress admin..."

# Get the login page first to grab the nonce and cookies
login_page=$(curl -s -c "$COOKIE_JAR" "$WP_URL/wp-admin/")

# Extract the login nonce
login_nonce=$(echo "$login_page" | grep -oP 'name="_wpnonce"[^>]*value="\K[^"]*' | head -1)
if [ -z "$login_nonce" ]; then
    echo "❌ Could not find login nonce"
    exit 1
fi
echo "✅ Login nonce found: $login_nonce"

# Perform login
login_response=$(curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
    -d "log=$USERNAME" \
    -d "pwd=$PASSWORD" \
    -d "_wpnonce=$login_nonce" \
    -d "wp-submit=Log In" \
    -d "redirect_to=$WP_URL/wp-admin/" \
    -d "testcookie=1" \
    -X POST "$WP_URL/wp-login.php")

# Check if login was successful by looking for admin page content
if echo "$login_response" | grep -q "Dashboard"; then
    echo "✅ Successfully logged in to WordPress"
else
    echo "❌ Login failed"
    exit 1
fi

echo
echo "📝 Step 2: Accessing settings page to check initial state..."

# Access the settings page
settings_page=$(curl -s -b "$COOKIE_JAR" "$WP_URL/wp-admin/admin.php?page=wp-content-flow-settings")

# Check if we got the settings page
if echo "$settings_page" | grep -q "wp-content-flow-settings-form"; then
    echo "✅ Settings page loaded successfully"
    
    # Extract current settings values
    echo
    echo "🔍 Current settings state:"
    
    # Extract default provider value
    current_provider=$(echo "$settings_page" | grep -oP 'name="wp_content_flow_settings\[default_ai_provider\]"[^>]*>\s*<option value="\K[^"]*(?="[^>]*selected)' | head -1)
    if [ -z "$current_provider" ]; then
        # Fallback: look for first option with selected attribute
        current_provider=$(echo "$settings_page" | grep -A 10 'default_ai_provider' | grep -oP '<option value="\K[^"]*(?="[^>]*selected)')
        if [ -z "$current_provider" ]; then
            current_provider="openai" # default fallback
        fi
    fi
    echo "  Default AI Provider: $current_provider"
    
    # Extract cache enabled state
    if echo "$settings_page" | grep -q 'name="wp_content_flow_settings\[cache_enabled\]"[^>]*checked'; then
        current_cache="true"
    else
        current_cache="false"
    fi
    echo "  Cache Enabled: $current_cache"
    
else
    echo "❌ Could not access settings page"
    echo "Response preview:"
    echo "$settings_page" | head -5
    exit 1
fi

echo
echo "📝 Step 3: Preparing settings change test..."

# Determine new values for testing
if [ "$current_provider" = "openai" ]; then
    new_provider="anthropic"
else
    new_provider="openai"
fi

if [ "$current_cache" = "true" ]; then
    new_cache_value=""  # unchecked checkbox sends nothing
    new_cache_display="false"
else
    new_cache_value="1"  # checked checkbox sends "1"
    new_cache_display="true"
fi

echo "  Will change provider from '$current_provider' to '$new_provider'"
echo "  Will change cache from '$current_cache' to '$new_cache_display'"

# Extract the settings nonce
settings_nonce=$(echo "$settings_page" | grep -oP 'name="_wpnonce"[^>]*value="\K[^"]*' | head -1)
if [ -z "$settings_nonce" ]; then
    echo "❌ Could not find settings nonce"
    exit 1
fi
echo "✅ Settings nonce found: $settings_nonce"

echo
echo "📝 Step 4: Submitting settings form..."

# Prepare form data
form_data="option_page=wp_content_flow_settings_group"
form_data+="&_wpnonce=$settings_nonce"
form_data+="&wp_content_flow_settings[openai_api_key]=sk-test-key-for-validation"
form_data+="&wp_content_flow_settings[anthropic_api_key]="
form_data+="&wp_content_flow_settings[google_api_key]="
form_data+="&wp_content_flow_settings[default_ai_provider]=$new_provider"
form_data+="&wp_content_flow_settings[requests_per_minute]=10"

# Add cache checkbox if it should be checked
if [ "$new_cache_value" = "1" ]; then
    form_data+="&wp_content_flow_settings[cache_enabled]=1"
fi

form_data+="&submit=Save Settings"

echo "Submitting form with data (API key hidden)..."

# Submit the form
submit_response=$(curl -s -b "$COOKIE_JAR" \
    -d "$form_data" \
    -X POST "$WP_URL/wp-admin/admin.php?page=wp-content-flow-settings")

# Check for redirect with settings-updated parameter
if echo "$submit_response" | grep -q "settings-updated=true"; then
    echo "✅ Form submission triggered redirect with settings-updated parameter"
else
    echo "⚠️  No redirect with settings-updated parameter detected"
fi

# Check for success messages
if echo "$submit_response" | grep -q -i "settings saved\|updated"; then
    echo "✅ Success message found in response"
else
    echo "⚠️  No success message found in response"
fi

# Check for error messages
if echo "$submit_response" | grep -q -i "error\|failed"; then
    echo "❌ Error message found in response"
    echo "Error preview:"
    echo "$submit_response" | grep -i -A 2 -B 2 "error\|failed" | head -5
else
    echo "✅ No error messages found"
fi

echo
echo "📝 Step 5: Verifying settings persistence..."

# Load the settings page again to check if changes persisted
verification_page=$(curl -s -b "$COOKIE_JAR" "$WP_URL/wp-admin/admin.php?page=wp-content-flow-settings")

# Check if settings page loads
if echo "$verification_page" | grep -q "wp-content-flow-settings-form"; then
    echo "✅ Settings page reloaded successfully"
    
    # Extract current settings values after save
    saved_provider=$(echo "$verification_page" | grep -oP 'name="wp_content_flow_settings\[default_ai_provider\]"[^>]*>\s*<option value="\K[^"]*(?="[^>]*selected)' | head -1)
    if [ -z "$saved_provider" ]; then
        saved_provider=$(echo "$verification_page" | grep -A 10 'default_ai_provider' | grep -oP '<option value="\K[^"]*(?="[^>]*selected)')
        if [ -z "$saved_provider" ]; then
            saved_provider="unknown"
        fi
    fi
    
    if echo "$verification_page" | grep -q 'name="wp_content_flow_settings\[cache_enabled\]"[^>]*checked'; then
        saved_cache="true"
    else
        saved_cache="false"
    fi
    
    echo
    echo "🔍 Settings after save:"
    echo "  Default AI Provider: $saved_provider"
    echo "  Cache Enabled: $saved_cache"
    
    # Verify persistence
    provider_persisted="false"
    cache_persisted="false"
    
    if [ "$saved_provider" = "$new_provider" ]; then
        provider_persisted="true"
        echo "✅ Provider setting persisted correctly"
    else
        echo "❌ Provider setting did NOT persist (expected: $new_provider, got: $saved_provider)"
    fi
    
    if [ "$saved_cache" = "$new_cache_display" ]; then
        cache_persisted="true"
        echo "✅ Cache setting persisted correctly"
    else
        echo "❌ Cache setting did NOT persist (expected: $new_cache_display, got: $saved_cache)"
    fi
    
else
    echo "❌ Could not reload settings page for verification"
    provider_persisted="false"
    cache_persisted="false"
fi

echo
echo "🎯 FINAL RESULTS:"
echo "================"

# Overall assessment
if [ "$provider_persisted" = "true" ] && [ "$cache_persisted" = "true" ]; then
    echo "🎉 OVERALL: ✅ FIX IS WORKING"
    echo "✅ WordPress Settings API registration is functioning"
    echo "✅ Settings save and persist correctly"
    echo "✅ Form submissions are being processed"
    exit_code=0
else
    echo "⚠️  OVERALL: ❌ ISSUES DETECTED"
    if [ "$provider_persisted" = "false" ]; then
        echo "❌ Provider dropdown does not persist"
    fi
    if [ "$cache_persisted" = "false" ]; then
        echo "❌ Cache checkbox does not persist"
    fi
    exit_code=1
fi

# Cleanup
rm -f "$COOKIE_JAR"

echo
echo "🏁 Test Complete!"
exit $exit_code