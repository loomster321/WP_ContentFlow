<?php
/**
 * Audit Trail Manager
 *
 * Comprehensive audit trail system for tracking all content changes, AI operations,
 * user actions, and system events with detailed forensic logging capabilities.
 *
 * @package WP_Content_Flow
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Audit Trail Manager Class
 */
class WP_Content_Flow_Audit_Trail {
    
    /**
     * Single instance of the class
     * @var WP_Content_Flow_Audit_Trail
     */
    private static $instance;
    
    /**
     * Audit event types
     * @var array
     */
    private $event_types = [
        'content_generated',
        'content_modified',
        'content_approved',
        'content_published',
        'ai_suggestion_created',
        'ai_suggestion_accepted',
        'ai_suggestion_rejected',
        'workflow_started',
        'workflow_completed',
        'user_permission_changed',
        'system_setting_changed',
        'collaboration_started',
        'collaboration_ended',
        'api_key_updated',
        'provider_switched',
        'error_occurred'
    ];
    
    /**
     * Retention policies (in seconds)
     * @var array
     */
    private $retention_policies = [
        'draft_content' => 30 * DAY_IN_SECONDS,        // 30 days for drafts
        'published_content' => 365 * DAY_IN_SECONDS,   // 1 year for published
        'user_actions' => 90 * DAY_IN_SECONDS,         // 90 days for user actions
        'system_events' => 180 * DAY_IN_SECONDS,       // 180 days for system events
        'security_events' => 2 * 365 * DAY_IN_SECONDS  // 2 years for security
    ];
    
    /**
     * Get single instance
     *
     * @return WP_Content_Flow_Audit_Trail
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->schedule_cleanup();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Content tracking hooks
        add_action( 'wp_content_flow_content_generated', [ $this, 'log_content_generated' ], 10, 4 );
        add_action( 'wp_content_flow_content_modified', [ $this, 'log_content_modified' ], 10, 4 );
        add_action( 'wp_content_flow_suggestion_created', [ $this, 'log_suggestion_created' ], 10, 3 );
        add_action( 'wp_content_flow_suggestion_accepted', [ $this, 'log_suggestion_accepted' ], 10, 3 );
        add_action( 'wp_content_flow_suggestion_rejected', [ $this, 'log_suggestion_rejected' ], 10, 3 );
        
        // Workflow tracking hooks
        add_action( 'wp_content_flow_workflow_started', [ $this, 'log_workflow_started' ], 10, 3 );
        add_action( 'wp_content_flow_workflow_completed', [ $this, 'log_workflow_completed' ], 10, 3 );
        
        // System and security hooks
        add_action( 'wp_content_flow_api_key_updated', [ $this, 'log_api_key_updated' ], 10, 2 );
        add_action( 'wp_content_flow_provider_switched', [ $this, 'log_provider_switched' ], 10, 3 );
        add_action( 'wp_content_flow_setting_changed', [ $this, 'log_setting_changed' ], 10, 3 );
        
        // WordPress core content hooks
        add_action( 'post_updated', [ $this, 'track_post_changes' ], 10, 3 );
        add_action( 'wp_insert_post', [ $this, 'track_post_creation' ], 10, 2 );
        
        // User permission hooks
        add_action( 'set_user_role', [ $this, 'track_role_changes' ], 10, 3 );
        add_action( 'add_user_to_blog', [ $this, 'track_user_added' ], 10, 3 );
        
        // Error logging hooks
        add_action( 'wp_content_flow_error', [ $this, 'log_error' ], 10, 3 );
        
        // AJAX endpoints for audit trail access
        add_action( 'wp_ajax_wp_content_flow_get_audit_trail', [ $this, 'ajax_get_audit_trail' ] );
        add_action( 'wp_ajax_wp_content_flow_export_audit_trail', [ $this, 'ajax_export_audit_trail' ] );
        
        // Admin menu for audit trail
        // Add with priority 20 to ensure parent menu exists
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ], 20 );
        
        // Schedule cleanup
        add_action( 'wp_content_flow_audit_cleanup', [ $this, 'cleanup_old_entries' ] );
    }
    
    /**
     * Log a comprehensive audit event
     *
     * @param string $event_type Type of event
     * @param array $data Event data
     * @param string $category Event category for retention policy
     * @return int|false Audit entry ID or false on failure
     */
    public function log_event( $event_type, $data = [], $category = 'system_events' ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'content_flow_audit_trail';
        
        // Validate event type
        if ( ! in_array( $event_type, $this->event_types ) ) {
            $event_type = 'custom_event';
        }
        
        // Prepare audit entry data
        $audit_data = [
            'event_type' => $event_type,
            'category' => $category,
            'user_id' => get_current_user_id(),
            'user_ip' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'session_id' => session_id() ?: wp_generate_uuid4(),
            'data' => wp_json_encode( $data ),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'http_method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            'created_at' => current_time( 'mysql' ),
            'site_url' => home_url(),
            'wp_version' => get_bloginfo( 'version' ),
            'plugin_version' => WP_CONTENT_FLOW_VERSION
        ];
        
        // Add contextual information
        if ( function_exists( 'wp_debug_backtrace_summary' ) ) {
            $audit_data['stack_trace'] = wp_debug_backtrace_summary();
        }
        
        // Insert audit entry
        $result = $wpdb->insert( $table_name, $audit_data, [
            '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
        ] );
        
        if ( $result ) {
            $audit_id = $wpdb->insert_id;
            
            // Fire hook for extensibility
            do_action( 'wp_content_flow_audit_logged', $audit_id, $event_type, $data );
            
            return $audit_id;
        }
        
        return false;
    }
    
    /**
     * Log content generation event
     *
     * @param int $post_id Post ID
     * @param string $content Generated content
     * @param string $provider AI provider used
     * @param array $parameters Generation parameters
     */
    public function log_content_generated( $post_id, $content, $provider, $parameters = [] ) {
        $this->log_event( 'content_generated', [
            'post_id' => $post_id,
            'content_preview' => wp_trim_words( $content, 50 ),
            'content_length' => strlen( $content ),
            'ai_provider' => $provider,
            'parameters' => $parameters,
            'word_count' => str_word_count( $content )
        ], 'published_content' );
    }
    
    /**
     * Log content modification event
     *
     * @param int $post_id Post ID
     * @param string $old_content Previous content
     * @param string $new_content New content
     * @param array $context Modification context
     */
    public function log_content_modified( $post_id, $old_content, $new_content, $context = [] ) {
        // Calculate content diff statistics
        $old_word_count = str_word_count( $old_content );
        $new_word_count = str_word_count( $new_content );
        $similarity = $this->calculate_content_similarity( $old_content, $new_content );
        
        $this->log_event( 'content_modified', [
            'post_id' => $post_id,
            'old_content_preview' => wp_trim_words( $old_content, 30 ),
            'new_content_preview' => wp_trim_words( $new_content, 30 ),
            'old_word_count' => $old_word_count,
            'new_word_count' => $new_word_count,
            'word_count_change' => $new_word_count - $old_word_count,
            'content_similarity' => $similarity,
            'modification_type' => $this->determine_modification_type( $similarity ),
            'context' => $context
        ], $this->get_content_category( $post_id ) );
    }
    
    /**
     * Log AI suggestion creation
     *
     * @param int $suggestion_id Suggestion ID
     * @param string $content Suggestion content
     * @param array $ai_data AI generation data
     */
    public function log_suggestion_created( $suggestion_id, $content, $ai_data ) {
        $this->log_event( 'ai_suggestion_created', [
            'suggestion_id' => $suggestion_id,
            'content_preview' => wp_trim_words( $content, 30 ),
            'content_length' => strlen( $content ),
            'ai_provider' => $ai_data['provider'] ?? 'unknown',
            'ai_model' => $ai_data['model'] ?? 'unknown',
            'prompt' => $ai_data['prompt'] ?? '',
            'parameters' => $ai_data['parameters'] ?? [],
            'generation_time' => $ai_data['generation_time'] ?? null,
            'cost_estimate' => $ai_data['cost_estimate'] ?? null
        ], 'published_content' );
    }
    
    /**
     * Log suggestion acceptance
     *
     * @param int $suggestion_id Suggestion ID
     * @param int $post_id Post ID
     * @param string $implementation_method How it was implemented
     */
    public function log_suggestion_accepted( $suggestion_id, $post_id, $implementation_method ) {
        $this->log_event( 'ai_suggestion_accepted', [
            'suggestion_id' => $suggestion_id,
            'post_id' => $post_id,
            'implementation_method' => $implementation_method,
            'decision_time' => time()
        ], $this->get_content_category( $post_id ) );
    }
    
    /**
     * Log suggestion rejection
     *
     * @param int $suggestion_id Suggestion ID
     * @param int $post_id Post ID
     * @param string $reason Rejection reason
     */
    public function log_suggestion_rejected( $suggestion_id, $post_id, $reason ) {
        $this->log_event( 'ai_suggestion_rejected', [
            'suggestion_id' => $suggestion_id,
            'post_id' => $post_id,
            'rejection_reason' => $reason,
            'decision_time' => time()
        ], 'user_actions' );
    }
    
    /**
     * Log workflow events
     */
    public function log_workflow_started( $workflow_id, $template_id, $context ) {
        $this->log_event( 'workflow_started', [
            'workflow_id' => $workflow_id,
            'template_id' => $template_id,
            'context' => $context
        ], 'system_events' );
    }
    
    public function log_workflow_completed( $workflow_id, $success, $results ) {
        $this->log_event( 'workflow_completed', [
            'workflow_id' => $workflow_id,
            'success' => $success,
            'results' => $results,
            'execution_time' => $results['execution_time'] ?? null
        ], 'system_events' );
    }
    
    /**
     * Log security-sensitive events
     */
    public function log_api_key_updated( $provider, $key_hash ) {
        $this->log_event( 'api_key_updated', [
            'provider' => $provider,
            'key_hash' => $key_hash
        ], 'security_events' );
    }
    
    public function log_provider_switched( $old_provider, $new_provider, $reason ) {
        $this->log_event( 'provider_switched', [
            'old_provider' => $old_provider,
            'new_provider' => $new_provider,
            'reason' => $reason
        ], 'system_events' );
    }
    
    public function log_setting_changed( $setting_name, $old_value, $new_value ) {
        $this->log_event( 'system_setting_changed', [
            'setting_name' => $setting_name,
            'old_value' => is_string( $old_value ) ? $old_value : wp_json_encode( $old_value ),
            'new_value' => is_string( $new_value ) ? $new_value : wp_json_encode( $new_value )
        ], 'system_events' );
    }
    
    public function log_error( $error_code, $error_message, $context = [] ) {
        $this->log_event( 'error_occurred', [
            'error_code' => $error_code,
            'error_message' => $error_message,
            'context' => $context,
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage( true ),
            'peak_memory' => memory_get_peak_usage( true )
        ], 'system_events' );
    }
    
    /**
     * Track WordPress post changes
     */
    public function track_post_changes( $post_id, $post_after, $post_before ) {
        // Only track if content changed
        if ( $post_after->post_content !== $post_before->post_content ) {
            $this->log_content_modified( 
                $post_id,
                $post_before->post_content,
                $post_after->post_content,
                [
                    'trigger' => 'wordpress_post_update',
                    'post_status' => $post_after->post_status,
                    'post_type' => $post_after->post_type
                ]
            );
        }
    }
    
    public function track_post_creation( $post_id, $post ) {
        if ( ! wp_is_post_revision( $post_id ) && ! wp_is_post_autosave( $post_id ) ) {
            $this->log_event( 'content_created', [
                'post_id' => $post_id,
                'post_type' => $post->post_type,
                'post_status' => $post->post_status,
                'content_length' => strlen( $post->post_content ),
                'word_count' => str_word_count( $post->post_content )
            ], $this->get_content_category( $post_id ) );
        }
    }
    
    /**
     * Track user permission changes
     */
    public function track_role_changes( $user_id, $role, $old_roles ) {
        $this->log_event( 'user_permission_changed', [
            'target_user_id' => $user_id,
            'new_role' => $role,
            'old_roles' => $old_roles
        ], 'security_events' );
    }
    
    public function track_user_added( $user_id, $role, $blog_id ) {
        $this->log_event( 'user_added_to_site', [
            'target_user_id' => $user_id,
            'role' => $role,
            'blog_id' => $blog_id
        ], 'security_events' );
    }
    
    /**
     * Get audit trail entries with filtering and pagination
     *
     * @param array $args Query arguments
     * @return array Audit trail results
     */
    public function get_audit_trail( $args = [] ) {
        global $wpdb;
        
        $defaults = [
            'event_type' => '',
            'user_id' => 0,
            'post_id' => 0,
            'date_from' => '',
            'date_to' => '',
            'category' => '',
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ];
        
        $args = wp_parse_args( $args, $defaults );
        
        $table_name = $wpdb->prefix . 'content_flow_audit_trail';
        
        // Build WHERE clause
        $where_conditions = [ '1=1' ];
        $where_values = [];
        
        if ( ! empty( $args['event_type'] ) ) {
            $where_conditions[] = 'event_type = %s';
            $where_values[] = $args['event_type'];
        }
        
        if ( ! empty( $args['user_id'] ) ) {
            $where_conditions[] = 'user_id = %d';
            $where_values[] = $args['user_id'];
        }
        
        if ( ! empty( $args['post_id'] ) ) {
            $where_conditions[] = 'JSON_EXTRACT(data, "$.post_id") = %d';
            $where_values[] = $args['post_id'];
        }
        
        if ( ! empty( $args['category'] ) ) {
            $where_conditions[] = 'category = %s';
            $where_values[] = $args['category'];
        }
        
        if ( ! empty( $args['date_from'] ) ) {
            $where_conditions[] = 'created_at >= %s';
            $where_values[] = $args['date_from'];
        }
        
        if ( ! empty( $args['date_to'] ) ) {
            $where_conditions[] = 'created_at <= %s';
            $where_values[] = $args['date_to'];
        }
        
        $where_clause = implode( ' AND ', $where_conditions );
        
        // Build ORDER BY clause
        $allowed_orderby = [ 'id', 'event_type', 'user_id', 'created_at' ];
        $orderby = in_array( $args['orderby'], $allowed_orderby ) ? $args['orderby'] : 'created_at';
        $order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
        
        // Build LIMIT clause
        $limit_clause = '';
        if ( $args['limit'] > 0 ) {
            $limit_clause = $wpdb->prepare( 'LIMIT %d OFFSET %d', $args['limit'], $args['offset'] );
        }
        
        // Execute query
        $query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY {$orderby} {$order} {$limit_clause}";
        
        if ( ! empty( $where_values ) ) {
            $query = $wpdb->prepare( $query, $where_values );
        }
        
        $results = $wpdb->get_results( $query );
        
        // Get total count for pagination
        $count_query = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}";
        if ( ! empty( $where_values ) ) {
            $count_query = $wpdb->prepare( $count_query, $where_values );
        }
        $total_count = (int) $wpdb->get_var( $count_query );
        
        // Process results
        foreach ( $results as &$entry ) {
            $entry->data = json_decode( $entry->data, true );
            $entry->user_display_name = get_userdata( $entry->user_id )->display_name ?? 'Unknown User';
        }
        
        return [
            'entries' => $results,
            'total_count' => $total_count,
            'page_count' => $args['limit'] > 0 ? ceil( $total_count / $args['limit'] ) : 1
        ];
    }
    
    /**
     * Cleanup old audit entries based on retention policies
     */
    public function cleanup_old_entries() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'content_flow_audit_trail';
        
        foreach ( $this->retention_policies as $category => $retention_seconds ) {
            $cutoff_date = date( 'Y-m-d H:i:s', time() - $retention_seconds );
            
            $deleted = $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE category = %s AND created_at < %s",
                $category,
                $cutoff_date
            ) );
            
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $deleted > 0 ) {
                error_log( "WP Content Flow: Cleaned up {$deleted} audit entries in category {$category}" );
            }
        }
    }
    
    /**
     * Export audit trail to CSV
     *
     * @param array $args Export arguments
     * @return string CSV content
     */
    public function export_audit_trail( $args = [] ) {
        $args['limit'] = 0; // No limit for export
        $results = $this->get_audit_trail( $args );
        
        $csv_data = [];
        $csv_data[] = [
            'ID', 'Event Type', 'Category', 'User', 'User IP', 'Timestamp',
            'Post ID', 'Content Preview', 'AI Provider', 'Success'
        ];
        
        foreach ( $results['entries'] as $entry ) {
            $data = $entry->data;
            $csv_data[] = [
                $entry->id,
                $entry->event_type,
                $entry->category,
                $entry->user_display_name,
                $entry->user_ip,
                $entry->created_at,
                $data['post_id'] ?? '',
                $data['content_preview'] ?? '',
                $data['ai_provider'] ?? '',
                isset( $data['success'] ) ? ( $data['success'] ? 'Yes' : 'No' ) : ''
            ];
        }
        
        // Convert to CSV format
        $csv_content = '';
        foreach ( $csv_data as $row ) {
            $csv_content .= '"' . implode( '","', array_map( 'str_replace', [ '"', '""' ], $row ) ) . '"' . "\n";
        }
        
        return $csv_content;
    }
    
    /**
     * Helper methods
     */
    
    private function get_client_ip() {
        $ip_keys = [ 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ];
        
        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = $_SERVER[ $key ];
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = explode( ',', $ip )[0];
                }
                return trim( $ip );
            }
        }
        
        return 'unknown';
    }
    
    private function get_content_category( $post_id ) {
        $post = get_post( $post_id );
        return ( $post && $post->post_status === 'publish' ) ? 'published_content' : 'draft_content';
    }
    
    private function calculate_content_similarity( $old_content, $new_content ) {
        $old_words = str_word_count( strtolower( $old_content ), 1 );
        $new_words = str_word_count( strtolower( $new_content ), 1 );
        
        $intersection = array_intersect( $old_words, $new_words );
        $union = array_unique( array_merge( $old_words, $new_words ) );
        
        return count( $union ) > 0 ? count( $intersection ) / count( $union ) : 0;
    }
    
    private function determine_modification_type( $similarity ) {
        if ( $similarity > 0.9 ) return 'minor_edit';
        if ( $similarity > 0.7 ) return 'moderate_edit';
        if ( $similarity > 0.3 ) return 'major_edit';
        return 'complete_rewrite';
    }
    
    private function schedule_cleanup() {
        if ( ! wp_next_scheduled( 'wp_content_flow_audit_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'wp_content_flow_audit_cleanup' );
        }
    }
    
    /**
     * AJAX handlers
     */
    
    public function ajax_get_audit_trail() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions' );
        }
        
        check_ajax_referer( 'wp_content_flow_audit', 'nonce' );
        
        $args = [
            'event_type' => sanitize_text_field( $_POST['event_type'] ?? '' ),
            'user_id' => intval( $_POST['user_id'] ?? 0 ),
            'post_id' => intval( $_POST['post_id'] ?? 0 ),
            'date_from' => sanitize_text_field( $_POST['date_from'] ?? '' ),
            'date_to' => sanitize_text_field( $_POST['date_to'] ?? '' ),
            'category' => sanitize_text_field( $_POST['category'] ?? '' ),
            'limit' => intval( $_POST['limit'] ?? 50 ),
            'offset' => intval( $_POST['offset'] ?? 0 )
        ];
        
        $results = $this->get_audit_trail( $args );
        wp_send_json_success( $results );
    }
    
    public function ajax_export_audit_trail() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions' );
        }
        
        check_ajax_referer( 'wp_content_flow_audit', 'nonce' );
        
        $csv_content = $this->export_audit_trail();
        
        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="audit-trail-' . date( 'Y-m-d' ) . '.csv"' );
        echo $csv_content;
        exit;
    }
    
    /**
     * Add admin menu for audit trail
     */
    public function add_admin_menu() {
        add_submenu_page(
            'wp-content-flow',
            'Audit Trail',
            'Audit Trail',
            'manage_options',
            'wp-content-flow-audit',
            [ $this, 'admin_page' ]
        );
    }
    
    /**
     * Render admin page for audit trail
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Content Flow Audit Trail</h1>
            <div id="wp-content-flow-audit-trail">
                <div class="audit-filters">
                    <h3>Filters</h3>
                    <form id="audit-filter-form">
                        <table class="form-table">
                            <tr>
                                <th><label for="event-type">Event Type</label></th>
                                <td>
                                    <select name="event_type" id="event-type">
                                        <option value="">All Events</option>
                                        <?php foreach ( $this->event_types as $event_type ): ?>
                                            <option value="<?php echo esc_attr( $event_type ); ?>">
                                                <?php echo esc_html( ucwords( str_replace( '_', ' ', $event_type ) ) ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="date-from">Date From</label></th>
                                <td><input type="date" name="date_from" id="date-from"></td>
                            </tr>
                            <tr>
                                <th><label for="date-to">Date To</label></th>
                                <td><input type="date" name="date_to" id="date-to"></td>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="submit" class="button button-primary">Filter</button>
                            <button type="button" class="button" id="export-audit">Export CSV</button>
                        </p>
                    </form>
                </div>
                
                <div id="audit-results">
                    <!-- Results will be loaded here via AJAX -->
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Load initial audit trail
            loadAuditTrail();
            
            // Filter form submission
            $('#audit-filter-form').on('submit', function(e) {
                e.preventDefault();
                loadAuditTrail();
            });
            
            // Export functionality
            $('#export-audit').on('click', function() {
                var form = $('#audit-filter-form');
                var formData = form.serialize();
                formData += '&action=wp_content_flow_export_audit_trail';
                formData += '&nonce=<?php echo wp_create_nonce( 'wp_content_flow_audit' ); ?>';
                
                window.location.href = '<?php echo admin_url( 'admin-ajax.php' ); ?>?' + formData;
            });
            
            function loadAuditTrail(offset = 0) {
                var formData = $('#audit-filter-form').serialize();
                formData += '&action=wp_content_flow_get_audit_trail';
                formData += '&nonce=<?php echo wp_create_nonce( 'wp_content_flow_audit' ); ?>';
                formData += '&offset=' + offset;
                
                $.post('<?php echo admin_url( 'admin-ajax.php' ); ?>', formData, function(response) {
                    if (response.success) {
                        displayAuditResults(response.data);
                    }
                });
            }
            
            function displayAuditResults(data) {
                var html = '<h3>Audit Trail Results (' + data.total_count + ' entries)</h3>';
                html += '<table class="wp-list-table widefat fixed striped">';
                html += '<thead><tr>';
                html += '<th>Timestamp</th><th>Event</th><th>User</th><th>Details</th>';
                html += '</tr></thead><tbody>';
                
                data.entries.forEach(function(entry) {
                    html += '<tr>';
                    html += '<td>' + entry.created_at + '</td>';
                    html += '<td>' + entry.event_type.replace(/_/g, ' ') + '</td>';
                    html += '<td>' + entry.user_display_name + '</td>';
                    html += '<td><details><summary>View Details</summary><pre>' + 
                           JSON.stringify(entry.data, null, 2) + '</pre></details></td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
                $('#audit-results').html(html);
            }
        });
        </script>
        <?php
    }
}