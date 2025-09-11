<?php
/**
 * Audit Trail Database Schema
 * 
 * Creates and manages the wp_content_flow_audit_trail table for comprehensive
 * forensic logging of all content changes, user actions, and system events.
 *
 * @package WP_Content_Flow
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Create wp_content_flow_audit_trail table
 *
 * @return bool True on success, false on failure
 */
function wp_content_flow_create_audit_trail_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'content_flow_audit_trail';
    
    // Check if table already exists
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name ) {
        return true;
    }
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE {$table_name} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        
        -- Event Classification
        event_type varchar(50) NOT NULL,
        category enum('draft_content', 'published_content', 'user_actions', 'system_events', 'security_events') NOT NULL DEFAULT 'system_events',
        severity enum('info', 'warning', 'error', 'critical') DEFAULT 'info',
        
        -- User and Session Context
        user_id bigint(20) UNSIGNED DEFAULT 0,
        user_ip varchar(45) DEFAULT NULL COMMENT 'IPv4 or IPv6 address',
        user_agent text DEFAULT NULL,
        session_id varchar(255) DEFAULT NULL,
        
        -- Request Context
        request_uri varchar(1000) DEFAULT NULL,
        http_method varchar(10) DEFAULT 'GET',
        http_referrer varchar(1000) DEFAULT NULL,
        
        -- Event Data (JSON)
        data longtext DEFAULT NULL COMMENT 'JSON: event-specific data',
        
        -- Content Tracking
        post_id bigint(20) UNSIGNED DEFAULT NULL,
        content_before longtext DEFAULT NULL COMMENT 'Content before change',
        content_after longtext DEFAULT NULL COMMENT 'Content after change',
        content_hash varchar(64) DEFAULT NULL COMMENT 'SHA256 hash of content',
        
        -- AI-Specific Fields
        ai_provider varchar(50) DEFAULT NULL,
        ai_model varchar(100) DEFAULT NULL,
        ai_parameters longtext DEFAULT NULL COMMENT 'JSON: AI generation parameters',
        ai_prompt text DEFAULT NULL,
        ai_response_time decimal(8,3) DEFAULT NULL COMMENT 'Response time in seconds',
        ai_tokens_used int(10) DEFAULT NULL,
        ai_cost_estimate decimal(10,4) DEFAULT NULL,
        
        -- Workflow Context
        workflow_id bigint(20) UNSIGNED DEFAULT NULL,
        workflow_step varchar(100) DEFAULT NULL,
        template_id bigint(20) UNSIGNED DEFAULT NULL,
        
        -- Error Information
        error_code varchar(50) DEFAULT NULL,
        error_message text DEFAULT NULL,
        stack_trace text DEFAULT NULL,
        
        -- System Environment
        site_url varchar(255) DEFAULT NULL,
        wp_version varchar(20) DEFAULT NULL,
        plugin_version varchar(20) DEFAULT NULL,
        php_version varchar(20) DEFAULT NULL,
        memory_usage bigint(20) DEFAULT NULL COMMENT 'Memory usage in bytes',
        
        -- Security and Forensics
        checksum varchar(64) DEFAULT NULL COMMENT 'SHA256 checksum of entry for integrity',
        is_suspicious tinyint(1) DEFAULT 0,
        flagged_reason varchar(255) DEFAULT NULL,
        
        -- Timestamps
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        expires_at datetime DEFAULT NULL COMMENT 'When entry should be purged',
        
        PRIMARY KEY (id),
        
        -- Performance Indexes
        KEY idx_event_type (event_type),
        KEY idx_category (category),
        KEY idx_user_id (user_id),
        KEY idx_post_id (post_id),
        KEY idx_workflow_id (workflow_id),
        KEY idx_template_id (template_id),
        KEY idx_created_at (created_at),
        KEY idx_expires_at (expires_at),
        KEY idx_user_ip (user_ip),
        KEY idx_severity (severity),
        KEY idx_ai_provider (ai_provider),
        KEY idx_session_id (session_id),
        KEY idx_is_suspicious (is_suspicious),
        KEY idx_content_hash (content_hash),
        
        -- Composite Indexes for Common Queries
        KEY idx_user_events (user_id, event_type, created_at),
        KEY idx_post_events (post_id, event_type, created_at),
        KEY idx_ai_operations (ai_provider, ai_model, created_at),
        KEY idx_workflow_events (workflow_id, event_type, created_at),
        KEY idx_security_events (category, is_suspicious, severity, created_at),
        KEY idx_content_changes (post_id, content_hash, created_at),
        KEY idx_error_tracking (error_code, severity, created_at),
        
        -- Full-text search capabilities
        FULLTEXT KEY ft_search_content (error_message, ai_prompt, stack_trace),
        FULLTEXT KEY ft_search_request (request_uri, http_referrer, user_agent),
        
        -- Foreign key constraints
        CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL,
        CONSTRAINT fk_audit_post FOREIGN KEY (post_id) REFERENCES {$wpdb->posts}(ID) ON DELETE SET NULL
    ) {$charset_collate};";
    
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    $result = dbDelta( $sql );
    
    // Verify table was created successfully
    $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name;
    
    if ( $table_exists ) {
        // Create additional indexes for better performance
        wp_content_flow_create_audit_trail_indexes( $table_name );
        
        // Log success
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'WP Content Flow: Audit trail table created successfully' );
        }
        
        return true;
    }
    
    // Log failure
    error_log( 'WP Content Flow: Failed to create audit trail table' );
    return false;
}

/**
 * Create additional indexes for audit trail table
 *
 * @param string $table_name Table name
 */
function wp_content_flow_create_audit_trail_indexes( $table_name ) {
    global $wpdb;
    
    // Additional performance indexes that may not be supported by all MySQL versions
    $indexes = [
        "CREATE INDEX idx_json_post_id ON {$table_name} ((CAST(JSON_EXTRACT(data, '$.post_id') AS UNSIGNED)))",
        "CREATE INDEX idx_json_suggestion_id ON {$table_name} ((CAST(JSON_EXTRACT(data, '$.suggestion_id') AS UNSIGNED)))",
        "CREATE INDEX idx_event_time_range ON {$table_name} (event_type, created_at, expires_at)",
        "CREATE INDEX idx_audit_integrity ON {$table_name} (checksum, is_suspicious)"
    ];
    
    foreach ( $indexes as $index ) {
        $wpdb->query( $index );
    }
}

/**
 * Drop audit trail table (for uninstallation)
 *
 * @return bool True on success, false on failure
 */
function wp_content_flow_drop_audit_trail_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'content_flow_audit_trail';
    
    $result = $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
    
    if ( false !== $result ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'WP Content Flow: Audit trail table dropped successfully' );
        }
        return true;
    }
    
    error_log( 'WP Content Flow: Failed to drop audit trail table' );
    return false;
}

/**
 * Get table schema version for migrations
 *
 * @return string Current schema version
 */
function wp_content_flow_get_audit_trail_schema_version() {
    return '1.0.0';
}

/**
 * Update audit trail table for schema changes
 *
 * @param string $current_version Current schema version
 * @return bool True on success, false on failure
 */
function wp_content_flow_update_audit_trail_schema( $current_version ) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'content_flow_audit_trail';
    
    // Future schema migrations would go here
    switch ( $current_version ) {
        case '1.0.0':
            // No updates needed for initial version
            return true;
            
        case '1.0.1':
            // Example: Add new column for future version
            // $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN new_field VARCHAR(255) DEFAULT NULL" );
            return true;
            
        default:
            return false;
    }
}

/**
 * Create audit trail partitions for better performance (optional)
 * This is useful for high-volume sites with many audit entries
 */
function wp_content_flow_create_audit_trail_partitions() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'content_flow_audit_trail';
    
    // Check if partitioning is supported
    $partition_support = $wpdb->get_var( "SELECT COUNT(*) FROM INFORMATION_SCHEMA.PLUGINS WHERE PLUGIN_NAME = 'partition'" );
    
    if ( ! $partition_support ) {
        return false;
    }
    
    // Create monthly partitions for the next 12 months
    $partitions = [];
    for ( $i = 0; $i < 12; $i++ ) {
        $date = date( 'Y_m', strtotime( "+{$i} months" ) );
        $next_month = date( 'Y-m-01', strtotime( "+{$i} months" ) );
        
        $partitions[] = "PARTITION p{$date} VALUES LESS THAN (TO_DAYS('{$next_month}'))";
    }
    
    $partition_clause = implode( ', ', $partitions );
    
    // Alter table to add partitioning
    $sql = "ALTER TABLE {$table_name} 
            PARTITION BY RANGE (TO_DAYS(created_at)) (
                {$partition_clause},
                PARTITION p_future VALUES LESS THAN MAXVALUE
            )";
    
    $result = $wpdb->query( $sql );
    
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        if ( $result !== false ) {
            error_log( 'WP Content Flow: Audit trail table partitioned successfully' );
        } else {
            error_log( 'WP Content Flow: Failed to partition audit trail table' );
        }
    }
    
    return $result !== false;
}

/**
 * Optimize audit trail table periodically
 */
function wp_content_flow_optimize_audit_trail_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'content_flow_audit_trail';
    
    // Optimize table structure
    $wpdb->query( "OPTIMIZE TABLE {$table_name}" );
    
    // Analyze table for query optimization
    $wpdb->query( "ANALYZE TABLE {$table_name}" );
    
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'WP Content Flow: Audit trail table optimized' );
    }
}