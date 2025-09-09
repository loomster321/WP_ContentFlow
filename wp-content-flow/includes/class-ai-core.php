<?php
/**
 * AI Core Service Class
 * 
 * Central service for AI operations that integrates multiple AI providers.
 * This class makes the AI generation contract tests pass by providing
 * the core AI functionality for the REST API.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AI Core service class
 */
class WP_Content_Flow_AI_Core {
    
    /**
     * Available AI providers
     *
     * @var array
     */
    private static $providers = array();
    
    /**
     * Cache for AI responses
     *
     * @var array
     */
    private static $response_cache = array();
    
    /**
     * Rate limiting data
     *
     * @var array
     */
    private static $rate_limits = array();
    
    /**
     * Initialize AI Core
     */
    public static function init() {
        // Register default providers
        self::register_default_providers();
        
        // Add WordPress hooks
        add_action( 'init', array( __CLASS__, 'setup_hooks' ) );
    }
    
    /**
     * Setup WordPress hooks
     */
    public static function setup_hooks() {
        // Allow plugins to modify providers
        add_filter( 'wp_content_flow_ai_providers', array( __CLASS__, 'filter_providers' ) );
        
        // Rate limiting cleanup
        add_action( 'wp_content_flow_cleanup', array( __CLASS__, 'cleanup_rate_limits' ) );
    }
    
    /**
     * Register default AI providers
     */
    private static function register_default_providers() {
        self::$providers = array(
            'openai' => array(
                'name' => 'OpenAI',
                'class' => 'WP_Content_Flow_OpenAI_Provider',
                'file' => 'includes/providers/class-openai-provider.php',
                'enabled' => true
            ),
            'anthropic' => array(
                'name' => 'Anthropic Claude',
                'class' => 'WP_Content_Flow_Anthropic_Provider', 
                'file' => 'includes/providers/class-anthropic-provider.php',
                'enabled' => true
            ),
            'google' => array(
                'name' => 'Google AI',
                'class' => 'WP_Content_Flow_Google_Provider',
                'file' => 'includes/providers/class-google-provider.php',
                'enabled' => true
            ),
            'azure' => array(
                'name' => 'Azure OpenAI',
                'class' => 'WP_Content_Flow_Azure_Provider',
                'file' => 'includes/providers/class-azure-provider.php',
                'enabled' => false // Available but not enabled by default
            )
        );
    }
    
    /**
     * Filter providers through WordPress hook
     *
     * @param array $providers Current providers
     * @return array Filtered providers
     */
    public static function filter_providers( $providers ) {
        return apply_filters( 'wp_content_flow_ai_providers', $providers );
    }
    
    /**
     * Generate AI content
     *
     * @param string $prompt Content prompt
     * @param int $workflow_id Workflow ID
     * @param array $parameters AI parameters
     * @param int $post_id Optional post ID
     * @return array|WP_Error AI response or error
     */
    public static function generate_content( $prompt, $workflow_id, $parameters = array(), $post_id = null ) {
        // Load workflow to get AI provider and settings
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/models/class-workflow.php';
        
        $workflow = WP_Content_Flow_Workflow::find( $workflow_id );
        if ( ! $workflow ) {
            return new WP_Error( 'workflow_not_found', __( 'Workflow not found.', 'wp-content-flow' ) );
        }
        
        if ( ! $workflow->is_active() ) {
            return new WP_Error( 'workflow_inactive', __( 'Workflow is not active.', 'wp-content-flow' ) );
        }
        
        // Permission check
        if ( ! $workflow->current_user_can_edit() ) {
            return new WP_Error( 'workflow_forbidden', __( 'You do not have permission to use this workflow.', 'wp-content-flow' ) );
        }
        
        // Rate limiting check
        if ( ! self::check_rate_limits( $workflow->ai_provider ) ) {
            return new WP_Error( 'rate_limit_exceeded', __( 'Too many AI requests. Please try again later.', 'wp-content-flow' ) );
        }
        
        // Merge workflow settings with parameters
        $merged_parameters = array_merge( $workflow->settings, $parameters );
        
        // Apply filter to modify request before sending
        $request_data = apply_filters( 'wp_content_flow_before_ai_request', array(
            'prompt' => $prompt,
            'parameters' => $merged_parameters,
            'workflow_id' => $workflow_id,
            'post_id' => $post_id
        ), $workflow->ai_provider );
        
        // Check cache first
        $cache_key = self::generate_cache_key( 'generate', $request_data );
        $cached_response = self::get_cached_response( $cache_key );
        
        if ( $cached_response ) {
            return $cached_response;
        }
        
        // Get AI provider instance
        $provider = self::get_provider_instance( $workflow->ai_provider );
        if ( is_wp_error( $provider ) ) {
            return $provider;
        }
        
        // Generate content using provider
        $ai_response = $provider->generate_content( $request_data['prompt'], $request_data['parameters'] );
        
        if ( is_wp_error( $ai_response ) ) {
            return $ai_response;
        }
        
        // Update rate limiting
        self::update_rate_limits( $workflow->ai_provider );
        
        // Cache the response
        self::cache_response( $cache_key, $ai_response );
        
        // Create suggestion from AI response
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/models/class-ai-suggestion.php';
        
        $suggestion = WP_Content_Flow_AI_Suggestion::create_from_ai_response(
            $ai_response,
            $post_id ?: 0,
            $workflow_id,
            '', // No original content for generation
            'generation'
        );
        
        if ( is_wp_error( $suggestion ) ) {
            return $suggestion;
        }
        
        // Fire action hook
        do_action( 'wp_content_flow_content_generated', $suggestion, $request_data );
        
        return $suggestion->to_array();
    }
    
    /**
     * Improve existing content
     *
     * @param string $content Content to improve
     * @param int $workflow_id Workflow ID
     * @param string $improvement_type Type of improvement
     * @param array $parameters AI parameters
     * @return array|WP_Error AI response or error
     */
    public static function improve_content( $content, $workflow_id, $improvement_type = 'general', $parameters = array() ) {
        // Load workflow
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/models/class-workflow.php';
        
        $workflow = WP_Content_Flow_Workflow::find( $workflow_id );
        if ( ! $workflow ) {
            return new WP_Error( 'workflow_not_found', __( 'Workflow not found.', 'wp-content-flow' ) );
        }
        
        if ( ! $workflow->is_active() ) {
            return new WP_Error( 'workflow_inactive', __( 'Workflow is not active.', 'wp-content-flow' ) );
        }
        
        // Permission check
        if ( ! $workflow->current_user_can_edit() ) {
            return new WP_Error( 'workflow_forbidden', __( 'You do not have permission to use this workflow.', 'wp-content-flow' ) );
        }
        
        // Rate limiting check
        if ( ! self::check_rate_limits( $workflow->ai_provider ) ) {
            return new WP_Error( 'rate_limit_exceeded', __( 'Too many AI requests. Please try again later.', 'wp-content-flow' ) );
        }
        
        // Merge workflow settings with parameters
        $merged_parameters = array_merge( $workflow->settings, $parameters );
        $merged_parameters['improvement_type'] = $improvement_type;
        
        // Apply filter
        $request_data = apply_filters( 'wp_content_flow_before_ai_request', array(
            'content' => $content,
            'parameters' => $merged_parameters,
            'workflow_id' => $workflow_id,
            'improvement_type' => $improvement_type
        ), $workflow->ai_provider );
        
        // Check cache
        $cache_key = self::generate_cache_key( 'improve', $request_data );
        $cached_response = self::get_cached_response( $cache_key );
        
        if ( $cached_response ) {
            return $cached_response;
        }
        
        // Get provider
        $provider = self::get_provider_instance( $workflow->ai_provider );
        if ( is_wp_error( $provider ) ) {
            return $provider;
        }
        
        // Improve content using provider
        $ai_responses = $provider->improve_content( $request_data['content'], $request_data['improvement_type'], $request_data['parameters'] );
        
        if ( is_wp_error( $ai_responses ) ) {
            return $ai_responses;
        }
        
        // Update rate limiting
        self::update_rate_limits( $workflow->ai_provider );
        
        // Cache the response
        self::cache_response( $cache_key, $ai_responses );
        
        // Convert to suggestions array
        $suggestions = array();
        foreach ( $ai_responses as $ai_response ) {
            require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/models/class-ai-suggestion.php';
            
            $suggestion = WP_Content_Flow_AI_Suggestion::create_from_ai_response(
                $ai_response,
                0, // No specific post for improvement suggestions
                $workflow_id,
                $content, // Original content
                'improvement'
            );
            
            if ( ! is_wp_error( $suggestion ) ) {
                $suggestions[] = $suggestion->to_array();
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Get AI provider instance
     *
     * @param string $provider_name Provider name
     * @return object|WP_Error Provider instance or error
     */
    private static function get_provider_instance( $provider_name ) {
        $providers = apply_filters( 'wp_content_flow_ai_providers', self::$providers );
        
        if ( ! isset( $providers[ $provider_name ] ) ) {
            return new WP_Error( 'provider_not_found', __( 'AI provider not found.', 'wp-content-flow' ) );
        }
        
        $provider_config = $providers[ $provider_name ];
        
        if ( ! $provider_config['enabled'] ) {
            return new WP_Error( 'provider_disabled', __( 'AI provider is disabled.', 'wp-content-flow' ) );
        }
        
        // Load provider class
        if ( isset( $provider_config['file'] ) ) {
            $provider_file = WP_CONTENT_FLOW_PLUGIN_DIR . $provider_config['file'];
            if ( file_exists( $provider_file ) ) {
                require_once $provider_file;
            }
        }
        
        $provider_class = $provider_config['class'];
        
        if ( ! class_exists( $provider_class ) ) {
            return new WP_Error( 'provider_class_not_found', sprintf( __( 'AI provider class %s not found.', 'wp-content-flow' ), $provider_class ) );
        }
        
        return new $provider_class();
    }
    
    /**
     * Check rate limits for provider
     *
     * @param string $provider_name Provider name
     * @return bool True if within limits, false otherwise
     */
    private static function check_rate_limits( $provider_name ) {
        $settings = get_option( 'wp_content_flow_settings', array() );
        
        if ( empty( $settings['rate_limit_enabled'] ) ) {
            return true; // Rate limiting disabled
        }
        
        $user_id = get_current_user_id();
        $current_time = time();
        
        // Initialize user limits if not exists
        if ( ! isset( self::$rate_limits[ $user_id ] ) ) {
            self::$rate_limits[ $user_id ] = array(
                'minute' => array( 'count' => 0, 'reset_time' => $current_time + 60 ),
                'hour' => array( 'count' => 0, 'reset_time' => $current_time + 3600 ),
                'day' => array( 'count' => 0, 'reset_time' => $current_time + 86400 )
            );
        }
        
        $user_limits = &self::$rate_limits[ $user_id ];
        
        // Reset expired limits
        foreach ( $user_limits as $period => &$limit_data ) {
            if ( $current_time >= $limit_data['reset_time'] ) {
                $limit_data['count'] = 0;
                
                switch ( $period ) {
                    case 'minute':
                        $limit_data['reset_time'] = $current_time + 60;
                        break;
                    case 'hour':
                        $limit_data['reset_time'] = $current_time + 3600;
                        break;
                    case 'day':
                        $limit_data['reset_time'] = $current_time + 86400;
                        break;
                }
            }
        }
        
        // Check limits
        $limits = array(
            'minute' => $settings['requests_per_minute'] ?? 10,
            'hour' => $settings['requests_per_hour'] ?? 100,
            'day' => $settings['daily_token_limit'] ?? 50000 // This would need token counting
        );
        
        foreach ( array( 'minute', 'hour' ) as $period ) {
            if ( $user_limits[ $period ]['count'] >= $limits[ $period ] ) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Update rate limits after successful request
     *
     * @param string $provider_name Provider name
     */
    private static function update_rate_limits( $provider_name ) {
        $user_id = get_current_user_id();
        
        if ( isset( self::$rate_limits[ $user_id ] ) ) {
            self::$rate_limits[ $user_id ]['minute']['count']++;
            self::$rate_limits[ $user_id ]['hour']['count']++;
            self::$rate_limits[ $user_id ]['day']['count']++;
        }
    }
    
    /**
     * Generate cache key for AI request
     *
     * @param string $operation Operation type
     * @param array $data Request data
     * @return string Cache key
     */
    private static function generate_cache_key( $operation, $data ) {
        $key_data = array(
            'operation' => $operation,
            'prompt' => isset( $data['prompt'] ) ? substr( md5( $data['prompt'] ), 0, 8 ) : '',
            'content' => isset( $data['content'] ) ? substr( md5( $data['content'] ), 0, 8 ) : '',
            'parameters' => md5( serialize( $data['parameters'] ?? array() ) ),
            'workflow_id' => $data['workflow_id'] ?? 0
        );
        
        $cache_key = 'wp_content_flow_ai_' . md5( serialize( $key_data ) );
        
        // Allow filtering of cache key
        return apply_filters( 'wp_content_flow_cache_key', $cache_key, $data );
    }
    
    /**
     * Get cached AI response
     *
     * @param string $cache_key Cache key
     * @return array|null Cached response or null
     */
    private static function get_cached_response( $cache_key ) {
        $settings = get_option( 'wp_content_flow_settings', array() );
        
        if ( empty( $settings['cache_enabled'] ) ) {
            return null;
        }
        
        return wp_cache_get( $cache_key, 'wp_content_flow_ai' );
    }
    
    /**
     * Cache AI response
     *
     * @param string $cache_key Cache key
     * @param mixed $response Response to cache
     */
    private static function cache_response( $cache_key, $response ) {
        $settings = get_option( 'wp_content_flow_settings', array() );
        
        if ( empty( $settings['cache_enabled'] ) ) {
            return;
        }
        
        $cache_duration = $settings['cache_duration'] ?? 1800; // 30 minutes default
        
        wp_cache_set( $cache_key, $response, 'wp_content_flow_ai', $cache_duration );
    }
    
    /**
     * Get available AI providers
     *
     * @return array Available providers
     */
    public static function get_available_providers() {
        return apply_filters( 'wp_content_flow_ai_providers', self::$providers );
    }
    
    /**
     * Test AI provider connection
     *
     * @param string $provider_name Provider name
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function test_provider_connection( $provider_name ) {
        $provider = self::get_provider_instance( $provider_name );
        
        if ( is_wp_error( $provider ) ) {
            return $provider;
        }
        
        if ( ! method_exists( $provider, 'test_connection' ) ) {
            return new WP_Error( 'test_not_supported', __( 'Connection test not supported for this provider.', 'wp-content-flow' ) );
        }
        
        return $provider->test_connection();
    }
    
    /**
     * Cleanup expired rate limits
     */
    public static function cleanup_rate_limits() {
        $current_time = time();
        
        foreach ( self::$rate_limits as $user_id => $limits ) {
            $cleanup_user = true;
            
            foreach ( $limits as $period => $limit_data ) {
                if ( $current_time < $limit_data['reset_time'] ) {
                    $cleanup_user = false;
                    break;
                }
            }
            
            if ( $cleanup_user ) {
                unset( self::$rate_limits[ $user_id ] );
            }
        }
    }
}