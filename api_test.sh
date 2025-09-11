#!/bin/bash

echo "🚀 WordPress AI Content Flow Plugin - API Testing"
echo "================================================"

# Extract nonce from the logged-in editor session
NONCE=$(grep -o '"nonce":"[^"]*"' /home/timl/dev/WP_ContentFlow/tmp/test_results/editor.html | cut -d'"' -f4)

if [ -z "$NONCE" ]; then
    echo "❌ No nonce found in editor HTML"
    exit 1
fi

echo "✅ Using nonce: ${NONCE:0:20}..."

# Test all API endpoints
echo -e "\n📋 Testing API Endpoints:"
echo "=========================="

# 1. Test workflows endpoint
echo "1. Testing /wp-json/wp-content-flow/v1/workflows"
WORKFLOWS_RESPONSE=$(curl -s \
    -b /home/timl/dev/WP_ContentFlow/tmp/cookies_after_login.txt \
    -H "X-WP-Nonce: $NONCE" \
    -H "Content-Type: application/json" \
    "http://localhost:8080/wp-json/wp-content-flow/v1/workflows")

echo "Response: $WORKFLOWS_RESPONSE"

# 2. Test settings endpoint  
echo -e "\n2. Testing /wp-json/wp-content-flow/v1/settings"
SETTINGS_RESPONSE=$(curl -s \
    -b /home/timl/dev/WP_ContentFlow/tmp/cookies_after_login.txt \
    -H "X-WP-Nonce: $NONCE" \
    -H "Content-Type: application/json" \
    "http://localhost:8080/wp-json/wp-content-flow/v1/settings")

echo "Response: $SETTINGS_RESPONSE"

# 3. Test generate endpoint (should fail without proper data)
echo -e "\n3. Testing /wp-json/wp-content-flow/v1/ai/generate (without data)"
GENERATE_RESPONSE=$(curl -s \
    -b /home/timl/dev/WP_ContentFlow/tmp/cookies_after_login.txt \
    -H "X-WP-Nonce: $NONCE" \
    -H "Content-Type: application/json" \
    -X POST \
    "http://localhost:8080/wp-json/wp-content-flow/v1/ai/generate")

echo "Response: $GENERATE_RESPONSE"

# 4. Test with sample data
echo -e "\n4. Testing /wp-json/wp-content-flow/v1/ai/generate (with sample data)"
GENERATE_WITH_DATA=$(curl -s \
    -b /home/timl/dev/WP_ContentFlow/tmp/cookies_after_login.txt \
    -H "X-WP-Nonce: $NONCE" \
    -H "Content-Type: application/json" \
    -X POST \
    -d '{"prompt":"Write a short paragraph about WordPress","workflow_id":1,"post_id":1}' \
    "http://localhost:8080/wp-json/wp-content-flow/v1/ai/generate")

echo "Response: $GENERATE_WITH_DATA"

# 5. Test improve endpoint
echo -e "\n5. Testing /wp-json/wp-content-flow/v1/ai/improve"
IMPROVE_RESPONSE=$(curl -s \
    -b /home/timl/dev/WP_ContentFlow/tmp/cookies_after_login.txt \
    -H "X-WP-Nonce: $NONCE" \
    -H "Content-Type: application/json" \
    -X POST \
    -d '{"content":"This is some text to improve","improvement_type":"grammar","workflow_id":1}' \
    "http://localhost:8080/wp-json/wp-content-flow/v1/ai/improve")

echo "Response: $IMPROVE_RESPONSE"

echo -e "\n✅ API testing completed"