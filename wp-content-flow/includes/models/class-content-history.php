<?php
/**
 * Content History Model Class
 * 
 * Object-oriented interface for wp_ai_content_history table operations.
 * This class provides content versioning and change tracking for AI-assisted modifications.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Content History model class
 */
class WP_Content_Flow_Content_History {
    
    /**
     * History entry ID
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
     * Associated suggestion ID (null for manual edits)
     *
     * @var int|null
     */
    public $suggestion_id;
    
    /**
     * User ID who made the change
     *
     * @var int
     */
    public $user_id;
    
    /**
     * Content before the change
     *
     * @var string
     */
    public $content_before;
    
    /**
     * Content after the change
     *
     * @var string
     */
    public $content_after;
    
    /**
     * Type of change made
     *
     * @var string
     */
    public $change_type;
    
    /**
     * Detailed change information (JSON decoded)
     *
     * @var array
     */
    public $diff_data;
    
    /**
     * Sequential revision number for the post
     *
     * @var int
     */
    public $revision_number;
    
    /**
     * Creation timestamp
     *
     * @var string
     */
    public $created_at;
    
    /**
     * Constructor
     *
     * @param int|array $history History ID or history data array
     */
    public function __construct( $history = 0 ) {
        if ( is_numeric( $history ) && $history > 0 ) {
            $this->load_by_id( $history );
        } elseif ( is_array( $history ) ) {
            $this->load_from_data( $history );
        }
    }
    
    /**
     * Load history entry by ID
     *
     * @param int $history_id History ID
     * @return bool True on success, false if not found
     */
    private function load_by_id( $history_id ) {
        // Load database schema functions
        if ( ! function_exists( 'wp_content_flow_get_history_entry' ) ) {
            require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/schema-history.php';
        }
        
        $history_data = wp_content_flow_get_history_entry( $history_id );
        
        if ( ! $history_data ) {
            return false;
        }
        
        $this->load_from_data( $history_data );
        return true;
    }
    
    /**
     * Load history entry from data array
     *
     * @param array $data History data
     */
    private function load_from_data( $data ) {
        $this->id = isset( $data['id'] ) ? (int) $data['id'] : 0;
        $this->post_id = isset( $data['post_id'] ) ? (int) $data['post_id'] : 0;
        $this->suggestion_id = isset( $data['suggestion_id'] ) ? (int) $data['suggestion_id'] : null;
        $this->user_id = isset( $data['user_id'] ) ? (int) $data['user_id'] : 0;
        $this->content_before = $data['content_before'] ?? '';
        $this->content_after = $data['content_after'] ?? '';
        $this->change_type = $data['change_type'] ?? '';
        $this->diff_data = $data['diff_data'] ?? array();
        $this->revision_number = isset( $data['revision_number'] ) ? (int) $data['revision_number'] : 0;
        $this->created_at = $data['created_at'] ?? '';
        
        // Ensure diff_data is array
        if ( is_string( $this->diff_data ) ) {
            $this->diff_data = json_decode( $this->diff_data, true ) ?: array();
        }
    }
    
    /**
     * Get history entry as array (for API responses)
     *
     * @return array History data
     */
    public function to_array() {
        return array(
            'id' => $this->id,
            'post_id' => $this->post_id,
            'suggestion_id' => $this->suggestion_id,
            'user_id' => $this->user_id,
            'content_before' => $this->content_before,
            'content_after' => $this->content_after,
            'change_type' => $this->change_type,
            'diff_data' => $this->diff_data,
            'revision_number' => $this->revision_number,
            'created_at' => $this->created_at
        );
    }
    
    /**
     * Get detailed diff information
     *
     * @return array Formatted diff data
     */
    public function get_diff_summary() {
        $summary = array(
            'changes_detected' => false,
            'word_count_change' => 0,
            'character_count_change' => 0,
            'similarity_score' => 0.0,
            'change_description' => ''
        );
        
        if ( ! empty( $this->diff_data ) ) {
            $summary['changes_detected'] = true;
            $summary['word_count_change'] = $this->diff_data['word_count_change'] ?? 0;
            $summary['character_count_change'] = $this->diff_data['character_count_change'] ?? 0;
            $summary['similarity_score'] = $this->diff_data['similarity_score'] ?? 0.0;
            
            // Generate human-readable change description
            $summary['change_description'] = $this->generate_change_description();
        }
        
        return $summary;
    }
    
    /**
     * Generate human-readable change description
     *
     * @return string Change description
     */
    private function generate_change_description() {
        $descriptions = array();
        
        if ( ! empty( $this->diff_data ) ) {
            $word_change = $this->diff_data['word_count_change'] ?? 0;
            $char_change = $this->diff_data['character_count_change'] ?? 0;
            
            if ( $word_change > 0 ) {
                $descriptions[] = sprintf( __( 'Added %d words', 'wp-content-flow' ), $word_change );
            } elseif ( $word_change < 0 ) {
                $descriptions[] = sprintf( __( 'Removed %d words', 'wp-content-flow' ), abs( $word_change ) );
            }
            
            if ( $char_change > 0 ) {
                $descriptions[] = sprintf( __( 'Added %d characters', 'wp-content-flow' ), $char_change );
            } elseif ( $char_change < 0 ) {
                $descriptions[] = sprintf( __( 'Removed %d characters', 'wp-content-flow' ), abs( $char_change ) );
            }
        }
        
        // Add change type context
        switch ( $this->change_type ) {
            case 'ai_generated':
                $descriptions[] = __( 'AI-generated content', 'wp-content-flow' );
                break;
            case 'ai_improved':
                $descriptions[] = __( 'AI-improved content', 'wp-content-flow' );
                break;
            case 'manual_edit':
                $descriptions[] = __( 'Manual edit', 'wp-content-flow' );
                break;
            case 'ai_rejected':
                $descriptions[] = __( 'AI suggestion rejected', 'wp-content-flow' );
                break;
        }
        
        return implode( ', ', array_filter( $descriptions ) );
    }
    
    /**
     * Check if current user can view this history entry
     *
     * @return bool True if user can view, false otherwise
     */
    public function current_user_can_view() {
        // Must be able to edit the associated post
        return current_user_can( 'edit_post', $this->post_id );
    }
    
    /**
     * Get the user who made this change
     *
     * @return WP_User|false User object or false if not found
     */
    public function get_user() {
        return get_user_by( 'id', $this->user_id );
    }
    
    /**
     * Get the associated post
     *
     * @return WP_Post|null Post object or null if not found
     */
    public function get_post() {
        return get_post( $this->post_id );
    }
    
    /**
     * Get the associated suggestion (if any)
     *
     * @return WP_Content_Flow_AI_Suggestion|null Suggestion object or null
     */
    public function get_suggestion() {
        if ( $this->suggestion_id ) {
            require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/models/class-ai-suggestion.php';
            return WP_Content_Flow_AI_Suggestion::find( $this->suggestion_id );
        }
        
        return null;
    }
    
    /**
     * Revert content to this revision
     *
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function revert_to_this_revision() {
        if ( ! $this->current_user_can_view() ) {
            return new WP_Error( 'history_revert_forbidden', __( 'You do not have permission to revert this content.', 'wp-content-flow' ) );
        }
        
        // Get current post content for history entry
        $post = get_post( $this->post_id );
        if ( ! $post ) {
            return new WP_Error( 'post_not_found', __( 'Associated post not found.', 'wp-content-flow' ) );
        }
        
        $current_content = $post->post_content;
        
        // Update post with content from this revision
        $result = wp_update_post( array(
            'ID' => $this->post_id,
            'post_content' => $this->content_before
        ), true );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        // Create new history entry for the revert action
        if ( ! function_exists( 'wp_content_flow_create_history_entry' ) ) {
            require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/schema-history.php';
        }
        
        $revert_history_data = array(
            'post_id' => $this->post_id,
            'suggestion_id' => null, // Manual revert
            'user_id' => get_current_user_id(),
            'content_before' => $current_content,
            'content_after' => $this->content_before,
            'change_type' => 'manual_edit'
        );
        
        wp_content_flow_create_history_entry( $revert_history_data );
        
        // Fire action hook
        do_action( 'wp_content_flow_content_reverted', $this->post_id, $this->id, $current_content, $this->content_before );
        
        return true;
    }
    
    /**
     * Get content history for a post
     *
     * @param int $post_id Post ID
     * @param array $args Query arguments
     * @return array Array of history objects
     */
    public static function get_for_post( $post_id, $args = array() ) {
        if ( ! function_exists( 'wp_content_flow_get_post_history' ) ) {
            require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/schema-history.php';
        }
        
        $history_data = wp_content_flow_get_post_history( $post_id, $args );
        
        $history_entries = array();
        foreach ( $history_data as $data ) {
            $history_entries[] = new self( $data );
        }
        
        return $history_entries;
    }
    
    /**
     * Get history entry by ID (static method)
     *
     * @param int $history_id History ID
     * @return WP_Content_Flow_Content_History|null History object or null if not found
     */
    public static function find( $history_id ) {
        $history = new self( $history_id );
        
        if ( $history->id > 0 ) {
            return $history;
        }
        
        return null;
    }
    
    /**
     * Create history entry from change data
     *
     * @param int $post_id Post ID
     * @param string $content_before Content before change
     * @param string $content_after Content after change
     * @param string $change_type Type of change
     * @param int|null $suggestion_id Associated suggestion ID
     * @param int|null $user_id User who made the change
     * @return WP_Content_Flow_Content_History|WP_Error History object or error
     */
    public static function create_entry( $post_id, $content_before, $content_after, $change_type, $suggestion_id = null, $user_id = null ) {
        if ( ! function_exists( 'wp_content_flow_create_history_entry' ) ) {
            require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/schema-history.php';
        }
        
        $history_data = array(
            'post_id' => $post_id,
            'suggestion_id' => $suggestion_id,
            'user_id' => $user_id ?: get_current_user_id(),
            'content_before' => $content_before,
            'content_after' => $content_after,
            'change_type' => $change_type
        );
        
        $history_id = wp_content_flow_create_history_entry( $history_data );
        
        if ( $history_id === false ) {
            return new WP_Error( 'history_create_failed', __( 'Failed to create history entry.', 'wp-content-flow' ) );
        }
        
        return new self( $history_id );
    }
    
    /**
     * Get revision statistics for a post
     *
     * @param int $post_id Post ID
     * @return array Statistics
     */
    public static function get_post_statistics( $post_id ) {
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return array();
        }
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_content_history';
        
        // Get basic counts
        $stats_query = $wpdb->prepare( "
            SELECT 
                COUNT(*) as total_revisions,
                COUNT(CASE WHEN change_type = 'ai_generated' THEN 1 END) as ai_generated_count,
                COUNT(CASE WHEN change_type = 'ai_improved' THEN 1 END) as ai_improved_count,
                COUNT(CASE WHEN change_type = 'manual_edit' THEN 1 END) as manual_edit_count,
                COUNT(CASE WHEN change_type = 'ai_rejected' THEN 1 END) as ai_rejected_count,
                MAX(revision_number) as latest_revision
            FROM {$table_name} 
            WHERE post_id = %d
        ", $post_id );
        
        $stats = $wpdb->get_row( $stats_query, ARRAY_A );
        
        if ( ! $stats ) {
            return array(
                'total_revisions' => 0,
                'ai_generated_count' => 0,
                'ai_improved_count' => 0,
                'manual_edit_count' => 0,
                'ai_rejected_count' => 0,
                'latest_revision' => 0,
                'most_active_user' => null,
                'first_revision_date' => null,
                'last_revision_date' => null
            );
        }
        
        // Get most active user
        $most_active_user_query = $wpdb->prepare( "
            SELECT user_id, COUNT(*) as revision_count
            FROM {$table_name} 
            WHERE post_id = %d 
            GROUP BY user_id 
            ORDER BY revision_count DESC 
            LIMIT 1
        ", $post_id );
        
        $most_active = $wpdb->get_row( $most_active_user_query, ARRAY_A );
        
        // Get date range
        $date_range_query = $wpdb->prepare( "
            SELECT MIN(created_at) as first_date, MAX(created_at) as last_date
            FROM {$table_name} 
            WHERE post_id = %d
        ", $post_id );
        
        $date_range = $wpdb->get_row( $date_range_query, ARRAY_A );
        
        $stats['most_active_user'] = $most_active ? (int) $most_active['user_id'] : null;
        $stats['first_revision_date'] = $date_range['first_date'];
        $stats['last_revision_date'] = $date_range['last_date'];
        
        // Convert string numbers to integers
        foreach ( array( 'total_revisions', 'ai_generated_count', 'ai_improved_count', 'manual_edit_count', 'ai_rejected_count', 'latest_revision' ) as $key ) {
            $stats[ $key ] = (int) $stats[ $key ];
        }
        
        return $stats;
    }
    
    /**
     * Compare two content strings and return diff information
     *
     * @param string $before Content before
     * @param string $after Content after
     * @return array Diff information
     */
    public static function calculate_diff( $before, $after ) {
        if ( ! function_exists( 'wp_content_flow_calculate_content_diff' ) ) {
            require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/schema-history.php';
        }
        
        return wp_content_flow_calculate_content_diff( $before, $after );
    }
}