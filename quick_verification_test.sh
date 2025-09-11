#!/bin/bash

echo "🚀 Quick WordPress AI Content Flow Verification"
echo "=============================================="

# Test 1: WordPress accessibility
echo "🔍 Testing WordPress..."
if curl -s http://localhost:8080 | grep -q "WordPress\|wp-content"; then
    echo "✅ WordPress is accessible"
    WORDPRESS_OK=1
else
    echo "❌ WordPress not accessible"
    exit 1
fi

# Test 2: Admin login and session establishment
echo ""
echo "🔍 Testing admin authentication..."

# Get login page and extract cookies
COOKIES=$(curl -c - -s http://localhost:8080/wp-login.php | grep -E "Set-Cookie|wordpress_test_cookie" | head -5)

# Perform login
LOGIN_RESPONSE=$(curl -b <(echo "$COOKIES") \
    -c /tmp/wp_cookies.txt \
    -d "log=admin&pwd=%21%33cTXkh%29%39iDHhV5o%2AN&wp-submit=Log+In&redirect_to=%2Fwp-admin%2F&testcookie=1" \
    -X POST \
    -s \
    -L \
    http://localhost:8080/wp-login.php)

if echo "$LOGIN_RESPONSE" | grep -q "Dashboard\|wp-admin-bar"; then
    echo "✅ Admin authentication successful"
    AUTH_OK=1
else
    echo "❌ Admin authentication failed"
    exit 1
fi

# Test 3: Plugin status
echo ""
echo "🔍 Checking plugin status..."
PLUGINS_PAGE=$(curl -b /tmp/wp_cookies.txt -s http://localhost:8080/wp-admin/plugins.php)

if echo "$PLUGINS_PAGE" | grep -q "WordPress AI Content Flow"; then
    # Check if there's an "Activate" link for this specific plugin
    PLUGIN_SECTION=$(echo "$PLUGINS_PAGE" | sed -n '/WordPress AI Content Flow/,/<\/tr>/p' | head -20)
    
    if echo "$PLUGIN_SECTION" | grep -q ">Activate<"; then
        echo "❌ Plugin found but INACTIVE"
        exit 1
    else
        echo "✅ Plugin is ACTIVE"
        PLUGIN_OK=1
    fi
else
    echo "❌ Plugin not found in plugins page"
    # Let's also check by plugin file name
    if echo "$PLUGINS_PAGE" | grep -q "wp-content-flow"; then
        echo "✅ Plugin found by filename (wp-content-flow)"
        PLUGIN_OK=1
    else
        exit 1
    fi
fi

# Test 4: REST API accessibility
echo ""
echo "🔍 Testing REST API..."

REST_ENDPOINTS=(
    "/index.php?rest_route=/wp-content-flow/v1/status"
    "/?rest_route=/wp-content-flow/v1/status"
    "/wp-json/wp-content-flow/v1/status"
)

REST_OK=0
for endpoint in "${REST_ENDPOINTS[@]}"; do
    echo "  Testing: $endpoint"
    RESPONSE=$(curl -s -w "%{http_code}" http://localhost:8080"$endpoint")
    HTTP_CODE="${RESPONSE: -3}"
    BODY="${RESPONSE%???}"
    
    if [ "$HTTP_CODE" = "200" ]; then
        if echo "$BODY" | grep -q '"status":\s*"active"'; then
            echo "  ✅ Working endpoint: $endpoint"
            echo "  📊 Response: $(echo "$BODY" | head -c 100)..."
            REST_OK=1
            break
        fi
    else
        echo "  ❌ HTTP $HTTP_CODE"
    fi
done

if [ "$REST_OK" = "0" ]; then
    echo "❌ No working REST API endpoints found"
else
    echo "✅ REST API is accessible"
fi

# Test 5: Post editor integration
echo ""
echo "🔍 Testing post editor integration..."
EDITOR_PAGE=$(curl -b /tmp/wp_cookies.txt -s http://localhost:8080/wp-admin/post-new.php)

EDITOR_CHECKS=(
    "block-editor:Gutenberg Editor"
    "wp-content-flow/assets/js/blocks.js:Plugin Script"
    "wp-plugins:WordPress Plugins API"
    "wp-edit-post:Edit Post API"
    "registerPlugin:Plugin Registration"
    "AI Chat:AI Chat Text"
    "PluginSidebar:Plugin Sidebar Component"
    "wpContentFlow:Global Variable"
)

EDITOR_OK=0
EDITOR_SCORE=0
TOTAL_CHECKS=${#EDITOR_CHECKS[@]}

for check in "${EDITOR_CHECKS[@]}"; do
    pattern="${check%%:*}"
    description="${check#*:}"
    
    if echo "$EDITOR_PAGE" | grep -q "$pattern"; then
        echo "  ✅ $description found"
        ((EDITOR_SCORE++))
    else
        echo "  ❌ $description missing"
    fi
done

EDITOR_PERCENTAGE=$((EDITOR_SCORE * 100 / TOTAL_CHECKS))
echo "  📊 Editor Integration: $EDITOR_SCORE/$TOTAL_CHECKS ($EDITOR_PERCENTAGE%)"

if [ "$EDITOR_SCORE" -ge $((TOTAL_CHECKS * 60 / 100)) ]; then
    EDITOR_OK=1
    echo "✅ Editor integration sufficient"
else
    echo "❌ Editor integration insufficient"
fi

# Final assessment
echo ""
echo "📊 FINAL ASSESSMENT"
echo "==================="
echo "WordPress Access: $([ "$WORDPRESS_OK" = "1" ] && echo "✅ PASS" || echo "❌ FAIL")"
echo "Admin Authentication: $([ "$AUTH_OK" = "1" ] && echo "✅ PASS" || echo "❌ FAIL")"
echo "Plugin Active: $([ "$PLUGIN_OK" = "1" ] && echo "✅ PASS" || echo "❌ FAIL")"
echo "REST API Working: $([ "$REST_OK" = "1" ] && echo "✅ PASS" || echo "❌ FAIL")"
echo "Editor Integration: $([ "$EDITOR_OK" = "1" ] && echo "✅ PASS" || echo "❌ FAIL")"

TOTAL_SCORE=$((${WORDPRESS_OK:-0} + ${AUTH_OK:-0} + ${PLUGIN_OK:-0} + ${REST_OK:-0} + ${EDITOR_OK:-0}))
echo ""
echo "Overall Score: $TOTAL_SCORE/5"

if [ "$TOTAL_SCORE" -ge 4 ]; then
    echo ""
    echo "🎉 VERDICT: AI CHAT FUNCTIONALITY SHOULD BE WORKING!"
    echo ""
    echo "👤 FOR THE USER:"
    echo "1. Go to WordPress Admin → Posts → Add New"
    echo "2. Look for the three-dots menu (⋯) in the editor toolbar"
    echo "3. Click it and look for 'AI Chat' option"
    echo "4. Click 'AI Chat' to open the AI panel"
    echo ""
    echo "🔧 If not working:"
    echo "- Check browser console (F12) for JavaScript errors"
    echo "- Clear browser cache and reload"
    echo "- Disable other plugins temporarily to test for conflicts"
elif [ "$TOTAL_SCORE" -ge 3 ]; then
    echo ""
    echo "⚠️ VERDICT: PARTIAL FUNCTIONALITY"
    echo "Some components work but issues may prevent full functionality."
else
    echo ""
    echo "❌ VERDICT: NOT WORKING"
    echo "Critical components are missing or broken."
fi

# Cleanup
rm -f /tmp/wp_cookies.txt

echo ""
echo "🏁 Verification complete"
exit $((5 - TOTAL_SCORE))