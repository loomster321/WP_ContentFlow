<?php
/**
 * Collaboration Manager
 *
 * Handles multi-user collaboration features including shared suggestion queues,
 * real-time notifications, collaborative editing, and user assignments.
 *
 * @package WP_Content_Flow
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Collaboration Manager Class
 */
class WP_Content_Flow_Collaboration_Manager {
    
    /**
     * Single instance of the class
     * @var WP_Content_Flow_Collaboration_Manager
     */
    private static $instance;
    
    /**
     * Active collaboration sessions
     * @var array
     */
    private $active_sessions = [];
    
    /**
     * Notification channels
     * @var array
     */
    private $notification_channels = [];
    
    /**
     * Get single instance
     *
     * @return WP_Content_Flow_Collaboration_Manager
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
        $this->init_notification_channels();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // AJAX handlers for collaboration features
        add_action( 'wp_ajax_wp_content_flow_join_collaboration', [ $this, 'ajax_join_collaboration' ] );
        add_action( 'wp_ajax_wp_content_flow_leave_collaboration', [ $this, 'ajax_leave_collaboration' ] );
        add_action( 'wp_ajax_wp_content_flow_get_collaboration_status', [ $this, 'ajax_get_collaboration_status' ] );
        add_action( 'wp_ajax_wp_content_flow_send_collaboration_message', [ $this, 'ajax_send_collaboration_message' ] );
        add_action( 'wp_ajax_wp_content_flow_get_shared_suggestions', [ $this, 'ajax_get_shared_suggestions' ] );
        add_action( 'wp_ajax_wp_content_flow_assign_suggestion', [ $this, 'ajax_assign_suggestion' ] );
        add_action( 'wp_ajax_wp_content_flow_claim_suggestion', [ $this, 'ajax_claim_suggestion' ] );
        
        // Real-time update hooks
        add_action( 'wp_content_flow_suggestion_created', [ $this, 'notify_suggestion_created' ], 10, 2 );
        add_action( 'wp_content_flow_suggestion_accepted', [ $this, 'notify_suggestion_accepted' ], 10, 3 );
        add_action( 'wp_content_flow_workflow_assigned', [ $this, 'notify_workflow_assigned' ], 10, 3 );
        
        // Heartbeat API integration for real-time updates
        add_filter( 'heartbeat_received', [ $this, 'heartbeat_received' ], 10, 2 );
        
        // Enqueue collaboration scripts
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_collaboration_scripts' ] );
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_collaboration_scripts' ] );
    }
    
    /**
     * Initialize notification channels
     */
    private function init_notification_channels() {
        $this->notification_channels = [
            'in_app' => [
                'enabled' => true,
                'handler' => [ $this, 'send_in_app_notification' ]
            ],
            'email' => [
                'enabled' => get_option( 'wp_content_flow_email_notifications', true ),
                'handler' => [ $this, 'send_email_notification' ]
            ],
            'slack' => [
                'enabled' => ! empty( get_option( 'wp_content_flow_slack_webhook' ) ),
                'handler' => [ $this, 'send_slack_notification' ]
            ],
            'webhook' => [
                'enabled' => ! empty( get_option( 'wp_content_flow_collaboration_webhook' ) ),
                'handler' => [ $this, 'send_webhook_notification' ]
            ]
        ];
        
        // Allow plugins to add custom notification channels
        $this->notification_channels = apply_filters( 'wp_content_flow_notification_channels', $this->notification_channels );
    }
    
    /**
     * Create shared suggestion queue
     *
     * @param int $post_id Post ID
     * @param array $participants User IDs who can access the queue
     * @param array $options Queue configuration options
     * @return int|false Queue ID or false on failure
     */
    public function create_shared_queue( $post_id, $participants = [], $options = [] ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'content_flow_shared_queues';
        
        $defaults = [
            'auto_assign' => false,
            'max_suggestions_per_user' => 5,
            'priority_system' => true,
            'deadline' => null,
            'notification_frequency' => 'immediate'
        ];
        
        $options = wp_parse_args( $options, $defaults );
        
        $result = $wpdb->insert(
            $table_name,
            [
                'post_id' => $post_id,
                'participants' => wp_json_encode( $participants ),
                'options' => wp_json_encode( $options ),
                'status' => 'active',
                'created_by' => get_current_user_id(),
                'created_at' => current_time( 'mysql' )
            ],
            [ '%d', '%s', '%s', '%s', '%d', '%s' ]
        );
        
        if ( $result ) {
            $queue_id = $wpdb->insert_id;
            
            // Notify participants about the new shared queue
            $this->notify_users( 
                $participants, 
                'shared_queue_created', 
                [
                    'queue_id' => $queue_id,
                    'post_id' => $post_id,
                    'creator' => get_current_user_id()
                ]
            );
            
            return $queue_id;
        }
        
        return false;
    }
    
    /**
     * Add user to collaboration session
     *
     * @param int $post_id Post ID
     * @param int $user_id User ID
     * @param string $role Collaboration role (editor, reviewer, viewer)
     * @return bool Success
     */
    public function join_collaboration( $post_id, $user_id = 0, $role = 'editor' ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        
        // Check permissions
        if ( ! $this->user_can_collaborate( $user_id, $post_id ) ) {
            return false;
        }
        
        $session_key = 'post_' . $post_id;
        
        if ( ! isset( $this->active_sessions[ $session_key ] ) ) {
            $this->active_sessions[ $session_key ] = [
                'post_id' => $post_id,
                'participants' => [],
                'created_at' => time(),
                'last_activity' => time()
            ];
        }
        
        // Add or update participant
        $this->active_sessions[ $session_key ]['participants'][ $user_id ] = [
            'user_id' => $user_id,
            'role' => $role,
            'joined_at' => time(),
            'last_seen' => time(),
            'cursor_position' => null,
            'active_selection' => null
        ];
        
        $this->active_sessions[ $session_key ]['last_activity'] = time();
        
        // Store session in database for persistence
        $this->save_collaboration_session( $session_key );
        
        // Notify other participants
        $this->notify_collaboration_change( $post_id, 'user_joined', [
            'user_id' => $user_id,
            'role' => $role
        ] );
        
        return true;
    }
    
    /**
     * Remove user from collaboration session
     *
     * @param int $post_id Post ID
     * @param int $user_id User ID
     * @return bool Success
     */
    public function leave_collaboration( $post_id, $user_id = 0 ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        
        $session_key = 'post_' . $post_id;
        
        if ( isset( $this->active_sessions[ $session_key ]['participants'][ $user_id ] ) ) {
            unset( $this->active_sessions[ $session_key ]['participants'][ $user_id ] );
            
            $this->active_sessions[ $session_key ]['last_activity'] = time();
            
            // Clean up empty sessions
            if ( empty( $this->active_sessions[ $session_key ]['participants'] ) ) {
                unset( $this->active_sessions[ $session_key ] );
                $this->delete_collaboration_session( $session_key );
            } else {
                $this->save_collaboration_session( $session_key );
            }
            
            // Notify other participants
            $this->notify_collaboration_change( $post_id, 'user_left', [
                'user_id' => $user_id
            ] );
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get collaboration status for a post
     *
     * @param int $post_id Post ID
     * @return array Collaboration status
     */
    public function get_collaboration_status( $post_id ) {
        $session_key = 'post_' . $post_id;
        $session = $this->active_sessions[ $session_key ] ?? null;
        
        if ( ! $session ) {
            $session = $this->load_collaboration_session( $session_key );
        }
        
        $status = [
            'active' => ! empty( $session ),
            'participants' => [],
            'total_participants' => 0,
            'last_activity' => null
        ];
        
        if ( $session ) {
            $status['participants'] = array_values( $session['participants'] );
            $status['total_participants'] = count( $session['participants'] );
            $status['last_activity'] = $session['last_activity'];
            
            // Add user display names
            foreach ( $status['participants'] as &$participant ) {
                $user = get_userdata( $participant['user_id'] );
                $participant['display_name'] = $user ? $user->display_name : 'Unknown User';
                $participant['avatar'] = get_avatar_url( $participant['user_id'], 32 );
            }
        }
        
        return $status;
    }
    
    /**
     * Assign suggestion to user
     *
     * @param int $suggestion_id Suggestion ID
     * @param int $assignee_id User ID to assign to
     * @param int $assigner_id User ID doing the assignment
     * @return bool Success
     */
    public function assign_suggestion( $suggestion_id, $assignee_id, $assigner_id = 0 ) {
        if ( ! $assigner_id ) {
            $assigner_id = get_current_user_id();
        }
        
        // Check if assigner has permission
        if ( ! current_user_can( 'edit_others_posts' ) ) {
            return false;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_suggestions';
        
        $result = $wpdb->update(
            $table_name,
            [
                'assigned_to' => $assignee_id,
                'assigned_by' => $assigner_id,
                'assigned_at' => current_time( 'mysql' ),
                'status' => 'assigned'
            ],
            [ 'id' => $suggestion_id ],
            [ '%d', '%d', '%s', '%s' ],
            [ '%d' ]
        );
        
        if ( $result !== false ) {
            // Create notification
            $this->notify_users( 
                [ $assignee_id ], 
                'suggestion_assigned', 
                [
                    'suggestion_id' => $suggestion_id,
                    'assigner_id' => $assigner_id
                ]
            );
            
            // Fire action hook
            do_action( 'wp_content_flow_suggestion_assigned', $suggestion_id, $assignee_id, $assigner_id );
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Allow user to claim an unassigned suggestion
     *
     * @param int $suggestion_id Suggestion ID
     * @param int $user_id User ID claiming the suggestion
     * @return bool Success
     */
    public function claim_suggestion( $suggestion_id, $user_id = 0 ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_suggestions';
        
        // Check if suggestion is available for claiming
        $suggestion = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d AND (assigned_to IS NULL OR assigned_to = 0)",
            $suggestion_id
        ) );
        
        if ( ! $suggestion ) {
            return false; // Already assigned or doesn't exist
        }
        
        $result = $wpdb->update(
            $table_name,
            [
                'assigned_to' => $user_id,
                'assigned_by' => $user_id,
                'assigned_at' => current_time( 'mysql' ),
                'status' => 'claimed'
            ],
            [ 'id' => $suggestion_id ],
            [ '%d', '%d', '%s', '%s' ],
            [ '%d' ]
        );
        
        if ( $result !== false ) {
            // Fire action hook
            do_action( 'wp_content_flow_suggestion_claimed', $suggestion_id, $user_id );
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Send notification to multiple users
     *
     * @param array $user_ids User IDs to notify
     * @param string $notification_type Type of notification
     * @param array $data Notification data
     */
    public function notify_users( $user_ids, $notification_type, $data = [] ) {
        foreach ( $user_ids as $user_id ) {
            $this->send_notification( $user_id, $notification_type, $data );
        }
    }
    
    /**
     * Send notification to a single user
     *
     * @param int $user_id User ID
     * @param string $notification_type Type of notification
     * @param array $data Notification data
     */
    public function send_notification( $user_id, $notification_type, $data = [] ) {
        $user = get_userdata( $user_id );
        
        if ( ! $user ) {
            return;
        }
        
        // Get user notification preferences
        $preferences = $this->get_user_notification_preferences( $user_id );
        
        foreach ( $this->notification_channels as $channel => $config ) {
            if ( ! $config['enabled'] || ! $preferences[ $channel ] ) {
                continue;
            }
            
            if ( is_callable( $config['handler'] ) ) {
                call_user_func( $config['handler'], $user_id, $notification_type, $data );
            }
        }
    }
    
    /**
     * Get user notification preferences
     *
     * @param int $user_id User ID
     * @return array Notification preferences
     */
    private function get_user_notification_preferences( $user_id ) {
        $defaults = [
            'in_app' => true,
            'email' => true,
            'slack' => false,
            'webhook' => false
        ];
        
        $preferences = get_user_meta( $user_id, 'wp_content_flow_notification_prefs', true );
        
        if ( ! is_array( $preferences ) ) {
            $preferences = [];
        }
        
        return wp_parse_args( $preferences, $defaults );
    }
    
    /**
     * Check if user can collaborate on post
     *
     * @param int $user_id User ID
     * @param int $post_id Post ID
     * @return bool Can collaborate
     */
    private function user_can_collaborate( $user_id, $post_id ) {
        $user = get_userdata( $user_id );
        
        if ( ! $user ) {
            return false;
        }
        
        // Check basic edit permission
        if ( ! user_can( $user_id, 'edit_post', $post_id ) ) {
            return false;
        }
        
        // Allow plugins to add custom collaboration permission checks
        return apply_filters( 'wp_content_flow_user_can_collaborate', true, $user_id, $post_id );
    }
    
    /**
     * Save collaboration session to database
     *
     * @param string $session_key Session key
     */
    private function save_collaboration_session( $session_key ) {
        $session = $this->active_sessions[ $session_key ] ?? null;
        
        if ( $session ) {
            update_option( 'wp_content_flow_collab_' . $session_key, $session, false );
        }
    }
    
    /**
     * Load collaboration session from database
     *
     * @param string $session_key Session key
     * @return array|null Session data
     */
    private function load_collaboration_session( $session_key ) {
        $session = get_option( 'wp_content_flow_collab_' . $session_key );
        
        if ( $session && is_array( $session ) ) {
            // Clean up old sessions (older than 1 hour of inactivity)
            if ( ( time() - $session['last_activity'] ) > HOUR_IN_SECONDS ) {
                $this->delete_collaboration_session( $session_key );
                return null;
            }
            
            $this->active_sessions[ $session_key ] = $session;
            return $session;
        }
        
        return null;
    }
    
    /**
     * Delete collaboration session
     *
     * @param string $session_key Session key
     */
    private function delete_collaboration_session( $session_key ) {
        delete_option( 'wp_content_flow_collab_' . $session_key );
        
        if ( isset( $this->active_sessions[ $session_key ] ) ) {
            unset( $this->active_sessions[ $session_key ] );
        }
    }
    
    /**
     * Notify about collaboration changes
     *
     * @param int $post_id Post ID
     * @param string $change_type Type of change
     * @param array $data Change data
     */
    private function notify_collaboration_change( $post_id, $change_type, $data = [] ) {
        $session_key = 'post_' . $post_id;
        $session = $this->active_sessions[ $session_key ] ?? null;
        
        if ( ! $session ) {
            return;
        }
        
        $notification_data = array_merge( $data, [
            'post_id' => $post_id,
            'timestamp' => time()
        ] );
        
        // Notify all participants except the one who made the change
        $exclude_user = $data['user_id'] ?? 0;
        
        foreach ( $session['participants'] as $participant ) {
            if ( $participant['user_id'] !== $exclude_user ) {
                $this->send_notification( 
                    $participant['user_id'], 
                    'collaboration_' . $change_type, 
                    $notification_data 
                );
            }
        }
    }
    
    /**
     * AJAX handler for joining collaboration
     */
    public function ajax_join_collaboration() {
        check_ajax_referer( 'wp_content_flow_collab', 'nonce' );
        
        $post_id = intval( $_POST['post_id'] ?? 0 );
        $role = sanitize_text_field( $_POST['role'] ?? 'editor' );
        
        if ( ! $post_id ) {
            wp_send_json_error( 'Post ID required' );
        }
        
        $result = $this->join_collaboration( $post_id, 0, $role );
        
        if ( $result ) {
            wp_send_json_success( $this->get_collaboration_status( $post_id ) );
        } else {
            wp_send_json_error( 'Failed to join collaboration' );
        }
    }
    
    /**
     * AJAX handler for leaving collaboration
     */
    public function ajax_leave_collaboration() {
        check_ajax_referer( 'wp_content_flow_collab', 'nonce' );
        
        $post_id = intval( $_POST['post_id'] ?? 0 );
        
        if ( ! $post_id ) {
            wp_send_json_error( 'Post ID required' );
        }
        
        $result = $this->leave_collaboration( $post_id );
        
        if ( $result ) {
            wp_send_json_success( [ 'message' => 'Left collaboration successfully' ] );
        } else {
            wp_send_json_error( 'Failed to leave collaboration' );
        }
    }
    
    /**
     * AJAX handler for getting collaboration status
     */
    public function ajax_get_collaboration_status() {
        check_ajax_referer( 'wp_content_flow_collab', 'nonce' );
        
        $post_id = intval( $_POST['post_id'] ?? 0 );
        
        if ( ! $post_id ) {
            wp_send_json_error( 'Post ID required' );
        }
        
        wp_send_json_success( $this->get_collaboration_status( $post_id ) );
    }
    
    /**
     * AJAX handler for assigning suggestions
     */
    public function ajax_assign_suggestion() {
        check_ajax_referer( 'wp_content_flow_collab', 'nonce' );
        
        $suggestion_id = intval( $_POST['suggestion_id'] ?? 0 );
        $assignee_id = intval( $_POST['assignee_id'] ?? 0 );
        
        if ( ! $suggestion_id || ! $assignee_id ) {
            wp_send_json_error( 'Suggestion ID and Assignee ID required' );
        }
        
        $result = $this->assign_suggestion( $suggestion_id, $assignee_id );
        
        if ( $result ) {
            wp_send_json_success( [ 'message' => 'Suggestion assigned successfully' ] );
        } else {
            wp_send_json_error( 'Failed to assign suggestion' );
        }
    }
    
    /**
     * AJAX handler for claiming suggestions
     */
    public function ajax_claim_suggestion() {
        check_ajax_referer( 'wp_content_flow_collab', 'nonce' );
        
        $suggestion_id = intval( $_POST['suggestion_id'] ?? 0 );
        
        if ( ! $suggestion_id ) {
            wp_send_json_error( 'Suggestion ID required' );
        }
        
        $result = $this->claim_suggestion( $suggestion_id );
        
        if ( $result ) {
            wp_send_json_success( [ 'message' => 'Suggestion claimed successfully' ] );
        } else {
            wp_send_json_error( 'Failed to claim suggestion - may already be assigned' );
        }
    }
    
    /**
     * Heartbeat API handler for real-time updates
     *
     * @param array $response Heartbeat response
     * @param array $data Heartbeat data
     * @return array Updated response
     */
    public function heartbeat_received( $response, $data ) {
        if ( isset( $data['wp_content_flow_collaboration'] ) ) {
            $collab_data = $data['wp_content_flow_collaboration'];
            $post_id = intval( $collab_data['post_id'] ?? 0 );
            
            if ( $post_id ) {
                $response['wp_content_flow_collaboration'] = $this->get_collaboration_status( $post_id );
            }
        }
        
        return $response;
    }
    
    /**
     * Enqueue collaboration scripts and styles
     */
    public function enqueue_collaboration_scripts() {
        global $pagenow;
        
        // Only enqueue on post editor pages
        if ( ! in_array( $pagenow, [ 'post.php', 'post-new.php' ] ) ) {
            return;
        }
        
        wp_enqueue_script(
            'wp-content-flow-collaboration',
            WP_CONTENT_FLOW_PLUGIN_URL . 'assets/js/collaboration.js',
            [ 'jquery', 'heartbeat' ],
            WP_CONTENT_FLOW_VERSION,
            true
        );
        
        wp_localize_script(
            'wp-content-flow-collaboration',
            'wpContentFlowCollab',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'wp_content_flow_collab' ),
                'currentUserId' => get_current_user_id(),
                'postId' => get_the_ID() ?: ( $_GET['post'] ?? 0 )
            ]
        );
        
        wp_enqueue_style(
            'wp-content-flow-collaboration',
            WP_CONTENT_FLOW_PLUGIN_URL . 'assets/css/collaboration.css',
            [],
            WP_CONTENT_FLOW_VERSION
        );
    }
    
    /**
     * Send in-app notification
     *
     * @param int $user_id User ID
     * @param string $type Notification type
     * @param array $data Notification data
     */
    private function send_in_app_notification( $user_id, $type, $data ) {
        $notification_key = 'wp_content_flow_notification_' . $user_id . '_' . uniqid();
        
        set_transient( $notification_key, [
            'type' => $type,
            'data' => $data,
            'timestamp' => time(),
            'read' => false
        ], DAY_IN_SECONDS );
        
        // Add to user's notification list
        $notifications = get_user_meta( $user_id, 'wp_content_flow_notifications', true );
        
        if ( ! is_array( $notifications ) ) {
            $notifications = [];
        }
        
        $notifications[] = $notification_key;
        
        // Keep only the latest 50 notifications
        $notifications = array_slice( $notifications, -50 );
        
        update_user_meta( $user_id, 'wp_content_flow_notifications', $notifications );
    }
    
    /**
     * Send email notification
     *
     * @param int $user_id User ID
     * @param string $type Notification type
     * @param array $data Notification data
     */
    private function send_email_notification( $user_id, $type, $data ) {
        $user = get_userdata( $user_id );
        
        if ( ! $user ) {
            return;
        }
        
        $subject = $this->get_notification_subject( $type, $data );
        $message = $this->get_notification_message( $type, $data );
        
        wp_mail( $user->user_email, $subject, $message );
    }
    
    /**
     * Get notification subject
     *
     * @param string $type Notification type
     * @param array $data Notification data
     * @return string Subject
     */
    private function get_notification_subject( $type, $data ) {
        switch ( $type ) {
            case 'suggestion_assigned':
                return 'AI Suggestion Assigned to You';
                
            case 'suggestion_claimed':
                return 'AI Suggestion Claimed';
                
            case 'collaboration_user_joined':
                return 'User Joined Collaboration Session';
                
            case 'shared_queue_created':
                return 'New Shared Suggestion Queue Created';
                
            default:
                return 'WordPress AI Content Flow Notification';
        }
    }
    
    /**
     * Get notification message
     *
     * @param string $type Notification type
     * @param array $data Notification data
     * @return string Message
     */
    private function get_notification_message( $type, $data ) {
        switch ( $type ) {
            case 'suggestion_assigned':
                $assigner = get_userdata( $data['assigner_id'] ?? 0 );
                $assigner_name = $assigner ? $assigner->display_name : 'Someone';
                return "{$assigner_name} assigned an AI suggestion to you. Please review it in the WordPress admin.";
                
            case 'collaboration_user_joined':
                $user = get_userdata( $data['user_id'] ?? 0 );
                $user_name = $user ? $user->display_name : 'A user';
                return "{$user_name} joined the collaboration session for a post you're working on.";
                
            default:
                return 'You have a new notification from WordPress AI Content Flow.';
        }
    }
}