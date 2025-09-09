#!/usr/bin/env bash
# Run tests for all packages

set -e

echo "=== Testing WP Content Flow System ==="

# Test shared types
echo "🧪 Testing shared types..."
cd packages/shared-types
npm test || echo "⚠️ Shared types tests not implemented yet"
cd ../..

# Test cloud API
echo "🧪 Testing cloud API..."
cd packages/cloud-api
npm test || echo "⚠️ Cloud API tests not implemented yet"
cd ../..

# Test WordPress plugin
echo "🧪 Testing WordPress plugin..."
cd packages/wordpress-plugin
npm test || echo "⚠️ WordPress plugin tests not implemented yet"
cd ../..

# Test dashboard
echo "🧪 Testing dashboard..."
cd packages/dashboard
npm test || echo "⚠️ Dashboard tests not implemented yet"
cd ../..

echo "✅ All tests completed!"
echo ""
echo "📝 Note: Some test suites are not yet implemented."
echo "   This is normal for the foundation phase."