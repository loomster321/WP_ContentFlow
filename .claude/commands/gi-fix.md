# GitHub Issue Fix (GI-Fix) Command

## ⚠️ CRITICAL REQUIREMENT: REGRESSION TESTING IS MANDATORY

**NO FIX CAN BE MARKED AS SUCCESSFUL WITHOUT:**
1. Running a test that reproduces the EXACT issue from the bug report
2. Confirming the test FAILS before the fix (proving issue exists)
3. Confirming the test PASSES after the fix (proving issue is resolved)
4. Capturing screenshots as evidence of the fix working

**If regression test cannot run:** Mark issue as `blocked`, NOT `human-qa`

## Overview
The `gi-fix` command is an automated issue resolution system that executes approved implementation plans, applies fixes using specialized WordPress subagents, and MUST verify resolution through regression testing before marking issues for human QA.

## Command Syntax
```bash
/gi-fix <issue-number>
```

## Prerequisites
- Issue must have an approved implementation plan (from `/gi-plan` or manual plan)
- Issue should have clear acceptance criteria
- WordPress Docker environment must be running
- GitHub CLI (`gh`) must be configured

## Workflow

### 1. Enhanced Issue Analysis Phase (NEW)
The command now performs comprehensive comment stream analysis before proceeding:

#### Comment History Analysis
- Fetches complete issue details including ALL comments
- Analyzes entire comment history for context
- Identifies previous fix attempts and their outcomes
- Extracts failure reasons from past attempts
- Detects human feedback on previous fixes

#### Pattern Recognition
The command looks for:
- Previous automated fix attempts (successful or failed)
- Human feedback indicating issues persist
- Regression test results from earlier attempts
- Error messages and diagnostic information
- Approval comments and implementation plans

#### Intelligent Decision Making
Based on the analysis, the command will:
- **HALT** if multiple failed attempts detected (≥2 failures)
- **HALT** if issue already marked `need-human` and attempted recently (<24 hours)
- **HALT** if no approved plan exists
- **PROCEED** only if new plan available since last failure
- **GENERATE SUMMARY** instead of retrying when appropriate

#### Summary Generation
When halting execution, generates comprehensive summary including:
- Timeline of all previous attempts
- Specific failure reasons extracted from comments
- Current issue status and labels
- Clear recommendation for next steps

### 2. Plan Verification
Looks for approval indicators:
- "approved-plan" label on the issue
- Implementation plan in comments (usually from `/gi-plan`)
- Human approval comments containing keywords like "approved", "LGTM", "proceed"
- Clear step-by-step implementation instructions

### 3. Implementation Phase
Uses specialized subagents based on issue type:

#### WordPress Development Issues
- **Subagent:** `wordpress-developer-expert`
- **Tasks:** Plugin architecture, Gutenberg blocks, REST APIs, database schemas
- **Approach:** Follows WordPress coding standards and best practices

#### Testing & Validation Issues
- **Subagent:** `wordpress-playwright-expert`
- **Tasks:** E2E test creation, form validation, user workflow testing
- **Approach:** Comprehensive test coverage with assertions

#### Performance Issues
- **Subagent:** `wordpress-performance-tester`
- **Tasks:** Database optimization, caching implementation, load testing
- **Approach:** Metrics-driven optimization

#### Debugging & Troubleshooting
- **Subagent:** `wordpress-troubleshooter`
- **Tasks:** Error diagnosis, conflict resolution, debugging
- **Approach:** Systematic root cause analysis

### 4. CRITICAL: Mandatory Regression Testing Phase

**WARNING: No fix can be marked as successful without passing regression tests.**

#### Step 1: Reproduce Original Issue First
Before claiming any fix works, you MUST:
1. Run a Playwright test that reproduces the EXACT issue reported
2. Confirm the test FAILS with the original error (e.g., 500 error, crash, etc.)
3. Capture a screenshot showing the failure
4. Document the specific error observed

#### Step 2: Apply the Fix
1. Implement the code changes according to the plan
2. Build/compile if necessary
3. Deploy changes to test environment

#### Step 3: Run Exact Same Test Again
After applying the fix, you MUST:
1. Run the SAME test that reproduced the issue
2. The test must now PASS without errors
3. Capture a screenshot showing success
4. Document that the specific error is gone

**NOTE:** If the Playwright test framework fails due to configuration issues, use the standalone regression test runner:
```bash
node .claude/commands/run-regression-test.js <issue-number>
```
This bypasses test framework issues and directly validates the fix.

#### Step 4: Regression Test Requirements
The regression test MUST follow the exact user steps from the issue:

**Example for Issue #5 (Editor Crash):**
```
1. Login to WordPress admin
2. Navigate to Posts → Add New
3. Add the AI Text Generator block
4. Add content to the block
5. Click "Save Draft" or press Ctrl+S
6. VERIFY: Post saves successfully (no 500 error)
7. CAPTURE: Screenshot of saved post with success message
```

#### Step 5: Verification Checklist
Before marking as `human-qa`, ALL must be true:
- [ ] Original issue reproduction test created and runs
- [ ] Test FAILED before fix (proving issue exists)
- [ ] Test PASSES after fix (proving issue resolved)
- [ ] Screenshot captured showing successful result
- [ ] No new errors introduced
- [ ] Specific error from issue is gone (e.g., no more 500)

**IF ANY CHECKLIST ITEM FAILS:** Mark as `fix-failed` not `human-qa`

### 5. Result Documentation
Based on verification results:

#### If Fix Successful ✅
- Removes "fixing" label
- Adds "human-qa" label
- Documents:
  - Implementation summary
  - Changed files list
  - Test results with evidence
  - Screenshots of working functionality
  - Instructions for human QA

#### If Fix Failed ❌
- Removes "fixing" label
- Adds "need-human" label
- Documents:
  - Attempted approaches
  - Failure reasons
  - Error messages and stack traces
  - Debugging recommendations
  - Blockers encountered

## Retry Logic
- **Maximum Attempts:** 3
- **Between Attempts:** Analyzes failure and tries alternative approach
- **Progressive Strategy:**
  - Attempt 1: Direct implementation per plan
  - Attempt 2: Alternative approach with workarounds
  - Attempt 3: Defensive implementation with fallbacks

## Label Management

### Labels Used
- `fixing` - Added when fix attempt starts (temporary)
- `human-qa` - Added when fix is verified (success)
- `need-human` - Added when automated fix fails (failure)
- `approved-plan` - Checked to verify plan exists (prerequisite)
- `cannot-reproduce` - Added when issue cannot be reproduced
- `blocked` - Added when environment or dependencies prevent testing

### Label Rules
- Never removes existing priority or type labels
- Never adds "closed" or "wontfix" labels
- Only humans can close issues

## Enhanced Failure Documentation

When a fix fails verification, the command automatically generates a comprehensive GitHub comment with detailed analysis. This ensures valuable diagnostic information is preserved even when automated fixes don't succeed.

### Failure Comment Contents

#### 1. What Was Attempted
- Specific code changes made based on the approved plan
- Build and deployment steps taken
- Test scenarios executed

#### 2. Test Results Comparison
- **Before Fix:** Shows the test failed as expected (proving issue exists)
- **After Fix:** Shows whether test passed or failed
- **Error Analysis:** Compares errors before/after to identify if fix had any effect

#### 3. Root Cause Analysis
- Explains why the fix didn't work based on test results
- Identifies if error is client-side vs server-side
- Distinguishes between symptoms and root causes

#### 4. Challenges Encountered
- Technical obstacles faced during implementation
- Environment or configuration issues
- Build system or deployment problems

#### 5. Suggested Next Steps
- Specific recommendations for manual investigation
- Which files or systems to examine
- What type of debugging would be most effective

#### 6. Supporting Evidence
- Screenshots from test runs
- Error logs and stack traces
- Network request/response data

### Example Failed Fix Comment
```markdown
## 🔍 Automated Fix Attempt - Failed

### What Was Attempted
The following fix was applied based on the approved plan:
- Removed `isGenerating` from saved block attributes (UI state only)
- Changed from attribute to local React state using `useState`
- Updated block deprecation for backward compatibility
- Rebuilt and deployed the plugin

### Test Results
#### Before Fix
- **Status**: FAILED ✅ (Expected failure)
- **Error**: 500 ERROR: 500 on http://localhost:8080/wp-json/wp/v2/posts/147

#### After Fix
- **Status**: FAILED ❌
- **Error**: 500 ERROR: 500 on http://localhost:8080/wp-json/wp/v2/posts/148

### Why The Fix Didn't Work
The same error persists after the fix, indicating:
- The root cause is not in the JavaScript block code
- The 500 error is likely from server-side PHP processing
- The block validation issue was a symptom, not the cause

### Challenges Encountered
1. **Build System**: Files were built to wrong location initially
2. **Test Framework**: Playwright global setup conflicts required standalone test runner
3. **Error Source**: 500 error originates from WordPress REST API, not block editor

### Suggested Next Steps
1. **Check PHP Error Logs**: The 500 error on `/wp-json/wp/v2/posts/` indicates server-side issue
2. **Debug REST API Handler**: Examine the WordPress REST API save post handler
3. **Check Plugin Activation**: Verify all database tables and options are properly initialized
4. **Review PHP Code**: Look for issues in:
   - `wp-content-flow.php` main plugin file
   - REST API endpoint handlers
   - Database save operations

### Recommendation
This appears to be a **server-side PHP issue**, not a JavaScript/block editor problem. Manual debugging of the WordPress REST API endpoint is required to identify the actual cause of the 500 error.

---
*Generated by gi-fix automated testing system*
```

### Benefits of Enhanced Documentation
1. **Knowledge Preservation**: Failed attempts provide valuable learning for future fixes
2. **Time Savings**: Developers don't repeat unsuccessful approaches
3. **Clear Handoff**: Human developers know exactly where to start investigating
4. **Pattern Recognition**: Common failure patterns become visible over time
5. **Continuous Improvement**: Documentation helps refine the automated fix process

## Comment Documentation

### Start Comment
```markdown
## 🔧 Starting Automated Fix Attempt

**Issue:** [Issue Title]
**Plan Source:** [Comment #X or Manual Plan]
**Timestamp:** [ISO 8601 timestamp]

### Execution Plan
1. Implement fixes according to approved plan
2. Run comprehensive tests
3. Verify issue resolution with Playwright
4. Document results

---
*Following implementation plan from [source]*
```

### Success Comment (ONLY if regression test passes)
```markdown
## ✅ Fix Successfully Implemented and Verified

### Regression Test Results
**Original Issue Reproduction:**
- Test Name: [exact test that reproduces issue]
- Before Fix: ❌ FAILED with [specific error, e.g., "500 error on save"]
- After Fix: ✅ PASSED - [specific error] no longer occurs
- Screenshot Evidence: [path to screenshot showing success]

### What Was Fixed
[Details of code changes made]

### Verification Steps Performed
1. [Step 1 from original issue - what user action]
2. [Step 2 from original issue - what user action]
3. [Step 3 from original issue - what user action]
4. **Result:** [What happened - must match expected behavior]

### Test Evidence
- **Playwright Test:** `e2e/issues/issue-[number]-regression.spec.js`
- **Before Fix Screenshot:** `e2e/screenshots/issue-[number]-before-fix.png`
- **After Fix Screenshot:** `e2e/screenshots/issue-[number]-after-fix.png`
- **Test Output:** All assertions passed, no errors

### Changed Files
- `file1.js` - [what was changed]
- `file2.php` - [what was changed]

### Next Steps for Human QA
Please verify by:
1. [Exact steps to reproduce the original issue]
2. Confirm the issue no longer occurs
3. Check for any side effects

---
*Automated fix completed and verified at [timestamp]*
```

### Failure Comment
```markdown
## ❌ Automated Fix Failed - Human Intervention Required

After 3 attempts, the automated fix could not be completed successfully.

### Last Error
```
[Error message]
```

### Attempted Solutions
[List of approaches tried]

### Recommendation
This issue requires manual investigation and fix by a human developer.

### Debugging Information
- Review the implementation plan for accuracy
- Check error logs in the test results
- Verify environment configuration
- Consider edge cases not covered in the plan

---
*Automated fix attempt failed at [timestamp]*
```

## Example Usage

### Successful Fix Flow
```bash
/gi-fix 5
```
1. Fetches issue #5 about WordPress editor crash
2. Finds approved plan in comments
3. Implements block validation fix
4. Runs Playwright test - editor saves successfully
5. Adds "human-qa" label
6. Documents success with test evidence

### Failed Fix Flow
```bash
/gi-fix 12
```
1. Fetches issue #12 about API timeout
2. Finds approved plan
3. Implements timeout increase - still fails
4. Tries caching approach - still fails
5. Tries async processing - still fails
6. Adds "need-human" label
7. Documents all attempts and errors

## Integration with GI-Plan

The `gi-fix` command is designed to work seamlessly with `/gi-plan`:

1. `/gi-plan 5` - Creates implementation plan
2. Human reviews and approves plan
3. `/gi-fix 5` - Executes approved plan
4. Human performs final QA
5. Human closes issue if satisfied

## Comment Analysis Intelligence

### What the Command Learns from Comments

#### Previous Attempt Detection
The command identifies these patterns in comments:
- "automated fix attempt" - Previous gi-fix execution
- "fix failed" or "failed fix" - Unsuccessful attempts
- "500 error still" - Persistent problems
- "same error persists" - Unchanged issues
- "human intervention required" - Manual fix needed
- "cannot reproduce" - Issue not reproducible

#### Human Feedback Recognition
Detects when humans report issues persist:
- "still broken" - Fix didn't work
- "not fixed" - Problem remains
- "issue persists" - Ongoing problem
- Comments after fix attempts indicating failure

#### Success Pattern Recognition
Identifies successful resolutions:
- "fix successfully verified" - Automated success
- "issue resolved" - Manual confirmation
- "working now" - Problem solved

### Decision Logic Based on Analysis

#### When Command Will NOT Proceed
1. **Multiple Failures**: 2+ previous failed attempts without new plan
2. **Recent Attempt**: Last attempt <24 hours ago with same plan
3. **Human Required**: Issue marked `need-human` without new information
4. **No Plan**: No approved implementation plan found
5. **Conflicting Feedback**: Human reports issue persists after "successful" fix

#### When Command WILL Proceed
1. **First Attempt**: No previous automated attempts detected
2. **New Plan Available**: Fresh plan posted after last failure
3. **Explicit Retry Request**: Human explicitly requests retry
4. **Changed Conditions**: Environment or code significantly changed

### Summary Report Generation

When the command decides not to proceed, it generates a detailed summary:

```markdown
## 📊 Issue Status Summary

### Previous Fix Attempts
This issue has been attempted 2 time(s):
- 11/29/2024: failed-fix ❌
- 11/30/2024: failed-fix ❌

### Last Failure Reason
The same error persists after the fix, indicating the root cause is not in the JavaScript block code

### Current Status
- Labels: bug, need-human, priority-high
- Approved Plan: Yes

### Recommendation
Multiple automated attempts have failed. Manual intervention is strongly recommended.
```

## Best Practices

### DO:
- ✅ ALWAYS analyze entire comment history before proceeding
- ✅ Learn from previous failed attempts
- ✅ Respect "need-human" labels unless explicitly overridden
- ✅ Generate summaries instead of repeating failures
- ✅ ALWAYS run regression test that reproduces the exact issue
- ✅ Capture before/after screenshots as proof
- ✅ Verify the SPECIFIC error is gone (not just "code changed")
- ✅ Document every action in comments
- ✅ Test the exact user workflow from the issue
- ✅ Provide clear next steps for humans

### DON'T:
- ❌ Close issues (only humans can close)
- ❌ Mark as `human-qa` without passing regression test
- ❌ Claim success based on:
  - "Code compiled successfully" (NOT sufficient)
  - "Pattern found/removed in code" (NOT sufficient)  
  - "Files were edited" (NOT sufficient)
  - "Build succeeded" (NOT sufficient)
- ❌ Pivot to simpler tests when real test fails
- ❌ Skip regression testing due to environment issues
- ❌ Report success without screenshot evidence

### CRITICAL FAILURES TO AVOID:
1. **Success Theater:** Claiming fix works without testing actual user problem
2. **Test Abandonment:** Giving up on real test and checking build files instead
3. **False Victory:** Marking as fixed because code changed, not because problem solved
4. **Missing Evidence:** No screenshot proving the issue is actually fixed
5. **Wrong Test:** Testing something else instead of the reported issue

## Error Handling

### When Regression Test Cannot Run (BLOCKED)
If the Playwright test cannot connect to WordPress or environment is broken:

**REQUIRED RESPONSE:**
```markdown
## ⚠️ Fix Applied But UNVERIFIED - Environment Blocked

### Code Changes Applied
- [List files changed]
- [What was modified]

### Regression Test Blocked
**Cannot verify fix because:**
- [ ] WordPress admin not accessible
- [ ] Playwright cannot connect (timeout/auth issues)
- [ ] Docker environment not responding
- [ ] Database connection failed

### Current Status: UNVERIFIED
The code has been changed but we CANNOT confirm if the issue is fixed.

### Required Before Marking as Fixed:
1. Resolve environment access issues
2. Run regression test successfully
3. Capture screenshot of working feature
4. Verify specific error is gone

**Label:** `blocked` (NOT `human-qa`)

---
*Fix implementation blocked at [timestamp]*
```

**DO NOT mark as `human-qa` if regression test didn't run!**

### Common Failure Scenarios
1. **No Approved Plan:** Exits with clear message
2. **Environment Issues:** Mark as `blocked`, NOT as fixed
3. **Test Failures:** Document what specifically failed
4. **Compilation Errors:** Attempts fixes or escalates
5. **Timeout Issues:** Increases timeouts or escalates

### Recovery Strategies
- Rollback changes if fix causes regression
- Document partial progress if some parts work
- Provide debugging artifacts for human review
- Suggest specific next steps based on failure type

## Verification Standards

### MANDATORY Regression Test Requirements

**A fix is ONLY verified when ALL of these are true:**

1. **Reproduction Test Exists**
   - Test reproduces the EXACT steps from the issue
   - Test checks for the SPECIFIC error mentioned (e.g., 500 error, crash)
   - Test captures screenshot of the problem

2. **Before-Fix Test Run**
   - Run test BEFORE applying fix
   - Test must FAIL with the reported error
   - Screenshot captured showing the failure
   - Error matches what user reported

3. **After-Fix Test Run**  
   - Run SAME test AFTER applying fix
   - Test must PASS without errors
   - Screenshot captured showing success
   - Specific error from issue is gone

4. **Evidence Documentation**
   - Before screenshot: `issue-X-before-fix.png`
   - After screenshot: `issue-X-after-fix.png`
   - Test output logs showing pass/fail
   - Specific error absence confirmed

### Regression Test Template (Plain English)

```markdown
## Regression Test for Issue #[number]

### Test Name: 
Reproduce [Issue Title]

### Test Steps:
1. [First user action from issue]
2. [Second user action from issue]
3. [Third user action from issue]
4. [Action that triggers the bug]

### Expected Before Fix:
- ERROR: [Specific error from issue, e.g., "500 error when saving"]
- User sees: [What breaks for the user]
- Screenshot shows: [Visual evidence of problem]

### Expected After Fix:
- SUCCESS: [What should happen instead]
- User sees: [Success message or normal behavior]  
- Screenshot shows: [Visual evidence of fix working]

### Verification Points:
- [ ] Specific error code gone (e.g., no 500)
- [ ] User workflow completes successfully
- [ ] No console errors
- [ ] UI shows success state
```

## Monitoring & Metrics

### Success Metrics
- Fix success rate
- Average attempts before success
- Time to resolution
- Human QA pass rate

### Failure Analysis
- Common failure patterns
- Subagent effectiveness
- Plan quality correlation
- Environment stability

## Human Handoff

### When Fix Succeeds (human-qa)
Human should:
1. Review implementation changes
2. Run manual testing
3. Verify user experience
4. Close issue if satisfied
5. Reopen with feedback if issues found

### When Fix Fails (need-human)
Human should:
1. Review attempted approaches
2. Analyze error messages
3. Investigate root cause
4. Manually implement fix
5. Update plan for future reference

## Security Considerations
- Never commits credentials or sensitive data
- Validates all inputs before execution
- Uses safe coding practices
- Documents security-relevant changes
- Alerts humans to security implications

## Performance Considerations
- Monitors execution time
- Optimizes test runs
- Caches dependencies where possible
- Cleans up test artifacts
- Reports performance impacts

## Future Enhancements
- Machine learning for plan optimization
- Automatic rollback on regression
- Parallel fix attempts
- Integration with CI/CD
- Metrics dashboard
- Fix pattern library