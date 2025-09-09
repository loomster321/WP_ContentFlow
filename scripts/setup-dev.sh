#!/bin/bash

# WordPress AI Content Flow Plugin - Development Environment Setup
set -e

echo "🚀 Setting up WordPress AI Content Flow Plugin development environment..."

# Check if Docker and Docker Compose are installed
if ! command -v docker &> /dev/null; then
    echo "❌ Docker is not installed. Please install Docker first."
    exit 1
fi

if ! command -v docker-compose &> /dev/null; then
    echo "❌ Docker Compose is not installed. Please install Docker Compose first."
    exit 1
fi

# Start Docker services
echo "📦 Starting Docker containers..."
docker-compose up -d

# Wait for WordPress to be ready
echo "⏳ Waiting for WordPress to be ready..."
sleep 30

# Check if WordPress is responding
if curl -f -s http://localhost:8080 > /dev/null; then
    echo "✅ WordPress is ready at http://localhost:8080"
else
    echo "❌ WordPress is not responding. Check container logs with: docker-compose logs wordpress"
    exit 1
fi

# Install WordPress using WP-CLI
echo "🔧 Installing WordPress..."
docker-compose exec wp-cli wp core install \
    --url=http://localhost:8080 \
    --title="WordPress AI Content Flow Development" \
    --admin_user=admin \
    --admin_password=password \
    --admin_email=admin@wpcontentflow.local \
    --allow-root

# Activate plugin if it exists
if [ -d "./wp-content-flow" ]; then
    echo "🔌 Activating WordPress AI Content Flow Plugin..."
    docker-compose exec wp-cli wp plugin activate wp-content-flow --allow-root
fi

# Set up development environment
echo "⚙️  Configuring development environment..."
docker-compose exec wp-cli wp config set WP_DEBUG true --raw --allow-root
docker-compose exec wp-cli wp config set WP_DEBUG_LOG true --raw --allow-root
docker-compose exec wp-cli wp config set WP_DEBUG_DISPLAY false --raw --allow-root

echo ""
echo "🎉 Development environment setup complete!"
echo ""
echo "📍 Access points:"
echo "   WordPress: http://localhost:8080"
echo "   WordPress Admin: http://localhost:8080/wp-admin (admin/password)"
echo "   PhpMyAdmin: http://localhost:8081 (wordpress/wordpress)"
echo ""
echo "🛠️  Development commands:"
echo "   Start: docker-compose up -d"
echo "   Stop: docker-compose down"
echo "   Logs: docker-compose logs -f wordpress"
echo "   WP-CLI: docker-compose exec wp-cli wp --allow-root"
echo ""
echo "📁 Plugin development:"
echo "   Plugin files are live-mounted in ./wp-content-flow/"
echo "   Changes are immediately reflected in WordPress"
echo ""