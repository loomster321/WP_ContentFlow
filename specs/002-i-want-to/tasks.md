# Tasks: WordPress AI Content Workflow Plugin

**Input**: Design documents from `/specs/002-i-want-to/`
**Prerequisites**: plan.md (required), research.md, data-model.md, contracts/

## Execution Flow (main)
```
1. Load plan.md from feature directory
   → Extract: WordPress plugin architecture, Docker development environment, AI provider integration
2. Load design documents:
   → data-model.md: Extract entities → database table creation tasks
   → contracts/: Each file → contract test task
   → research.md: Extract decisions → Docker setup tasks
3. Generate tasks by category:
   → Setup: Docker environment, WordPress plugin structure, dependencies
   → Tests: contract tests, integration tests (WordPress-specific)
   → Core: database models, AI services, WordPress hooks
   → Integration: REST API, Gutenberg blocks, admin interface
   → Polish: unit tests, performance optimization, documentation
4. Apply task rules:
   → Different files = mark [P] for parallel
   → Same file = sequential (no [P])
   → Tests before implementation (TDD)
5. Number tasks sequentially (T001, T002...)
6. Generate dependency graph
7. Create parallel execution examples
```

## Format: `[ID] [P?] Description`
- **[P]**: Can run in parallel (different files, no dependencies)
- Include exact file paths in descriptions

## Path Conventions
WordPress plugin structure with Docker development environment:
- Plugin root: `wp-content-flow/`
- PHP classes: `includes/`
- JavaScript: `assets/js/`
- Tests: `tests/contract/`, `tests/integration/`, `tests/unit/`

## Phase 3.1: Setup

- [ ] T001 Create Docker development environment with docker-compose.yml (WordPress + MySQL + plugin volume mount)
- [ ] T002 [P] Initialize WordPress plugin structure in wp-content-flow/ with main plugin file
- [ ] T003 [P] Configure development dependencies (Composer, npm, WordPress test suite)
- [ ] T004 [P] Set up linting and formatting tools (PHPCS WordPress standards, ESLint)

## Phase 3.2: Tests First (TDD) ⚠️ MUST COMPLETE BEFORE 3.3
**CRITICAL: These tests MUST be written and MUST FAIL before ANY implementation**

### Contract Tests (API Endpoints)
- [ ] T005 [P] Contract test GET /wp-json/wp-content-flow/v1/workflows in tests/contract/test_workflows_get.php
- [ ] T006 [P] Contract test POST /wp-json/wp-content-flow/v1/workflows in tests/contract/test_workflows_post.php
- [ ] T007 [P] Contract test PUT /wp-json/wp-content-flow/v1/workflows/{id} in tests/contract/test_workflows_put.php
- [ ] T008 [P] Contract test DELETE /wp-json/wp-content-flow/v1/workflows/{id} in tests/contract/test_workflows_delete.php
- [ ] T009 [P] Contract test POST /wp-json/wp-content-flow/v1/ai/generate in tests/contract/test_ai_generate.php
- [ ] T010 [P] Contract test POST /wp-json/wp-content-flow/v1/ai/improve in tests/contract/test_ai_improve.php
- [ ] T011 [P] Contract test POST /wp-json/wp-content-flow/v1/suggestions/{id}/accept in tests/contract/test_suggestions_accept.php
- [ ] T012 [P] Contract test GET /wp-json/wp-content-flow/v1/posts/{post_id}/history in tests/contract/test_content_history.php

### Block Editor Contract Tests
- [ ] T013 [P] Contract test AI Text Generator block registration in tests/contract/test_ai_text_block.js
- [ ] T014 [P] Contract test Content Improvement toolbar in tests/contract/test_improvement_toolbar.js
- [ ] T015 [P] Contract test Workflow Settings panel in tests/contract/test_workflow_panel.js

### WordPress Hook Contract Tests
- [ ] T016 [P] Contract test wp_content_flow_content_generated action in tests/contract/test_wordpress_hooks.php
- [ ] T017 [P] Contract test wp_content_flow_ai_providers filter in tests/contract/test_wordpress_filters.php

### Integration Tests
- [ ] T018 [P] Integration test complete content generation workflow in tests/integration/test_content_generation.php
- [ ] T019 [P] Integration test content improvement workflow in tests/integration/test_content_improvement.php
- [ ] T020 [P] Integration test multi-user workflow collaboration in tests/integration/test_multiuser_workflow.php
- [ ] T021 [P] Integration test AI provider switching in tests/integration/test_ai_provider_switching.php

## Phase 3.3: Core Implementation (ONLY after tests are failing)

### Database Models
- [ ] T022 [P] Create wp_ai_workflows table schema in includes/database/schema-workflows.php
- [ ] T023 [P] Create wp_ai_suggestions table schema in includes/database/schema-suggestions.php
- [ ] T024 [P] Create wp_workflow_templates table schema in includes/database/schema-templates.php
- [ ] T025 [P] Create wp_ai_content_history table schema in includes/database/schema-history.php

### WordPress Model Classes
- [ ] T026 [P] Workflow model class in includes/models/class-workflow.php
- [ ] T027 [P] AI_Suggestion model class in includes/models/class-ai-suggestion.php
- [ ] T028 [P] Workflow_Template model class in includes/models/class-workflow-template.php
- [ ] T029 [P] Content_History model class in includes/models/class-content-history.php

### AI Service Libraries
- [ ] T030 [P] AI_Core service class in includes/class-ai-core.php
- [ ] T031 [P] OpenAI provider integration in includes/providers/class-openai-provider.php
- [ ] T032 [P] Anthropic Claude provider integration in includes/providers/class-anthropic-provider.php
- [ ] T033 [P] Google AI provider integration in includes/providers/class-google-provider.php

### WordPress REST API Endpoints
- [ ] T034 Workflows REST controller in includes/api/class-workflows-controller.php
- [ ] T035 AI operations REST controller in includes/api/class-ai-controller.php
- [ ] T036 Suggestions REST controller in includes/api/class-suggestions-controller.php
- [ ] T037 Content history REST controller in includes/api/class-history-controller.php

### Gutenberg Block Components
- [ ] T038 [P] AI Text Generator block in blocks/ai-text-generator/index.js
- [ ] T039 [P] Content Improvement toolbar in assets/js/improvement-toolbar.js
- [ ] T040 [P] Workflow Settings panel in assets/js/workflow-settings.js
- [ ] T041 AI block editor styles in assets/css/editor-blocks.css

## Phase 3.4: WordPress Integration

### Plugin Activation & Setup
- [ ] T042 Plugin activation hook with database table creation in wp-content-flow.php
- [ ] T043 WordPress admin menu integration in includes/admin/class-admin-menu.php
- [ ] T044 Settings page for API keys and configuration in includes/admin/class-settings-page.php
- [ ] T045 User capabilities and role management in includes/class-user-capabilities.php

### WordPress Hooks & Filters
- [ ] T046 Content generation action hooks in includes/class-content-hooks.php
- [ ] T047 AI provider filter system in includes/class-provider-filters.php
- [ ] T048 Post save automation triggers in includes/class-post-hooks.php

### Security & Validation
- [ ] T049 WordPress nonce verification for all API endpoints
- [ ] T050 User permission validation (edit_posts capability checks)
- [ ] T051 API key encryption and secure storage in WordPress options
- [ ] T052 Input sanitization and SQL injection prevention

## Phase 3.5: Polish & Performance

### Unit Tests
- [ ] T053 [P] Unit tests for AI_Core service in tests/unit/test-ai-core.php
- [ ] T054 [P] Unit tests for Workflow model in tests/unit/test-workflow.php
- [ ] T055 [P] Unit tests for content validation in tests/unit/test-content-validation.php
- [ ] T056 [P] JavaScript unit tests for blocks in tests/unit/blocks.test.js

### Performance & Caching
- [ ] T057 [P] AI response caching system in includes/class-ai-cache.php
- [ ] T058 [P] Rate limiting for AI API calls in includes/class-rate-limiter.php
- [ ] T059 WordPress object cache integration for workflow data
- [ ] T060 Database query optimization and indexing validation

### Documentation & Deployment
- [ ] T061 [P] Update WordPress plugin readme.txt with installation instructions
- [ ] T062 [P] WordPress plugin header documentation and version management
- [ ] T063 [P] Execute quickstart.md scenarios for user acceptance testing
- [ ] T064 WordPress plugin repository preparation (assets, screenshots)

## Dependencies

### Phase Dependencies
- Tests (T005-T021) before implementation (T022-T052)
- Database schemas (T022-T025) before models (T026-T029)
- Models before API controllers (T034-T037)
- API controllers before blocks (T038-T041)
- Core implementation before WordPress integration (T042-T052)
- Implementation before polish (T053-T064)

### Specific Task Dependencies
- T022 blocks T026, T034
- T023 blocks T027, T035
- T026-T029 block T034-T037
- T030-T033 block T035
- T034-T037 block T038-T041
- T042 blocks all integration tasks (T043-T048)

## Parallel Example
```
# Launch T005-T012 together (Contract tests for REST API):
Task: "Contract test GET /wp-json/wp-content-flow/v1/workflows in tests/contract/test_workflows_get.php"
Task: "Contract test POST /wp-json/wp-content-flow/v1/workflows in tests/contract/test_workflows_post.php"
Task: "Contract test POST /wp-json/wp-content-flow/v1/ai/generate in tests/contract/test_ai_generate.php"
Task: "Contract test POST /wp-json/wp-content-flow/v1/ai/improve in tests/contract/test_ai_improve.php"

# Launch T022-T025 together (Database schema creation):
Task: "Create wp_ai_workflows table schema in includes/database/schema-workflows.php"
Task: "Create wp_ai_suggestions table schema in includes/database/schema-suggestions.php"
Task: "Create wp_workflow_templates table schema in includes/database/schema-templates.php"
Task: "Create wp_ai_content_history table schema in includes/database/schema-history.php"
```

## Notes
- [P] tasks = different files, no dependencies
- All tests must fail before implementing corresponding functionality
- Follow WordPress coding standards and plugin review guidelines
- Commit after each task completion
- Docker environment enables rapid WordPress testing and development
- AI provider API keys must be encrypted in WordPress database

## Task Generation Rules
*Applied during main() execution*

1. **From REST API Contracts**:
   - Each endpoint → contract test task [P]
   - Each controller → implementation task
   
2. **From Data Model**:
   - Each entity → database schema task [P]
   - Each entity → WordPress model class task [P]
   
3. **From Block Editor Contracts**:
   - Each block → contract test task [P]
   - Each block → implementation task [P]

4. **From WordPress Integration**:
   - Plugin activation → database setup task
   - Admin interface → settings page tasks
   - Security → capability and nonce tasks

5. **Ordering**:
   - Setup → Tests → Database → Models → APIs → Blocks → Integration → Polish
   - TDD: Contract tests before any implementation
   - WordPress dependencies: Database before models before controllers

## Validation Checklist
*GATE: Checked by main() before returning*

- [x] All REST API contracts have corresponding tests
- [x] All database entities have schema and model tasks
- [x] All Gutenberg blocks have contract tests
- [x] All tests come before implementation
- [x] Parallel tasks truly independent (different files)
- [x] Each task specifies exact file path
- [x] No task modifies same file as another [P] task
- [x] WordPress-specific requirements included (activation hooks, capabilities, nonces)
- [x] Docker development environment setup included
- [x] TDD cycle enforced with failing tests before implementation