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
        $workflow_id = $request->get_param( 'workflow_id' );
        
        if ( empty( $prompt ) ) {
            return new WP_Error( 'rest_missing_callback_param', __( 'Prompt is required.', 'wp-content-flow' ), array( 'status' => 400 ) );
        }
        
        if ( empty( $workflow_id ) ) {
            return new WP_Error( 'rest_missing_callback_param', __( 'Workflow ID is required.', 'wp-content-flow' ), array( 'status' => 400 ) );
        }
        
        // Validate parameters constraints
        $parameters = $request->get_param( 'parameters' ) ?: array();
        $validation_result = $this->validate_ai_parameters( $parameters );
        
        if ( is_wp_error( $validation_result ) ) {
            return $validation_result;
        }
        
        // Load AI core service
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/class-ai-core.php';
        
        // Generate content
        $result = WP_Content_Flow_AI_Core::generate_content(
            $prompt,
            $workflow_id,
            $parameters,
            $request->get_param( 'post_id' )
        );
        
        if ( is_wp_error( $result ) ) {
            // Map internal errors to appropriate HTTP status codes
            switch ( $result->get_error_code() ) {
                case 'workflow_not_found':
                    return new WP_Error( 'rest_not_found', $result->get_error_message(), array( 'status' => 404 ) );
                case 'workflow_forbidden':
                    return new WP_Error( 'rest_forbidden', $result->get_error_message(), array( 'status' => 403 ) );
                case 'rate_limit_exceeded':
                    return new WP_Error( 'rate_limit_exceeded', $result->get_error_message(), array( 'status' => 429 ) );
                default:
                    return new WP_Error( 'ai_generation_failed', $result->get_error_message(), array( 'status' => 500 ) );
            }
        }
        
        return rest_ensure_response( $result );
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
        $workflow_id = $request->get_param( 'workflow_id' );
        
        if ( empty( $content ) ) {
            return new WP_Error( 'rest_missing_callback_param', __( 'Content is required.', 'wp-content-flow' ), array( 'status' => 400 ) );
        }
        
        if ( empty( $workflow_id ) ) {
            return new WP_Error( 'rest_missing_callback_param', __( 'Workflow ID is required.', 'wp-content-flow' ), array( 'status' => 400 ) );
        }
        
        $improvement_type = $request->get_param( 'improvement_type' ) ?: 'general';
        
        // Validate improvement type
        $valid_types = array( 'grammar', 'style', 'clarity', 'engagement', 'seo' );
        if ( ! in_array( $improvement_type, $valid_types, true ) ) {
            return new WP_Error( 'rest_invalid_param', sprintf( __( 'Improvement type must be one of: %s', 'wp-content-flow' ), implode( ', ', $valid_types ) ), array( 'status' => 400 ) );
        }
        
        // Load AI core service
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/class-ai-core.php';
        
        // Improve content
        $result = WP_Content_Flow_AI_Core::improve_content(
            $content,
            $workflow_id,
            $improvement_type,
            $request->get_param( 'parameters' ) ?: array()
        );
        
        if ( is_wp_error( $result ) ) {
            // Map internal errors to appropriate HTTP status codes
            switch ( $result->get_error_code() ) {
                case 'workflow_not_found':
                    return new WP_Error( 'rest_not_found', $result->get_error_message(), array( 'status' => 404 ) );
                case 'workflow_forbidden':
                    return new WP_Error( 'rest_forbidden', $result->get_error_message(), array( 'status' => 403 ) );
                case 'rate_limit_exceeded':
                    return new WP_Error( 'rate_limit_exceeded', $result->get_error_message(), array( 'status' => 429 ) );
                default:
                    return new WP_Error( 'ai_improvement_failed', $result->get_error_message(), array( 'status' => 500 ) );
            }
        }
        
        return rest_ensure_response( $result );
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
                'description' => __( 'The ID of the workflow to use.', 'wp-content-flow' ),
                'type' => 'integer',
                'required' => true,
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
                'description' => __( 'The ID of the workflow to use.', 'wp-content-flow' ),
                'type' => 'integer',
                'required' => true,
                'sanitize_callback' => 'absint'
            ),
            'improvement_type' => array(
                'description' => __( 'The type of improvement to make.', 'wp-content-flow' ),
                'type' => 'string',
                'enum' => array( 'grammar', 'style', 'clarity', 'engagement', 'seo' ),
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
}