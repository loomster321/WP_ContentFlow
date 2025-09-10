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
        
        // Handle settings form submission debugging
        initializeSettingsDebugging();
        
        console.log('WP Content Flow admin initialized');
    }
    
    /**
     * Initialize settings form debugging
     */
    function initializeSettingsDebugging() {
        console.log('Initializing WP Content Flow settings debugging...');
        
        // Enhanced settings form handling with specific form ID
        $(document).on('submit', '#wp-content-flow-settings-form', function(e) {
            console.log('WP Content Flow Settings form submission detected');
            
            var $form = $(this);
            var formData = $form.serializeArray();
            console.log('Settings form data:', formData);
            
            // Validate at least one API key is provided
            var hasApiKey = false;
            var apiKeyFields = [
                'wp_content_flow_settings[openai_api_key]',
                'wp_content_flow_settings[anthropic_api_key]',
                'wp_content_flow_settings[google_api_key]'
            ];
            
            formData.forEach(function(field) {
                if (apiKeyFields.includes(field.name) && field.value.trim() !== '') {
                    hasApiKey = true;
                    console.log('Found API key for:', field.name);
                }
            });
            
            // Check required security fields
            var optionPage = $form.find('input[name="option_page"]').val();
            var nonce = $form.find('input[name="_wpnonce"]').val();
            
            console.log('Form validation:', {
                optionPage: optionPage,
                noncePresent: !!nonce,
                hasApiKey: hasApiKey,
                formAction: $form.attr('action'),
                formMethod: $form.attr('method')
            });
            
            if (!optionPage || !nonce) {
                console.error('Missing required security fields');
                showNotice('Form security validation failed. Please refresh and try again.', 'error');
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            var $submitBtn = $('#wp-content-flow-submit-btn');
            if ($submitBtn.length === 0) {
                $submitBtn = $form.find('input[type="submit"]');
            }
            
            if ($submitBtn.length > 0) {
                var originalText = $submitBtn.val();
                $submitBtn.val('Saving Settings...').prop('disabled', true);
                
                // Re-enable after delay as safety measure
                setTimeout(function() {
                    $submitBtn.val(originalText).prop('disabled', false);
                }, 15000);
            }
            
            console.log('Form validation passed, submitting...');
            return true;
        });
        
        // Generic form debugging (fallback for other forms)
        $('form').on('submit', function(e) {
            var $form = $(this);
            
            // Only handle forms with option_page that aren't our main form
            if ($form.find('input[name="option_page"]').length > 0 && !$form.is('#wp-content-flow-settings-form')) {
                console.log('Generic WordPress settings form submission detected');
                
                var optionPage = $form.find('input[name="option_page"]').val();
                console.log('Generic form option_page:', optionPage);
                
                if (optionPage && optionPage.includes('wp_content_flow')) {
                    console.log('This appears to be a WP Content Flow form without proper ID');
                    var formData = $form.serializeArray();
                    console.log('Generic WP Content Flow form data:', formData);
                }
            }
        });
        
        // Enhanced message detection
        setTimeout(function() {
            checkForAdminMessages();
        }, 500);
        
        // Monitor URL changes for settings updates
        if (window.location.search.includes('settings-updated=true') || 
            window.location.search.includes('wp-content-flow-settings')) {
            console.log('Settings page loaded with potential update');
            setTimeout(checkForAdminMessages, 1000);
        }
    }
    
    /**
     * Check for WordPress admin messages
     */
    function checkForAdminMessages() {
        console.log('Checking for admin messages...');
        
        var $allNotices = $('.notice, .updated, .error, .settings-error');
        console.log('Total admin notices found:', $allNotices.length);
        
        var $successNotices = $('.notice-success, .updated, .settings-error-settings_updated');
        var $errorNotices = $('.notice-error, .error');
        var $warningNotices = $('.notice-warning');
        
        if ($successNotices.length > 0) {
            console.log('✅ Success notices found:', $successNotices.length);
            $successNotices.each(function(index) {
                var text = $(this).text().trim();
                console.log(`Success notice ${index + 1}:`, text);
            });
        }
        
        if ($errorNotices.length > 0) {
            console.log('❌ Error notices found:', $errorNotices.length);
            $errorNotices.each(function(index) {
                var text = $(this).text().trim();
                console.log(`Error notice ${index + 1}:`, text);
            });
        }
        
        if ($warningNotices.length > 0) {
            console.log('⚠️ Warning notices found:', $warningNotices.length);
            $warningNotices.each(function(index) {
                var text = $(this).text().trim();
                console.log(`Warning notice ${index + 1}:`, text);
            });
        }
        
        if ($allNotices.length === 0) {
            console.log('No admin notices found on the page');
        }
        
        // Look for specific WP Content Flow messages
        var $wpContentFlowNotices = $allNotices.filter(function() {
            return $(this).text().toLowerCase().includes('content flow') || 
                   $(this).text().toLowerCase().includes('settings saved');
        });
        
        if ($wpContentFlowNotices.length > 0) {
            console.log('🎯 WP Content Flow specific notices found:', $wpContentFlowNotices.length);
            $wpContentFlowNotices.each(function(index) {
                console.log(`WP Content Flow notice ${index + 1}:`, $(this).text().trim());
            });
        }
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