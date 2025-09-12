<?php
/**
 * REST API Main Class
 * 
 * This class initializes and manages all REST API controllers for the plugin.
 * It registers all endpoints and ensures proper integration with WordPress.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST API main class
 */
class WP_Content_Flow_REST_API {
    
    /**
     * API version
     *
     * @var string
     */
    private $version = 'v1';
    
    /**
     * API namespace
     *
     * @var string
     */
    private $namespace = 'wp-content-flow/v1';
    
    /**
     * Registered controllers
     *
     * @var array
     */
    private $controllers = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        error_log('WP Content Flow REST API: Constructor called');
        $this->init_hooks();
        error_log('WP Content Flow REST API: Hooks initialized');
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        error_log('WP Content Flow REST API: Adding rest_api_init hook');
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ), 10 );
        add_action( 'rest_api_init', array( $this, 'add_cors_headers' ), 10 );
        
        // Add custom REST API error handling
        add_filter( 'rest_request_before_callbacks', array( $this, 'validate_rest_request' ), 10, 3 );
    }
    
    /**
     * Register all REST routes
     */
    public function register_rest_routes() {
        error_log('WP Content Flow REST API: register_rest_routes called');
        
        // Load and register controllers
        $this->load_controllers();
        $this->register_controllers();
        
        // Register custom endpoints
        $this->register_custom_endpoints();
        
        // Register a test endpoint to verify API is working
        register_rest_route( $this->namespace, '/test', array(
            'methods' => 'GET',
            'callback' => array( $this, 'test_endpoint' ),
            'permission_callback' => '__return_true'
        ) );
        
        error_log('WP Content Flow REST API: Routes registered for namespace: ' . $this->namespace);
    }
    
    /**
     * Load all REST API controllers
     */
    private function load_controllers() {
        $controllers = array(
            'workflows' => 'includes/api/class-workflows-controller.php',
            'ai' => 'includes/api/class-ai-controller.php',
            'suggestions' => 'includes/api/class-suggestions-controller.php',
            'history' => 'includes/api/class-history-controller.php',
            'settings' => 'includes/api/class-settings-controller.php'
        );
        
        foreach ( $controllers as $controller_name => $controller_file ) {
            $file_path = WP_CONTENT_FLOW_PLUGIN_DIR . $controller_file;
            
            if ( file_exists( $file_path ) ) {
                require_once $file_path;
                
                // Create controller class name
                $controller_class = 'WP_Content_Flow_' . ucfirst( $controller_name ) . '_Controller';
                
                if ( class_exists( $controller_class ) ) {
                    $this->controllers[ $controller_name ] = new $controller_class();
                }
            }
        }
    }
    
    /**
     * Register all loaded controllers
     */
    private function register_controllers() {
        foreach ( $this->controllers as $controller ) {
            if ( method_exists( $controller, 'register_routes' ) ) {
                $controller->register_routes();
            }
        }
    }
    
    /**
     * Register custom endpoints that don't fit into controller classes
     */
    private function register_custom_endpoints() {
        // GET /posts/{post_id}/history - Content history endpoint
        register_rest_route( $this->namespace, '/posts/(?P<post_id>[\d]+)/history', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array( $this, 'get_post_history' ),
                'permission_callback' => array( $this, 'get_post_history_permissions_check' ),
                'args' => array(
                    'post_id' => array(
                        'description' => __( 'Post ID to get history for.', 'wp-content-flow' ),
                        'type' => 'integer'
                    ),
                    'per_page' => array(
                        'description' => __( 'Number of history entries per page.', 'wp-content-flow' ),
                        'type' => 'integer',
                        'default' => 20,
                        'minimum' => 1,
                        'maximum' => 100
                    )
                )
            )
        ) );
        
        // GET /status - API status endpoint
        register_rest_route( $this->namespace, '/status', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array( $this, 'get_api_status' ),
                'permission_callback' => '__return_true' // Public endpoint
            )
        ) );
    }
    
    /**
     * Get post content history
     *
     * @param WP_REST_Request $request Full details about the request
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure
     */
    public function get_post_history( $request ) {
        $post_id = (int) $request->get_param( 'post_id' );
        $per_page = (int) $request->get_param( 'per_page' ) ?: 20;
        
        // Load content history model
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/models/class-content-history.php';
        
        $history_entries = WP_Content_Flow_Content_History::get_for_post( $post_id, array(
            'limit' => $per_page
        ) );
        
        $history_data = array();
        foreach ( $history_entries as $entry ) {
            $history_data[] = $entry->to_array();
        }
        
        return rest_ensure_response( $history_data );
    }
    
    /**
     * Check permissions for post history endpoint
     *
     * @param WP_REST_Request $request Full details about the request
     * @return bool|WP_Error True if the request has access, WP_Error object otherwise
     */
    public function get_post_history_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_forbidden', __( 'You must be logged in to view post history.', 'wp-content-flow' ), array( 'status' => 401 ) );
        }
        
        $post_id = (int) $request->get_param( 'post_id' );
        
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return new WP_Error( 'rest_forbidden', __( 'You do not have permission to view this post history.', 'wp-content-flow' ), array( 'status' => 403 ) );
        }
        
        return true;
    }
    
    /**
     * Get API status
     *
     * @param WP_REST_Request $request Full details about the request
     * @return WP_REST_Response Response object
     */
    public function get_api_status( $request ) {
        $status = array(
            'version' => WP_CONTENT_FLOW_VERSION,
            'namespace' => $this->namespace,
            'endpoints' => array_keys( $this->controllers ),
            'wordpress_version' => get_bloginfo( 'version' ),
            'php_version' => PHP_VERSION,
            'status' => 'active'
        );
        
        // Check AI providers status
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/class-ai-core.php';
        $providers = WP_Content_Flow_AI_Core::get_available_providers();
        
        $status['ai_providers'] = array();
        foreach ( $providers as $provider_name => $provider_config ) {
            $status['ai_providers'][ $provider_name ] = array(
                'name' => $provider_config['name'],
                'enabled' => $provider_config['enabled']
            );
        }
        
        return rest_ensure_response( $status );
    }
    
    /**
     * Add CORS headers for REST API requests
     */
    public function add_cors_headers() {
        // Only add CORS headers for our namespace
        add_filter( 'rest_pre_serve_request', function( $served, $result, $request, $server ) {
            if ( strpos( $request->get_route(), '/wp-content-flow/' ) === 0 ) {
                header( 'Access-Control-Allow-Origin: *' );
                header( 'Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS' );
                header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce' );
            }
            
            return $served;
        }, 10, 4 );
        
        // Handle preflight requests
        add_action( 'rest_api_init', function() {
            if ( $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ) {
                header( 'Access-Control-Allow-Origin: *' );
                header( 'Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS' );
                header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce' );
                exit;
            }
        } );
    }
    
    /**
     * Validate REST API requests before processing
     *
     * @param WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response Result to send to the client
     * @param array $handler Route handler used for the request
     * @param WP_REST_Request $request Request used to generate the response
     * @return WP_REST_Response|WP_HTTP_Response|WP_Error|mixed Response to send to the client
     */
    public function validate_rest_request( $response, $handler, $request ) {
        // Only validate our API requests
        if ( strpos( $request->get_route(), '/wp-content-flow/' ) !== 0 ) {
            return $response;
        }
        
        // Validate nonce for authenticated requests
        if ( is_user_logged_in() && in_array( $request->get_method(), array( 'POST', 'PUT', 'DELETE' ), true ) ) {
            $nonce = $request->get_header( 'X-WP-Nonce' );
            
            if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
                return new WP_Error( 'rest_forbidden', __( 'Invalid nonce.', 'wp-content-flow' ), array( 'status' => 403 ) );
            }
        }
        
        return $response;
    }
    
    /**
     * Get registered controllers
     *
     * @return array Registered controllers
     */
    public function get_controllers() {
        return $this->controllers;
    }
    
    /**
     * Get API namespace
     *
     * @return string API namespace
     */
    public function get_namespace() {
        return $this->namespace;
    }
    
    /**
     * Get API version
     *
     * @return string API version
     */
    public function get_version() {
        return $this->version;
    }
    
    /**
     * Test endpoint callback
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function test_endpoint( $request ) {
        return new WP_REST_Response( array(
            'success' => true,
            'message' => 'WP Content Flow REST API is working!',
            'namespace' => $this->namespace,
            'version' => $this->version,
            'timestamp' => current_time('mysql')
        ), 200 );
    }
}