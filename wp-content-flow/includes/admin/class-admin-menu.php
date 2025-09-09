<?php
/**
 * Admin Menu Class
 * 
 * Handles WordPress admin menu registration and page management
 *
 * @package WP_Content_Flow
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Content_Flow_Admin_Menu {
    
    /**
     * Instance of this class
     * @var WP_Content_Flow_Admin_Menu
     */
    private static $instance = null;
    
    /**
     * Main menu slug
     * @var string
     */
    private $menu_slug = 'wp-content-flow';
    
    /**
     * Get singleton instance
     * @return WP_Content_Flow_Admin_Menu
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
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'init_settings'));
    }
    
    /**
     * Register admin menu pages
     */
    public function register_admin_menu() {
        // Main menu page
        add_menu_page(
            __('Content Flow', 'wp-content-flow'),
            __('Content Flow', 'wp-content-flow'),
            'manage_options',
            $this->menu_slug,
            array($this, 'render_dashboard_page'),
            'dashicons-edit-page',
            30
        );
        
        // Settings submenu
        add_submenu_page(
            $this->menu_slug,
            __('Settings', 'wp-content-flow'),
            __('Settings', 'wp-content-flow'),
            'manage_options',
            $this->menu_slug . '-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our plugin pages
        if (strpos($hook, $this->menu_slug) === false) {
            return;
        }
        
        wp_enqueue_script(
            'wp-content-flow-admin',
            WP_CONTENT_FLOW_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            WP_CONTENT_FLOW_VERSION,
            true
        );
        
        wp_localize_script('wp-content-flow-admin', 'wpContentFlow', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_content_flow_nonce'),
        ));
    }
    
    /**
     * Settings page instance
     * @var WP_Content_Flow_Settings_Page
     */
    private $settings_page;
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        // Initialize settings page to register settings on admin_init
        if (class_exists('WP_Content_Flow_Settings_Page') && !$this->settings_page) {
            $this->settings_page = new WP_Content_Flow_Settings_Page();
        }
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        // Get plugin statistics
        $settings = get_option('wp_content_flow_settings', array());
        $configured_providers = 0;
        
        if (!empty($settings['openai_api_key'])) $configured_providers++;
        if (!empty($settings['anthropic_api_key'])) $configured_providers++;
        if (!empty($settings['google_api_key'])) $configured_providers++;
        
        ?>
        <div class="wrap">
            <h1><?php _e('WP Content Flow Dashboard', 'wp-content-flow'); ?></h1>
            
            <div class="wp-content-flow-hero">
                <div class="hero-content">
                    <h2><?php _e('Welcome to WordPress AI Content Flow!', 'wp-content-flow'); ?></h2>
                    <p class="about-description"><?php _e('AI-powered content generation and workflow automation for WordPress.', 'wp-content-flow'); ?></p>
                </div>
                <div class="hero-image">
                    <img src="<?php echo WP_CONTENT_FLOW_PLUGIN_URL; ?>assets/images/ai-workflow-dashboard.png" alt="AI Content Workflow Dashboard" style="width: 100%; height: 200px; object-fit: cover; border-radius: 8px;" />
                </div>
            </div>
            
            <div class="dashboard-widgets-wrap">
                <div class="dashboard-widgets">
                    
                    <!-- Status Widget -->
                    <div class="postbox">
                        <h3 class="hndle"><span><?php _e('Plugin Status', 'wp-content-flow'); ?></span></h3>
                        <div class="inside">
                            <ul>
                                <li><strong><?php _e('Status:', 'wp-content-flow'); ?></strong> 
                                    <span style="color: green;"><?php _e('Active ✓', 'wp-content-flow'); ?></span>
                                </li>
                                <li><strong><?php _e('Version:', 'wp-content-flow'); ?></strong> <?php echo WP_CONTENT_FLOW_VERSION; ?></li>
                                <li><strong><?php _e('AI Providers Configured:', 'wp-content-flow'); ?></strong> 
                                    <?php echo $configured_providers; ?>/3
                                </li>
                                <li><strong><?php _e('Database Tables:', 'wp-content-flow'); ?></strong> 
                                    <span style="color: green;"><?php _e('Initialized ✓', 'wp-content-flow'); ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Configuration Widget -->
                    <div class="postbox">
                        <h3 class="hndle"><span><?php _e('Configuration', 'wp-content-flow'); ?></span></h3>
                        <div class="inside">
                            <?php if ($configured_providers > 0): ?>
                                <p style="color: green;"><?php _e('✓ AI providers configured and ready to use.', 'wp-content-flow'); ?></p>
                                <p><strong><?php _e('Default Provider:', 'wp-content-flow'); ?></strong> 
                                    <?php echo ucfirst($settings['default_ai_provider'] ?? 'openai'); ?>
                                </p>
                            <?php else: ?>
                                <p style="color: orange;"><?php _e('⚠ No AI providers configured yet.', 'wp-content-flow'); ?></p>
                            <?php endif; ?>
                            
                            <p>
                                <a href="<?php echo admin_url('admin.php?page=wp-content-flow-settings'); ?>" class="button button-primary">
                                    <?php _e('Configure Settings', 'wp-content-flow'); ?>
                                </a>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Quick Actions Widget -->
                    <div class="postbox">
                        <h3 class="hndle"><span><?php _e('Quick Actions', 'wp-content-flow'); ?></span></h3>
                        <div class="inside">
                            <ul>
                                <li><a href="<?php echo admin_url('post-new.php'); ?>"><?php _e('Create New Post with AI', 'wp-content-flow'); ?></a></li>
                                <li><a href="<?php echo admin_url('admin.php?page=wp-content-flow-settings'); ?>"><?php _e('Configure API Keys', 'wp-content-flow'); ?></a></li>
                                <li><a href="<?php echo admin_url('edit.php?post_type=wp_ai_workflow'); ?>"><?php _e('Manage Workflows', 'wp-content-flow'); ?></a></li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Getting Started Widget -->
                    <div class="postbox">
                        <h3 class="hndle"><span><?php _e('Getting Started', 'wp-content-flow'); ?></span></h3>
                        <div class="inside">
                            <ol>
                                <li><?php _e('Configure your AI provider API keys in Settings', 'wp-content-flow'); ?></li>
                                <li><?php _e('Create a new post and add the "AI Text Generator" block', 'wp-content-flow'); ?></li>
                                <li><?php _e('Enter a prompt and generate AI-powered content', 'wp-content-flow'); ?></li>
                                <li><?php _e('Set up automated workflows for content generation', 'wp-content-flow'); ?></li>
                            </ol>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
        
        <style>
        .wp-content-flow-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            padding: 0;
            margin-bottom: 30px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .hero-content {
            padding: 30px;
            color: white;
        }
        .hero-content h2 {
            margin: 0 0 15px 0;
            font-size: 28px;
            font-weight: 600;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        .hero-content .about-description {
            margin: 0;
            font-size: 16px;
            opacity: 0.9;
            line-height: 1.6;
            max-width: 600px;
        }
        .hero-image {
            margin-top: 20px;
        }
        .hero-image img {
            display: block;
            width: 100%;
            height: auto;
            max-height: 200px;
            object-fit: cover;
        }
        .dashboard-widgets-wrap {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .postbox {
            border: 1px solid #ccd0d4;
            background: #fff;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .postbox h3 {
            margin: 0;
            padding: 12px 16px;
            border-bottom: 1px solid #eee;
            background: #f8f9fa;
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }
        .postbox .inside {
            padding: 16px;
        }
        .postbox ul {
            margin: 0;
            padding-left: 20px;
        }
        .postbox li {
            margin-bottom: 8px;
            line-height: 1.5;
        }
        .postbox a {
            text-decoration: none;
            color: #0073aa;
        }
        .postbox a:hover {
            color: #005a87;
            text-decoration: underline;
        }
        </style>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Use the settings page instance that was created during init_settings()
        if ($this->settings_page) {
            $this->settings_page->render();
        } else {
            echo '<div class="wrap">';
            echo '<h1>' . __('Settings', 'wp-content-flow') . '</h1>';
            echo '<p>' . __('Settings page is being prepared.', 'wp-content-flow') . '</p>';
            echo '</div>';
        }
    }
}