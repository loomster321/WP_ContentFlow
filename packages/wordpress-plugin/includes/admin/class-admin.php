<?php
/**
 * Admin interface for WP Content Flow
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPContentFlow_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menus'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menus() {
        // Main menu page
        add_menu_page(
            __('AI Workflows', 'wp-content-flow'),
            __('AI Workflows', 'wp-content-flow'),
            'manage_options',
            'wp-content-flow',
            array($this, 'workflows_page'),
            'dashicons-admin-site-alt3',
            30
        );
        
        // Settings submenu
        add_submenu_page(
            'wp-content-flow',
            __('AI Settings', 'wp-content-flow'),
            __('Settings', 'wp-content-flow'),
            'manage_options',
            'wp-content-flow-settings',
            array($this, 'settings_page')
        );
        
        // Analytics submenu  
        add_submenu_page(
            'wp-content-flow',
            __('AI Analytics', 'wp-content-flow'),
            __('Analytics', 'wp-content-flow'),
            'manage_options',
            'wp-content-flow-analytics',
            array($this, 'analytics_page')
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        // API Settings
        register_setting('wp_content_flow_api_settings', 'wp_content_flow_api_url');
        register_setting('wp_content_flow_api_settings', 'wp_content_flow_api_token');
        register_setting('wp_content_flow_api_settings', 'wp_content_flow_openai_api_key');
        register_setting('wp_content_flow_api_settings', 'wp_content_flow_anthropic_api_key');
        register_setting('wp_content_flow_api_settings', 'wp_content_flow_google_api_key');
        
        // Performance Settings
        register_setting('wp_content_flow_performance_settings', 'wp_content_flow_cache_enabled');
        register_setting('wp_content_flow_performance_settings', 'wp_content_flow_cache_duration');
        register_setting('wp_content_flow_performance_settings', 'wp_content_flow_rate_limit_enabled');
        register_setting('wp_content_flow_performance_settings', 'wp_content_flow_rate_limit_requests_per_minute');
        
        // User Interface Settings
        register_setting('wp_content_flow_ui_settings', 'wp_content_flow_show_confidence_scores');
        register_setting('wp_content_flow_ui_settings', 'wp_content_flow_auto_apply_high_confidence');
        register_setting('wp_content_flow_ui_settings', 'wp_content_flow_max_suggestions');
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'wp-content-flow') !== false) {
            wp_enqueue_script('wp-content-flow-admin', WP_CONTENT_FLOW_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), WP_CONTENT_FLOW_VERSION, true);
            wp_enqueue_style('wp-content-flow-admin', WP_CONTENT_FLOW_PLUGIN_URL . 'assets/css/admin.css', array(), WP_CONTENT_FLOW_VERSION);
            
            // Localize script for AJAX
            wp_localize_script('wp-content-flow-admin', 'wpContentFlowAdmin', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wp_content_flow_admin_nonce'),
                'strings' => array(
                    'testConnection' => __('Testing connection...', 'wp-content-flow'),
                    'connectionSuccess' => __('Connection successful!', 'wp-content-flow'),
                    'connectionFailed' => __('Connection failed. Please check your settings.', 'wp-content-flow')
                )
            ));
        }
    }
    
    /**
     * Workflows admin page
     */
    public function workflows_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div id="wp-content-flow-workflows-app">
                <!-- React app will mount here -->
            </div>
        </div>
        <?php
    }
    
    /**
     * Settings admin page
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('AI Content Flow Settings', 'wp-content-flow'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('wp_content_flow_api_settings');
                do_settings_sections('wp_content_flow_api_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Cloud API URL', 'wp-content-flow'); ?></th>
                        <td>
                            <input type="url" name="wp_content_flow_api_url" value="<?php echo esc_attr(get_option('wp_content_flow_api_url', 'http://localhost:3001')); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e('URL of your WP Content Flow cloud API service.', 'wp-content-flow'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('OpenAI API Key', 'wp-content-flow'); ?></th>
                        <td>
                            <input type="password" name="wp_content_flow_openai_api_key" value="<?php echo esc_attr(get_option('wp_content_flow_openai_api_key')); ?>" class="regular-text" />
                            <button type="button" class="button test-connection-btn" data-provider="openai"><?php esc_html_e('Test Connection', 'wp-content-flow'); ?></button>
                            <p class="description"><?php esc_html_e('Your OpenAI API key for content generation.', 'wp-content-flow'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Anthropic API Key', 'wp-content-flow'); ?></th>
                        <td>
                            <input type="password" name="wp_content_flow_anthropic_api_key" value="<?php echo esc_attr(get_option('wp_content_flow_anthropic_api_key')); ?>" class="regular-text" />
                            <button type="button" class="button test-connection-btn" data-provider="anthropic"><?php esc_html_e('Test Connection', 'wp-content-flow'); ?></button>
                            <p class="description"><?php esc_html_e('Your Anthropic API key for Claude models.', 'wp-content-flow'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Show Confidence Scores', 'wp-content-flow'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wp_content_flow_show_confidence_scores" value="1" <?php checked(get_option('wp_content_flow_show_confidence_scores', 1)); ?> />
                                <?php esc_html_e('Display AI confidence ratings with suggestions', 'wp-content-flow'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Auto-apply High Confidence', 'wp-content-flow'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wp_content_flow_auto_apply_high_confidence" value="1" <?php checked(get_option('wp_content_flow_auto_apply_high_confidence', 0)); ?> />
                                <?php esc_html_e('Automatically apply suggestions with confidence > 90%', 'wp-content-flow'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Analytics admin page
     */
    public function analytics_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('AI Analytics', 'wp-content-flow'); ?></h1>
            <div id="wp-content-flow-analytics-app">
                <!-- Analytics dashboard will be rendered here -->
            </div>
        </div>
        <?php
    }
}