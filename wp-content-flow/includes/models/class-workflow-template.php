<?php
/**
 * Workflow Template Model Class
 *
 * Manages workflow template data operations including CRUD operations,
 * template validation, and workflow execution configuration.
 *
 * @package WP_Content_Flow
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Workflow Template Model Class
 */
class WP_Content_Flow_Workflow_Template {
    
    /**
     * Database table name (without prefix)
     * @var string
     */
    private static $table_name = 'workflow_templates';
    
    /**
     * Template data
     * @var array
     */
    private $data = [];
    
    /**
     * Original data for change detection
     * @var array
     */
    private $original_data = [];
    
    /**
     * Template validation errors
     * @var array
     */
    private $errors = [];
    
    /**
     * Constructor
     *
     * @param int|array $template Template ID or data array
     */
    public function __construct( $template = null ) {
        global $wpdb;
        $this->table_name = $wpdb->prefix . self::$table_name;
        
        if ( is_numeric( $template ) ) {
            $this->load( $template );
        } elseif ( is_array( $template ) ) {
            $this->set_data( $template );
        }
    }
    
    /**
     * Load template by ID
     *
     * @param int $template_id Template ID
     * @return bool True on success, false on failure
     */
    public function load( $template_id ) {
        global $wpdb;
        
        $template_data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $template_id
            ),
            ARRAY_A
        );
        
        if ( $template_data ) {
            $this->set_data( $template_data );
            $this->original_data = $this->data;
            return true;
        }
        
        return false;
    }
    
    /**
     * Set template data
     *
     * @param array $data Template data
     */
    public function set_data( $data ) {
        $this->data = $data;
        
        // Decode JSON fields
        $json_fields = [
            'ai_parameters',
            'workflow_steps',
            'approval_criteria',
            'content_filters',
            'assigned_users',
            'trigger_conditions',
            'schedule_settings',
            'notification_settings'
        ];
        
        foreach ( $json_fields as $field ) {
            if ( isset( $this->data[ $field ] ) && is_string( $this->data[ $field ] ) ) {
                $this->data[ $field ] = json_decode( $this->data[ $field ], true );
            }
        }
    }
    
    /**
     * Get template property
     *
     * @param string $property Property name
     * @return mixed Property value
     */
    public function get( $property ) {
        return $this->data[ $property ] ?? null;
    }
    
    /**
     * Set template property
     *
     * @param string $property Property name
     * @param mixed $value Property value
     */
    public function set( $property, $value ) {
        $this->data[ $property ] = $value;
    }
    
    /**
     * Get template ID
     *
     * @return int|null Template ID
     */
    public function get_id() {
        return $this->get( 'id' );
    }
    
    /**
     * Get template name
     *
     * @return string Template name
     */
    public function get_name() {
        return $this->get( 'template_name' ) ?: '';
    }
    
    /**
     * Get template type
     *
     * @return string Template type
     */
    public function get_type() {
        return $this->get( 'template_type' ) ?: 'content_generation';
    }
    
    /**
     * Get AI provider
     *
     * @return string AI provider
     */
    public function get_ai_provider() {
        return $this->get( 'ai_provider' ) ?: 'openai';
    }
    
    /**
     * Get AI model
     *
     * @return string AI model
     */
    public function get_ai_model() {
        return $this->get( 'ai_model' ) ?: 'gpt-3.5-turbo';
    }
    
    /**
     * Get AI parameters
     *
     * @return array AI parameters
     */
    public function get_ai_parameters() {
        $params = $this->get( 'ai_parameters' ) ?: [];
        
        // Provide defaults
        return wp_parse_args( $params, [
            'temperature' => 0.7,
            'max_tokens' => 1000,
            'top_p' => 0.9,
            'frequency_penalty' => 0,
            'presence_penalty' => 0
        ] );
    }
    
    /**
     * Get workflow steps
     *
     * @return array Workflow steps
     */
    public function get_workflow_steps() {
        return $this->get( 'workflow_steps' ) ?: [];
    }
    
    /**
     * Get approval criteria
     *
     * @return array Approval criteria
     */
    public function get_approval_criteria() {
        return $this->get( 'approval_criteria' ) ?: [];
    }
    
    /**
     * Get allowed roles
     *
     * @return array Allowed WordPress roles
     */
    public function get_allowed_roles() {
        $roles = $this->get( 'allowed_roles' ) ?: 'administrator,editor';
        return explode( ',', $roles );
    }
    
    /**
     * Get assigned users
     *
     * @return array Assigned user IDs
     */
    public function get_assigned_users() {
        return $this->get( 'assigned_users' ) ?: [];
    }
    
    /**
     * Check if user can use template
     *
     * @param int $user_id User ID (default: current user)
     * @return bool True if user can use template
     */
    public function can_user_access( $user_id = null ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        
        if ( ! $user_id ) {
            return false;
        }
        
        // Check if user is specifically assigned
        $assigned_users = $this->get_assigned_users();
        if ( ! empty( $assigned_users ) && in_array( $user_id, $assigned_users ) ) {
            return true;
        }
        
        // Check role-based access
        $allowed_roles = $this->get_allowed_roles();
        $user = get_userdata( $user_id );
        
        if ( ! $user ) {
            return false;
        }
        
        foreach ( $user->roles as $role ) {
            if ( in_array( $role, $allowed_roles ) ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Validate template data
     *
     * @return bool True if valid, false otherwise
     */
    public function validate() {
        $this->errors = [];
        
        // Required fields
        $required_fields = [ 'template_name', 'template_type', 'ai_provider', 'workflow_steps' ];
        
        foreach ( $required_fields as $field ) {
            if ( empty( $this->data[ $field ] ) ) {
                $this->errors[] = sprintf( 'Field "%s" is required.', $field );
            }
        }
        
        // Validate template name length
        if ( strlen( $this->get_name() ) > 255 ) {
            $this->errors[] = 'Template name cannot exceed 255 characters.';
        }
        
        // Validate template type
        $valid_types = [ 'content_generation', 'content_review', 'approval_workflow', 'publishing', 'custom' ];
        if ( ! in_array( $this->get_type(), $valid_types ) ) {
            $this->errors[] = 'Invalid template type.';
        }
        
        // Validate AI provider
        $valid_providers = [ 'openai', 'anthropic', 'google' ];
        if ( ! in_array( $this->get_ai_provider(), $valid_providers ) ) {
            $this->errors[] = 'Invalid AI provider.';
        }
        
        // Validate workflow steps
        $workflow_steps = $this->get_workflow_steps();
        if ( empty( $workflow_steps ) || ! is_array( $workflow_steps ) ) {
            $this->errors[] = 'Workflow steps are required and must be an array.';
        } else {
            foreach ( $workflow_steps as $index => $step ) {
                if ( ! isset( $step['step'] ) || ! isset( $step['role'] ) ) {
                    $this->errors[] = sprintf( 'Workflow step %d must have "step" and "role" properties.', $index + 1 );
                }
            }
        }
        
        // Validate AI parameters
        $ai_params = $this->get_ai_parameters();
        if ( isset( $ai_params['temperature'] ) ) {
            if ( $ai_params['temperature'] < 0 || $ai_params['temperature'] > 2 ) {
                $this->errors[] = 'Temperature must be between 0 and 2.';
            }
        }
        
        if ( isset( $ai_params['max_tokens'] ) ) {
            if ( $ai_params['max_tokens'] < 1 || $ai_params['max_tokens'] > 4000 ) {
                $this->errors[] = 'Max tokens must be between 1 and 4000.';
            }
        }
        
        return empty( $this->errors );
    }
    
    /**
     * Get validation errors
     *
     * @return array Validation errors
     */
    public function get_errors() {
        return $this->errors;
    }
    
    /**
     * Save template to database
     *
     * @return bool|int Template ID on success, false on failure
     */
    public function save() {
        global $wpdb;
        
        if ( ! $this->validate() ) {
            return false;
        }
        
        // Prepare data for database
        $db_data = $this->prepare_data_for_db();
        
        $template_id = $this->get_id();
        
        if ( $template_id ) {
            // Update existing template
            $db_data['updated_at'] = current_time( 'mysql' );
            
            $result = $wpdb->update(
                $this->table_name,
                $db_data,
                [ 'id' => $template_id ],
                $this->get_data_format( $db_data ),
                [ '%d' ]
            );
            
            if ( false !== $result ) {
                $this->original_data = $this->data;
                
                // Fire action hook
                do_action( 'wp_content_flow_template_updated', $template_id, $this );
                
                return $template_id;
            }
        } else {
            // Insert new template
            $db_data['created_at'] = current_time( 'mysql' );
            $db_data['updated_at'] = current_time( 'mysql' );
            
            if ( ! isset( $db_data['created_by'] ) ) {
                $db_data['created_by'] = get_current_user_id();
            }
            
            $result = $wpdb->insert(
                $this->table_name,
                $db_data,
                $this->get_data_format( $db_data )
            );
            
            if ( $result ) {
                $template_id = $wpdb->insert_id;
                $this->set( 'id', $template_id );
                $this->original_data = $this->data;
                
                // Fire action hook
                do_action( 'wp_content_flow_template_created', $template_id, $this );
                
                return $template_id;
            }
        }
        
        return false;
    }
    
    /**
     * Delete template from database
     *
     * @return bool True on success, false on failure
     */
    public function delete() {
        global $wpdb;
        
        $template_id = $this->get_id();
        if ( ! $template_id ) {
            return false;
        }
        
        $result = $wpdb->delete(
            $this->table_name,
            [ 'id' => $template_id ],
            [ '%d' ]
        );
        
        if ( false !== $result ) {
            // Fire action hook
            do_action( 'wp_content_flow_template_deleted', $template_id, $this );
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Prepare data for database storage
     *
     * @return array Database-ready data
     */
    private function prepare_data_for_db() {
        $db_data = $this->data;
        
        // Encode JSON fields
        $json_fields = [
            'ai_parameters',
            'workflow_steps',
            'approval_criteria',
            'content_filters',
            'assigned_users',
            'trigger_conditions',
            'schedule_settings',
            'notification_settings'
        ];
        
        foreach ( $json_fields as $field ) {
            if ( isset( $db_data[ $field ] ) && ! is_string( $db_data[ $field ] ) ) {
                $db_data[ $field ] = wp_json_encode( $db_data[ $field ] );
            }
        }
        
        // Remove computed fields
        unset( $db_data['id'] );
        
        return $db_data;
    }
    
    /**
     * Get data format array for wpdb operations
     *
     * @param array $data Data array
     * @return array Format array
     */
    private function get_data_format( $data ) {
        $format = [];
        
        foreach ( $data as $key => $value ) {
            switch ( $key ) {
                case 'id':
                case 'created_by':
                case 'usage_count':
                case 'max_execution_time':
                case 'retry_attempts':
                    $format[] = '%d';
                    break;
                    
                case 'quality_threshold':
                case 'avg_execution_time':
                case 'success_rate':
                    $format[] = '%f';
                    break;
                    
                case 'auto_trigger':
                    $format[] = '%d';
                    break;
                    
                default:
                    $format[] = '%s';
                    break;
            }
        }
        
        return $format;
    }
    
    /**
     * Duplicate template
     *
     * @param string $new_name New template name
     * @return WP_Content_Flow_Workflow_Template|false New template instance or false
     */
    public function duplicate( $new_name = null ) {
        $template_data = $this->data;
        
        // Remove ID and modify name
        unset( $template_data['id'] );
        $template_data['template_name'] = $new_name ?: ( $this->get_name() . ' (Copy)' );
        $template_data['template_status'] = 'draft';
        $template_data['usage_count'] = 0;
        $template_data['last_used_at'] = null;
        
        $new_template = new self( $template_data );
        
        if ( $new_template->save() ) {
            return $new_template;
        }
        
        return false;
    }
    
    /**
     * Update usage statistics
     *
     * @param float $execution_time Execution time in seconds
     * @param bool $success Whether execution was successful
     */
    public function update_usage_stats( $execution_time = null, $success = true ) {
        global $wpdb;
        
        $template_id = $this->get_id();
        if ( ! $template_id ) {
            return;
        }
        
        $updates = [
            'usage_count' => 'usage_count + 1',
            'last_used_at' => "'" . current_time( 'mysql' ) . "'"
        ];
        
        if ( $execution_time !== null ) {
            // Calculate new average execution time
            $current_avg = $this->get( 'avg_execution_time' ) ?: 0;
            $current_count = $this->get( 'usage_count' ) ?: 0;
            $new_avg = ( ( $current_avg * $current_count ) + $execution_time ) / ( $current_count + 1 );
            
            $updates['avg_execution_time'] = $new_avg;
        }
        
        if ( $success !== null ) {
            // Calculate new success rate
            $current_rate = $this->get( 'success_rate' ) ?: 100;
            $current_count = $this->get( 'usage_count' ) ?: 0;
            $success_value = $success ? 100 : 0;
            $new_rate = ( ( $current_rate * $current_count ) + $success_value ) / ( $current_count + 1 );
            
            $updates['success_rate'] = $new_rate;
        }
        
        // Build dynamic SQL
        $set_clauses = [];
        foreach ( $updates as $field => $value ) {
            $set_clauses[] = "{$field} = {$value}";
        }
        
        $sql = "UPDATE {$this->table_name} SET " . implode( ', ', $set_clauses ) . " WHERE id = %d";
        
        $wpdb->query( $wpdb->prepare( $sql, $template_id ) );
        
        // Reload data to reflect changes
        $this->load( $template_id );
    }
    
    /**
     * Get all templates
     *
     * @param array $args Query arguments
     * @return array Template instances
     */
    public static function get_templates( $args = [] ) {
        global $wpdb;
        
        $defaults = [
            'template_type' => null,
            'template_status' => 'active',
            'ai_provider' => null,
            'created_by' => null,
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'search' => null
        ];
        
        $args = wp_parse_args( $args, $defaults );
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        $where_conditions = [];
        $where_values = [];
        
        if ( $args['template_type'] ) {
            $where_conditions[] = 'template_type = %s';
            $where_values[] = $args['template_type'];
        }
        
        if ( $args['template_status'] ) {
            $where_conditions[] = 'template_status = %s';
            $where_values[] = $args['template_status'];
        }
        
        if ( $args['ai_provider'] ) {
            $where_conditions[] = 'ai_provider = %s';
            $where_values[] = $args['ai_provider'];
        }
        
        if ( $args['created_by'] ) {
            $where_conditions[] = 'created_by = %d';
            $where_values[] = $args['created_by'];
        }
        
        if ( $args['search'] ) {
            $where_conditions[] = 'MATCH(template_name, template_description, tags) AGAINST(%s IN BOOLEAN MODE)';
            $where_values[] = $args['search'];
        }
        
        $where_clause = '';
        if ( ! empty( $where_conditions ) ) {
            $where_clause = 'WHERE ' . implode( ' AND ', $where_conditions );
        }
        
        $order_clause = sprintf(
            'ORDER BY %s %s',
            sanitize_sql_orderby( $args['orderby'] ),
            in_array( strtoupper( $args['order'] ), [ 'ASC', 'DESC' ] ) ? $args['order'] : 'DESC'
        );
        
        $limit_clause = '';
        if ( $args['limit'] > 0 ) {
            $limit_clause = $wpdb->prepare( 'LIMIT %d OFFSET %d', $args['limit'], $args['offset'] );
        }
        
        $sql = "SELECT * FROM {$table_name} {$where_clause} {$order_clause} {$limit_clause}";
        
        if ( ! empty( $where_values ) ) {
            $sql = $wpdb->prepare( $sql, $where_values );
        }
        
        $results = $wpdb->get_results( $sql, ARRAY_A );
        
        $templates = [];
        foreach ( $results as $template_data ) {
            $templates[] = new self( $template_data );
        }
        
        return $templates;
    }
    
    /**
     * Get template by name
     *
     * @param string $name Template name
     * @return WP_Content_Flow_Workflow_Template|null Template instance or null
     */
    public static function get_by_name( $name ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        $template_data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE template_name = %s LIMIT 1",
                $name
            ),
            ARRAY_A
        );
        
        if ( $template_data ) {
            return new self( $template_data );
        }
        
        return null;
    }
}