<?php
/**
 * AI Operations REST API Controller
 * 
 * This controller handles AI content generation and improvement endpoints,
 * making the AI contract tests pass by implementing the OpenAPI specification.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AI Operations REST API Controller class
 */
class WP_Content_Flow_AI_Controller extends WP_REST_Controller {
    
    /**
     * Endpoint namespace
     *
     * @var string
     */
    protected $namespace = 'wp-content-flow/v1';
    
    /**
     * Route base
     *
     * @var string
     */
    protected $rest_base = 'ai';
    
    /**
     * Register the routes for AI operations
     */
    public function register_routes() {
        // POST /ai/generate - Generate AI content
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/generate', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array( $this, 'generate_content' ),
                'permission_callback' => array( $this, 'generate_content_permissions_check' ),
                'args' => $this->get_generate_endpoint_args()
            ),
            'schema' => array( $this, 'get_generate_schema' )
        ) );
        
        // POST /ai/improve - Improve existing content
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/improve', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array( $this, 'improve_content' ),
                'permission_callback' => array( $this, 'improve_content_permissions_check' ),
                'args' => $this->get_improve_endpoint_args()
            ),
            'schema' => array( $this, 'get_improve_schema' )
        ) );
    }
    
    /**
     * Check if a given request has access to generate AI content
     *
     * @param WP_REST_Request $request Full details about the request
     * @return bool|WP_Error True if the request has access, WP_Error object otherwise
     */
    public function generate_content_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_forbidden', __( 'You must be logged in to generate AI content.', 'wp-content-flow' ), array( 'status' => 401 ) );
        }
        
        if ( ! current_user_can( 'edit_posts' ) ) {
            return new WP_Error( 'rest_forbidden', __( 'You do not have permission to generate AI content.', 'wp-content-flow' ), array( 'status' => 403 ) );
        }
        
        // Check post permissions if post_id is provided
        $post_id = $request->get_param( 'post_id' );
        if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
            return new WP_Error( 'rest_forbidden', __( 'You do not have permission to edit this post.', 'wp-content-flow' ), array( 'status' => 403 ) );
        }
        
        return true;
    }
    
    /**
     * Check if a given request has access to improve content
     *
     * @param WP_REST_Request $request Full details about the request
     * @return bool|WP_Error True if the request has access, WP_Error object otherwise
     */
    public function improve_content_permissions_check( $request ) {
        return $this->generate_content_permissions_check( $request );
    }
    
    /**
     * Generate AI content
     *
     * @param WP_REST_Request $request Full details about the request
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure
     */
    public function generate_content( $request ) {
        // Validate required parameters
        $prompt = $request->get_param( 'prompt' );
        
        if ( empty( $prompt ) ) {
            return new WP_Error( 'rest_missing_callback_param', __( 'Prompt is required.', 'wp-content-flow' ), array( 'status' => 400 ) );
        }
        
        // Validate parameters constraints
        $parameters = $request->get_param( 'parameters' ) ?: array();
        $validation_result = $this->validate_ai_parameters( $parameters );
        
        if ( is_wp_error( $validation_result ) ) {
            return $validation_result;
        }
        
        // Check for workflow_id or use settings-based approach
        $workflow_id = $request->get_param( 'workflow_id' );
        
        error_log( 'WP Content Flow: AI Controller - workflow_id: ' . ( $workflow_id ?: 'none' ) );
        error_log( 'WP Content Flow: AI Controller - using path: ' . ( ! empty( $workflow_id ) ? 'workflow' : 'settings' ) );
        
        if ( ! empty( $workflow_id ) ) {
            // Workflow-based generation (existing behavior)
            $result = $this->generate_content_with_workflow( $prompt, $workflow_id, $parameters, $request->get_param( 'post_id' ) );
        } else {
            // Settings-based generation (for Gutenberg block)
            $result = $this->generate_content_with_settings( $prompt, $parameters, $request->get_param( 'post_id' ) );
        }
        
        if ( is_wp_error( $result ) ) {
            // Map internal errors to appropriate HTTP status codes
            switch ( $result->get_error_code() ) {
                case 'workflow_not_found':
                    return new WP_Error( 'rest_not_found', $result->get_error_message(), array( 'status' => 404 ) );
                case 'workflow_forbidden':
                    return new WP_Error( 'rest_forbidden', $result->get_error_message(), array( 'status' => 403 ) );
                case 'rate_limit_exceeded':
                    return new WP_Error( 'rate_limit_exceeded', $result->get_error_message(), array( 'status' => 429 ) );
                case 'no_provider_configured':
                    return new WP_Error( 'rest_no_provider', $result->get_error_message(), array( 'status' => 400 ) );
                case 'provider_not_found':
                    return new WP_Error( 'rest_provider_error', $result->get_error_message(), array( 'status' => 500 ) );
                default:
                    return new WP_Error( 'ai_generation_failed', $result->get_error_message(), array( 'status' => 500 ) );
            }
        }
        
        return rest_ensure_response( $result );
    }
    
    /**
     * Generate content using workflow
     *
     * @param string $prompt Content prompt
     * @param int $workflow_id Workflow ID
     * @param array $parameters AI parameters
     * @param int|null $post_id Optional post ID
     * @return array|WP_Error AI response or error
     */
    private function generate_content_with_workflow( $prompt, $workflow_id, $parameters = array(), $post_id = null ) {
        // Load AI core service
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/class-ai-core.php';
        
        return WP_Content_Flow_AI_Core::generate_content( $prompt, $workflow_id, $parameters, $post_id );
    }
    
    /**
     * Generate content using plugin settings
     *
     * @param string $prompt Content prompt
     * @param array $parameters AI parameters
     * @param int|null $post_id Optional post ID
     * @return array|WP_Error AI response or error
     */
    private function generate_content_with_settings( $prompt, $parameters = array(), $post_id = null ) {
        error_log( 'WP Content Flow: generate_content_with_settings called' );
        
        // Get plugin settings
        $settings = get_option( 'wp_content_flow_settings', array() );
        error_log( 'WP Content Flow: Settings keys: ' . implode( ', ', array_keys( $settings ) ) );
        
        // Check for test/demo mode (for development and testing)
        if ( defined( 'WP_CONTENT_FLOW_TEST_MODE' ) && WP_CONTENT_FLOW_TEST_MODE ) {
            error_log( 'WP Content Flow: Test mode enabled, using mock response' );
            return $this->generate_mock_response( $prompt, $parameters );
        }
        
        // Check if any AI provider is configured (check for encrypted keys)
        $has_provider = false;
        $providers = array( 'openai_api_key_encrypted', 'anthropic_api_key_encrypted', 'google_api_key_encrypted' );
        
        foreach ( $providers as $provider_key ) {
            error_log( 'WP Content Flow: Checking ' . $provider_key . ': ' . ( ! empty( $settings[ $provider_key ] ) ? 'EXISTS' : 'EMPTY' ) );
            if ( ! empty( $settings[ $provider_key ] ) ) {
                $has_provider = true;
                break;
            }
        }
        
        if ( ! $has_provider ) {
            error_log( 'WP Content Flow: No provider configured, using mock response for demo' );
            // Return a mock response for demo purposes
            return $this->generate_mock_response( $prompt, $parameters );
        }
        
        error_log( 'WP Content Flow: Provider found, continuing...' );
        
        // Get default provider from settings
        $default_provider = $settings['default_ai_provider'] ?? 'openai';
        
        // Check if the default provider has an API key (check encrypted key)
        $provider_key = $default_provider . '_api_key_encrypted';
        if ( empty( $settings[ $provider_key ] ) ) {
            // Find the first available provider
            $available_provider = null;
            foreach ( array( 'openai', 'anthropic', 'google' ) as $provider ) {
                if ( ! empty( $settings[ $provider . '_api_key_encrypted' ] ) ) {
                    $available_provider = $provider;
                    break;
                }
            }
            
            if ( ! $available_provider ) {
                return new WP_Error( 'no_provider_configured', __( 'No AI provider API keys are configured.', 'wp-content-flow' ) );
            }
            
            $default_provider = $available_provider;
        }
        
        // Rate limiting check
        if ( ! $this->check_rate_limits_from_settings( $settings ) ) {
            return new WP_Error( 'rate_limit_exceeded', __( 'Too many AI requests. Please try again later.', 'wp-content-flow' ) );
        }
        
        // Prepare AI provider parameters
        $merged_parameters = array_merge( array(
            'max_tokens' => 1000,
            'temperature' => 0.7
        ), $parameters );
        
        // Generate cache key
        $cache_key = $this->generate_cache_key_from_settings( $prompt, $merged_parameters, $default_provider );
        
        // Check cache if enabled
        if ( ! empty( $settings['cache_enabled'] ) ) {
            error_log( 'WP Content Flow: Cache enabled, checking for cached response' );
            $cached_response = $this->get_cached_response( $cache_key );
            if ( $cached_response ) {
                error_log( 'WP Content Flow: Returning cached response' );
                return $cached_response;
            }
            error_log( 'WP Content Flow: No cached response found' );
        }
        
        // Get AI provider instance
        error_log( 'WP Content Flow: Getting provider instance for: ' . $default_provider );
        $provider_instance = $this->get_provider_instance_from_settings( $default_provider, $settings );
        if ( is_wp_error( $provider_instance ) ) {
            error_log( 'WP Content Flow: Provider instance error: ' . $provider_instance->get_error_message() );
            return $provider_instance;
        }
        
        error_log( 'WP Content Flow: Provider instance created: ' . get_class( $provider_instance ) );
        
        // Generate content using provider
        $ai_response = $provider_instance->generate_content( $prompt, $merged_parameters );
        
        if ( is_wp_error( $ai_response ) ) {
            return $ai_response;
        }
        
        // Update rate limiting
        $this->update_rate_limits_from_settings();
        
        // Cache the response if enabled
        if ( ! empty( $settings['cache_enabled'] ) ) {
            $this->cache_response( $cache_key, $ai_response, $settings );
        }
        
        // Format response for API
        $formatted_response = array(
            'suggested_content' => $ai_response['content'] ?? $ai_response['suggested_content'] ?? '',
            'confidence_score' => $ai_response['confidence_score'] ?? 0.85,
            'provider_used' => $default_provider,
            'tokens_used' => $ai_response['tokens_used'] ?? 0
        );
        
        return $formatted_response;
    }
    
    /**
     * Improve existing content
     *
     * @param WP_REST_Request $request Full details about the request
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure
     */
    public function improve_content( $request ) {
        // Validate required parameters
        $content = $request->get_param( 'content' );
        
        if ( empty( $content ) ) {
            return new WP_Error( 'rest_missing_callback_param', __( 'Content is required.', 'wp-content-flow' ), array( 'status' => 400 ) );
        }
        
        $improvement_type = $request->get_param( 'improvement_type' ) ?: 'general';
        
        // Validate improvement type
        $valid_types = array( 'grammar', 'style', 'clarity', 'engagement', 'seo', 'general' );
        if ( ! in_array( $improvement_type, $valid_types, true ) ) {
            return new WP_Error( 'rest_invalid_param', sprintf( __( 'Improvement type must be one of: %s', 'wp-content-flow' ), implode( ', ', $valid_types ) ), array( 'status' => 400 ) );
        }
        
        // Check for workflow_id or use settings-based approach
        $workflow_id = $request->get_param( 'workflow_id' );
        
        if ( ! empty( $workflow_id ) ) {
            // Workflow-based improvement (existing behavior)
            $result = $this->improve_content_with_workflow( $content, $workflow_id, $improvement_type, $request->get_param( 'parameters' ) ?: array() );
        } else {
            // Settings-based improvement (for Gutenberg block)
            $result = $this->improve_content_with_settings( $content, $improvement_type, $request->get_param( 'parameters' ) ?: array() );
        }
        
        if ( is_wp_error( $result ) ) {
            // Map internal errors to appropriate HTTP status codes
            switch ( $result->get_error_code() ) {
                case 'workflow_not_found':
                    return new WP_Error( 'rest_not_found', $result->get_error_message(), array( 'status' => 404 ) );
                case 'workflow_forbidden':
                    return new WP_Error( 'rest_forbidden', $result->get_error_message(), array( 'status' => 403 ) );
                case 'rate_limit_exceeded':
                    return new WP_Error( 'rate_limit_exceeded', $result->get_error_message(), array( 'status' => 429 ) );
                case 'no_provider_configured':
                    return new WP_Error( 'rest_no_provider', $result->get_error_message(), array( 'status' => 400 ) );
                case 'provider_not_found':
                    return new WP_Error( 'rest_provider_error', $result->get_error_message(), array( 'status' => 500 ) );
                default:
                    return new WP_Error( 'ai_improvement_failed', $result->get_error_message(), array( 'status' => 500 ) );
            }
        }
        
        return rest_ensure_response( $result );
    }
    
    /**
     * Improve content using workflow
     *
     * @param string $content Content to improve
     * @param int $workflow_id Workflow ID
     * @param string $improvement_type Type of improvement
     * @param array $parameters AI parameters
     * @return array|WP_Error AI response or error
     */
    private function improve_content_with_workflow( $content, $workflow_id, $improvement_type, $parameters = array() ) {
        // Load AI core service
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/class-ai-core.php';
        
        return WP_Content_Flow_AI_Core::improve_content( $content, $workflow_id, $improvement_type, $parameters );
    }
    
    /**
     * Improve content using plugin settings
     *
     * @param string $content Content to improve
     * @param string $improvement_type Type of improvement
     * @param array $parameters AI parameters
     * @return array|WP_Error AI response or error
     */
    private function improve_content_with_settings( $content, $improvement_type, $parameters = array() ) {
        // Get plugin settings
        $settings = get_option( 'wp_content_flow_settings', array() );
        
        // Check if any AI provider is configured (check for encrypted keys)
        $has_provider = false;
        $providers = array( 'openai_api_key_encrypted', 'anthropic_api_key_encrypted', 'google_api_key_encrypted' );
        
        foreach ( $providers as $provider_key ) {
            if ( ! empty( $settings[ $provider_key ] ) ) {
                $has_provider = true;
                break;
            }
        }
        
        if ( ! $has_provider ) {
            return new WP_Error( 'no_provider_configured', __( 'No AI provider API keys are configured. Please configure at least one provider in the plugin settings.', 'wp-content-flow' ) );
        }
        
        // Get default provider from settings
        $default_provider = $settings['default_ai_provider'] ?? 'openai';
        
        // Check if the default provider has an API key (check encrypted key)
        $provider_key = $default_provider . '_api_key_encrypted';
        if ( empty( $settings[ $provider_key ] ) ) {
            // Find the first available provider
            $available_provider = null;
            foreach ( array( 'openai', 'anthropic', 'google' ) as $provider ) {
                if ( ! empty( $settings[ $provider . '_api_key_encrypted' ] ) ) {
                    $available_provider = $provider;
                    break;
                }
            }
            
            if ( ! $available_provider ) {
                return new WP_Error( 'no_provider_configured', __( 'No AI provider API keys are configured.', 'wp-content-flow' ) );
            }
            
            $default_provider = $available_provider;
        }
        
        // Rate limiting check
        if ( ! $this->check_rate_limits_from_settings( $settings ) ) {
            return new WP_Error( 'rate_limit_exceeded', __( 'Too many AI requests. Please try again later.', 'wp-content-flow' ) );
        }
        
        // Prepare AI provider parameters
        $merged_parameters = array_merge( array(
            'max_tokens' => 1000,
            'temperature' => 0.7,
            'improvement_type' => $improvement_type
        ), $parameters );
        
        // Generate cache key
        $cache_key = $this->generate_cache_key_from_settings( 'improve:' . $content, $merged_parameters, $default_provider );
        
        // Check cache if enabled
        if ( ! empty( $settings['cache_enabled'] ) ) {
            $cached_response = $this->get_cached_response( $cache_key );
            if ( $cached_response ) {
                return $cached_response;
            }
        }
        
        // Get AI provider instance
        $provider_instance = $this->get_provider_instance_from_settings( $default_provider, $settings );
        if ( is_wp_error( $provider_instance ) ) {
            return $provider_instance;
        }
        
        // Improve content using provider
        $ai_responses = $provider_instance->improve_content( $content, $improvement_type, $merged_parameters );
        
        if ( is_wp_error( $ai_responses ) ) {
            return $ai_responses;
        }
        
        // Update rate limiting
        $this->update_rate_limits_from_settings();
        
        // Cache the response if enabled
        if ( ! empty( $settings['cache_enabled'] ) ) {
            $this->cache_response( $cache_key, $ai_responses, $settings );
        }
        
        // Format response for API (improve returns array of suggestions)
        $formatted_responses = array();
        $responses_array = is_array( $ai_responses ) ? $ai_responses : array( $ai_responses );
        
        foreach ( $responses_array as $ai_response ) {
            $formatted_responses[] = array(
                'suggested_content' => $ai_response['content'] ?? $ai_response['suggested_content'] ?? '',
                'confidence_score' => $ai_response['confidence_score'] ?? 0.85,
                'improvement_type' => $improvement_type
            );
        }
        
        return $formatted_responses;
    }
    
    /**
     * Validate AI parameters
     *
     * @param array $parameters AI parameters to validate
     * @return bool|WP_Error True if valid, WP_Error otherwise
     */
    private function validate_ai_parameters( $parameters ) {
        if ( isset( $parameters['max_tokens'] ) ) {
            $max_tokens = (int) $parameters['max_tokens'];
            if ( $max_tokens < 50 || $max_tokens > 4000 ) {
                return new WP_Error( 'rest_invalid_param', __( 'max_tokens must be between 50 and 4000.', 'wp-content-flow' ), array( 'status' => 400 ) );
            }
        }
        
        if ( isset( $parameters['temperature'] ) ) {
            $temperature = (float) $parameters['temperature'];
            if ( $temperature < 0.0 || $temperature > 2.0 ) {
                return new WP_Error( 'rest_invalid_param', __( 'temperature must be between 0.0 and 2.0.', 'wp-content-flow' ), array( 'status' => 400 ) );
            }
        }
        
        return true;
    }
    
    /**
     * Get the args for the generate endpoint
     *
     * @return array Endpoint arguments
     */
    public function get_generate_endpoint_args() {
        return array(
            'prompt' => array(
                'description' => __( 'The content generation prompt.', 'wp-content-flow' ),
                'type' => 'string',
                'required' => true,
                'minLength' => 1,
                'sanitize_callback' => 'sanitize_textarea_field'
            ),
            'workflow_id' => array(
                'description' => __( 'The ID of the workflow to use. If not provided, will use plugin settings.', 'wp-content-flow' ),
                'type' => 'integer',
                'required' => false,
                'sanitize_callback' => 'absint'
            ),
            'post_id' => array(
                'description' => __( 'Optional post ID to associate with the generation.', 'wp-content-flow' ),
                'type' => 'integer',
                'sanitize_callback' => 'absint'
            ),
            'parameters' => array(
                'description' => __( 'Additional AI parameters.', 'wp-content-flow' ),
                'type' => 'object',
                'properties' => array(
                    'max_tokens' => array(
                        'type' => 'integer',
                        'minimum' => 50,
                        'maximum' => 4000
                    ),
                    'temperature' => array(
                        'type' => 'number',
                        'minimum' => 0.0,
                        'maximum' => 2.0
                    )
                )
            )
        );
    }
    
    /**
     * Get the args for the improve endpoint
     *
     * @return array Endpoint arguments
     */
    public function get_improve_endpoint_args() {
        return array(
            'content' => array(
                'description' => __( 'The content to improve.', 'wp-content-flow' ),
                'type' => 'string',
                'required' => true,
                'minLength' => 1,
                'sanitize_callback' => 'wp_kses_post'
            ),
            'workflow_id' => array(
                'description' => __( 'The ID of the workflow to use. If not provided, will use plugin settings.', 'wp-content-flow' ),
                'type' => 'integer',
                'required' => false,
                'sanitize_callback' => 'absint'
            ),
            'improvement_type' => array(
                'description' => __( 'The type of improvement to make.', 'wp-content-flow' ),
                'type' => 'string',
                'enum' => array( 'grammar', 'style', 'clarity', 'engagement', 'seo', 'general' ),
                'default' => 'general',
                'sanitize_callback' => 'sanitize_key'
            )
        );
    }
    
    /**
     * Get the schema for generate endpoint responses
     *
     * @return array Schema data
     */
    public function get_generate_schema() {
        return array(
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title' => 'ai_suggestion',
            'type' => 'object',
            'properties' => array(
                'id' => array(
                    'description' => __( 'Unique identifier for the suggestion.', 'wp-content-flow' ),
                    'type' => 'integer'
                ),
                'post_id' => array(
                    'description' => __( 'Associated post ID.', 'wp-content-flow' ),
                    'type' => 'integer'
                ),
                'workflow_id' => array(
                    'description' => __( 'Associated workflow ID.', 'wp-content-flow' ),
                    'type' => 'integer'
                ),
                'original_content' => array(
                    'description' => __( 'Original content before AI processing.', 'wp-content-flow' ),
                    'type' => 'string'
                ),
                'suggested_content' => array(
                    'description' => __( 'AI-generated suggested content.', 'wp-content-flow' ),
                    'type' => 'string'
                ),
                'suggestion_type' => array(
                    'description' => __( 'Type of AI suggestion.', 'wp-content-flow' ),
                    'type' => 'string',
                    'enum' => array( 'generation', 'improvement', 'correction' )
                ),
                'status' => array(
                    'description' => __( 'Current status of the suggestion.', 'wp-content-flow' ),
                    'type' => 'string',
                    'enum' => array( 'pending', 'accepted', 'rejected', 'modified' )
                ),
                'confidence_score' => array(
                    'description' => __( 'AI confidence score for the suggestion.', 'wp-content-flow' ),
                    'type' => 'number',
                    'minimum' => 0.0,
                    'maximum' => 1.0
                ),
                'created_at' => array(
                    'description' => __( 'When the suggestion was created.', 'wp-content-flow' ),
                    'type' => 'string',
                    'format' => 'date-time'
                )
            )
        );
    }
    
    /**
     * Get the schema for improve endpoint responses
     *
     * @return array Schema data
     */
    public function get_improve_schema() {
        return array(
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title' => 'improvement_suggestions',
            'type' => 'array',
            'items' => $this->get_generate_schema()
        );
    }
    
    /**
     * Check rate limits from settings
     *
     * @param array $settings Plugin settings
     * @return bool True if within limits, false otherwise
     */
    private function check_rate_limits_from_settings( $settings ) {
        if ( empty( $settings['requests_per_minute'] ) ) {
            return true; // Rate limiting disabled
        }
        
        $user_id = get_current_user_id();
        $current_time = time();
        $rate_limit_key = 'wp_content_flow_rate_limit_' . $user_id;
        
        // Get current rate limit data
        $rate_data = get_transient( $rate_limit_key );
        if ( ! $rate_data ) {
            $rate_data = array(
                'minute' => array( 'count' => 0, 'reset_time' => $current_time + 60 ),
                'requests' => array()
            );
        }
        
        // Clean old requests (older than 1 minute)
        $rate_data['requests'] = array_filter( $rate_data['requests'], function( $timestamp ) use ( $current_time ) {
            return $current_time - $timestamp < 60;
        } );
        
        // Check if we've exceeded the limit
        $requests_per_minute = (int) $settings['requests_per_minute'];
        if ( count( $rate_data['requests'] ) >= $requests_per_minute ) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Update rate limits from settings
     */
    private function update_rate_limits_from_settings() {
        $user_id = get_current_user_id();
        $current_time = time();
        $rate_limit_key = 'wp_content_flow_rate_limit_' . $user_id;
        
        // Get current rate limit data
        $rate_data = get_transient( $rate_limit_key );
        if ( ! $rate_data ) {
            $rate_data = array(
                'minute' => array( 'count' => 0, 'reset_time' => $current_time + 60 ),
                'requests' => array()
            );
        }
        
        // Add current request timestamp
        $rate_data['requests'][] = $current_time;
        
        // Clean old requests
        $rate_data['requests'] = array_filter( $rate_data['requests'], function( $timestamp ) use ( $current_time ) {
            return $current_time - $timestamp < 60;
        } );
        
        // Save for 2 minutes to be safe
        set_transient( $rate_limit_key, $rate_data, 120 );
    }
    
    /**
     * Generate cache key from settings
     *
     * @param string $prompt The prompt or content
     * @param array $parameters AI parameters
     * @param string $provider Provider name
     * @return string Cache key
     */
    private function generate_cache_key_from_settings( $prompt, $parameters, $provider ) {
        $key_data = array(
            'prompt' => substr( md5( $prompt ), 0, 8 ),
            'parameters' => md5( serialize( $parameters ) ),
            'provider' => $provider
        );
        
        return 'wp_content_flow_ai_' . md5( serialize( $key_data ) );
    }
    
    /**
     * Get cached response
     *
     * @param string $cache_key Cache key
     * @return array|null Cached response or null
     */
    private function get_cached_response( $cache_key ) {
        return get_transient( $cache_key );
    }
    
    /**
     * Cache response
     *
     * @param string $cache_key Cache key
     * @param mixed $response Response to cache
     * @param array $settings Plugin settings
     */
    private function cache_response( $cache_key, $response, $settings ) {
        $cache_duration = $settings['cache_duration'] ?? 1800; // 30 minutes default
        set_transient( $cache_key, $response, $cache_duration );
    }
    
    /**
     * Get AI provider instance from settings
     *
     * @param string $provider_name Provider name
     * @param array $settings Plugin settings
     * @return object|WP_Error Provider instance or error
     */
    private function get_provider_instance_from_settings( $provider_name, $settings ) {
        // Check that the provider has an encrypted API key
        $encrypted_key = $settings[ $provider_name . '_api_key_encrypted' ] ?? '';
        if ( empty( $encrypted_key ) ) {
            return new WP_Error( 'provider_not_configured', sprintf( __( '%s API key is not configured.', 'wp-content-flow' ), ucfirst( $provider_name ) ) );
        }
        
        // Create the appropriate provider instance
        switch ( $provider_name ) {
            case 'openai':
                require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/providers/class-openai-provider.php';
                $provider_instance = new WP_Content_Flow_OpenAI_Provider();
                break;
                
            case 'anthropic':
                require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/providers/class-anthropic-provider.php';
                $provider_instance = new WP_Content_Flow_Anthropic_Provider();
                break;
                
            case 'google':
                require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/providers/class-google-ai-provider.php';
                $provider_instance = new WP_Content_Flow_Google_AI_Provider();
                break;
                
            default:
                return new WP_Error( 'provider_not_found', sprintf( __( 'Provider %s not found.', 'wp-content-flow' ), $provider_name ) );
        }
        
        return $provider_instance;
    }
    
    /**
     * Generate mock response for testing/demo purposes
     *
     * @param string $prompt The prompt text
     * @param array $parameters AI parameters
     * @return array Mock AI response
     */
    private function generate_mock_response( $prompt, $parameters = array() ) {
        // Generate mock content based on the prompt
        $mock_content = $this->create_mock_content( $prompt );
        
        // Return formatted response
        return array(
            'suggested_content' => $mock_content,
            'confidence_score' => 0.92,
            'provider_used' => 'mock',
            'tokens_used' => strlen( $prompt ) + strlen( $mock_content ),
            'is_mock' => true,
            'message' => __( 'This is demo content. Configure AI providers for real content generation.', 'wp-content-flow' )
        );
    }
    
    /**
     * Create mock content based on prompt
     *
     * @param string $prompt The prompt text
     * @return string Mock content
     */
    private function create_mock_content( $prompt ) {
        // Extract key topic from prompt
        $prompt_lower = strtolower( $prompt );
        
        // Default mock response
        $default_content = "<h2>AI-Generated Content Demo</h2>\n\n" .
            "<p>This is a demonstration of AI-generated content based on your prompt: \"" . esc_html( $prompt ) . "\"</p>\n\n" .
            "<p>In a production environment with configured AI providers, this would be replaced with actual AI-generated content tailored to your specific request. The AI would analyze your prompt and generate relevant, contextual content.</p>\n\n" .
            "<h3>Key Features</h3>\n" .
            "<ul>\n" .
            "  <li>Contextual content generation based on your prompts</li>\n" .
            "  <li>Multiple AI provider support (OpenAI, Anthropic, Google AI)</li>\n" .
            "  <li>Customizable workflows for different content types</li>\n" .
            "  <li>Content improvement and optimization capabilities</li>\n" .
            "</ul>\n\n" .
            "<p>To enable real AI content generation, please configure your API keys in the plugin settings.</p>";
        
        // Customize based on common keywords
        if ( strpos( $prompt_lower, 'artificial intelligence' ) !== false || strpos( $prompt_lower, 'ai' ) !== false ) {
            return "<h2>Understanding Artificial Intelligence</h2>\n\n" .
                "<p>Artificial Intelligence (AI) represents one of the most transformative technologies of our time. It encompasses machine learning, natural language processing, and advanced algorithms that enable computers to perform tasks that typically require human intelligence.</p>\n\n" .
                "<h3>Benefits of AI</h3>\n" .
                "<p>AI technology offers numerous advantages across various industries:</p>\n" .
                "<ul>\n" .
                "  <li><strong>Automation:</strong> Streamlines repetitive tasks and improves efficiency</li>\n" .
                "  <li><strong>Data Analysis:</strong> Processes vast amounts of information quickly</li>\n" .
                "  <li><strong>Personalization:</strong> Delivers customized experiences and recommendations</li>\n" .
                "  <li><strong>Innovation:</strong> Enables new solutions to complex problems</li>\n" .
                "</ul>\n\n" .
                "<h3>Applications</h3>\n" .
                "<p>From healthcare diagnostics to autonomous vehicles, AI is reshaping how we work, learn, and interact with technology. Content generation, like this example, represents just one of many practical applications.</p>\n\n" .
                "<p><em>Note: This is demo content. Configure AI providers for authentic AI-generated content.</em></p>";
        } elseif ( strpos( $prompt_lower, 'blog' ) !== false || strpos( $prompt_lower, 'post' ) !== false ) {
            return "<h2>Creating Engaging Blog Content</h2>\n\n" .
                "<p>Writing compelling blog posts requires a blend of creativity, research, and understanding your audience. Based on your prompt about \"" . esc_html( $prompt ) . "\", here's a structured approach to content creation.</p>\n\n" .
                "<h3>Essential Elements</h3>\n" .
                "<ul>\n" .
                "  <li>Captivating headlines that grab attention</li>\n" .
                "  <li>Clear, concise introductions</li>\n" .
                "  <li>Well-organized body content with subheadings</li>\n" .
                "  <li>Actionable insights and takeaways</li>\n" .
                "</ul>\n\n" .
                "<p>Remember to optimize for SEO while maintaining readability and value for your readers.</p>\n\n" .
                "<p><em>Note: This is demo content. Configure AI providers for authentic AI-generated content.</em></p>";
        } elseif ( strpos( $prompt_lower, 'product' ) !== false ) {
            return "<h2>Product Description</h2>\n\n" .
                "<p>This innovative solution addresses your needs with cutting-edge features and exceptional quality. Designed with user experience in mind, it delivers outstanding performance and reliability.</p>\n\n" .
                "<h3>Key Features</h3>\n" .
                "<ul>\n" .
                "  <li>Premium quality construction</li>\n" .
                "  <li>User-friendly interface</li>\n" .
                "  <li>Advanced functionality</li>\n" .
                "  <li>Exceptional value</li>\n" .
                "</ul>\n\n" .
                "<p>Transform your experience with this remarkable product that combines innovation with practicality.</p>\n\n" .
                "<p><em>Note: This is demo content. Configure AI providers for authentic AI-generated content.</em></p>";
        }
        
        return $default_content;
    }
}