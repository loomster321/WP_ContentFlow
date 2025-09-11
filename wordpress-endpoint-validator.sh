#!/bin/bash

# WordPress WP Content Flow Plugin Endpoint Validation Script
# Tests critical WordPress endpoints to validate plugin functionality

echo "🚀 WordPress WP Content Flow Plugin - Endpoint Validation"
echo "============================================================"

BASE_URL="http://localhost:8080"
ADMIN_USER="admin"
ADMIN_PASS="!3cTXkh)9iDHhV5o*N"
TEMP_COOKIES="/tmp/wp_test_cookies.txt"

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test results tracking
TESTS_PASSED=0
TESTS_FAILED=0
CRITICAL_ISSUES=()

log_test() {
    local test_name="$1"
    local status="$2"
    local details="$3"
    
    if [ "$status" = "PASS" ]; then
        echo -e "${GREEN}✅ $test_name: PASSED${NC}${details:+ - $details}"
        ((TESTS_PASSED++))
    else
        echo -e "${RED}❌ $test_name: FAILED${NC}${details:+ - $details}"
        ((TESTS_FAILED++))
        if [[ "$test_name" == *"CRITICAL"* ]]; then
            CRITICAL_ISSUES+=("$test_name: $details")
        fi
    fi
}

# Test 1: WordPress Basic Connectivity
test_wordpress_connectivity() {
    echo -e "\n📡 Testing WordPress Basic Connectivity..."
    
    response=$(curl -s -w "%{http_code}" -o /dev/null "$BASE_URL")
    
    if [ "$response" = "200" ] || [ "$response" = "302" ]; then
        log_test "WordPress Connectivity" "PASS" "HTTP $response"
    else
        log_test "WordPress Connectivity" "FAIL" "HTTP $response"
        return 1
    fi
}

# Test 2: WordPress Admin Login
test_wordpress_login() {
    echo -e "\n🔑 Testing WordPress Admin Login..."
    
    # Get login page first to extract nonce
    login_page=$(curl -s -c "$TEMP_COOKIES" "$BASE_URL/wp-login.php")
    
    if [[ $login_page == *"wp-login.php"* ]] || [[ $login_page == *"log in"* ]]; then
        log_test "Login Page Access" "PASS" "Login form accessible"
    else
        log_test "Login Page Access" "FAIL" "Cannot access login page"
        return 1
    fi
    
    # Attempt login
    login_response=$(curl -s -b "$TEMP_COOKIES" -c "$TEMP_COOKIES" -w "%{http_code}" \
        -d "log=$ADMIN_USER" \
        -d "pwd=$ADMIN_PASS" \
        -d "wp-submit=Log In" \
        -d "redirect_to=$BASE_URL/wp-admin/" \
        -d "testcookie=1" \
        -o /tmp/login_response.html \
        "$BASE_URL/wp-login.php")
    
    # Check if redirected to admin (302) or if login was successful
    if [ "$login_response" = "302" ] || [ "$login_response" = "200" ]; then
        # Verify by accessing admin dashboard
        admin_response=$(curl -s -b "$TEMP_COOKIES" "$BASE_URL/wp-admin/")
        if [[ $admin_response == *"wpadminbar"* ]] || [[ $admin_response == *"Dashboard"* ]]; then
            log_test "WordPress Admin Login" "PASS" "Successfully logged into admin"
            return 0
        else
            log_test "WordPress Admin Login" "FAIL" "Login appears failed - no admin bar found"
            return 1
        fi
    else
        log_test "WordPress Admin Login" "FAIL" "Login request failed with HTTP $login_response"
        return 1
    fi
}

# Test 3: Plugin Detection
test_plugin_detection() {
    echo -e "\n🔌 Testing Plugin Detection..."
    
    if [ ! -f "$TEMP_COOKIES" ]; then
        log_test "Plugin Detection" "FAIL" "No login session available"
        return 1
    fi
    
    plugins_page=$(curl -s -b "$TEMP_COOKIES" "$BASE_URL/wp-admin/plugins.php")
    
    # Look for various possible plugin names
    if [[ $plugins_page == *"WP Content Flow"* ]] || [[ $plugins_page == *"Content Flow"* ]] || [[ $plugins_page == *"wp-content-flow"* ]]; then
        log_test "Plugin Detection" "PASS" "WP Content Flow plugin found in plugins list"
        
        # Check if active
        if [[ $plugins_page == *"Deactivate"* ]] && ([[ $plugins_page == *"WP Content Flow"* ]] || [[ $plugins_page == *"Content Flow"* ]]); then
            log_test "Plugin Status" "PASS" "Plugin appears to be active"
        else
            log_test "Plugin Status" "FAIL" "Plugin may not be active"
        fi
    else
        log_test "Plugin Detection" "FAIL" "WP Content Flow plugin not found in plugins list"
        
        # Debug: Check what plugins are actually listed
        plugin_count=$(echo "$plugins_page" | grep -c "plugin-title")
        echo -e "${YELLOW}   Debug: Found $plugin_count total plugins${NC}"
    fi
}

# Test 4: Settings Page Access
test_settings_access() {
    echo -e "\n⚙️ Testing Settings Page Access..."
    
    if [ ! -f "$TEMP_COOKIES" ]; then
        log_test "Settings Page Access" "FAIL" "No login session available"
        return 1
    fi
    
    # Try multiple possible settings page URLs
    settings_urls=(
        "/wp-admin/admin.php?page=wp-content-flow-settings"
        "/wp-admin/admin.php?page=wp-content-flow"
        "/wp-admin/admin.php?page=content-flow-settings"
        "/wp-admin/options-general.php?page=wp-content-flow"
    )
    
    settings_found=false
    working_url=""
    
    for url in "${settings_urls[@]}"; do
        settings_page=$(curl -s -b "$TEMP_COOKIES" -w "%{http_code}" -o /tmp/settings_test.html "$BASE_URL$url")
        settings_content=$(cat /tmp/settings_test.html)
        
        # Check if it's a real settings page (not 404 or empty)
        if [ "$settings_page" = "200" ] && ([[ $settings_content == *"<form"* ]] || [[ $settings_content == *"settings"* ]]) && [[ $settings_content != *"Not Found"* ]]; then
            settings_found=true
            working_url="$url"
            break
        fi
    done
    
    if $settings_found; then
        log_test "Settings Page Access" "PASS" "Found settings page at: $working_url"
        
        # Test for form elements
        if [[ $settings_content == *"default_provider"* ]] || [[ $settings_content == *"provider"* ]]; then
            log_test "Settings Form Validation" "PASS" "Provider settings form detected"
        else
            log_test "Settings Form Validation" "FAIL" "Provider dropdown not found in settings"
        fi
        
        # Test for save button
        if [[ $settings_content == *"submit"* ]] || [[ $settings_content == *"Save"* ]]; then
            log_test "Save Button Detection" "PASS" "Save functionality detected"
        else
            log_test "Save Button Detection" "FAIL" "Save button not found"
        fi
        
    else
        log_test "Settings Page Access" "FAIL" "Could not locate plugin settings page"
    fi
}

# Test 5: API Key Security Check
test_api_security() {
    echo -e "\n🔒 Testing API Key Security..."
    
    if [ ! -f "/tmp/settings_test.html" ]; then
        log_test "API Key Security" "FAIL" "No settings page content to analyze"
        return 1
    fi
    
    settings_content=$(cat /tmp/settings_test.html)
    
    # Check for plaintext API keys (security issue)
    if [[ $settings_content == *'type="text"'* ]] && [[ $settings_content == *"key"* ]]; then
        log_test "CRITICAL - API Key Security" "FAIL" "API keys may be exposed in plain text fields"
    elif [[ $settings_content == *'type="password"'* ]] && [[ $settings_content == *"key"* ]]; then
        log_test "API Key Security" "PASS" "API keys properly secured with password fields"
    else
        log_test "API Key Security" "INFO" "No API key fields detected for security analysis"
    fi
}

# Test 6: Gutenberg Editor Access
test_gutenberg_access() {
    echo -e "\n📝 Testing Gutenberg Editor Access..."
    
    if [ ! -f "$TEMP_COOKIES" ]; then
        log_test "Gutenberg Access" "FAIL" "No login session available"
        return 1
    fi
    
    editor_page=$(curl -s -b "$TEMP_COOKIES" "$BASE_URL/wp-admin/post-new.php")
    
    if [[ $editor_page == *"block-editor"* ]] || [[ $editor_page == *"gutenberg"* ]] || [[ $editor_page == *"wp-block"* ]]; then
        log_test "Gutenberg Editor Access" "PASS" "Gutenberg block editor is accessible"
        
        # Look for plugin blocks (this is harder to detect via curl)
        if [[ $editor_page == *"ai-"* ]] || [[ $editor_page == *"content-flow"* ]]; then
            log_test "Plugin Block Detection" "PASS" "Plugin blocks may be registered"
        else
            log_test "Plugin Block Detection" "INFO" "Plugin blocks not detectable via server-side check"
        fi
    else
        log_test "Gutenberg Editor Access" "FAIL" "Gutenberg editor not accessible or not enabled"
    fi
}

# Generate Final Report
generate_report() {
    echo -e "\n" 
    echo "============================================================"
    echo "📊 COMPREHENSIVE WORDPRESS E2E TEST RESULTS"
    echo "============================================================"
    echo -e "🏃 Total Tests: $((TESTS_PASSED + TESTS_FAILED))"
    echo -e "✅ Passed: $TESTS_PASSED"
    echo -e "❌ Failed: $TESTS_FAILED"
    
    if [ $((TESTS_PASSED + TESTS_FAILED)) -gt 0 ]; then
        success_rate=$(( (TESTS_PASSED * 100) / (TESTS_PASSED + TESTS_FAILED) ))
        echo -e "📈 Success Rate: $success_rate%"
    fi
    
    if [ ${#CRITICAL_ISSUES[@]} -gt 0 ]; then
        echo -e "\n🚨 CRITICAL ISSUES FOUND:"
        for issue in "${CRITICAL_ISSUES[@]}"; do
            echo -e "${RED}   • $issue${NC}"
        done
    fi
    
    echo -e "\n🎯 PRODUCTION READINESS ASSESSMENT:"
    
    if [ $TESTS_FAILED -eq 0 ] && [ $TESTS_PASSED -ge 4 ]; then
        echo -e "${GREEN}🟢 READY FOR PRODUCTION${NC} - All critical tests passing"
    elif [ ${#CRITICAL_ISSUES[@]} -gt 0 ]; then
        echo -e "${RED}🔴 NOT READY FOR PRODUCTION${NC} - Critical issues need fixing"
    elif [ $TESTS_FAILED -le 2 ]; then
        echo -e "${YELLOW}🟡 MOSTLY READY${NC} - Some minor issues to address"
    else
        echo -e "${RED}🔴 NOT READY FOR PRODUCTION${NC} - Multiple issues found"
    fi
    
    echo -e "\n⚠️  NOTE: This script cannot test the critical settings persistence bug."
    echo -e "   Please use the manual test HTML file to validate dropdown persistence."
    echo "============================================================"
}

# Cleanup function
cleanup() {
    rm -f "$TEMP_COOKIES" /tmp/login_response.html /tmp/settings_test.html
}

# Main execution
main() {
    echo "🔧 Starting WordPress endpoint validation..."
    echo "Base URL: $BASE_URL"
    echo "Admin User: $ADMIN_USER"
    echo ""
    
    # Run tests in sequence
    test_wordpress_connectivity
    
    if test_wordpress_login; then
        test_plugin_detection
        test_settings_access
        test_api_security
        test_gutenberg_access
    else
        echo -e "\n${RED}⚠️ Cannot continue tests without admin access${NC}"
    fi
    
    # Generate final report
    generate_report
    
    # Cleanup
    cleanup
}

# Run main function
main