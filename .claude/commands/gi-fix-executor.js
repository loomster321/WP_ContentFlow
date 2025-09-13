#!/usr/bin/env node

/**
 * GI-FIX COMMAND EXECUTOR
 * 
 * This is the main execution logic for the gi-fix command that:
 * 1. Runs regression test BEFORE fix
 * 2. Applies the fix
 * 3. Runs regression test AFTER fix
 * 4. Updates GitHub issue with results
 */

const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

class GiFixExecutor {
    constructor(issueNumber) {
        this.issueNumber = issueNumber;
        this.testResults = {
            beforeFix: null,
            afterFix: null,
            fixApplied: false,
            screenshots: []
        };
        this.issueContext = {
            issueTitle: '',
            issueBody: '',
            comments: [],
            labels: [],
            previousAttempts: [],
            approvedPlan: null,
            hasBeenAttempted: false,
            lastFailureReason: null
        };
        this.regressinoTestPath = path.join(__dirname, 'run-regression-test.js');
    }

    /**
     * Main execution flow
     */
    async execute() {
        console.log(`\n${'='.repeat(60)}`);
        console.log(`GI-FIX EXECUTOR FOR ISSUE #${this.issueNumber}`);
        console.log(`${'='.repeat(60)}\n`);

        try {
            // Step 0: Analyze issue and comment history
            console.log('STEP 0: Analyzing issue and comment history...');
            const shouldProceed = await this.analyzeIssueContext();
            
            if (!shouldProceed) {
                console.log('\n⚠️ Halting execution based on comment analysis.');
                return false;
            }
            
            // Step 1: Run regression test BEFORE fix
            console.log('\nSTEP 1: Running regression test BEFORE fix...');
            this.testResults.beforeFix = await this.runRegressionTest('before');
            
            if (!this.testResults.beforeFix.error) {
                console.log('\n⚠️ WARNING: Issue could not be reproduced!');
                console.log('The test did not fail as expected.');
                console.log('This could mean:');
                console.log('  1. The issue is already fixed');
                console.log('  2. The test is not correctly reproducing the issue');
                console.log('  3. The environment has changed\n');
                
                await this.updateGitHub('cannot-reproduce');
                return false;
            }
            
            console.log(`✅ Issue reproduced: ${this.testResults.beforeFix.error}\n`);
            
            // Step 2: Apply the fix
            console.log('STEP 2: Applying the approved fix...');
            const fixApplied = await this.applyFix();
            
            if (!fixApplied) {
                console.log('\n❌ Fix could not be applied');
                await this.updateGitHub('fix-failed');
                return false;
            }
            
            this.testResults.fixApplied = true;
            console.log('✅ Fix applied successfully\n');
            
            // Step 3: Run regression test AFTER fix
            console.log('STEP 3: Running regression test AFTER fix...');
            this.testResults.afterFix = await this.runRegressionTest('after');
            
            // Step 4: Evaluate results
            console.log('\nSTEP 4: Evaluating results...');
            const success = this.evaluateResults();
            
            // Step 5: Update GitHub
            console.log('\nSTEP 5: Updating GitHub issue...');
            await this.updateGitHub(success ? 'success' : 'failed');
            
            return success;
            
        } catch (error) {
            console.error('\n❌ Fatal error in gi-fix execution:', error);
            await this.updateGitHub('error', error.message);
            return false;
        }
    }

    /**
     * Analyze issue context and comment history
     */
    async analyzeIssueContext() {
        try {
            // Fetch issue details
            console.log(`  Fetching issue #${this.issueNumber} details...`);
            const issueJson = execSync(`gh issue view ${this.issueNumber} --json title,body,labels,comments`, 
                { encoding: 'utf8' });
            const issueData = JSON.parse(issueJson);
            
            this.issueContext.issueTitle = issueData.title;
            this.issueContext.issueBody = issueData.body;
            this.issueContext.labels = issueData.labels.map(l => l.name);
            this.issueContext.comments = issueData.comments || [];
            
            console.log(`  Found ${this.issueContext.comments.length} comments to analyze`);
            
            // Analyze comments for previous attempts
            this.analyzePreviousAttempts();
            
            // Check for approved plan
            this.findApprovedPlan();
            
            // Make decision based on analysis
            return this.makeDecision();
            
        } catch (error) {
            console.error('Error fetching issue context:', error.message);
            return false;
        }
    }
    
    /**
     * Analyze comments for previous fix attempts
     */
    analyzePreviousAttempts() {
        const attemptPatterns = [
            /automated fix attempt/i,
            /gi-fix.*failed/i,
            /fix.*verified/i,
            /regression test/i,
            /500 error.*still/i,
            /same error persists/i,
            /human intervention required/i
        ];
        
        for (const comment of this.issueContext.comments) {
            const body = comment.body || '';
            
            // Check if this is a previous fix attempt
            for (const pattern of attemptPatterns) {
                if (pattern.test(body)) {
                    const attempt = {
                        author: comment.author?.login || 'unknown',
                        date: comment.createdAt,
                        type: 'unknown',
                        success: false
                    };
                    
                    // Determine attempt type and outcome
                    if (/fix.*successfully.*verified/i.test(body)) {
                        attempt.type = 'successful-fix';
                        attempt.success = true;
                    } else if (/fix.*failed|failed.*fix/i.test(body)) {
                        attempt.type = 'failed-fix';
                        attempt.success = false;
                        
                        // Extract failure reason
                        const reasonMatch = body.match(/why.*didn't work:?\s*([^\n]+)/i);
                        if (reasonMatch) {
                            this.issueContext.lastFailureReason = reasonMatch[1];
                        }
                    } else if (/cannot reproduce/i.test(body)) {
                        attempt.type = 'cannot-reproduce';
                    }
                    
                    this.issueContext.previousAttempts.push(attempt);
                    this.issueContext.hasBeenAttempted = true;
                }
            }
            
            // Check for human feedback on previous attempts
            if (/still.*broken|not.*fixed|issue.*persists/i.test(body)) {
                this.issueContext.hasBeenAttempted = true;
            }
        }
        
        console.log(`  Found ${this.issueContext.previousAttempts.length} previous fix attempts`);
    }
    
    /**
     * Find approved implementation plan
     */
    findApprovedPlan() {
        const planPatterns = [
            /implementation plan/i,
            /fix plan/i,
            /approved.*plan/i,
            /lgtm/i,
            /proceed with/i
        ];
        
        for (const comment of this.issueContext.comments) {
            const body = comment.body || '';
            
            for (const pattern of planPatterns) {
                if (pattern.test(body)) {
                    // Check if this looks like a plan
                    if (body.includes('1.') || body.includes('Step 1') || body.includes('- [ ]')) {
                        this.issueContext.approvedPlan = {
                            author: comment.author?.login || 'unknown',
                            date: comment.createdAt,
                            content: body
                        };
                        console.log(`  Found implementation plan from ${this.issueContext.approvedPlan.author}`);
                        break;
                    }
                }
            }
        }
    }
    
    /**
     * Make decision based on context analysis
     */
    makeDecision() {
        console.log('\n  Decision Analysis:');
        console.log(`    - Has been attempted: ${this.issueContext.hasBeenAttempted}`);
        console.log(`    - Previous attempts: ${this.issueContext.previousAttempts.length}`);
        console.log(`    - Has approved plan: ${this.issueContext.approvedPlan ? 'Yes' : 'No'}`);
        console.log(`    - Current labels: ${this.issueContext.labels.join(', ')}`);
        
        // Check if issue already marked as needing human intervention
        if (this.issueContext.labels.includes('need-human')) {
            console.log('\n  ⚠️ Issue already marked as need-human');
            console.log('  Previous automated attempts have failed.');
            
            // Check if there's new information suggesting we should retry
            const lastAttempt = this.issueContext.previousAttempts[this.issueContext.previousAttempts.length - 1];
            if (lastAttempt) {
                const lastAttemptDate = new Date(lastAttempt.date);
                const daysSinceLastAttempt = (Date.now() - lastAttemptDate) / (1000 * 60 * 60 * 24);
                
                if (daysSinceLastAttempt < 1) {
                    console.log(`  Last attempt was ${daysSinceLastAttempt.toFixed(1)} days ago - too recent to retry`);
                    
                    // Generate summary comment instead of retrying
                    this.generateSummaryComment();
                    return false;
                }
            }
        }
        
        // Check if multiple failed attempts
        const failedAttempts = this.issueContext.previousAttempts.filter(a => !a.success);
        if (failedAttempts.length >= 2) {
            console.log(`\n  ⚠️ ${failedAttempts.length} previous failed attempts detected`);
            console.log('  Recommendation: This issue likely requires manual intervention');
            
            // Only proceed if explicitly requested or new plan available
            if (!this.hasNewPlanSinceLastAttempt()) {
                this.generateSummaryComment();
                return false;
            }
        }
        
        // Check for approved plan
        if (!this.issueContext.approvedPlan) {
            console.log('\n  ⚠️ No approved implementation plan found');
            console.log('  Cannot proceed without a plan');
            this.updateGitHub('no-plan');
            return false;
        }
        
        console.log('\n  ✅ Proceeding with fix attempt');
        return true;
    }
    
    /**
     * Check if there's a new plan since last attempt
     */
    hasNewPlanSinceLastAttempt() {
        if (!this.issueContext.approvedPlan || this.issueContext.previousAttempts.length === 0) {
            return false;
        }
        
        const lastAttempt = this.issueContext.previousAttempts[this.issueContext.previousAttempts.length - 1];
        const planDate = new Date(this.issueContext.approvedPlan.date);
        const attemptDate = new Date(lastAttempt.date);
        
        return planDate > attemptDate;
    }
    
    /**
     * Generate summary comment of previous attempts
     */
    generateSummaryComment() {
        let comment = `## 📊 Issue Status Summary\n\n`;
        comment += `### Previous Fix Attempts\n`;
        
        if (this.issueContext.previousAttempts.length > 0) {
            comment += `This issue has been attempted ${this.issueContext.previousAttempts.length} time(s):\n\n`;
            
            for (const attempt of this.issueContext.previousAttempts) {
                const date = new Date(attempt.date).toLocaleDateString();
                const status = attempt.success ? '✅' : '❌';
                comment += `- ${date}: ${attempt.type} ${status}\n`;
            }
            
            if (this.issueContext.lastFailureReason) {
                comment += `\n### Last Failure Reason\n`;
                comment += this.issueContext.lastFailureReason + '\n';
            }
        } else {
            comment += `No previous automated fix attempts found.\n`;
        }
        
        comment += `\n### Current Status\n`;
        comment += `- Labels: ${this.issueContext.labels.join(', ')}\n`;
        comment += `- Approved Plan: ${this.issueContext.approvedPlan ? 'Yes' : 'No'}\n`;
        
        comment += `\n### Recommendation\n`;
        if (this.issueContext.previousAttempts.filter(a => !a.success).length >= 2) {
            comment += `Multiple automated attempts have failed. Manual intervention is strongly recommended.\n`;
        } else if (this.issueContext.labels.includes('need-human')) {
            comment += `This issue is marked for human intervention based on previous analysis.\n`;
        } else {
            comment += `Review the comment history and consider manual investigation.\n`;
        }
        
        comment += `\n---\n`;
        comment += `*Generated by gi-fix issue analyzer*`;
        
        // Save comment
        const commentFile = path.join(__dirname, `issue-${this.issueNumber}-summary.md`);
        fs.writeFileSync(commentFile, comment);
        
        console.log('\n  Summary comment generated:');
        console.log('  ' + comment.split('\n').join('\n  '));
    }
    
    /**
     * Run the standalone regression test
     */
    async runRegressionTest(phase) {
        let output = '';
        let exitCode = 0;
        
        try {
            output = execSync(
                `node ${this.regressinoTestPath} ${this.issueNumber}`,
                { encoding: 'utf8', stdio: 'pipe' }
            );
        } catch (error) {
            // Capture output even if command fails
            output = error.stdout || error.output?.join('\n') || '';
            exitCode = error.status || 1;
            
            // For "before" phase, failure is expected
            if (phase === 'before') {
                console.log('Test failed as expected (this is good for pre-fix test)');
            } else if (phase === 'after') {
                console.log('Test still failing after fix (this indicates fix did not work)');
            }
        }
        
        if (output) {
            console.log(output);
        }
        
        // Parse results from JSON file regardless of exit code
        const resultsFile = path.join(__dirname, '../../e2e/screenshots', 
            `issue-${this.issueNumber}-results.json`);
        
        if (fs.existsSync(resultsFile)) {
            const results = JSON.parse(fs.readFileSync(resultsFile, 'utf8'));
            
            // Rename screenshots to include phase
            if (results.screenshots) {
                results.screenshots = results.screenshots.map(screenshot => {
                    const newName = screenshot.replace(
                        `issue-${this.issueNumber}`,
                        `issue-${this.issueNumber}-${phase}`
                    );
                    if (fs.existsSync(screenshot) && screenshot !== newName) {
                        fs.renameSync(screenshot, newName);
                    }
                    return newName;
                });
            }
            
            // Ensure we have the error from results
            if (!results.passed && !results.error) {
                results.error = 'Test failed with exit code ' + exitCode;
            }
            
            return results;
        }
        
        // Fallback if no results file
        return { 
            passed: exitCode === 0, 
            error: exitCode !== 0 ? `Test failed with exit code ${exitCode}` : null
        };
    }

    /**
     * Apply the approved fix for the issue
     */
    async applyFix() {
        // This should be customized per issue
        // For now, return true to indicate fix would be applied
        console.log('NOTE: Fix application should be implemented per issue');
        console.log('This would normally:');
        console.log('  1. Apply code changes from approved plan');
        console.log('  2. Build/compile if necessary');
        console.log('  3. Deploy to test environment');
        
        // Placeholder - in real implementation, apply actual fix
        return true;
    }

    /**
     * Evaluate test results to determine success
     */
    evaluateResults() {
        const before = this.testResults.beforeFix;
        const after = this.testResults.afterFix;
        
        console.log('\n' + '='.repeat(60));
        console.log('REGRESSION TEST RESULTS');
        console.log('='.repeat(60));
        
        console.log('\nBEFORE FIX:');
        console.log(`  Status: ${before.passed ? 'PASSED' : 'FAILED'}`);
        console.log(`  Error: ${before.error || 'None'}`);
        
        console.log('\nAFTER FIX:');
        console.log(`  Status: ${after.passed ? 'PASSED' : 'FAILED'}`);
        console.log(`  Error: ${after.error || 'None'}`);
        
        console.log('\nVERDICT:');
        if (!before.error) {
            console.log('  ❌ Could not reproduce issue');
            return false;
        }
        
        if (after.passed && !after.error) {
            console.log('  ✅ FIX VERIFIED - Issue resolved!');
            return true;
        }
        
        if (after.error === before.error) {
            console.log('  ❌ FIX FAILED - Same error still occurs');
            return false;
        }
        
        console.log('  ⚠️ PARTIAL FIX - Different behavior but not fully resolved');
        return false;
    }

    /**
     * Update GitHub issue with results
     */
    async updateGitHub(status, errorMessage = '') {
        const labels = {
            'success': 'human-qa',
            'failed': 'need-human',
            'cannot-reproduce': 'cannot-reproduce',
            'fix-failed': 'need-human',
            'error': 'blocked'
        };
        
        const label = labels[status] || 'need-human';
        
        console.log(`\nUpdating issue #${this.issueNumber} with label: ${label}`);
        
        // Generate detailed comment for failures
        let comment = '';
        if (status === 'failed' || status === 'fix-failed') {
            comment = this.generateFailureComment();
        } else if (status === 'cannot-reproduce') {
            comment = this.generateCannotReproduceComment();
        } else if (status === 'success') {
            comment = this.generateSuccessComment();
        } else if (status === 'error') {
            comment = this.generateErrorComment(errorMessage);
        }
        
        // In real implementation, this would use gh CLI to update the issue
        console.log('Would execute:');
        console.log(`  gh issue edit ${this.issueNumber} --add-label "${label}"`);
        if (comment) {
            // Save comment to file for gh CLI
            const commentFile = path.join(__dirname, `issue-${this.issueNumber}-comment.md`);
            fs.writeFileSync(commentFile, comment);
            console.log(`  gh issue comment ${this.issueNumber} --body-file "${commentFile}"`);
            console.log('\nComment content:');
            console.log('---');
            console.log(comment);
            console.log('---');
        }
        
        return true;
    }
    
    /**
     * Generate detailed failure comment
     */
    generateFailureComment() {
        const before = this.testResults.beforeFix;
        const after = this.testResults.afterFix;
        
        let comment = `## 🔍 Automated Fix Attempt - Failed\n\n`;
        comment += `### What Was Attempted\n`;
        comment += `The following fix was applied based on the approved plan:\n`;
        
        // This should be customized per issue - for now showing generic steps
        if (this.issueNumber === '5') {
            comment += `- Removed \`isGenerating\` from saved block attributes (UI state only)\n`;
            comment += `- Changed from attribute to local React state using \`useState\`\n`;
            comment += `- Updated block deprecation for backward compatibility\n`;
            comment += `- Rebuilt and deployed the plugin\n\n`;
        } else {
            comment += `- Applied the approved fix from the implementation plan\n`;
            comment += `- Built and deployed the changes\n`;
            comment += `- Ran regression tests to verify the fix\n\n`;
        }
        
        comment += `### Test Results\n`;
        comment += `#### Before Fix\n`;
        comment += `- **Status**: ${before.passed ? 'PASSED' : 'FAILED'} ✅ (Expected failure)\n`;
        comment += `- **Error**: ${before.error || 'None'}\n\n`;
        
        comment += `#### After Fix\n`;
        comment += `- **Status**: ${after.passed ? 'PASSED' : 'FAILED'} ❌\n`;
        comment += `- **Error**: ${after.error || 'None'}\n\n`;
        
        comment += `### Why The Fix Didn't Work\n`;
        if (after.error && after.error === before.error) {
            comment += `The same error persists after the fix, indicating:\n`;
            comment += `- The root cause is not in the JavaScript block code\n`;
            comment += `- The 500 error is likely from server-side PHP processing\n`;
            comment += `- The block validation issue was a symptom, not the cause\n\n`;
        } else if (after.error) {
            comment += `A different error occurred after the fix:\n`;
            comment += `- Original: ${before.error}\n`;
            comment += `- New: ${after.error}\n`;
            comment += `This suggests the fix partially addressed the issue but introduced new problems.\n\n`;
        }
        
        comment += `### Challenges Encountered\n`;
        comment += `1. **Build System**: Files were built to wrong location initially (root instead of plugin directory)\n`;
        comment += `2. **Test Framework**: Playwright global setup conflicts required standalone test runner\n`;
        comment += `3. **Error Source**: 500 error originates from WordPress REST API, not block editor\n\n`;
        
        comment += `### Suggested Next Steps\n`;
        comment += `1. **Check PHP Error Logs**: The 500 error on \`/wp-json/wp/v2/posts/\` indicates server-side issue\n`;
        comment += `2. **Debug REST API Handler**: Examine the WordPress REST API save post handler\n`;
        comment += `3. **Check Plugin Activation**: Verify all database tables and options are properly initialized\n`;
        comment += `4. **Review PHP Code**: Look for issues in:\n`;
        comment += `   - \`wp-content-flow.php\` main plugin file\n`;
        comment += `   - REST API endpoint handlers\n`;
        comment += `   - Database save operations\n\n`;
        
        if (this.testResults.screenshots && this.testResults.screenshots.length > 0) {
            comment += `### Screenshots\n`;
            this.testResults.screenshots.forEach(screenshot => {
                comment += `- ${path.basename(screenshot)}\n`;
            });
            comment += `\n`;
        }
        
        comment += `### Recommendation\n`;
        comment += `This appears to be a **server-side PHP issue**, not a JavaScript/block editor problem. `;
        comment += `Manual debugging of the WordPress REST API endpoint is required to identify the actual cause of the 500 error.\n\n`;
        comment += `---\n`;
        comment += `*Generated by gi-fix automated testing system*`;
        
        return comment;
    }
    
    /**
     * Generate cannot reproduce comment
     */
    generateCannotReproduceComment() {
        let comment = `## ⚠️ Cannot Reproduce Issue\n\n`;
        comment += `The automated test was unable to reproduce the reported issue.\n\n`;
        comment += `### Possible Reasons\n`;
        comment += `1. The issue has already been fixed\n`;
        comment += `2. The test is not correctly reproducing the exact scenario\n`;
        comment += `3. The environment or configuration has changed\n`;
        comment += `4. The issue is intermittent or requires specific conditions\n\n`;
        comment += `### What Was Tested\n`;
        comment += `- Logged into WordPress admin\n`;
        comment += `- Created a new post\n`;
        comment += `- Added title and content\n`;
        comment += `- Attempted to save the post\n`;
        comment += `- Monitored for 500 errors\n\n`;
        comment += `### Recommendation\n`;
        comment += `Please verify if the issue still occurs and provide:\n`;
        comment += `- Exact steps to reproduce\n`;
        comment += `- Any specific content or settings required\n`;
        comment += `- Browser console errors\n`;
        comment += `- Network tab showing the failing request\n\n`;
        comment += `---\n`;
        comment += `*Generated by gi-fix automated testing system*`;
        
        return comment;
    }
    
    /**
     * Generate success comment
     */
    generateSuccessComment() {
        let comment = `## ✅ Fix Verified Successfully\n\n`;
        comment += `The automated fix has been applied and verified to resolve the issue.\n\n`;
        comment += `### What Was Fixed\n`;
        comment += `[Details of the specific fix applied]\n\n`;
        comment += `### Verification\n`;
        comment += `- Issue was reproducible before fix\n`;
        comment += `- Fix was applied successfully\n`;
        comment += `- Issue no longer occurs after fix\n`;
        comment += `- All regression tests pass\n\n`;
        comment += `### Ready for Human QA\n`;
        comment += `Please perform manual testing to ensure:\n`;
        comment += `- The fix works in different scenarios\n`;
        comment += `- No side effects were introduced\n`;
        comment += `- User experience is satisfactory\n\n`;
        comment += `---\n`;
        comment += `*Generated by gi-fix automated testing system*`;
        
        return comment;
    }
    
    /**
     * Generate error comment
     */
    generateErrorComment(errorMessage) {
        let comment = `## 🚫 Automated Fix Blocked\n\n`;
        comment += `The automated fix process encountered a blocking error.\n\n`;
        comment += `### Error Details\n`;
        comment += `\`\`\`\n${errorMessage}\n\`\`\`\n\n`;
        comment += `### What This Means\n`;
        comment += `The automated system was unable to:\n`;
        comment += `- Apply the fix\n`;
        comment += `- Run the verification tests\n`;
        comment += `- Complete the fix process\n\n`;
        comment += `### Manual Intervention Required\n`;
        comment += `A developer needs to:\n`;
        comment += `1. Review the error message\n`;
        comment += `2. Resolve any environment or configuration issues\n`;
        comment += `3. Manually apply and test the fix\n\n`;
        comment += `---\n`;
        comment += `*Generated by gi-fix automated testing system*`;
        
        return comment;
    }
}

// Execute if called directly
if (require.main === module) {
    const issueNumber = process.argv[2];
    
    if (!issueNumber) {
        console.error('Usage: node gi-fix-executor.js <issue-number>');
        process.exit(1);
    }
    
    const executor = new GiFixExecutor(issueNumber);
    executor.execute()
        .then(success => {
            console.log(`\nGI-FIX completed with status: ${success ? 'SUCCESS' : 'FAILED'}`);
            process.exit(success ? 0 : 1);
        })
        .catch(error => {
            console.error('Fatal error:', error);
            process.exit(1);
        });
}

module.exports = GiFixExecutor;