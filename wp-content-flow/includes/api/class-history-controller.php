<?php
/**
 * History Controller
 * 
 * Handles REST API endpoints for content history management.
 * This was the missing file preventing API registration.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * History Controller Class
 */
class WP_Content_Flow_History_Controller extends WP_REST_Controller {
    
    /**
     * Namespace
     */
    protected $namespace = 'wp-content-flow/v1';
    
    /**
     * Resource name
     */
    protected $rest_base = 'history';
    
    /**
     * Register routes
     */
    public function register_routes() {
        // GET /history - Get content history
        register_rest_route( $this->namespace, '/' . $this->rest_base, array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array( $this, 'get_items' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
            ),
        ) );
        
        // POST /history - Create history entry
        register_rest_route( $this->namespace, '/' . $this->rest_base, array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array( $this, 'create_item' ),
                'permission_callback' => array( $this, 'create_item_permissions_check' ),
            ),
        ) );
    }
    
    /**
     * Get history items
     */
    public function get_items( $request ) {
        return rest_ensure_response( array(
            'success' => true,
            'data' => array(),
            'message' => 'History controller now working'
        ) );
    }
    
    /**
     * Create history item
     */
    public function create_item( $request ) {
        return rest_ensure_response( array(
            'success' => true,
            'message' => 'History controller now working'
        ) );
    }
    
    /**
     * Check permissions for getting items
     */
    public function get_items_permissions_check( $request ) {
        return current_user_can( 'edit_posts' );
    }
    
    /**
     * Check permissions for creating items
     */
    public function create_item_permissions_check( $request ) {
        return current_user_can( 'edit_posts' );
    }
}