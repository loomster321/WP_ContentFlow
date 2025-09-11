#!/bin/bash

echo "🚀 Comprehensive WordPress AI Content Flow Plugin Test"
echo "=================================================="
echo "🌍 Testing WordPress at: http://localhost:8080"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test results
TESTS_PASSED=0
TESTS_FAILED=0

# Function to run test
run_test() {
    local test_name="$1"
    local test_command="$2"
    local expected_result="$3"
    
    echo -e "${BLUE}📋 TEST: ${test_name}${NC}"
    
    if eval "$test_command"; then
        echo -e "${GREEN}✅ PASSED: ${test_name}${NC}"
        ((TESTS_PASSED++))
        return 0
    else
        echo -e "${RED}❌ FAILED: ${test_name}${NC}"
        ((TESTS_FAILED++))
        return 1
    fi
    echo ""
}

# Create tmp directory for outputs
mkdir -p /home/timl/dev/WP_ContentFlow/tmp/test_results

# 1. Test WordPress Basic Connectivity
echo -e "${BLUE}📋 TEST 1: WordPress Basic Connectivity${NC}"
if curl -s -o /dev/null -w "%{http_code}" http://localhost:8080 | grep -q "200"; then
    echo -e "${GREEN}✅ WordPress is accessible${NC}"
    ((TESTS_PASSED++))
else
    echo -e "${RED}❌ WordPress is not accessible${NC}"
    ((TESTS_FAILED++))
fi
echo ""

# 2. Test WordPress Admin Login Page
echo -e "${BLUE}📋 TEST 2: WordPress Admin Login Page${NC}"
ADMIN_RESPONSE=$(curl -s http://localhost:8080/wp-admin/)
if echo "$ADMIN_RESPONSE" | grep -q "wp-login"; then
    echo -e "${GREEN}✅ Admin login page accessible${NC}"
    ((TESTS_PASSED++))
else
    echo -e "${RED}❌ Admin login page not accessible${NC}"
    ((TESTS_FAILED++))
fi
echo ""

# 3. Test WordPress Login and Cookie Retrieval
echo -e "${BLUE}📋 TEST 3: WordPress Admin Login${NC}"
LOGIN_RESPONSE=$(curl -s -c /home/timl/dev/WP_ContentFlow/tmp/cookies.txt \
    -d "log=admin" \
    -d "pwd=%21%33cTXkh%29%239iDHhV5o%2aN" \
    -d "wp-submit=Log+In" \
    -d "redirect_to=http%3A%2F%2Flocalhost%3A8080%2Fwp-admin%2F" \
    -d "testcookie=1" \
    -X POST \
    http://localhost:8080/wp-login.php)

if [ -f /home/timl/dev/WP_ContentFlow/tmp/cookies.txt ] && grep -q "wordpress_logged_in" /home/timl/dev/WP_ContentFlow/tmp/cookies.txt; then
    echo -e "${GREEN}✅ Login successful - cookies obtained${NC}"
    ((TESTS_PASSED++))
else
    echo -e "${RED}❌ Login failed - no valid cookies${NC}"
    ((TESTS_FAILED++))
fi
echo ""

# 4. Test WordPress Admin Dashboard Access
echo -e "${BLUE}📋 TEST 4: WordPress Admin Dashboard Access${NC}"
DASHBOARD_RESPONSE=$(curl -s -b /home/timl/dev/WP_ContentFlow/tmp/cookies.txt http://localhost:8080/wp-admin/)
if echo "$DASHBOARD_RESPONSE" | grep -q "Dashboard"; then
    echo -e "${GREEN}✅ Admin dashboard accessible${NC}"
    ((TESTS_PASSED++))
    echo "$DASHBOARD_RESPONSE" > /home/timl/dev/WP_ContentFlow/tmp/test_results/dashboard.html
else
    echo -e "${RED}❌ Admin dashboard not accessible${NC}"
    ((TESTS_FAILED++))
fi
echo ""

# 5. Test Plugins Page and Plugin Detection
echo -e "${BLUE}📋 TEST 5: Plugin Detection${NC}"
PLUGINS_RESPONSE=$(curl -s -b /home/timl/dev/WP_ContentFlow/tmp/cookies.txt http://localhost:8080/wp-admin/plugins.php)
echo "$PLUGINS_RESPONSE" > /home/timl/dev/WP_ContentFlow/tmp/test_results/plugins.html

if echo "$PLUGINS_RESPONSE" | grep -q "wp-content-flow"; then
    echo -e "${GREEN}✅ WP Content Flow plugin found in plugins list${NC}"
    ((TESTS_PASSED++))
    
    # Check if plugin is active
    if echo "$PLUGINS_RESPONSE" | grep "wp-content-flow" | grep -q "Deactivate"; then
        echo -e "${GREEN}   ✅ Plugin is ACTIVE${NC}"
    else
        echo -e "${YELLOW}   ⚠️  Plugin found but may not be active${NC}"
    fi
else
    echo -e "${RED}❌ WP Content Flow plugin NOT found in plugins list${NC}"
    ((TESTS_FAILED++))
fi
echo ""

# 6. Test Plugin Admin Menu
echo -e "${BLUE}📋 TEST 6: Plugin Admin Menu${NC}"
if echo "$DASHBOARD_RESPONSE" | grep -q "wp-content-flow"; then
    echo -e "${GREEN}✅ Plugin admin menu found${NC}"
    ((TESTS_PASSED++))
else
    echo -e "${RED}❌ Plugin admin menu not found${NC}"
    ((TESTS_FAILED++))
fi
echo ""

# 7. Test Plugin Admin Page
echo -e "${BLUE}📋 TEST 7: Plugin Admin Page Access${NC}"
PLUGIN_ADMIN_RESPONSE=$(curl -s -b /home/timl/dev/WP_ContentFlow/tmp/cookies.txt \
    "http://localhost:8080/wp-admin/admin.php?page=wp-content-flow")
echo "$PLUGIN_ADMIN_RESPONSE" > /home/timl/dev/WP_ContentFlow/tmp/test_results/plugin_admin.html

if echo "$PLUGIN_ADMIN_RESPONSE" | grep -q -i "content.flow\|AI.*Content"; then
    echo -e "${GREEN}✅ Plugin admin page loads${NC}"
    ((TESTS_PASSED++))
else
    echo -e "${RED}❌ Plugin admin page not loading properly${NC}"
    ((TESTS_FAILED++))
fi
echo ""

# 8. Test Plugin Settings Page
echo -e "${BLUE}📋 TEST 8: Plugin Settings Page${NC}"
SETTINGS_RESPONSE=$(curl -s -b /home/timl/dev/WP_ContentFlow/tmp/cookies.txt \
    "http://localhost:8080/wp-admin/admin.php?page=wp-content-flow-settings")
echo "$SETTINGS_RESPONSE" > /home/timl/dev/WP_ContentFlow/tmp/test_results/settings.html

if echo "$SETTINGS_RESPONSE" | grep -q "ai_provider\|openai_api_key"; then
    echo -e "${GREEN}✅ Plugin settings page loads with form elements${NC}"
    ((TESTS_PASSED++))
else
    echo -e "${RED}❌ Plugin settings page not loading properly${NC}"
    ((TESTS_FAILED++))
fi
echo ""

# 9. Test WordPress Block Editor
echo -e "${BLUE}📋 TEST 9: WordPress Block Editor${NC}"
EDITOR_RESPONSE=$(curl -s -b /home/timl/dev/WP_ContentFlow/tmp/cookies.txt \
    "http://localhost:8080/wp-admin/post-new.php")
echo "$EDITOR_RESPONSE" > /home/timl/dev/WP_ContentFlow/tmp/test_results/editor.html

if echo "$EDITOR_RESPONSE" | grep -q "block-editor"; then
    echo -e "${GREEN}✅ Gutenberg block editor loads${NC}"
    ((TESTS_PASSED++))
else
    echo -e "${RED}❌ Gutenberg block editor not loading${NC}"
    ((TESTS_FAILED++))
fi
echo ""

# 10. Test REST API Endpoints
echo -e "${BLUE}📋 TEST 10: REST API Endpoints${NC}"

# Test WordPress REST API
WP_API_RESPONSE=$(curl -s -w "%{http_code}" -o /dev/null http://localhost:8080/wp-json/)
if [ "$WP_API_RESPONSE" = "200" ]; then
    echo -e "${GREEN}✅ WordPress REST API accessible${NC}"
    ((TESTS_PASSED++))
else
    echo -e "${RED}❌ WordPress REST API not accessible (${WP_API_RESPONSE})${NC}"
    ((TESTS_FAILED++))
fi

# Test Plugin REST API
PLUGIN_API_RESPONSE=$(curl -s -w "%{http_code}" -o /dev/null http://localhost:8080/wp-json/wp-content-flow/v1/workflows)
echo -e "${BLUE}   Plugin API Status: ${PLUGIN_API_RESPONSE}${NC}"

if [ "$PLUGIN_API_RESPONSE" = "200" ] || [ "$PLUGIN_API_RESPONSE" = "401" ] || [ "$PLUGIN_API_RESPONSE" = "403" ]; then
    echo -e "${GREEN}   ✅ Plugin REST API endpoints responding${NC}"
else
    echo -e "${YELLOW}   ⚠️  Plugin REST API may not be registered (${PLUGIN_API_RESPONSE})${NC}"
fi
echo ""

# 11. Test JavaScript and CSS Asset Loading
echo -e "${BLUE}📋 TEST 11: Plugin Assets${NC}"
ASSETS_FOUND=0

# Check if editor contains references to plugin scripts/styles
if echo "$EDITOR_RESPONSE" | grep -q "wp-content-flow"; then
    echo -e "${GREEN}✅ Plugin assets referenced in editor${NC}"
    ((ASSETS_FOUND++))
fi

if echo "$PLUGIN_ADMIN_RESPONSE" | grep -q "wp-content-flow.*\.js\|wp-content-flow.*\.css"; then
    echo -e "${GREEN}✅ Plugin assets found in admin page${NC}"
    ((ASSETS_FOUND++))
fi

if [ $ASSETS_FOUND -gt 0 ]; then
    ((TESTS_PASSED++))
else
    echo -e "${YELLOW}⚠️  Plugin assets not clearly identified${NC}"
    ((TESTS_FAILED++))
fi
echo ""

# 12. Check for JavaScript Errors in HTML
echo -e "${BLUE}📋 TEST 12: HTML Error Analysis${NC}"

# Check for common error patterns in the HTML responses
ERROR_PATTERNS=("Fatal error" "Parse error" "Warning:" "Notice:" "Deprecated:")
ERRORS_FOUND=0

for pattern in "${ERROR_PATTERNS[@]}"; do
    if grep -q "$pattern" /home/timl/dev/WP_ContentFlow/tmp/test_results/*.html 2>/dev/null; then
        echo -e "${RED}❌ Found PHP error pattern: ${pattern}${NC}"
        ((ERRORS_FOUND++))
    fi
done

if [ $ERRORS_FOUND -eq 0 ]; then
    echo -e "${GREEN}✅ No obvious PHP errors detected in HTML responses${NC}"
    ((TESTS_PASSED++))
else
    echo -e "${RED}❌ PHP errors detected in responses${NC}"
    ((TESTS_FAILED++))
fi
echo ""

# Summary
echo "=================================================="
echo -e "${BLUE}📊 TEST SUMMARY${NC}"
echo "=================================================="
echo -e "✅ Tests Passed: ${GREEN}${TESTS_PASSED}${NC}"
echo -e "❌ Tests Failed: ${RED}${TESTS_FAILED}${NC}"
echo -e "📁 Test Results: /home/timl/dev/WP_ContentFlow/tmp/test_results/"
echo ""

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "${GREEN}🎉 ALL TESTS PASSED!${NC}"
    exit 0
else
    echo -e "${YELLOW}⚠️  Some tests failed - check results for details${NC}"
    exit 1
fi