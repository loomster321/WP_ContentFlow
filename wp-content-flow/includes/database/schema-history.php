<?php
/**
 * Database schema for wp_ai_content_history table
 * 
 * This implements the Content History entity from data-model.md
 * for tracking AI-assisted content modifications.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Create wp_ai_content_history table
 * 
 * @global wpdb $wpdb WordPress database abstraction object
 * @return bool True on success, false on failure
 */
function wp_content_flow_create_history_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'ai_content_history';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        post_id BIGINT(20) UNSIGNED NOT NULL,
        suggestion_id BIGINT(20) UNSIGNED NULL COMMENT 'NULL for manual edits',
        user_id BIGINT(20) UNSIGNED NOT NULL,
        content_before LONGTEXT NOT NULL,
        content_after LONGTEXT NOT NULL,
        change_type ENUM('ai_generated', 'ai_improved', 'manual_edit', 'ai_rejected') NOT NULL,
        diff_data LONGTEXT COMMENT 'JSON with detailed change information',
        revision_number INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        FOREIGN KEY (post_id) REFERENCES {$wpdb->posts}(ID) ON DELETE CASCADE,
        FOREIGN KEY (suggestion_id) REFERENCES {$wpdb->prefix}ai_suggestions(id) ON DELETE SET NULL,
        FOREIGN KEY (user_id) REFERENCES {$wpdb->users}(ID) ON DELETE CASCADE,
        INDEX idx_post_revision (post_id, revision_number),
        INDEX idx_change_type (change_type),
        INDEX idx_created (created_at DESC),
        INDEX idx_post_created (post_id, created_at DESC)
    ) {$charset_collate};";
    
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    
    $result = dbDelta( $sql );
    
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( "WP Content Flow: Created content history table - " . print_r( $result, true ) );
    }
    
    // Verify table was created
    $table_exists = $wpdb->get_var( $wpdb->prepare(
        "SHOW TABLES LIKE %s",
        $table_name
    ) ) === $table_name;
    
    return $table_exists;
}

/**
 * Drop wp_ai_content_history table
 * 
 * @return bool True on success, false on failure
 */
function wp_content_flow_drop_history_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'ai_content_history';
    $result = $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
    
    return $result !== false;
}

/**
 * Get content history table name
 * 
 * @return string Table name with prefix
 */
function wp_content_flow_get_history_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'ai_content_history';
}

/**
 * Create content history entry
 * 
 * @param array $history_data History entry data
 * @return int|false History entry ID on success, false on failure
 */
function wp_content_flow_create_history_entry( $history_data ) {
    global $wpdb;
    
    // Validate required fields
    $required_fields = array( 'post_id', 'user_id', 'content_before', 'content_after', 'change_type' );
    
    foreach ( $required_fields as $field ) {
        if ( ! isset( $history_data[ $field ] ) ) {
            error_log( "WP Content Flow: Missing required history field: {$field}" );
            return false;
        }
    }
    
    // Validate change type
    $valid_change_types = array( 'ai_generated', 'ai_improved', 'manual_edit', 'ai_rejected' );
    if ( ! in_array( $history_data['change_type'], $valid_change_types, true ) ) {
        error_log( "WP Content Flow: Invalid change type: " . $history_data['change_type'] );
        return false;
    }
    
    // Permission check
    if ( ! current_user_can( 'edit_post', $history_data['post_id'] ) ) {
        error_log( "WP Content Flow: No permission to create history for post: " . $history_data['post_id'] );
        return false;
    }
    
    // Get next revision number for this post
    $revision_number = wp_content_flow_get_next_revision_number( $history_data['post_id'] );
    
    $table_name = wp_content_flow_get_history_table_name();
    
    // Calculate diff data
    $diff_data = wp_content_flow_calculate_content_diff( 
        $history_data['content_before'], 
        $history_data['content_after'] 
    );
    
    // Prepare data for insertion
    $insert_data = array(
        'post_id' => (int) $history_data['post_id'],
        'suggestion_id' => isset( $history_data['suggestion_id'] ) ? (int) $history_data['suggestion_id'] : null,
        'user_id' => (int) $history_data['user_id'],
        'content_before' => $history_data['content_before'],
        'content_after' => $history_data['content_after'],
        'change_type' => $history_data['change_type'],
        'diff_data' => wp_json_encode( $diff_data ),
        'revision_number' => $revision_number
    );
    
    $format = array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d' );
    
    // Handle null suggestion_id
    if ( $insert_data['suggestion_id'] === null ) {
        $format[1] = null;
    }
    
    $result = $wpdb->insert( $table_name, $insert_data, $format );
    
    if ( $result === false ) {
        error_log( "WP Content Flow: Failed to insert history entry - " . $wpdb->last_error );
        return false;
    }
    
    $history_id = $wpdb->insert_id;
    
    // Fire action hook for extensibility
    do_action( 'wp_content_flow_history_entry_created', $history_id, $history_data );
    
    return $history_id;
}

/**
 * Get content history for a post
 * 
 * @param int $post_id Post ID
 * @param array $args Query arguments
 * @return array Array of history entries
 */
function wp_content_flow_get_post_history( $post_id, $args = array() ) {
    global $wpdb;
    
    // Permission check
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return array();
    }
    
    $defaults = array(
        'change_type' => null,
        'limit' => 20,
        'offset' => 0,
        'orderby' => 'created_at',
        'order' => 'DESC'
    );
    
    $args = wp_parse_args( $args, $defaults );
    
    $table_name = wp_content_flow_get_history_table_name();
    
    $where_clauses = array( 'post_id = %d' );
    $where_values = array( $post_id );
    
    if ( $args['change_type'] ) {
        $where_clauses[] = 'change_type = %s';
        $where_values[] = $args['change_type'];
    }
    
    $where_clause = implode( ' AND ', $where_clauses );
    $order_clause = sprintf( 'ORDER BY %s %s', $args['orderby'], $args['order'] );
    $limit_clause = sprintf( 'LIMIT %d OFFSET %d', $args['limit'], $args['offset'] );
    
    $query = $wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE {$where_clause} {$order_clause} {$limit_clause}",
        $where_values
    );
    
    $history_entries = $wpdb->get_results( $query, ARRAY_A );
    
    // Decode JSON diff_data for each entry
    foreach ( $history_entries as &$entry ) {
        if ( $entry['diff_data'] ) {
            $entry['diff_data'] = json_decode( $entry['diff_data'], true );
        }
    }
    
    return $history_entries;
}

/**
 * Get next revision number for a post
 * 
 * @param int $post_id Post ID
 * @return int Next revision number
 */
function wp_content_flow_get_next_revision_number( $post_id ) {
    global $wpdb;
    
    $table_name = wp_content_flow_get_history_table_name();
    
    $max_revision = $wpdb->get_var( $wpdb->prepare(
        "SELECT MAX(revision_number) FROM {$table_name} WHERE post_id = %d",
        $post_id
    ) );
    
    return $max_revision ? $max_revision + 1 : 1;
}

/**
 * Calculate diff between two content strings
 * 
 * @param string $before Original content
 * @param string $after Modified content
 * @return array Diff data
 */
function wp_content_flow_calculate_content_diff( $before, $after ) {
    // Basic diff calculation - word and character counts
    $before_words = str_word_count( $before );
    $after_words = str_word_count( $after );
    
    $before_chars = strlen( $before );
    $after_chars = strlen( $after );
    
    $diff_data = array(
        'word_count_change' => $after_words - $before_words,
        'character_count_change' => $after_chars - $before_chars,
        'before_stats' => array(
            'words' => $before_words,
            'characters' => $before_chars
        ),
        'after_stats' => array(
            'words' => $after_words,
            'characters' => $after_chars
        ),
        'similarity_score' => wp_content_flow_calculate_similarity( $before, $after )
    );
    
    // Calculate semantic changes (basic implementation)
    $before_sentences = preg_split( '/[.!?]+/', $before, -1, PREG_SPLIT_NO_EMPTY );
    $after_sentences = preg_split( '/[.!?]+/', $after, -1, PREG_SPLIT_NO_EMPTY );
    
    $diff_data['sentence_count_change'] = count( $after_sentences ) - count( $before_sentences );
    
    return $diff_data;
}

/**
 * Calculate similarity between two strings (basic implementation)
 * 
 * @param string $str1 First string
 * @param string $str2 Second string
 * @return float Similarity score between 0.0 and 1.0
 */
function wp_content_flow_calculate_similarity( $str1, $str2 ) {
    $len1 = strlen( $str1 );
    $len2 = strlen( $str2 );
    
    if ( $len1 === 0 && $len2 === 0 ) {
        return 1.0;
    }
    
    if ( $len1 === 0 || $len2 === 0 ) {
        return 0.0;
    }
    
    // Use similar_text function for basic similarity
    similar_text( $str1, $str2, $percentage );
    
    return $percentage / 100.0;
}

/**
 * Get history entry by ID
 * 
 * @param int $history_id History entry ID
 * @return array|null History entry or null if not found/no permission
 */
function wp_content_flow_get_history_entry( $history_id ) {
    global $wpdb;
    
    $table_name = wp_content_flow_get_history_table_name();
    
    $entry = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d",
        $history_id
    ), ARRAY_A );
    
    if ( ! $entry ) {
        return null;
    }
    
    // Permission check
    if ( ! current_user_can( 'edit_post', $entry['post_id'] ) ) {
        return null;
    }
    
    // Decode JSON diff_data
    if ( $entry['diff_data'] ) {
        $entry['diff_data'] = json_decode( $entry['diff_data'], true );
    }
    
    return $entry;
}