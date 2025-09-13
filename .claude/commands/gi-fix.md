# GitHub Issue Fix (GI-Fix) Command - Comprehensive Resolution System

## Overview
The `gi-fix` command is an intelligent, end-to-end issue resolution system that automatically reproduces, troubleshoots, plans, implements, and validates fixes for GitHub issues through iterative problem-solving with specialized WordPress subagents.

## Command Syntax
```bash
/gi-fix <issue-number> [--max-iterations=3] [--skip-reproduction] [--force-plan]
```

## Core Resolution Loop

```
┌─────────────────────────────────────────────────────────┐
│  1. REPRODUCE → 2. TROUBLESHOOT → 3. PLAN → 4. EXECUTE  │
│                           ↑                      ↓       │
│                    6. ITERATE ← 5. REGRESSION TEST       │
└─────────────────────────────────────────────────────────┘
```

## Prerequisites
- WordPress Docker environment running (localhost:8080)
- GitHub CLI (`gh`) configured
- Playwright installed for testing
- Specialized subagents available in `.claude/agents/`

## Phase 1: Issue Analysis & Intelligence Gathering

### Automatic Issue Classification
```javascript
const issueType = detectIssueType(issue); // bug|feature|performance|security
const component = identifyComponent(issue); // frontend|backend|database|api
const severity = assessSeverity(issue); // critical|high|medium|low
```

### Comment History Analysis
- Fetches ALL comments to understand context
- Identifies previous fix attempts and outcomes
- Extracts failure reasons from past attempts
- Detects patterns: "fix failed", "500 error still", "issue persists"

### Decision Logic
**HALT if:**
- ≥2 previous failed attempts without new information
- Issue marked `need-human` within 24 hours
- Conflicting feedback (human says broken after "fix")

**PROCEED if:**
- First attempt OR new plan available
- Explicit retry request from human
- Environment/code significantly changed

## Phase 2: Reproduction & Verification

### Subagent Selection for Reproduction
| Issue Type | Primary Subagent | Approach |
|------------|-----------------|----------|
| UI/Frontend bugs | `wordpress-playwright-expert` | Automated browser testing |
| Backend/PHP errors | `wordpress-troubleshooter` | Error log analysis + debugging |
| Performance issues | `wordpress-performance-tester` | Load testing + profiling |
| API/Integration | `ai-validation-expert` | Endpoint testing + validation |

### Reproduction Requirements
```bash
# Must capture:
1. Exact error message/behavior
2. Screenshot/video evidence  
3. Console/server logs
4. Network requests (if applicable)
```

## Phase 3: Intelligent Troubleshooting

### Root Cause Analysis with `wordpress-troubleshooter`
```bash
# Use Context7 MCP integration for:
- PHP error analysis and stack traces
- Database query debugging and optimization
- Plugin conflict detection
- Memory/resource profiling
- JavaScript console error analysis
```

### Troubleshooting Decision Tree
```python
if "500" in error:
    check_php_errors()
    verify_database_tables()
    test_with_plugin_disabled()
elif "block validation" in error:
    analyze_block_attributes()
    check_deprecations()
    verify_save_function()
elif "performance" in error:
    profile_database_queries()
    check_cache_implementation()
    analyze_resource_usage()
```

## Phase 4: Smart Fix Planning

### Automatic Plan Generation
If no approved plan exists, generate one based on root cause:

| Root Cause | Fix Strategy | Primary Subagent |
|------------|-------------|------------------|
| Block validation | Update save/edit functions | `wordpress-developer-expert` |
| Database issue | Optimize queries/schema | `wordpress-troubleshooter` |
| API failure | Fix endpoints/handlers | `ai-validation-expert` |
| Performance | Implement caching | `wordpress-performance-tester` |
| UI/UX bug | Update React components | `wordpress-playwright-expert` |

### Plan Validation
- ✅ Addresses root cause (not symptoms)
- ✅ Includes regression test strategy
- ✅ Considers backward compatibility
- ✅ Has rollback procedure

## Phase 5: Fix Implementation

### Implementation Framework
```javascript
async function implementFix(plan, issueType) {
  const subagent = selectSubagent(issueType);
  
  try {
    await backupCurrentState();
    await subagent.executeFixPlan(plan);
    await rebuildAssets();
    await validateChanges();
    return { success: true };
  } catch (error) {
    await rollbackChanges();
    return { success: false, error };
  }
}
```

### Subagent Task Distribution
- **`wordpress-developer-expert`**: Code changes, architecture updates
- **`wordpress-troubleshooter`**: Debug complex issues, fix configurations
- **`wordpress-playwright-expert`**: UI fixes, frontend validation
- **`wordpress-performance-tester`**: Optimization, caching
- **`ai-validation-expert`**: API fixes, provider integration

## Phase 6: Regression Testing (MANDATORY)

### Test Strategy with `wordpress-playwright-expert`
```javascript
// MUST test exact user scenario from issue
const regressionTest = {
  beforeFix: {
    expected: "FAIL",
    error: "Specific error from issue",
    screenshot: "issue-X-before.png"
  },
  afterFix: {
    expected: "PASS",
    error: "None",
    screenshot: "issue-X-after.png"
  }
};
```

### Verification Checklist
**ALL must be true before marking `human-qa`:**
- ✅ Test reproduces exact issue
- ✅ Test FAILS before fix (issue confirmed)
- ✅ Test PASSES after fix (issue resolved)
- ✅ Screenshots captured as evidence
- ✅ No new errors introduced

**Fallback:** If Playwright fails, use:
```bash
node .claude/commands/run-regression-test.js <issue-number>
```

## Phase 7: Iteration & Continuous Learning

### Iteration Strategy (Max 3 attempts)
```python
class IterativeResolver:
    def next_approach(self, last_failure):
        if "same error" in last_failure:
            return try_different_component()
        elif "new error" in last_failure:
            return address_side_effect()
        elif "partial fix" in last_failure:
            return enhance_current_fix()
```

### Progressive Fix Strategies
1. **Iteration 1**: Direct fix per initial plan
2. **Iteration 2**: Alternative approach with different subagent
3. **Iteration 3**: Defensive implementation with fallbacks

## Result Documentation

### Success Documentation (✅ Fix Verified)
```markdown
## ✅ Issue #[number] Successfully Resolved

### Root Cause
[Technical explanation]

### Solution Applied
[Changes with file:line references]

### Verification
- Regression test: PASSED ✅
- Before/After screenshots: [paths]
- Performance impact: [metrics]

### Test Instructions for Human QA
1. [Exact steps to verify]
2. [Expected outcomes]
```

### Failure Documentation (❌ Manual Intervention Required)
```markdown
## ⚠️ Issue #[number] - Requires Manual Investigation

### [X] Iterations Attempted
1. **Approach A**: [what/why/result]
2. **Approach B**: [what/why/result]

### Blocker Identified
[Specific technical barrier]

### Diagnostic Data
- Error logs: [findings]
- Subagents used: [list]
- Environment state: [details]

### Recommended Next Steps
1. [Specific action for human]
2. [Debugging technique]
```

## Label Management

| Label | When Applied | Meaning |
|-------|-------------|---------|  
| `fixing` | During execution | Temporary, auto-removed |
| `human-qa` | Fix verified by tests | Ready for human validation |
| `need-human` | Automated fix failed | Requires manual intervention |
| `cannot-reproduce` | Issue not reproducible | May be environment-specific |
| `blocked` | Environment issues | Cannot test properly |

## Subagent Utilization Matrix

| Task | Primary Subagent | When to Use | Fallback |
|------|-----------------|-------------|----------|
| Reproduce UI issue | `wordpress-playwright-expert` | Frontend bugs, visual issues | `troubleshooter` |
| Debug PHP errors | `wordpress-troubleshooter` | 500 errors, fatals, configs | `developer-expert` |
| Fix block code | `wordpress-developer-expert` | Gutenberg, REST API, PHP | `troubleshooter` |
| Optimize performance | `wordpress-performance-tester` | Slow queries, caching | `troubleshooter` |
| Test AI providers | `ai-validation-expert` | API integration, providers | `troubleshooter` |
| Create test plan | `wordpress-test-planner` | Complex test scenarios | `playwright-expert` |

## Common Fix Patterns

### Pattern A: Block Validation Fix
```javascript
// Before: dangerouslySetInnerHTML
save: ({ attributes }) => (
  <div dangerouslySetInnerHTML={{ __html: content }} />
)

// After: RichText.Content
save: ({ attributes }) => (
  <RichText.Content tagName="div" value={content} />
)
```

### Pattern B: Database Optimization
```sql
-- Add index for performance
ALTER TABLE wp_content_flow_history 
ADD INDEX idx_post_date (post_id, created_at);
```

### Pattern C: API Error Handling
```php
try {
    $result = $api->call();
} catch (Exception $e) {
    return new WP_Error('api_error', $e->getMessage());
}
```

## Best Practices

### DO:
- ✅ **Always reproduce first** (unless --skip-reproduction)
- ✅ **Use appropriate subagent** for each task
- ✅ **Capture evidence** (screenshots, logs, metrics)
- ✅ **Test exact user scenario** from issue
- ✅ **Document why** approaches failed
- ✅ **Learn from previous attempts** in comments
- ✅ **Verify backward compatibility**

### DON'T:
- ❌ **Skip regression testing** to save time
- ❌ **Apply fixes without understanding** root cause
- ❌ **Mark as fixed without verification**
- ❌ **Continue iterating on environment issues**
- ❌ **Close issues** (only humans can close)
- ❌ **Claim success** without test evidence

## Command Examples

### Basic Usage (Full Cycle)
```bash
/gi-fix 5
# Reproduce → Troubleshoot → Plan → Fix → Test → Iterate if needed
```

### Skip Reproduction (Issue Already Confirmed)
```bash
/gi-fix 5 --skip-reproduction
# Start directly with troubleshooting
```

### Force New Plan (Ignore Existing Plans)
```bash
/gi-fix 5 --force-plan
# Generate fresh plan even if one exists
```

### Extended Iterations (Complex Issues)
```bash
/gi-fix 5 --max-iterations=5
# Allow up to 5 fix attempts
```

## Error Recovery Strategies

### Strategy 1: Progressive Enhancement
```
Iteration 1: Fix syntax/validation errors only
Iteration 2: Add error handling and edge cases
Iteration 3: Optimize for performance and UX
```

### Strategy 2: Component Isolation
```
Iteration 1: Fix backend/PHP logic
Iteration 2: Fix frontend/JavaScript display
Iteration 3: Fix integration between layers
```

### Strategy 3: Dependency Resolution
```
Iteration 1: Fix database schema/queries
Iteration 2: Fix model/API layer
Iteration 3: Fix UI components
```

### When to Stop Iterating
- Environment issue detected (Docker/WordPress broken)
- Same error after 3 different approaches
- Human explicitly requested in comments
- Security vulnerability discovered

## Configuration Options

```yaml
gi-fix:
  max_iterations: 3           # Maximum fix attempts
  timeout_per_phase: 300      # Seconds per phase
  
  reproduction:
    use_playwright: true      # Primary test tool
    capture_screenshots: true # Evidence collection
    retry_count: 2           # Reproduction attempts
  
  troubleshooting:
    use_context7: true       # Advanced debugging
    check_error_logs: true   # PHP/JS error analysis
    test_isolation: true     # Test with plugin disabled
  
  planning:
    auto_generate: true      # Create plan if missing
    use_ai_analysis: true    # Smart plan generation
  
  testing:
    regression_required: true # Cannot skip
    performance_check: true  # Monitor impact
    screenshot_evidence: true # Visual proof
```

## Success Metrics

### Track and Optimize
- **First-attempt success rate**: Target >60%
- **Average iterations to fix**: Target <2.5
- **Regression test coverage**: Target >90%
- **Time to resolution**: Target <15min simple, <45min complex
- **Subagent utilization**: Track which are most effective

### Continuous Improvement
- Learn from failed attempts
- Identify common patterns
- Update fix strategies
- Refine subagent selection

## Integration Points

### GitHub Actions CI/CD
```yaml
on:
  issue_comment:
    types: [created]
jobs:
  auto-fix:
    if: contains(github.event.comment.body, '/gi-fix')
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - run: npm run gi-fix ${{ github.event.issue.number }}
```

### WordPress Environment
- **URL**: http://localhost:8080
- **Admin**: admin / !3cTXkh)9iDHhV5o*N
- **Database**: MySQL on port 3306
- **Plugin Path**: /wp-content/plugins/wp-content-flow/

## Summary

The `gi-fix` command provides comprehensive, automated issue resolution by:

1. **Reproducing** issues with appropriate testing subagents
2. **Troubleshooting** root causes using Context7 and specialized debugging
3. **Planning** fixes automatically if no plan exists
4. **Implementing** solutions with domain-expert subagents
5. **Testing** with mandatory regression validation
6. **Iterating** intelligently based on failure patterns
7. **Documenting** everything for knowledge preservation

By leveraging specialized WordPress subagents at each phase and following an iterative improvement cycle, the system can resolve most issues autonomously while providing clear handoff documentation when human intervention is needed.

---
*Optimized for WordPress development with specialized subagent integration*