<?php
/**
 * WordPress AI Content Flow - Hooks and Filters
 *
 * Manages WordPress hooks, filters, and integration points.
 * Provides extensibility for other plugins and themes.
 *
 * @package WP_Content_Flow
 * @subpackage Integration
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Hooks and Filters Class
 */
class WP_Content_Flow_Hooks_Filters {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_content_hooks();
        $this->init_admin_hooks();
        $this->init_api_hooks();
        $this->init_block_editor_hooks();
        $this->init_user_hooks();
        $this->init_performance_hooks();
        $this->init_security_hooks();
    }
    
    /**
     * Initialize content-related hooks
     */
    private function init_content_hooks() {
        // Content generation hooks
        add_filter( 'wp_content_flow_pre_generate_content', array( $this, 'pre_generate_content_filter' ), 10, 3 );
        add_action( 'wp_content_flow_content_generated', array( $this, 'post_generate_content_action' ), 10, 4 );
        add_filter( 'wp_content_flow_generated_content', array( $this, 'filter_generated_content' ), 10, 3 );
        
        // Content improvement hooks
        add_filter( 'wp_content_flow_pre_improve_content', array( $this, 'pre_improve_content_filter' ), 10, 4 );
        add_action( 'wp_content_flow_content_improved', array( $this, 'post_improve_content_action' ), 10, 5 );
        add_filter( 'wp_content_flow_improved_content', array( $this, 'filter_improved_content' ), 10, 4 );
        
        // Content validation hooks
        add_filter( 'wp_content_flow_validate_content', array( $this, 'validate_generated_content' ), 10, 2 );
        add_filter( 'wp_content_flow_sanitize_content', array( $this, 'sanitize_generated_content' ), 10, 2 );
        
        // Auto-save integration
        add_action( 'wp_content_flow_content_approved', array( $this, 'maybe_auto_save_content' ), 10, 3 );
        
        // Content logging
        add_action( 'wp_content_flow_content_generated', array( $this, 'log_content_generation' ), 20, 4 );
        add_action( 'wp_content_flow_content_improved', array( $this, 'log_content_improvement' ), 20, 5 );
    }
    
    /**
     * Initialize admin-related hooks
     */
    private function init_admin_hooks() {
        // Plugin settings hooks
        add_filter( 'wp_content_flow_default_settings', array( $this, 'filter_default_settings' ) );
        add_action( 'wp_content_flow_settings_updated', array( $this, 'settings_updated_action' ), 10, 2 );
        add_filter( 'wp_content_flow_sanitize_settings', array( $this, 'sanitize_settings_filter' ), 10, 1 );
        
        // Admin notices hooks
        add_action( 'wp_content_flow_add_admin_notice', array( $this, 'add_admin_notice_action' ), 10, 4 );
        
        // Menu and page hooks
        add_filter( 'wp_content_flow_admin_menu_pages', array( $this, 'filter_admin_menu_pages' ) );
        add_action( 'wp_content_flow_admin_page_loaded', array( $this, 'admin_page_loaded_action' ), 10, 1 );
    }
    
    /**
     * Initialize API-related hooks
     */
    private function init_api_hooks() {
        // REST API hooks
        add_filter( 'wp_content_flow_rest_endpoints', array( $this, 'filter_rest_endpoints' ) );
        add_action( 'wp_content_flow_rest_api_init', array( $this, 'rest_api_init_action' ) );
        add_filter( 'wp_content_flow_rest_authentication', array( $this, 'rest_authentication_filter' ), 10, 2 );
        
        // AI provider hooks
        add_filter( 'wp_content_flow_ai_providers', array( $this, 'filter_ai_providers' ) );
        add_action( 'wp_content_flow_ai_provider_registered', array( $this, 'ai_provider_registered_action' ), 10, 2 );
        add_filter( 'wp_content_flow_ai_request_params', array( $this, 'filter_ai_request_params' ), 10, 3 );
        add_filter( 'wp_content_flow_ai_response', array( $this, 'filter_ai_response' ), 10, 3 );
        
        // Error handling hooks
        add_action( 'wp_content_flow_api_error', array( $this, 'log_api_error' ), 10, 3 );
        add_filter( 'wp_content_flow_error_response', array( $this, 'filter_error_response' ), 10, 2 );
    }
    
    /**
     * Initialize block editor hooks
     */
    private function init_block_editor_hooks() {
        // Block registration hooks
        add_filter( 'wp_content_flow_block_types', array( $this, 'filter_block_types' ) );
        add_action( 'wp_content_flow_block_registered', array( $this, 'block_registered_action' ), 10, 2 );
        
        // Block editor assets hooks
        add_filter( 'wp_content_flow_editor_assets', array( $this, 'filter_editor_assets' ) );
        add_action( 'wp_content_flow_enqueue_editor_assets', array( $this, 'enqueue_editor_assets_action' ) );
        
        // Block rendering hooks
        add_filter( 'wp_content_flow_render_block', array( $this, 'filter_block_render' ), 10, 3 );
        add_action( 'wp_content_flow_block_rendered', array( $this, 'block_rendered_action' ), 10, 3 );
    }
    
    /**
     * Initialize user-related hooks
     */
    private function init_user_hooks() {
        // User capability hooks
        add_filter( 'wp_content_flow_user_can_generate', array( $this, 'filter_user_can_generate' ), 10, 3 );
        add_filter( 'wp_content_flow_user_can_improve', array( $this, 'filter_user_can_improve' ), 10, 3 );
        add_action( 'wp_content_flow_capability_checked', array( $this, 'capability_checked_action' ), 10, 4 );
        
        // User preference hooks
        add_filter( 'wp_content_flow_user_preferences', array( $this, 'filter_user_preferences' ), 10, 2 );
        add_action( 'wp_content_flow_user_preference_updated', array( $this, 'user_preference_updated_action' ), 10, 3 );
        
        // Rate limiting hooks
        add_filter( 'wp_content_flow_rate_limit_exceeded', array( $this, 'rate_limit_exceeded_filter' ), 10, 3 );
        add_action( 'wp_content_flow_rate_limit_hit', array( $this, 'rate_limit_hit_action' ), 10, 3 );
    }
    
    /**
     * Initialize performance-related hooks
     */
    private function init_performance_hooks() {
        // Caching hooks
        add_filter( 'wp_content_flow_cache_key', array( $this, 'filter_cache_key' ), 10, 2 );
        add_action( 'wp_content_flow_cache_set', array( $this, 'cache_set_action' ), 10, 4 );
        add_action( 'wp_content_flow_cache_hit', array( $this, 'cache_hit_action' ), 10, 2 );
        add_action( 'wp_content_flow_cache_miss', array( $this, 'cache_miss_action' ), 10, 2 );
        add_action( 'wp_content_flow_cache_cleared', array( $this, 'cache_cleared_action' ), 10, 1 );
        
        // Performance monitoring hooks
        add_action( 'wp_content_flow_performance_start', array( $this, 'performance_start_action' ), 10, 2 );
        add_action( 'wp_content_flow_performance_end', array( $this, 'performance_end_action' ), 10, 3 );
        
        // Database optimization hooks
        add_action( 'wp_content_flow_cleanup_started', array( $this, 'cleanup_started_action' ) );
        add_action( 'wp_content_flow_cleanup_completed', array( $this, 'cleanup_completed_action' ), 10, 1 );
    }
    
    /**
     * Initialize security-related hooks
     */
    private function init_security_hooks() {
        // Authentication hooks
        add_filter( 'wp_content_flow_authenticate_request', array( $this, 'authenticate_request_filter' ), 10, 2 );
        add_action( 'wp_content_flow_authentication_failed', array( $this, 'authentication_failed_action' ), 10, 2 );
        
        // Content security hooks
        add_filter( 'wp_content_flow_content_safe', array( $this, 'validate_content_safety' ), 10, 2 );
        add_action( 'wp_content_flow_unsafe_content_detected', array( $this, 'unsafe_content_detected_action' ), 10, 2 );
        
        // Data privacy hooks
        add_action( 'wp_content_flow_data_export_request', array( $this, 'data_export_request_action' ), 10, 2 );
        add_action( 'wp_content_flow_data_deletion_request', array( $this, 'data_deletion_request_action' ), 10, 2 );
    }
    
    /**
     * Pre-generate content filter
     */
    public function pre_generate_content_filter( $should_generate, $prompt, $workflow_id ) {
        // Allow other plugins to prevent content generation
        if ( ! $should_generate ) {
            return false;
        }
        
        // Check for blacklisted terms in prompt
        $blacklisted_terms = apply_filters( 'wp_content_flow_blacklisted_terms', array() );
        
        foreach ( $blacklisted_terms as $term ) {
            if ( stripos( $prompt, $term ) !== false ) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Post content generation action
     */
    public function post_generate_content_action( $content, $prompt, $workflow_id, $metadata ) {
        // Update usage statistics
        $this->update_usage_stats( 'generate', $workflow_id );
        
        // Trigger third-party integrations
        do_action( 'wp_content_flow_after_generation', $content, $prompt, $workflow_id, $metadata );
    }
    
    /**
     * Filter generated content
     */
    public function filter_generated_content( $content, $prompt, $workflow_id ) {
        // Apply content transformations
        $content = $this->apply_content_transformations( $content );
        
        // Apply formatting
        $content = $this->apply_content_formatting( $content );
        
        return $content;
    }
    
    /**
     * Validate generated content
     */
    public function validate_generated_content( $is_valid, $content ) {
        // Check content length
        if ( strlen( $content ) < 10 ) {
            return false;
        }
        
        // Check for required elements based on content type
        $content_requirements = apply_filters( 'wp_content_flow_content_requirements', array() );
        
        foreach ( $content_requirements as $requirement ) {
            if ( ! $this->check_content_requirement( $content, $requirement ) ) {
                return false;
            }
        }
        
        return $is_valid;
    }
    
    /**
     * Sanitize generated content
     */
    public function sanitize_generated_content( $content, $context ) {
        // Remove potentially harmful content
        $content = wp_kses_post( $content );
        
        // Remove tracking scripts and external resources
        $content = preg_replace( '/<script[^>]*>.*?<\/script>/si', '', $content );
        $content = preg_replace( '/<iframe[^>]*>.*?<\/iframe>/si', '', $content );
        
        // Sanitize URLs
        $content = preg_replace_callback(
            '/href=["\']([^"\']*)["\']/',
            function( $matches ) {
                return 'href="' . esc_url( $matches[1] ) . '"';
            },
            $content
        );
        
        return $content;
    }
    
    /**
     * Maybe auto-save content
     */
    public function maybe_auto_save_content( $content, $post_id, $user_id ) {
        $settings = get_option( 'wp_content_flow_settings', array() );
        
        if ( empty( $settings['auto_save_enabled'] ) ) {
            return;
        }
        
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        
        // Create revision before saving
        wp_save_post_revision( $post_id );
        
        // Update post content
        wp_update_post( array(
            'ID' => $post_id,
            'post_content' => $content
        ) );
        
        do_action( 'wp_content_flow_content_auto_saved', $post_id, $content, $user_id );
    }
    
    /**
     * Log content generation
     */
    public function log_content_generation( $content, $prompt, $workflow_id, $metadata ) {
        if ( ! $this->should_log_activity() ) {
            return;
        }
        
        $log_entry = array(
            'action' => 'content_generated',
            'user_id' => get_current_user_id(),
            'workflow_id' => $workflow_id,
            'prompt_length' => strlen( $prompt ),
            'content_length' => strlen( $content ),
            'timestamp' => current_time( 'mysql' ),
            'metadata' => $metadata
        );
        
        $this->write_activity_log( $log_entry );
    }
    
    /**
     * Filter default settings
     */
    public function filter_default_settings( $defaults ) {
        // Allow other plugins to modify default settings
        return $defaults;
    }
    
    /**
     * Settings updated action
     */
    public function settings_updated_action( $old_settings, $new_settings ) {
        // Clear caches when settings change
        $this->clear_related_caches( $old_settings, $new_settings );
        
        // Notify other plugins of settings change
        do_action( 'wp_content_flow_after_settings_update', $old_settings, $new_settings );
    }
    
    /**
     * Log API errors
     */
    public function log_api_error( $error, $endpoint, $context ) {
        if ( ! $this->should_log_errors() ) {
            return;
        }
        
        $error_log = array(
            'error' => $error,
            'endpoint' => $endpoint,
            'context' => $context,
            'user_id' => get_current_user_id(),
            'timestamp' => current_time( 'mysql' ),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        );
        
        error_log( '[WP Content Flow API Error] ' . wp_json_encode( $error_log ) );
    }
    
    /**
     * Filter error response
     */
    public function filter_error_response( $response, $error ) {
        // Customize error messages for different contexts
        if ( is_admin() ) {
            // More detailed errors for admin users
            return $response;
        }
        
        // Generic errors for frontend users
        if ( isset( $response['message'] ) ) {
            $response['message'] = __( 'An error occurred while processing your request.', 'wp-content-flow' );
        }
        
        return $response;
    }
    
    /**
     * Rate limit exceeded filter
     */
    public function rate_limit_exceeded_filter( $exceeded, $user_id, $operation ) {
        // Allow bypassing rate limits for certain users
        if ( user_can( $user_id, 'manage_options' ) ) {
            return false;
        }
        
        // Check for premium users or special roles
        $bypass_roles = apply_filters( 'wp_content_flow_rate_limit_bypass_roles', array() );
        $user = get_user_by( 'ID', $user_id );
        
        if ( $user ) {
            foreach ( $bypass_roles as $role ) {
                if ( in_array( $role, $user->roles ) ) {
                    return false;
                }
            }
        }
        
        return $exceeded;
    }
    
    /**
     * Rate limit hit action
     */
    public function rate_limit_hit_action( $user_id, $operation, $limit ) {
        // Log rate limit hits
        $this->log_rate_limit_hit( $user_id, $operation, $limit );
        
        // Send notification to admin if configured
        $this->maybe_notify_admin_rate_limit( $user_id, $operation );
    }
    
    /**
     * Update usage statistics
     */
    private function update_usage_stats( $operation, $workflow_id ) {
        $stats_key = 'wp_content_flow_usage_stats';
        $stats = get_option( $stats_key, array() );
        
        $today = current_time( 'Y-m-d' );
        
        if ( ! isset( $stats[ $today ] ) ) {
            $stats[ $today ] = array();
        }
        
        if ( ! isset( $stats[ $today ][ $operation ] ) ) {
            $stats[ $today ][ $operation ] = 0;
        }
        
        $stats[ $today ][ $operation ]++;
        
        update_option( $stats_key, $stats );
    }
    
    /**
     * Apply content transformations
     */
    private function apply_content_transformations( $content ) {
        // Apply custom transformations from settings
        $transformations = apply_filters( 'wp_content_flow_content_transformations', array() );
        
        foreach ( $transformations as $transformation ) {
            $content = call_user_func( $transformation, $content );
        }
        
        return $content;
    }
    
    /**
     * Apply content formatting
     */
    private function apply_content_formatting( $content ) {
        // Auto-format content based on type
        $content = wpautop( $content );
        
        // Apply shortcode processing if enabled
        if ( apply_filters( 'wp_content_flow_process_shortcodes', true ) ) {
            $content = do_shortcode( $content );
        }
        
        return $content;
    }
    
    /**
     * Check if we should log activity
     */
    private function should_log_activity() {
        $settings = get_option( 'wp_content_flow_settings', array() );
        return ! empty( $settings['content_logging'] );
    }
    
    /**
     * Check if we should log errors
     */
    private function should_log_errors() {
        return defined( 'WP_DEBUG' ) && WP_DEBUG;
    }
    
    /**
     * Write activity log
     */
    private function write_activity_log( $log_entry ) {
        // Store in database or file as configured
        do_action( 'wp_content_flow_write_log', $log_entry );
    }
    
    /**
     * Clear related caches when settings change
     */
    private function clear_related_caches( $old_settings, $new_settings ) {
        // Clear workflow cache if AI provider changed
        if ( $old_settings['ai_provider'] !== $new_settings['ai_provider'] ) {
            delete_transient( 'wp_content_flow_workflows' );
        }
        
        // Clear all caches if cache settings changed
        if ( $old_settings['cache_enabled'] !== $new_settings['cache_enabled'] ) {
            wp_cache_flush();
        }
    }
    
    /**
     * Log rate limit hit
     */
    private function log_rate_limit_hit( $user_id, $operation, $limit ) {
        $log_entry = array(
            'user_id' => $user_id,
            'operation' => $operation,
            'limit' => $limit,
            'timestamp' => current_time( 'mysql' ),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        );
        
        error_log( '[WP Content Flow Rate Limit] ' . wp_json_encode( $log_entry ) );
    }
    
    /**
     * Maybe notify admin of rate limit hits
     */
    private function maybe_notify_admin_rate_limit( $user_id, $operation ) {
        // Only notify for repeated violations
        $violation_key = 'wp_content_flow_rate_violations_' . $user_id;
        $violations = get_transient( $violation_key );
        
        if ( false === $violations ) {
            $violations = 1;
        } else {
            $violations++;
        }
        
        set_transient( $violation_key, $violations, HOUR_IN_SECONDS );
        
        if ( $violations >= 5 ) {
            // Send admin notification
            do_action( 'wp_content_flow_repeated_rate_limit_violations', $user_id, $operation, $violations );
        }
    }
    
    /**
     * Get all available hooks
     */
    public static function get_available_hooks() {
        return array(
            'content' => array(
                'wp_content_flow_pre_generate_content',
                'wp_content_flow_content_generated',
                'wp_content_flow_generated_content',
                'wp_content_flow_pre_improve_content',
                'wp_content_flow_content_improved',
                'wp_content_flow_improved_content'
            ),
            'admin' => array(
                'wp_content_flow_settings_updated',
                'wp_content_flow_admin_page_loaded',
                'wp_content_flow_add_admin_notice'
            ),
            'api' => array(
                'wp_content_flow_rest_api_init',
                'wp_content_flow_ai_provider_registered',
                'wp_content_flow_api_error'
            ),
            'performance' => array(
                'wp_content_flow_cache_set',
                'wp_content_flow_cache_hit',
                'wp_content_flow_cache_miss'
            ),
            'security' => array(
                'wp_content_flow_authenticate_request',
                'wp_content_flow_authentication_failed',
                'wp_content_flow_unsafe_content_detected'
            )
        );
    }
}