<?php
/**
 * WordPress AI Content Flow - WordPress Hooks and Filters Contract
 * 
 * This file defines the WordPress action and filter hooks that the plugin
 * provides for extensibility and integration.
 */

// =============================================================================
// ACTION HOOKS
// =============================================================================

/**
 * Fired when the AI Content Flow plugin is activated
 * 
 * @since 1.0.0
 * @param bool $network_wide Whether the plugin is being network-activated
 */
do_action('wp_content_flow_activated', $network_wide);

/**
 * Fired when the AI Content Flow plugin is deactivated
 * 
 * @since 1.0.0
 * @param bool $network_wide Whether the plugin is being network-deactivated
 */
do_action('wp_content_flow_deactivated', $network_wide);

/**
 * Fired when AI content is successfully generated
 * 
 * @since 1.0.0
 * @param array $suggestion {
 *     Generated AI suggestion data
 *     @type int    $id                Suggestion ID
 *     @type int    $post_id           Target post ID
 *     @type int    $workflow_id       Workflow used for generation
 *     @type string $original_content  Original content (if improving)
 *     @type string $suggested_content AI-generated content
 *     @type string $suggestion_type   'generation' | 'improvement' | 'correction'
 *     @type float  $confidence_score  AI confidence score (0.0-1.0)
 * }
 * @param array $request {
 *     Original generation request
 *     @type string $prompt      User prompt
 *     @type int    $workflow_id Workflow ID
 *     @type array  $parameters  AI parameters used
 * }
 */
do_action('wp_content_flow_content_generated', $suggestion, $request);

/**
 * Fired when user accepts an AI suggestion
 * 
 * @since 1.0.0
 * @param int $suggestion_id Accepted suggestion ID
 * @param int $user_id       User who accepted the suggestion
 * @param int $post_id       Target post ID
 */
do_action('wp_content_flow_suggestion_accepted', $suggestion_id, $user_id, $post_id);

/**
 * Fired when user rejects an AI suggestion
 * 
 * @since 1.0.0
 * @param int $suggestion_id Rejected suggestion ID
 * @param int $user_id       User who rejected the suggestion
 * @param int $post_id       Target post ID
 */
do_action('wp_content_flow_suggestion_rejected', $suggestion_id, $user_id, $post_id);

/**
 * Fired when an AI workflow is executed
 * 
 * @since 1.0.0
 * @param int   $workflow_id Executed workflow ID
 * @param array $context {
 *     Execution context
 *     @type int    $user_id   User executing the workflow
 *     @type int    $post_id   Target post (if applicable)
 *     @type string $operation Operation type ('generate', 'improve', 'correct')
 *     @type array  $settings  Workflow settings used
 * }
 */
do_action('wp_content_flow_workflow_executed', $workflow_id, $context);

/**
 * Fired before making an AI API request
 * 
 * @since 1.0.0
 * @param string $provider    AI provider ('openai', 'anthropic', 'google', 'azure')
 * @param array  $request_data Request payload
 * @param int    $user_id     User making the request
 */
do_action('wp_content_flow_before_ai_request', $provider, $request_data, $user_id);

/**
 * Fired after receiving an AI API response
 * 
 * @since 1.0.0
 * @param string $provider     AI provider
 * @param array  $response     API response data
 * @param array  $request_data Original request
 * @param float  $elapsed_time Request duration in seconds
 */
do_action('wp_content_flow_after_ai_response', $provider, $response, $request_data, $elapsed_time);

/**
 * Fired when content history is recorded
 * 
 * @since 1.0.0
 * @param int    $history_id   Content history record ID
 * @param int    $post_id      Post that was modified
 * @param string $change_type  'ai_generated' | 'ai_improved' | 'manual_edit' | 'ai_rejected'
 * @param int    $user_id      User who made the change
 */
do_action('wp_content_flow_content_history_recorded', $history_id, $post_id, $change_type, $user_id);

// =============================================================================
// FILTER HOOKS
// =============================================================================

/**
 * Filter AI-generated content before saving to database
 * 
 * @since 1.0.0
 * @param string $content Generated content
 * @param array  $context {
 *     Generation context
 *     @type int    $workflow_id Workflow used
 *     @type string $provider    AI provider
 *     @type int    $user_id     User who generated content
 *     @type int    $post_id     Target post ID
 * }
 * @return string Filtered content
 */
apply_filters('wp_content_flow_filter_generated_content', $content, $context);

/**
 * Filter available AI providers
 * 
 * @since 1.0.0
 * @param array $providers {
 *     Default AI providers
 *     @type array $openai {
 *         @type string $name        'OpenAI'
 *         @type string $api_url     API endpoint
 *         @type array  $models      Available models
 *         @type bool   $enabled     Whether provider is enabled
 *     }
 * }
 * @return array Filtered providers
 */
apply_filters('wp_content_flow_ai_providers', $providers);

/**
 * Filter workflow settings before execution
 * 
 * @since 1.0.0
 * @param array $settings {
 *     Workflow settings
 *     @type string $ai_provider    Provider to use
 *     @type string $model         AI model
 *     @type float  $temperature   Creativity setting (0.0-2.0)
 *     @type int    $max_tokens    Maximum response tokens
 *     @type array  $prompts       System and user prompts
 * }
 * @param int   $workflow_id Workflow ID being executed
 * @param int   $user_id     User executing the workflow
 * @return array Filtered settings
 */
apply_filters('wp_content_flow_workflow_settings', $settings, $workflow_id, $user_id);

/**
 * Filter user capabilities required for AI operations
 * 
 * @since 1.0.0
 * @param array $capabilities {
 *     Required capabilities for different operations
 *     @type string $generate_content    'edit_posts'
 *     @type string $manage_workflows    'edit_posts'
 *     @type string $access_ai_history   'edit_posts'
 *     @type string $manage_ai_settings  'manage_options'
 * }
 * @return array Filtered capabilities
 */
apply_filters('wp_content_flow_required_capabilities', $capabilities);

/**
 * Filter AI API request parameters before sending
 * 
 * @since 1.0.0
 * @param array  $parameters {
 *     API request parameters
 *     @type string $model         AI model to use
 *     @type array  $messages      Request messages
 *     @type float  $temperature   Creativity setting
 *     @type int    $max_tokens    Maximum response tokens
 *     @type array  $tools         Available tools/functions
 * }
 * @param string $provider    AI provider ('openai', 'anthropic', etc.)
 * @param int    $workflow_id Workflow being executed
 * @return array Filtered parameters
 */
apply_filters('wp_content_flow_api_request_parameters', $parameters, $provider, $workflow_id);

/**
 * Filter content suggestions before presenting to user
 * 
 * @since 1.0.0
 * @param array $suggestions Array of suggestion objects
 * @param array $context {
 *     Suggestion context
 *     @type int    $post_id     Target post ID
 *     @type string $operation   'generate' | 'improve' | 'correct'
 *     @type int    $workflow_id Workflow used
 * }
 * @return array Filtered suggestions
 */
apply_filters('wp_content_flow_content_suggestions', $suggestions, $context);

/**
 * Filter block editor AI toolbar settings
 * 
 * @since 1.0.0
 * @param array $settings {
 *     Toolbar settings
 *     @type bool  $show_confidence_scores Show AI confidence ratings
 *     @type array $enabled_tools          Enabled AI tools
 *     @type int   $max_suggestions        Maximum suggestions to show
 *     @type bool  $auto_apply_high_confidence Auto-apply high-confidence suggestions
 * }
 * @param int   $user_id Current user ID
 * @return array Filtered settings
 */
apply_filters('wp_content_flow_editor_toolbar_settings', $settings, $user_id);

/**
 * Filter database table names used by the plugin
 * 
 * @since 1.0.0
 * @param array $table_names {
 *     Plugin database table names
 *     @type string $workflows           'wp_ai_workflows'
 *     @type string $suggestions         'wp_ai_suggestions'  
 *     @type string $workflow_templates  'wp_workflow_templates'
 *     @type string $content_history     'wp_ai_content_history'
 * }
 * @return array Filtered table names
 */
apply_filters('wp_content_flow_table_names', $table_names);

/**
 * Filter caching duration for different data types
 * 
 * @since 1.0.0
 * @param array $cache_durations {
 *     Cache durations in seconds
 *     @type int $ai_responses      1800 (30 minutes)
 *     @type int $workflows         86400 (24 hours)
 *     @type int $user_preferences  3600 (1 hour)
 *     @type int $content_history   7200 (2 hours)
 * }
 * @return array Filtered cache durations
 */
apply_filters('wp_content_flow_cache_durations', $cache_durations);

/**
 * Filter rate limiting settings
 * 
 * @since 1.0.0
 * @param array $rate_limits {
 *     Rate limiting configuration
 *     @type int $requests_per_minute  Per-user requests per minute
 *     @type int $requests_per_hour    Per-user requests per hour
 *     @type int $requests_per_day     Per-user requests per day
 *     @type int $daily_token_limit    Maximum tokens per day per user
 * }
 * @param int   $user_id User ID for rate limiting
 * @return array Filtered rate limits
 */
apply_filters('wp_content_flow_rate_limits', $rate_limits, $user_id);

// =============================================================================
// WORDPRESS INTEGRATION HOOKS
// =============================================================================

/**
 * Add AI workflow meta boxes to post edit screens
 * 
 * @since 1.0.0
 */
add_action('add_meta_boxes', 'wp_content_flow_add_meta_boxes');

/**
 * Save AI workflow data when post is saved
 * 
 * @since 1.0.0
 */
add_action('save_post', 'wp_content_flow_save_post_ai_data');

/**
 * Register AI-related REST API endpoints
 * 
 * @since 1.0.0
 */
add_action('rest_api_init', 'wp_content_flow_register_rest_routes');

/**
 * Enqueue block editor scripts and styles
 * 
 * @since 1.0.0
 */
add_action('enqueue_block_editor_assets', 'wp_content_flow_enqueue_block_editor_assets');

/**
 * Register custom Gutenberg blocks
 * 
 * @since 1.0.0
 */
add_action('init', 'wp_content_flow_register_blocks');

/**
 * Add plugin settings page to WordPress admin
 * 
 * @since 1.0.0
 */
add_action('admin_menu', 'wp_content_flow_add_admin_menu');

/**
 * Handle plugin activation tasks
 * 
 * @since 1.0.0
 */
register_activation_hook(__FILE__, 'wp_content_flow_activate');

/**
 * Handle plugin deactivation tasks
 * 
 * @since 1.0.0
 */
register_deactivation_hook(__FILE__, 'wp_content_flow_deactivate');

/**
 * Handle plugin uninstall tasks
 * 
 * @since 1.0.0
 */
register_uninstall_hook(__FILE__, 'wp_content_flow_uninstall');
?>