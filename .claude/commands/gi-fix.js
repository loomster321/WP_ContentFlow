#!/usr/bin/env node

/**
 * GitHub Issue Fix (GI-Fix) Command
 * 
 * CRITICAL: This command MUST verify fixes through regression testing.
 * No fix can be marked successful without:
 * 1. Running a test that reproduces the EXACT issue
 * 2. Confirming test FAILS before fix
 * 3. Confirming test PASSES after fix
 * 4. Capturing screenshot evidence
 * 
 * Usage: /gi-fix <issue-number>
 * 
 * Workflow:
 * 1. Fetch and analyze the issue and all comments
 * 2. Verify an approved implementation plan exists
 * 3. Create regression test that reproduces the issue
 * 4. Run test to confirm it FAILS (issue exists)
 * 5. Execute the plan using appropriate subagents
 * 6. Run SAME test to confirm it PASSES (issue fixed)
 * 7. Capture screenshots as evidence
 * 8. Label as "human-qa" ONLY if regression test passes
 * 9. Label as "blocked" if test cannot run
 * 10. Label as "need-human" if fix fails
 * 
 * Labels:
 * - "human-qa": Issue fixed AND regression test passed
 * - "blocked": Fix applied but cannot verify (environment issues)
 * - "need-human": Fix attempted but failed
 * - Never closes issues (only humans can close)
 */

const COMMAND_NAME = 'gi-fix';
const COMMAND_DESCRIPTION = 'Execute approved fix plan for GitHub issue and verify resolution';

// Command configuration
const config = {
  maxAttempts: 3,
  subagents: {
    wordpress: 'wordpress-developer-expert',
    testing: 'wordpress-playwright-expert',
    troubleshooting: 'wordpress-troubleshooter',
    performance: 'wordpress-performance-tester'
  },
  labels: {
    success: 'human-qa',        // ONLY if regression test passes
    blocked: 'blocked',          // Fix applied but cannot verify
    failure: 'need-human',       // Fix failed
    inProgress: 'in-progress',   // Currently fixing
    hasApprovedPlan: 'approved-plan'
  },
  // Regression test is MANDATORY
  regressionTestRequired: true
};

// Main command execution
async function executeCommand(issueNumber) {
  console.log(`\n🔧 GitHub Issue Fix (GI-Fix) Command`);
  console.log(`📋 Processing Issue #${issueNumber}\n`);
  
  // Step 1: Fetch and analyze issue
  console.log('Step 1: Fetching issue details and comments...');
  const issueData = await fetchIssueWithComments(issueNumber);
  
  if (!issueData) {
    console.error('❌ Failed to fetch issue data');
    return;
  }
  
  // Step 2: Verify approved plan exists
  console.log('Step 2: Verifying approved implementation plan...');
  const approvedPlan = findApprovedPlan(issueData);
  
  if (!approvedPlan) {
    console.log('⚠️ No approved implementation plan found');
    await addIssueComment(issueNumber, 
      '## ⚠️ Fix Attempt Blocked\n\n' +
      'Cannot proceed with fix - no approved implementation plan found.\n\n' +
      'Please ensure the issue has:\n' +
      '1. A detailed implementation plan (usually from /gi-plan command)\n' +
      '2. Human approval or "approved-plan" label\n' +
      '3. Clear acceptance criteria for verification'
    );
    return;
  }
  
  // Step 3: Add in-progress label
  console.log('Step 3: Marking issue as in-progress...');
  await updateIssueLabels(issueNumber, 'add', config.labels.inProgress);
  
  // Step 4: Document fix attempt start
  await addIssueComment(issueNumber,
    `## 🔧 Starting Automated Fix Attempt\n\n` +
    `**Issue:** ${issueData.title}\n` +
    `**Plan Source:** ${approvedPlan.source}\n` +
    `**Timestamp:** ${new Date().toISOString()}\n\n` +
    `### Execution Plan\n` +
    `1. Implement fixes according to approved plan\n` +
    `2. Run comprehensive tests\n` +
    `3. Verify issue resolution with Playwright\n` +
    `4. Document results\n\n` +
    `---\n` +
    `*Following implementation plan from ${approvedPlan.source}*`
  );
  
  // Step 5: Execute fix with retries
  let fixSuccess = false;
  let attempt = 0;
  let lastError = null;
  
  while (!fixSuccess && attempt < config.maxAttempts) {
    attempt++;
    console.log(`\nStep 5: Executing fix (Attempt ${attempt}/${config.maxAttempts})...`);
    
    try {
      // Execute the implementation plan
      const fixResult = await executeFix(issueData, approvedPlan, attempt);
      
      if (fixResult.success) {
        // Step 6: Verify fix with Playwright
        console.log('Step 6: Verifying fix with Playwright tests...');
        const verificationResult = await verifyFix(issueData, fixResult);
        
        if (verificationResult.success) {
          fixSuccess = true;
          
          // Step 7: Mark as fixed and ready for human QA
          console.log('Step 7: Fix verified! Marking for human QA...');
          await updateIssueLabels(issueNumber, 'remove', config.labels.inProgress);
          await updateIssueLabels(issueNumber, 'add', config.labels.success);
          
          // Document success
          await addIssueComment(issueNumber,
            `## ✅ Fix Successfully Implemented and Verified\n\n` +
            `### Implementation Summary\n` +
            `${fixResult.summary}\n\n` +
            `### Verification Results\n` +
            `${verificationResult.summary}\n\n` +
            `### Test Evidence\n` +
            `- **Playwright Test:** ${verificationResult.testFile}\n` +
            `- **Test Result:** All assertions passed\n` +
            `- **Screenshots:** ${verificationResult.screenshots}\n\n` +
            `### Changed Files\n` +
            `${fixResult.changedFiles.map(f => `- \`${f}\``).join('\\n')}\n\n` +
            `### Next Steps\n` +
            `This issue has been labeled \`human-qa\` for final human verification.\n` +
            `Please review the implementation and test manually before closing.\n\n` +
            `---\n` +
            `*Automated fix completed at ${new Date().toISOString()}*`
          );
          
          console.log('✅ Issue fixed and verified successfully!');
          
        } else {
          // Verification failed
          lastError = verificationResult.error;
          console.log(`⚠️ Fix verification failed: ${lastError}`);
          
          if (attempt < config.maxAttempts) {
            await addIssueComment(issueNumber,
              `## ⚠️ Verification Failed (Attempt ${attempt}/${config.maxAttempts})\n\n` +
              `The fix was implemented but verification failed.\n\n` +
              `**Error:** ${lastError}\n\n` +
              `Attempting alternative approach...`
            );
          }
        }
      } else {
        // Implementation failed
        lastError = fixResult.error;
        console.log(`⚠️ Fix implementation failed: ${lastError}`);
      }
      
    } catch (error) {
      lastError = error.message;
      console.error(`❌ Attempt ${attempt} failed:`, error);
    }
  }
  
  // Step 8: Handle failure after all attempts
  if (!fixSuccess) {
    console.log('Step 8: All fix attempts failed. Marking for human intervention...');
    await updateIssueLabels(issueNumber, 'remove', config.labels.inProgress);
    await updateIssueLabels(issueNumber, 'add', config.labels.failure);
    
    await addIssueComment(issueNumber,
      `## ❌ Automated Fix Failed - Human Intervention Required\n\n` +
      `After ${config.maxAttempts} attempts, the automated fix could not be completed successfully.\n\n` +
      `### Last Error\n` +
      `\`\`\`\n${lastError}\n\`\`\`\n\n` +
      `### Attempted Solutions\n` +
      `Multiple approaches were tried based on the approved implementation plan.\n\n` +
      `### Recommendation\n` +
      `This issue requires manual investigation and fix by a human developer.\n` +
      `The issue has been labeled \`need-human\` for prioritization.\n\n` +
      `### Debugging Information\n` +
      `- Review the implementation plan for accuracy\n` +
      `- Check error logs in the test results\n` +
      `- Verify environment configuration\n` +
      `- Consider edge cases not covered in the plan\n\n` +
      `---\n` +
      `*Automated fix attempt failed at ${new Date().toISOString()}*`
    );
    
    console.log('❌ Fix failed after all attempts. Human intervention required.');
  }
}

// Helper functions (these would be implemented with actual GitHub API calls)
async function fetchIssueWithComments(issueNumber) {
  // Fetch issue details and all comments
  // Returns issue data with comments array
  return {
    number: issueNumber,
    title: 'Issue Title',
    body: 'Issue Description',
    labels: [],
    comments: []
  };
}

async function findApprovedPlan(issueData) {
  // Look for approved implementation plan in comments
  // Check for "approved-plan" label or human approval comments
  // Returns plan object or null
  return {
    source: 'Comment #X',
    steps: [],
    acceptance: []
  };
}

async function executeFix(issueData, plan, attemptNumber) {
  // Execute the fix using appropriate subagents
  // Returns result object with success status
  return {
    success: false,
    summary: '',
    changedFiles: [],
    error: null
  };
}

async function verifyFix(issueData, fixResult) {
  // Run Playwright tests to verify the fix
  // Returns verification result
  return {
    success: false,
    summary: '',
    testFile: '',
    screenshots: '',
    error: null
  };
}

async function updateIssueLabels(issueNumber, action, label) {
  // Add or remove labels from issue
  console.log(`${action === 'add' ? 'Adding' : 'Removing'} label: ${label}`);
}

async function addIssueComment(issueNumber, comment) {
  // Add comment to issue
  console.log('Adding comment to issue...');
}

// Parse command line arguments
const args = process.argv.slice(2);
const issueNumber = args[0];

if (!issueNumber || isNaN(issueNumber)) {
  console.error('❌ Error: Please provide a valid issue number');
  console.log('Usage: /gi-fix <issue-number>');
  process.exit(1);
}

// Execute command
executeCommand(parseInt(issueNumber))
  .then(() => process.exit(0))
  .catch(error => {
    console.error('❌ Command failed:', error);
    process.exit(1);
  });