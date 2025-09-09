<?php
/**
 * Database schema for wp_ai_suggestions table
 * 
 * This implements the AI Suggestion entity from data-model.md
 * to make the AI generation contract tests pass.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Create wp_ai_suggestions table
 * 
 * @global wpdb $wpdb WordPress database abstraction object
 * @return bool True on success, false on failure
 */
function wp_content_flow_create_suggestions_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'ai_suggestions';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        post_id BIGINT(20) UNSIGNED NOT NULL,
        workflow_id BIGINT(20) UNSIGNED NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        original_content LONGTEXT NOT NULL,
        suggested_content LONGTEXT NOT NULL,
        suggestion_type ENUM('generation', 'improvement', 'correction') NOT NULL,
        status ENUM('pending', 'accepted', 'rejected', 'modified') DEFAULT 'pending',
        confidence_score DECIMAL(3,2) DEFAULT NULL COMMENT 'AI confidence rating (0.00-1.00)',
        metadata LONGTEXT COMMENT 'JSON for additional AI data',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        processed_at DATETIME NULL COMMENT 'When user acted on suggestion',
        PRIMARY KEY (id),
        FOREIGN KEY (post_id) REFERENCES {$wpdb->posts}(ID) ON DELETE CASCADE,
        FOREIGN KEY (workflow_id) REFERENCES {$wpdb->prefix}ai_workflows(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES {$wpdb->users}(ID) ON DELETE CASCADE,
        INDEX idx_post_workflow (post_id, workflow_id),
        INDEX idx_status (status),
        INDEX idx_user_status (user_id, status),
        INDEX idx_confidence (confidence_score),
        INDEX idx_type (suggestion_type),
        INDEX idx_created (created_at DESC)
    ) {$charset_collate};";
    
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    
    $result = dbDelta( $sql );
    
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( "WP Content Flow: Created suggestions table - " . print_r( $result, true ) );
    }
    
    // Verify table was created
    $table_exists = $wpdb->get_var( $wpdb->prepare(
        "SHOW TABLES LIKE %s",
        $table_name
    ) ) === $table_name;
    
    return $table_exists;
}

/**
 * Drop wp_ai_suggestions table
 * 
 * @return bool True on success, false on failure
 */
function wp_content_flow_drop_suggestions_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'ai_suggestions';
    $result = $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
    
    return $result !== false;
}

/**
 * Get suggestions table name
 * 
 * @return string Table name with prefix
 */
function wp_content_flow_get_suggestions_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'ai_suggestions';
}

/**
 * Validate suggestion data
 * 
 * @param array $suggestion_data Suggestion data to validate
 * @return array|WP_Error Validated data or error
 */
function wp_content_flow_validate_suggestion_data( $suggestion_data ) {
    $errors = new WP_Error();
    
    // Required fields
    $required_fields = array( 'post_id', 'workflow_id', 'user_id', 'original_content', 'suggested_content', 'suggestion_type' );
    
    foreach ( $required_fields as $field ) {
        if ( empty( $suggestion_data[ $field ] ) ) {
            $errors->add( "missing_{$field}", sprintf( __( '%s is required.', 'wp-content-flow' ), ucfirst( str_replace( '_', ' ', $field ) ) ) );
        }
    }
    
    // Validate suggestion type
    $valid_types = array( 'generation', 'improvement', 'correction' );
    if ( ! empty( $suggestion_data['suggestion_type'] ) && ! in_array( $suggestion_data['suggestion_type'], $valid_types, true ) ) {
        $errors->add( 'invalid_suggestion_type', sprintf(
            __( 'Suggestion type must be one of: %s', 'wp-content-flow' ),
            implode( ', ', $valid_types )
        ) );
    }
    
    // Validate status
    if ( isset( $suggestion_data['status'] ) ) {
        $valid_statuses = array( 'pending', 'accepted', 'rejected', 'modified' );
        if ( ! in_array( $suggestion_data['status'], $valid_statuses, true ) ) {
            $errors->add( 'invalid_status', sprintf(
                __( 'Status must be one of: %s', 'wp-content-flow' ),
                implode( ', ', $valid_statuses )
            ) );
        }
    }
    
    // Validate confidence score
    if ( isset( $suggestion_data['confidence_score'] ) ) {
        $score = (float) $suggestion_data['confidence_score'];
        if ( $score < 0.0 || $score > 1.0 ) {
            $errors->add( 'invalid_confidence_score', __( 'Confidence score must be between 0.00 and 1.00.', 'wp-content-flow' ) );
        }
    }
    
    // Validate post exists and user has permission
    if ( ! empty( $suggestion_data['post_id'] ) ) {
        $post = get_post( $suggestion_data['post_id'] );
        if ( ! $post ) {
            $errors->add( 'invalid_post', __( 'Post does not exist.', 'wp-content-flow' ) );
        } elseif ( ! current_user_can( 'edit_post', $suggestion_data['post_id'] ) ) {
            $errors->add( 'no_post_permission', __( 'You do not have permission to edit this post.', 'wp-content-flow' ) );
        }
    }
    
    // Validate workflow exists and is active
    if ( ! empty( $suggestion_data['workflow_id'] ) ) {
        $workflow = wp_content_flow_get_workflow( $suggestion_data['workflow_id'] );
        if ( ! $workflow ) {
            $errors->add( 'invalid_workflow', __( 'Workflow does not exist or you do not have permission to use it.', 'wp-content-flow' ) );
        } elseif ( $workflow['status'] !== 'active' ) {
            $errors->add( 'inactive_workflow', __( 'Workflow is not active.', 'wp-content-flow' ) );
        }
    }
    
    if ( $errors->has_errors() ) {
        return $errors;
    }
    
    // Sanitize data
    $sanitized_data = array(
        'post_id' => (int) $suggestion_data['post_id'],
        'workflow_id' => (int) $suggestion_data['workflow_id'], 
        'user_id' => (int) $suggestion_data['user_id'],
        'original_content' => wp_kses_post( $suggestion_data['original_content'] ),
        'suggested_content' => wp_kses_post( $suggestion_data['suggested_content'] ),
        'suggestion_type' => sanitize_key( $suggestion_data['suggestion_type'] ),
        'status' => isset( $suggestion_data['status'] ) ? sanitize_key( $suggestion_data['status'] ) : 'pending',
        'confidence_score' => isset( $suggestion_data['confidence_score'] ) ? (float) $suggestion_data['confidence_score'] : null,
        'metadata' => isset( $suggestion_data['metadata'] ) ? wp_json_encode( $suggestion_data['metadata'] ) : null
    );
    
    return $sanitized_data;
}

/**
 * Insert suggestion into database
 * 
 * @param array $suggestion_data Validated suggestion data
 * @return int|false Suggestion ID on success, false on failure
 */
function wp_content_flow_insert_suggestion( $suggestion_data ) {
    global $wpdb;
    
    $table_name = wp_content_flow_get_suggestions_table_name();
    
    $result = $wpdb->insert(
        $table_name,
        $suggestion_data,
        array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%f', '%s' )
    );
    
    if ( $result === false ) {
        error_log( "WP Content Flow: Failed to insert suggestion - " . $wpdb->last_error );
        return false;
    }
    
    return $wpdb->insert_id;
}

/**
 * Get suggestion by ID with permission check
 * 
 * @param int $suggestion_id Suggestion ID
 * @return array|null Suggestion data or null if not found/no permission
 */
function wp_content_flow_get_suggestion( $suggestion_id ) {
    global $wpdb;
    
    $table_name = wp_content_flow_get_suggestions_table_name();
    
    $suggestion = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d",
        $suggestion_id
    ), ARRAY_A );
    
    if ( ! $suggestion ) {
        return null;
    }
    
    // Permission check - user must be able to edit the associated post
    if ( ! current_user_can( 'edit_post', $suggestion['post_id'] ) ) {
        return null;
    }
    
    // Decode JSON metadata
    if ( $suggestion['metadata'] ) {
        $suggestion['metadata'] = json_decode( $suggestion['metadata'], true );
    }
    
    return $suggestion;
}

/**
 * Update suggestion status
 * 
 * @param int $suggestion_id Suggestion ID
 * @param string $status New status
 * @return bool True on success, false on failure
 */
function wp_content_flow_update_suggestion_status( $suggestion_id, $status ) {
    global $wpdb;
    
    $valid_statuses = array( 'pending', 'accepted', 'rejected', 'modified' );
    if ( ! in_array( $status, $valid_statuses, true ) ) {
        return false;
    }
    
    $table_name = wp_content_flow_get_suggestions_table_name();
    
    $result = $wpdb->update(
        $table_name,
        array(
            'status' => $status,
            'processed_at' => current_time( 'mysql', true )
        ),
        array( 'id' => $suggestion_id ),
        array( '%s', '%s' ),
        array( '%d' )
    );
    
    return $result !== false;
}

/**
 * Get suggestions for a post
 * 
 * @param int $post_id Post ID
 * @param array $args Query arguments
 * @return array Array of suggestions
 */
function wp_content_flow_get_post_suggestions( $post_id, $args = array() ) {
    global $wpdb;
    
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return array();
    }
    
    $defaults = array(
        'status' => null,
        'suggestion_type' => null,
        'limit' => 50,
        'offset' => 0,
        'orderby' => 'created_at',
        'order' => 'DESC'
    );
    
    $args = wp_parse_args( $args, $defaults );
    
    $table_name = wp_content_flow_get_suggestions_table_name();
    
    $where_clauses = array( 'post_id = %d' );
    $where_values = array( $post_id );
    
    if ( $args['status'] ) {
        $where_clauses[] = 'status = %s';
        $where_values[] = $args['status'];
    }
    
    if ( $args['suggestion_type'] ) {
        $where_clauses[] = 'suggestion_type = %s';
        $where_values[] = $args['suggestion_type'];
    }
    
    $where_clause = implode( ' AND ', $where_clauses );
    $order_clause = sprintf( 'ORDER BY %s %s', $args['orderby'], $args['order'] );
    $limit_clause = sprintf( 'LIMIT %d OFFSET %d', $args['limit'], $args['offset'] );
    
    $query = $wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE {$where_clause} {$order_clause} {$limit_clause}",
        $where_values
    );
    
    $suggestions = $wpdb->get_results( $query, ARRAY_A );
    
    // Decode JSON metadata for each suggestion
    foreach ( $suggestions as &$suggestion ) {
        if ( $suggestion['metadata'] ) {
            $suggestion['metadata'] = json_decode( $suggestion['metadata'], true );
        }
    }
    
    return $suggestions;
}