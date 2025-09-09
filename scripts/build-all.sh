#!/usr/bin/env bash
# Build all packages in the mono-repo

set -e

echo "=== Building WP Content Flow System ==="

# Check if npm is available
if ! command -v npm &> /dev/null; then
    echo "❌ npm is required but not installed. Please install Node.js first."
    exit 1
fi

# Build shared types first (required by other packages)
echo "📦 Building shared types..."
cd packages/shared-types
npm run build
cd ../..

# Build cloud API
echo "🚀 Building cloud API..."
cd packages/cloud-api
npm run build
cd ../..

# Build WordPress plugin assets
echo "🔧 Building WordPress plugin..."
cd packages/wordpress-plugin
npm run build
cd ../..

# Build dashboard
echo "🎨 Building dashboard..."
cd packages/dashboard
npm run build
cd ../..

echo "✅ All packages built successfully!"
echo ""
echo "📚 Next steps:"
echo "   1. Run 'npm run docker:build' to build Docker containers"
echo "   2. Run 'npm run docker:up' to start the development environment"
echo "   3. Configure API keys in .env files"