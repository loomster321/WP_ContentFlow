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
        error_log('WP Content Flow: sanitize_settings called with input: ' . print_r($input, true));
        
        // Get existing settings to preserve unchanged values
        $existing = get_option($this->option_name, array());
        $sanitized = $existing; // Start with existing values
        
        // Handle OpenAI API key
        if (isset($input['openai_api_key']) && !empty(trim($input['openai_api_key']))) {
            $clean_key = sanitize_text_field($input['openai_api_key']);
            
            // Check if this is a masked key (contains asterisks)
            if (strpos($clean_key, '*') === false) {
                // This is a new key, encrypt it
                $encrypted = $this->encrypt_api_key($clean_key);
                if (!empty($encrypted)) {
                    $sanitized['openai_api_key_encrypted'] = $encrypted;
                    // Store plain key temporarily for immediate display (will be removed on next save)
                    $sanitized['openai_api_key'] = $clean_key;
                    error_log('WP Content Flow: OpenAI key encrypted and stored');
                } else {
                    error_log('WP Content Flow: OpenAI key encryption failed');
                }
            } else {
                // This is a masked key, don't change the stored value
                error_log('WP Content Flow: OpenAI key unchanged (masked value submitted)');
            }
        }
        
        // Handle Anthropic API key
        if (isset($input['anthropic_api_key']) && !empty(trim($input['anthropic_api_key']))) {
            $clean_key = sanitize_text_field($input['anthropic_api_key']);
            
            // Check if this is a masked key (contains asterisks)
            if (strpos($clean_key, '*') === false) {
                // This is a new key, encrypt it
                $encrypted = $this->encrypt_api_key($clean_key);
                if (!empty($encrypted)) {
                    $sanitized['anthropic_api_key_encrypted'] = $encrypted;
                    // Store plain key temporarily for immediate display
                    $sanitized['anthropic_api_key'] = $clean_key;
                    error_log('WP Content Flow: Anthropic key encrypted and stored');
                } else {
                    error_log('WP Content Flow: Anthropic key encryption failed');
                }
            } else {
                error_log('WP Content Flow: Anthropic key unchanged (masked value submitted)');
            }
        }
        
        // Handle Google AI API key
        if (isset($input['google_api_key']) && !empty(trim($input['google_api_key']))) {
            $clean_key = sanitize_text_field($input['google_api_key']);
            
            // Check if this is a masked key (contains asterisks)
            if (strpos($clean_key, '*') === false) {
                // This is a new key, encrypt it
                $encrypted = $this->encrypt_api_key($clean_key);
                if (!empty($encrypted)) {
                    $sanitized['google_ai_api_key_encrypted'] = $encrypted;
                    // Store plain key temporarily for immediate display
                    $sanitized['google_api_key'] = $clean_key;
                    error_log('WP Content Flow: Google AI key encrypted and stored');
                } else {
                    error_log('WP Content Flow: Google AI key encryption failed');
                }
            } else {
                error_log('WP Content Flow: Google AI key unchanged (masked value submitted)');
            }
        }
        
        // Sanitize configuration settings
        if (isset($input['default_ai_provider'])) {
            $allowed_providers = array('openai', 'anthropic', 'google');
            $sanitized['default_ai_provider'] = in_array($input['default_ai_provider'], $allowed_providers) 
                ? $input['default_ai_provider'] : 'openai';
            error_log('WP Content Flow: Default provider set to: ' . $sanitized['default_ai_provider']);
        }
        
        // Handle checkbox: set to true if present, false if not present
        $sanitized['cache_enabled'] = isset($input['cache_enabled']) ? true : false;
        error_log('WP Content Flow: Cache enabled checkbox - Input present: ' . (isset($input['cache_enabled']) ? 'YES' : 'NO') . ', Setting to: ' . ($sanitized['cache_enabled'] ? 'true' : 'false'));
        
        if (isset($input['requests_per_minute'])) {
            $sanitized['requests_per_minute'] = absint($input['requests_per_minute']);
            if ($sanitized['requests_per_minute'] < 1) {
                $sanitized['requests_per_minute'] = 10;
            }
            error_log('WP Content Flow: Requests per minute set to: ' . $sanitized['requests_per_minute']);
        }
        
        error_log('WP Content Flow: Final sanitized settings: ' . print_r($sanitized, true));
        
        // Set a transient to show success message
        set_transient('wp_content_flow_settings_saved', true, 5);
        
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
        $value = $this->get_api_key_for_display($settings, 'openai');
        ?>
        <input type="password" name="<?php echo $this->option_name; ?>[openai_api_key]" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="<?php echo !empty($value) && strpos($value, '*') !== false ? __('Key configured - enter new key to change', 'wp-content-flow') : __('Enter OpenAI API key...', 'wp-content-flow'); ?>" />
        <p class="description">
            <?php _e('Enter your OpenAI API key. Get one from:', 'wp-content-flow'); ?> 
            <a href="https://platform.openai.com/api-keys" target="_blank">https://platform.openai.com/api-keys</a>
            <?php if (!empty($value) && strpos($value, '*') !== false): ?>
                <br><span style="color: #2271b1;">✓ <?php _e('API key is configured and encrypted', 'wp-content-flow'); ?></span>
            <?php endif; ?>
        </p>
        <?php
    }
    
    /**
     * Render Anthropic API key field
     */
    public function render_anthropic_api_key_field() {
        $settings = get_option($this->option_name, array());
        $value = $this->get_api_key_for_display($settings, 'anthropic');
        ?>
        <input type="password" name="<?php echo $this->option_name; ?>[anthropic_api_key]" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="<?php echo !empty($value) && strpos($value, '*') !== false ? __('Key configured - enter new key to change', 'wp-content-flow') : __('Enter Anthropic API key...', 'wp-content-flow'); ?>" />
        <p class="description">
            <?php _e('Enter your Anthropic API key. Get one from:', 'wp-content-flow'); ?> 
            <a href="https://console.anthropic.com/" target="_blank">https://console.anthropic.com/</a>
            <?php if (!empty($value) && strpos($value, '*') !== false): ?>
                <br><span style="color: #2271b1;">✓ <?php _e('API key is configured and encrypted', 'wp-content-flow'); ?></span>
            <?php endif; ?>
        </p>
        <?php
    }
    
    /**
     * Render Google AI API key field
     */
    public function render_google_api_key_field() {
        $settings = get_option($this->option_name, array());
        $value = $this->get_api_key_for_display($settings, 'google_ai');
        ?>
        <input type="password" name="<?php echo $this->option_name; ?>[google_api_key]" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="<?php echo !empty($value) && strpos($value, '*') !== false ? __('Key configured - enter new key to change', 'wp-content-flow') : __('Enter Google AI API key...', 'wp-content-flow'); ?>" />
        <p class="description">
            <?php _e('Enter your Google AI API key. Get one from:', 'wp-content-flow'); ?> 
            <a href="https://makersuite.google.com/app/apikey" target="_blank">https://makersuite.google.com/app/apikey</a>
            <?php if (!empty($value) && strpos($value, '*') !== false): ?>
                <br><span style="color: #2271b1;">✓ <?php _e('API key is configured and encrypted', 'wp-content-flow'); ?></span>
            <?php endif; ?>
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
     * Encrypt API key for secure storage
     */
    private function encrypt_api_key($key) {
        if (empty($key)) {
            error_log('WP Content Flow: Empty key provided to encrypt_api_key');
            return '';
        }
        
        try {
            // Use WordPress salts for encryption key base
            $encryption_key = wp_salt('secure_auth') . wp_salt('logged_in');
            $encryption_key = hash('sha256', $encryption_key);
            
            // Generate a random IV
            $iv_length = openssl_cipher_iv_length('aes-256-cbc');
            $iv = openssl_random_pseudo_bytes($iv_length);
            
            if ($iv === false) {
                error_log('WP Content Flow: Failed to generate IV for encryption');
                return '';
            }
            
            // Encrypt the API key
            $encrypted = openssl_encrypt($key, 'aes-256-cbc', $encryption_key, 0, $iv);
            
            if ($encrypted === false) {
                error_log('WP Content Flow: openssl_encrypt failed');
                return '';
            }
            
            // Return base64 encoded IV + encrypted data
            $result = base64_encode($iv . $encrypted);
            error_log('WP Content Flow: Successfully encrypted key, length: ' . strlen($result));
            return $result;
            
        } catch (Exception $e) {
            error_log('WP Content Flow: Encryption error: ' . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Decrypt API key for use
     */
    private function decrypt_api_key($encrypted_key) {
        if (empty($encrypted_key)) {
            error_log('WP Content Flow: Empty encrypted key provided to decrypt_api_key');
            return '';
        }
        
        try {
            // Use same encryption key as encrypt method
            $encryption_key = wp_salt('secure_auth') . wp_salt('logged_in');
            $encryption_key = hash('sha256', $encryption_key);
            
            // Decode the encrypted data
            $data = base64_decode($encrypted_key);
            if ($data === false) {
                error_log('WP Content Flow: base64_decode failed in decrypt_api_key');
                return '';
            }
            
            // Extract IV and encrypted content
            $iv_length = openssl_cipher_iv_length('aes-256-cbc');
            if (strlen($data) < $iv_length) {
                error_log('WP Content Flow: Encrypted data too short, expected at least ' . $iv_length . ' bytes');
                return '';
            }
            
            $iv = substr($data, 0, $iv_length);
            $encrypted = substr($data, $iv_length);
            
            // Decrypt the API key
            $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $encryption_key, 0, $iv);
            
            if ($decrypted === false) {
                error_log('WP Content Flow: openssl_decrypt failed');
                return '';
            }
            
            error_log('WP Content Flow: Successfully decrypted key');
            return $decrypted;
            
        } catch (Exception $e) {
            error_log('WP Content Flow: Decryption error: ' . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Get API key for display (show partial key if encrypted)
     */
    private function get_api_key_for_display($settings, $provider) {
        error_log('WP Content Flow: get_api_key_for_display for ' . $provider);
        
        // First check for plain key (temporary storage)
        $plain_key = '';
        if ($provider === 'google_ai') {
            $plain_key = $settings['google_api_key'] ?? '';
        } else {
            $plain_key = $settings[$provider . '_api_key'] ?? '';
        }
        
        // Then check for encrypted key
        $encrypted_key = $settings[$provider . '_api_key_encrypted'] ?? '';
        
        // If we have a plain key, show masked version
        if (!empty($plain_key) && strpos($plain_key, '*') === false) {
            error_log('WP Content Flow: Found plain key for ' . $provider);
            // Show masked version
            if (strlen($plain_key) > 8) {
                return substr($plain_key, 0, 4) . str_repeat('*', 20) . substr($plain_key, -4);
            } else {
                return str_repeat('*', strlen($plain_key));
            }
        }
        
        // If we have an encrypted key, decrypt and show masked version
        if (!empty($encrypted_key)) {
            error_log('WP Content Flow: Found encrypted key for ' . $provider);
            $decrypted = $this->decrypt_api_key($encrypted_key);
            if (!empty($decrypted)) {
                error_log('WP Content Flow: Successfully decrypted key for ' . $provider);
                // Show first 4 and last 4 characters with asterisks in between
                if (strlen($decrypted) > 8) {
                    return substr($decrypted, 0, 4) . str_repeat('*', 20) . substr($decrypted, -4);
                } else {
                    return str_repeat('*', strlen($decrypted));
                }
            } else {
                error_log('WP Content Flow: Failed to decrypt key for ' . $provider);
            }
        }
        
        error_log('WP Content Flow: No key found for ' . $provider);
        return '';
    }
    
    /**
     * Migrate plain text API key to encrypted storage
     */
    private function migrate_plain_key_to_encrypted($provider, $plain_key) {
        $settings = get_option($this->option_name, array());
        
        // Only migrate if we don't already have an encrypted version
        if (empty($settings[$provider . '_api_key_encrypted'])) {
            $encrypted_key = $this->encrypt_api_key($plain_key);
            if (!empty($encrypted_key)) {
                // Save encrypted version and remove plain text
                $settings[$provider . '_api_key_encrypted'] = $encrypted_key;
                unset($settings[$provider . '_api_key']);
                
                update_option($this->option_name, $settings);
                error_log("WP Content Flow: Migrated {$provider} API key to encrypted storage");
            }
        }
    }
    
    /**
     * Get decrypted API key for use by providers (public method)
     */
    public function get_decrypted_api_key($provider) {
        $settings = get_option($this->option_name, array());
        $encrypted_key = $settings[$provider . '_api_key_encrypted'] ?? '';
        $plain_key = $settings[$provider . '_api_key'] ?? '';
        
        if (!empty($encrypted_key)) {
            return $this->decrypt_api_key($encrypted_key);
        }
        
        return $plain_key;
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