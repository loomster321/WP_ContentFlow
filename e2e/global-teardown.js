/**
 * Global Teardown for WordPress AI Content Flow Plugin E2E Tests
 * 
 * Cleanup test environment and generate final reports
 * Handles Docker cleanup and test artifact management
 */
async function globalTeardown() {
  console.log('🧹 Starting WordPress AI Content Flow Plugin E2E Teardown...');
  
  try {
    // Generate test summary report
    await generateTestSummaryReport();
    
    // Cleanup test data if not in CI (preserve for debugging)
    if (!process.env.CI) {
      await cleanupTestData();
    }
    
    // Archive test artifacts
    await archiveTestArtifacts();
    
    // Docker cleanup (optional - let docker-compose handle this)
    if (process.env.CLEANUP_DOCKER) {
      await cleanupDockerEnvironment();
    }
    
    console.log('✅ WordPress AI Content Flow Plugin E2E Teardown completed successfully!');
    
  } catch (error) {
    console.error('❌ Global teardown encountered issues:', error);
    // Don't throw error in teardown to avoid masking test failures
  }
}

/**
 * Generate comprehensive test summary report
 */
async function generateTestSummaryReport() {
  const fs = require('fs');
  const path = require('path');
  
  try {
    // Read test results
    const resultsPath = path.join(process.cwd(), 'test-results', 'results.json');
    
    if (fs.existsSync(resultsPath)) {
      const results = JSON.parse(fs.readFileSync(resultsPath, 'utf8'));
      
      // Generate summary
      const summary = {
        timestamp: new Date().toISOString(),
        stats: results.stats || {},
        environment: {
          nodeVersion: process.version,
          platform: process.platform,
          ci: !!process.env.CI,
          wpVersion: '6.4',
          pluginVersion: '1.0.0'
        },
        config: {
          baseURL: 'http://localhost:8080',
          browsers: ['chromium', 'firefox'],
          timeout: 45000
        }
      };
      
      // Write summary report
      const summaryPath = path.join(process.cwd(), 'test-results', 'summary.json');
      fs.writeFileSync(summaryPath, JSON.stringify(summary, null, 2));
      
      console.log(`📊 Test summary report generated: ${summaryPath}`);
      
      // Log key statistics
      if (results.stats) {
        console.log(`📈 Test Results Summary:`);
        console.log(`   Total tests: ${results.stats.total || 0}`);
        console.log(`   Passed: ${results.stats.passed || 0}`);
        console.log(`   Failed: ${results.stats.failed || 0}`);
        console.log(`   Skipped: ${results.stats.skipped || 0}`);
        console.log(`   Duration: ${results.stats.duration || 0}ms`);
      }
    }
  } catch (error) {
    console.warn('⚠️  Could not generate test summary report:', error.message);
  }
}

/**
 * Cleanup test data from WordPress
 */
async function cleanupTestData() {
  const { chromium } = require('@playwright/test');
  
  try {
    console.log('🗑️  Cleaning up test data...');
    
    const browser = await chromium.launch();
    const context = await browser.newContext();
    const page = await context.newPage();
    
    // Login as admin
    await page.goto('http://localhost:8080/wp-admin/', { waitUntil: 'networkidle' });
    
    try {
      await page.fill('#user_login', 'admin');
      await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
      await page.click('#wp-submit');
      await page.waitForSelector('#wpadminbar', { timeout: 10000 });
    } catch (loginError) {
      console.warn('⚠️  Could not login for cleanup, skipping...');
      await browser.close();
      return;
    }
    
    // Delete test posts
    await cleanupTestPosts(page);
    
    // Delete test users
    await cleanupTestUsers(page);
    
    // Reset plugin settings to defaults
    await resetPluginSettings(page);
    
    await browser.close();
    console.log('✅ Test data cleanup completed');
    
  } catch (error) {
    console.warn('⚠️  Test data cleanup failed:', error.message);
  }
}

/**
 * Cleanup test posts
 */
async function cleanupTestPosts(page) {
  try {
    await page.goto('http://localhost:8080/wp-admin/edit.php', { waitUntil: 'networkidle' });
    
    // Look for E2E test posts
    const testPostLinks = page.locator('a.row-title').filter({ hasText: /E2E Test Post/ });
    const count = await testPostLinks.count();
    
    if (count > 0) {
      console.log(`🗑️  Deleting ${count} test posts...`);
      
      for (let i = 0; i < count; i++) {
        try {
          // Navigate to posts list
          await page.goto('http://localhost:8080/wp-admin/edit.php', { waitUntil: 'networkidle' });
          
          // Find first test post
          const firstTestPost = page.locator('a.row-title').filter({ hasText: /E2E Test Post/ }).first();
          
          if (await firstTestPost.count() > 0) {
            // Hover to show action links
            await firstTestPost.hover();
            
            // Click trash link
            const trashLink = page.locator('.row-actions .trash a').first();
            if (await trashLink.count() > 0) {
              await trashLink.click();
              await page.waitForTimeout(1000); // Brief pause
            }
          }
        } catch (postError) {
          console.warn(`⚠️  Could not delete test post ${i + 1}:`, postError.message);
        }
      }
    }
  } catch (error) {
    console.warn('⚠️  Test posts cleanup failed:', error.message);
  }
}

/**
 * Cleanup test users
 */
async function cleanupTestUsers(page) {
  const testUsers = ['editor_test', 'author_test', 'contributor_test'];
  
  try {
    await page.goto('http://localhost:8080/wp-admin/users.php', { waitUntil: 'networkidle' });
    
    for (const username of testUsers) {
      try {
        const userRow = page.locator(`tr:has-text("${username}")`);
        
        if (await userRow.count() > 0) {
          // Hover to show delete link
          await userRow.hover();
          
          // Click delete link
          const deleteLink = userRow.locator('.delete a').first();
          if (await deleteLink.count() > 0) {
            await deleteLink.click();
            
            // Confirm deletion
            await page.waitForSelector('#submit', { timeout: 5000 });
            await page.click('#submit');
            
            console.log(`✅ Deleted test user: ${username}`);
            await page.waitForTimeout(1000); // Brief pause
          }
        }
      } catch (userError) {
        console.warn(`⚠️  Could not delete test user ${username}:`, userError.message);
      }
    }
  } catch (error) {
    console.warn('⚠️  Test users cleanup failed:', error.message);
  }
}

/**
 * Reset plugin settings to defaults
 */
async function resetPluginSettings(page) {
  try {
    await page.goto('http://localhost:8080/wp-admin/admin.php?page=wp-content-flow-settings', { 
      waitUntil: 'networkidle' 
    });
    
    // Look for reset button or clear test settings
    if (await page.locator('[name="wp_content_flow_openai_api_key"]').count() > 0) {
      await page.fill('[name="wp_content_flow_openai_api_key"]', '');
      await page.click('input[type="submit"]');
      await page.waitForTimeout(2000);
      console.log('✅ Plugin settings reset');
    }
  } catch (error) {
    console.warn('⚠️  Plugin settings reset failed:', error.message);
  }
}

/**
 * Archive test artifacts for later analysis
 */
async function archiveTestArtifacts() {
  const fs = require('fs');
  const path = require('path');
  
  try {
    const testResultsDir = path.join(process.cwd(), 'test-results');
    
    if (fs.existsSync(testResultsDir)) {
      // Create archive directory with timestamp
      const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
      const archiveDir = path.join(process.cwd(), 'test-archives', `run-${timestamp}`);
      
      if (!fs.existsSync(path.dirname(archiveDir))) {
        fs.mkdirSync(path.dirname(archiveDir), { recursive: true });
      }
      
      // Copy test results to archive (simple copy, could use proper archiving)
      fs.cpSync(testResultsDir, archiveDir, { recursive: true });
      
      console.log(`📦 Test artifacts archived: ${archiveDir}`);
    }
  } catch (error) {
    console.warn('⚠️  Test artifact archival failed:', error.message);
  }
}

/**
 * Cleanup Docker environment
 */
async function cleanupDockerEnvironment() {
  const { exec } = require('child_process');
  const { promisify } = require('util');
  const execAsync = promisify(exec);
  
  try {
    console.log('🐳 Cleaning up Docker environment...');
    
    // Stop and remove containers
    await execAsync('docker-compose down -v');
    
    // Remove unused volumes (optional)
    if (process.env.CLEANUP_DOCKER_VOLUMES) {
      await execAsync('docker volume prune -f');
    }
    
    console.log('✅ Docker environment cleaned up');
    
  } catch (error) {
    console.warn('⚠️  Docker cleanup failed:', error.message);
  }
}

module.exports = globalTeardown;