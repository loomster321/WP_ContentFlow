/**
 * AI Text Block Save Fix Verification Test
 * 
 * This test verifies that the critical fix for the WordPress Gutenberg block
 * that was causing crashes when saving posts is working properly.
 */

import { test, expect } from '@playwright/test';

test.describe('AI Text Block Save Fix Verification', () => {
    test.beforeEach(async ({ page }) => {
        // Navigate to WordPress admin
        await page.goto('/wp-admin/');
        
        // Login if needed (basic WordPress setup)
        try {
            await page.waitForSelector('#user_login', { timeout: 2000 });
            await page.fill('#user_login', 'admin');
            await page.fill('#user_pass', '!3cTXkh)9iDHhV5o*N');
            await page.click('#wp-submit');
            await page.waitForLoadState('networkidle');
        } catch {
            // Already logged in or login not needed
        }
    });

    test('should add AI text block and save post without 500 error', async ({ page }) => {
        // Navigate to new post editor
        await page.goto('/wp-admin/post-new.php');
        await page.waitForLoadState('networkidle');
        
        // Wait for Gutenberg editor to be ready
        await page.waitForSelector('.block-editor-writing-flow', { timeout: 10000 });
        
        // Add AI Text Generator block
        await page.click('.block-editor-inserter__toggle');
        await page.waitForSelector('.block-editor-inserter__search-input');
        await page.fill('.block-editor-inserter__search-input', 'AI Text Generator');
        
        // Look for the block in search results
        const aiTextBlock = page.locator('.block-editor-block-types-list__item').filter({ hasText: 'AI Text Generator' }).first();
        
        if (await aiTextBlock.count() > 0) {
            await aiTextBlock.click();
            
            // Add some test content to the block to simulate generated content
            const blockSelector = '[data-type="wp-content-flow/ai-text"]';
            await page.waitForSelector(blockSelector);
            
            // Try to add content to the RichText field
            const richTextElement = page.locator(`${blockSelector} .content-display`).first();
            if (await richTextElement.count() > 0) {
                await richTextElement.click();
                await richTextElement.fill('This is test AI generated content for save verification.');
            }
            
            // Save the post
            await page.click('.editor-post-save-draft, .editor-post-publish-panel__toggle');
            
            // Wait for save to complete and check for success
            await page.waitForSelector('.editor-post-saved-state', { timeout: 15000 });
            
            // Verify no 500 error occurred by checking the page doesn't show error
            const errorMessage = page.locator('text=500').first();
            const errorExists = await errorMessage.count() > 0;
            
            if (errorExists) {
                console.log('❌ SAVE FAILED: 500 error occurred during post save');
                throw new Error('Post save failed with 500 error - block validation issue persists');
            }
            
            // Additional check: verify post was actually saved
            const savedIndicator = await page.locator('.editor-post-saved-state').textContent();
            expect(savedIndicator).toContain('Saved');
            
            console.log('✅ SUCCESS: Post with AI Text block saved without errors');
            
            // Verify the block content is preserved after save
            await page.reload({ waitUntil: 'networkidle' });
            await page.waitForSelector(blockSelector, { timeout: 10000 });
            
            const preservedContent = await page.locator(`${blockSelector} .wp-content-flow-ai-generated-content`).first().textContent();
            expect(preservedContent).toContain('This is test AI generated content');
            
            console.log('✅ SUCCESS: Block content preserved after save and reload');
            
        } else {
            console.log('⚠️  AI Text Generator block not found - plugin may not be active');
            throw new Error('AI Text Generator block not found in inserter');
        }
    });
    
    test('should handle empty AI text block save without error', async ({ page }) => {
        // Navigate to new post editor
        await page.goto('/wp-admin/post-new.php');
        await page.waitForLoadState('networkidle');
        
        // Wait for Gutenberg editor to be ready
        await page.waitForSelector('.block-editor-writing-flow', { timeout: 10000 });
        
        // Add AI Text Generator block
        await page.click('.block-editor-inserter__toggle');
        await page.waitForSelector('.block-editor-inserter__search-input');
        await page.fill('.block-editor-inserter__search-input', 'AI Text Generator');
        
        // Look for the block in search results
        const aiTextBlock = page.locator('.block-editor-block-types-list__item').filter({ hasText: 'AI Text Generator' }).first();
        
        if (await aiTextBlock.count() > 0) {
            await aiTextBlock.click();
            
            // Wait for block to be added
            const blockSelector = '[data-type="wp-content-flow/ai-text"]';
            await page.waitForSelector(blockSelector);
            
            // Save the post without adding any content to the block
            await page.click('.editor-post-save-draft, .editor-post-publish-panel__toggle');
            
            // Wait for save to complete
            await page.waitForSelector('.editor-post-saved-state', { timeout: 15000 });
            
            // Verify no 500 error occurred
            const errorMessage = page.locator('text=500').first();
            const errorExists = await errorMessage.count() > 0;
            
            if (errorExists) {
                throw new Error('Empty AI Text block caused 500 error during save');
            }
            
            console.log('✅ SUCCESS: Empty AI Text block saved without errors');
            
        } else {
            console.log('⚠️  AI Text Generator block not found - plugin may not be active');
            throw new Error('AI Text Generator block not found in inserter');
        }
    });
});