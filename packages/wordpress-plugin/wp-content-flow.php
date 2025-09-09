<?php
/**
 * Plugin Name: WP Content Flow
 * Plugin URI: https://github.com/your-org/wp-content-flow
 * Description: AI-powered content workflow system that integrates multi-agent AI capabilities directly into the WordPress Gutenberg editor.
 * Version: 1.0.0
 * Author: WP Content Flow Team
 * Author URI: https://wp-content-flow.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-content-flow
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.4
 * Requires PHP: 8.1
 * Network: false
 *
 * @package WPContentFlow
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_CONTENT_FLOW_VERSION', '1.0.0');
define('WP_CONTENT_FLOW_PLUGIN_FILE', __FILE__);
define('WP_CONTENT_FLOW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_CONTENT_FLOW_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_CONTENT_FLOW_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class WPContentFlow {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
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
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('init', array($this, 'init'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Load plugin textdomain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    /**
     * Plugin initialization
     */
    public function init() {
        // Load required classes
        $this->load_dependencies();
        
        // Initialize components
        $this->init_components();
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/class-ai-core.php';
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/class-workflow-engine.php';
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/class-content-manager.php';
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/admin/class-admin.php';
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/class-rest-api.php';
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/class-database.php';
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        new WPContentFlow_AI_Core();
        new WPContentFlow_Workflow_Engine();
        new WPContentFlow_Content_Manager();
        new WPContentFlow_Admin();
        new WPContentFlow_REST_API();
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        // Routes will be registered by WPContentFlow_REST_API class
    }
    
    /**
     * Enqueue block editor assets
     */
    public function enqueue_block_editor_assets() {
        $asset_file = include(WP_CONTENT_FLOW_PLUGIN_DIR . 'dist/editor.asset.php');
        
        wp_enqueue_script(
            'wp-content-flow-editor',
            WP_CONTENT_FLOW_PLUGIN_URL . 'dist/editor.js',
            $asset_file['dependencies'],
            $asset_file['version']
        );
        
        wp_enqueue_style(
            'wp-content-flow-editor',
            WP_CONTENT_FLOW_PLUGIN_URL . 'dist/editor.css',
            array(),
            $asset_file['version']
        );
        
        // Localize script with API settings
        wp_localize_script('wp-content-flow-editor', 'wpContentFlow', array(
            'apiUrl' => rest_url('wp-content-flow/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'pluginUrl' => WP_CONTENT_FLOW_PLUGIN_URL,
            'version' => WP_CONTENT_FLOW_VERSION,
        ));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('AI Workflows', 'wp-content-flow'),
            __('AI Workflows', 'wp-content-flow'),
            'manage_options',
            'wp-content-flow',
            array($this, 'admin_page'),
            'dashicons-admin-site-alt3',
            30
        );
    }
    
    /**
     * Admin page callback
     */
    public function admin_page() {
        echo '<div id="wp-content-flow-admin"></div>';
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'wp-content-flow',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        WPContentFlow_Database::create_tables();
        
        // Set default options
        add_option('wp_content_flow_version', WP_CONTENT_FLOW_VERSION);
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Fire activation hook
        do_action('wp_content_flow_activated', false);
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('wp_content_flow_cleanup');
        
        // Fire deactivation hook
        do_action('wp_content_flow_deactivated', false);
    }
}

/**
 * Initialize the plugin
 */
function wp_content_flow_init() {
    return WPContentFlow::get_instance();
}

// Global function for external access
$GLOBALS['wp_content_flow'] = wp_content_flow_init();