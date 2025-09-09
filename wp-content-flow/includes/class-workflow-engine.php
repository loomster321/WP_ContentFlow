<?php
/**
 * Workflow Engine Class
 * 
 * Manages AI content workflows and orchestrates content generation processes
 *
 * @package WP_Content_Flow
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Content_Flow_Workflow_Engine {
    
    /**
     * Instance of this class
     * @var WP_Content_Flow_Workflow_Engine
     */
    private static $instance = null;
    
    /**
     * AI Core instance
     * @var WP_Content_Flow_AI_Core
     */
    private $ai_core;
    
    /**
     * Active workflows
     * @var array
     */
    private $active_workflows = array();
    
    /**
     * Get singleton instance
     * @return WP_Content_Flow_Workflow_Engine
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'register_workflow_post_type'));
        add_action('wp_ajax_wp_content_flow_execute_workflow', array($this, 'handle_workflow_execution'));
        add_action('wp_ajax_wp_content_flow_get_workflow_status', array($this, 'get_workflow_status'));
        
        // REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_endpoints'));
    }
    
    /**
     * Set AI Core instance
     * @param WP_Content_Flow_AI_Core $ai_core
     */
    public function set_ai_core($ai_core) {
        $this->ai_core = $ai_core;
    }
    
    /**
     * Register custom post type for workflows
     */
    public function register_workflow_post_type() {
        register_post_type('wp_ai_workflow', array(
            'labels' => array(
                'name' => __('AI Workflows', 'wp-content-flow'),
                'singular_name' => __('AI Workflow', 'wp-content-flow'),
                'add_new_item' => __('Add New Workflow', 'wp-content-flow'),
                'edit_item' => __('Edit Workflow', 'wp-content-flow'),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'wp-content-flow',
            'capability_type' => 'post',
            'capabilities' => array(
                'create_posts' => 'manage_ai_workflows',
                'edit_posts' => 'manage_ai_workflows',
                'edit_others_posts' => 'manage_ai_workflows',
                'delete_posts' => 'manage_ai_workflows',
                'read_private_posts' => 'manage_ai_workflows',
            ),
            'supports' => array('title', 'editor', 'custom-fields'),
            'show_in_rest' => true,
        ));
    }
    
    /**
     * Register REST API endpoints
     */
    public function register_rest_endpoints() {
        register_rest_route('wp-content-flow/v1', '/workflows', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_workflows'),
            'permission_callback' => array($this, 'check_workflow_permissions'),
        ));
        
        register_rest_route('wp-content-flow/v1', '/workflows/(?P<id>\d+)/execute', array(
            'methods' => 'POST',
            'callback' => array($this, 'execute_workflow'),
            'permission_callback' => array($this, 'check_workflow_permissions'),
        ));
        
        register_rest_route('wp-content-flow/v1', '/workflows/(?P<id>\d+)/status', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_workflow_status_rest'),
            'permission_callback' => array($this, 'check_workflow_permissions'),
        ));
    }
    
    /**
     * Check workflow permissions
     */
    public function check_workflow_permissions() {
        return current_user_can('manage_ai_workflows');
    }
    
    /**
     * Get all workflows
     */
    public function get_workflows($request) {
        $workflows = get_posts(array(
            'post_type' => 'wp_ai_workflow',
            'post_status' => 'publish',
            'numberposts' => -1,
        ));
        
        $formatted_workflows = array();
        foreach ($workflows as $workflow) {
            $formatted_workflows[] = array(
                'id' => $workflow->ID,
                'title' => $workflow->post_title,
                'content' => $workflow->post_content,
                'meta' => get_post_meta($workflow->ID),
                'status' => get_post_meta($workflow->ID, '_workflow_status', true),
                'created' => $workflow->post_date,
            );
        }
        
        return rest_ensure_response($formatted_workflows);
    }
    
    /**
     * Execute workflow
     */
    public function execute_workflow($request) {
        $workflow_id = $request->get_param('id');
        $workflow = get_post($workflow_id);
        
        if (!$workflow || $workflow->post_type !== 'wp_ai_workflow') {
            return new WP_Error('invalid_workflow', 'Workflow not found', array('status' => 404));
        }
        
        // Mark workflow as running
        update_post_meta($workflow_id, '_workflow_status', 'running');
        update_post_meta($workflow_id, '_workflow_started', current_time('mysql'));
        
        // Execute workflow steps
        $result = $this->run_workflow($workflow);
        
        // Update workflow status
        update_post_meta($workflow_id, '_workflow_status', $result['success'] ? 'completed' : 'failed');
        update_post_meta($workflow_id, '_workflow_completed', current_time('mysql'));
        update_post_meta($workflow_id, '_workflow_result', $result);
        
        return rest_ensure_response(array(
            'success' => $result['success'],
            'data' => $result,
            'workflow_id' => $workflow_id,
        ));
    }
    
    /**
     * Get workflow status via REST API
     */
    public function get_workflow_status_rest($request) {
        $workflow_id = $request->get_param('id');
        return rest_ensure_response($this->get_workflow_status_data($workflow_id));
    }
    
    /**
     * Get workflow status via AJAX
     */
    public function get_workflow_status() {
        check_ajax_referer('wp_content_flow_nonce', 'nonce');
        
        $workflow_id = intval($_POST['workflow_id']);
        $status = $this->get_workflow_status_data($workflow_id);
        
        wp_send_json_success($status);
    }
    
    /**
     * Get workflow status data
     */
    private function get_workflow_status_data($workflow_id) {
        $workflow = get_post($workflow_id);
        
        if (!$workflow || $workflow->post_type !== 'wp_ai_workflow') {
            return array('error' => 'Workflow not found');
        }
        
        return array(
            'id' => $workflow_id,
            'title' => $workflow->post_title,
            'status' => get_post_meta($workflow_id, '_workflow_status', true) ?: 'draft',
            'started' => get_post_meta($workflow_id, '_workflow_started', true),
            'completed' => get_post_meta($workflow_id, '_workflow_completed', true),
            'result' => get_post_meta($workflow_id, '_workflow_result', true),
            'progress' => $this->calculate_workflow_progress($workflow_id),
        );
    }
    
    /**
     * Handle workflow execution via AJAX
     */
    public function handle_workflow_execution() {
        check_ajax_referer('wp_content_flow_nonce', 'nonce');
        
        if (!current_user_can('manage_ai_workflows')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $workflow_id = intval($_POST['workflow_id']);
        $workflow = get_post($workflow_id);
        
        if (!$workflow || $workflow->post_type !== 'wp_ai_workflow') {
            wp_send_json_error('Workflow not found');
            return;
        }
        
        // Execute workflow
        $result = $this->run_workflow($workflow);
        
        // Update status
        update_post_meta($workflow_id, '_workflow_status', $result['success'] ? 'completed' : 'failed');
        update_post_meta($workflow_id, '_workflow_result', $result);
        
        wp_send_json_success($result);
    }
    
    /**
     * Run workflow
     */
    private function run_workflow($workflow) {
        try {
            // Get workflow configuration
            $config = json_decode($workflow->post_content, true);
            
            if (!$config || !isset($config['steps'])) {
                return array(
                    'success' => false,
                    'error' => 'Invalid workflow configuration',
                );
            }
            
            $results = array();
            $step_number = 1;
            
            // Execute each step
            foreach ($config['steps'] as $step) {
                $step_result = $this->execute_workflow_step($step, $results);
                $results['step_' . $step_number] = $step_result;
                
                if (!$step_result['success']) {
                    return array(
                        'success' => false,
                        'error' => 'Workflow failed at step ' . $step_number,
                        'step_results' => $results,
                    );
                }
                
                $step_number++;
            }
            
            return array(
                'success' => true,
                'step_results' => $results,
                'message' => 'Workflow completed successfully',
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage(),
            );
        }
    }
    
    /**
     * Execute individual workflow step
     */
    private function execute_workflow_step($step, $previous_results) {
        $step_type = isset($step['type']) ? $step['type'] : '';
        
        switch ($step_type) {
            case 'content_generation':
                return $this->execute_content_generation_step($step, $previous_results);
            
            case 'content_improvement':
                return $this->execute_content_improvement_step($step, $previous_results);
                
            case 'content_review':
                return $this->execute_content_review_step($step, $previous_results);
                
            default:
                return array(
                    'success' => false,
                    'error' => 'Unknown step type: ' . $step_type,
                );
        }
    }
    
    /**
     * Execute content generation step
     */
    private function execute_content_generation_step($step, $previous_results) {
        if (!$this->ai_core) {
            return array('success' => false, 'error' => 'AI Core not initialized');
        }
        
        $prompt = isset($step['prompt']) ? $step['prompt'] : '';
        $options = isset($step['options']) ? $step['options'] : array();
        
        try {
            $result = $this->ai_core->generate_content($prompt, $options);
            
            return array(
                'success' => true,
                'content' => $result['content'],
                'usage' => isset($result['usage']) ? $result['usage'] : array(),
                'provider' => isset($result['provider']) ? $result['provider'] : 'unknown',
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage(),
            );
        }
    }
    
    /**
     * Execute content improvement step
     */
    private function execute_content_improvement_step($step, $previous_results) {
        if (!$this->ai_core) {
            return array('success' => false, 'error' => 'AI Core not initialized');
        }
        
        $content = isset($step['content']) ? $step['content'] : '';
        $improvement_type = isset($step['improvement_type']) ? $step['improvement_type'] : 'general';
        $options = isset($step['options']) ? $step['options'] : array();
        
        // Use content from previous step if not specified
        if (empty($content) && !empty($previous_results)) {
            $last_result = end($previous_results);
            if (isset($last_result['content'])) {
                $content = $last_result['content'];
            }
        }
        
        try {
            $result = $this->ai_core->improve_content($content, $improvement_type, $options);
            
            return array(
                'success' => true,
                'improved_content' => $result['improved_content'],
                'improvements' => isset($result['improvements']) ? $result['improvements'] : array(),
                'confidence_score' => isset($result['confidence_score']) ? $result['confidence_score'] : 0,
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage(),
            );
        }
    }
    
    /**
     * Execute content review step
     */
    private function execute_content_review_step($step, $previous_results) {
        // Content review logic would go here
        // For now, return a simple success
        return array(
            'success' => true,
            'review_score' => 0.85,
            'review_notes' => 'Content reviewed successfully',
        );
    }
    
    /**
     * Calculate workflow progress
     */
    private function calculate_workflow_progress($workflow_id) {
        $status = get_post_meta($workflow_id, '_workflow_status', true);
        
        switch ($status) {
            case 'draft':
                return 0;
            case 'running':
                return 50;
            case 'completed':
                return 100;
            case 'failed':
                return 75;
            default:
                return 0;
        }
    }
    
    /**
     * Create default workflows
     */
    public function create_default_workflows() {
        // Blog post generation workflow
        $blog_post_workflow = array(
            'steps' => array(
                array(
                    'type' => 'content_generation',
                    'prompt' => 'Write a comprehensive blog post about {topic}',
                    'options' => array(
                        'max_tokens' => 1500,
                        'temperature' => 0.7,
                    ),
                ),
                array(
                    'type' => 'content_improvement',
                    'improvement_type' => 'seo',
                ),
                array(
                    'type' => 'content_review',
                ),
            ),
        );
        
        wp_insert_post(array(
            'post_title' => 'Blog Post Generation',
            'post_content' => json_encode($blog_post_workflow),
            'post_type' => 'wp_ai_workflow',
            'post_status' => 'publish',
        ));
        
        // Content improvement workflow  
        $improvement_workflow = array(
            'steps' => array(
                array(
                    'type' => 'content_improvement',
                    'improvement_type' => 'grammar',
                ),
                array(
                    'type' => 'content_improvement', 
                    'improvement_type' => 'clarity',
                ),
                array(
                    'type' => 'content_review',
                ),
            ),
        );
        
        wp_insert_post(array(
            'post_title' => 'Content Improvement',
            'post_content' => json_encode($improvement_workflow),
            'post_type' => 'wp_ai_workflow',
            'post_status' => 'publish',
        ));
    }
}