<?php
/**
 * Workflow Model Class
 * 
 * Object-oriented interface for wp_ai_workflows table operations.
 * This class makes the workflow contract tests pass by providing
 * the data layer for REST API operations.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Workflow model class
 */
class WP_Content_Flow_Workflow {
    
    /**
     * Workflow ID
     *
     * @var int
     */
    public $id;
    
    /**
     * Workflow name
     *
     * @var string
     */
    public $name;
    
    /**
     * Workflow description
     *
     * @var string
     */
    public $description;
    
    /**
     * AI provider name
     *
     * @var string
     */
    public $ai_provider;
    
    /**
     * Workflow settings (JSON decoded)
     *
     * @var array
     */
    public $settings;
    
    /**
     * Workflow status
     *
     * @var string
     */
    public $status;
    
    /**
     * User ID who owns the workflow
     *
     * @var int
     */
    public $user_id;
    
    /**
     * Creation timestamp
     *
     * @var string
     */
    public $created_at;
    
    /**
     * Last update timestamp
     *
     * @var string
     */
    public $updated_at;
    
    /**
     * Constructor
     *
     * @param int|array $workflow Workflow ID or workflow data array
     */
    public function __construct( $workflow = 0 ) {
        if ( is_numeric( $workflow ) && $workflow > 0 ) {
            $this->load_by_id( $workflow );
        } elseif ( is_array( $workflow ) ) {
            $this->load_from_data( $workflow );
        }
    }
    
    /**
     * Load workflow by ID
     *
     * @param int $workflow_id Workflow ID
     * @return bool True on success, false if not found
     */
    private function load_by_id( $workflow_id ) {
        // Load database schema functions
        if ( ! function_exists( 'wp_content_flow_get_workflow' ) ) {
            require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/schema-workflows.php';
        }
        
        $workflow_data = wp_content_flow_get_workflow( $workflow_id );
        
        if ( ! $workflow_data ) {
            return false;
        }
        
        $this->load_from_data( $workflow_data );
        return true;
    }
    
    /**
     * Load workflow from data array
     *
     * @param array $data Workflow data
     */
    private function load_from_data( $data ) {
        $this->id = isset( $data['id'] ) ? (int) $data['id'] : 0;
        $this->name = $data['name'] ?? '';
        $this->description = $data['description'] ?? '';
        $this->ai_provider = $data['ai_provider'] ?? '';
        $this->settings = $data['settings'] ?? array();
        $this->status = $data['status'] ?? 'active';
        $this->user_id = isset( $data['user_id'] ) ? (int) $data['user_id'] : 0;
        $this->created_at = $data['created_at'] ?? '';
        $this->updated_at = $data['updated_at'] ?? '';
        
        // Ensure settings is array (may be JSON string from database)
        if ( is_string( $this->settings ) ) {
            $this->settings = json_decode( $this->settings, true ) ?: array();
        }
    }
    
    /**
     * Save workflow (create or update)
     *
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function save() {
        // Load database schema functions
        if ( ! function_exists( 'wp_content_flow_validate_workflow_data' ) ) {
            require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/schema-workflows.php';
        }
        
        // Prepare data for validation
        $workflow_data = array(
            'name' => $this->name,
            'description' => $this->description,
            'ai_provider' => $this->ai_provider,
            'settings' => $this->settings,
            'status' => $this->status,
            'user_id' => $this->user_id ?: get_current_user_id()
        );
        
        // Validate data
        $validated_data = wp_content_flow_validate_workflow_data( $workflow_data );
        
        if ( is_wp_error( $validated_data ) ) {
            return $validated_data;
        }
        
        if ( $this->id > 0 ) {
            // Update existing workflow
            return $this->update( $validated_data );
        } else {
            // Create new workflow
            return $this->create( $validated_data );
        }
    }
    
    /**
     * Create new workflow
     *
     * @param array $validated_data Validated workflow data
     * @return bool True on success, false on failure
     */
    private function create( $validated_data ) {
        $workflow_id = wp_content_flow_insert_workflow( $validated_data );
        
        if ( $workflow_id === false ) {
            return new WP_Error( 'workflow_create_failed', __( 'Failed to create workflow.', 'wp-content-flow' ) );
        }
        
        $this->id = $workflow_id;
        $this->user_id = $validated_data['user_id'];
        $this->created_at = current_time( 'mysql', true );
        $this->updated_at = $this->created_at;
        
        // Fire action hook
        do_action( 'wp_content_flow_workflow_created', $this );
        do_action( 'wp_content_flow_workflow_activated', $this->to_array() );
        
        return true;
    }
    
    /**
     * Update existing workflow
     *
     * @param array $validated_data Validated workflow data
     * @return bool True on success, false on failure
     */
    private function update( $validated_data ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_workflows';
        
        $result = $wpdb->update(
            $table_name,
            $validated_data,
            array( 'id' => $this->id ),
            array( '%s', '%s', '%s', '%s', '%s', '%d' ),
            array( '%d' )
        );
        
        if ( $result === false ) {
            return new WP_Error( 'workflow_update_failed', __( 'Failed to update workflow.', 'wp-content-flow' ) );
        }
        
        // Update object properties
        $this->name = $validated_data['name'];
        $this->description = $validated_data['description'];
        $this->ai_provider = $validated_data['ai_provider'];
        $this->settings = json_decode( $validated_data['settings'], true );
        $this->status = $validated_data['status'];
        $this->updated_at = current_time( 'mysql', true );
        
        // Fire action hook
        do_action( 'wp_content_flow_workflow_updated', $this );
        
        return true;
    }
    
    /**
     * Delete workflow
     *
     * @return bool True on success, false on failure
     */
    public function delete() {
        if ( $this->id <= 0 ) {
            return false;
        }
        
        // Permission check
        if ( ! $this->current_user_can_edit() ) {
            return new WP_Error( 'workflow_delete_forbidden', __( 'You do not have permission to delete this workflow.', 'wp-content-flow' ) );
        }
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_workflows';
        
        $result = $wpdb->delete(
            $table_name,
            array( 'id' => $this->id ),
            array( '%d' )
        );
        
        if ( $result === false ) {
            return new WP_Error( 'workflow_delete_failed', __( 'Failed to delete workflow.', 'wp-content-flow' ) );
        }
        
        // Fire action hook before clearing data
        do_action( 'wp_content_flow_workflow_deleted', $this->to_array() );
        
        // Clear object data
        $this->id = 0;
        
        return true;
    }
    
    /**
     * Check if current user can edit this workflow
     *
     * @return bool True if user can edit, false otherwise
     */
    public function current_user_can_edit() {
        // Administrators can edit any workflow
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        
        // Users can edit their own workflows if they have edit_posts capability
        if ( current_user_can( 'edit_posts' ) && $this->user_id === get_current_user_id() ) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if workflow is active
     *
     * @return bool True if active, false otherwise
     */
    public function is_active() {
        return $this->status === 'active';
    }
    
    /**
     * Activate workflow
     *
     * @return bool True on success, false on failure
     */
    public function activate() {
        if ( ! $this->current_user_can_edit() ) {
            return false;
        }
        
        $this->status = 'active';
        return $this->save();
    }
    
    /**
     * Deactivate workflow
     *
     * @return bool True on success, false on failure
     */
    public function deactivate() {
        if ( ! $this->current_user_can_edit() ) {
            return false;
        }
        
        $this->status = 'inactive';
        return $this->save();
    }
    
    /**
     * Archive workflow
     *
     * @return bool True on success, false on failure
     */
    public function archive() {
        if ( ! $this->current_user_can_edit() ) {
            return false;
        }
        
        $this->status = 'archived';
        return $this->save();
    }
    
    /**
     * Get workflow as array (for API responses)
     *
     * @return array Workflow data
     */
    public function to_array() {
        return array(
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'ai_provider' => $this->ai_provider,
            'settings' => $this->settings,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        );
    }
    
    /**
     * Get workflows for current user
     *
     * @param array $args Query arguments
     * @return array Array of workflow objects
     */
    public static function get_user_workflows( $args = array() ) {
        global $wpdb;
        
        $defaults = array(
            'user_id' => get_current_user_id(),
            'status' => null,
            'per_page' => 10,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args( $args, $defaults );
        
        // Permission check
        if ( ! current_user_can( 'edit_posts' ) ) {
            return array();
        }
        
        $table_name = $wpdb->prefix . 'ai_workflows';
        
        $where_clauses = array();
        $where_values = array();
        
        // User restriction (unless admin viewing all)
        if ( ! current_user_can( 'manage_options' ) || $args['user_id'] !== null ) {
            $where_clauses[] = 'user_id = %d';
            $where_values[] = $args['user_id'];
        }
        
        // Status filter
        if ( $args['status'] ) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        $where_clause = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';
        
        $offset = ( $args['page'] - 1 ) * $args['per_page'];
        $limit_clause = sprintf( 'LIMIT %d OFFSET %d', $args['per_page'], $offset );
        $order_clause = sprintf( 'ORDER BY %s %s', $args['orderby'], $args['order'] );
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$table_name} {$where_clause} {$order_clause} {$limit_clause}",
            $where_values
        );
        
        $workflow_data = $wpdb->get_results( $query, ARRAY_A );
        
        // Convert to workflow objects
        $workflows = array();
        foreach ( $workflow_data as $data ) {
            $workflows[] = new self( $data );
        }
        
        return $workflows;
    }
    
    /**
     * Get total count of workflows for pagination
     *
     * @param array $args Query arguments
     * @return int Total count
     */
    public static function get_user_workflows_count( $args = array() ) {
        global $wpdb;
        
        $defaults = array(
            'user_id' => get_current_user_id(),
            'status' => null
        );
        
        $args = wp_parse_args( $args, $defaults );
        
        if ( ! current_user_can( 'edit_posts' ) ) {
            return 0;
        }
        
        $table_name = $wpdb->prefix . 'ai_workflows';
        
        $where_clauses = array();
        $where_values = array();
        
        if ( ! current_user_can( 'manage_options' ) || $args['user_id'] !== null ) {
            $where_clauses[] = 'user_id = %d';
            $where_values[] = $args['user_id'];
        }
        
        if ( $args['status'] ) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        $where_clause = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} {$where_clause}",
            $where_values
        );
        
        return (int) $wpdb->get_var( $query );
    }
    
    /**
     * Get workflow by ID (static method)
     *
     * @param int $workflow_id Workflow ID
     * @return WP_Content_Flow_Workflow|null Workflow object or null if not found
     */
    public static function find( $workflow_id ) {
        $workflow = new self( $workflow_id );
        
        if ( $workflow->id > 0 ) {
            return $workflow;
        }
        
        return null;
    }
    
    /**
     * Validate workflow data
     *
     * @param array $data Workflow data to validate
     * @return array|WP_Error Validated data or error
     */
    public static function validate( $data ) {
        if ( ! function_exists( 'wp_content_flow_validate_workflow_data' ) ) {
            require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/schema-workflows.php';
        }
        
        return wp_content_flow_validate_workflow_data( $data );
    }
}