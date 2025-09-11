<?php
/**
 * Shared Queues Database Schema
 * 
 * Creates and manages the wp_content_flow_shared_queues table for collaborative
 * AI suggestion queues and team-based content workflows.
 *
 * @package WP_Content_Flow
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Create wp_content_flow_shared_queues table
 *
 * @return bool True on success, false on failure
 */
function wp_content_flow_create_shared_queues_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'content_flow_shared_queues';
    
    // Check if table already exists
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name ) {
        return true;
    }
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE {$table_name} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        
        -- Queue Identification
        queue_name varchar(255) DEFAULT NULL,
        queue_description text DEFAULT NULL,
        post_id bigint(20) UNSIGNED DEFAULT NULL,
        
        -- Collaboration Settings
        participants longtext NOT NULL COMMENT 'JSON: array of user IDs who can access queue',
        queue_type enum('shared_suggestions', 'collaborative_editing', 'review_workflow', 'mixed') DEFAULT 'shared_suggestions',
        
        -- Access Control
        visibility enum('private', 'team', 'public') DEFAULT 'team',
        join_permission enum('invite_only', 'request_to_join', 'auto_join') DEFAULT 'invite_only',
        
        -- Queue Options
        options longtext DEFAULT NULL COMMENT 'JSON: configuration options',
        
        -- Assignment Rules
        auto_assign tinyint(1) DEFAULT 0,
        assignment_method enum('round_robin', 'workload_based', 'skill_based', 'manual') DEFAULT 'manual',
        max_suggestions_per_user int(10) DEFAULT 5,
        
        -- Priority and Deadlines
        priority_system tinyint(1) DEFAULT 1,
        default_priority enum('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
        deadline_enabled tinyint(1) DEFAULT 0,
        default_deadline_hours int(10) DEFAULT 24,
        
        -- Notification Settings
        notification_frequency enum('immediate', 'hourly', 'daily', 'weekly', 'disabled') DEFAULT 'immediate',
        notification_channels longtext DEFAULT NULL COMMENT 'JSON: enabled notification channels',
        
        -- Statistics
        total_suggestions int(10) DEFAULT 0,
        completed_suggestions int(10) DEFAULT 0,
        avg_completion_time decimal(8,2) DEFAULT NULL COMMENT 'Average completion time in hours',
        
        -- Status and Metadata
        status enum('active', 'paused', 'archived', 'closed') NOT NULL DEFAULT 'active',
        tags varchar(500) DEFAULT NULL COMMENT 'Comma-separated tags',
        
        -- Audit Fields
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        created_by bigint(20) UNSIGNED NOT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        last_activity_at datetime DEFAULT NULL,
        
        PRIMARY KEY (id),
        KEY idx_post_id (post_id),
        KEY idx_status (status),
        KEY idx_queue_type (queue_type),
        KEY idx_visibility (visibility),
        KEY idx_created_by (created_by),
        KEY idx_created_at (created_at),
        KEY idx_last_activity (last_activity_at),
        KEY idx_priority_system (priority_system),
        KEY idx_composite_search (status, queue_type, visibility),
        
        -- Full-text search for queue names and descriptions
        FULLTEXT KEY ft_queue_search (queue_name, queue_description, tags),
        
        -- Foreign key constraints
        CONSTRAINT fk_shared_queue_post FOREIGN KEY (post_id) REFERENCES {$wpdb->posts}(ID) ON DELETE SET NULL,
        CONSTRAINT fk_shared_queue_creator FOREIGN KEY (created_by) REFERENCES {$wpdb->users}(ID) ON DELETE CASCADE
    ) {$charset_collate};";
    
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    $result = dbDelta( $sql );
    
    // Verify table was created successfully
    $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name;
    
    if ( $table_exists ) {
        // Log success
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'WP Content Flow: Shared queues table created successfully' );
        }
        
        return true;
    }
    
    // Log failure
    error_log( 'WP Content Flow: Failed to create shared queues table' );
    return false;
}

/**
 * Drop shared queues table (for uninstallation)
 *
 * @return bool True on success, false on failure
 */
function wp_content_flow_drop_shared_queues_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'content_flow_shared_queues';
    
    $result = $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
    
    if ( false !== $result ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'WP Content Flow: Shared queues table dropped successfully' );
        }
        return true;
    }
    
    error_log( 'WP Content Flow: Failed to drop shared queues table' );
    return false;
}

/**
 * Get table schema version for migrations
 *
 * @return string Current schema version
 */
function wp_content_flow_get_shared_queues_schema_version() {
    return '1.0.0';
}

/**
 * Update shared queues table for schema changes
 *
 * @param string $current_version Current schema version
 * @return bool True on success, false on failure
 */
function wp_content_flow_update_shared_queues_schema( $current_version ) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'content_flow_shared_queues';
    
    // Future schema migrations would go here
    switch ( $current_version ) {
        case '1.0.0':
            // No updates needed for initial version
            return true;
            
        default:
            return false;
    }
}