<?php
/**
 * REST API Settings Controller
 * 
 * @package WP_Content_Flow
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Settings REST controller
 */
class WP_Content_Flow_Settings_Controller extends WP_REST_Controller {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->namespace = 'wp-content-flow/v1';
        $this->rest_base = 'settings';
    }
    
    /**
     * Register routes
     */
    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_settings' ],
                'permission_callback' => [ $this, 'get_settings_permissions_check' ],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'update_settings' ],
                'permission_callback' => [ $this, 'update_settings_permissions_check' ],
                'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
            ],
            'schema' => [ $this, 'get_public_item_schema' ],
        ]);
    }
    
    /**
     * Check permissions for getting settings
     * 
     * @param \WP_REST_Request $request Request object
     * @return bool|WP_Error
     */
    public function get_settings_permissions_check( $request ) {
        // Allow anyone who can edit posts to read settings
        if ( ! current_user_can( 'edit_posts' ) ) {
            return new WP_Error(
                'rest_forbidden',
                esc_html__( 'You do not have permission to view settings.', 'wp-content-flow' ),
                [ 'status' => 403 ]
            );
        }
        
        return true;
    }
    
    /**
     * Check permissions for updating settings
     * 
     * @param \WP_REST_Request $request Request object
     * @return bool|WP_Error
     */
    public function update_settings_permissions_check( $request ) {
        // Only administrators can update settings
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error(
                'rest_forbidden',
                esc_html__( 'You do not have permission to update settings.', 'wp-content-flow' ),
                [ 'status' => 403 ]
            );
        }
        
        return true;
    }
    
    /**
     * Get plugin settings
     * 
     * @param \WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_settings( $request ) {
        $settings = [
            'cache_enabled'        => get_option( 'wp_content_flow_cache_enabled', true ),
            'cache_duration'       => intval( get_option( 'wp_content_flow_cache_duration', 1800 ) ),
            'rate_limit_enabled'   => get_option( 'wp_content_flow_rate_limit_enabled', true ),
            'requests_per_minute'  => intval( get_option( 'wp_content_flow_requests_per_minute', 10 ) ),
            'auto_save_enabled'    => get_option( 'wp_content_flow_auto_save_enabled', true ),
            'debug_mode'           => get_option( 'wp_content_flow_debug_mode', false ),
            'default_ai_provider'  => get_option( 'wp_content_flow_default_ai_provider', 'openai' ),
            'content_safety'       => get_option( 'wp_content_flow_content_safety', true ),
            'show_confidence'      => get_option( 'wp_content_flow_show_confidence', true ),
            'max_content_length'   => intval( get_option( 'wp_content_flow_max_content_length', 5000 ) ),
            'enable_collaboration' => get_option( 'wp_content_flow_enable_collaboration', true ),
            'enable_audit_trail'   => get_option( 'wp_content_flow_enable_audit_trail', true ),
        ];
        
        return rest_ensure_response( $settings );
    }
    
    /**
     * Update plugin settings
     * 
     * @param \WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function update_settings( $request ) {
        $params = $request->get_params();
        
        // List of allowed settings to update
        $allowed_settings = [
            'cache_enabled',
            'cache_duration',
            'rate_limit_enabled',
            'requests_per_minute',
            'auto_save_enabled',
            'debug_mode',
            'default_ai_provider',
            'content_safety',
            'show_confidence',
            'max_content_length',
            'enable_collaboration',
            'enable_audit_trail',
        ];
        
        $updated = [];
        
        foreach ( $allowed_settings as $setting ) {
            if ( isset( $params[ $setting ] ) ) {
                $value = $params[ $setting ];
                
                // Validate and sanitize based on setting type
                switch ( $setting ) {
                    case 'cache_duration':
                    case 'requests_per_minute':
                    case 'max_content_length':
                        $value = absint( $value );
                        break;
                        
                    case 'cache_enabled':
                    case 'rate_limit_enabled':
                    case 'auto_save_enabled':
                    case 'debug_mode':
                    case 'content_safety':
                    case 'show_confidence':
                    case 'enable_collaboration':
                    case 'enable_audit_trail':
                        $value = (bool) $value;
                        break;
                        
                    case 'default_ai_provider':
                        $value = sanitize_text_field( $value );
                        // Validate provider
                        if ( ! in_array( $value, [ 'openai', 'anthropic', 'google' ], true ) ) {
                            $value = 'openai';
                        }
                        break;
                        
                    default:
                        $value = sanitize_text_field( $value );
                }
                
                update_option( 'wp_content_flow_' . $setting, $value );
                $updated[ $setting ] = $value;
            }
        }
        
        // Clear cache if cache settings changed
        if ( isset( $updated['cache_enabled'] ) || isset( $updated['cache_duration'] ) ) {
            do_action( 'wp_content_flow_clear_cache' );
        }
        
        return rest_ensure_response( [
            'success' => true,
            'updated' => $updated,
            'message' => __( 'Settings updated successfully.', 'wp-content-flow' ),
        ]);
    }
    
    /**
     * Get item schema
     * 
     * @return array
     */
    public function get_item_schema() {
        if ( $this->schema ) {
            return $this->add_additional_fields_schema( $this->schema );
        }
        
        $schema = [
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'wp_content_flow_settings',
            'type'       => 'object',
            'properties' => [
                'cache_enabled' => [
                    'description' => __( 'Enable response caching', 'wp-content-flow' ),
                    'type'        => 'boolean',
                    'default'     => true,
                ],
                'cache_duration' => [
                    'description' => __( 'Cache duration in seconds', 'wp-content-flow' ),
                    'type'        => 'integer',
                    'minimum'     => 300,
                    'maximum'     => 86400,
                    'default'     => 1800,
                ],
                'rate_limit_enabled' => [
                    'description' => __( 'Enable rate limiting', 'wp-content-flow' ),
                    'type'        => 'boolean',
                    'default'     => true,
                ],
                'requests_per_minute' => [
                    'description' => __( 'Maximum requests per minute', 'wp-content-flow' ),
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'maximum'     => 60,
                    'default'     => 10,
                ],
                'auto_save_enabled' => [
                    'description' => __( 'Auto-save generated content', 'wp-content-flow' ),
                    'type'        => 'boolean',
                    'default'     => true,
                ],
                'debug_mode' => [
                    'description' => __( 'Enable debug mode', 'wp-content-flow' ),
                    'type'        => 'boolean',
                    'default'     => false,
                ],
                'default_ai_provider' => [
                    'description' => __( 'Default AI provider', 'wp-content-flow' ),
                    'type'        => 'string',
                    'enum'        => [ 'openai', 'anthropic', 'google' ],
                    'default'     => 'openai',
                ],
                'content_safety' => [
                    'description' => __( 'Enable content safety checks', 'wp-content-flow' ),
                    'type'        => 'boolean',
                    'default'     => true,
                ],
                'show_confidence' => [
                    'description' => __( 'Show confidence scores', 'wp-content-flow' ),
                    'type'        => 'boolean',
                    'default'     => true,
                ],
                'max_content_length' => [
                    'description' => __( 'Maximum content length', 'wp-content-flow' ),
                    'type'        => 'integer',
                    'minimum'     => 100,
                    'maximum'     => 50000,
                    'default'     => 5000,
                ],
                'enable_collaboration' => [
                    'description' => __( 'Enable collaboration features', 'wp-content-flow' ),
                    'type'        => 'boolean',
                    'default'     => true,
                ],
                'enable_audit_trail' => [
                    'description' => __( 'Enable audit trail', 'wp-content-flow' ),
                    'type'        => 'boolean',
                    'default'     => true,
                ],
            ],
        ];
        
        $this->schema = $schema;
        
        return $this->add_additional_fields_schema( $this->schema );
    }
}