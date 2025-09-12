# GitHub Issue Planning (GI-Plan) Command

This command analyzes a GitHub issue, attempts to replicate it using Playwright, and either labels it as "cant-reproduce" or creates a detailed implementation plan for human review.

## Input
- Issue number (required) - The GitHub issue number to analyze and plan

## Workflow

### 1. Issue Retrieval
- Fetch the issue details using GitHub CLI (`gh issue view`)
- Extract issue title, description, and current labels
- Identify issue type (bug, enhancement, feature request)
- Review any comments for additional context

### 2. Screenshot Analysis
- Check for screenshots in issue description or comments
- Look for visual evidence files in `/tmp/` directory
- Analyze screenshots to understand the visual problem/feature
- Extract UI elements and workflows from screenshots

### 3. Playwright Replication Attempt
For bugs and issues that can be tested:
- Set up WordPress test environment with Docker
- Write Playwright test to reproduce the issue
- Execute test with detailed logging
- Capture screenshots of test execution
- Document exact steps taken in test

#### Replication Test Structure:
```javascript
// Example structure for replication test
test('Issue #[number] - [description]', async ({ page }) => {
  // 1. Navigate to WordPress admin
  // 2. Reproduce steps from issue description
  // 3. Verify expected vs actual behavior
  // 4. Capture evidence screenshots
});
```

### 4. Outcome Determination

#### If Issue CANNOT Be Reproduced:
1. **Label Application**: Apply "cant-reproduce" label to the issue
2. **Comment Creation**: Add detailed comment including:
   - Test environment details (WordPress version, plugin version, browser)
   - Exact steps attempted with Playwright
   - Screenshots from test execution
   - Request for additional information if needed
   - Playwright test code used for reproduction attempt

#### If Issue CAN Be Reproduced:
1. **Root Cause Analysis**: Investigate the underlying cause
2. **Implementation Plan Creation**: Develop comprehensive fix/implementation plan
3. **Label Application**: Apply "need-human" label for review
4. **Comment Creation**: Add detailed plan including:
   - Confirmed reproduction steps with evidence
   - Root cause analysis findings
   - Proposed solution approach
   - Implementation steps breakdown
   - Affected files and components
   - Testing requirements
   - Estimated effort and complexity
   - Risk assessment and mitigation

### 5. Implementation Plan Structure
When issue is reproducible, create plan with:

**Problem Confirmation**
- Reproduction confirmation with Playwright test results
- Screenshots/evidence of the issue
- Environment details where reproduced

**Root Cause Analysis**
- Technical investigation findings
- Code paths affected
- Why the issue occurs

**Proposed Solution**
- High-level approach to fix/implement
- Alternative approaches considered
- Recommended approach with justification

**Implementation Steps**
1. Specific code changes required
2. Files to be modified/created
3. Database changes if needed
4. API changes if applicable

**Testing Plan**
- Unit tests to be written
- E2E tests with Playwright
- Manual testing requirements
- Regression testing needs

**Risk Assessment**
- Potential side effects
- Breaking changes
- Performance implications
- Security considerations

**Effort Estimation**
- Complexity level (Low/Medium/High)
- Estimated time to implement
- Dependencies on other issues/features

### 6. GitHub Integration
- Use `gh` CLI for all GitHub operations
- Apply labels atomically with issue updates
- Format comments with proper markdown
- Include code blocks for test scripts
- Link to related issues/PRs

### 7. Error Handling
- Handle WordPress environment setup failures gracefully
- Manage Playwright test timeouts appropriately
- Provide clear error messages if reproduction fails
- Fallback to manual analysis if automation fails

## Label Definitions
- **cant-reproduce**: Issue cannot be replicated using Playwright testing
- **need-human**: Issue confirmed and plan ready for human review/approval
- **in-progress**: (Not applied by this command, but noted for context)

## Output
- GitHub issue URL with updated labels
- Summary of reproduction attempt results
- If reproducible: Link to implementation plan comment
- If not reproducible: Link to cant-reproduce comment
- Suggested next steps for team

## Example Usage
```bash
/gi-plan 42
```

This would:
1. Fetch issue #42 from the repository
2. Analyze any screenshots or visual evidence
3. Create and run Playwright test to reproduce
4. Either mark as "cant-reproduce" with evidence, or
5. Create detailed implementation plan and mark as "need-human"

## WordPress-Specific Considerations
- Test in standard WordPress Docker environment
- Consider WordPress version compatibility
- Check for plugin conflicts
- Verify with different user roles
- Test in both Classic and Block editors where applicable
- Consider multisite implications
- Check PHP version requirements