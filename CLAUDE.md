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

## Functional Requirements & Living Documentation

**CRITICAL: These are LIVING DOCUMENTS that must be actively maintained throughout development.**

### Living Documentation Philosophy
- **Single Source of Truth**: These documents define the authoritative project requirements and plans
- **Update BEFORE Implementation**: Always update documentation before making code changes
- **Real-time Progress Tracking**: Mark tasks complete immediately as work is finished
- **Continuous Evolution**: Documents grow with the project - add new requirements, decisions, and learnings

### Core Specification Documents

Located in `specs/002-i-want-to/`:

#### Primary Documents (Update Frequently)
- **spec.md** - Functional Requirements (FR-001 through FR-014)
  - ADD new requirements with sequential numbering (FR-015, FR-016, etc.)
  - UPDATE when requirements change or evolve
  - MARK deprecated requirements rather than deleting
  
- **tasks.md** - Implementation Task Breakdown
  - MARK [x] complete immediately when tasks are finished
  - ADD new tasks as discovered during implementation
  - UPDATE task descriptions if scope changes
  - TRACK blockers and dependencies

- **plan.md** - Technical Implementation Plan
  - UPDATE when architecture decisions change
  - DOCUMENT deviations from original plan
  - ADD new technical decisions and rationale

#### Supporting Documents (Update as Needed)
- **data-model.md** - Database Schema & Entity Definitions
  - UPDATE before modifying database structure
  - VERSION schema changes with migration notes
  - DOCUMENT relationships and constraints

- **quickstart.md** - Getting Started Guide
  - UPDATE when setup process changes
  - ADD troubleshooting for common issues
  - MAINTAIN current with actual setup steps

- **research.md** - Technical Research & Decisions
  - ADD new findings and investigations
  - DOCUMENT alternatives considered
  - RECORD decision rationale

- **contracts/** - API Contract Specifications
  - VERSION contracts when breaking changes occur
  - UPDATE before implementing API changes
  - MAINTAIN backward compatibility notes

### Document Maintenance Policy

#### When Adding Features
1. FIRST update spec.md with new FR-XXX requirement
2. UPDATE plan.md if architecture is affected
3. ADD tasks to tasks.md before starting work
4. UPDATE data-model.md for any schema changes

#### During Implementation
1. MARK tasks [x] complete in tasks.md immediately upon completion
2. UPDATE spec.md if requirements need clarification
3. ADD to research.md for technical decisions made
4. DOCUMENT blockers and resolutions

#### When Making Changes
- **Breaking Changes**: Update ALL affected documents first
- **Bug Fixes**: Note in tasks.md if not already tracked
- **Performance Improvements**: Document in research.md
- **Security Updates**: Add to spec.md security requirements

#### Git Commit Practice
- Include document updates in same commit as related code
- Use commit messages like: "Update FR-007 and implement collaborative editing"
- Tag major requirement changes for version tracking

## CRITICAL: Specialized Subagents

**Use the general-purpose subagent via the Task tool, referencing specialized agents in `.claude/agents/` for domain-specific work:**

### Available Specialized Agents

#### Development & Architecture
- **WordPress Developer Expert** (`.claude/agents/wordpress-developer-expert.md`)
- **WordPress Troubleshooter** (`.claude/agents/wordpress-troubleshooter.md`)

#### Testing & Quality Assurance  
- **WordPress Playwright Expert** (`.claude/agents/wordpress-playwright-expert.md`)
- **WordPress Test Planner** (`.claude/agents/wordpress-test-planner.md`)
- **AI Validation Expert** (`.claude/agents/ai-validation-expert.md`)

#### Performance & Optimization
- **WordPress Performance Tester** (`.claude/agents/wordpress-performance-tester.md`)

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

### WordPress Test Planner
- **Specialization**: WordPress plugin test plan development and E2E test strategy
- **Use when**: Creating comprehensive test plans for plugin releases, analyzing test requirements, developing QA frameworks
- **Expertise**: WordPress test planning, E2E strategy, user role testing, plugin lifecycle testing, quality assurance

Example usage:
```
Use Task tool with subagent_type: "wordpress-test-planner" to:
- Create comprehensive test plans for WordPress plugin functionality
- Develop E2E test scenarios for admin interfaces and user workflows  
- Plan multi-user role testing (admin, editor, author, contributor, subscriber)
- Design plugin activation/deactivation test workflows
- Structure test plans for WordPress Playwright Expert execution
```

### WordPress Troubleshooter
- **Specialization**: WordPress development issues and debugging with Context7 MCP integration
- **Use when**: Diagnosing plugin problems, resolving theme conflicts, investigating performance bottlenecks, debugging database queries
- **Expertise**: WordPress core debugging, plugin lifecycle issues, Context7 advanced debugging, error diagnosis

Example usage:
```
Use Task tool with subagent_type: "wordpress-troubleshooter" to:
- Debug WordPress plugin activation errors and fatal errors
- Investigate WordPress admin performance issues
- Resolve Gutenberg block development problems  
- Troubleshoot database query performance with Context7 profiling
- Diagnose plugin conflicts and theme compatibility issues
```

### WordPress Performance Tester
- **Specialization**: WordPress performance optimization and load testing
- **Use when**: Analyzing bottlenecks, testing caching, database optimization, scalability testing
- **Expertise**: Database queries, Redis caching, memory monitoring, resource optimization

Example usage:
```
Use Task tool with subagent_type: "wordpress-performance-tester" to:
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
├── specs/                          # LIVING DOCUMENTATION - Must be kept current!
│   └── 002-i-want-to/              # Feature specifications and plans
│       ├── spec.md                 # Functional requirements (FR-001 to FR-014+)
│       ├── plan.md                 # Technical implementation plan
│       ├── tasks.md                # Task breakdown (mark complete as you work!)
│       ├── data-model.md           # Database schema definitions
│       ├── quickstart.md           # Getting started guide
│       ├── research.md             # Technical decisions and findings
│       └── contracts/              # API contract specifications
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

## Communication Style Requirements

### CRITICAL: Be Direct and Factual - No Sugarcoating

When reporting on development status, test results, or any technical work:

1. **State failures and problems FIRST and PROMINENTLY**
   - "The settings save is broken - API keys disappear immediately"
   - "All 17 E2E tests failed because the plugin has no functionality"
   - "This feature doesn't work" NOT "This feature is implemented"

2. **No success theater or false positives**
   - Don't use ✅ unless something actually works end-to-end for the user
   - Don't say "complete" for files that exist but don't function
   - Don't say "ready for testing" unless it can actually be tested
   - Don't say "running successfully" when tests are failing

3. **Be brutally honest about current state**
   - "Created test files but plugin has no working functionality to test"
   - "Tests execute but all fail because nothing is implemented"
   - "Cannot proceed with testing until critical bugs are fixed"

4. **Clearly separate file creation from functionality**
   - "Created settings file" ≠ "Settings working"
   - "Wrote tests" ≠ "Tests passing"
   - "Added feature code" ≠ "Feature functional"
   - "Tests run" ≠ "Tests succeed"

5. **Lead with blockers and showstoppers**
   - Start responses with what's broken
   - Identify critical issues immediately
   - Don't bury problems in optimistic framing
   - If nothing works, say "Nothing works" first

6. **Use precise language**
   - "Failed" not "didn't succeed"
   - "Broken" not "needs attention"
   - "Doesn't work" not "has issues"
   - "0 of 17 tests passed" not "tests completed"

### Example of GOOD reporting:
"The plugin is non-functional. Settings don't save - API keys disappear on form submission. 0 of 17 E2E tests pass. Cannot do any testing until settings are fixed. Created 37 test files but they test nothing because there's no working code."

### Example of BAD reporting:
"✅ E2E tests running successfully! The comprehensive test suite is ready. You can proceed with manual testing. Some failures are expected as features are still being implemented."

## Authentication and Permission Policy

**CRITICAL: When encountering authentication or permission issues, DO NOT implement workarounds.**

### Required Behavior:
1. **STOP immediately** when encountering permission/authentication blocks
2. **ASK the human** for direction and assistance
3. **WAIT for human resolution** before proceeding

### Common Scenarios:
- `npm install` permission denied → **STOP and ask human**
- Docker container access issues → **STOP and ask human**
- File system permission problems → **STOP and ask human**
- Database connection authentication → **STOP and ask human**
- WordPress admin login issues → **STOP and ask human**

### Why This Policy Exists:
- Human has proper credentials and system access
- Workarounds lead to incomplete testing and poor decisions
- Proper authentication enables optimal solutions
- Prevents false positive assessments and dangerous recommendations

### What NOT to do:
- ❌ Skip testing due to permission issues
- ❌ Create alternative/workaround testing approaches
- ❌ Make production readiness claims without proper testing
- ❌ Assume functionality works based on code review alone

### What TO do:
- ✅ Stop and clearly explain the permission/authentication issue
- ✅ Request human assistance to resolve access problems
- ✅ Wait for proper access before continuing testing
- ✅ Use proper tools and testing approaches once access is restored

## Error Handling

- **Graceful Degradation**: Plugin should work even with AI provider failures
- **User-Friendly Messages**: Clear error messages for different user roles
- **Debug Information**: Detailed logging with Context7 integration
- **Recovery Mechanisms**: Automatic retry and fallback strategies