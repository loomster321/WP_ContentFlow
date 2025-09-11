#!/bin/bash

echo "🚀 WordPress AI Content Flow Plugin - Proper Login Test"
echo "======================================================"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Create results directory
mkdir -p /home/timl/dev/WP_ContentFlow/tmp/test_results

# Step 1: Get login page and extract nonce
echo -e "${BLUE}📋 Step 1: Getting login page and extracting nonce${NC}"

LOGIN_PAGE=$(curl -s -c /home/timl/dev/WP_ContentFlow/tmp/cookies.txt http://localhost:8080/wp-login.php)
echo "$LOGIN_PAGE" > /home/timl/dev/WP_ContentFlow/tmp/test_results/login_page.html

# Step 2: Perform login with proper form data
echo -e "${BLUE}📋 Step 2: Performing login${NC}"

LOGIN_RESPONSE=$(curl -s -b /home/timl/dev/WP_ContentFlow/tmp/cookies.txt \
    -c /home/timl/dev/WP_ContentFlow/tmp/cookies_after_login.txt \
    -d "log=admin" \
    -d "pwd=!3cTXkh)9iDHhV5o*N" \
    -d "wp-submit=Log+In" \
    -d "redirect_to=http%3A%2F%2Flocalhost%3A8080%2Fwp-admin%2F" \
    -d "testcookie=1" \
    -X POST \
    -L \
    http://localhost:8080/wp-login.php)

echo "$LOGIN_RESPONSE" > /home/timl/dev/WP_ContentFlow/tmp/test_results/login_response.html

# Check if login was successful
if echo "$LOGIN_RESPONSE" | grep -q "Dashboard\|wp-admin" && ! echo "$LOGIN_RESPONSE" | grep -q "loginform"; then
    echo -e "${GREEN}✅ Login successful${NC}"
    
    # Step 3: Test plugin pages
    echo -e "${BLUE}📋 Step 3: Testing plugin admin pages${NC}"
    
    # Check plugin admin main page
    PLUGIN_MAIN=$(curl -s -b /home/timl/dev/WP_ContentFlow/tmp/cookies_after_login.txt \
        "http://localhost:8080/wp-admin/admin.php?page=wp-content-flow")
    echo "$PLUGIN_MAIN" > /home/timl/dev/WP_ContentFlow/tmp/test_results/plugin_main.html
    
    if echo "$PLUGIN_MAIN" | grep -q -i "content.*flow\|AI.*Content"; then
        echo -e "${GREEN}✅ Plugin main page accessible${NC}"
    else
        echo -e "${RED}❌ Plugin main page not loading correctly${NC}"
    fi
    
    # Check plugin settings page
    PLUGIN_SETTINGS=$(curl -s -b /home/timl/dev/WP_ContentFlow/tmp/cookies_after_login.txt \
        "http://localhost:8080/wp-admin/admin.php?page=wp-content-flow-settings")
    echo "$PLUGIN_SETTINGS" > /home/timl/dev/WP_ContentFlow/tmp/test_results/plugin_settings.html
    
    if echo "$PLUGIN_SETTINGS" | grep -q "ai_provider\|openai_api_key"; then
        echo -e "${GREEN}✅ Plugin settings page accessible${NC}"
    else
        echo -e "${RED}❌ Plugin settings page not loading correctly${NC}"
    fi
    
    # Step 4: Test Gutenberg editor
    echo -e "${BLUE}📋 Step 4: Testing Gutenberg editor${NC}"
    
    EDITOR_PAGE=$(curl -s -b /home/timl/dev/WP_ContentFlow/tmp/cookies_after_login.txt \
        "http://localhost:8080/wp-admin/post-new.php")
    echo "$EDITOR_PAGE" > /home/timl/dev/WP_ContentFlow/tmp/test_results/editor.html
    
    if echo "$EDITOR_PAGE" | grep -q "block-editor"; then
        echo -e "${GREEN}✅ Gutenberg editor loads${NC}"
        
        # Look for plugin-related scripts and styles
        if echo "$EDITOR_PAGE" | grep -q "wp-content-flow"; then
            echo -e "${GREEN}   ✅ Plugin assets found in editor${NC}"
        else
            echo -e "${YELLOW}   ⚠️  Plugin assets not clearly visible in editor${NC}"
        fi
    else
        echo -e "${RED}❌ Gutenberg editor not loading${NC}"
    fi
    
    # Step 5: Test REST API
    echo -e "${BLUE}📋 Step 5: Testing REST API with authentication${NC}"
    
    # Get nonce for API requests
    if echo "$EDITOR_PAGE" | grep -q "wpApiSettings"; then
        # Extract nonce from editor page
        NONCE=$(echo "$EDITOR_PAGE" | grep -o '"nonce":"[^"]*"' | cut -d'"' -f4)
        if [ ! -z "$NONCE" ]; then
            echo -e "${GREEN}   ✅ Found REST API nonce: ${NONCE:0:20}...${NC}"
            
            # Test plugin API endpoints
            API_WORKFLOWS=$(curl -s -w "%{http_code}" -o /dev/null \
                -b /home/timl/dev/WP_ContentFlow/tmp/cookies_after_login.txt \
                -H "X-WP-Nonce: $NONCE" \
                "http://localhost:8080/wp-json/wp-content-flow/v1/workflows")
            echo -e "${BLUE}   Workflows API: ${API_WORKFLOWS}${NC}"
            
            API_SETTINGS=$(curl -s -w "%{http_code}" -o /dev/null \
                -b /home/timl/dev/WP_ContentFlow/tmp/cookies_after_login.txt \
                -H "X-WP-Nonce: $NONCE" \
                "http://localhost:8080/wp-json/wp-content-flow/v1/settings")
            echo -e "${BLUE}   Settings API: ${API_SETTINGS}${NC}"
        fi
    fi
    
    # Step 6: Check for JavaScript console errors in HTML
    echo -e "${BLUE}📋 Step 6: Analyzing HTML for potential issues${NC}"
    
    # Look for error patterns
    if grep -q "Fatal error\|Parse error\|Warning:\|Notice:" /home/titml/dev/WP_ContentFlow/tmp/test_results/*.html 2>/dev/null; then
        echo -e "${RED}❌ PHP errors detected in HTML responses${NC}"
        grep -n "Fatal error\|Parse error\|Warning:\|Notice:" /home/timl/dev/WP_ContentFlow/tmp/test_results/*.html
    else
        echo -e "${GREEN}✅ No obvious PHP errors in HTML${NC}"
    fi
    
    # Look for JavaScript errors in inline scripts
    if grep -q "console\.error\|Uncaught\|TypeError" /home/timl/dev/WP_ContentFlow/tmp/test_results/*.html; then
        echo -e "${YELLOW}⚠️  Potential JavaScript errors found${NC}"
    else
        echo -e "${GREEN}✅ No obvious JavaScript errors in HTML${NC}"
    fi
    
    # Step 7: Analyze editor page for AI Chat features
    echo -e "${BLUE}📋 Step 7: Looking for AI Chat features in editor${NC}"
    
    # Search for globe icons or AI-related elements
    if grep -q "dashicons-admin-site\|globe\|ai-chat\|content-flow" /home/timl/dev/WP_ContentFlow/tmp/test_results/editor.html; then
        echo -e "${GREEN}✅ Found potential AI-related UI elements${NC}"
        echo -e "${BLUE}   Elements found:${NC}"
        grep -o "dashicons-admin-site[^\"]*\|[^\"]*globe[^\"]*\|[^\"]*ai-chat[^\"]*\|[^\"]*content-flow[^\"]*" /home/timl/dev/WP_ContentFlow/tmp/test_results/editor.html | head -5
    else
        echo -e "${YELLOW}⚠️  No obvious AI Chat UI elements found in editor HTML${NC}"
    fi
    
else
    echo -e "${RED}❌ Login failed${NC}"
    echo "Login response saved to: /home/timl/dev/WP_ContentFlow/tmp/test_results/login_response.html"
fi

echo ""
echo "======================================================"
echo -e "${BLUE}📊 Test completed. Results saved in:${NC}"
echo "📁 /home/timl/dev/WP_ContentFlow/tmp/test_results/"
echo ""
echo -e "${BLUE}Key files to examine:${NC}"
echo "• login_response.html - Login result"
echo "• plugin_main.html - Plugin main admin page"  
echo "• plugin_settings.html - Plugin settings page"
echo "• editor.html - Gutenberg editor with potential AI features"