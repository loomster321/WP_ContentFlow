<?php
/**
 * Workflow Automation Engine
 *
 * Manages automated content creation and review processes, including
 * trigger systems, approval workflows, and content quality checks.
 *
 * @package WP_Content_Flow
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Workflow Automation Engine Class
 */
class WP_Content_Flow_Workflow_Automation_Engine {
    
    /**
     * Single instance of the class
     * @var WP_Content_Flow_Workflow_Automation_Engine
     */
    private static $instance;
    
    /**
     * Active workflow executions
     * @var array
     */
    private $active_workflows = [];
    
    /**
     * Workflow hooks registry
     * @var array
     */
    private $workflow_hooks = [];
    
    /**
     * Get single instance
     *
     * @return WP_Content_Flow_Workflow_Automation_Engine
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->register_workflow_triggers();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Content triggers
        add_action( 'wp_insert_post', array( $this, 'handle_post_created' ), 10, 3 );
        add_action( 'transition_post_status', array( $this, 'handle_post_status_change' ), 10, 3 );
        add_action( 'post_updated', array( $this, 'handle_post_updated' ), 10, 3 );
        
        // User workflow triggers
        add_action( 'wp_content_flow_workflow_step_completed', array( $this, 'handle_workflow_step_completed' ), 10, 3 );
        add_action( 'wp_content_flow_content_generated', array( $this, 'handle_content_generated' ), 10, 2 );
        
        // Scheduled workflows
        add_action( 'wp_content_flow_scheduled_workflow', array( $this, 'execute_scheduled_workflow' ), 10, 2 );
        
        // AJAX endpoints for workflow management
        add_action( 'wp_ajax_wp_content_flow_trigger_workflow', array( $this, 'ajax_trigger_workflow' ) );
        
        // Note: Admin workflow actions and approval/rejection AJAX methods
        // have been removed as they were incomplete and causing fatal errors.
        // These can be implemented in future versions if workflow automation
        // features are required.
    }
    
    /**
     * Register workflow triggers
     */
    private function register_workflow_triggers() {
        $this->workflow_hooks = [
            'post_created' => [
                'description' => 'Triggered when a new post is created',
                'parameters' => [ 'post_id', 'post', 'update' ]
            ],
            'post_published' => [
                'description' => 'Triggered when a post is published',
                'parameters' => [ 'post_id', 'post' ]
            ],
            'post_updated' => [
                'description' => 'Triggered when a post is updated',
                'parameters' => [ 'post_id', 'post_before', 'post_after' ]
            ],
            'content_generated' => [
                'description' => 'Triggered when AI content is generated',
                'parameters' => [ 'content', 'post_id' ]
            ],
            'workflow_step_completed' => [
                'description' => 'Triggered when a workflow step is completed',
                'parameters' => [ 'workflow_id', 'step_name', 'result' ]
            ],
            'scheduled_trigger' => [
                'description' => 'Triggered by scheduled events',
                'parameters' => [ 'schedule_data', 'template_id' ]
            ]
        ];
        
        // Allow plugins to register custom triggers
        $this->workflow_hooks = apply_filters( 'wp_content_flow_workflow_triggers', $this->workflow_hooks );
    }
    
    /**
     * Execute workflow from template
     *
     * @param int $template_id Workflow template ID
     * @param array $context Execution context
     * @param int $post_id Optional post ID
     * @return array Execution result
     */
    public function execute_workflow( $template_id, $context = [], $post_id = null ) {
        $template = new WP_Content_Flow_Workflow_Template( $template_id );
        
        if ( ! $template->get_id() ) {
            return [
                'success' => false,
                'error' => 'Workflow template not found'
            ];
        }
        
        // Check user permissions
        if ( ! $template->can_user_access() ) {
            return [
                'success' => false,
                'error' => 'Insufficient permissions to execute workflow'
            ];
        }
        
        $workflow_id = $this->create_workflow_execution( $template, $context, $post_id );
        
        if ( ! $workflow_id ) {
            return [
                'success' => false,
                'error' => 'Failed to create workflow execution'
            ];
        }
        
        $start_time = microtime( true );
        
        try {
            $result = $this->execute_workflow_steps( $workflow_id, $template );
            
            $execution_time = microtime( true ) - $start_time;
            
            // Update template usage statistics
            $template->update_usage_stats( $execution_time, $result['success'] );
            
            // Log workflow execution
            $this->log_workflow_execution( $workflow_id, $result, $execution_time );
            
            return $result;
            
        } catch ( Exception $e ) {
            $execution_time = microtime( true ) - $start_time;
            
            // Update template usage statistics (failure)
            $template->update_usage_stats( $execution_time, false );
            
            // Log error
            error_log( 'WP Content Flow: Workflow execution failed - ' . $e->getMessage() );
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'workflow_id' => $workflow_id
            ];
        }
    }
    
    /**
     * Create workflow execution record
     *
     * @param WP_Content_Flow_Workflow_Template $template Workflow template
     * @param array $context Execution context
     * @param int $post_id Optional post ID
     * @return int|false Workflow ID or false on failure
     */
    private function create_workflow_execution( $template, $context, $post_id = null ) {
        global $wpdb;
        
        $workflow_data = [
            'template_id' => $template->get_id(),
            'template_name' => $template->get_name(),
            'post_id' => $post_id,
            'user_id' => get_current_user_id(),
            'workflow_type' => $template->get_type(),
            'status' => 'pending',
            'context_data' => wp_json_encode( $context ),
            'ai_provider' => $template->get_ai_provider(),
            'ai_model' => $template->get_ai_model(),
            'ai_parameters' => wp_json_encode( $template->get_ai_parameters() ),
            'workflow_steps' => wp_json_encode( $template->get_workflow_steps() ),
            'current_step' => 0,
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' )
        ];
        
        $table_name = $wpdb->prefix . 'ai_workflows';
        
        $result = $wpdb->insert( $table_name, $workflow_data );
        
        if ( $result ) {
            $workflow_id = $wpdb->insert_id;
            
            // Fire action hook
            do_action( 'wp_content_flow_workflow_created', $workflow_id, $template, $context );
            
            return $workflow_id;
        }
        
        return false;
    }
    
    /**
     * Execute workflow steps
     *
     * @param int $workflow_id Workflow ID
     * @param WP_Content_Flow_Workflow_Template $template Workflow template
     * @return array Execution result
     */
    private function execute_workflow_steps( $workflow_id, $template ) {
        $workflow_steps = $template->get_workflow_steps();
        $step_results = [];
        $current_content = '';
        
        foreach ( $workflow_steps as $step_index => $step_config ) {
            $step_result = $this->execute_workflow_step( $workflow_id, $step_index, $step_config, $current_content );
            
            $step_results[] = $step_result;
            
            // Update workflow record with current step
            $this->update_workflow_status( $workflow_id, $step_index, $step_result );
            
            // If step failed and not marked as optional, stop execution
            if ( ! $step_result['success'] && empty( $step_config['optional'] ) ) {
                return [
                    'success' => false,
                    'error' => $step_result['error'],
                    'workflow_id' => $workflow_id,
                    'failed_step' => $step_index,
                    'step_results' => $step_results
                ];
            }
            
            // If step requires manual approval, pause workflow
            if ( ! empty( $step_config['manual_approval'] ) && ! $step_result['auto_approved'] ) {
                $this->pause_workflow_for_approval( $workflow_id, $step_index, $step_config );
                
                return [
                    'success' => true,
                    'pending_approval' => true,
                    'workflow_id' => $workflow_id,
                    'pending_step' => $step_index,
                    'step_results' => $step_results
                ];
            }
            
            // Update current content if step generated content
            if ( ! empty( $step_result['content'] ) ) {
                $current_content = $step_result['content'];
            }
            
            // Fire step completion hook
            do_action( 'wp_content_flow_workflow_step_completed', $workflow_id, $step_config['step'], $step_result );
        }
        
        // Mark workflow as completed
        $this->complete_workflow( $workflow_id, $step_results );
        
        return [
            'success' => true,
            'workflow_id' => $workflow_id,
            'step_results' => $step_results,
            'final_content' => $current_content
        ];
    }
    
    /**
     * Execute individual workflow step
     *
     * @param int $workflow_id Workflow ID
     * @param int $step_index Step index
     * @param array $step_config Step configuration
     * @param string $current_content Current content state
     * @return array Step execution result
     */
    private function execute_workflow_step( $workflow_id, $step_index, $step_config, $current_content = '' ) {
        $step_name = $step_config['step'];
        $step_role = $step_config['role'] ?? 'system';
        $is_auto = $step_config['auto'] ?? false;
        
        try {
            switch ( $step_name ) {
                case 'generate':
                    return $this->execute_content_generation_step( $workflow_id, $step_config );
                    
                case 'review':
                    return $this->execute_content_review_step( $workflow_id, $step_config, $current_content );
                    
                case 'approve':
                    return $this->execute_approval_step( $workflow_id, $step_config, $current_content );
                    
                case 'publish':
                    return $this->execute_publish_step( $workflow_id, $step_config, $current_content );
                    
                case 'ai_check':
                    return $this->execute_ai_quality_check( $workflow_id, $step_config, $current_content );
                    
                case 'grammar_check':
                    return $this->execute_grammar_check( $workflow_id, $step_config, $current_content );
                    
                case 'seo_optimize':
                    return $this->execute_seo_optimization( $workflow_id, $step_config, $current_content );
                    
                case 'notify':
                    return $this->execute_notification_step( $workflow_id, $step_config, $current_content );
                    
                default:
                    // Allow custom step handlers via filter
                    $custom_result = apply_filters( 
                        'wp_content_flow_execute_custom_step', 
                        null, 
                        $step_name, 
                        $workflow_id, 
                        $step_config, 
                        $current_content 
                    );
                    
                    if ( $custom_result !== null ) {
                        return $custom_result;
                    }
                    
                    return [
                        'success' => false,
                        'error' => "Unknown workflow step: {$step_name}"
                    ];
            }
            
        } catch ( Exception $e ) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'step' => $step_name
            ];
        }
    }
    
    /**
     * Execute content generation step
     *
     * @param int $workflow_id Workflow ID
     * @param array $step_config Step configuration
     * @return array Step result
     */
    private function execute_content_generation_step( $workflow_id, $step_config ) {
        // Get workflow context
        $workflow = $this->get_workflow_execution( $workflow_id );
        
        if ( ! $workflow ) {
            return [
                'success' => false,
                'error' => 'Workflow not found'
            ];
        }
        
        $context = json_decode( $workflow['context_data'], true ) ?: [];
        $ai_parameters = json_decode( $workflow['ai_parameters'], true ) ?: [];
        
        // Get AI service
        $ai_core = WP_Content_Flow_AI_Core::get_instance();
        
        // Generate content using AI
        $prompt = $step_config['prompt'] ?? $context['prompt'] ?? '';
        
        if ( empty( $prompt ) ) {
            return [
                'success' => false,
                'error' => 'No prompt provided for content generation'
            ];
        }
        
        $generation_result = $ai_core->generate_content([
            'prompt' => $prompt,
            'provider' => $workflow['ai_provider'],
            'model' => $workflow['ai_model'],
            'parameters' => $ai_parameters
        ]);
        
        if ( $generation_result['success'] ) {
            // Store generated content
            $this->store_generated_content( $workflow_id, $generation_result['content'] );
            
            return [
                'success' => true,
                'content' => $generation_result['content'],
                'provider' => $workflow['ai_provider'],
                'model' => $workflow['ai_model'],
                'auto_approved' => true
            ];
        } else {
            return [
                'success' => false,
                'error' => $generation_result['error']
            ];
        }
    }
    
    /**
     * Execute content review step
     *
     * @param int $workflow_id Workflow ID
     * @param array $step_config Step configuration
     * @param string $content Content to review
     * @return array Step result
     */
    private function execute_content_review_step( $workflow_id, $step_config, $content ) {
        // If auto review enabled, use AI for review
        if ( ! empty( $step_config['auto'] ) ) {
            return $this->execute_ai_content_review( $workflow_id, $step_config, $content );
        }
        
        // Manual review - create review assignment
        $review_assignment = $this->create_review_assignment( $workflow_id, $step_config, $content );
        
        if ( $review_assignment ) {
            return [
                'success' => true,
                'content' => $content,
                'auto_approved' => false,
                'review_assignment' => $review_assignment
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Failed to create review assignment'
            ];
        }
    }
    
    /**
     * Execute AI content review
     *
     * @param int $workflow_id Workflow ID
     * @param array $step_config Step configuration
     * @param string $content Content to review
     * @return array Step result
     */
    private function execute_ai_content_review( $workflow_id, $step_config, $content ) {
        $ai_core = WP_Content_Flow_AI_Core::get_instance();
        
        $review_prompt = "Please review the following content for quality, accuracy, grammar, and adherence to best practices. Provide specific feedback and suggestions for improvement:\n\n" . $content;
        
        $review_result = $ai_core->generate_content([
            'prompt' => $review_prompt,
            'provider' => 'anthropic', // Use Claude for review
            'model' => 'claude-3-sonnet',
            'parameters' => [
                'temperature' => 0.3,
                'max_tokens' => 1000
            ]
        ]);
        
        if ( $review_result['success'] ) {
            // Store review feedback
            $this->store_review_feedback( $workflow_id, $review_result['content'] );
            
            // Auto-approve if review is positive
            $auto_approved = $this->analyze_review_sentiment( $review_result['content'] );
            
            return [
                'success' => true,
                'content' => $content,
                'review_feedback' => $review_result['content'],
                'auto_approved' => $auto_approved
            ];
        } else {
            return [
                'success' => false,
                'error' => 'AI review failed: ' . $review_result['error']
            ];
        }
    }
    
    /**
     * Handle post creation trigger
     *
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     * @param bool $update Whether this is an update
     */
    public function handle_post_created( $post_id, $post, $update ) {
        if ( $update ) {
            return;
        }
        
        // Find templates with auto-trigger enabled for this post type
        $templates = WP_Content_Flow_Workflow_Template::get_templates([
            'template_status' => 'active',
            'limit' => 100
        ]);
        
        foreach ( $templates as $template ) {
            if ( ! $template->get( 'auto_trigger' ) ) {
                continue;
            }
            
            $content_types = explode( ',', $template->get( 'content_types' ) ?: '' );
            
            if ( ! in_array( $post->post_type, $content_types ) ) {
                continue;
            }
            
            // Check trigger conditions
            if ( $this->check_trigger_conditions( $template, $post ) ) {
                $this->execute_workflow( $template->get_id(), [
                    'trigger' => 'post_created',
                    'post_id' => $post_id
                ], $post_id );
            }
        }
    }
    
    /**
     * Handle post status change
     *
     * @param string $new_status New post status
     * @param string $old_status Old post status
     * @param WP_Post $post Post object
     */
    public function handle_post_status_change( $new_status, $old_status, $post ) {
        if ( $new_status === 'publish' && $old_status !== 'publish' ) {
            // Post was published
            do_action( 'wp_content_flow_post_published', $post->ID, $post );
        }
    }
    
    /**
     * Get workflow execution data
     *
     * @param int $workflow_id Workflow ID
     * @return array|null Workflow data
     */
    private function get_workflow_execution( $workflow_id ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_workflows';
        
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $workflow_id ),
            ARRAY_A
        );
    }
    
    /**
     * Update workflow status
     *
     * @param int $workflow_id Workflow ID
     * @param int $current_step Current step index
     * @param array $step_result Step result
     */
    private function update_workflow_status( $workflow_id, $current_step, $step_result ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_workflows';
        
        $status = $step_result['success'] ? 'running' : 'failed';
        
        if ( ! empty( $step_result['pending_approval'] ) ) {
            $status = 'pending_approval';
        }
        
        $wpdb->update(
            $table_name,
            [
                'current_step' => $current_step,
                'status' => $status,
                'updated_at' => current_time( 'mysql' )
            ],
            [ 'id' => $workflow_id ],
            [ '%d', '%s', '%s' ],
            [ '%d' ]
        );
    }
    
    /**
     * Complete workflow execution
     *
     * @param int $workflow_id Workflow ID
     * @param array $step_results All step results
     */
    private function complete_workflow( $workflow_id, $step_results ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_workflows';
        
        $success = true;
        foreach ( $step_results as $result ) {
            if ( ! $result['success'] ) {
                $success = false;
                break;
            }
        }
        
        $wpdb->update(
            $table_name,
            [
                'status' => $success ? 'completed' : 'failed',
                'completed_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' )
            ],
            [ 'id' => $workflow_id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );
        
        // Fire completion hook
        do_action( 'wp_content_flow_workflow_completed', $workflow_id, $success, $step_results );
    }
    
    /**
     * Check trigger conditions for template
     *
     * @param WP_Content_Flow_Workflow_Template $template Workflow template
     * @param WP_Post $post Post object
     * @return bool True if conditions are met
     */
    private function check_trigger_conditions( $template, $post ) {
        $conditions = $template->get( 'trigger_conditions' );
        
        if ( empty( $conditions ) ) {
            return true; // No conditions means always trigger
        }
        
        foreach ( $conditions as $condition ) {
            $field = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? 'equals';
            $value = $condition['value'] ?? '';
            
            $post_value = '';
            
            switch ( $field ) {
                case 'post_title':
                    $post_value = $post->post_title;
                    break;
                case 'post_content':
                    $post_value = $post->post_content;
                    break;
                case 'post_status':
                    $post_value = $post->post_status;
                    break;
                case 'post_author':
                    $post_value = $post->post_author;
                    break;
                default:
                    $post_value = get_post_meta( $post->ID, $field, true );
                    break;
            }
            
            $condition_met = $this->evaluate_condition( $post_value, $operator, $value );
            
            if ( ! $condition_met ) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Evaluate condition
     *
     * @param mixed $post_value Post field value
     * @param string $operator Comparison operator
     * @param mixed $condition_value Condition value
     * @return bool True if condition is met
     */
    private function evaluate_condition( $post_value, $operator, $condition_value ) {
        switch ( $operator ) {
            case 'equals':
                return $post_value == $condition_value;
                
            case 'not_equals':
                return $post_value != $condition_value;
                
            case 'contains':
                return strpos( $post_value, $condition_value ) !== false;
                
            case 'not_contains':
                return strpos( $post_value, $condition_value ) === false;
                
            case 'greater_than':
                return $post_value > $condition_value;
                
            case 'less_than':
                return $post_value < $condition_value;
                
            case 'empty':
                return empty( $post_value );
                
            case 'not_empty':
                return ! empty( $post_value );
                
            default:
                return false;
        }
    }
    
    /**
     * Execute approval workflow step
     *
     * @param int $workflow_id Workflow ID
     * @param array $step_config Step configuration
     * @param string $content Content to approve
     * @return array Execution result
     */
    private function execute_approval_step( $workflow_id, $step_config, $content ) {
        try {
            $required_role = $step_config['role'] ?? 'editor';
            $post_id = $step_config['post_id'] ?? 0;
            
            // Create approval assignment
            $assignment_id = $this->create_approval_assignment( $workflow_id, $required_role, $post_id, $content );
            
            if ( ! $assignment_id ) {
                throw new Exception( 'Failed to create approval assignment' );
            }
            
            // If auto-approval enabled and user has permission
            if ( ! empty( $step_config['auto_approve'] ) && current_user_can( 'publish_posts' ) ) {
                $this->process_approval( $assignment_id, true, 'Auto-approved' );
            }
            
            return [
                'success' => true,
                'data' => [
                    'assignment_id' => $assignment_id,
                    'status' => 'pending_approval',
                    'message' => 'Approval assignment created'
                ]
            ];
            
        } catch ( Exception $e ) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Execute publish workflow step
     *
     * @param int $workflow_id Workflow ID
     * @param array $step_config Step configuration
     * @param string $content Content to publish
     * @return array Execution result
     */
    private function execute_publish_step( $workflow_id, $step_config, $content ) {
        try {
            $post_id = $step_config['post_id'] ?? 0;
            
            if ( ! $post_id ) {
                throw new Exception( 'Post ID required for publish step' );
            }
            
            // Check user permissions
            if ( ! current_user_can( 'publish_posts' ) ) {
                throw new Exception( 'Insufficient permissions to publish' );
            }
            
            $post = get_post( $post_id );
            if ( ! $post ) {
                throw new Exception( 'Post not found' );
            }
            
            // Update post content if provided
            if ( ! empty( $content ) && $content !== $post->post_content ) {
                wp_update_post( [
                    'ID' => $post_id,
                    'post_content' => $content
                ] );
            }
            
            // Publish the post
            $result = wp_update_post( [
                'ID' => $post_id,
                'post_status' => 'publish'
            ] );
            
            if ( is_wp_error( $result ) ) {
                throw new Exception( 'Failed to publish post: ' . $result->get_error_message() );
            }
            
            // Fire publish hook
            do_action( 'wp_content_flow_post_published', $post_id, $workflow_id );
            
            return [
                'success' => true,
                'data' => [
                    'post_id' => $post_id,
                    'post_url' => get_permalink( $post_id ),
                    'message' => 'Post published successfully'
                ]
            ];
            
        } catch ( Exception $e ) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Execute notification workflow step
     *
     * @param int $workflow_id Workflow ID
     * @param array $step_config Step configuration
     * @param string $content Current content
     * @return array Execution result
     */
    private function execute_notification_step( $workflow_id, $step_config, $content ) {
        try {
            $notification_type = $step_config['notification_type'] ?? 'email';
            $recipients = $step_config['recipients'] ?? [];
            $message = $step_config['message'] ?? 'Workflow notification';
            
            switch ( $notification_type ) {
                case 'email':
                    $result = $this->send_email_notification( $workflow_id, $recipients, $message, $content );
                    break;
                    
                case 'admin_notice':
                    $result = $this->create_admin_notice( $workflow_id, $message );
                    break;
                    
                case 'webhook':
                    $result = $this->send_webhook_notification( $workflow_id, $step_config, $content );
                    break;
                    
                default:
                    $result = apply_filters( 'wp_content_flow_custom_notification', false, $notification_type, $step_config, $content );
                    break;
            }
            
            return [
                'success' => $result,
                'data' => [
                    'notification_type' => $notification_type,
                    'recipients_count' => count( $recipients ),
                    'message' => $result ? 'Notification sent successfully' : 'Failed to send notification'
                ]
            ];
            
        } catch ( Exception $e ) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create approval assignment
     *
     * @param int $workflow_id Workflow ID
     * @param string $required_role Required user role
     * @param int $post_id Post ID
     * @param string $content Content to approve
     * @return int|false Assignment ID or false on failure
     */
    private function create_approval_assignment( $workflow_id, $required_role, $post_id, $content ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'workflow_approval_assignments';
        
        $result = $wpdb->insert(
            $table_name,
            [
                'workflow_id' => $workflow_id,
                'post_id' => $post_id,
                'required_role' => $required_role,
                'content' => $content,
                'status' => 'pending',
                'created_at' => current_time( 'mysql' ),
                'created_by' => get_current_user_id()
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%s', '%d' ]
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Process approval
     *
     * @param int $assignment_id Assignment ID
     * @param bool $approved Whether approved
     * @param string $notes Approval notes
     * @return bool Success
     */
    private function process_approval( $assignment_id, $approved, $notes = '' ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'workflow_approval_assignments';
        
        $result = $wpdb->update(
            $table_name,
            [
                'status' => $approved ? 'approved' : 'rejected',
                'approved_by' => get_current_user_id(),
                'approved_at' => current_time( 'mysql' ),
                'notes' => $notes
            ],
            [ 'id' => $assignment_id ],
            [ '%s', '%d', '%s', '%s' ],
            [ '%d' ]
        );
        
        return $result !== false;
    }
    
    /**
     * Send email notification
     *
     * @param int $workflow_id Workflow ID
     * @param array $recipients Recipient email addresses
     * @param string $message Message content
     * @param string $content Workflow content
     * @return bool Success
     */
    private function send_email_notification( $workflow_id, $recipients, $message, $content ) {
        $subject = sprintf( 'Workflow Notification - ID %d', $workflow_id );
        $body = $message . "\n\n" . wp_trim_words( $content, 50 );
        
        $success = true;
        foreach ( $recipients as $email ) {
            if ( ! wp_mail( $email, $subject, $body ) ) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Create admin notice
     *
     * @param int $workflow_id Workflow ID
     * @param string $message Notice message
     * @return bool Success
     */
    private function create_admin_notice( $workflow_id, $message ) {
        $notice_key = 'wp_content_flow_workflow_' . $workflow_id;
        set_transient( $notice_key, $message, HOUR_IN_SECONDS );
        
        return true;
    }
    
    /**
     * Send webhook notification
     *
     * @param int $workflow_id Workflow ID
     * @param array $step_config Step configuration
     * @param string $content Workflow content
     * @return bool Success
     */
    private function send_webhook_notification( $workflow_id, $step_config, $content ) {
        $webhook_url = $step_config['webhook_url'] ?? '';
        
        if ( empty( $webhook_url ) ) {
            return false;
        }
        
        $payload = [
            'workflow_id' => $workflow_id,
            'timestamp' => current_time( 'timestamp' ),
            'site_url' => home_url(),
            'content_preview' => wp_trim_words( $content, 20 ),
            'user_id' => get_current_user_id()
        ];
        
        $response = wp_remote_post( $webhook_url, [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body' => wp_json_encode( $payload ),
            'timeout' => 15
        ] );
        
        return ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200;
    }
    
    /**
     * Log workflow execution
     *
     * @param int $workflow_id Workflow ID
     * @param array $result Execution result
     * @param float $execution_time Execution time in seconds
     */
    private function log_workflow_execution( $workflow_id, $result, $execution_time ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf(
                'WP Content Flow: Workflow %d executed in %.2fs - %s',
                $workflow_id,
                $execution_time,
                $result['success'] ? 'SUCCESS' : 'FAILED: ' . ( $result['error'] ?? 'Unknown error' )
            ) );
        }
    }
    
    /**
     * AJAX handler for triggering workflows
     */
    public function ajax_trigger_workflow() {
        check_ajax_referer( 'wp_content_flow_workflow', 'nonce' );
        
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( 'Insufficient permissions' );
        }
        
        $template_id = intval( $_POST['template_id'] ?? 0 );
        $post_id = intval( $_POST['post_id'] ?? 0 );
        $context = $_POST['context'] ?? [];
        
        if ( ! $template_id ) {
            wp_send_json_error( 'Template ID required' );
        }
        
        $result = $this->execute_workflow( $template_id, $context, $post_id );
        
        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }
    
    /**
     * Get available workflow triggers
     *
     * @return array Workflow triggers
     */
    public function get_workflow_triggers() {
        return $this->workflow_hooks;
    }
}