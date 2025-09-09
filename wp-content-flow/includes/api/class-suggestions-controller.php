<?php
/**
 * Suggestions REST API Controller
 * 
 * This controller handles suggestion acceptance, rejection, and management,
 * making the suggestion contract tests pass.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Suggestions REST API Controller class
 */
class WP_Content_Flow_Suggestions_Controller extends WP_REST_Controller {
    
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
    protected $rest_base = 'suggestions';
    
    /**
     * Register the routes for suggestions
     */
    public function register_routes() {
        // POST /suggestions/{id}/accept - Accept suggestion
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/accept', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array( $this, 'accept_suggestion' ),
                'permission_callback' => array( $this, 'suggestion_permissions_check' ),
                'args' => array(
                    'id' => array(
                        'description' => __( 'Unique identifier for the suggestion.', 'wp-content-flow' ),
                        'type' => 'integer'
                    )
                )
            )
        ) );
        
        // POST /suggestions/{id}/reject - Reject suggestion
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/reject', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array( $this, 'reject_suggestion' ),
                'permission_callback' => array( $this, 'suggestion_permissions_check' ),
                'args' => array(
                    'id' => array(
                        'description' => __( 'Unique identifier for the suggestion.', 'wp-content-flow' ),
                        'type' => 'integer'
                    )
                )
            )
        ) );
    }
    
    /**
     * Check if a given request has access to manage suggestions
     *
     * @param WP_REST_Request $request Full details about the request
     * @return bool|WP_Error True if the request has access, WP_Error object otherwise
     */
    public function suggestion_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_forbidden', __( 'You must be logged in to manage suggestions.', 'wp-content-flow' ), array( 'status' => 401 ) );
        }
        
        if ( ! current_user_can( 'edit_posts' ) ) {
            return new WP_Error( 'rest_forbidden', __( 'You do not have permission to manage suggestions.', 'wp-content-flow' ), array( 'status' => 403 ) );
        }
        
        // Additional permission check for specific suggestion will be done in the callback methods
        
        return true;
    }
    
    /**
     * Accept a suggestion
     *
     * @param WP_REST_Request $request Full details about the request
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure
     */
    public function accept_suggestion( $request ) {
        $suggestion_id = (int) $request->get_param( 'id' );
        
        // Load suggestion model
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/models/class-ai-suggestion.php';
        
        $suggestion = WP_Content_Flow_AI_Suggestion::find( $suggestion_id );
        
        if ( ! $suggestion ) {
            return new WP_Error( 'rest_not_found', __( 'Suggestion not found.', 'wp-content-flow' ), array( 'status' => 404 ) );
        }
        
        // Check permissions for this specific suggestion
        if ( ! $suggestion->current_user_can_edit() ) {
            return new WP_Error( 'rest_forbidden', __( 'You do not have permission to accept this suggestion.', 'wp-content-flow' ), array( 'status' => 403 ) );
        }
        
        // Accept the suggestion
        $result = $suggestion->accept();
        
        if ( is_wp_error( $result ) ) {
            switch ( $result->get_error_code() ) {
                case 'suggestion_accept_forbidden':
                    return new WP_Error( 'rest_forbidden', $result->get_error_message(), array( 'status' => 403 ) );
                case 'suggestion_already_processed':
                    return new WP_Error( 'rest_invalid_request', $result->get_error_message(), array( 'status' => 400 ) );
                case 'post_not_found':
                    return new WP_Error( 'rest_not_found', $result->get_error_message(), array( 'status' => 404 ) );
                default:
                    return new WP_Error( 'suggestion_accept_failed', $result->get_error_message(), array( 'status' => 500 ) );
            }
        }
        
        return rest_ensure_response( array(
            'success' => true,
            'message' => __( 'Suggestion accepted and applied successfully.', 'wp-content-flow' )
        ) );
    }
    
    /**
     * Reject a suggestion
     *
     * @param WP_REST_Request $request Full details about the request
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure
     */
    public function reject_suggestion( $request ) {
        $suggestion_id = (int) $request->get_param( 'id' );
        
        // Load suggestion model
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/models/class-ai-suggestion.php';
        
        $suggestion = WP_Content_Flow_AI_Suggestion::find( $suggestion_id );
        
        if ( ! $suggestion ) {
            return new WP_Error( 'rest_not_found', __( 'Suggestion not found.', 'wp-content-flow' ), array( 'status' => 404 ) );
        }
        
        // Check permissions for this specific suggestion
        if ( ! $suggestion->current_user_can_edit() ) {
            return new WP_Error( 'rest_forbidden', __( 'You do not have permission to reject this suggestion.', 'wp-content-flow' ), array( 'status' => 403 ) );
        }
        
        // Reject the suggestion
        $result = $suggestion->reject();
        
        if ( is_wp_error( $result ) ) {
            switch ( $result->get_error_code() ) {
                case 'suggestion_reject_forbidden':
                    return new WP_Error( 'rest_forbidden', $result->get_error_message(), array( 'status' => 403 ) );
                case 'suggestion_already_processed':
                    return new WP_Error( 'rest_invalid_request', $result->get_error_message(), array( 'status' => 400 ) );
                default:
                    return new WP_Error( 'suggestion_reject_failed', $result->get_error_message(), array( 'status' => 500 ) );
            }
        }
        
        return rest_ensure_response( array(
            'success' => true,
            'message' => __( 'Suggestion rejected successfully.', 'wp-content-flow' )
        ) );
    }
}