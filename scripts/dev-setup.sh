#!/usr/bin/env bash
# Development environment setup script

set -e

echo "=== WP Content Flow Development Setup ==="

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "❌ Docker is not installed. Please install Docker first."
    exit 1
fi

if ! command -v docker-compose &> /dev/null && ! command -v docker &> /dev/null; then
    echo "❌ Docker Compose is not installed. Please install Docker Compose first."
    exit 1
fi

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    echo "❌ Node.js is not installed. Please install Node.js 18+ first."
    exit 1
fi

# Copy environment files
echo "📁 Setting up environment files..."
if [ ! -f .env ]; then
    cp .env.example .env
    echo "✅ Created root .env file"
fi

if [ ! -f packages/cloud-api/.env ]; then
    cp packages/cloud-api/.env.example packages/cloud-api/.env
    echo "✅ Created cloud-api .env file"
fi

if [ ! -f packages/dashboard/.env.local ]; then
    cp packages/dashboard/.env.example packages/dashboard/.env.local
    echo "✅ Created dashboard .env.local file"
fi

# Install dependencies
echo "📦 Installing dependencies..."
npm install

# Build shared types
echo "🔨 Building shared types..."
npm run build --workspace=packages/shared-types

# Build Docker images
echo "🐳 Building Docker containers..."
docker-compose build

# Start services
echo "🚀 Starting development environment..."
docker-compose up -d mysql postgres redis

# Wait for databases to be ready
echo "⏳ Waiting for databases to be ready..."
sleep 10

# Run database migrations
echo "🗃️ Setting up databases..."
# This will be implemented when we create the migration scripts

echo "✅ Development environment setup complete!"
echo ""
echo "🌐 Services available at:"
echo "   WordPress: http://localhost:8080"
echo "   Dashboard: http://localhost:3000"
echo "   Cloud API: http://localhost:3001"
echo "   MySQL: localhost:3306"
echo "   PostgreSQL: localhost:5432"
echo "   Redis: localhost:6379"
echo ""
echo "📚 Next steps:"
echo "   1. Update API keys in .env files"
echo "   2. Run 'npm run dev' to start development servers"
echo "   3. Visit http://localhost:8080 to set up WordPress"