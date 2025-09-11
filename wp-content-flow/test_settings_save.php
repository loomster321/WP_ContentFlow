<?php
/**
 * Settings Save Test
 * 
 * This script specifically tests the WordPress admin settings form functionality
 * to identify why settings are not being saved properly.
 * 
 * @package WP_Content_Flow
 */

// WordPress environment bootstrap
if ( ! defined( 'ABSPATH' ) ) {
    if ( file_exists( dirname( __FILE__ ) . '/../../../wp-config.php' ) ) {
        require_once( dirname( __FILE__ ) . '/../../../wp-config.php' );
    } else {
        die( 'WordPress environment not found.' );
    }
}

class WP_Content_Flow_Settings_Test {
    
    private $option_name = 'wp_content_flow_settings';
    private $settings_group = 'wp_content_flow_settings_group';
    
    public function __construct() {
        echo "<style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 20px; }
        .test-section { background: #f9f9f9; padding: 15px; margin: 10px 0; border-left: 4px solid #2271b1; }
        .success { color: #007017; background: #f0f8f0; border-left-color: #00a32a; }
        .warning { color: #8c4400; background: #fff8e5; border-left-color: #ffb900; }
        .error { color: #d63638; background: #f9f0f0; border-left-color: #d63638; }
        .code { background: #f1f1f1; padding: 10px; font-family: Monaco, Consolas, monospace; border-radius: 4px; white-space: pre-wrap; }
        </style>";
        
        echo "<h1>WP Content Flow - Settings Save Test</h1>";
        
        $this->run_tests();
    }
    
    private function run_tests() {
        $this->test_current_settings();
        $this->test_settings_registration_status();
        $this->test_settings_form_simulation();
        $this->test_options_php_capability();
        $this->test_wordpress_settings_api();
        $this->provide_debugging_info();
    }
    
    /**
     * Test current settings in database
     */
    private function test_current_settings() {
        echo "<div class='test-section'><h2>1. Current Settings in Database</h2>";
        
        $current_settings = get_option( $this->option_name, 'NOT_FOUND' );
        
        if ( $current_settings === 'NOT_FOUND' ) {
            echo "<p class='warning'>⚠ No settings found in database (never been saved)</p>";
        } elseif ( empty( $current_settings ) ) {
            echo "<p class='warning'>⚠ Settings exist but are empty</p>";
        } else {
            echo "<p class='success'>✓ Settings found in database:</p>";
            echo "<div class='code'>" . print_r( $current_settings, true ) . "</div>";
            
            // Check for API keys (without revealing them)
            $api_keys = array( 'openai_api_key', 'anthropic_api_key', 'google_api_key' );
            foreach ( $api_keys as $key ) {
                if ( isset( $current_settings[$key] ) && ! empty( $current_settings[$key] ) ) {
                    echo "<p class='success'>✓ {$key} is configured</p>";
                } else {
                    echo "<p class='warning'>⚠ {$key} is not configured</p>";
                }
            }
        }
        
        echo "</div>";
    }
    
    /**
     * Test settings registration status
     */
    private function test_settings_registration_status() {
        echo "<div class='test-section'><h2>2. Settings Registration Status</h2>";
        
        global $allowed_options;
        
        // Check if our settings group is in allowed_options
        if ( isset( $allowed_options[$this->settings_group] ) ) {
            echo "<p class='success'>✓ Settings group '{$this->settings_group}' is registered</p>";
            
            if ( in_array( $this->option_name, $allowed_options[$this->settings_group] ) ) {
                echo "<p class='success'>✓ Option '{$this->option_name}' is in allowed list</p>";
            } else {
                echo "<p class='error'>✗ Option '{$this->option_name}' is NOT in allowed list</p>";
                echo "<p>Allowed options for group: " . implode( ', ', $allowed_options[$this->settings_group] ) . "</p>";
            }
        } else {
            echo "<p class='error'>✗ Settings group '{$this->settings_group}' is NOT registered</p>";
            echo "<p>Available groups: " . implode( ', ', array_keys( $allowed_options ) ) . "</p>";
            
            // Try to manually register for testing
            echo "<p>Attempting manual registration...</p>";
            $this->manual_register_settings();
            
            // Check again
            if ( isset( $allowed_options[$this->settings_group] ) ) {
                echo "<p class='success'>✓ Manual registration successful</p>";
            } else {
                echo "<p class='error'>✗ Manual registration failed</p>";
            }
        }
        
        echo "</div>";
    }
    
    /**
     * Manually register settings for testing
     */
    private function manual_register_settings() {
        global $allowed_options;
        
        // Force add to allowed options
        if ( ! isset( $allowed_options[$this->settings_group] ) ) {
            $allowed_options[$this->settings_group] = array();
        }
        if ( ! in_array( $this->option_name, $allowed_options[$this->settings_group] ) ) {
            $allowed_options[$this->settings_group][] = $this->option_name;
        }
        
        // Register with WordPress Settings API
        if ( function_exists( 'register_setting' ) ) {
            register_setting( $this->settings_group, $this->option_name, array( $this, 'sanitize_test_settings' ) );
        }
    }
    
    /**
     * Test settings form simulation
     */
    private function test_settings_form_simulation() {
        echo "<div class='test-section'><h2>3. Settings Form Simulation</h2>";
        
        // Simulate form submission data
        $test_data = array(
            $this->option_name => array(
                'openai_api_key' => 'test_openai_key_123',
                'anthropic_api_key' => 'test_anthropic_key_456',
                'google_api_key' => 'test_google_key_789',
                'default_ai_provider' => 'openai',
                'cache_enabled' => true,
                'requests_per_minute' => 20
            ),
            'option_page' => $this->settings_group,
            'action' => 'update',
            '_wpnonce' => wp_create_nonce( $this->settings_group . '-options' )
        );
        
        echo "<p>Simulating form submission with test data...</p>";
        
        // Backup current settings
        $original_settings = get_option( $this->option_name );
        echo "<p>Original settings backed up</p>";
        
        // Test 1: Direct update_option
        echo "<h3>Test 1: Direct update_option()</h3>";
        $direct_result = update_option( $this->option_name . '_direct_test', $test_data[$this->option_name] );
        if ( $direct_result ) {
            echo "<p class='success'>✓ Direct update_option() works</p>";
            
            $retrieved = get_option( $this->option_name . '_direct_test' );
            if ( $retrieved === $test_data[$this->option_name] ) {
                echo "<p class='success'>✓ Direct retrieval matches saved data</p>";
            } else {
                echo "<p class='error'>✗ Direct retrieval doesn't match</p>";
                echo "<div class='code'>Expected: " . print_r( $test_data[$this->option_name], true ) . "</div>";
                echo "<div class='code'>Retrieved: " . print_r( $retrieved, true ) . "</div>";
            }
            delete_option( $this->option_name . '_direct_test' );
        } else {
            echo "<p class='error'>✗ Direct update_option() failed</p>";
        }
        
        // Test 2: WordPress Settings API simulation
        echo "<h3>Test 2: WordPress Settings API Simulation</h3>";
        
        // Ensure settings are registered
        $this->manual_register_settings();
        
        // Simulate options.php processing
        $_POST = $test_data;
        $_REQUEST = $test_data;
        
        // Check if we can process this like options.php would
        if ( function_exists( 'settings_fields' ) && function_exists( 'check_admin_referer' ) ) {
            try {
                // Simulate the security check that options.php does
                $option_page = $test_data['option_page'];
                $capability = apply_filters( "option_page_capability_{$option_page}", 'manage_options' );
                
                if ( current_user_can( $capability ) ) {
                    echo "<p class='success'>✓ User has required capability: {$capability}</p>";
                    
                    // Simulate settings processing
                    global $allowed_options;
                    if ( isset( $allowed_options[$option_page] ) ) {
                        echo "<p class='success'>✓ Settings group found in allowed_options</p>";
                        
                        foreach ( $allowed_options[$option_page] as $option ) {
                            if ( isset( $test_data[$option] ) ) {
                                $value = $test_data[$option];
                                
                                // Apply sanitization if callback exists
                                $sanitize_callback = null;
                                if ( has_filter( "sanitize_option_{$option}" ) ) {
                                    $value = apply_filters( "sanitize_option_{$option}", $value, $option );
                                    echo "<p class='success'>✓ Sanitization applied to {$option}</p>";
                                }
                                
                                $update_result = update_option( $option, $value );
                                if ( $update_result ) {
                                    echo "<p class='success'>✓ Successfully updated {$option}</p>";
                                } else {
                                    echo "<p class='warning'>⚠ update_option() returned false for {$option} (might be unchanged)</p>";
                                }
                            }
                        }
                    } else {
                        echo "<p class='error'>✗ Settings group '{$option_page}' not in allowed_options</p>";
                    }
                } else {
                    echo "<p class='error'>✗ User lacks required capability: {$capability}</p>";
                }
                
            } catch ( Exception $e ) {
                echo "<p class='error'>✗ Exception during settings API simulation: " . $e->getMessage() . "</p>";
            }
        }
        
        // Clean up
        unset( $_POST );
        unset( $_REQUEST );
        
        echo "</div>";
    }
    
    /**
     * Test options.php capability
     */
    private function test_options_php_capability() {
        echo "<div class='test-section'><h2>4. options.php Access Test</h2>";
        
        $options_url = admin_url( 'options.php' );
        echo "<p>Options URL: {$options_url}</p>";
        
        // Check user capabilities
        $required_caps = array( 'manage_options' );
        foreach ( $required_caps as $cap ) {
            if ( current_user_can( $cap ) ) {
                echo "<p class='success'>✓ User has '{$cap}' capability</p>";
            } else {
                echo "<p class='error'>✗ User lacks '{$cap}' capability</p>";
            }
        }
        
        // Test option page capability filter
        $capability = apply_filters( "option_page_capability_{$this->settings_group}", 'manage_options' );
        echo "<p>Required capability for settings group: {$capability}</p>";
        
        if ( current_user_can( $capability ) ) {
            echo "<p class='success'>✓ User can access settings page</p>";
        } else {
            echo "<p class='error'>✗ User cannot access settings page</p>";
        }
        
        echo "</div>";
    }
    
    /**
     * Test WordPress Settings API functions
     */
    private function test_wordpress_settings_api() {
        echo "<div class='test-section'><h2>5. WordPress Settings API Functions</h2>";
        
        $functions = array(
            'register_setting',
            'add_settings_section',
            'add_settings_field',
            'settings_fields',
            'do_settings_sections',
            'get_option',
            'update_option',
            'delete_option'
        );
        
        foreach ( $functions as $function ) {
            if ( function_exists( $function ) ) {
                echo "<p class='success'>✓ {$function}() available</p>";
            } else {
                echo "<p class='error'>✗ {$function}() NOT available</p>";
            }
        }
        
        // Test settings sections and fields
        global $wp_settings_sections, $wp_settings_fields;
        
        if ( isset( $wp_settings_sections['wp-content-flow'] ) ) {
            $section_count = count( $wp_settings_sections['wp-content-flow'] );
            echo "<p class='success'>✓ {$section_count} settings sections registered</p>";
        } else {
            echo "<p class='error'>✗ No settings sections found</p>";
        }
        
        if ( isset( $wp_settings_fields['wp-content-flow'] ) ) {
            $field_count = 0;
            foreach ( $wp_settings_fields['wp-content-flow'] as $section => $fields ) {
                $field_count += count( $fields );
            }
            echo "<p class='success'>✓ {$field_count} settings fields registered</p>";
        } else {
            echo "<p class='error'>✗ No settings fields found</p>";
        }
        
        echo "</div>";
    }
    
    /**
     * Provide debugging information
     */
    private function provide_debugging_info() {
        echo "<div class='test-section'><h2>6. Debugging Information</h2>";
        
        echo "<h3>WordPress Environment:</h3>";
        echo "<p><strong>WordPress Version:</strong> " . get_bloginfo( 'version' ) . "</p>";
        echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";
        echo "<p><strong>Is Admin:</strong> " . ( is_admin() ? 'Yes' : 'No' ) . "</p>";
        echo "<p><strong>Is Multisite:</strong> " . ( is_multisite() ? 'Yes' : 'No' ) . "</p>";
        echo "<p><strong>Debug Mode:</strong> " . ( WP_DEBUG ? 'Enabled' : 'Disabled' ) . "</p>";
        
        echo "<h3>Current User:</h3>";
        $user = wp_get_current_user();
        echo "<p><strong>User ID:</strong> {$user->ID}</p>";
        echo "<p><strong>Username:</strong> {$user->user_login}</p>";
        echo "<p><strong>Display Name:</strong> {$user->display_name}</p>";
        echo "<p><strong>Roles:</strong> " . implode( ', ', $user->roles ) . "</p>";
        
        echo "<h3>Plugin Information:</h3>";
        if ( defined( 'WP_CONTENT_FLOW_VERSION' ) ) {
            echo "<p><strong>Plugin Version:</strong> " . WP_CONTENT_FLOW_VERSION . "</p>";
        }
        if ( defined( 'WP_CONTENT_FLOW_PLUGIN_DIR' ) ) {
            echo "<p><strong>Plugin Directory:</strong> " . WP_CONTENT_FLOW_PLUGIN_DIR . "</p>";
        }
        
        echo "<h3>Settings Registration Debug:</h3>";
        global $allowed_options;
        
        echo "<p><strong>All Allowed Options Groups:</strong></p>";
        echo "<div class='code'>" . print_r( array_keys( $allowed_options ), true ) . "</div>";
        
        if ( isset( $allowed_options[$this->settings_group] ) ) {
            echo "<p><strong>Our Settings Group Options:</strong></p>";
            echo "<div class='code'>" . print_r( $allowed_options[$this->settings_group], true ) . "</div>";
        }
        
        echo "<h3>Settings Class Status:</h3>";
        if ( class_exists( 'WP_Content_Flow_Settings_Page' ) ) {
            echo "<p class='success'>✓ WP_Content_Flow_Settings_Page class exists</p>";
            
            try {
                $reflection = new ReflectionClass( 'WP_Content_Flow_Settings_Page' );
                echo "<p><strong>Class File:</strong> " . $reflection->getFileName() . "</p>";
                echo "<p><strong>Class Methods:</strong> " . implode( ', ', $reflection->getMethods() ) . "</p>";
            } catch ( Exception $e ) {
                echo "<p class='error'>Error reflecting class: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p class='error'>✗ WP_Content_Flow_Settings_Page class NOT found</p>";
        }
        
        echo "<h3>Form Submission Simulation:</h3>";
        $form_action = admin_url( 'options.php' );
        $nonce_field = wp_nonce_field( $this->settings_group . '-options', '_wpnonce', true, false );
        
        echo "<p><strong>Form Action:</strong> {$form_action}</p>";
        echo "<p><strong>Nonce Field:</strong></p>";
        echo "<div class='code'>" . esc_html( $nonce_field ) . "</div>";
        
        echo "</div>";
    }
    
    /**
     * Sanitize test settings
     */
    public function sanitize_test_settings( $input ) {
        $sanitized = array();
        
        if ( isset( $input['openai_api_key'] ) ) {
            $sanitized['openai_api_key'] = sanitize_text_field( $input['openai_api_key'] );
        }
        
        if ( isset( $input['anthropic_api_key'] ) ) {
            $sanitized['anthropic_api_key'] = sanitize_text_field( $input['anthropic_api_key'] );
        }
        
        if ( isset( $input['google_api_key'] ) ) {
            $sanitized['google_api_key'] = sanitize_text_field( $input['google_api_key'] );
        }
        
        if ( isset( $input['default_ai_provider'] ) ) {
            $allowed_providers = array( 'openai', 'anthropic', 'google' );
            $sanitized['default_ai_provider'] = in_array( $input['default_ai_provider'], $allowed_providers ) 
                ? $input['default_ai_provider'] : 'openai';
        }
        
        $sanitized['cache_enabled'] = isset( $input['cache_enabled'] ) ? true : false;
        
        if ( isset( $input['requests_per_minute'] ) ) {
            $sanitized['requests_per_minute'] = absint( $input['requests_per_minute'] );
            if ( $sanitized['requests_per_minute'] < 1 ) {
                $sanitized['requests_per_minute'] = 10;
            }
        }
        
        return $sanitized;
    }
}

// Run the test
new WP_Content_Flow_Settings_Test();