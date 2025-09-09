# WordPress AI Content Flow Plugin - Development Makefile

.PHONY: help install build test lint fix clean start stop logs

# Default target
help: ## Show this help message
	@echo "WordPress AI Content Flow Plugin - Development Commands"
	@echo ""
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

# Installation and setup
install: ## Install all dependencies (PHP and JavaScript)
	composer install
	npm install

install-dev: ## Install development dependencies
	composer install --dev
	npm install --include=dev

# Build assets
build: ## Build production assets
	npm run build

build-dev: ## Build development assets
	npm run build:dev

watch: ## Watch for file changes and rebuild
	npm run start

# Testing
test: ## Run all tests (PHP and JavaScript)
	composer run test
	npm test

test-php: ## Run PHP tests only
	composer run test

test-js: ## Run JavaScript tests only
	npm test

test-unit: ## Run unit tests only
	composer run test-unit
	npm test

test-integration: ## Run integration tests only
	composer run test-integration

test-contract: ## Run contract tests only
	composer run test-contract

test-coverage: ## Run tests with coverage report
	composer run test-coverage
	npm run test:coverage

# Code quality
lint: ## Run all linters (PHP and JavaScript)
	composer run lint
	npm run lint

lint-php: ## Run PHP linting only
	composer run phpcs
	composer run phpstan

lint-js: ## Run JavaScript linting only
	npm run lint:js
	npm run lint:css

fix: ## Auto-fix code style issues
	composer run fix
	npm run format

# Docker environment
start: ## Start Docker development environment
	docker-compose up -d
	@echo "Waiting for WordPress to be ready..."
	@sleep 30
	@./scripts/setup-dev.sh

stop: ## Stop Docker development environment
	docker-compose down

restart: ## Restart Docker development environment
	docker-compose restart

logs: ## View Docker logs
	docker-compose logs -f wordpress

logs-db: ## View database logs
	docker-compose logs -f db

shell: ## Access WordPress container shell
	docker-compose exec wordpress bash

wp-cli: ## Access WP-CLI
	docker-compose exec wp-cli wp --allow-root

# Database
db-shell: ## Access MySQL shell
	docker-compose exec db mysql -u wordpress -pwordpress wordpress

db-export: ## Export database
	docker-compose exec db mysqldump -u wordpress -pwordpress wordpress > backup.sql

db-import: ## Import database (requires backup.sql)
	docker-compose exec -T db mysql -u wordpress -pwordpress wordpress < backup.sql

# Plugin management
activate: ## Activate plugin in WordPress
	docker-compose exec wp-cli wp plugin activate wp-content-flow --allow-root

deactivate: ## Deactivate plugin in WordPress
	docker-compose exec wp-cli wp plugin deactivate wp-content-flow --allow-root

plugin-status: ## Check plugin status
	docker-compose exec wp-cli wp plugin status wp-content-flow --allow-root

# Cleanup
clean: ## Clean build artifacts and dependencies
	rm -rf build/
	rm -rf coverage/
	rm -rf node_modules/
	rm -rf vendor/

clean-build: ## Clean build artifacts only
	rm -rf build/
	rm -rf coverage/

# Development utilities
check: ## Run all checks (install, lint, test)
	make install-dev
	make lint
	make test

prepare: ## Prepare for development (full setup)
	make clean
	make install-dev
	make build-dev
	make start

package: ## Create plugin package for distribution
	make clean
	make install --no-dev
	make build
	zip -r wp-content-flow.zip . -x "*.git*" "node_modules/*" "vendor/*/tests/*" "tests/*" "*.json" "*.js" "*.md" "Makefile" "docker-compose.yml" ".env*"

# WordPress specific
flush-cache: ## Flush WordPress cache
	docker-compose exec wp-cli wp cache flush --allow-root

flush-rewrite: ## Flush rewrite rules
	docker-compose exec wp-cli wp rewrite flush --allow-root

create-admin: ## Create admin user (admin/password)
	docker-compose exec wp-cli wp user create admin admin@wpcontentflow.local --role=administrator --user_pass=password --allow-root

# Security
security-check: ## Run security checks
	composer audit
	npm audit

update-deps: ## Update all dependencies
	composer update
	npm update

# Performance
benchmark: ## Run performance benchmarks
	@echo "Running WordPress performance tests..."
	@./scripts/test-wordpress.sh