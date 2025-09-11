<?php
/**
 * Workflow Approval Assignments Database Schema
 * 
 * Creates and manages the wp_workflow_approval_assignments table for tracking
 * content approval workflows, assignments, and approval decisions.
 *
 * @package WP_Content_Flow
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Create wp_workflow_approval_assignments table
 *
 * @return bool True on success, false on failure
 */
function wp_content_flow_create_approval_assignments_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'workflow_approval_assignments';
    
    // Check if table already exists
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name ) {
        return true;
    }
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE {$table_name} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        workflow_id bigint(20) UNSIGNED NOT NULL,
        post_id bigint(20) UNSIGNED DEFAULT NULL,
        
        -- Assignment Details
        required_role varchar(50) NOT NULL DEFAULT 'editor',
        assigned_user_id bigint(20) UNSIGNED DEFAULT NULL,
        content longtext DEFAULT NULL COMMENT 'Content to be approved',
        
        -- Approval Status
        status enum('pending', 'approved', 'rejected', 'expired') NOT NULL DEFAULT 'pending',
        priority enum('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
        
        -- Approval Decision
        approved_by bigint(20) UNSIGNED DEFAULT NULL,
        approved_at datetime DEFAULT NULL,
        notes text DEFAULT NULL COMMENT 'Approval/rejection notes',
        
        -- Assignment Metadata
        due_date datetime DEFAULT NULL,
        reminder_sent_at datetime DEFAULT NULL,
        expires_at datetime DEFAULT NULL,
        
        -- Audit Fields
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        created_by bigint(20) UNSIGNED NOT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        PRIMARY KEY (id),
        KEY idx_workflow_id (workflow_id),
        KEY idx_post_id (post_id),
        KEY idx_status (status),
        KEY idx_required_role (required_role),
        KEY idx_assigned_user (assigned_user_id),
        KEY idx_approved_by (approved_by),
        KEY idx_created_by (created_by),
        KEY idx_due_date (due_date),
        KEY idx_created_at (created_at),
        KEY idx_composite_status (status, priority, created_at),
        
        -- Foreign key constraints
        CONSTRAINT fk_approval_workflow FOREIGN KEY (workflow_id) REFERENCES {$wpdb->prefix}ai_workflows(id) ON DELETE CASCADE,
        CONSTRAINT fk_approval_post FOREIGN KEY (post_id) REFERENCES {$wpdb->posts}(ID) ON DELETE SET NULL,
        CONSTRAINT fk_approval_assigned_user FOREIGN KEY (assigned_user_id) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL,
        CONSTRAINT fk_approval_approved_by FOREIGN KEY (approved_by) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL,
        CONSTRAINT fk_approval_creator FOREIGN KEY (created_by) REFERENCES {$wpdb->users}(ID) ON DELETE CASCADE
    ) {$charset_collate};";
    
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    $result = dbDelta( $sql );
    
    // Verify table was created successfully
    $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name;
    
    if ( $table_exists ) {
        // Log success
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'WP Content Flow: Workflow approval assignments table created successfully' );
        }
        
        return true;
    }
    
    // Log failure
    error_log( 'WP Content Flow: Failed to create workflow approval assignments table' );
    return false;
}

/**
 * Drop workflow approval assignments table (for uninstallation)
 *
 * @return bool True on success, false on failure
 */
function wp_content_flow_drop_approval_assignments_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'workflow_approval_assignments';
    
    $result = $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
    
    if ( false !== $result ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'WP Content Flow: Workflow approval assignments table dropped successfully' );
        }
        return true;
    }
    
    error_log( 'WP Content Flow: Failed to drop workflow approval assignments table' );
    return false;
}

/**
 * Get table schema version for migrations
 *
 * @return string Current schema version
 */
function wp_content_flow_get_approval_assignments_schema_version() {
    return '1.0.0';
}

/**
 * Update approval assignments table for schema changes
 *
 * @param string $current_version Current schema version
 * @return bool True on success, false on failure
 */
function wp_content_flow_update_approval_assignments_schema( $current_version ) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'workflow_approval_assignments';
    
    // Future schema migrations would go here
    switch ( $current_version ) {
        case '1.0.0':
            // No updates needed for initial version
            return true;
            
        default:
            return false;
    }
}