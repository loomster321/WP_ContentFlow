<?php
/**
 * WordPress AI Content Flow - User Capabilities
 *
 * Manages user capabilities and permissions for AI content operations.
 * Provides fine-grained access control for different plugin features.
 *
 * @package WP_Content_Flow
 * @subpackage Security
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * User Capabilities Class
 */
class WP_Content_Flow_User_Capabilities {
    
    /**
     * Plugin capabilities
     */
    const CAP_MANAGE_AI_WORKFLOWS = 'wp_content_flow_manage_workflows';
    const CAP_USE_AI_GENERATION = 'wp_content_flow_use_generation';
    const CAP_USE_AI_IMPROVEMENT = 'wp_content_flow_use_improvement';
    const CAP_MANAGE_AI_SETTINGS = 'wp_content_flow_manage_settings';
    const CAP_VIEW_AI_ANALYTICS = 'wp_content_flow_view_analytics';
    const CAP_EXPORT_AI_DATA = 'wp_content_flow_export_data';
    const CAP_DELETE_AI_HISTORY = 'wp_content_flow_delete_history';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'init', array( $this, 'init_capabilities' ) );
        add_filter( 'user_has_cap', array( $this, 'filter_user_capabilities' ), 10, 4 );
        add_action( 'wp_content_flow_activate', array( $this, 'add_capabilities_to_roles' ) );
        add_action( 'wp_content_flow_deactivate', array( $this, 'remove_capabilities_from_roles' ) );
    }
    
    /**
     * Initialize capabilities system
     */
    public function init_capabilities() {
        // Hook into WordPress role and capability system
        add_action( 'admin_init', array( $this, 'maybe_upgrade_capabilities' ) );
    }
    
    /**
     * Check if current user can perform AI operation
     *
     * @param string $capability The capability to check
     * @param mixed $object Optional object to check capability against
     * @return bool Whether user has capability
     */
    public static function current_user_can( $capability, $object = null ) {
        return current_user_can( $capability, $object );
    }
    
    /**
     * Check if user can manage AI workflows
     *
     * @param int $user_id Optional user ID, defaults to current user
     * @return bool Whether user can manage workflows
     */
    public static function can_manage_workflows( $user_id = null ) {
        if ( ! $user_id ) {
            return current_user_can( self::CAP_MANAGE_AI_WORKFLOWS );
        }
        
        $user = get_user_by( 'ID', $user_id );
        return $user && $user->has_cap( self::CAP_MANAGE_AI_WORKFLOWS );
    }
    
    /**
     * Check if user can use AI content generation
     *
     * @param int $user_id Optional user ID, defaults to current user
     * @param int $post_id Optional post ID for context
     * @return bool Whether user can generate AI content
     */
    public static function can_use_generation( $user_id = null, $post_id = null ) {
        if ( ! $user_id ) {
            $can_generate = current_user_can( self::CAP_USE_AI_GENERATION );
        } else {
            $user = get_user_by( 'ID', $user_id );
            $can_generate = $user && $user->has_cap( self::CAP_USE_AI_GENERATION );
        }
        
        // Additional check for post editing capability if post ID provided
        if ( $can_generate && $post_id ) {
            $can_generate = current_user_can( 'edit_post', $post_id );
        }
        
        return apply_filters( 'wp_content_flow_can_use_generation', $can_generate, $user_id, $post_id );
    }
    
    /**
     * Check if user can use AI content improvement
     *
     * @param int $user_id Optional user ID, defaults to current user
     * @param int $post_id Optional post ID for context
     * @return bool Whether user can improve AI content
     */
    public static function can_use_improvement( $user_id = null, $post_id = null ) {
        if ( ! $user_id ) {
            $can_improve = current_user_can( self::CAP_USE_AI_IMPROVEMENT );
        } else {
            $user = get_user_by( 'ID', $user_id );
            $can_improve = $user && $user->has_cap( self::CAP_USE_AI_IMPROVEMENT );
        }
        
        // Additional check for post editing capability if post ID provided
        if ( $can_improve && $post_id ) {
            $can_improve = current_user_can( 'edit_post', $post_id );
        }
        
        return apply_filters( 'wp_content_flow_can_use_improvement', $can_improve, $user_id, $post_id );
    }
    
    /**
     * Check if user can manage plugin settings
     *
     * @param int $user_id Optional user ID, defaults to current user
     * @return bool Whether user can manage settings
     */
    public static function can_manage_settings( $user_id = null ) {
        if ( ! $user_id ) {
            return current_user_can( self::CAP_MANAGE_AI_SETTINGS );
        }
        
        $user = get_user_by( 'ID', $user_id );
        return $user && $user->has_cap( self::CAP_MANAGE_AI_SETTINGS );
    }
    
    /**
     * Check if user can view analytics
     *
     * @param int $user_id Optional user ID, defaults to current user
     * @return bool Whether user can view analytics
     */
    public static function can_view_analytics( $user_id = null ) {
        if ( ! $user_id ) {
            return current_user_can( self::CAP_VIEW_AI_ANALYTICS );
        }
        
        $user = get_user_by( 'ID', $user_id );
        return $user && $user->has_cap( self::CAP_VIEW_AI_ANALYTICS );
    }
    
    /**
     * Check if user can export data
     *
     * @param int $user_id Optional user ID, defaults to current user
     * @return bool Whether user can export data
     */
    public static function can_export_data( $user_id = null ) {
        if ( ! $user_id ) {
            return current_user_can( self::CAP_EXPORT_AI_DATA );
        }
        
        $user = get_user_by( 'ID', $user_id );
        return $user && $user->has_cap( self::CAP_EXPORT_AI_DATA );
    }
    
    /**
     * Check if user can delete AI history
     *
     * @param int $user_id Optional user ID, defaults to current user
     * @return bool Whether user can delete history
     */
    public static function can_delete_history( $user_id = null ) {
        if ( ! $user_id ) {
            return current_user_can( self::CAP_DELETE_AI_HISTORY );
        }
        
        $user = get_user_by( 'ID', $user_id );
        return $user && $user->has_cap( self::CAP_DELETE_AI_HISTORY );
    }
    
    /**
     * Filter user capabilities for dynamic capability checks
     *
     * @param array $allcaps All capabilities
     * @param array $caps Requested capabilities
     * @param array $args Arguments
     * @param WP_User $user User object
     * @return array Modified capabilities
     */
    public function filter_user_capabilities( $allcaps, $caps, $args, $user ) {
        // Handle custom capability mapping
        if ( empty( $caps ) ) {
            return $allcaps;
        }
        
        foreach ( $caps as $cap ) {
            switch ( $cap ) {
                case self::CAP_USE_AI_GENERATION:
                    // Authors and above can use AI generation by default
                    if ( isset( $allcaps['publish_posts'] ) && $allcaps['publish_posts'] ) {
                        $allcaps[ $cap ] = true;
                    }
                    break;
                    
                case self::CAP_USE_AI_IMPROVEMENT:
                    // Editors and above can use AI improvement by default
                    if ( isset( $allcaps['edit_others_posts'] ) && $allcaps['edit_others_posts'] ) {
                        $allcaps[ $cap ] = true;
                    }
                    break;
                    
                case self::CAP_MANAGE_AI_WORKFLOWS:
                    // Only admins can manage workflows by default
                    if ( isset( $allcaps['manage_options'] ) && $allcaps['manage_options'] ) {
                        $allcaps[ $cap ] = true;
                    }
                    break;
                    
                case self::CAP_MANAGE_AI_SETTINGS:
                    // Only admins can manage settings
                    if ( isset( $allcaps['manage_options'] ) && $allcaps['manage_options'] ) {
                        $allcaps[ $cap ] = true;
                    }
                    break;
                    
                case self::CAP_VIEW_AI_ANALYTICS:
                    // Editors and above can view analytics
                    if ( isset( $allcaps['edit_others_posts'] ) && $allcaps['edit_others_posts'] ) {
                        $allcaps[ $cap ] = true;
                    }
                    break;
                    
                case self::CAP_EXPORT_AI_DATA:
                    // Only admins can export data by default
                    if ( isset( $allcaps['manage_options'] ) && $allcaps['manage_options'] ) {
                        $allcaps[ $cap ] = true;
                    }
                    break;
                    
                case self::CAP_DELETE_AI_HISTORY:
                    // Only admins can delete history
                    if ( isset( $allcaps['manage_options'] ) && $allcaps['manage_options'] ) {
                        $allcaps[ $cap ] = true;
                    }
                    break;
            }
        }
        
        // Apply filters for custom capability logic
        return apply_filters( 'wp_content_flow_user_capabilities', $allcaps, $caps, $args, $user );
    }
    
    /**
     * Add plugin capabilities to WordPress roles on activation
     */
    public function add_capabilities_to_roles() {
        // Get WordPress roles
        $wp_roles = wp_roles();
        
        if ( ! $wp_roles ) {
            return;
        }
        
        // Administrator capabilities
        $admin_caps = array(
            self::CAP_MANAGE_AI_WORKFLOWS,
            self::CAP_USE_AI_GENERATION,
            self::CAP_USE_AI_IMPROVEMENT,
            self::CAP_MANAGE_AI_SETTINGS,
            self::CAP_VIEW_AI_ANALYTICS,
            self::CAP_EXPORT_AI_DATA,
            self::CAP_DELETE_AI_HISTORY,
        );
        
        foreach ( $admin_caps as $cap ) {
            $wp_roles->add_cap( 'administrator', $cap );
        }
        
        // Editor capabilities
        $editor_caps = array(
            self::CAP_USE_AI_GENERATION,
            self::CAP_USE_AI_IMPROVEMENT,
            self::CAP_VIEW_AI_ANALYTICS,
        );
        
        foreach ( $editor_caps as $cap ) {
            $wp_roles->add_cap( 'editor', $cap );
        }
        
        // Author capabilities
        $author_caps = array(
            self::CAP_USE_AI_GENERATION,
            self::CAP_USE_AI_IMPROVEMENT,
        );
        
        foreach ( $author_caps as $cap ) {
            $wp_roles->add_cap( 'author', $cap );
        }
        
        // Contributor capabilities (limited)
        $contributor_caps = array(
            self::CAP_USE_AI_GENERATION, // Can generate content for their drafts
        );
        
        foreach ( $contributor_caps as $cap ) {
            $wp_roles->add_cap( 'contributor', $cap );
        }
    }
    
    /**
     * Remove plugin capabilities from roles on deactivation
     */
    public function remove_capabilities_from_roles() {
        // Get WordPress roles
        $wp_roles = wp_roles();
        
        if ( ! $wp_roles ) {
            return;
        }
        
        $all_caps = array(
            self::CAP_MANAGE_AI_WORKFLOWS,
            self::CAP_USE_AI_GENERATION,
            self::CAP_USE_AI_IMPROVEMENT,
            self::CAP_MANAGE_AI_SETTINGS,
            self::CAP_VIEW_AI_ANALYTICS,
            self::CAP_EXPORT_AI_DATA,
            self::CAP_DELETE_AI_HISTORY,
        );
        
        $roles = array( 'administrator', 'editor', 'author', 'contributor' );
        
        foreach ( $roles as $role ) {
            foreach ( $all_caps as $cap ) {
                $wp_roles->remove_cap( $role, $cap );
            }
        }
    }
    
    /**
     * Maybe upgrade capabilities on plugin update
     */
    public function maybe_upgrade_capabilities() {
        $current_version = get_option( 'wp_content_flow_capabilities_version', '0.0.0' );
        
        if ( version_compare( $current_version, WP_CONTENT_FLOW_VERSION, '<' ) ) {
            $this->add_capabilities_to_roles();
            update_option( 'wp_content_flow_capabilities_version', WP_CONTENT_FLOW_VERSION );
        }
    }
    
    /**
     * Get all plugin capabilities
     *
     * @return array All plugin capabilities
     */
    public static function get_all_capabilities() {
        return array(
            self::CAP_MANAGE_AI_WORKFLOWS => __( 'Manage AI Workflows', 'wp-content-flow' ),
            self::CAP_USE_AI_GENERATION => __( 'Use AI Content Generation', 'wp-content-flow' ),
            self::CAP_USE_AI_IMPROVEMENT => __( 'Use AI Content Improvement', 'wp-content-flow' ),
            self::CAP_MANAGE_AI_SETTINGS => __( 'Manage AI Settings', 'wp-content-flow' ),
            self::CAP_VIEW_AI_ANALYTICS => __( 'View AI Analytics', 'wp-content-flow' ),
            self::CAP_EXPORT_AI_DATA => __( 'Export AI Data', 'wp-content-flow' ),
            self::CAP_DELETE_AI_HISTORY => __( 'Delete AI History', 'wp-content-flow' ),
        );
    }
    
    /**
     * Get capabilities for a specific role
     *
     * @param string $role Role name
     * @return array Capabilities for the role
     */
    public static function get_role_capabilities( $role ) {
        $wp_roles = wp_roles();
        
        if ( ! $wp_roles || ! $wp_roles->is_role( $role ) ) {
            return array();
        }
        
        $role_obj = $wp_roles->get_role( $role );
        $all_caps = self::get_all_capabilities();
        $role_caps = array();
        
        foreach ( array_keys( $all_caps ) as $cap ) {
            if ( $role_obj && $role_obj->has_cap( $cap ) ) {
                $role_caps[] = $cap;
            }
        }
        
        return $role_caps;
    }
    
    /**
     * Grant capability to user
     *
     * @param int $user_id User ID
     * @param string $capability Capability to grant
     * @return bool Success
     */
    public static function grant_capability( $user_id, $capability ) {
        $user = get_user_by( 'ID', $user_id );
        
        if ( ! $user ) {
            return false;
        }
        
        $user->add_cap( $capability );
        return true;
    }
    
    /**
     * Revoke capability from user
     *
     * @param int $user_id User ID
     * @param string $capability Capability to revoke
     * @return bool Success
     */
    public static function revoke_capability( $user_id, $capability ) {
        $user = get_user_by( 'ID', $user_id );
        
        if ( ! $user ) {
            return false;
        }
        
        $user->remove_cap( $capability );
        return true;
    }
    
    /**
     * Check rate limits for user AI operations
     *
     * @param int $user_id User ID
     * @param string $operation Operation type (generate, improve)
     * @return bool Whether user can perform operation
     */
    public static function check_rate_limits( $user_id, $operation ) {
        $settings = get_option( 'wp_content_flow_settings', array() );
        
        if ( empty( $settings['rate_limit_enabled'] ) ) {
            return true;
        }
        
        $transient_key = 'wp_content_flow_rate_limit_' . $user_id . '_' . $operation;
        $current_count = get_transient( $transient_key );
        
        if ( false === $current_count ) {
            set_transient( $transient_key, 1, MINUTE_IN_SECONDS );
            return true;
        }
        
        $requests_per_minute = $settings['requests_per_minute'] ?? 10;
        
        if ( $current_count >= $requests_per_minute ) {
            return false;
        }
        
        set_transient( $transient_key, $current_count + 1, MINUTE_IN_SECONDS );
        return true;
    }
    
    /**
     * Log capability check for auditing
     *
     * @param int $user_id User ID
     * @param string $capability Capability checked
     * @param bool $granted Whether capability was granted
     * @param array $context Additional context
     */
    public static function log_capability_check( $user_id, $capability, $granted, $context = array() ) {
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            return;
        }
        
        $log_entry = array(
            'timestamp' => current_time( 'mysql' ),
            'user_id' => $user_id,
            'capability' => $capability,
            'granted' => $granted,
            'context' => $context,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        );
        
        error_log( '[WP Content Flow] Capability Check: ' . wp_json_encode( $log_entry ) );
    }
}