<?php
/**
 * WordPress AI Content Flow - Admin Notices
 *
 * Handles admin notices, alerts, and user notifications throughout the plugin.
 * Provides consistent messaging and notification management.
 *
 * @package WP_Content_Flow
 * @subpackage Admin
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin Notices Class
 */
class WP_Content_Flow_Admin_Notices {
    
    /**
     * Notice types
     */
    const NOTICE_SUCCESS = 'success';
    const NOTICE_INFO = 'info';
    const NOTICE_WARNING = 'warning';
    const NOTICE_ERROR = 'error';
    
    /**
     * Stored notices option key
     */
    const NOTICES_OPTION = 'wp_content_flow_admin_notices';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
        add_action( 'wp_ajax_wp_content_flow_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_notices_script' ) );
        add_action( 'admin_init', array( $this, 'handle_notice_actions' ) );
    }
    
    /**
     * Add a notice to be displayed
     *
     * @param string $message The notice message
     * @param string $type Notice type (success, info, warning, error)
     * @param bool $dismissible Whether the notice can be dismissed
     * @param string $id Unique notice ID for dismissal
     * @param array $actions Optional action buttons
     */
    public static function add_notice( $message, $type = self::NOTICE_INFO, $dismissible = true, $id = '', $actions = array() ) {
        $notices = get_option( self::NOTICES_OPTION, array() );
        
        $notice = array(
            'message' => $message,
            'type' => $type,
            'dismissible' => $dismissible,
            'id' => $id ?: uniqid( 'notice_' ),
            'actions' => $actions,
            'timestamp' => time()
        );
        
        $notices[] = $notice;
        update_option( self::NOTICES_OPTION, $notices );
    }
    
    /**
     * Add success notice
     */
    public static function add_success( $message, $dismissible = true, $id = '', $actions = array() ) {
        self::add_notice( $message, self::NOTICE_SUCCESS, $dismissible, $id, $actions );
    }
    
    /**
     * Add info notice
     */
    public static function add_info( $message, $dismissible = true, $id = '', $actions = array() ) {
        self::add_notice( $message, self::NOTICE_INFO, $dismissible, $id, $actions );
    }
    
    /**
     * Add warning notice
     */
    public static function add_warning( $message, $dismissible = true, $id = '', $actions = array() ) {
        self::add_notice( $message, self::NOTICE_WARNING, $dismissible, $id, $actions );
    }
    
    /**
     * Add error notice
     */
    public static function add_error( $message, $dismissible = true, $id = '', $actions = array() ) {
        self::add_notice( $message, self::NOTICE_ERROR, $dismissible, $id, $actions );
    }
    
    /**
     * Display all admin notices
     */
    public function display_admin_notices() {
        // Only show on plugin pages or when specifically requested
        if ( ! $this->should_display_notices() ) {
            return;
        }
        
        $notices = get_option( self::NOTICES_OPTION, array() );
        
        if ( empty( $notices ) ) {
            return;
        }
        
        $displayed_notices = array();
        $remaining_notices = array();
        
        foreach ( $notices as $notice ) {
            // Check if notice should still be displayed
            if ( $this->should_display_notice( $notice ) ) {
                $this->render_notice( $notice );
                $displayed_notices[] = $notice['id'];
            } else {
                $remaining_notices[] = $notice;
            }
        }
        
        // Update notices option with remaining notices
        update_option( self::NOTICES_OPTION, $remaining_notices );
        
        // Display contextual notices
        $this->display_contextual_notices();
    }
    
    /**
     * Check if notices should be displayed on current page
     */
    private function should_display_notices() {
        global $pagenow;
        
        // Show on plugin pages
        if ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'wp-content-flow' ) !== false ) {
            return true;
        }
        
        // Show on main admin pages when explicitly set
        if ( in_array( $pagenow, array( 'index.php', 'plugins.php' ) ) ) {
            return true;
        }
        
        // Show in block editor when our blocks are present
        if ( $pagenow === 'post.php' || $pagenow === 'post-new.php' ) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if specific notice should be displayed
     */
    private function should_display_notice( $notice ) {
        // Check if notice has expired (24 hours for most notices)
        $max_age = apply_filters( 'wp_content_flow_notice_max_age', 24 * HOUR_IN_SECONDS );
        if ( ( time() - $notice['timestamp'] ) > $max_age ) {
            return false;
        }
        
        // Check if notice was dismissed
        if ( $notice['dismissible'] && $this->is_notice_dismissed( $notice['id'] ) ) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Render a notice
     */
    private function render_notice( $notice ) {
        $classes = array(
            'notice',
            'notice-' . $notice['type']
        );
        
        if ( $notice['dismissible'] ) {
            $classes[] = 'is-dismissible';
        }
        
        printf(
            '<div class="%s" data-notice-id="%s">',
            esc_attr( implode( ' ', $classes ) ),
            esc_attr( $notice['id'] )
        );
        
        echo '<p>' . wp_kses_post( $notice['message'] ) . '</p>';
        
        // Render action buttons
        if ( ! empty( $notice['actions'] ) ) {
            echo '<p>';
            foreach ( $notice['actions'] as $action ) {
                printf(
                    '<a href="%s" class="button button-%s" %s>%s</a> ',
                    esc_url( $action['url'] ),
                    esc_attr( $action['type'] ?? 'secondary' ),
                    isset( $action['target'] ) ? 'target="' . esc_attr( $action['target'] ) . '"' : '',
                    esc_html( $action['label'] )
                );
            }
            echo '</p>';
        }
        
        echo '</div>';
    }
    
    /**
     * Display contextual notices based on current state
     */
    private function display_contextual_notices() {
        // Check for missing API configuration
        $this->check_api_configuration();
        
        // Check for plugin updates
        $this->check_plugin_updates();
        
        // Check for WordPress compatibility
        $this->check_wordpress_compatibility();
        
        // Check for performance issues
        $this->check_performance_issues();
    }
    
    /**
     * Check API configuration status
     */
    private function check_api_configuration() {
        $settings = get_option( 'wp_content_flow_settings', array() );
        $provider = $settings['ai_provider'] ?? 'openai';
        $api_key_option = 'wp_content_flow_' . $provider . '_api_key';
        $api_key = get_option( $api_key_option );
        
        if ( empty( $api_key ) ) {
            $message = sprintf(
                __( 'No API key configured for %s. <a href="%s">Configure your API settings</a> to start using AI features.', 'wp-content-flow' ),
                ucfirst( $provider ),
                admin_url( 'admin.php?page=wp-content-flow-settings' )
            );
            
            $this->render_notice( array(
                'message' => $message,
                'type' => self::NOTICE_WARNING,
                'dismissible' => true,
                'id' => 'missing_api_key_' . $provider,
                'actions' => array(
                    array(
                        'label' => __( 'Configure Settings', 'wp-content-flow' ),
                        'url' => admin_url( 'admin.php?page=wp-content-flow-settings' ),
                        'type' => 'primary'
                    )
                )
            ) );
        }
    }
    
    /**
     * Check plugin updates
     */
    private function check_plugin_updates() {
        if ( ! current_user_can( 'update_plugins' ) ) {
            return;
        }
        
        // This would typically check with a remote server for updates
        // For now, we'll show a placeholder notice if debug mode is enabled
        $settings = get_option( 'wp_content_flow_settings', array() );
        
        if ( ! empty( $settings['debug_mode'] ) ) {
            $this->render_notice( array(
                'message' => __( 'Debug mode is enabled. Remember to disable it in production for optimal performance.', 'wp-content-flow' ),
                'type' => self::NOTICE_INFO,
                'dismissible' => true,
                'id' => 'debug_mode_warning',
                'actions' => array()
            ) );
        }
    }
    
    /**
     * Check WordPress compatibility
     */
    private function check_wordpress_compatibility() {
        // Check WordPress version
        $wp_version = get_bloginfo( 'version' );
        if ( version_compare( $wp_version, '6.0', '<' ) ) {
            $this->render_notice( array(
                'message' => sprintf(
                    __( 'WordPress AI Content Flow requires WordPress 6.0 or higher. You are running version %s. Please update WordPress.', 'wp-content-flow' ),
                    $wp_version
                ),
                'type' => self::NOTICE_ERROR,
                'dismissible' => false,
                'id' => 'wordpress_version_incompatible'
            ) );
        }
        
        // Check PHP version
        if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
            $this->render_notice( array(
                'message' => sprintf(
                    __( 'WordPress AI Content Flow requires PHP 8.1 or higher. You are running version %s. Please contact your hosting provider.', 'wp-content-flow' ),
                    PHP_VERSION
                ),
                'type' => self::NOTICE_ERROR,
                'dismissible' => false,
                'id' => 'php_version_incompatible'
            ) );
        }
    }
    
    /**
     * Check for performance issues
     */
    private function check_performance_issues() {
        $settings = get_option( 'wp_content_flow_settings', array() );
        
        // Check if caching is disabled
        if ( empty( $settings['cache_enabled'] ) ) {
            $this->render_notice( array(
                'message' => sprintf(
                    __( 'Caching is disabled, which may impact performance and increase API costs. <a href="%s">Enable caching</a> for better performance.', 'wp-content-flow' ),
                    admin_url( 'admin.php?page=wp-content-flow-settings' )
                ),
                'type' => self::NOTICE_WARNING,
                'dismissible' => true,
                'id' => 'caching_disabled'
            ) );
        }
        
        // Check rate limiting
        if ( empty( $settings['rate_limit_enabled'] ) ) {
            $this->render_notice( array(
                'message' => sprintf(
                    __( 'Rate limiting is disabled, which may lead to API quota exhaustion. <a href="%s">Enable rate limiting</a> for better control.', 'wp-content-flow' ),
                    admin_url( 'admin.php?page=wp-content-flow-settings' )
                ),
                'type' => self::NOTICE_INFO,
                'dismissible' => true,
                'id' => 'rate_limiting_disabled'
            ) );
        }
    }
    
    /**
     * Check if notice was dismissed
     */
    private function is_notice_dismissed( $notice_id ) {
        $dismissed_notices = get_user_meta( get_current_user_id(), 'wp_content_flow_dismissed_notices', true );
        
        if ( ! is_array( $dismissed_notices ) ) {
            return false;
        }
        
        return in_array( $notice_id, $dismissed_notices );
    }
    
    /**
     * Handle notice actions from URL parameters
     */
    public function handle_notice_actions() {
        if ( ! isset( $_GET['wp_content_flow_action'] ) || ! isset( $_GET['_wpnonce'] ) ) {
            return;
        }
        
        $action = sanitize_text_field( $_GET['wp_content_flow_action'] );
        
        if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'wp_content_flow_notice_action' ) ) {
            return;
        }
        
        switch ( $action ) {
            case 'dismiss_notice':
                $notice_id = sanitize_text_field( $_GET['notice_id'] ?? '' );
                if ( $notice_id ) {
                    $this->dismiss_notice( $notice_id );
                }
                break;
        }
        
        // Redirect to clean URL
        wp_safe_redirect( remove_query_arg( array( 'wp_content_flow_action', 'notice_id', '_wpnonce' ) ) );
        exit;
    }
    
    /**
     * Dismiss a notice
     */
    private function dismiss_notice( $notice_id ) {
        $dismissed_notices = get_user_meta( get_current_user_id(), 'wp_content_flow_dismissed_notices', true );
        
        if ( ! is_array( $dismissed_notices ) ) {
            $dismissed_notices = array();
        }
        
        if ( ! in_array( $notice_id, $dismissed_notices ) ) {
            $dismissed_notices[] = $notice_id;
            update_user_meta( get_current_user_id(), 'wp_content_flow_dismissed_notices', $dismissed_notices );
        }
    }
    
    /**
     * AJAX handler for dismissing notices
     */
    public function ajax_dismiss_notice() {
        check_ajax_referer( 'wp_content_flow_admin_nonce', 'nonce' );
        
        $notice_id = sanitize_text_field( $_POST['notice_id'] ?? '' );
        
        if ( empty( $notice_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid notice ID.', 'wp-content-flow' ) ) );
        }
        
        $this->dismiss_notice( $notice_id );
        
        wp_send_json_success( array( 'message' => __( 'Notice dismissed.', 'wp-content-flow' ) ) );
    }
    
    /**
     * Enqueue notices script
     */
    public function enqueue_notices_script( $hook ) {
        // Only load on admin pages where notices are shown
        if ( ! $this->should_display_notices() ) {
            return;
        }
        
        wp_enqueue_script(
            'wp-content-flow-admin-notices',
            WP_CONTENT_FLOW_PLUGIN_URL . 'assets/js/admin-notices.js',
            array( 'jquery' ),
            WP_CONTENT_FLOW_VERSION,
            true
        );
        
        wp_localize_script(
            'wp-content-flow-admin-notices',
            'wpContentFlowNotices',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'wp_content_flow_admin_nonce' )
            )
        );
    }
    
    /**
     * Clear all notices
     */
    public static function clear_notices() {
        delete_option( self::NOTICES_OPTION );
    }
    
    /**
     * Clear dismissed notices for user
     */
    public static function clear_dismissed_notices( $user_id = null ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        
        delete_user_meta( $user_id, 'wp_content_flow_dismissed_notices' );
    }
    
    /**
     * Get all pending notices
     */
    public static function get_notices() {
        return get_option( self::NOTICES_OPTION, array() );
    }
}