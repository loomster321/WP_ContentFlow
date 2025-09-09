<?php
/**
 * WordPress AI Content Flow - Admin Menu
 *
 * Handles the plugin's admin menu structure and navigation.
 * Provides centralized menu management for all plugin admin pages.
 *
 * @package WP_Content_Flow
 * @subpackage Admin
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin Menu Class
 */
class WP_Content_Flow_Admin_Menu {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_init', array( $this, 'admin_init' ) );
        add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
        add_filter( 'plugin_action_links_' . WP_CONTENT_FLOW_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        // Check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // Main menu page
        add_menu_page(
            __( 'AI Content Flow', 'wp-content-flow' ),
            __( 'AI Content Flow', 'wp-content-flow' ),
            'manage_options',
            'wp-content-flow',
            array( $this, 'render_main_page' ),
            'data:image/svg+xml;base64,' . base64_encode( $this->get_menu_icon() ),
            30
        );
        
        // Dashboard submenu
        add_submenu_page(
            'wp-content-flow',
            __( 'Dashboard', 'wp-content-flow' ),
            __( 'Dashboard', 'wp-content-flow' ),
            'manage_options',
            'wp-content-flow',
            array( $this, 'render_main_page' )
        );
        
        // Workflows submenu
        add_submenu_page(
            'wp-content-flow',
            __( 'Workflows', 'wp-content-flow' ),
            __( 'Workflows', 'wp-content-flow' ),
            'manage_options',
            'wp-content-flow-workflows',
            array( $this, 'render_workflows_page' )
        );
        
        // Settings submenu
        add_submenu_page(
            'wp-content-flow',
            __( 'Settings', 'wp-content-flow' ),
            __( 'Settings', 'wp-content-flow' ),
            'manage_options',
            'wp-content-flow-settings',
            array( $this, 'render_settings_page' )
        );
        
        // AI Providers submenu
        add_submenu_page(
            'wp-content-flow',
            __( 'AI Providers', 'wp-content-flow' ),
            __( 'AI Providers', 'wp-content-flow' ),
            'manage_options',
            'wp-content-flow-providers',
            array( $this, 'render_providers_page' )
        );
        
        // Usage & Analytics submenu
        add_submenu_page(
            'wp-content-flow',
            __( 'Usage & Analytics', 'wp-content-flow' ),
            __( 'Analytics', 'wp-content-flow' ),
            'manage_options',
            'wp-content-flow-analytics',
            array( $this, 'render_analytics_page' )
        );
        
        // Help & Documentation submenu
        add_submenu_page(
            'wp-content-flow',
            __( 'Help & Documentation', 'wp-content-flow' ),
            __( 'Help', 'wp-content-flow' ),
            'manage_options',
            'wp-content-flow-help',
            array( $this, 'render_help_page' )
        );
    }
    
    /**
     * Initialize admin functionality
     */
    public function admin_init() {
        // Register plugin settings
        register_setting( 
            'wp_content_flow_settings_group', 
            'wp_content_flow_settings',
            array( $this, 'sanitize_settings' )
        );
        
        // Add settings sections and fields
        $this->add_settings_sections();
        
        // Handle admin actions
        $this->handle_admin_actions();
    }
    
    /**
     * Handle admin form submissions and actions
     */
    private function handle_admin_actions() {
        if ( ! isset( $_POST['wp_content_flow_action'] ) || ! wp_verify_nonce( $_POST['wp_content_flow_nonce'], 'wp_content_flow_admin_action' ) ) {
            return;
        }
        
        $action = sanitize_text_field( $_POST['wp_content_flow_action'] );
        
        switch ( $action ) {
            case 'test_api_connection':
                $this->handle_test_api_connection();
                break;
                
            case 'clear_cache':
                $this->handle_clear_cache();
                break;
                
            case 'reset_settings':
                $this->handle_reset_settings();
                break;
                
            case 'export_workflows':
                $this->handle_export_workflows();
                break;
                
            case 'import_workflows':
                $this->handle_import_workflows();
                break;
        }
    }
    
    /**
     * Test API connection
     */
    private function handle_test_api_connection() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        
        $provider = sanitize_text_field( $_POST['provider'] ?? '' );
        
        try {
            // Load AI Core and test connection
            $ai_core = WP_Content_Flow_AI_Core::get_instance();
            $result = $ai_core->test_connection( $provider );
            
            if ( $result ) {
                add_settings_error(
                    'wp_content_flow_messages',
                    'api_test_success',
                    sprintf( __( '%s API connection successful!', 'wp-content-flow' ), ucfirst( $provider ) ),
                    'success'
                );
            } else {
                add_settings_error(
                    'wp_content_flow_messages',
                    'api_test_failed',
                    sprintf( __( '%s API connection failed. Please check your credentials.', 'wp-content-flow' ), ucfirst( $provider ) ),
                    'error'
                );
            }
        } catch ( Exception $e ) {
            add_settings_error(
                'wp_content_flow_messages',
                'api_test_error',
                sprintf( __( 'API test error: %s', 'wp-content-flow' ), $e->getMessage() ),
                'error'
            );
        }
    }
    
    /**
     * Clear plugin cache
     */
    private function handle_clear_cache() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        
        // Clear WordPress transients
        delete_transient( 'wp_content_flow_workflows' );
        delete_transient( 'wp_content_flow_analytics' );
        delete_transient( 'wp_content_flow_usage_stats' );
        
        // Clear any cached AI responses
        global $wpdb;
        $cache_table = $wpdb->prefix . 'wp_content_flow_cache';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$cache_table'" ) === $cache_table ) {
            $wpdb->query( "TRUNCATE TABLE $cache_table" );
        }
        
        add_settings_error(
            'wp_content_flow_messages',
            'cache_cleared',
            __( 'Cache cleared successfully!', 'wp-content-flow' ),
            'success'
        );
    }
    
    /**
     * Reset plugin settings
     */
    private function handle_reset_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        
        // Reset to default settings
        $default_settings = array(
            'ai_provider' => 'openai',
            'cache_enabled' => true,
            'cache_duration' => 1800,
            'rate_limit_enabled' => true,
            'requests_per_minute' => 10,
            'requests_per_hour' => 100,
            'daily_token_limit' => 50000,
        );
        
        update_option( 'wp_content_flow_settings', $default_settings );
        
        add_settings_error(
            'wp_content_flow_messages',
            'settings_reset',
            __( 'Settings reset to defaults successfully!', 'wp-content-flow' ),
            'success'
        );
    }
    
    /**
     * Add settings sections and fields
     */
    private function add_settings_sections() {
        // General Settings Section
        add_settings_section(
            'wp_content_flow_general',
            __( 'General Settings', 'wp-content-flow' ),
            array( $this, 'render_general_section' ),
            'wp-content-flow-settings'
        );
        
        // AI Provider Settings Section
        add_settings_section(
            'wp_content_flow_providers',
            __( 'AI Provider Settings', 'wp-content-flow' ),
            array( $this, 'render_providers_section' ),
            'wp-content-flow-settings'
        );
        
        // Performance Settings Section
        add_settings_section(
            'wp_content_flow_performance',
            __( 'Performance Settings', 'wp-content-flow' ),
            array( $this, 'render_performance_section' ),
            'wp-content-flow-settings'
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load on plugin pages
        if ( strpos( $hook, 'wp-content-flow' ) === false ) {
            return;
        }
        
        wp_enqueue_style(
            'wp-content-flow-admin',
            WP_CONTENT_FLOW_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WP_CONTENT_FLOW_VERSION
        );
        
        wp_enqueue_script(
            'wp-content-flow-admin',
            WP_CONTENT_FLOW_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery', 'wp-api' ),
            WP_CONTENT_FLOW_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script(
            'wp-content-flow-admin',
            'wpContentFlowAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'wp_content_flow_admin_nonce' ),
                'apiUrl' => rest_url( 'wp-content-flow/v1/' ),
                'strings' => array(
                    'confirmDelete' => __( 'Are you sure you want to delete this workflow?', 'wp-content-flow' ),
                    'confirmReset' => __( 'Are you sure you want to reset all settings?', 'wp-content-flow' ),
                    'testing' => __( 'Testing connection...', 'wp-content-flow' ),
                    'success' => __( 'Success!', 'wp-content-flow' ),
                    'error' => __( 'Error!', 'wp-content-flow' )
                )
            )
        );
    }
    
    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        settings_errors( 'wp_content_flow_messages' );
        
        // Show activation notice
        if ( get_option( 'wp_content_flow_activated' ) ) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . __( 'WordPress AI Content Flow has been activated successfully! Configure your AI providers to get started.', 'wp-content-flow' ) . '</p>';
            echo '</div>';
            delete_option( 'wp_content_flow_activated' );
        }
        
        // Check for missing API keys
        $settings = get_option( 'wp_content_flow_settings', array() );
        $provider = $settings['ai_provider'] ?? 'openai';
        $api_key_option = 'wp_content_flow_' . $provider . '_api_key';
        
        if ( empty( get_option( $api_key_option ) ) ) {
            echo '<div class="notice notice-warning">';
            echo '<p>' . sprintf(
                __( 'Please configure your %s API key in the <a href="%s">settings page</a> to start using AI content generation.', 'wp-content-flow' ),
                ucfirst( $provider ),
                admin_url( 'admin.php?page=wp-content-flow-settings' )
            ) . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Add plugin action links
     */
    public function add_action_links( $links ) {
        $action_links = array(
            'settings' => '<a href="' . admin_url( 'admin.php?page=wp-content-flow-settings' ) . '">' . __( 'Settings', 'wp-content-flow' ) . '</a>',
            'workflows' => '<a href="' . admin_url( 'admin.php?page=wp-content-flow-workflows' ) . '">' . __( 'Workflows', 'wp-content-flow' ) . '</a>',
        );
        
        return array_merge( $action_links, $links );
    }
    
    /**
     * Get menu icon SVG
     */
    private function get_menu_icon() {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
        </svg>';
    }
    
    /**
     * Sanitize settings input
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();
        
        if ( isset( $input['ai_provider'] ) ) {
            $sanitized['ai_provider'] = sanitize_text_field( $input['ai_provider'] );
        }
        
        if ( isset( $input['cache_enabled'] ) ) {
            $sanitized['cache_enabled'] = (bool) $input['cache_enabled'];
        }
        
        if ( isset( $input['cache_duration'] ) ) {
            $sanitized['cache_duration'] = absint( $input['cache_duration'] );
        }
        
        if ( isset( $input['rate_limit_enabled'] ) ) {
            $sanitized['rate_limit_enabled'] = (bool) $input['rate_limit_enabled'];
        }
        
        if ( isset( $input['requests_per_minute'] ) ) {
            $sanitized['requests_per_minute'] = absint( $input['requests_per_minute'] );
        }
        
        if ( isset( $input['requests_per_hour'] ) ) {
            $sanitized['requests_per_hour'] = absint( $input['requests_per_hour'] );
        }
        
        if ( isset( $input['daily_token_limit'] ) ) {
            $sanitized['daily_token_limit'] = absint( $input['daily_token_limit'] );
        }
        
        return $sanitized;
    }
    
    /**
     * Render main dashboard page
     */
    public function render_main_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        
        include WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/admin/views/dashboard.php';
    }
    
    /**
     * Render workflows page
     */
    public function render_workflows_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        
        include WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/admin/views/workflows.php';
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        
        include WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/admin/views/settings.php';
    }
    
    /**
     * Render providers page
     */
    public function render_providers_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        
        include WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/admin/views/providers.php';
    }
    
    /**
     * Render analytics page
     */
    public function render_analytics_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        
        include WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/admin/views/analytics.php';
    }
    
    /**
     * Render help page
     */
    public function render_help_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        
        include WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/admin/views/help.php';
    }
    
    /**
     * Render general settings section
     */
    public function render_general_section() {
        echo '<p>' . __( 'Configure general plugin settings and behavior.', 'wp-content-flow' ) . '</p>';
    }
    
    /**
     * Render providers settings section
     */
    public function render_providers_section() {
        echo '<p>' . __( 'Configure AI provider credentials and settings.', 'wp-content-flow' ) . '</p>';
    }
    
    /**
     * Render performance settings section
     */
    public function render_performance_section() {
        echo '<p>' . __( 'Configure caching, rate limiting, and performance settings.', 'wp-content-flow' ) . '</p>';
    }
}