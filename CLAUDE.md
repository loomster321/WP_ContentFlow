# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with the WordPress AI Content Flow Plugin codebase.

## Project Overview

WordPress AI Content Flow Plugin - An AI-powered content workflow system that integrates multiple AI providers (OpenAI, Anthropic, Google AI) directly into the WordPress Gutenberg block editor for content generation, editing suggestions, and workflow automation.

## Screenshots and Documentation

**ALWAYS check the `tmp/` subfolder first for screenshots, error reports, and project documentation:**
- User-provided screenshots are stored in `/home/timl/dev/WP_ContentFlow/tmp/`
- Check this directory before asking for additional information
- Screenshots may show WordPress admin interfaces, error messages, or setup instructions
- Use these screenshots to understand current project state and user requirements

## CRITICAL: Specialized Subagents

**Use specialized subagents via the Task tool for domain-specific work:**

### WordPress Testing Agent
- **Subagent**: `wordpress-testing-agent`
- **Specialization**: WordPress plugin testing, Gutenberg blocks, admin interfaces
- **Use when**: Running E2E tests, validating WordPress functionality, testing user workflows
- **Access**: WordPress test environment, Playwright configuration, test data setup

Example usage:
```
Use Task tool with subagent_type: "wordpress-testing-agent" to:
- Execute comprehensive E2E test suites for WordPress admin workflows
- Validate Gutenberg block editor integration and AI content generation
- Test user capability enforcement across different WordPress roles
- Verify plugin activation/deactivation and settings management
- Run cross-browser compatibility tests for WordPress themes
```

### AI Validation Agent  
- **Subagent**: `ai-validation-agent`
- **Specialization**: AI provider integration, content validation, safety checks
- **Use when**: Validating AI responses, testing provider switching, content safety
- **Access**: AI provider configurations, content validation rules, safety filters

Example usage:
```
Use Task tool with subagent_type: "ai-validation-agent" to:
- Validate AI provider response formatting and content quality
- Test content safety filters and prompt injection prevention
- Verify AI provider switching and fallback mechanisms
- Check rate limiting and quota management functionality
- Validate content improvement suggestions and approval workflows
```

### Performance Testing Agent
- **Subagent**: `performance-testing-agent` 
- **Specialization**: Performance monitoring, caching, database optimization
- **Use when**: Testing performance, analyzing bottlenecks, validating caching
- **Access**: Performance metrics, caching systems, database queries

Example usage:
```
Use Task tool with subagent_type: "performance-testing-agent" to:
- Analyze WordPress database query performance and optimization
- Test caching systems (Redis, WordPress transients) under load
- Validate rate limiting effectiveness and user experience
- Monitor memory usage during AI content generation
- Test plugin performance impact on WordPress admin and frontend
```

## Architecture Overview

### Core Components
- **WordPress Plugin**: `wp-content-flow/wp-content-flow.php` - Main plugin file
- **AI Core**: `includes/class-ai-core.php` - Multi-provider AI service orchestration
- **REST API**: `includes/api/` - WordPress REST API endpoints for AI operations
- **Gutenberg Blocks**: `blocks/ai-text-generator/` - Block editor integration
- **Admin Interface**: `includes/admin/` - WordPress admin pages and settings

### Key Technologies
- **WordPress 6.0+** with Gutenberg block editor
- **PHP 8.1+** with WordPress coding standards
- **JavaScript ES6+** with React components for blocks
- **Multi-AI Providers**: OpenAI, Anthropic Claude, Google AI
- **Testing**: Playwright for E2E, Jest for unit tests, PHPUnit for WordPress
- **Debugging**: Context7 integration for advanced monitoring
- **Docker**: Development environment with WordPress + MySQL

### Database Schema
- **wp_content_flow_workflows** - AI workflow definitions
- **wp_content_flow_suggestions** - Generated content suggestions  
- **wp_content_flow_history** - Content generation history
- **wp_content_flow_templates** - Workflow templates

## Development Workflow

### Testing Strategy
1. **Contract Tests**: API endpoint validation (`tests/contract/`)
2. **Integration Tests**: End-to-end workflow validation (`tests/integration/`)
3. **E2E Tests**: Full user journey testing with Playwright (`e2e/`)
4. **Unit Tests**: Component-level testing (`tests/unit/`)

### Performance Requirements
- **Page Load**: < 3 seconds for WordPress admin pages
- **AI Generation**: < 30 seconds for content generation
- **API Calls**: < 10 seconds for REST API responses
- **Memory**: < 256MB additional memory usage

### Security Requirements
- **WordPress Nonces**: All AJAX requests must use nonces
- **User Capabilities**: Fine-grained permission checks
- **Input Sanitization**: All user input sanitized and validated
- **API Key Security**: Encrypted storage, no logging of keys

## Common Tasks

### Running Tests
```bash
# E2E tests with Playwright
npm run test:e2e

# Smoke tests (critical workflows)
npm run test:smoke

# Unit tests
npm run test:unit

# WordPress PHPUnit tests
cd wp-content-flow && phpunit
```

### Development Environment
```bash
# Start WordPress Docker environment
npm run docker:up

# WordPress admin: http://localhost:8080/wp-admin
# Username: admin, Password: !3cTXkh)9iDHhV5o*N
# Email: tl.oralucent@gmail.com
# Site Title: WP_Contentflow Test

# Stop environment
npm run docker:down
```

### Code Standards
- **WordPress Coding Standards** for PHP
- **ESLint** for JavaScript
- **WordPress block development** patterns for Gutenberg
- **Context7** debugging integration for development

## File Structure
```
/
├── wp-content-flow/                 # WordPress plugin directory
│   ├── wp-content-flow.php         # Main plugin file
│   ├── includes/                   # PHP classes
│   │   ├── class-ai-core.php       # AI service orchestration
│   │   ├── api/                    # REST API controllers  
│   │   ├── admin/                  # Admin interface
│   │   ├── models/                 # Database models
│   │   └── database/               # Schema definitions
│   ├── blocks/                     # Gutenberg blocks
│   │   └── ai-text-generator/      # AI content generation block
│   ├── assets/                     # Frontend assets
│   │   ├── js/                     # JavaScript files
│   │   └── css/                    # Stylesheets
│   └── tests/                      # PHP tests
├── e2e/                            # Playwright E2E tests
│   ├── admin/                      # WordPress admin tests
│   ├── blocks/                     # Block editor tests
│   ├── workflows/                  # Content workflow tests
│   └── smoke-tests/                # Critical path tests
├── context7.config.js              # Context7 debugging configuration
├── playwright.config.js           # Playwright test configuration
└── docker-compose.yml             # Development environment
```

## Important Notes

- **Always use specialized subagents** for domain-specific work
- **Follow WordPress coding standards** and security best practices
- **Test across multiple user roles** (admin, editor, author, contributor)
- **Validate AI provider switching** and fallback mechanisms
- **Monitor performance impact** on WordPress admin and frontend
- **Use Context7 debugging** for development and troubleshooting

## Error Handling

- **Graceful Degradation**: Plugin should work even with AI provider failures
- **User-Friendly Messages**: Clear error messages for different user roles
- **Debug Information**: Detailed logging with Context7 integration
- **Recovery Mechanisms**: Automatic retry and fallback strategies