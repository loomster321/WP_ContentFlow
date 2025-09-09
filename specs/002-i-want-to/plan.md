# Implementation Plan: WordPress AI Content Workflow Plugin

**Branch**: `002-i-want-to` | **Date**: 2025-09-08 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/home/timl/dev/WP_ContentFlow/specs/002-i-want-to/spec.md`

## Execution Flow (/plan command scope)
```
1. Load feature spec from Input path
   → If not found: ERROR "No feature spec at {path}"
2. Fill Technical Context (scan for NEEDS CLARIFICATION)
   → Detect Project Type from context (web=frontend+backend, mobile=app+api)
   → Set Structure Decision based on project type
3. Evaluate Constitution Check section below
   → If violations exist: Document in Complexity Tracking
   → If no justification possible: ERROR "Simplify approach first"
   → Update Progress Tracking: Initial Constitution Check
4. Execute Phase 0 → research.md
   → If NEEDS CLARIFICATION remain: ERROR "Resolve unknowns"
5. Execute Phase 1 → contracts, data-model.md, quickstart.md, agent-specific template file (e.g., `CLAUDE.md` for Claude Code, `.github/copilot-instructions.md` for GitHub Copilot, or `GEMINI.md` for Gemini CLI).
6. Re-evaluate Constitution Check section
   → If new violations: Refactor design, return to Phase 1
   → Update Progress Tracking: Post-Design Constitution Check
7. Plan Phase 2 → Describe task generation approach (DO NOT create tasks.md)
8. STOP - Ready for /tasks command
```

**IMPORTANT**: The /plan command STOPS at step 7. Phases 2-4 are executed by other commands:
- Phase 2: /tasks command creates tasks.md
- Phase 3-4: Implementation execution (manual or via tools)

## Summary
WordPress plugin that integrates AI capabilities directly into the block editor for content generation, editing suggestions, and workflow automation. Users can generate content from prompts, get inline AI editing suggestions, and automate content review processes without leaving the WordPress editor. Based on the user's question, the system will use WordPress in a Docker environment for local testing and development.

## Technical Context
**Language/Version**: PHP 8.1+ (WordPress requirement), JavaScript ES6+ (block editor)  
**Primary Dependencies**: WordPress 6.0+, React/Gutenberg blocks, REST API  
**Storage**: WordPress database (wp_posts, custom tables), AI service APIs  
**Testing**: PHPUnit (WordPress), Jest (JavaScript), WordPress test suite  
**Target Platform**: WordPress multisite compatible, web browsers, Docker development environment for local testing  
**Project Type**: web - WordPress plugin architecture  
**Performance Goals**: <2s AI response time, minimal editor impact  
**Constraints**: WordPress coding standards, plugin review guidelines, AI token limits  
**Scale/Scope**: Multi-user WordPress sites, content teams of 5-50 users

## Constitution Check
*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

**Simplicity**:
- Projects: 3 (plugin-core, ai-services, workflow-engine)
- Using framework directly? (WordPress hooks/filters, Gutenberg blocks)
- Single data model? (unified content workflow entities)
- Avoiding patterns? (no unnecessary abstractions over WP APIs)

**Architecture**:
- EVERY feature as library? (core AI library, workflow library, UI library)
- Libraries listed: [wp-ai-core + AI operations, wp-workflow + automation, wp-ui-blocks + editor integration]
- CLI per library: [wp-cli commands with --help/--version/--format]
- Library docs: llms.txt format planned? Yes

**Testing (NON-NEGOTIABLE)**:
- RED-GREEN-Refactor cycle enforced? Yes
- Git commits show tests before implementation? Required
- Order: Contract→Integration→E2E→Unit strictly followed? Yes
- Real dependencies used? (actual WordPress, AI APIs, not mocks)
- Integration tests for: new libraries, contract changes, shared schemas? Yes
- FORBIDDEN: Implementation before test, skipping RED phase

**Observability**:
- Structured logging included? WordPress debug.log + custom logging
- Frontend logs → backend? (unified stream via REST API)
- Error context sufficient? AI errors, workflow states, user actions

**Versioning**:
- Version number assigned? 1.0.0
- BUILD increments on every change? Yes
- Breaking changes handled? Migration hooks, backward compatibility

## Project Structure

### Documentation (this feature)
```
specs/002-i-want-to/
├── plan.md              # This file (/plan command output)
├── research.md          # Phase 0 output (/plan command)
├── data-model.md        # Phase 1 output (/plan command)
├── quickstart.md        # Phase 1 output (/plan command)
├── contracts/           # Phase 1 output (/plan command)
└── tasks.md             # Phase 2 output (/tasks command - NOT created by /plan)
```

### Source Code (repository root)
```
# WordPress Plugin Structure with Docker Development Environment
wp-content-flow/
├── wp-content-flow.php           # Main plugin file
├── includes/
│   ├── class-ai-core.php         # Core AI functionality library
│   ├── class-workflow-engine.php # Workflow automation library  
│   ├── class-content-manager.php # Content operations library
│   └── admin/                    # WordPress admin integration
├── assets/
│   ├── js/                       # Block editor JavaScript
│   └── css/                      # Admin and editor styles
├── blocks/                       # Custom Gutenberg blocks
├── templates/                    # Workflow templates
└── tests/
    ├── contract/                 # API contract tests
    ├── integration/              # WordPress integration tests  
    └── unit/                     # PHPUnit and Jest unit tests

# Docker Development Environment
docker-compose.yml               # WordPress + MySQL setup
.env                            # Environment configuration
scripts/
├── setup-dev.sh               # Development environment setup
└── test-wordpress.sh          # WordPress-specific testing
```

**Structure Decision**: WordPress plugin architecture with Docker development environment

## Phase 0: Outline & Research
1. **Extract unknowns from Technical Context** above:
   - AI service integration patterns for WordPress
   - Gutenberg block development best practices
   - WordPress database schema for workflow data
   - AI content processing performance optimization
   - WordPress plugin security requirements

2. **Generate and dispatch research agents**:
   ```
   For each unknown in Technical Context:
     Task: "Research WordPress AI integration patterns"
     Task: "Research Gutenberg block development for AI features"  
     Task: "Research WordPress database design for content workflows"
     Task: "Research AI API performance optimization in WordPress"
     Task: "Research WordPress plugin security best practices"
   ```

3. **Consolidate findings** in `research.md` using format:
   - Decision: [what was chosen]
   - Rationale: [why chosen]
   - Alternatives considered: [what else evaluated]

**Output**: research.md with all NEEDS CLARIFICATION resolved

## Phase 1: Design & Contracts
*Prerequisites: research.md complete*

1. **Extract entities from feature spec** → `data-model.md`:
   - Content Workflow, AI Suggestion, Workflow Template, Content History, User Preferences
   - WordPress database table design
   - State transitions for workflow automation

2. **Generate API contracts** from functional requirements:
   - REST API endpoints for AI operations
   - Block editor JavaScript APIs
   - WordPress hook/filter contracts
   - Output OpenAPI schemas to `/contracts/`

3. **Generate contract tests** from contracts:
   - WordPress REST API tests
   - Block registration tests
   - Hook/filter integration tests
   - Tests must fail (no implementation yet)

4. **Extract test scenarios** from user stories:
   - Content creation with AI assistance scenarios
   - Workflow automation test scenarios  
   - Multi-user collaboration test cases

5. **Update agent file incrementally** (O(1) operation):
   - Run `/scripts/update-agent-context.sh claude` for Claude Code
   - Add WordPress development context
   - Add AI integration patterns
   - Update recent changes (keep last 3)
   - Keep under 150 lines for token efficiency

**Output**: data-model.md, /contracts/*, failing tests, quickstart.md, CLAUDE.md

## Phase 2: Task Planning Approach
*This section describes what the /tasks command will do - DO NOT execute during /plan*

**Task Generation Strategy**:
- Load `/templates/tasks-template.md` as base
- Generate tasks from Phase 1 design docs
- Each API contract → contract test task [P]
- Each entity → WordPress table/model task [P]
- Each block → block development task
- Each user story → integration test task

**Ordering Strategy**:
- TDD order: Tests before implementation
- WordPress dependency order: Database → Models → API → Blocks → UI
- Mark [P] for parallel execution (independent components)

**Estimated Output**: 30-35 numbered, ordered tasks in tasks.md

**IMPORTANT**: This phase is executed by the /tasks command, NOT by /plan

## Phase 3+: Future Implementation
*These phases are beyond the scope of the /plan command*

**Phase 3**: Task execution (/tasks command creates tasks.md)  
**Phase 4**: Implementation (execute tasks.md following constitutional principles)  
**Phase 5**: Validation (run tests, execute quickstart.md, performance validation)

## Complexity Tracking
*No constitutional violations identified - proceeding with standard approach*


## Progress Tracking
*This checklist is updated during execution flow*

**Phase Status**:
- [x] Phase 0: Research complete (/plan command)
- [x] Phase 1: Design complete (/plan command)
- [x] Phase 2: Task planning complete (/plan command - describe approach only)
- [ ] Phase 3: Tasks generated (/tasks command)
- [ ] Phase 4: Implementation complete
- [ ] Phase 5: Validation passed

**Gate Status**:
- [x] Initial Constitution Check: PASS
- [x] Post-Design Constitution Check: PASS
- [ ] All NEEDS CLARIFICATION resolved
- [ ] Complexity deviations documented

---
*Based on Constitution v2.1.1 - See `/memory/constitution.md`*