<?php
/**
 * Database handler for WP Content Flow
 * Creates and manages custom database tables
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPContentFlow_Database {
    
    /**
     * Create plugin database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Workflows table
        $workflows_table = $wpdb->prefix . 'ai_workflows';
        $workflows_sql = "CREATE TABLE $workflows_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            agent_id varchar(100) NOT NULL,
            ai_provider varchar(50) NOT NULL,
            ai_model varchar(100) NOT NULL,
            ai_parameters longtext,
            prompts longtext,
            is_active tinyint(1) DEFAULT 1,
            user_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        // Suggestions table
        $suggestions_table = $wpdb->prefix . 'ai_suggestions';
        $suggestions_sql = "CREATE TABLE $suggestions_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) UNSIGNED NOT NULL,
            workflow_id mediumint(9) NOT NULL,
            original_content longtext,
            suggested_content longtext NOT NULL,
            suggestion_type varchar(50) NOT NULL,
            confidence_score decimal(3,2) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            user_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY workflow_id (workflow_id),
            KEY status (status),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        // Content history table
        $history_table = $wpdb->prefix . 'ai_content_history';
        $history_sql = "CREATE TABLE $history_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) UNSIGNED NOT NULL,
            change_type varchar(50) NOT NULL,
            content_before longtext,
            content_after longtext NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY change_type (change_type),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Workflow templates table
        $templates_table = $wpdb->prefix . 'workflow_templates';
        $templates_sql = "CREATE TABLE $templates_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            category varchar(100),
            template_data longtext NOT NULL,
            is_public tinyint(1) DEFAULT 0,
            user_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY category (category),
            KEY is_public (is_public),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($workflows_sql);
        dbDelta($suggestions_sql);
        dbDelta($history_sql);
        dbDelta($templates_sql);
        
        // Add foreign key constraints if needed
        self::add_foreign_keys();
    }
    
    /**
     * Add foreign key constraints
     */
    private static function add_foreign_keys() {
        global $wpdb;
        
        // Check if constraints already exist to avoid duplicates
        $constraints = $wpdb->get_results(
            "SELECT CONSTRAINT_NAME 
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME LIKE '{$wpdb->prefix}ai_%'"
        );
        
        // Only add if constraints don't exist
        if (empty($constraints)) {
            $suggestions_table = $wpdb->prefix . 'ai_suggestions';
            $workflows_table = $wpdb->prefix . 'ai_workflows';
            $history_table = $wpdb->prefix . 'ai_content_history';
            
            // Add foreign key for suggestions -> workflows
            $wpdb->query(
                "ALTER TABLE $suggestions_table 
                 ADD CONSTRAINT fk_suggestion_workflow 
                 FOREIGN KEY (workflow_id) REFERENCES $workflows_table(id) ON DELETE CASCADE"
            );
        }
    }
    
    /**
     * Drop plugin tables (used during uninstall)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'ai_suggestions',
            $wpdb->prefix . 'ai_content_history', 
            $wpdb->prefix . 'workflow_templates',
            $wpdb->prefix . 'ai_workflows'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
    
    /**
     * Get table names for the plugin
     */
    public static function get_table_names() {
        global $wpdb;
        
        return apply_filters('wp_content_flow_table_names', array(
            'workflows' => $wpdb->prefix . 'ai_workflows',
            'suggestions' => $wpdb->prefix . 'ai_suggestions',
            'content_history' => $wpdb->prefix . 'ai_content_history',
            'workflow_templates' => $wpdb->prefix . 'workflow_templates'
        ));
    }
}