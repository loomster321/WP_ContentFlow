<?php
/**
 * Workflow Templates Database Schema
 * 
 * Creates and manages the wp_workflow_templates table for storing reusable
 * workflow configurations with AI parameters, processing steps, and approval criteria.
 *
 * @package WP_Content_Flow
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Create wp_workflow_templates table
 *
 * @return bool True on success, false on failure
 */
function wp_content_flow_create_workflow_templates_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'workflow_templates';
    
    // Check if table already exists
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name ) {
        return true;
    }
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE {$table_name} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        template_name varchar(255) NOT NULL,
        template_description text,
        template_type enum('content_generation', 'content_review', 'approval_workflow', 'publishing', 'custom') NOT NULL DEFAULT 'content_generation',
        
        -- AI Configuration
        ai_provider varchar(50) NOT NULL DEFAULT 'openai',
        ai_model varchar(100) DEFAULT 'gpt-3.5-turbo',
        ai_parameters longtext DEFAULT NULL COMMENT 'JSON: temperature, max_tokens, etc.',
        
        -- Workflow Steps Configuration
        workflow_steps longtext NOT NULL COMMENT 'JSON: array of workflow step definitions',
        approval_criteria longtext DEFAULT NULL COMMENT 'JSON: approval rules and criteria',
        
        -- Content Configuration  
        content_types varchar(500) DEFAULT 'post,page' COMMENT 'Comma-separated list of post types',
        prompt_template longtext DEFAULT NULL,
        content_filters longtext DEFAULT NULL COMMENT 'JSON: filtering rules',
        
        -- User Access Control
        allowed_roles varchar(500) DEFAULT 'administrator,editor' COMMENT 'Comma-separated WordPress roles',
        created_by bigint(20) UNSIGNED NOT NULL,
        assigned_users longtext DEFAULT NULL COMMENT 'JSON: specific user assignments',
        
        -- Automation Settings
        auto_trigger tinyint(1) DEFAULT 0 COMMENT 'Auto-trigger on content creation',
        trigger_conditions longtext DEFAULT NULL COMMENT 'JSON: trigger rules',
        schedule_settings longtext DEFAULT NULL COMMENT 'JSON: cron scheduling',
        
        -- Performance & Quality Settings
        max_execution_time int(10) DEFAULT 300 COMMENT 'Max execution seconds',
        retry_attempts tinyint(3) DEFAULT 3,
        quality_threshold decimal(3,2) DEFAULT 0.80 COMMENT 'Minimum quality score',
        
        -- Notification Settings
        notification_settings longtext DEFAULT NULL COMMENT 'JSON: email, slack, etc.',
        
        -- Status and Metadata
        template_status enum('active', 'inactive', 'draft', 'archived') NOT NULL DEFAULT 'draft',
        version varchar(20) DEFAULT '1.0.0',
        tags varchar(1000) DEFAULT NULL COMMENT 'Comma-separated tags',
        
        -- Audit Fields
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        last_used_at datetime DEFAULT NULL,
        usage_count bigint(20) DEFAULT 0,
        
        -- Performance Metrics
        avg_execution_time decimal(8,3) DEFAULT NULL COMMENT 'Average execution time in seconds',
        success_rate decimal(5,2) DEFAULT NULL COMMENT 'Success percentage',
        
        PRIMARY KEY (id),
        KEY idx_template_type (template_type),
        KEY idx_template_status (template_status),
        KEY idx_created_by (created_by),
        KEY idx_ai_provider (ai_provider),
        KEY idx_created_at (created_at),
        KEY idx_last_used (last_used_at),
        KEY idx_auto_trigger (auto_trigger),
        KEY idx_composite_search (template_type, template_status, ai_provider),
        
        -- Full-text search for template names and descriptions
        FULLTEXT KEY ft_template_search (template_name, template_description, tags),
        
        -- Foreign key constraints
        CONSTRAINT fk_workflow_template_creator FOREIGN KEY (created_by) REFERENCES {$wpdb->users}(ID) ON DELETE CASCADE
    ) {$charset_collate};";
    
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    $result = dbDelta( $sql );
    
    // Verify table was created successfully
    $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name;
    
    if ( $table_exists ) {
        // Insert default workflow templates
        wp_content_flow_insert_default_workflow_templates();
        
        // Log success
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'WP Content Flow: Workflow templates table created successfully' );
        }
        
        return true;
    }
    
    // Log failure
    error_log( 'WP Content Flow: Failed to create workflow templates table' );
    return false;
}

/**
 * Insert default workflow templates
 *
 * @return void
 */
function wp_content_flow_insert_default_workflow_templates() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'workflow_templates';
    
    // Check if templates already exist
    $existing_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
    if ( $existing_count > 0 ) {
        return;
    }
    
    $current_user_id = get_current_user_id() ?: 1;
    
    $default_templates = [
        [
            'template_name' => 'Blog Post Generation',
            'template_description' => 'Generate blog posts with SEO optimization and review workflow',
            'template_type' => 'content_generation',
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-3.5-turbo',
            'ai_parameters' => json_encode([
                'temperature' => 0.7,
                'max_tokens' => 2000,
                'top_p' => 0.9
            ]),
            'workflow_steps' => json_encode([
                ['step' => 'generate', 'role' => 'author', 'auto' => true],
                ['step' => 'review', 'role' => 'editor', 'auto' => false],
                ['step' => 'approve', 'role' => 'editor', 'auto' => false],
                ['step' => 'publish', 'role' => 'editor', 'auto' => true]
            ]),
            'content_types' => 'post',
            'prompt_template' => 'Write a comprehensive blog post about: {topic}. Include an introduction, main points with examples, and a conclusion. Target audience: {audience}. Tone: {tone}.',
            'allowed_roles' => 'administrator,editor,author',
            'created_by' => $current_user_id,
            'template_status' => 'active'
        ],
        [
            'template_name' => 'Product Description',
            'template_description' => 'Generate product descriptions with marketing focus',
            'template_type' => 'content_generation',
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-3.5-turbo',
            'ai_parameters' => json_encode([
                'temperature' => 0.8,
                'max_tokens' => 500,
                'top_p' => 0.9
            ]),
            'workflow_steps' => json_encode([
                ['step' => 'generate', 'role' => 'author', 'auto' => true],
                ['step' => 'review', 'role' => 'editor', 'auto' => false],
                ['step' => 'approve', 'role' => 'administrator', 'auto' => false]
            ]),
            'content_types' => 'product,page',
            'prompt_template' => 'Create a compelling product description for: {product_name}. Features: {features}. Benefits: {benefits}. Target customer: {target}.',
            'allowed_roles' => 'administrator,editor',
            'created_by' => $current_user_id,
            'template_status' => 'active'
        ],
        [
            'template_name' => 'Content Review Workflow',
            'template_description' => 'Multi-stage content review with AI assistance',
            'template_type' => 'content_review',
            'ai_provider' => 'anthropic',
            'ai_model' => 'claude-3-sonnet',
            'ai_parameters' => json_encode([
                'temperature' => 0.3,
                'max_tokens' => 1000
            ]),
            'workflow_steps' => json_encode([
                ['step' => 'ai_check', 'role' => 'system', 'auto' => true],
                ['step' => 'grammar_check', 'role' => 'contributor', 'auto' => false],
                ['step' => 'content_review', 'role' => 'editor', 'auto' => false],
                ['step' => 'final_approval', 'role' => 'administrator', 'auto' => false]
            ]),
            'approval_criteria' => json_encode([
                'min_word_count' => 300,
                'readability_score' => 60,
                'ai_quality_score' => 0.75
            ]),
            'content_types' => 'post,page',
            'allowed_roles' => 'administrator,editor,contributor',
            'created_by' => $current_user_id,
            'template_status' => 'active'
        ]
    ];
    
    foreach ( $default_templates as $template ) {
        $wpdb->insert( $table_name, $template );
    }
    
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'WP Content Flow: Default workflow templates inserted' );
    }
}

/**
 * Drop workflow templates table (for uninstallation)
 *
 * @return bool True on success, false on failure
 */
function wp_content_flow_drop_workflow_templates_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'workflow_templates';
    
    $result = $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
    
    if ( false !== $result ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'WP Content Flow: Workflow templates table dropped successfully' );
        }
        return true;
    }
    
    error_log( 'WP Content Flow: Failed to drop workflow templates table' );
    return false;
}

/**
 * Get table schema version for migrations
 *
 * @return string Current schema version
 */
function wp_content_flow_get_workflow_templates_schema_version() {
    return '1.0.0';
}

/**
 * Update workflow templates table for schema changes
 *
 * @param string $current_version Current schema version
 * @return bool True on success, false on failure
 */
function wp_content_flow_update_workflow_templates_schema( $current_version ) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'workflow_templates';
    
    // Future schema migrations would go here
    switch ( $current_version ) {
        case '1.0.0':
            // No updates needed for initial version
            return true;
            
        default:
            return false;
    }
}