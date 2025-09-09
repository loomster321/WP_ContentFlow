<?php
/**
 * Settings Page Class
 * 
 * Handles plugin settings management and configuration UI
 *
 * @package WP_Content_Flow
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Content_Flow_Settings_Page {
    
    /**
     * Settings option name
     * @var string
     */
    private $option_name = 'wp_content_flow_settings';
    
    /**
     * Settings group name
     * @var string
     */
    private $settings_group = 'wp_content_flow_settings_group';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'register_settings'), 1);
        
        // Force registration during WordPress options processing
        add_filter('option_page_capability_' . $this->settings_group, array($this, 'settings_page_capability'));
        add_action('init', array($this, 'ensure_settings_registration'), 1);
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // Only register if functions are available (admin context)
        if (!function_exists('register_setting')) {
            return;
        }
        
        register_setting(
            'wp_content_flow_settings_group',
            $this->option_name,
            array($this, 'sanitize_settings')
        );
        
        // AI Providers Section
        add_settings_section(
            'wp_content_flow_providers',
            __('AI Provider Configuration', 'wp-content-flow'),
            array($this, 'render_providers_section'),
            'wp-content-flow'
        );
        
        // OpenAI Settings
        add_settings_field(
            'openai_api_key',
            __('OpenAI API Key', 'wp-content-flow'),
            array($this, 'render_openai_api_key_field'),
            'wp-content-flow',
            'wp_content_flow_providers'
        );
        
        // Anthropic Settings
        add_settings_field(
            'anthropic_api_key',
            __('Anthropic API Key', 'wp-content-flow'),
            array($this, 'render_anthropic_api_key_field'),
            'wp-content-flow',
            'wp_content_flow_providers'
        );
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Sanitize API keys
        if (isset($input['openai_api_key'])) {
            $sanitized['openai_api_key'] = sanitize_text_field($input['openai_api_key']);
        }
        
        if (isset($input['anthropic_api_key'])) {
            $sanitized['anthropic_api_key'] = sanitize_text_field($input['anthropic_api_key']);
        }
        
        return $sanitized;
    }
    
    /**
     * Render settings page
     */
    public function render() {
        
        ?>
        <div class="wrap">
            <h1><?php _e('WP Content Flow Settings', 'wp-content-flow'); ?></h1>
            
            <?php settings_errors(); ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('wp_content_flow_settings_group');
                do_settings_sections('wp-content-flow');
                submit_button(__('Save Settings', 'wp-content-flow'));
                ?>
            </form>
            
            <div class="wp-content-flow-info">
                <h3><?php _e('Current Configuration', 'wp-content-flow'); ?></h3>
                <?php
                $settings = get_option($this->option_name, array());
                if (!empty($settings)) {
                    echo '<ul>';
                    foreach ($settings as $key => $value) {
                        if (strpos($key, 'api_key') !== false) {
                            echo '<li><strong>' . ucfirst(str_replace('_', ' ', $key)) . ':</strong> ' . (empty($value) ? 'Not configured' : 'Configured ✓') . '</li>';
                        } else {
                            echo '<li><strong>' . ucfirst(str_replace('_', ' ', $key)) . ':</strong> ' . esc_html($value) . '</li>';
                        }
                    }
                    echo '</ul>';
                } else {
                    echo '<p>No settings configured yet.</p>';
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render providers section
     */
    public function render_providers_section() {
        echo '<p>' . __('Configure your AI provider API keys. You need at least one provider configured.', 'wp-content-flow') . '</p>';
    }
    
    /**
     * Render OpenAI API key field
     */
    public function render_openai_api_key_field() {
        $settings = get_option($this->option_name, array());
        $value = isset($settings['openai_api_key']) ? $settings['openai_api_key'] : '';
        ?>
        <input type="password" name="<?php echo $this->option_name; ?>[openai_api_key]" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description">
            <?php _e('Enter your OpenAI API key. Get one from:', 'wp-content-flow'); ?> 
            <a href="https://platform.openai.com/api-keys" target="_blank">https://platform.openai.com/api-keys</a>
        </p>
        <?php
    }
    
    /**
     * Render Anthropic API key field
     */
    public function render_anthropic_api_key_field() {
        $settings = get_option($this->option_name, array());
        $value = isset($settings['anthropic_api_key']) ? $settings['anthropic_api_key'] : '';
        ?>
        <input type="password" name="<?php echo $this->option_name; ?>[anthropic_api_key]" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description">
            <?php _e('Enter your Anthropic API key. Get one from:', 'wp-content-flow'); ?> 
            <a href="https://console.anthropic.com/" target="_blank">https://console.anthropic.com/</a>
        </p>
        <?php
    }
}