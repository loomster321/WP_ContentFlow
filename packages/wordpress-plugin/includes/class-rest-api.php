<?php
/**
 * REST API endpoints for WP Content Flow
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPContentFlow_REST_API {
    
    private $namespace = 'wp-content-flow/v1';
    
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // AI generation endpoint
        register_rest_route($this->namespace, '/ai/generate', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'generate_content'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'prompt' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field'
                ),
                'workflow_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ),
                'post_id' => array(
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ),
                'selected_content' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'wp_kses_post'
                )
            )
        ));
        
        // AI improvement endpoint
        register_rest_route($this->namespace, '/ai/improve', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'improve_content'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'content' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'wp_kses_post'
                ),
                'improvement_type' => array(
                    'required' => true,
                    'type' => 'string',
                    'enum' => array('grammar', 'style', 'clarity', 'engagement', 'seo')
                ),
                'workflow_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                )
            )
        ));
        
        // Workflows endpoints
        register_rest_route($this->namespace, '/workflows', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_workflows'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        register_rest_route($this->namespace, '/workflows', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'create_workflow'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Suggestions endpoints
        register_rest_route($this->namespace, '/suggestions/(?P<id>\\d+)/accept', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'accept_suggestion'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                )
            )
        ));
        
        register_rest_route($this->namespace, '/suggestions/(?P<id>\\d+)/reject', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'reject_suggestion'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                )
            )
        ));
    }
    
    /**
     * Check user permissions for API endpoints
     */
    public function check_permissions($request) {
        $required_caps = apply_filters('wp_content_flow_required_capabilities', array(
            'generate_content' => 'edit_posts',
            'manage_workflows' => 'edit_posts',
            'access_ai_history' => 'edit_posts',
            'manage_ai_settings' => 'manage_options'
        ));
        
        // Default capability for most endpoints
        return current_user_can($required_caps['generate_content']);
    }
    
    /**
     * Generate AI content
     */
    public function generate_content($request) {
        $prompt = $request->get_param('prompt');
        $workflow_id = $request->get_param('workflow_id');
        $post_id = $request->get_param('post_id');
        $selected_content = $request->get_param('selected_content');
        
        try {
            // Build request for cloud API
            $api_request = array(
                'prompt' => $prompt,
                'workflowId' => $workflow_id,
                'context' => array(
                    'postId' => $post_id,
                    'selectedContent' => $selected_content
                )
            );
            
            // Call cloud API
            $response = $this->call_cloud_api('/api/ai/generate', $api_request);
            
            if (is_wp_error($response)) {
                return new WP_Error(
                    'ai_generation_failed',
                    'Failed to generate content: ' . $response->get_error_message(),
                    array('status' => 500)
                );
            }
            
            // Store suggestion in database
            $suggestion_id = $this->store_suggestion(array(
                'post_id' => $post_id ?: 0,
                'workflow_id' => $workflow_id,
                'original_content' => $selected_content ?: '',
                'suggested_content' => $response['content'],
                'suggestion_type' => 'generation',
                'confidence_score' => $response['confidenceScore'],
                'user_id' => get_current_user_id()
            ));
            
            // Fire action hook
            do_action('wp_content_flow_content_generated', array(
                'id' => $suggestion_id,
                'post_id' => $post_id,
                'workflow_id' => $workflow_id,
                'suggested_content' => $response['content'],
                'confidence_score' => $response['confidenceScore']
            ), $api_request);
            
            return rest_ensure_response(array(
                'success' => true,
                'data' => array(
                    'suggestion_id' => $suggestion_id,
                    'content' => $response['content'],
                    'confidence_score' => $response['confidenceScore'],
                    'processing_time' => $response['processingTime'] ?? null,
                    'token_usage' => $response['tokenUsage'] ?? null
                )
            ));
            
        } catch (Exception $e) {
            return new WP_Error(
                'ai_generation_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }
    
    /**
     * Improve existing content
     */
    public function improve_content($request) {
        $content = $request->get_param('content');
        $improvement_type = $request->get_param('improvement_type');
        $workflow_id = $request->get_param('workflow_id');
        
        try {
            $prompt = $this->build_improvement_prompt($improvement_type, $content);
            
            $api_request = array(
                'prompt' => $prompt,
                'workflowId' => $workflow_id,
                'context' => array(
                    'selectedContent' => $content
                )
            );
            
            $response = $this->call_cloud_api('/api/ai/generate', $api_request);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $suggestion_id = $this->store_suggestion(array(
                'post_id' => 0,
                'workflow_id' => $workflow_id,
                'original_content' => $content,
                'suggested_content' => $response['content'],
                'suggestion_type' => 'improvement',
                'confidence_score' => $response['confidenceScore'],
                'user_id' => get_current_user_id()
            ));
            
            return rest_ensure_response(array(
                'success' => true,
                'data' => array(
                    'suggestion_id' => $suggestion_id,
                    'improved_content' => $response['content'],
                    'confidence_score' => $response['confidenceScore'],
                    'improvement_type' => $improvement_type
                )
            ));
            
        } catch (Exception $e) {
            return new WP_Error('content_improvement_error', $e->getMessage());
        }
    }
    
    /**
     * Get user workflows
     */
    public function get_workflows($request) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_workflows';
        $user_id = get_current_user_id();
        
        $workflows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND is_active = 1 ORDER BY name",
            $user_id
        ));
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $workflows
        ));
    }
    
    /**
     * Create new workflow
     */
    public function create_workflow($request) {
        // TODO: Implement workflow creation
        return rest_ensure_response(array(
            'success' => false,
            'message' => 'Workflow creation not yet implemented'
        ));
    }
    
    /**
     * Accept AI suggestion
     */
    public function accept_suggestion($request) {
        $suggestion_id = $request->get_param('id');
        $user_id = get_current_user_id();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_suggestions';
        
        // Update suggestion status
        $updated = $wpdb->update(
            $table_name,
            array('status' => 'accepted'),
            array('id' => $suggestion_id),
            array('%s'),
            array('%d')
        );
        
        if ($updated === false) {
            return new WP_Error('update_failed', 'Failed to accept suggestion');
        }
        
        // Fire action hook
        do_action('wp_content_flow_suggestion_accepted', $suggestion_id, $user_id, 0);
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Suggestion accepted successfully'
        ));
    }
    
    /**
     * Reject AI suggestion
     */
    public function reject_suggestion($request) {
        $suggestion_id = $request->get_param('id');
        $user_id = get_current_user_id();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_suggestions';
        
        $updated = $wpdb->update(
            $table_name,
            array('status' => 'rejected'),
            array('id' => $suggestion_id),
            array('%s'),
            array('%d')
        );
        
        if ($updated === false) {
            return new WP_Error('update_failed', 'Failed to reject suggestion');
        }
        
        // Fire action hook
        do_action('wp_content_flow_suggestion_rejected', $suggestion_id, $user_id, 0);
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Suggestion rejected successfully'
        ));
    }
    
    /**
     * Call cloud API
     */
    private function call_cloud_api($endpoint, $data) {
        $api_url = get_option('wp_content_flow_api_url', 'http://localhost:3001');
        $url = trailingslashit($api_url) . ltrim($endpoint, '/');
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . get_option('wp_content_flow_api_token', '')
            ),
            'body' => wp_json_encode($data),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        
        if (!$decoded || !$decoded['success']) {
            return new WP_Error(
                'api_error',
                $decoded['error']['message'] ?? 'Unknown API error'
            );
        }
        
        return $decoded['data'];
    }
    
    /**
     * Store suggestion in database
     */
    private function store_suggestion($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_suggestions';
        
        $wpdb->insert($table_name, $data);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Build improvement prompt based on type
     */
    private function build_improvement_prompt($type, $content) {
        $prompts = array(
            'grammar' => "Please improve the grammar and correct any errors in the following content:\n\n",
            'style' => "Please improve the writing style and make this content more engaging:\n\n",
            'clarity' => "Please improve the clarity and make this content easier to understand:\n\n",
            'engagement' => "Please make this content more engaging and compelling for readers:\n\n",
            'seo' => "Please optimize this content for search engines while maintaining readability:\n\n"
        );
        
        return ($prompts[$type] ?? $prompts['style']) . $content;
    }
}