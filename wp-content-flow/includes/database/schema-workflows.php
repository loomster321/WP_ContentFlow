<?php
/**
 * Database schema for wp_ai_workflows table
 * 
 * This implements the data model from specs/002-i-want-to/data-model.md
 * to make the workflow contract tests pass.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Create wp_ai_workflows table
 * 
 * @global wpdb $wpdb WordPress database abstraction object
 * @return bool True on success, false on failure
 */
function wp_content_flow_create_workflows_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'ai_workflows';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        ai_provider VARCHAR(50) NOT NULL,
        settings LONGTEXT NOT NULL COMMENT 'JSON configuration',
        status ENUM('active', 'inactive', 'archived') DEFAULT 'active',
        user_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'Creator/owner',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        FOREIGN KEY (user_id) REFERENCES {$wpdb->users}(ID) ON DELETE CASCADE,
        INDEX idx_status (status),
        INDEX idx_provider (ai_provider),
        INDEX idx_user (user_id),
        UNIQUE KEY idx_user_name (user_id, name) COMMENT 'Workflow names must be unique per user'
    ) {$charset_collate};";
    
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    
    $result = dbDelta( $sql );
    
    // Log creation for debugging
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( "WP Content Flow: Created workflows table - " . print_r( $result, true ) );
    }
    
    // Verify table was created
    $table_exists = $wpdb->get_var( $wpdb->prepare(
        "SHOW TABLES LIKE %s",
        $table_name
    ) ) === $table_name;
    
    if ( ! $table_exists ) {
        error_log( "WP Content Flow: Failed to create workflows table" );
        return false;
    }
    
    return true;
}

/**
 * Drop wp_ai_workflows table
 * 
 * @global wpdb $wpdb WordPress database abstraction object
 * @return bool True on success, false on failure
 */
function wp_content_flow_drop_workflows_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'ai_workflows';
    
    $result = $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
    
    if ( $result === false ) {
        error_log( "WP Content Flow: Failed to drop workflows table" );
        return false;
    }
    
    return true;
}

/**
 * Get workflows table name
 * 
 * @global wpdb $wpdb WordPress database abstraction object
 * @return string Table name with prefix
 */
function wp_content_flow_get_workflows_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'ai_workflows';
}

/**
 * Validate workflow data before database insertion
 * 
 * @param array $workflow_data Workflow data to validate
 * @return array|WP_Error Validated data or WP_Error on failure
 */
function wp_content_flow_validate_workflow_data( $workflow_data ) {
    $errors = new WP_Error();
    
    // Required fields validation
    if ( empty( $workflow_data['name'] ) ) {
        $errors->add( 'missing_name', __( 'Workflow name is required.', 'wp-content-flow' ) );
    }
    
    if ( empty( $workflow_data['ai_provider'] ) ) {
        $errors->add( 'missing_provider', __( 'AI provider is required.', 'wp-content-flow' ) );
    }
    
    if ( ! isset( $workflow_data['settings'] ) ) {
        $errors->add( 'missing_settings', __( 'Workflow settings are required.', 'wp-content-flow' ) );
    }
    
    // Field constraint validation
    if ( ! empty( $workflow_data['name'] ) && strlen( $workflow_data['name'] ) > 255 ) {
        $errors->add( 'name_too_long', __( 'Workflow name must be 255 characters or less.', 'wp-content-flow' ) );
    }
    
    // AI provider validation
    $valid_providers = array( 'openai', 'anthropic', 'google', 'azure' );
    if ( ! empty( $workflow_data['ai_provider'] ) && ! in_array( $workflow_data['ai_provider'], $valid_providers, true ) ) {
        $errors->add( 'invalid_provider', sprintf( 
            __( 'AI provider must be one of: %s', 'wp-content-flow' ),
            implode( ', ', $valid_providers )
        ) );
    }
    
    // Settings validation (must be valid JSON)
    if ( isset( $workflow_data['settings'] ) ) {
        if ( is_string( $workflow_data['settings'] ) ) {
            $json_decoded = json_decode( $workflow_data['settings'], true );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                $errors->add( 'invalid_settings_json', __( 'Workflow settings must be valid JSON.', 'wp-content-flow' ) );
            }
        } elseif ( ! is_array( $workflow_data['settings'] ) ) {
            $errors->add( 'invalid_settings_type', __( 'Workflow settings must be an array or JSON string.', 'wp-content-flow' ) );
        }
    }
    
    // Status validation
    if ( isset( $workflow_data['status'] ) ) {
        $valid_statuses = array( 'active', 'inactive', 'archived' );
        if ( ! in_array( $workflow_data['status'], $valid_statuses, true ) ) {
            $errors->add( 'invalid_status', sprintf(
                __( 'Status must be one of: %s', 'wp-content-flow' ),
                implode( ', ', $valid_statuses )
            ) );
        }
    }
    
    if ( $errors->has_errors() ) {
        return $errors;
    }
    
    // Sanitize and prepare data
    $sanitized_data = array(
        'name' => sanitize_text_field( $workflow_data['name'] ),
        'description' => isset( $workflow_data['description'] ) ? wp_kses_post( $workflow_data['description'] ) : '',
        'ai_provider' => sanitize_key( $workflow_data['ai_provider'] ),
        'settings' => is_array( $workflow_data['settings'] ) ? wp_json_encode( $workflow_data['settings'] ) : $workflow_data['settings'],
        'status' => isset( $workflow_data['status'] ) ? sanitize_key( $workflow_data['status'] ) : 'active',
        'user_id' => isset( $workflow_data['user_id'] ) ? (int) $workflow_data['user_id'] : get_current_user_id()
    );
    
    return $sanitized_data;
}

/**
 * Check if workflow name is unique for user
 * 
 * @param string $name Workflow name
 * @param int $user_id User ID
 * @param int $exclude_id Optional workflow ID to exclude from check
 * @return bool True if name is unique, false if duplicate exists
 */
function wp_content_flow_is_workflow_name_unique( $name, $user_id, $exclude_id = 0 ) {
    global $wpdb;
    
    $table_name = wp_content_flow_get_workflows_table_name();
    
    $query = $wpdb->prepare(
        "SELECT id FROM {$table_name} WHERE name = %s AND user_id = %d AND id != %d",
        $name,
        $user_id,
        $exclude_id
    );
    
    $existing_id = $wpdb->get_var( $query );
    
    return $existing_id === null;
}

/**
 * Get workflow by ID with permission check
 * 
 * @param int $workflow_id Workflow ID
 * @param int $user_id Optional user ID for permission check
 * @return array|null Workflow data or null if not found/no permission
 */
function wp_content_flow_get_workflow( $workflow_id, $user_id = null ) {
    global $wpdb;
    
    if ( $user_id === null ) {
        $user_id = get_current_user_id();
    }
    
    $table_name = wp_content_flow_get_workflows_table_name();
    
    $workflow = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d",
        $workflow_id
    ), ARRAY_A );
    
    if ( ! $workflow ) {
        return null;
    }
    
    // Permission check - user can only access their own workflows unless admin
    if ( $workflow['user_id'] != $user_id && ! current_user_can( 'manage_options' ) ) {
        return null;
    }
    
    // Decode JSON settings
    $workflow['settings'] = json_decode( $workflow['settings'], true );
    
    return $workflow;
}

/**
 * Insert workflow into database
 * 
 * @param array $workflow_data Validated workflow data
 * @return int|false Workflow ID on success, false on failure
 */
function wp_content_flow_insert_workflow( $workflow_data ) {
    global $wpdb;
    
    $table_name = wp_content_flow_get_workflows_table_name();
    
    // Check for duplicate name
    if ( ! wp_content_flow_is_workflow_name_unique( $workflow_data['name'], $workflow_data['user_id'] ) ) {
        return false;
    }
    
    $result = $wpdb->insert(
        $table_name,
        $workflow_data,
        array( '%s', '%s', '%s', '%s', '%s', '%d' ) // Format: name, description, ai_provider, settings, status, user_id
    );
    
    if ( $result === false ) {
        error_log( "WP Content Flow: Failed to insert workflow - " . $wpdb->last_error );
        return false;
    }
    
    return $wpdb->insert_id;
}