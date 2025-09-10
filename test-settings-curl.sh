#!/bin/bash

# Comprehensive Settings Save Test using curl
# Tests the complete WordPress Content Flow plugin settings save workflow

set -e  # Exit on any error

WORDPRESS_URL="http://localhost:8080"
LOGIN_URL="${WORDPRESS_URL}/wp-login.php"
SETTINGS_URL="${WORDPRESS_URL}/wp-admin/admin.php?page=wp-content-flow-settings"
USERNAME="admin"
PASSWORD="!3cTXkh)9iDHhV5o*N"
COOKIES_FILE="cookies.txt"
RESULTS_DIR="test-results"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🚀 Starting WordPress Content Flow Settings Save Test${NC}"
echo "================================================="

# Create results directory
mkdir -p "$RESULTS_DIR"

# Clean up any existing cookies
rm -f "$COOKIES_FILE"

# Step 1: Login to WordPress Admin
echo -e "\n${BLUE}📝 Step 1: Logging into WordPress Admin${NC}"
login_response=$(curl -s -c "$COOKIES_FILE" \
    -d "log=${USERNAME}" \
    -d "pwd=${PASSWORD}" \
    -d "wp-submit=Log In" \
    -d "redirect_to=${WORDPRESS_URL}/wp-admin/" \
    -L \
    -w "HTTP_CODE:%{http_code}|URL:%{url_effective}|TIME:%{time_total}" \
    "$LOGIN_URL")

echo "Login response: $login_response"

if [[ $login_response == *"HTTP_CODE:200"* ]]; then
    echo -e "${GREEN}✅ Successfully logged in${NC}"
else
    echo -e "${RED}❌ Login failed${NC}"
    exit 1
fi

# Step 2: Get the settings page and extract current values
echo -e "\n${BLUE}🔧 Step 2: Getting current settings page${NC}"
settings_html=$(curl -s -b "$COOKIES_FILE" "$SETTINGS_URL")

# Save the initial HTML for debugging
echo "$settings_html" > "$RESULTS_DIR/01-initial-settings.html"

# Extract current dropdown value
current_provider=$(echo "$settings_html" | grep -o 'value="[^"]*" selected' | grep -o 'value="[^"]*"' | sed 's/value="//g' | sed 's/"//g' | head -n 1)

# If no selected option found, check default structure
if [[ -z "$current_provider" ]]; then
    current_provider=$(echo "$settings_html" | grep -A 10 'name="wp_content_flow_settings\[default_ai_provider\]"' | grep 'selected' | head -n 1 | grep -o 'value="[^"]*"' | sed 's/value="//g' | sed 's/"//g')
fi

# Fallback: extract from debug comment
if [[ -z "$current_provider" ]]; then
    current_provider=$(echo "$settings_html" | grep -o 'Current provider value = "[^"]*"' | sed 's/Current provider value = "//g' | sed 's/"//g')
fi

echo "Current provider: ${current_provider:-'NOT_FOUND'}"

# Extract nonce
nonce=$(echo "$settings_html" | grep -o 'name="_wpnonce"[^>]*value="[^"]*"' | sed 's/.*value="//g' | sed 's/".*//g')
echo "Extracted nonce: ${nonce:-'NOT_FOUND'}"

# Extract option_page value
option_page=$(echo "$settings_html" | grep -o 'name="option_page"[^>]*value="[^"]*"' | sed 's/.*value="//g' | sed 's/".*//g')
echo "Option page: ${option_page:-'NOT_FOUND'}"

# Extract cache setting
cache_checked=$(echo "$settings_html" | grep -o 'name="wp_content_flow_settings\[cache_enabled\]"[^>]*checked' | wc -l)
echo "Cache currently enabled: $([ $cache_checked -gt 0 ] && echo 'YES' || echo 'NO')"

# Extract rate limit value
rate_limit=$(echo "$settings_html" | grep -o 'name="wp_content_flow_settings\[requests_per_minute\]"[^>]*value="[^"]*"' | sed 's/.*value="//g' | sed 's/".*//g')
echo "Current rate limit: ${rate_limit:-'NOT_FOUND'}"

# Step 3: Determine target values for testing
echo -e "\n${BLUE}🎯 Step 3: Determining target test values${NC}"

# Choose different provider
if [[ "$current_provider" == "openai" ]]; then
    target_provider="anthropic"
elif [[ "$current_provider" == "anthropic" ]]; then
    target_provider="google"  
else
    target_provider="openai"
fi

# Toggle cache setting
if [[ $cache_checked -gt 0 ]]; then
    target_cache=""  # Unchecked (no value sent)
    target_cache_display="DISABLED"
else
    target_cache="1"  # Checked
    target_cache_display="ENABLED"
fi

target_rate_limit="25"

echo "Target provider: $target_provider"
echo "Target cache: $target_cache_display"
echo "Target rate limit: $target_rate_limit"

# Step 4: Submit the settings form with changes
echo -e "\n${BLUE}💾 Step 4: Submitting settings form with changes${NC}"

# Prepare form data
form_data=""
form_data="${form_data}option_page=${option_page}"
form_data="${form_data}&_wpnonce=${nonce}"
form_data="${form_data}&wp_content_flow_settings%5Bdefault_ai_provider%5D=${target_provider}"
form_data="${form_data}&wp_content_flow_settings%5Brequests_per_minute%5D=${target_rate_limit}"

# Add cache setting if enabled
if [[ -n "$target_cache" ]]; then
    form_data="${form_data}&wp_content_flow_settings%5Bcache_enabled%5D=${target_cache}"
fi

# Add some API keys to pass validation
form_data="${form_data}&wp_content_flow_settings%5Bopenai_api_key%5D=test-openai-key-12345"
form_data="${form_data}&wp_content_flow_settings%5Banthropic_api_key%5D=test-anthropic-key-12345"

echo "Form data: $form_data"

# Submit the form
submit_response=$(curl -s -b "$COOKIES_FILE" -c "$COOKIES_FILE" \
    -X POST \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -H "Referer: $SETTINGS_URL" \
    -d "$form_data" \
    -L \
    -w "HTTP_CODE:%{http_code}|URL:%{url_effective}|TIME:%{time_total}|REDIRECT_COUNT:%{num_redirects}" \
    "$SETTINGS_URL")

echo "Submit response: $submit_response"

# Save the response for analysis
echo "$submit_response" > "$RESULTS_DIR/02-submit-response.html"

# Step 5: Check the settings page again to see if changes persisted
echo -e "\n${BLUE}🔍 Step 5: Verifying settings persistence${NC}"

# Wait a moment for any processing
sleep 2

# Get the settings page again
verification_html=$(curl -s -b "$COOKIES_FILE" "$SETTINGS_URL")
echo "$verification_html" > "$RESULTS_DIR/03-verification-settings.html"

# Check for success/error messages
success_messages=$(echo "$verification_html" | grep -o 'class="[^"]*notice[^"]*success[^"]*"[^>]*>[^<]*<p>[^<]*</p>' | sed 's/<[^>]*>//g' | tr '\n' ' ')
error_messages=$(echo "$verification_html" | grep -o 'class="[^"]*notice[^"]*error[^"]*"[^>]*>[^<]*<p>[^<]*</p>' | sed 's/<[^>]*>//g' | tr '\n' ' ')

echo "Success messages: ${success_messages:-'NONE'}"
echo "Error messages: ${error_messages:-'NONE'}"

# Check if settings persisted
final_provider=$(echo "$verification_html" | grep -o 'value="[^"]*" selected' | grep -o 'value="[^"]*"' | sed 's/value="//g' | sed 's/"//g' | head -n 1)

# Fallback extraction methods
if [[ -z "$final_provider" ]]; then
    final_provider=$(echo "$verification_html" | grep -A 10 'name="wp_content_flow_settings\[default_ai_provider\]"' | grep 'selected' | head -n 1 | grep -o 'value="[^"]*"' | sed 's/value="//g' | sed 's/"//g')
fi

if [[ -z "$final_provider" ]]; then
    final_provider=$(echo "$verification_html" | grep -o 'Current provider value = "[^"]*"' | sed 's/Current provider value = "//g' | sed 's/"//g')
fi

final_cache_checked=$(echo "$verification_html" | grep -o 'name="wp_content_flow_settings\[cache_enabled\]"[^>]*checked' | wc -l)
final_rate_limit=$(echo "$verification_html" | grep -o 'name="wp_content_flow_settings\[requests_per_minute\]"[^>]*value="[^"]*"' | sed 's/.*value="//g' | sed 's/".*//g')

# Step 6: Analyze Results
echo -e "\n${BLUE}📊 Step 6: Test Results Analysis${NC}"
echo "============================================="

echo -e "\n${YELLOW}PROVIDER SETTING:${NC}"
echo "  Initial:  $current_provider"
echo "  Target:   $target_provider"
echo "  Final:    ${final_provider:-'NOT_FOUND'}"

if [[ "$final_provider" == "$target_provider" ]]; then
    echo -e "  Result:   ${GREEN}✅ SUCCESS - Provider change persisted${NC}"
else
    echo -e "  Result:   ${RED}❌ FAILED - Provider reverted or not saved${NC}"
fi

echo -e "\n${YELLOW}CACHE SETTING:${NC}"
echo "  Initial:  $([ $cache_checked -gt 0 ] && echo 'ENABLED' || echo 'DISABLED')"
echo "  Target:   $target_cache_display"
echo "  Final:    $([ $final_cache_checked -gt 0 ] && echo 'ENABLED' || echo 'DISABLED')"

expected_cache_state=$([ -n "$target_cache" ] && echo 1 || echo 0)
if [[ $final_cache_checked -eq $expected_cache_state ]]; then
    echo -e "  Result:   ${GREEN}✅ SUCCESS - Cache setting persisted${NC}"
else
    echo -e "  Result:   ${RED}❌ FAILED - Cache setting not persisted${NC}"
fi

echo -e "\n${YELLOW}RATE LIMIT SETTING:${NC}"
echo "  Initial:  $rate_limit"
echo "  Target:   $target_rate_limit"
echo "  Final:    ${final_rate_limit:-'NOT_FOUND'}"

if [[ "$final_rate_limit" == "$target_rate_limit" ]]; then
    echo -e "  Result:   ${GREEN}✅ SUCCESS - Rate limit persisted${NC}"
else
    echo -e "  Result:   ${RED}❌ FAILED - Rate limit not persisted${NC}"
fi

echo -e "\n${YELLOW}MESSAGES:${NC}"
if [[ -n "$success_messages" ]]; then
    echo -e "  Success:  ${GREEN}$success_messages${NC}"
fi
if [[ -n "$error_messages" ]]; then
    echo -e "  Errors:   ${RED}$error_messages${NC}"
fi

# Step 7: Additional debugging information
echo -e "\n${BLUE}🔧 Step 7: Additional Debug Information${NC}"

# Check for WordPress errors in the HTML
wp_errors=$(echo "$verification_html" | grep -i "fatal error\|parse error\|database error" | head -3)
if [[ -n "$wp_errors" ]]; then
    echo -e "${RED}WordPress Errors Found:${NC}"
    echo "$wp_errors"
fi

# Check for JavaScript errors (look for script tags with errors)
js_errors=$(echo "$verification_html" | grep -o "console\.error[^;]*" | head -3)
if [[ -n "$js_errors" ]]; then
    echo -e "${YELLOW}Potential JavaScript Issues:${NC}"  
    echo "$js_errors"
fi

# Check database value display  
db_value_display=$(echo "$verification_html" | grep -A 2 "Current database value" | sed 's/<[^>]*>//g' | tr '\n' ' ')
if [[ -n "$db_value_display" ]]; then
    echo -e "${YELLOW}Database Value Display:${NC} $db_value_display"
fi

# Final summary
echo -e "\n${BLUE}📋 FINAL SUMMARY${NC}"
echo "================="

provider_success=$([[ "$final_provider" == "$target_provider" ]] && echo "YES" || echo "NO")
cache_success=$([[ $final_cache_checked -eq $expected_cache_state ]] && echo "YES" || echo "NO")  
rate_success=$([[ "$final_rate_limit" == "$target_rate_limit" ]] && echo "YES" || echo "NO")

echo "Provider save successful: $provider_success"
echo "Cache save successful: $cache_success"
echo "Rate limit save successful: $rate_success"

if [[ "$provider_success" == "YES" && "$cache_success" == "YES" && "$rate_success" == "YES" ]]; then
    echo -e "\n${GREEN}🎉 ALL TESTS PASSED - Settings save functionality working correctly${NC}"
    exit_code=0
else
    echo -e "\n${RED}❌ SOME TESTS FAILED - Settings save functionality has issues${NC}"
    exit_code=1
fi

# Clean up
rm -f "$COOKIES_FILE"

echo -e "\n${BLUE}📁 Test artifacts saved in: $RESULTS_DIR/${NC}"
echo "   - 01-initial-settings.html"
echo "   - 02-submit-response.html"  
echo "   - 03-verification-settings.html"

exit $exit_code