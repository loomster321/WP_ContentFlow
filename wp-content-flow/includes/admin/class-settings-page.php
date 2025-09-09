<?php
/**
 * WordPress AI Content Flow - Settings Page
 *
 * Handles the plugin's settings page functionality and form processing.
 * Provides a comprehensive interface for configuring all plugin options.
 *
 * @package WP_Content_Flow
 * @subpackage Admin
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Settings Page Class
 */
class WP_Content_Flow_Settings_Page {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_init', array( $this, 'init_settings' ) );
        add_action( 'wp_ajax_wp_content_flow_test_connection', array( $this, 'ajax_test_connection' ) );
        add_action( 'wp_ajax_wp_content_flow_save_settings', array( $this, 'ajax_save_settings' ) );
        add_action( 'wp_ajax_wp_content_flow_reset_settings', array( $this, 'ajax_reset_settings' ) );
    }
    
    /**
     * Initialize settings fields and sections
     */
    public function init_settings() {
        // Register main settings
        register_setting(
            'wp_content_flow_settings',
            'wp_content_flow_settings',
            array(
                'type' => 'array',
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
                'default' => $this->get_default_settings()
            )
        );
        
        // Register API key settings
        $this->register_api_key_settings();
        
        // Add settings sections
        $this->add_settings_sections();
        
        // Add settings fields
        $this->add_settings_fields();
    }
    
    /**
     * Register API key settings for each provider
     */
    private function register_api_key_settings() {
        $providers = array( 'openai', 'anthropic', 'google' );
        
        foreach ( $providers as $provider ) {
            register_setting(
                'wp_content_flow_api_keys',
                'wp_content_flow_' . $provider . '_api_key',
                array(
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default' => ''
                )
            );
            
            register_setting(
                'wp_content_flow_api_keys',
                'wp_content_flow_' . $provider . '_endpoint',
                array(
                    'type' => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                    'default' => $this->get_default_endpoint( $provider )
                )
            );
        }
    }
    
    /**
     * Add settings sections
     */
    private function add_settings_sections() {
        // General Settings
        add_settings_section(
            'wp_content_flow_general',
            __( 'General Settings', 'wp-content-flow' ),
            array( $this, 'render_general_section_description' ),
            'wp-content-flow-settings'
        );
        
        // AI Provider Settings
        add_settings_section(
            'wp_content_flow_providers',
            __( 'AI Provider Configuration', 'wp-content-flow' ),
            array( $this, 'render_providers_section_description' ),
            'wp-content-flow-settings'
        );
        
        // Performance Settings
        add_settings_section(
            'wp_content_flow_performance',
            __( 'Performance & Caching', 'wp-content-flow' ),
            array( $this, 'render_performance_section_description' ),
            'wp-content-flow-settings'
        );
        
        // Rate Limiting Settings
        add_settings_section(
            'wp_content_flow_rate_limiting',
            __( 'Rate Limiting', 'wp-content-flow' ),
            array( $this, 'render_rate_limiting_section_description' ),
            'wp-content-flow-settings'
        );
        
        // Advanced Settings
        add_settings_section(
            'wp_content_flow_advanced',
            __( 'Advanced Settings', 'wp-content-flow' ),
            array( $this, 'render_advanced_section_description' ),
            'wp-content-flow-settings'
        );
    }
    
    /**
     * Add settings fields
     */
    private function add_settings_fields() {
        $this->add_general_fields();
        $this->add_provider_fields();
        $this->add_performance_fields();
        $this->add_rate_limiting_fields();
        $this->add_advanced_fields();
    }
    
    /**
     * Add general settings fields
     */
    private function add_general_fields() {
        add_settings_field(
            'ai_provider',
            __( 'Default AI Provider', 'wp-content-flow' ),
            array( $this, 'render_ai_provider_field' ),
            'wp-content-flow-settings',
            'wp_content_flow_general'
        );
        
        add_settings_field(
            'auto_save_enabled',
            __( 'Auto-save Content', 'wp-content-flow' ),
            array( $this, 'render_auto_save_field' ),
            'wp-content-flow-settings',
            'wp_content_flow_general'
        );
        
        add_settings_field(
            'content_logging',
            __( 'Content Logging', 'wp-content-flow' ),
            array( $this, 'render_content_logging_field' ),
            'wp-content-flow-settings',
            'wp_content_flow_general'
        );
    }
    
    /**
     * Add provider settings fields
     */
    private function add_provider_fields() {
        $providers = array(
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic Claude',
            'google' => 'Google AI'
        );
        
        foreach ( $providers as $provider_key => $provider_name ) {
            add_settings_field(
                $provider_key . '_api_key',
                sprintf( __( '%s API Key', 'wp-content-flow' ), $provider_name ),
                array( $this, 'render_api_key_field' ),
                'wp-content-flow-settings',
                'wp_content_flow_providers',
                array( 'provider' => $provider_key, 'name' => $provider_name )
            );
            
            add_settings_field(
                $provider_key . '_endpoint',
                sprintf( __( '%s Endpoint URL', 'wp-content-flow' ), $provider_name ),
                array( $this, 'render_endpoint_field' ),
                'wp-content-flow-settings',
                'wp_content_flow_providers',
                array( 'provider' => $provider_key, 'name' => $provider_name )
            );
        }
    }
    
    /**
     * Add performance settings fields
     */
    private function add_performance_fields() {
        add_settings_field(
            'cache_enabled',
            __( 'Enable Caching', 'wp-content-flow' ),
            array( $this, 'render_cache_enabled_field' ),
            'wp-content-flow-settings',
            'wp_content_flow_performance'
        );
        
        add_settings_field(
            'cache_duration',
            __( 'Cache Duration', 'wp-content-flow' ),
            array( $this, 'render_cache_duration_field' ),
            'wp-content-flow-settings',
            'wp_content_flow_performance'
        );
        
        add_settings_field(
            'cache_compression',
            __( 'Cache Compression', 'wp-content-flow' ),
            array( $this, 'render_cache_compression_field' ),
            'wp-content-flow-settings',
            'wp_content_flow_performance'
        );
    }
    
    /**
     * Add rate limiting settings fields
     */
    private function add_rate_limiting_fields() {
        add_settings_field(
            'rate_limit_enabled',
            __( 'Enable Rate Limiting', 'wp-content-flow' ),
            array( $this, 'render_rate_limit_enabled_field' ),
            'wp-content-flow-settings',
            'wp_content_flow_rate_limiting'
        );
        
        add_settings_field(
            'requests_per_minute',
            __( 'Requests per Minute', 'wp-content-flow' ),
            array( $this, 'render_requests_per_minute_field' ),
            'wp-content-flow-settings',
            'wp_content_flow_rate_limiting'
        );
        
        add_settings_field(
            'requests_per_hour',
            __( 'Requests per Hour', 'wp-content-flow' ),
            array( $this, 'render_requests_per_hour_field' ),
            'wp-content-flow-settings',
            'wp_content_flow_rate_limiting'
        );
        
        add_settings_field(
            'daily_token_limit',
            __( 'Daily Token Limit', 'wp-content-flow' ),
            array( $this, 'render_daily_token_limit_field' ),
            'wp-content-flow-settings',
            'wp_content_flow_rate_limiting'
        );
    }
    
    /**
     * Add advanced settings fields
     */
    private function add_advanced_fields() {
        add_settings_field(
            'debug_mode',
            __( 'Debug Mode', 'wp-content-flow' ),
            array( $this, 'render_debug_mode_field' ),
            'wp-content-flow-settings',
            'wp_content_flow_advanced'
        );
        
        add_settings_field(
            'webhook_enabled',
            __( 'Webhook Integration', 'wp-content-flow' ),
            array( $this, 'render_webhook_enabled_field' ),
            'wp-content-flow-settings',
            'wp_content_flow_advanced'
        );
        
        add_settings_field(
            'custom_css',
            __( 'Custom CSS', 'wp-content-flow' ),
            array( $this, 'render_custom_css_field' ),
            'wp-content-flow-settings',
            'wp_content_flow_advanced'
        );
    }
    
    /**
     * Get default settings
     */
    private function get_default_settings() {
        return array(
            'ai_provider' => 'openai',
            'cache_enabled' => true,
            'cache_duration' => 1800,
            'cache_compression' => true,
            'rate_limit_enabled' => true,
            'requests_per_minute' => 10,
            'requests_per_hour' => 100,
            'daily_token_limit' => 50000,
            'auto_save_enabled' => false,
            'content_logging' => true,
            'debug_mode' => false,
            'webhook_enabled' => false,
            'custom_css' => ''
        );
    }
    
    /**
     * Get default endpoint for provider
     */
    private function get_default_endpoint( $provider ) {
        switch ( $provider ) {
            case 'openai':
                return 'https://api.openai.com/v1';
            case 'anthropic':
                return 'https://api.anthropic.com';
            case 'google':
                return 'https://generativelanguage.googleapis.com/v1beta';
            default:
                return '';
        }
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();
        $defaults = $this->get_default_settings();
        
        foreach ( $defaults as $key => $default_value ) {
            if ( isset( $input[ $key ] ) ) {
                switch ( $key ) {
                    case 'ai_provider':
                        $sanitized[ $key ] = sanitize_text_field( $input[ $key ] );
                        break;
                        
                    case 'cache_duration':
                    case 'requests_per_minute':
                    case 'requests_per_hour':
                    case 'daily_token_limit':
                        $sanitized[ $key ] = absint( $input[ $key ] );
                        break;
                        
                    case 'cache_enabled':
                    case 'cache_compression':
                    case 'rate_limit_enabled':
                    case 'auto_save_enabled':
                    case 'content_logging':
                    case 'debug_mode':
                    case 'webhook_enabled':
                        $sanitized[ $key ] = (bool) $input[ $key ];
                        break;
                        
                    case 'custom_css':
                        $sanitized[ $key ] = sanitize_textarea_field( $input[ $key ] );
                        break;
                        
                    default:
                        $sanitized[ $key ] = sanitize_text_field( $input[ $key ] );
                        break;
                }
            } else {
                $sanitized[ $key ] = $default_value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * AJAX handler for testing API connection
     */
    public function ajax_test_connection() {
        check_ajax_referer( 'wp_content_flow_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( -1, 403 );
        }
        
        $provider = sanitize_text_field( $_POST['provider'] ?? '' );
        
        if ( empty( $provider ) ) {
            wp_send_json_error( array( 'message' => __( 'Provider not specified.', 'wp-content-flow' ) ) );
        }
        
        try {
            $ai_core = WP_Content_Flow_AI_Core::get_instance();
            $result = $ai_core->test_connection( $provider );
            
            if ( $result ) {
                wp_send_json_success( array(
                    'message' => sprintf( __( '%s connection successful!', 'wp-content-flow' ), ucfirst( $provider ) )
                ) );
            } else {
                wp_send_json_error( array(
                    'message' => sprintf( __( '%s connection failed.', 'wp-content-flow' ), ucfirst( $provider ) )
                ) );
            }
        } catch ( Exception $e ) {
            wp_send_json_error( array( 'message' => $e->getMessage() ) );
        }
    }
    
    /**
     * AJAX handler for saving settings
     */
    public function ajax_save_settings() {
        check_ajax_referer( 'wp_content_flow_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( -1, 403 );
        }
        
        $settings_data = $_POST['settings'] ?? array();
        
        // Sanitize and save settings
        $sanitized_settings = $this->sanitize_settings( $settings_data );
        update_option( 'wp_content_flow_settings', $sanitized_settings );
        
        wp_send_json_success( array(
            'message' => __( 'Settings saved successfully!', 'wp-content-flow' )
        ) );
    }
    
    /**
     * AJAX handler for resetting settings
     */
    public function ajax_reset_settings() {
        check_ajax_referer( 'wp_content_flow_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( -1, 403 );
        }
        
        $default_settings = $this->get_default_settings();
        update_option( 'wp_content_flow_settings', $default_settings );
        
        wp_send_json_success( array(
            'message' => __( 'Settings reset to defaults!', 'wp-content-flow' ),
            'settings' => $default_settings
        ) );
    }
    
    // Render methods for each field...
    
    public function render_general_section_description() {
        echo '<p>' . __( 'Configure general plugin behavior and default settings.', 'wp-content-flow' ) . '</p>';
    }
    
    public function render_providers_section_description() {
        echo '<p>' . __( 'Configure API credentials for different AI providers. At least one provider must be configured.', 'wp-content-flow' ) . '</p>';
    }
    
    public function render_performance_section_description() {
        echo '<p>' . __( 'Optimize plugin performance with caching and content optimization.', 'wp-content-flow' ) . '</p>';
    }
    
    public function render_rate_limiting_section_description() {
        echo '<p>' . __( 'Control API usage to prevent quota exhaustion and manage costs.', 'wp-content-flow' ) . '</p>';
    }
    
    public function render_advanced_section_description() {
        echo '<p>' . __( 'Advanced configuration options for power users and developers.', 'wp-content-flow' ) . '</p>';
    }
    
    public function render_ai_provider_field() {
        $settings = get_option( 'wp_content_flow_settings', $this->get_default_settings() );
        $current_provider = $settings['ai_provider'] ?? 'openai';
        
        $providers = array(
            'openai' => 'OpenAI (GPT-3.5, GPT-4)',
            'anthropic' => 'Anthropic (Claude)',
            'google' => 'Google AI (Gemini)'
        );
        
        echo '<select name="wp_content_flow_settings[ai_provider]" id="ai_provider">';
        foreach ( $providers as $key => $label ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $key ),
                selected( $current_provider, $key, false ),
                esc_html( $label )
            );
        }
        echo '</select>';
        echo '<p class="description">' . __( 'Default AI provider for new workflows.', 'wp-content-flow' ) . '</p>';
    }
    
    public function render_api_key_field( $args ) {
        $provider = $args['provider'];
        $name = $args['name'];
        $option_name = 'wp_content_flow_' . $provider . '_api_key';
        $current_value = get_option( $option_name, '' );
        
        printf(
            '<input type="password" name="%s" id="%s" value="%s" class="regular-text" autocomplete="new-password" />',
            esc_attr( $option_name ),
            esc_attr( $option_name ),
            esc_attr( $current_value )
        );
        
        if ( ! empty( $current_value ) ) {
            printf(
                '<button type="button" class="button button-secondary test-connection" data-provider="%s" style="margin-left: 10px;">%s</button>',
                esc_attr( $provider ),
                __( 'Test Connection', 'wp-content-flow' )
            );
        }
        
        printf(
            '<p class="description">%s</p>',
            sprintf( __( 'API key for %s. Keep this secure and do not share.', 'wp-content-flow' ), $name )
        );
    }
    
    public function render_endpoint_field( $args ) {
        $provider = $args['provider'];
        $name = $args['name'];
        $option_name = 'wp_content_flow_' . $provider . '_endpoint';
        $current_value = get_option( $option_name, $this->get_default_endpoint( $provider ) );
        
        printf(
            '<input type="url" name="%s" id="%s" value="%s" class="regular-text" />',
            esc_attr( $option_name ),
            esc_attr( $option_name ),
            esc_attr( $current_value )
        );
        
        printf(
            '<p class="description">%s</p>',
            sprintf( __( 'API endpoint URL for %s. Use default unless using a custom deployment.', 'wp-content-flow' ), $name )
        );
    }
    
    public function render_cache_enabled_field() {
        $settings = get_option( 'wp_content_flow_settings', $this->get_default_settings() );
        $checked = $settings['cache_enabled'] ?? true;
        
        printf(
            '<input type="checkbox" name="wp_content_flow_settings[cache_enabled]" id="cache_enabled" value="1" %s />',
            checked( $checked, true, false )
        );
        echo '<label for="cache_enabled">' . __( 'Enable response caching to improve performance and reduce API costs.', 'wp-content-flow' ) . '</label>';
    }
    
    public function render_cache_duration_field() {
        $settings = get_option( 'wp_content_flow_settings', $this->get_default_settings() );
        $duration = $settings['cache_duration'] ?? 1800;
        
        printf(
            '<input type="number" name="wp_content_flow_settings[cache_duration]" id="cache_duration" value="%d" min="300" max="86400" class="small-text" />',
            esc_attr( $duration )
        );
        echo ' <span>' . __( 'seconds', 'wp-content-flow' ) . '</span>';
        echo '<p class="description">' . __( 'How long to cache AI responses (300-86400 seconds).', 'wp-content-flow' ) . '</p>';
    }
    
    public function render_rate_limit_enabled_field() {
        $settings = get_option( 'wp_content_flow_settings', $this->get_default_settings() );
        $checked = $settings['rate_limit_enabled'] ?? true;
        
        printf(
            '<input type="checkbox" name="wp_content_flow_settings[rate_limit_enabled]" id="rate_limit_enabled" value="1" %s />',
            checked( $checked, true, false )
        );
        echo '<label for="rate_limit_enabled">' . __( 'Enable rate limiting to prevent API quota exhaustion.', 'wp-content-flow' ) . '</label>';
    }
    
    public function render_requests_per_minute_field() {
        $settings = get_option( 'wp_content_flow_settings', $this->get_default_settings() );
        $rpm = $settings['requests_per_minute'] ?? 10;
        
        printf(
            '<input type="number" name="wp_content_flow_settings[requests_per_minute]" id="requests_per_minute" value="%d" min="1" max="100" class="small-text" />',
            esc_attr( $rpm )
        );
        echo '<p class="description">' . __( 'Maximum requests per minute per user.', 'wp-content-flow' ) . '</p>';
    }
    
    public function render_debug_mode_field() {
        $settings = get_option( 'wp_content_flow_settings', $this->get_default_settings() );
        $checked = $settings['debug_mode'] ?? false;
        
        printf(
            '<input type="checkbox" name="wp_content_flow_settings[debug_mode]" id="debug_mode" value="1" %s />',
            checked( $checked, true, false )
        );
        echo '<label for="debug_mode">' . __( 'Enable detailed logging for troubleshooting (may impact performance).', 'wp-content-flow' ) . '</label>';
    }
    
    // Additional render methods would continue here for all other fields...
}