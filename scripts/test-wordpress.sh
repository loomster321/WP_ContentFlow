#!/bin/bash

# WordPress AI Content Flow Plugin - Testing Script
set -e

echo "🧪 Running WordPress AI Content Flow Plugin tests..."

# Check if containers are running
if ! docker-compose ps | grep -q "Up"; then
    echo "❌ Docker containers are not running. Start them with: docker-compose up -d"
    exit 1
fi

# Run PHPUnit tests
if [ -d "./wp-content-flow/tests" ]; then
    echo "🔬 Running PHP unit tests..."
    docker-compose exec wordpress php -d memory_limit=256M ./wp-content/plugins/wp-content-flow/vendor/bin/phpunit \
        --configuration ./wp-content/plugins/wp-content-flow/phpunit.xml \
        --testsuite unit
    
    echo "🔬 Running PHP integration tests..."
    docker-compose exec wordpress php -d memory_limit=256M ./wp-content/plugins/wp-content-flow/vendor/bin/phpunit \
        --configuration ./wp-content/plugins/wp-content-flow/phpunit.xml \
        --testsuite integration
    
    echo "🔬 Running PHP contract tests..."
    docker-compose exec wordpress php -d memory_limit=256M ./wp-content/plugins/wp-content-flow/vendor/bin/phpunit \
        --configuration ./wp-content/plugins/wp-content-flow/phpunit.xml \
        --testsuite contract
fi

# Run JavaScript tests
if [ -f "./wp-content-flow/package.json" ]; then
    echo "🔬 Running JavaScript tests..."
    cd wp-content-flow
    npm test
    cd ..
fi

# Run WordPress plugin validation
echo "✅ Running WordPress plugin validation..."
docker-compose exec wp-cli wp plugin verify-checksums wp-content-flow --allow-root || echo "⚠️  Plugin checksum verification skipped (development plugin)"

# Check plugin status
echo "📊 Plugin status:"
docker-compose exec wp-cli wp plugin status wp-content-flow --allow-root

# Run basic functionality tests
echo "🔍 Testing basic plugin functionality..."
docker-compose exec wp-cli wp eval "if (class_exists('WP_Content_Flow')) { echo 'Plugin main class loaded successfully\n'; } else { echo 'Plugin main class not found\n'; }" --allow-root

# Test REST API endpoints
echo "🌐 Testing REST API endpoints..."
curl -s -o /dev/null -w "Workflows endpoint: %{http_code}\n" http://localhost:8080/wp-json/wp-content-flow/v1/workflows || echo "⚠️  REST API not yet implemented"

echo ""
echo "✅ WordPress testing complete!"