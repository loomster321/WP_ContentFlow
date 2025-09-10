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
        // Register settings immediately if admin_init has already fired, otherwise hook it
        if (did_action('admin_init')) {
            error_log('WP Content Flow: admin_init already fired, registering settings immediately');
            $this->register_settings();
        } else {
            error_log('WP Content Flow: admin_init not fired yet, hooking registration');
            add_action('admin_init', array($this, 'register_settings'), 5);
        }
        
        // Set capability filter for settings page
        add_filter('option_page_capability_' . $this->settings_group, array($this, 'settings_page_capability'));
    }
    
    /**
     * Force settings registration immediately
     */
    public function force_settings_registration() {
        // Immediately register with WordPress Settings API
        if (function_exists('register_setting')) {
            register_setting(
                $this->settings_group,
                $this->option_name,
                array($this, 'sanitize_settings')
            );
        }
        
        // Also ensure it's in allowed_options
        global $allowed_options;
        if (!isset($allowed_options[$this->settings_group])) {
            $allowed_options[$this->settings_group] = array();
        }
        if (!in_array($this->option_name, $allowed_options[$this->settings_group])) {
            $allowed_options[$this->settings_group][] = $this->option_name;
        }
        
        error_log('WP Content Flow: Force registration completed - group added to allowed_options');
    }
    
    /**
     * Set capability for settings page
     */
    public function settings_page_capability($capability) {
        return 'manage_options';
    }
    
    
    public function register_settings() {
        error_log('WP Content Flow: register_settings() method called');
        
        // Only register if functions are available (admin context)
        if (!function_exists('register_setting')) {
            error_log('WP Content Flow: register_setting function not available');
            return;
        }
        
        if (!function_exists('add_settings_section')) {
            error_log('WP Content Flow: add_settings_section function not available');
            return;
        }
        
        if (!function_exists('add_settings_field')) {
            error_log('WP Content Flow: add_settings_field function not available');
            return;
        }
        
        error_log('WP Content Flow: All WordPress Settings API functions available');
        
        // Force add to allowed options BEFORE calling register_setting
        global $allowed_options;
        if (!isset($allowed_options[$this->settings_group])) {
            $allowed_options[$this->settings_group] = array();
        }
        if (!in_array($this->option_name, $allowed_options[$this->settings_group])) {
            $allowed_options[$this->settings_group][] = $this->option_name;
        }
        
        error_log('WP Content Flow: Added to allowed_options - group: ' . $this->settings_group . ', option: ' . $this->option_name);
        
        // Register with WordPress Settings API
        $result = register_setting(
            $this->settings_group,
            $this->option_name,
            array($this, 'sanitize_settings')
        );
        
        error_log('WP Content Flow: register_setting called - result: ' . ($result ? 'true' : 'false'));
        
        // Verify registration worked
        if (isset($allowed_options[$this->settings_group]) && in_array($this->option_name, $allowed_options[$this->settings_group])) {
            error_log('WP Content Flow: Settings registration SUCCESS - group is in allowed_options');
        } else {
            error_log('WP Content Flow: Settings registration FAILED - group not in allowed_options');
        }
        
        error_log('WP Content Flow: Starting sections and fields registration...');
        
        // AI Providers Section
        add_settings_section(
            'wp_content_flow_providers',
            __('AI Provider Configuration', 'wp-content-flow'),
            array($this, 'render_providers_section'),
            'wp-content-flow'
        );
        error_log('WP Content Flow: Added providers section');
        
        // OpenAI Settings
        add_settings_field(
            'openai_api_key',
            __('OpenAI API Key', 'wp-content-flow'),
            array($this, 'render_openai_api_key_field'),
            'wp-content-flow',
            'wp_content_flow_providers'
        );
        error_log('WP Content Flow: Added OpenAI API key field');
        
        // Anthropic Settings
        add_settings_field(
            'anthropic_api_key',
            __('Anthropic API Key', 'wp-content-flow'),
            array($this, 'render_anthropic_api_key_field'),
            'wp-content-flow',
            'wp_content_flow_providers'
        );
        error_log('WP Content Flow: Added Anthropic API key field');
        
        // Google AI Settings
        add_settings_field(
            'google_api_key',
            __('Google AI API Key', 'wp-content-flow'),
            array($this, 'render_google_api_key_field'),
            'wp-content-flow',
            'wp_content_flow_providers'
        );
        error_log('WP Content Flow: Added Google AI API key field');
        
        // Configuration Section
        add_settings_section(
            'wp_content_flow_config',
            __('Configuration', 'wp-content-flow'),
            array($this, 'render_config_section'),
            'wp-content-flow'
        );
        error_log('WP Content Flow: Added config section');
        
        // Default AI Provider
        add_settings_field(
            'default_ai_provider',
            __('Default AI Provider', 'wp-content-flow'),
            array($this, 'render_default_provider_field'),
            'wp-content-flow',
            'wp_content_flow_config'
        );
        error_log('WP Content Flow: Added default AI provider field');
        
        // Cache Settings
        add_settings_field(
            'cache_enabled',
            __('Enable Caching', 'wp-content-flow'),
            array($this, 'render_cache_enabled_field'),
            'wp-content-flow',
            'wp_content_flow_config'
        );
        error_log('WP Content Flow: Added cache enabled field');
        
        // Rate Limiting
        add_settings_field(
            'requests_per_minute',
            __('Requests Per Minute', 'wp-content-flow'),
            array($this, 'render_requests_per_minute_field'),
            'wp-content-flow',
            'wp_content_flow_config'
        );
        error_log('WP Content Flow: Added requests per minute field');
        
        // Final verification - check global state
        global $wp_settings_sections, $wp_settings_fields;
        
        $providers_section_exists = isset($wp_settings_sections['wp-content-flow']['wp_content_flow_providers']);
        $config_section_exists = isset($wp_settings_sections['wp-content-flow']['wp_content_flow_config']);
        $fields_exist = isset($wp_settings_fields['wp-content-flow']);
        
        error_log('WP Content Flow: Final verification - Providers section exists: ' . ($providers_section_exists ? 'YES' : 'NO'));
        error_log('WP Content Flow: Final verification - Config section exists: ' . ($config_section_exists ? 'YES' : 'NO'));
        error_log('WP Content Flow: Final verification - Fields exist: ' . ($fields_exist ? 'YES' : 'NO'));
        
        if ($fields_exist) {
            $field_count = 0;
            foreach ($wp_settings_fields['wp-content-flow'] as $section => $fields) {
                $field_count += count($fields);
            }
            error_log('WP Content Flow: Total fields registered: ' . $field_count);
        }
        
        error_log('WP Content Flow: register_settings() method completed successfully');
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
        
        if (isset($input['google_api_key'])) {
            $sanitized['google_api_key'] = sanitize_text_field($input['google_api_key']);
        }
        
        // Sanitize configuration settings
        if (isset($input['default_ai_provider'])) {
            $allowed_providers = array('openai', 'anthropic', 'google');
            $sanitized['default_ai_provider'] = in_array($input['default_ai_provider'], $allowed_providers) 
                ? $input['default_ai_provider'] : 'openai';
        }
        
        // Handle checkbox: set to true if present, false if not present
        $sanitized['cache_enabled'] = isset($input['cache_enabled']) ? true : false;
        error_log('WP Content Flow: Cache enabled checkbox - Input present: ' . (isset($input['cache_enabled']) ? 'YES' : 'NO') . ', Setting to: ' . ($sanitized['cache_enabled'] ? 'true' : 'false'));
        
        if (isset($input['requests_per_minute'])) {
            $sanitized['requests_per_minute'] = absint($input['requests_per_minute']);
            if ($sanitized['requests_per_minute'] < 1) {
                $sanitized['requests_per_minute'] = 10;
            }
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
            
            <?php 
            // Display custom success message
            if (get_transient('wp_content_flow_settings_saved')) {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'wp-content-flow') . '</p></div>';
                delete_transient('wp_content_flow_settings_saved');
            }
            
            settings_errors(); 
            ?>
            
            <form method="post" action="options.php" id="wp-content-flow-settings-form" novalidate>
                <?php
                // Use standard WordPress Settings API
                settings_fields($this->settings_group);
                
                // Debug: Check if sections exist before rendering
                global $wp_settings_sections;
                if (isset($wp_settings_sections['wp-content-flow']) && !empty($wp_settings_sections['wp-content-flow'])) {
                    error_log('WP Content Flow: Rendering sections - found ' . count($wp_settings_sections['wp-content-flow']) . ' sections');
                    do_settings_sections('wp-content-flow');
                } else {
                    error_log('WP Content Flow: WARNING - No sections found for wp-content-flow, falling back to manual rendering');
                    echo '<div class="notice notice-warning"><p><strong>Debug:</strong> WordPress Settings API sections not found. Using fallback rendering.</p></div>';
                    
                    // Force re-registration if needed
                    $this->register_settings();
                    
                    // Try again
                    if (isset($wp_settings_sections['wp-content-flow']) && !empty($wp_settings_sections['wp-content-flow'])) {
                        error_log('WP Content Flow: After re-registration, found ' . count($wp_settings_sections['wp-content-flow']) . ' sections');
                        do_settings_sections('wp-content-flow');
                    } else {
                        error_log('WP Content Flow: Still no sections after re-registration, using manual fallback');
                        $this->render_settings_sections_manually();
                    }
                }
                
                submit_button(__('Save Settings', 'wp-content-flow'), 'primary', 'submit', false, array('id' => 'wp-content-flow-submit-btn'));
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
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            console.log('WP Content Flow Settings: Page loaded');
            
            // Fix dropdown persistence issue - force correct value display
            var providerDropdown = $('select[name="<?php echo $this->option_name; ?>[default_ai_provider]"]');
            if (providerDropdown.length) {
                var expectedValue = '<?php echo esc_js($settings['default_ai_provider'] ?? 'openai'); ?>';
                var currentValue = providerDropdown.val();
                
                console.log('WP Content Flow: Expected provider value:', expectedValue);
                console.log('WP Content Flow: Current dropdown value:', currentValue);
                
                if (currentValue !== expectedValue) {
                    console.log('WP Content Flow: Fixing dropdown mismatch - setting to:', expectedValue);
                    providerDropdown.val(expectedValue);
                    // Trigger change event to ensure any listeners are notified
                    providerDropdown.trigger('change');
                } else {
                    console.log('WP Content Flow: Dropdown value is correct');
                }
                
                // Add visual debugging to make the selected option obvious
                providerDropdown.css({
                    'border': '2px solid #0073aa',
                    'background-color': '#f0f8ff'
                });
                
                // Verify after a short delay to catch any race conditions
                setTimeout(function() {
                    var finalValue = providerDropdown.val();
                    if (finalValue !== expectedValue) {
                        console.warn('WP Content Flow: PERSISTENT DROPDOWN ISSUE - forcing value again');
                        providerDropdown.val(expectedValue);
                        providerDropdown.trigger('change');
                    }
                }, 500);
            }
            
            // Enhanced form submission handling
            $('#wp-content-flow-settings-form').on('submit', function(e) {
                console.log('WP Content Flow Settings: Form submission started');
                
                // Basic validation
                var hasApiKey = false;
                var apiKeyFields = [
                    'input[name="wp_content_flow_settings[openai_api_key]"]',
                    'input[name="wp_content_flow_settings[anthropic_api_key]"]',
                    'input[name="wp_content_flow_settings[google_api_key]"]'
                ];
                
                apiKeyFields.forEach(function(selector) {
                    var field = $(selector);
                    if (field.length && field.val().trim() !== '') {
                        hasApiKey = true;
                        console.log('Found API key in: ' + selector);
                    }
                });
                
                if (!hasApiKey) {
                    alert('Please configure at least one AI provider API key before saving.');
                    e.preventDefault();
                    return false;
                }
                
                // Show loading state
                var submitBtn = $('#wp-content-flow-submit-btn');
                var originalText = submitBtn.val();
                submitBtn.val('Saving Settings...').prop('disabled', true);
                
                // Add form debugging data
                var formData = $(this).serializeArray();
                console.log('Form data being submitted:', formData);
                
                // Check for required hidden fields
                var optionPage = $('input[name="option_page"]').val();
                var nonce = $('input[name="_wpnonce"]').val();
                
                console.log('Option page:', optionPage);
                console.log('Nonce present:', !!nonce);
                
                if (!optionPage || !nonce) {
                    alert('Form security validation failed. Please refresh the page and try again.');
                    submitBtn.val(originalText).prop('disabled', false);
                    e.preventDefault();
                    return false;
                }
                
                console.log('Form validation passed, submitting...');
                
                // Allow form to submit normally
                return true;
            });
            
            // Monitor for WordPress admin notices after page load
            setTimeout(function() {
                var notices = $('.notice-success, .updated, .notice-error, .error');
                if (notices.length > 0) {
                    console.log('Found admin notices:', notices.length);
                    notices.each(function() {
                        console.log('Notice text:', $(this).text().trim());
                    });
                } else {
                    console.log('No admin notices found');
                }
            }, 1000);
        });
        </script>
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
    
    /**
     * Render Google AI API key field
     */
    public function render_google_api_key_field() {
        $settings = get_option($this->option_name, array());
        $value = isset($settings['google_api_key']) ? $settings['google_api_key'] : '';
        ?>
        <input type="password" name="<?php echo $this->option_name; ?>[google_api_key]" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description">
            <?php _e('Enter your Google AI API key. Get one from:', 'wp-content-flow'); ?> 
            <a href="https://makersuite.google.com/app/apikey" target="_blank">https://makersuite.google.com/app/apikey</a>
        </p>
        <?php
    }
    
    /**
     * Render configuration section
     */
    public function render_config_section() {
        echo '<p>' . __('Configure plugin behavior and performance settings.', 'wp-content-flow') . '</p>';
    }
    
    /**
     * Render default AI provider field
     */
    public function render_default_provider_field() {
        $settings = get_option($this->option_name, array());
        $value = isset($settings['default_ai_provider']) ? $settings['default_ai_provider'] : 'openai';
        
        // Debug information for troubleshooting
        echo '<!-- WP Content Flow Debug: Current provider value = "' . esc_attr($value) . '" -->';
        echo '<!-- WP Content Flow Debug: Settings = ' . esc_attr(json_encode($settings)) . ' -->';
        ?>
        <select name="<?php echo $this->option_name; ?>[default_ai_provider]" class="regular-text" id="default-ai-provider-dropdown">
            <option value="openai" <?php selected($value, 'openai'); ?>>OpenAI (GPT)</option>
            <option value="anthropic" <?php selected($value, 'anthropic'); ?>>Anthropic (Claude)</option>
            <option value="google" <?php selected($value, 'google'); ?>>Google AI (Gemini)</option>
        </select>
        <p class="description">
            <?php _e('Choose which AI provider to use by default for content generation.', 'wp-content-flow'); ?>
            <br><small><strong>Current database value:</strong> <code><?php echo esc_html($value); ?></code></small>
        </p>
        <?php
    }
    
    /**
     * Render cache enabled field
     */
    public function render_cache_enabled_field() {
        $settings = get_option($this->option_name, array());
        $value = isset($settings['cache_enabled']) ? $settings['cache_enabled'] : true;
        
        // Debug information for troubleshooting
        echo '<!-- WP Content Flow Debug: Cache enabled value = ' . ($value ? 'true' : 'false') . ' -->';
        echo '<!-- WP Content Flow Debug: Cache setting type = ' . gettype($value) . ' -->';
        ?>
        <input type="checkbox" name="<?php echo $this->option_name; ?>[cache_enabled]" value="1" <?php checked($value, 1); ?> />
        <p class="description">
            <?php _e('Enable caching to improve performance and reduce API calls.', 'wp-content-flow'); ?>
            <br><small><strong>Current database value:</strong> <code><?php echo $value ? 'true' : 'false'; ?></code></small>
        </p>
        <?php
    }
    
    /**
     * Render requests per minute field
     */
    public function render_requests_per_minute_field() {
        $settings = get_option($this->option_name, array());
        $value = isset($settings['requests_per_minute']) ? $settings['requests_per_minute'] : 10;
        ?>
        <input type="number" name="<?php echo $this->option_name; ?>[requests_per_minute]" value="<?php echo esc_attr($value); ?>" min="1" max="100" class="small-text" />
        <p class="description">
            <?php _e('Maximum number of AI requests per minute to prevent rate limiting.', 'wp-content-flow'); ?>
        </p>
        <?php
    }
    
    /**
     * Render settings sections manually (fallback when WordPress Settings API fails)
     */
    private function render_settings_sections_manually() {
        ?>
        <h2><?php _e('AI Provider Configuration', 'wp-content-flow'); ?></h2>
        <p><?php _e('Configure your AI provider API keys. You need at least one provider configured.', 'wp-content-flow'); ?></p>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('OpenAI API Key', 'wp-content-flow'); ?></th>
                <td><?php $this->render_openai_api_key_field(); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Anthropic API Key', 'wp-content-flow'); ?></th>
                <td><?php $this->render_anthropic_api_key_field(); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Google AI API Key', 'wp-content-flow'); ?></th>
                <td><?php $this->render_google_api_key_field(); ?></td>
            </tr>
        </table>
        
        <h2><?php _e('Configuration', 'wp-content-flow'); ?></h2>
        <p><?php _e('Configure plugin behavior and performance settings.', 'wp-content-flow'); ?></p>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Default AI Provider', 'wp-content-flow'); ?></th>
                <td><?php $this->render_default_provider_field(); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Enable Caching', 'wp-content-flow'); ?></th>
                <td><?php $this->render_cache_enabled_field(); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Requests Per Minute', 'wp-content-flow'); ?></th>
                <td><?php $this->render_requests_per_minute_field(); ?></td>
            </tr>
        </table>
        <?php
    }
}