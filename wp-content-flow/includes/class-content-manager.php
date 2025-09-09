<?php
/**
 * Content Manager Class
 * 
 * Manages content operations, suggestions, and history tracking
 *
 * @package WP_Content_Flow
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Content_Flow_Content_Manager {
    
    /**
     * Instance of this class
     * @var WP_Content_Flow_Content_Manager
     */
    private static $instance = null;
    
    /**
     * Database table names
     * @var array
     */
    private $tables = array();
    
    /**
     * Get singleton instance
     * @return WP_Content_Flow_Content_Manager
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        
        $this->tables = array(
            'suggestions' => $wpdb->prefix . 'ai_suggestions',
            'history' => $wpdb->prefix . 'ai_content_history',
            'workflows' => $wpdb->prefix . 'ai_workflows',
        );
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_wp_content_flow_save_suggestion', array($this, 'save_suggestion'));
        add_action('wp_ajax_wp_content_flow_get_suggestions', array($this, 'get_suggestions'));
        add_action('wp_ajax_wp_content_flow_apply_suggestion', array($this, 'apply_suggestion'));
        add_action('wp_ajax_wp_content_flow_get_content_history', array($this, 'get_content_history'));
        
        // REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_endpoints'));
        
        // Content hooks
        add_action('save_post', array($this, 'track_content_changes'), 10, 2);
        add_filter('content_save_pre', array($this, 'process_ai_suggestions'));
    }
    
    /**
     * Register REST API endpoints
     */
    public function register_rest_endpoints() {
        register_rest_route('wp-content-flow/v1', '/suggestions', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_suggestions_rest'),
            'permission_callback' => array($this, 'check_content_permissions'),
        ));
        
        register_rest_route('wp-content-flow/v1', '/suggestions', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_suggestion_rest'),
            'permission_callback' => array($this, 'check_content_permissions'),
        ));
        
        register_rest_route('wp-content-flow/v1', '/suggestions/(?P<id>\d+)', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_suggestion_rest'),
            'permission_callback' => array($this, 'check_content_permissions'),
        ));
        
        register_rest_route('wp-content-flow/v1', '/content/(?P<id>\d+)/history', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_content_history_rest'),
            'permission_callback' => array($this, 'check_content_permissions'),
        ));
    }
    
    /**
     * Check content permissions
     */
    public function check_content_permissions() {
        return current_user_can('edit_posts');
    }
    
    /**
     * Save content suggestion
     */
    public function save_suggestion() {
        check_ajax_referer('wp_content_flow_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $post_id = intval($_POST['post_id']);
        $suggestion_type = sanitize_text_field($_POST['type']);
        $original_content = wp_kses_post($_POST['original_content']);
        $suggested_content = wp_kses_post($_POST['suggested_content']);
        $confidence_score = floatval($_POST['confidence_score']);
        $ai_provider = sanitize_text_field($_POST['ai_provider']);
        
        $suggestion_id = $this->create_suggestion($post_id, $suggestion_type, $original_content, $suggested_content, $confidence_score, $ai_provider);
        
        if ($suggestion_id) {
            wp_send_json_success(array(
                'suggestion_id' => $suggestion_id,
                'message' => 'Suggestion saved successfully',
            ));
        } else {
            wp_send_json_error('Failed to save suggestion');
        }
    }
    
    /**
     * Create content suggestion
     */
    public function create_suggestion($post_id, $type, $original_content, $suggested_content, $confidence_score = 0.0, $ai_provider = '') {
        global $wpdb;
        
        $result = $wpdb->insert(
            $this->tables['suggestions'],
            array(
                'post_id' => $post_id,
                'user_id' => get_current_user_id(),
                'suggestion_type' => $type,
                'original_content' => $original_content,
                'suggested_content' => $suggested_content,
                'confidence_score' => $confidence_score,
                'ai_provider' => $ai_provider,
                'status' => 'pending',
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%s', '%s', '%f', '%s', '%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Get suggestions for a post
     */
    public function get_suggestions() {
        check_ajax_referer('wp_content_flow_nonce', 'nonce');
        
        $post_id = intval($_GET['post_id']);
        $suggestions = $this->get_post_suggestions($post_id);
        
        wp_send_json_success($suggestions);
    }
    
    /**
     * Get suggestions for a post (REST API)
     */
    public function get_suggestions_rest($request) {
        $post_id = $request->get_param('post_id');
        $suggestions = $this->get_post_suggestions($post_id);
        
        return rest_ensure_response($suggestions);
    }
    
    /**
     * Get suggestions for a specific post
     */
    public function get_post_suggestions($post_id, $status = 'pending') {
        global $wpdb;
        
        $suggestions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->tables['suggestions']} 
                WHERE post_id = %d AND status = %s 
                ORDER BY created_at DESC",
                $post_id,
                $status
            ),
            ARRAY_A
        );
        
        return $suggestions ?: array();
    }
    
    /**
     * Apply suggestion
     */
    public function apply_suggestion() {
        check_ajax_referer('wp_content_flow_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $suggestion_id = intval($_POST['suggestion_id']);
        $suggestion = $this->get_suggestion($suggestion_id);
        
        if (!$suggestion) {
            wp_send_json_error('Suggestion not found');
            return;
        }
        
        // Update post content
        $post_data = array(
            'ID' => $suggestion['post_id'],
            'post_content' => $suggestion['suggested_content'],
        );
        
        $updated = wp_update_post($post_data);
        
        if ($updated && !is_wp_error($updated)) {
            // Mark suggestion as applied
            $this->update_suggestion_status($suggestion_id, 'applied');
            
            // Track in history
            $this->track_content_change($suggestion['post_id'], 'suggestion_applied', $suggestion['original_content'], $suggestion['suggested_content']);
            
            wp_send_json_success(array(
                'message' => 'Suggestion applied successfully',
                'post_id' => $suggestion['post_id'],
            ));
        } else {
            wp_send_json_error('Failed to update post');
        }
    }
    
    /**
     * Get single suggestion
     */
    public function get_suggestion($suggestion_id) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->tables['suggestions']} WHERE id = %d",
                $suggestion_id
            ),
            ARRAY_A
        );
    }
    
    /**
     * Update suggestion status
     */
    public function update_suggestion_status($suggestion_id, $status) {
        global $wpdb;
        
        return $wpdb->update(
            $this->tables['suggestions'],
            array('status' => $status, 'updated_at' => current_time('mysql')),
            array('id' => $suggestion_id),
            array('%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Create suggestion (REST API)
     */
    public function create_suggestion_rest($request) {
        $post_id = $request->get_param('post_id');
        $type = $request->get_param('type');
        $original_content = $request->get_param('original_content');
        $suggested_content = $request->get_param('suggested_content');
        $confidence_score = $request->get_param('confidence_score') ?: 0.0;
        $ai_provider = $request->get_param('ai_provider') ?: '';
        
        $suggestion_id = $this->create_suggestion($post_id, $type, $original_content, $suggested_content, $confidence_score, $ai_provider);
        
        if ($suggestion_id) {
            return rest_ensure_response(array(
                'suggestion_id' => $suggestion_id,
                'message' => 'Suggestion created successfully',
            ));
        } else {
            return new WP_Error('creation_failed', 'Failed to create suggestion', array('status' => 500));
        }
    }
    
    /**
     * Update suggestion (REST API)
     */
    public function update_suggestion_rest($request) {
        $suggestion_id = $request->get_param('id');
        $status = $request->get_param('status');
        
        if ($this->update_suggestion_status($suggestion_id, $status)) {
            return rest_ensure_response(array(
                'message' => 'Suggestion updated successfully',
            ));
        } else {
            return new WP_Error('update_failed', 'Failed to update suggestion', array('status' => 500));
        }
    }
    
    /**
     * Track content changes
     */
    public function track_content_changes($post_id, $post) {
        // Skip auto-saves and revisions
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        
        // Get previous version
        $previous_content = get_post_meta($post_id, '_previous_content', true);
        $current_content = $post->post_content;
        
        if ($previous_content && $previous_content !== $current_content) {
            $this->track_content_change($post_id, 'manual_edit', $previous_content, $current_content);
        }
        
        // Store current content for next comparison
        update_post_meta($post_id, '_previous_content', $current_content);
    }
    
    /**
     * Track content change in history
     */
    public function track_content_change($post_id, $change_type, $old_content, $new_content) {
        global $wpdb;
        
        return $wpdb->insert(
            $this->tables['history'],
            array(
                'post_id' => $post_id,
                'user_id' => get_current_user_id(),
                'change_type' => $change_type,
                'old_content' => $old_content,
                'new_content' => $new_content,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get content history
     */
    public function get_content_history() {
        check_ajax_referer('wp_content_flow_nonce', 'nonce');
        
        $post_id = intval($_GET['post_id']);
        $history = $this->get_post_history($post_id);
        
        wp_send_json_success($history);
    }
    
    /**
     * Get content history (REST API)
     */
    public function get_content_history_rest($request) {
        $post_id = $request->get_param('id');
        $history = $this->get_post_history($post_id);
        
        return rest_ensure_response($history);
    }
    
    /**
     * Get history for a specific post
     */
    public function get_post_history($post_id, $limit = 50) {
        global $wpdb;
        
        $history = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT h.*, u.display_name as user_name 
                FROM {$this->tables['history']} h
                LEFT JOIN {$wpdb->users} u ON h.user_id = u.ID
                WHERE h.post_id = %d 
                ORDER BY h.created_at DESC 
                LIMIT %d",
                $post_id,
                $limit
            ),
            ARRAY_A
        );
        
        return $history ?: array();
    }
    
    /**
     * Process AI suggestions before saving content
     */
    public function process_ai_suggestions($content) {
        // This filter allows for pre-processing content with AI suggestions
        // before it's saved to the database
        
        return apply_filters('wp_content_flow_process_suggestions', $content);
    }
    
    /**
     * Get content statistics
     */
    public function get_content_stats($post_id = null) {
        global $wpdb;
        
        $where_clause = $post_id ? $wpdb->prepare('WHERE post_id = %d', $post_id) : '';
        
        $stats = array(
            'total_suggestions' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->tables['suggestions']} $where_clause"),
            'applied_suggestions' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->tables['suggestions']} $where_clause AND status = 'applied'"),
            'pending_suggestions' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->tables['suggestions']} $where_clause AND status = 'pending'"),
            'total_changes' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->tables['history']} $where_clause"),
        );
        
        $stats['suggestion_acceptance_rate'] = $stats['total_suggestions'] > 0 
            ? round(($stats['applied_suggestions'] / $stats['total_suggestions']) * 100, 2)
            : 0;
        
        return $stats;
    }
    
    /**
     * Clean up old suggestions and history
     */
    public function cleanup_old_data($days = 90) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Clean up old suggestions
        $deleted_suggestions = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->tables['suggestions']} WHERE created_at < %s AND status IN ('applied', 'rejected')",
                $cutoff_date
            )
        );
        
        // Clean up old history (keep more recent history)
        $history_cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $deleted_history = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->tables['history']} WHERE created_at < %s",
                $history_cutoff
            )
        );
        
        return array(
            'deleted_suggestions' => $deleted_suggestions,
            'deleted_history' => $deleted_history,
        );
    }
    
    /**
     * Export content data
     */
    public function export_content_data($post_id, $format = 'json') {
        $data = array(
            'post_id' => $post_id,
            'suggestions' => $this->get_post_suggestions($post_id, 'all'),
            'history' => $this->get_post_history($post_id),
            'stats' => $this->get_content_stats($post_id),
            'exported_at' => current_time('mysql'),
        );
        
        switch ($format) {
            case 'csv':
                return $this->export_to_csv($data);
            case 'xml':
                return $this->export_to_xml($data);
            default:
                return json_encode($data, JSON_PRETTY_PRINT);
        }
    }
    
    /**
     * Export data to CSV format
     */
    private function export_to_csv($data) {
        $output = fopen('php://temp', 'w+');
        
        // Export suggestions
        fputcsv($output, array('Type', 'ID', 'Created', 'Status', 'Confidence', 'Provider'));
        foreach ($data['suggestions'] as $suggestion) {
            fputcsv($output, array(
                'suggestion',
                $suggestion['id'],
                $suggestion['created_at'],
                $suggestion['status'],
                $suggestion['confidence_score'],
                $suggestion['ai_provider']
            ));
        }
        
        // Export history
        foreach ($data['history'] as $history_item) {
            fputcsv($output, array(
                'history',
                $history_item['id'],
                $history_item['created_at'],
                $history_item['change_type'],
                '',
                ''
            ));
        }
        
        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);
        
        return $csv_content;
    }
    
    /**
     * Export data to XML format
     */
    private function export_to_xml($data) {
        $xml = new SimpleXMLElement('<content_data/>');
        
        // Add metadata
        $xml->addChild('post_id', $data['post_id']);
        $xml->addChild('exported_at', $data['exported_at']);
        
        // Add suggestions
        $suggestions_node = $xml->addChild('suggestions');
        foreach ($data['suggestions'] as $suggestion) {
            $suggestion_node = $suggestions_node->addChild('suggestion');
            foreach ($suggestion as $key => $value) {
                $suggestion_node->addChild($key, htmlspecialchars($value));
            }
        }
        
        // Add history
        $history_node = $xml->addChild('history');
        foreach ($data['history'] as $history_item) {
            $item_node = $history_node->addChild('item');
            foreach ($history_item as $key => $value) {
                $item_node->addChild($key, htmlspecialchars($value));
            }
        }
        
        return $xml->asXML();
    }
}