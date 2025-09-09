/**
 * WordPress AI Content Flow - Admin JavaScript
 * 
 * Handles admin interface interactions and AJAX requests
 */

(function($) {
    'use strict';
    
    // Initialize admin functionality when document is ready
    $(document).ready(function() {
        initializeAdmin();
    });
    
    /**
     * Initialize admin functionality
     */
    function initializeAdmin() {
        // Handle test connection buttons
        $('.test-connection').on('click', handleTestConnection);
        
        // Handle workflow execution
        $('.execute-workflow').on('click', handleWorkflowExecution);
        
        // Handle suggestion application
        $('.apply-suggestion').on('click', handleApplySuggestion);
        
        console.log('WP Content Flow admin initialized');
    }
    
    /**
     * Handle AI provider connection testing
     */
    function handleTestConnection() {
        var $button = $(this);
        var provider = $button.data('provider');
        var $status = $('#' + provider + '-status');
        
        $button.prop('disabled', true).text('Testing...');
        $status.html('<span class="testing">Testing connection...</span>');
        
        $.ajax({
            url: wpContentFlow.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wp_content_flow_test_connection',
                provider: provider,
                nonce: wpContentFlow.nonce
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<span class="success" style="color: green;">✓ Connected successfully</span>');
                } else {
                    $status.html('<span class="error" style="color: red;">✗ Connection failed: ' + response.data + '</span>');
                }
            },
            error: function(xhr, status, error) {
                $status.html('<span class="error" style="color: red;">✗ Connection error: ' + error + '</span>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Test ' + provider.charAt(0).toUpperCase() + provider.slice(1) + ' Connection');
            }
        });
    }
    
    /**
     * Handle workflow execution
     */
    function handleWorkflowExecution() {
        var $button = $(this);
        var workflowId = $button.data('workflow-id');
        var $status = $('.workflow-status[data-workflow-id="' + workflowId + '"]');
        
        $button.prop('disabled', true).text('Executing...');
        $status.text('Running...');
        
        $.ajax({
            url: wpContentFlow.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wp_content_flow_execute_workflow',
                workflow_id: workflowId,
                nonce: wpContentFlow.nonce
            },
            success: function(response) {
                if (response.success) {
                    $status.text('Completed successfully');
                    $button.text('Execute Again');
                } else {
                    $status.text('Failed: ' + response.data);
                    $button.text('Retry Execution');
                }
            },
            error: function(xhr, status, error) {
                $status.text('Error: ' + error);
                $button.text('Retry Execution');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    }
    
    /**
     * Handle suggestion application
     */
    function handleApplySuggestion() {
        var $button = $(this);
        var suggestionId = $button.data('suggestion-id');
        
        if (!confirm('Are you sure you want to apply this suggestion?')) {
            return;
        }
        
        $button.prop('disabled', true).text('Applying...');
        
        $.ajax({
            url: wpContentFlow.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wp_content_flow_apply_suggestion',
                suggestion_id: suggestionId,
                nonce: wpContentFlow.nonce
            },
            success: function(response) {
                if (response.success) {
                    $button.closest('.suggestion-item').addClass('applied');
                    $button.text('Applied').prop('disabled', true);
                    
                    // Show success message
                    showNotice('Suggestion applied successfully!', 'success');
                } else {
                    $button.text('Apply Suggestion').prop('disabled', false);
                    showNotice('Failed to apply suggestion: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                $button.text('Apply Suggestion').prop('disabled', false);
                showNotice('Error applying suggestion: ' + error, 'error');
            }
        });
    }
    
    /**
     * Show admin notice
     */
    function showNotice(message, type) {
        type = type || 'info';
        
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after($notice);
        
        // Auto-remove after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    /**
     * Initialize content generation in block editor
     */
    function initializeBlockEditor() {
        // This will be called when the block editor loads
        if (typeof wp !== 'undefined' && wp.data) {
            // Register block editor integration
            wp.data.subscribe(function() {
                // Monitor for AI Text Generator block selection
                var selectedBlock = wp.data.select('core/block-editor').getSelectedBlock();
                
                if (selectedBlock && selectedBlock.name === 'wp-content-flow/ai-text-generator') {
                    // Initialize AI text generation functionality
                    initializeAITextGenerator(selectedBlock);
                }
            });
        }
    }
    
    /**
     * Initialize AI Text Generator block functionality
     */
    function initializeAITextGenerator(block) {
        // This would handle AI text generation within the block
        console.log('AI Text Generator block selected:', block);
        
        // Add event listeners for generation triggers
        $(document).on('click', '.wp-content-flow-generate-btn', function() {
            var $button = $(this);
            var prompt = $button.closest('.wp-content-flow-block').find('.prompt-input').val();
            
            generateAIContent(prompt, function(content) {
                // Update block content
                wp.data.dispatch('core/block-editor').updateBlock(block.clientId, {
                    attributes: {
                        ...block.attributes,
                        generatedContent: content
                    }
                });
            });
        });
    }
    
    /**
     * Generate AI content
     */
    function generateAIContent(prompt, callback) {
        if (!prompt || prompt.trim() === '') {
            showNotice('Please enter a prompt for content generation.', 'warning');
            return;
        }
        
        $.ajax({
            url: wpContentFlow.restUrl + 'generate',
            type: 'POST',
            headers: {
                'X-WP-Nonce': wpContentFlow.restNonce
            },
            data: JSON.stringify({
                prompt: prompt,
                max_tokens: 1500,
                temperature: 0.7
            }),
            contentType: 'application/json',
            success: function(response) {
                if (response.content) {
                    callback(response.content);
                    showNotice('Content generated successfully!', 'success');
                } else {
                    showNotice('No content was generated. Please try again.', 'warning');
                }
            },
            error: function(xhr, status, error) {
                showNotice('Error generating content: ' + error, 'error');
            }
        });
    }
    
    // Initialize block editor functionality if we're in the editor
    if (window.location.pathname.includes('post.php') || window.location.pathname.includes('post-new.php')) {
        $(window).on('load', initializeBlockEditor);
    }
    
})(jQuery);