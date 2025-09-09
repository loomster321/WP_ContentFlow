<?php
/**
 * AI Suggestion Model Class
 * 
 * Object-oriented interface for wp_ai_suggestions table operations.
 * This class handles AI-generated content suggestions and their lifecycle.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AI Suggestion model class
 */
class WP_Content_Flow_AI_Suggestion {
    
    /**
     * Suggestion ID
     *
     * @var int
     */
    public $id;
    
    /**
     * Associated post ID
     *
     * @var int
     */
    public $post_id;
    
    /**
     * Associated workflow ID
     *
     * @var int
     */
    public $workflow_id;
    
    /**
     * User ID who created the suggestion
     *
     * @var int
     */
    public $user_id;
    
    /**
     * Original content before AI processing
     *
     * @var string
     */
    public $original_content;
    
    /**
     * AI-suggested content
     *
     * @var string
     */
    public $suggested_content;
    
    /**
     * Type of suggestion
     *
     * @var string
     */
    public $suggestion_type;
    
    /**
     * Suggestion status
     *
     * @var string
     */
    public $status;
    
    /**
     * AI confidence score (0.0 to 1.0)
     *
     * @var float
     */
    public $confidence_score;
    
    /**
     * Additional AI metadata
     *
     * @var array
     */
    public $metadata;
    
    /**
     * Creation timestamp
     *
     * @var string
     */
    public $created_at;
    
    /**
     * Processing timestamp (when user acted on suggestion)
     *
     * @var string|null
     */
    public $processed_at;
    
    /**
     * Constructor
     *
     * @param int|array $suggestion Suggestion ID or suggestion data array
     */
    public function __construct( $suggestion = 0 ) {
        if ( is_numeric( $suggestion ) && $suggestion > 0 ) {
            $this->load_by_id( $suggestion );
        } elseif ( is_array( $suggestion ) ) {
            $this->load_from_data( $suggestion );
        }
    }
    
    /**
     * Load suggestion by ID
     *
     * @param int $suggestion_id Suggestion ID
     * @return bool True on success, false if not found
     */
    private function load_by_id( $suggestion_id ) {
        // Load database schema functions
        if ( ! function_exists( 'wp_content_flow_get_suggestion' ) ) {
            require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/schema-suggestions.php';
        }
        
        $suggestion_data = wp_content_flow_get_suggestion( $suggestion_id );
        
        if ( ! $suggestion_data ) {
            return false;
        }
        
        $this->load_from_data( $suggestion_data );
        return true;
    }
    
    /**
     * Load suggestion from data array
     *
     * @param array $data Suggestion data
     */
    private function load_from_data( $data ) {
        $this->id = isset( $data['id'] ) ? (int) $data['id'] : 0;
        $this->post_id = isset( $data['post_id'] ) ? (int) $data['post_id'] : 0;
        $this->workflow_id = isset( $data['workflow_id'] ) ? (int) $data['workflow_id'] : 0;
        $this->user_id = isset( $data['user_id'] ) ? (int) $data['user_id'] : 0;
        $this->original_content = $data['original_content'] ?? '';
        $this->suggested_content = $data['suggested_content'] ?? '';
        $this->suggestion_type = $data['suggestion_type'] ?? 'generation';
        $this->status = $data['status'] ?? 'pending';
        $this->confidence_score = isset( $data['confidence_score'] ) ? (float) $data['confidence_score'] : null;
        $this->metadata = $data['metadata'] ?? array();
        $this->created_at = $data['created_at'] ?? '';
        $this->processed_at = $data['processed_at'] ?? null;
        
        // Ensure metadata is array
        if ( is_string( $this->metadata ) ) {
            $this->metadata = json_decode( $this->metadata, true ) ?: array();
        }
    }
    
    /**
     * Save suggestion (create or update)
     *
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function save() {
        // Load database schema functions
        if ( ! function_exists( 'wp_content_flow_validate_suggestion_data' ) ) {
            require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/schema-suggestions.php';
        }
        
        // Prepare data for validation
        $suggestion_data = array(
            'post_id' => $this->post_id,
            'workflow_id' => $this->workflow_id,
            'user_id' => $this->user_id ?: get_current_user_id(),
            'original_content' => $this->original_content,
            'suggested_content' => $this->suggested_content,
            'suggestion_type' => $this->suggestion_type,
            'status' => $this->status,
            'confidence_score' => $this->confidence_score,
            'metadata' => $this->metadata
        );
        
        // Validate data
        $validated_data = wp_content_flow_validate_suggestion_data( $suggestion_data );
        
        if ( is_wp_error( $validated_data ) ) {
            return $validated_data;
        }
        
        if ( $this->id > 0 ) {
            // Update existing suggestion
            return $this->update( $validated_data );
        } else {
            // Create new suggestion
            return $this->create( $validated_data );
        }
    }
    
    /**
     * Create new suggestion
     *
     * @param array $validated_data Validated suggestion data
     * @return bool True on success, false on failure
     */
    private function create( $validated_data ) {
        $suggestion_id = wp_content_flow_insert_suggestion( $validated_data );
        
        if ( $suggestion_id === false ) {
            return new WP_Error( 'suggestion_create_failed', __( 'Failed to create suggestion.', 'wp-content-flow' ) );
        }
        
        $this->id = $suggestion_id;
        $this->user_id = $validated_data['user_id'];
        $this->created_at = current_time( 'mysql', true );
        
        // Fire action hook
        do_action( 'wp_content_flow_suggestion_created', $this );
        
        return true;
    }
    
    /**
     * Update existing suggestion
     *
     * @param array $validated_data Validated suggestion data
     * @return bool True on success, false on failure
     */
    private function update( $validated_data ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_suggestions';
        
        $result = $wpdb->update(
            $table_name,
            $validated_data,
            array( 'id' => $this->id ),
            array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%f', '%s' ),
            array( '%d' )
        );
        
        if ( $result === false ) {
            return new WP_Error( 'suggestion_update_failed', __( 'Failed to update suggestion.', 'wp-content-flow' ) );
        }
        
        // Update object properties
        $this->post_id = $validated_data['post_id'];
        $this->workflow_id = $validated_data['workflow_id'];
        $this->original_content = $validated_data['original_content'];
        $this->suggested_content = $validated_data['suggested_content'];
        $this->suggestion_type = $validated_data['suggestion_type'];
        $this->status = $validated_data['status'];
        $this->confidence_score = $validated_data['confidence_score'];
        $this->metadata = json_decode( $validated_data['metadata'], true );
        
        // Fire action hook
        do_action( 'wp_content_flow_suggestion_updated', $this );
        
        return true;
    }
    
    /**
     * Accept suggestion and apply to post content
     *
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function accept() {
        if ( ! $this->current_user_can_edit() ) {
            return new WP_Error( 'suggestion_accept_forbidden', __( 'You do not have permission to accept this suggestion.', 'wp-content-flow' ) );
        }
        
        if ( $this->status !== 'pending' ) {
            return new WP_Error( 'suggestion_already_processed', __( 'This suggestion has already been processed.', 'wp-content-flow' ) );
        }
        
        // Get current post content
        $post = get_post( $this->post_id );
        if ( ! $post ) {
            return new WP_Error( 'post_not_found', __( 'Associated post not found.', 'wp-content-flow' ) );
        }
        
        $old_content = $post->post_content;
        
        // Apply suggestion based on type
        $new_content = $this->apply_suggestion_to_content( $old_content );
        
        // Update post content
        $result = wp_update_post( array(
            'ID' => $this->post_id,
            'post_content' => $new_content
        ), true );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        // Update suggestion status
        $this->status = 'accepted';
        $this->processed_at = current_time( 'mysql', true );
        
        if ( ! function_exists( 'wp_content_flow_update_suggestion_status' ) ) {
            require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/schema-suggestions.php';
        }
        
        wp_content_flow_update_suggestion_status( $this->id, 'accepted' );
        
        // Create content history entry
        $this->create_history_entry( $old_content, $new_content, 'ai_generated' );
        
        // Fire action hooks
        do_action( 'wp_content_flow_suggestion_accepted', $this );
        do_action( 'wp_content_flow_post_content_updated', $this->post_id, $old_content, $new_content, $this->id );
        
        return true;
    }
    
    /**
     * Reject suggestion
     *
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function reject() {
        if ( ! $this->current_user_can_edit() ) {
            return new WP_Error( 'suggestion_reject_forbidden', __( 'You do not have permission to reject this suggestion.', 'wp-content-flow' ) );
        }
        
        if ( $this->status !== 'pending' ) {
            return new WP_Error( 'suggestion_already_processed', __( 'This suggestion has already been processed.', 'wp-content-flow' ) );
        }
        
        // Update suggestion status
        $this->status = 'rejected';
        $this->processed_at = current_time( 'mysql', true );
        
        if ( ! function_exists( 'wp_content_flow_update_suggestion_status' ) ) {
            require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/schema-suggestions.php';
        }
        
        wp_content_flow_update_suggestion_status( $this->id, 'rejected' );
        
        // Create history entry for rejection
        $post = get_post( $this->post_id );
        if ( $post ) {
            $this->create_history_entry( $post->post_content, $post->post_content, 'ai_rejected' );
        }
        
        // Fire action hook
        do_action( 'wp_content_flow_suggestion_rejected', $this );
        
        return true;
    }
    
    /**
     * Apply suggestion to content based on suggestion type
     *
     * @param string $current_content Current post content
     * @return string Modified content
     */
    private function apply_suggestion_to_content( $current_content ) {
        switch ( $this->suggestion_type ) {
            case 'generation':
                // For generation, replace or append based on original content
                if ( empty( $this->original_content ) ) {
                    // New content generation - append to existing or replace if empty
                    return empty( $current_content ) ? $this->suggested_content : $current_content . "\n\n" . $this->suggested_content;
                } else {
                    // Replace specific content
                    return str_replace( $this->original_content, $this->suggested_content, $current_content );
                }
                
            case 'improvement':
            case 'correction':
                // Replace original content with improved version
                return str_replace( $this->original_content, $this->suggested_content, $current_content );
                
            default:
                return $current_content;
        }
    }
    
    /**
     * Create content history entry
     *
     * @param string $old_content Content before change
     * @param string $new_content Content after change
     * @param string $change_type Type of change
     */
    private function create_history_entry( $old_content, $new_content, $change_type ) {
        if ( ! function_exists( 'wp_content_flow_create_history_entry' ) ) {
            require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/schema-history.php';
        }
        
        $history_data = array(
            'post_id' => $this->post_id,
            'suggestion_id' => $this->id,
            'user_id' => get_current_user_id(),
            'content_before' => $old_content,
            'content_after' => $new_content,
            'change_type' => $change_type
        );
        
        wp_content_flow_create_history_entry( $history_data );
    }
    
    /**
     * Check if current user can edit this suggestion
     *
     * @return bool True if user can edit, false otherwise
     */
    public function current_user_can_edit() {
        // Must be able to edit the associated post
        return current_user_can( 'edit_post', $this->post_id );
    }
    
    /**
     * Get suggestion as array (for API responses)
     *
     * @return array Suggestion data
     */
    public function to_array() {
        return array(
            'id' => $this->id,
            'post_id' => $this->post_id,
            'workflow_id' => $this->workflow_id,
            'original_content' => $this->original_content,
            'suggested_content' => $this->suggested_content,
            'suggestion_type' => $this->suggestion_type,
            'status' => $this->status,
            'confidence_score' => $this->confidence_score,
            'created_at' => $this->created_at,
            'processed_at' => $this->processed_at,
            'metadata' => $this->metadata
        );
    }
    
    /**
     * Get suggestions for a post
     *
     * @param int $post_id Post ID
     * @param array $args Query arguments
     * @return array Array of suggestion objects
     */
    public static function get_for_post( $post_id, $args = array() ) {
        if ( ! function_exists( 'wp_content_flow_get_post_suggestions' ) ) {
            require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/schema-suggestions.php';
        }
        
        $suggestions_data = wp_content_flow_get_post_suggestions( $post_id, $args );
        
        $suggestions = array();
        foreach ( $suggestions_data as $data ) {
            $suggestions[] = new self( $data );
        }
        
        return $suggestions;
    }
    
    /**
     * Get suggestion by ID (static method)
     *
     * @param int $suggestion_id Suggestion ID
     * @return WP_Content_Flow_AI_Suggestion|null Suggestion object or null if not found
     */
    public static function find( $suggestion_id ) {
        $suggestion = new self( $suggestion_id );
        
        if ( $suggestion->id > 0 ) {
            return $suggestion;
        }
        
        return null;
    }
    
    /**
     * Create suggestion from AI response data
     *
     * @param array $ai_response AI provider response
     * @param int $post_id Post ID
     * @param int $workflow_id Workflow ID
     * @param string $original_content Original content
     * @param string $suggestion_type Type of suggestion
     * @return WP_Content_Flow_AI_Suggestion|WP_Error Suggestion object or error
     */
    public static function create_from_ai_response( $ai_response, $post_id, $workflow_id, $original_content = '', $suggestion_type = 'generation' ) {
        $suggestion = new self();
        
        $suggestion->post_id = $post_id;
        $suggestion->workflow_id = $workflow_id;
        $suggestion->user_id = get_current_user_id();
        $suggestion->original_content = $original_content;
        $suggestion->suggested_content = $ai_response['suggested_content'] ?? $ai_response['content'] ?? '';
        $suggestion->suggestion_type = $suggestion_type;
        $suggestion->status = 'pending';
        $suggestion->confidence_score = $ai_response['confidence_score'] ?? null;
        $suggestion->metadata = $ai_response['metadata'] ?? array();
        
        $result = $suggestion->save();
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        return $suggestion;
    }
    
    /**
     * Validate suggestion data
     *
     * @param array $data Suggestion data to validate
     * @return array|WP_Error Validated data or error
     */
    public static function validate( $data ) {
        if ( ! function_exists( 'wp_content_flow_validate_suggestion_data' ) ) {
            require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/schema-suggestions.php';
        }
        
        return wp_content_flow_validate_suggestion_data( $data );
    }
}