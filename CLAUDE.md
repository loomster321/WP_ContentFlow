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

**Use the general-purpose subagent via the Task tool, referencing specialized agents in `.claude/agents/` for domain-specific work:**

### Available Specialized Agents
- **WordPress Developer Expert** (`.claude/agents/wordpress-developer-expert.md`)
- **WordPress Playwright Expert** (`.claude/agents/wordpress-playwright-expert.md`)
- **AI Validation Expert** (`.claude/agents/ai-validation-expert.md`)
- **Performance Testing Expert** (`.claude/agents/performance-testing-expert.md`)

### WordPress Developer Expert
- **Specialization**: WordPress plugin and theme development, architecture, and standards
- **Use when**: Implementing WordPress features, following best practices, architecting solutions
- **Expertise**: WordPress core, plugin architecture, Gutenberg blocks, Security, REST API, database design

Example usage:
```
Use Task tool with subagent_type: "general-purpose" and reference wordpress-developer-expert.md to:
- Implement WordPress plugin architecture and core functionality
- Create custom Gutenberg blocks and admin interfaces
- Design database schemas and custom post types following WordPress standards
- Implement WordPress REST API endpoints and security best practices
- Prepare plugins for WordPress.org repository submission
```

### WordPress Playwright Expert
- **Specialization**: WordPress plugin E2E testing using Playwright
- **Use when**: Testing settings forms, admin interfaces, user workflows, debugging WordPress functionality
- **Expertise**: WordPress-specific selectors, authentication, form validation, database persistence

Example usage:
```
Use Task tool with subagent_type: "general-purpose" and reference wordpress-playwright-expert.md to:
- Test WordPress admin settings form save functionality end-to-end
- Debug form submission issues and data persistence
- Validate WordPress admin interface interactions
- Test plugin activation/deactivation workflows
- Create comprehensive E2E test suites for WordPress functionality
```

### AI Validation Expert
- **Specialization**: Multi-AI provider integration and content validation
- **Use when**: Testing AI responses, provider switching, content safety, rate limiting
- **Expertise**: OpenAI/Anthropic/Google AI testing, content quality validation, safety filters

Example usage:
```
Use Task tool with subagent_type: "general-purpose" and reference ai-validation-expert.md to:
- Validate AI provider response formatting and content quality
- Test content safety filters and prompt injection prevention
- Verify AI provider switching and fallback mechanisms
- Check rate limiting and quota management functionality
- Test multi-provider content generation workflows
```

### Performance Testing Expert
- **Specialization**: WordPress performance optimization and load testing
- **Use when**: Analyzing bottlenecks, testing caching, database optimization, scalability testing
- **Expertise**: Database queries, Redis caching, memory monitoring, resource optimization

Example usage:
```
Use Task tool with subagent_type: "general-purpose" and reference performance-testing-expert.md to:
- Analyze WordPress database query performance and optimization
- Test caching systems (Redis, WordPress transients) under load
- Monitor memory usage during AI content generation
- Validate rate limiting effectiveness and user experience
- Implement performance monitoring and alerting systems
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
├── .claude/                        # Claude Code agent definitions
│   └── agents/                     # Specialized subagent expertise
│       ├── wordpress-developer-expert.md    # WordPress development expert
│       ├── wordpress-playwright-expert.md   # WordPress E2E testing expert
│       ├── ai-validation-expert.md          # AI provider validation expert
│       └── performance-testing-expert.md    # Performance optimization expert
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
├── tmp/                            # Screenshots and documentation
├── context7.config.js              # Context7 debugging configuration
├── playwright.config.js           # Playwright test configuration
└── docker-compose.yml             # Development environment
```

## Important Notes

- **Use specialized subagents in .claude/agents/** for domain-specific work via general-purpose Task tool
- **Reference expert knowledge** from wordpress-developer-expert, wordpress-playwright-expert, ai-validation-expert, and performance-testing-expert
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