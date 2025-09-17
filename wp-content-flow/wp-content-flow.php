<?php
/**
 * Plugin Name: WordPress AI Content Flow
 * Plugin URI: https://github.com/your-org/wp-content-flow
 * Description: AI-powered content workflow plugin that integrates multiple AI providers directly into the Gutenberg block editor for content generation, editing suggestions, and workflow automation.
 * Version: 1.0.0
 * Author: WP Content Flow Team
 * Author URI: https://wpcontentflow.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-content-flow
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.4
 * Requires PHP: 8.1
 * Network: true
 *
 * @package WP_Content_Flow
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'WP_CONTENT_FLOW_VERSION', '1.0.0' );
define( 'WP_CONTENT_FLOW_PLUGIN_FILE', __FILE__ );
define( 'WP_CONTENT_FLOW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_CONTENT_FLOW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_CONTENT_FLOW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader for plugin classes
spl_autoload_register( function ( $class_name ) {
    if ( strpos( $class_name, 'WP_Content_Flow' ) === 0 ) {
        $class_file = str_replace( '_', '-', strtolower( $class_name ) );
        $class_file = str_replace( 'wp-content-flow-', '', $class_file );
        
        // Try different directory structures
        $possible_paths = [
            WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/class-' . $class_file . '.php',
            WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/admin/class-' . $class_file . '.php',
            WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/api/class-' . $class_file . '.php',
            WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/models/class-' . $class_file . '.php',
            WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/providers/class-' . $class_file . '.php',
        ];
        
        foreach ( $possible_paths as $file_path ) {
            if ( file_exists( $file_path ) ) {
                require_once $file_path;
                break;
            }
        }
    }
} );

/**
 * Main plugin class
 */
class WP_Content_Flow {
    
    /**
     * Single instance of the class
     *
     * @var WP_Content_Flow
     */
    private static $instance;
    
    /**
     * Get single instance of the class
     *
     * @return WP_Content_Flow
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
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
        register_uninstall_hook( __FILE__, array( 'WP_Content_Flow', 'uninstall' ) );
        
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'init', array( $this, 'register_blocks' ), 20 );
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Initialize core components
        if ( $this->check_requirements() ) {
            $this->load_includes();
            $this->init_components();
        }
    }
    
    /**
     * Check plugin requirements
     *
     * @return bool
     */
    private function check_requirements() {
        // Check WordPress version
        if ( version_compare( get_bloginfo( 'version' ), '6.0', '<' ) ) {
            add_action( 'admin_notices', array( $this, 'wordpress_version_notice' ) );
            return false;
        }
        
        // Check PHP version
        if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
            add_action( 'admin_notices', array( $this, 'php_version_notice' ) );
            return false;
        }
        
        return true;
    }
    
    /**
     * Load plugin includes
     */
    private function load_includes() {
        // Core classes
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/class-ai-core.php';
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/class-workflow-engine.php';
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/class-workflow-automation-engine.php';
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/class-collaboration-manager.php';
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/class-audit-trail.php';
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/class-user-capabilities.php';
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/class-content-manager.php';
        
        // Provider classes
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/providers/class-openai-provider.php';
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/providers/class-anthropic-provider.php';
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/providers/class-google-ai-provider.php';
        
        // Admin classes
        if ( is_admin() ) {
            require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/admin/class-admin-menu.php';
            require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/admin/class-settings-page.php';
        }
        
        // API classes
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/api/class-rest-api.php';
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Core components temporarily disabled for debugging
        try {
            // Initialize AI Core service
            require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/class-ai-core.php';
            if ( class_exists( 'WP_Content_Flow_AI_Core' ) ) {
                WP_Content_Flow_AI_Core::init();
            }
            
            // Initialize core components with error handling
            if ( class_exists( 'WP_Content_Flow_Workflow_Engine' ) ) {
                WP_Content_Flow_Workflow_Engine::get_instance();
            }
            if ( class_exists( 'WP_Content_Flow_Workflow_Automation_Engine' ) ) {
                WP_Content_Flow_Workflow_Automation_Engine::get_instance();
            }
            if ( class_exists( 'WP_Content_Flow_Collaboration_Manager' ) ) {
                WP_Content_Flow_Collaboration_Manager::get_instance();
            }
            if ( class_exists( 'WP_Content_Flow_Audit_Trail' ) ) {
                WP_Content_Flow_Audit_Trail::get_instance();
            }
            if ( class_exists( 'WP_Content_Flow_User_Capabilities' ) ) {
                new WP_Content_Flow_User_Capabilities();
            }
            if ( class_exists( 'WP_Content_Flow_Content_Manager' ) ) {
                WP_Content_Flow_Content_Manager::get_instance();
            }
        } catch ( Exception $e ) {
            error_log( 'WP Content Flow initialization error: ' . $e->getMessage() );
        }
        
        // AI providers temporarily disabled for debugging
        try {
            if ( class_exists( 'WP_Content_Flow_OpenAI_Provider' ) ) {
                new WP_Content_Flow_OpenAI_Provider();
            }
            if ( class_exists( 'WP_Content_Flow_Anthropic_Provider' ) ) {
                new WP_Content_Flow_Anthropic_Provider();
            }
            if ( class_exists( 'WP_Content_Flow_Google_AI_Provider' ) ) {
                new WP_Content_Flow_Google_AI_Provider();
            }
        } catch ( Exception $e ) {
            error_log( 'WP Content Flow provider initialization error: ' . $e->getMessage() );
        }
        
        // Initialize REST API
        if ( class_exists( 'WP_Content_Flow_REST_API' ) ) {
            new WP_Content_Flow_REST_API();
        }
        
        // Initialize admin components - temporarily disabled for debugging
        if ( is_admin() && class_exists( 'WP_Content_Flow_Admin_Menu' ) ) {
            try {
                WP_Content_Flow_Admin_Menu::get_instance();
            } catch ( Exception $e ) {
                error_log( 'WP Content Flow admin initialization error: ' . $e->getMessage() );
            }
        }
        
        // Initialize block editor components
        
        // Schedule cleanup tasks
        if ( ! wp_next_scheduled( 'wp_content_flow_cleanup' ) ) {
            wp_schedule_event( time(), 'hourly', 'wp_content_flow_cleanup' );
        }
    }
    
    /**
     * Register Gutenberg blocks
     */
    public function register_blocks() {
        // Register AI Text Generator block with matching attributes
        register_block_type( 'wp-content-flow/ai-text', array(
            'editor_script' => 'wp-content-flow-blocks',
            'editor_style'  => 'wp-content-flow-editor',
            'style'         => 'wp-content-flow-frontend',
            'attributes'    => array(
                'content' => array(
                    'type'     => 'string',
                    'source'   => 'html',
                    'selector' => '.wp-content-flow-ai-generated-content',
                    'default'  => '',
                ),
                'workflowId' => array(
                    'type'    => 'number',
                    'default' => 0,
                ),
                'prompt' => array(
                    'type'    => 'string',
                    'default' => '',
                ),
                'lastGenerated' => array(
                    'type'    => 'string',
                    'default' => '',
                ),
                'confidence' => array(
                    'type'    => 'number',
                    'default' => 0,
                ),
                // Note: isGenerating is excluded from server-side as it's UI state only
            ),
            'render_callback' => array( $this, 'render_ai_text_block' ),
        ) );
    }
    
    /**
     * Render AI Text Generator block on frontend
     *
     * @param array $attributes Block attributes
     * @param string $content Block inner content
     * @return string Block HTML
     */
    public function render_ai_text_block( $attributes, $content ) {
        // Get content from attributes
        $block_content = $attributes['content'] ?? '';
        
        // If no content in attributes, fallback to inner content
        if ( empty( $block_content ) && ! empty( $content ) ) {
            $block_content = $content;
        }
        
        if ( empty( $block_content ) ) {
            return '';
        }
        
        // Return the content wrapped in a div with proper classes
        return sprintf(
            '<div class="wp-block-wp-content-flow-ai-text"><div class="wp-content-flow-ai-generated-content">%s</div></div>',
            wp_kses_post( $block_content )
        );
    }
    
    /**
     * Enqueue block editor assets
     */
    public function enqueue_block_editor_assets() {
        // Temporarily disabled all JavaScript enqueuing for debugging
        // This will help identify if the custom JavaScript is breaking the block editor
        
        // Load main blocks dependencies - this includes all blocks and sidebar panels
        $main_blocks_asset = include( WP_CONTENT_FLOW_PLUGIN_DIR . 'build/blocks.asset.php' );
        
        // Enqueue the main blocks script (includes AI text generator block and sidebar panels)
        wp_enqueue_script(
            'wp-content-flow-blocks',
            WP_CONTENT_FLOW_PLUGIN_URL . 'build/blocks.js',
            $main_blocks_asset['dependencies'],
            $main_blocks_asset['version'],
            true
        );
        
        wp_enqueue_style(
            'wp-content-flow-editor',
            WP_CONTENT_FLOW_PLUGIN_URL . 'assets/css/editor.css',
            array(),
            WP_CONTENT_FLOW_VERSION
        );
        
        // Localize script with API data for both scripts
        $localize_data = array(
            'apiUrl' => rest_url( 'wp-content-flow/v1/' ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'version' => WP_CONTENT_FLOW_VERSION,
            'defaultWorkflows' => $this->get_default_workflows_for_js(),
        );
        
        wp_localize_script( 'wp-content-flow-blocks', 'wpContentFlow', $localize_data );
        wp_localize_script( 'wp-content-flow-ai-text-block', 'wpContentFlow', $localize_data );
        
        // Register workflow data store
        wp_add_inline_script( 'wp-content-flow-blocks', $this->get_workflow_store_script(), 'before' );
    }
    
    /**
     * Get default workflows for JavaScript
     */
    private function get_default_workflows_for_js() {
        return [
            [
                'id' => 1,
                'name' => 'Blog Post Workflow',
                'description' => 'Generate comprehensive blog posts',
                'ai_provider' => 'openai',
                'status' => 'active'
            ],
            [
                'id' => 2,
                'name' => 'Product Description',
                'description' => 'Create product descriptions',
                'ai_provider' => 'openai',
                'status' => 'active'
            ],
            [
                'id' => 3,
                'name' => 'Social Media Content',
                'description' => 'Generate social media posts',
                'ai_provider' => 'openai',
                'status' => 'active'
            ]
        ];
    }
    
    /**
     * Get workflow data store registration script
     */
    private function get_workflow_store_script() {
        return "
        (function() {
            if (typeof wp !== 'undefined' && wp.data) {
                // Register the workflows data store
                const { createReduxStore, register } = wp.data;
                
                const DEFAULT_STATE = {
                    workflows: " . json_encode($this->get_default_workflows_for_js()) . ",
                    isLoading: false,
                    error: null
                };
                
                const actions = {
                    setWorkflows(workflows) {
                        return { type: 'SET_WORKFLOWS', workflows };
                    },
                    setLoading(isLoading) {
                        return { type: 'SET_LOADING', isLoading };
                    },
                    setError(error) {
                        return { type: 'SET_ERROR', error };
                    }
                };
                
                const selectors = {
                    getWorkflows(state) {
                        return state.workflows;
                    },
                    getActiveWorkflows(state) {
                        return state.workflows.filter(w => w.status === 'active');
                    },
                    isLoading(state) {
                        return state.isLoading;
                    }
                };
                
                function reducer(state = DEFAULT_STATE, action) {
                    switch (action.type) {
                        case 'SET_WORKFLOWS':
                            return { ...state, workflows: action.workflows };
                        case 'SET_LOADING':
                            return { ...state, isLoading: action.isLoading };
                        case 'SET_ERROR':
                            return { ...state, error: action.error };
                        default:
                            return state;
                    }
                }
                
                const store = createReduxStore('wp-content-flow/workflows', {
                    reducer,
                    actions,
                    selectors
                });
                
                register(store);
            }
        })();
        ";
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'wp-content-flow-frontend',
            WP_CONTENT_FLOW_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            WP_CONTENT_FLOW_VERSION
        );
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'wp-content-flow',
            false,
            dirname( WP_CONTENT_FLOW_PLUGIN_BASENAME ) . '/languages/'
        );
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->create_database_tables();
        
        // Seed default workflows
        $this->seed_default_data();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set activation flag
        update_option( 'wp_content_flow_activated', true );
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Clear any scheduled events
        wp_clear_scheduled_hook( 'wp_content_flow_cleanup' );
    }
    
    /**
     * Plugin uninstall
     */
    public static function uninstall() {
        // Remove database tables
        self::drop_database_tables();
        
        // Remove options
        self::remove_options();
        
        // Clear any cached data
        wp_cache_flush();
    }
    
    /**
     * Create database tables
     */
    private function create_database_tables() {
        // Load database schema files
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/schema-workflows.php';
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/schema-suggestions.php';
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/schema-history.php';
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/schema-templates.php';
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/schema-approval-assignments.php';
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/schema-shared-queues.php';
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/schema-audit-trail.php';
        
        // Create tables in dependency order
        $table_creation_results = array();
        
        $table_creation_results['workflows'] = wp_content_flow_create_workflows_table();
        $table_creation_results['suggestions'] = wp_content_flow_create_suggestions_table();
        $table_creation_results['history'] = wp_content_flow_create_history_table();
        $table_creation_results['templates'] = wp_content_flow_create_workflow_templates_table();
        $table_creation_results['approval_assignments'] = wp_content_flow_create_approval_assignments_table();
        $table_creation_results['shared_queues'] = wp_content_flow_create_shared_queues_table();
        $table_creation_results['audit_trail'] = wp_content_flow_create_audit_trail_table();
        
        // Log results if debug enabled
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'WP Content Flow: Database table creation results - ' . print_r( $table_creation_results, true ) );
        }
        
        // Check if all tables were created successfully
        $all_created = array_reduce( $table_creation_results, function( $carry, $result ) {
            return $carry && $result;
        }, true );
        
        if ( ! $all_created ) {
            // Log error and potentially show admin notice
            error_log( 'WP Content Flow: One or more database tables failed to create' );
            
            add_action( 'admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo esc_html__( 'WordPress AI Content Flow: Database tables could not be created. Please check your database permissions.', 'wp-content-flow' );
                echo '</p></div>';
            } );
        }
        
        // Update database version
        update_option( 'wp_content_flow_db_version', WP_CONTENT_FLOW_VERSION );
        
        // Fire action for extensibility
        do_action( 'wp_content_flow_database_created', $table_creation_results );
    }
    
    /**
     * Seed default data
     */
    private function seed_default_data() {
        // Load seed file
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/seed-workflows.php';
        
        // Seed default workflows
        $result = wp_content_flow_seed_default_workflows();
        
        if ( ! $result ) {
            error_log( 'WP Content Flow: Failed to seed default workflows during activation' );
        }
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $default_options = array(
            'ai_provider' => 'openai',
            'cache_enabled' => true,
            'cache_duration' => 1800, // 30 minutes
            'rate_limit_enabled' => true,
            'requests_per_minute' => 10,
            'requests_per_hour' => 100,
            'daily_token_limit' => 50000,
        );
        
        update_option( 'wp_content_flow_settings', $default_options );
    }
    
    /**
     * Drop database tables
     */
    private static function drop_database_tables() {
        // Load database schema files for table dropping functions
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/schema-workflows.php';
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/schema-suggestions.php';
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/schema-history.php';
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/schema-templates.php';
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/schema-approval-assignments.php';
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/schema-shared-queues.php';
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/schema-audit-trail.php';
        
        // Drop tables in reverse dependency order (foreign keys)
        wp_content_flow_drop_audit_trail_table();
        wp_content_flow_drop_shared_queues_table();
        wp_content_flow_drop_approval_assignments_table();
        wp_content_flow_drop_workflow_templates_table();
        wp_content_flow_drop_history_table();
        wp_content_flow_drop_suggestions_table();
        wp_content_flow_drop_workflows_table();
        
        // Fire action for extensibility
        do_action( 'wp_content_flow_database_dropped' );
    }
    
    /**
     * Remove plugin options
     */
    private static function remove_options() {
        delete_option( 'wp_content_flow_settings' );
        delete_option( 'wp_content_flow_db_version' );
        delete_option( 'wp_content_flow_activated' );
    }
    
    /**
     * WordPress version notice
     */
    public function wordpress_version_notice() {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__( 'WordPress AI Content Flow requires WordPress version 6.0 or higher.', 'wp-content-flow' );
        echo '</p></div>';
    }
    
    /**
     * PHP version notice
     */
    public function php_version_notice() {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__( 'WordPress AI Content Flow requires PHP version 8.1 or higher.', 'wp-content-flow' );
        echo '</p></div>';
    }
}

// Initialize the plugin
WP_Content_Flow::get_instance();