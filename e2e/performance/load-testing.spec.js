const { test, expect } = require('@playwright/test');

test.describe('Performance and Load Testing', () => {
  
  test.beforeEach(async ({ page }) => {
    // Login as admin for performance tests
    await page.goto('/wp-admin');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'admin');
    await page.click('#wp-submit');
    await page.waitForURL('/wp-admin/index.php');
  });

  test.describe('Page Load Performance', () => {
    test('should load admin pages within performance thresholds', async ({ page }) => {
      const performanceEntries = [];
      
      // Test plugin settings page
      const startTime = Date.now();
      await page.goto('/wp-admin/admin.php?page=wp-content-flow-settings');
      await page.waitForSelector('h1');
      const settingsLoadTime = Date.now() - startTime;
      
      performanceEntries.push({ page: 'settings', loadTime: settingsLoadTime });
      expect(settingsLoadTime).toBeLessThan(3000); // 3 seconds max
      
      // Test workflows page
      const workflowStartTime = Date.now();
      await page.goto('/wp-admin/admin.php?page=wp-content-flow-workflows');
      await page.waitForSelector('.workflows-table');
      const workflowLoadTime = Date.now() - workflowStartTime;
      
      performanceEntries.push({ page: 'workflows', loadTime: workflowLoadTime });
      expect(workflowLoadTime).toBeLessThan(3000);
      
      // Test block editor with AI blocks
      const editorStartTime = Date.now();
      await page.goto('/wp-admin/post-new.php');
      await page.waitForSelector('.block-editor-page');
      const editorLoadTime = Date.now() - editorStartTime;
      
      performanceEntries.push({ page: 'block-editor', loadTime: editorLoadTime });
      expect(editorLoadTime).toBeLessThan(5000); // Block editor can be slower
      
      console.log('Performance Results:', performanceEntries);
    });

    test('should handle large workflow lists efficiently', async ({ page }) => {
      // Mock API with large workflow dataset
      await page.route('**/wp-json/wp-content-flow/v1/workflows', async route => {
        const workflows = Array.from({ length: 50 }, (_, i) => ({
          id: i + 1,
          name: `Workflow ${i + 1}`,
          description: `Auto-generated workflow ${i + 1} for testing`,
          status: Math.random() > 0.5 ? 'active' : 'draft',
          created_at: new Date(Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000).toISOString()
        }));
        
        await route.fulfill({
          contentType: 'application/json',
          body: JSON.stringify({ success: true, data: workflows })
        });
      });
      
      const startTime = Date.now();
      await page.goto('/wp-admin/admin.php?page=wp-content-flow-workflows');
      
      // Wait for all workflows to be rendered
      await page.waitForSelector('.workflows-table tbody tr:nth-child(10)');
      const renderTime = Date.now() - startTime;
      
      expect(renderTime).toBeLessThan(5000); // Should render 50 items within 5 seconds
      
      // Test pagination performance
      if (await page.locator('.tablenav-pages').count() > 0) {
        const paginationStart = Date.now();
        await page.click('.tablenav-pages .next-page');
        await page.waitForSelector('.workflows-table tbody tr:first-child');
        const paginationTime = Date.now() - paginationStart;
        
        expect(paginationTime).toBeLessThan(2000); // Pagination should be fast
      }
    });
  });

  test.describe('API Performance', () => {
    test('should handle concurrent API requests efficiently', async ({ page, context }) => {
      // Create multiple pages for concurrent testing
      const pages = await Promise.all([
        context.newPage(),
        context.newPage(),
        context.newPage()
      ]);
      
      // Login all pages
      for (const testPage of pages) {
        await testPage.goto('/wp-admin');
        await testPage.fill('#user_login', 'admin');
        await testPage.fill('#user_pass', 'admin');
        await testPage.click('#wp-submit');
        await testPage.waitForURL('/wp-admin/index.php');
      }
      
      // Mock AI API responses
      for (const testPage of pages) {
        await testPage.route('**/wp-json/wp-content-flow/v1/ai/generate', async route => {
          // Simulate processing time
          await new Promise(resolve => setTimeout(resolve, 1000));
          await route.fulfill({
            contentType: 'application/json',
            body: JSON.stringify({
              success: true,
              data: {
                content: `Generated content ${Math.random().toString(36).substring(7)}`,
                usage: { tokens: Math.floor(Math.random() * 100) + 50 }
              }
            })
          });
        });
      }
      
      // Start concurrent AI generation requests
      const startTime = Date.now();
      const concurrentPromises = pages.map(async (testPage, index) => {
        await testPage.goto('/wp-admin/post-new.php');
        await testPage.waitForSelector('.block-editor-page');
        
        // Insert AI block
        await testPage.click('.edit-post-header-toolbar__inserter-toggle');
        await testPage.fill('.block-editor-inserter__search input', 'AI Text Generator');
        await testPage.click('.block-editor-block-types-list__item:has-text("AI Text Generator")');
        
        // Generate content
        await testPage.fill('.wp-content-flow-ai-generator .prompt-input', `Concurrent test ${index}`);
        await testPage.click('.wp-content-flow-ai-generator .generate-button');
        
        // Wait for completion
        await testPage.waitForSelector('.wp-content-flow-ai-generator .generated-content', { timeout: 15000 });
      });
      
      await Promise.all(concurrentPromises);
      const totalTime = Date.now() - startTime;
      
      // All concurrent requests should complete within a reasonable time
      expect(totalTime).toBeLessThan(20000); // 20 seconds for 3 concurrent requests
      
      // Clean up
      for (const testPage of pages) {
        await testPage.close();
      }
    });

    test('should maintain performance under rapid sequential requests', async ({ page }) => {
      await page.goto('/wp-admin/post-new.php');
      await page.waitForSelector('.block-editor-page');
      
      // Mock rapid AI responses
      let requestCount = 0;
      await page.route('**/wp-json/wp-content-flow/v1/ai/generate', async route => {
        requestCount++;
        await route.fulfill({
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            data: {
              content: `Sequential content ${requestCount}`,
              usage: { tokens: 75 }
            }
          })
        });
      });
      
      // Insert AI block
      await page.click('.edit-post-header-toolbar__inserter-toggle');
      await page.fill('.block-editor-inserter__search input', 'AI Text Generator');
      await page.click('.block-editor-block-types-list__item:has-text("AI Text Generator")');
      
      // Make rapid sequential requests
      const requestTimes = [];
      for (let i = 0; i < 5; i++) {
        const startTime = Date.now();
        
        await page.fill('.wp-content-flow-ai-generator .prompt-input', `Sequential test ${i}`);
        await page.click('.wp-content-flow-ai-generator .generate-button');
        await page.waitForSelector('.wp-content-flow-ai-generator .generated-content');
        
        // Accept and clear for next test
        if (await page.locator('.wp-content-flow-ai-generator .accept-button').count() > 0) {
          await page.click('.wp-content-flow-ai-generator .accept-button');
          
          // Insert new AI block for next iteration
          if (i < 4) {
            await page.click('.edit-post-header-toolbar__inserter-toggle');
            await page.fill('.block-editor-inserter__search input', 'AI Text Generator');
            await page.click('.block-editor-block-types-list__item:has-text("AI Text Generator")');
          }
        }
        
        const requestTime = Date.now() - startTime;
        requestTimes.push(requestTime);
      }
      
      // Each request should complete within reasonable time
      requestTimes.forEach((time, index) => {
        expect(time).toBeLessThan(5000); // 5 seconds per request
        console.log(`Request ${index + 1}: ${time}ms`);
      });
      
      // Performance shouldn't degrade significantly over sequential requests
      const firstRequest = requestTimes[0];
      const lastRequest = requestTimes[requestTimes.length - 1];
      const degradationRatio = lastRequest / firstRequest;
      
      expect(degradationRatio).toBeLessThan(2); // No more than 2x slower
    });
  });

  test.describe('Memory and Resource Usage', () => {
    test('should not cause memory leaks in long-running sessions', async ({ page }) => {
      // Simulate a long editing session
      await page.goto('/wp-admin/post-new.php');
      await page.waitForSelector('.block-editor-page');
      
      // Mock consistent AI responses
      await page.route('**/wp-json/wp-content-flow/v1/ai/generate', async route => {
        await route.fulfill({
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            data: {
              content: `Memory test content ${Date.now()}`,
              usage: { tokens: 100 }
            }
          })
        });
      });
      
      // Perform multiple operations that could cause memory leaks
      for (let i = 0; i < 10; i++) {
        // Insert AI block
        await page.click('.edit-post-header-toolbar__inserter-toggle');
        await page.fill('.block-editor-inserter__search input', 'AI Text Generator');
        await page.click('.block-editor-block-types-list__item:has-text("AI Text Generator")');
        
        // Generate content
        await page.fill('.wp-content-flow-ai-generator .prompt-input', `Memory test ${i}`);
        await page.click('.wp-content-flow-ai-generator .generate-button');
        await page.waitForSelector('.wp-content-flow-ai-generator .generated-content');
        
        // Accept content
        await page.click('.wp-content-flow-ai-generator .accept-button');
        
        // Add some variety to test different code paths
        if (i % 3 === 0) {
          // Save draft
          await page.click('.editor-post-save-draft');
          await page.waitForSelector('.is-saved');
        }
      }
      
      // Check that the page is still responsive
      const responseCheck = Date.now();
      await page.click('.edit-post-header-toolbar__inserter-toggle');
      const responseTime = Date.now() - responseCheck;
      
      // Should still be responsive after many operations
      expect(responseTime).toBeLessThan(2000);
      
      // Check JavaScript heap size if available
      const heapSize = await page.evaluate(() => {
        return (performance as any).memory ? (performance as any).memory.usedJSHeapSize : null;
      });
      
      if (heapSize) {
        console.log(`JavaScript heap size after operations: ${(heapSize / 1024 / 1024).toFixed(2)}MB`);
        // Heap size shouldn't be unreasonably large
        expect(heapSize).toBeLessThan(100 * 1024 * 1024); // 100MB limit
      }
    });

    test('should handle large content efficiently', async ({ page }) => {
      await page.goto('/wp-admin/post-new.php');
      await page.waitForSelector('.block-editor-page');
      
      // Generate large content
      const largeContent = 'Lorem ipsum dolor sit amet. '.repeat(1000); // ~27KB of text
      
      await page.route('**/wp-json/wp-content-flow/v1/ai/generate', async route => {
        await route.fulfill({
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            data: {
              content: largeContent,
              usage: { tokens: 5000 }
            }
          })
        });
      });
      
      // Insert AI block and generate large content
      await page.click('.edit-post-header-toolbar__inserter-toggle');
      await page.fill('.block-editor-inserter__search input', 'AI Text Generator');
      await page.click('.block-editor-block-types-list__item:has-text("AI Text Generator")');
      
      const startTime = Date.now();
      await page.fill('.wp-content-flow-ai-generator .prompt-input', 'Generate large content');
      await page.click('.wp-content-flow-ai-generator .generate-button');
      
      // Wait for large content to load
      await page.waitForSelector('.wp-content-flow-ai-generator .generated-content');
      const loadTime = Date.now() - startTime;
      
      // Should handle large content within reasonable time
      expect(loadTime).toBeLessThan(10000); // 10 seconds max
      
      // Verify content is fully loaded
      const contentLength = await page.locator('.wp-content-flow-ai-generator .generated-content').textContent();
      expect(contentLength?.length).toBeGreaterThan(25000); // Should have most of the large content
      
      // Accept should work smoothly even with large content
      const acceptStartTime = Date.now();
      await page.click('.wp-content-flow-ai-generator .accept-button');
      const acceptTime = Date.now() - acceptStartTime;
      
      expect(acceptTime).toBeLessThan(5000); // Should accept large content quickly
    });
  });

  test.describe('Database Performance', () => {
    test('should handle workflow history efficiently', async ({ page }) => {
      // Mock large workflow history
      await page.route('**/wp-json/wp-content-flow/v1/workflows/*/history', async route => {
        const history = Array.from({ length: 100 }, (_, i) => ({
          id: i + 1,
          action: Math.random() > 0.5 ? 'executed' : 'created',
          timestamp: new Date(Date.now() - i * 60 * 1000).toISOString(),
          user: 'admin',
          details: `History entry ${i + 1}`,
          tokens_used: Math.floor(Math.random() * 200) + 50
        }));
        
        await route.fulfill({
          contentType: 'application/json',
          body: JSON.stringify({ success: true, data: history })
        });
      });
      
      await page.goto('/wp-admin/admin.php?page=wp-content-flow-workflows');
      await page.waitForSelector('.workflows-table');
      
      // Click on workflow to view history
      if (await page.locator('.workflows-table tbody tr .view-history').count() > 0) {
        const startTime = Date.now();
        await page.click('.workflows-table tbody tr .view-history');
        await page.waitForSelector('.workflow-history-modal');
        const historyLoadTime = Date.now() - startTime;
        
        expect(historyLoadTime).toBeLessThan(3000); // History should load quickly
        
        // Check pagination for large history
        if (await page.locator('.history-pagination').count() > 0) {
          const paginationStart = Date.now();
          await page.click('.history-pagination .next-page');
          await page.waitForSelector('.history-table tbody tr:first-child');
          const paginationTime = Date.now() - paginationStart;
          
          expect(paginationTime).toBeLessThan(1500); // Pagination should be fast
        }
      }
    });
  });
});