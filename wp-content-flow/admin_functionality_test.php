<?php
/**
 * WordPress Admin Functionality Test Script
 * 
 * This script performs comprehensive testing of WP Content Flow plugin admin functionality
 * Run this script by placing it in your WordPress root directory and accessing it via browser
 * or run via WP-CLI: wp eval-file admin_functionality_test.php
 * 
 * @package WP_Content_Flow
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    // If not in WordPress context, try to load WordPress
    if ( file_exists( dirname( __FILE__ ) . '/../../../wp-config.php' ) ) {
        require_once( dirname( __FILE__ ) . '/../../../wp-config.php' );
    } else {
        die( 'WordPress environment not found. Please run this script from WordPress root or plugin directory.' );
    }
}

class WP_Content_Flow_Admin_Test {
    
    private $results = array();
    private $errors = array();
    
    public function __construct() {
        // Set up error handling
        set_error_handler( array( $this, 'error_handler' ) );
        
        echo "<style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 20px; }
        .test-section { background: #f9f9f9; padding: 15px; margin: 10px 0; border-left: 4px solid #2271b1; }
        .success { color: #007017; background: #f0f8f0; border-left-color: #00a32a; }
        .warning { color: #8c4400; background: #fff8e5; border-left-color: #ffb900; }
        .error { color: #d63638; background: #f9f0f0; border-left-color: #d63638; }
        .code { background: #f1f1f1; padding: 10px; font-family: Monaco, Consolas, monospace; border-radius: 4px; }
        .result-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .result-table th, .result-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .result-table th { background: #2271b1; color: white; }
        .status-ok { color: #007017; font-weight: bold; }
        .status-warning { color: #8c4400; font-weight: bold; }
        .status-error { color: #d63638; font-weight: bold; }
        </style>";
        
        echo "<h1>WP Content Flow Plugin - Admin Functionality Test</h1>";
        echo "<p>Testing Date: " . date( 'Y-m-d H:i:s' ) . "</p>";
        
        $this->run_all_tests();
        $this->display_summary();
    }
    
    /**
     * Error handler for capturing PHP errors
     */
    public function error_handler( $errno, $errstr, $errfile, $errline ) {
        $this->errors[] = "PHP Error [{$errno}]: {$errstr} in {$errfile} on line {$errline}";
        return false; // Let PHP handle the error normally
    }
    
    /**
     * Run all admin functionality tests
     */
    private function run_all_tests() {
        $this->test_plugin_activation_status();
        $this->test_admin_menu_registration();
        $this->test_settings_registration();
        $this->test_database_tables();
        $this->test_plugin_constants();
        $this->test_class_autoloading();
        $this->test_settings_page_access();
        $this->test_api_key_storage();
        $this->test_rest_api_endpoints();
        $this->test_user_capabilities();
        $this->test_admin_assets();
        $this->test_WordPress_hooks();
    }
    
    /**
     * Test if plugin is properly activated
     */
    private function test_plugin_activation_status() {
        echo "<div class='test-section'><h2>1. Plugin Activation Status</h2>";
        
        $plugin_file = 'wp-content-flow/wp-content-flow.php';
        $active_plugins = get_option( 'active_plugins', array() );
        $is_active = in_array( $plugin_file, $active_plugins );
        
        $this->results['plugin_active'] = $is_active;
        
        if ( $is_active ) {
            echo "<p class='success'>✓ Plugin is ACTIVE</p>";
        } else {
            echo "<p class='error'>✗ Plugin is NOT ACTIVE</p>";
            echo "<p>Active plugins: " . implode( ', ', $active_plugins ) . "</p>";
        }
        
        // Check plugin constants
        $constants = array( 'WP_CONTENT_FLOW_VERSION', 'WP_CONTENT_FLOW_PLUGIN_FILE', 'WP_CONTENT_FLOW_PLUGIN_DIR' );
        foreach ( $constants as $constant ) {
            if ( defined( $constant ) ) {
                echo "<p class='success'>✓ Constant {$constant} is defined: " . constant( $constant ) . "</p>";
            } else {
                echo "<p class='error'>✗ Constant {$constant} is NOT defined</p>";
            }
        }
        
        echo "</div>";
    }
    
    /**
     * Test admin menu registration
     */
    private function test_admin_menu_registration() {
        echo "<div class='test-section'><h2>2. Admin Menu Registration</h2>";
        
        global $menu, $submenu;
        
        $main_menu_found = false;
        $settings_submenu_found = false;
        
        // Check main menu
        if ( is_array( $menu ) ) {
            foreach ( $menu as $menu_item ) {
                if ( isset( $menu_item[2] ) && $menu_item[2] === 'wp-content-flow' ) {
                    $main_menu_found = true;
                    echo "<p class='success'>✓ Main menu 'Content Flow' found</p>";
                    echo "<p>Menu details: " . print_r( $menu_item, true ) . "</p>";
                    break;
                }
            }
        }
        
        if ( ! $main_menu_found ) {
            echo "<p class='error'>✗ Main menu 'Content Flow' NOT found</p>";
        }
        
        // Check submenu
        if ( isset( $submenu['wp-content-flow'] ) ) {
            $settings_submenu_found = true;
            echo "<p class='success'>✓ Settings submenu found</p>";
            echo "<p>Submenu items: " . print_r( $submenu['wp-content-flow'], true ) . "</p>";
        } else {
            echo "<p class='error'>✗ Settings submenu NOT found</p>";
        }
        
        // Test admin menu class instantiation
        if ( class_exists( 'WP_Content_Flow_Admin_Menu' ) ) {
            echo "<p class='success'>✓ WP_Content_Flow_Admin_Menu class exists</p>";
            
            try {
                $admin_menu = WP_Content_Flow_Admin_Menu::get_instance();
                if ( $admin_menu ) {
                    echo "<p class='success'>✓ Admin menu instance created successfully</p>";
                }
            } catch ( Exception $e ) {
                echo "<p class='error'>✗ Error creating admin menu instance: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p class='error'>✗ WP_Content_Flow_Admin_Menu class NOT found</p>";
        }
        
        $this->results['main_menu_registered'] = $main_menu_found;
        $this->results['settings_submenu_registered'] = $settings_submenu_found;
        
        echo "</div>";
    }
    
    /**
     * Test settings registration
     */
    private function test_settings_registration() {
        echo "<div class='test-section'><h2>3. Settings Registration</h2>";
        
        global $allowed_options, $wp_settings_sections, $wp_settings_fields;
        
        $settings_group = 'wp_content_flow_settings_group';
        $option_name = 'wp_content_flow_settings';
        
        // Check allowed options
        $in_allowed_options = isset( $allowed_options[$settings_group] ) && 
                             in_array( $option_name, $allowed_options[$settings_group] );
        
        if ( $in_allowed_options ) {
            echo "<p class='success'>✓ Settings group '{$settings_group}' is registered in allowed_options</p>";
        } else {
            echo "<p class='error'>✗ Settings group '{$settings_group}' NOT found in allowed_options</p>";
            echo "<p>Available groups: " . implode( ', ', array_keys( $allowed_options ) ) . "</p>";
        }
        
        // Check settings sections
        $sections_exist = isset( $wp_settings_sections['wp-content-flow'] );
        if ( $sections_exist ) {
            $section_count = count( $wp_settings_sections['wp-content-flow'] );
            echo "<p class='success'>✓ Found {$section_count} settings sections</p>";
            foreach ( $wp_settings_sections['wp-content-flow'] as $section_id => $section ) {
                echo "<p>   - Section: {$section_id} - {$section['title']}</p>";
            }
        } else {
            echo "<p class='error'>✗ No settings sections found</p>";
        }
        
        // Check settings fields
        $fields_exist = isset( $wp_settings_fields['wp-content-flow'] );
        if ( $fields_exist ) {
            $field_count = 0;
            foreach ( $wp_settings_fields['wp-content-flow'] as $section => $fields ) {
                $field_count += count( $fields );
                echo "<p class='success'>✓ Section '{$section}' has " . count( $fields ) . " fields</p>";
                foreach ( $fields as $field_id => $field ) {
                    echo "<p>   - Field: {$field_id} - {$field['title']}</p>";
                }
            }
            echo "<p class='success'>✓ Total settings fields: {$field_count}</p>";
        } else {
            echo "<p class='error'>✗ No settings fields found</p>";
        }
        
        // Test Settings Page class
        if ( class_exists( 'WP_Content_Flow_Settings_Page' ) ) {
            echo "<p class='success'>✓ WP_Content_Flow_Settings_Page class exists</p>";
            
            try {
                $settings_page = new WP_Content_Flow_Settings_Page();
                echo "<p class='success'>✓ Settings page instance created successfully</p>";
            } catch ( Exception $e ) {
                echo "<p class='error'>✗ Error creating settings page instance: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p class='error'>✗ WP_Content_Flow_Settings_Page class NOT found</p>";
        }
        
        $this->results['settings_registered'] = $in_allowed_options;
        $this->results['settings_sections_exist'] = $sections_exist;
        $this->results['settings_fields_exist'] = $fields_exist;
        
        echo "</div>";
    }
    
    /**
     * Test database table creation
     */
    private function test_database_tables() {
        echo "<div class='test-section'><h2>4. Database Tables</h2>";
        
        global $wpdb;
        
        $expected_tables = array(
            'wp_content_flow_workflows',
            'wp_content_flow_suggestions', 
            'wp_content_flow_history'
        );
        
        $tables_exist = array();
        
        foreach ( $expected_tables as $table ) {
            $table_name = $wpdb->prefix . str_replace( 'wp_', '', $table );
            $result = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );
            
            $exists = ( $result === $table_name );
            $tables_exist[$table] = $exists;
            
            if ( $exists ) {
                echo "<p class='success'>✓ Table '{$table_name}' exists</p>";
                
                // Check table structure
                $columns = $wpdb->get_results( "DESCRIBE {$table_name}" );
                echo "<p>   Columns: " . count( $columns ) . "</p>";
                
                // Check if table has data
                $row_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
                echo "<p>   Row count: {$row_count}</p>";
            } else {
                echo "<p class='error'>✗ Table '{$table_name}' does NOT exist</p>";
            }
        }
        
        $this->results['database_tables'] = $tables_exist;
        
        // Check database version option
        $db_version = get_option( 'wp_content_flow_db_version' );
        if ( $db_version ) {
            echo "<p class='success'>✓ Database version: {$db_version}</p>";
        } else {
            echo "<p class='warning'>⚠ Database version option not set</p>";
        }
        
        echo "</div>";
    }
    
    /**
     * Test plugin constants
     */
    private function test_plugin_constants() {
        echo "<div class='test-section'><h2>5. Plugin Constants</h2>";
        
        $required_constants = array(
            'WP_CONTENT_FLOW_VERSION',
            'WP_CONTENT_FLOW_PLUGIN_FILE',
            'WP_CONTENT_FLOW_PLUGIN_DIR',
            'WP_CONTENT_FLOW_PLUGIN_URL',
            'WP_CONTENT_FLOW_PLUGIN_BASENAME'
        );
        
        foreach ( $required_constants as $constant ) {
            if ( defined( $constant ) ) {
                $value = constant( $constant );
                echo "<p class='success'>✓ {$constant}: {$value}</p>";
                
                // Validate path constants
                if ( strpos( $constant, 'DIR' ) !== false || strpos( $constant, 'FILE' ) !== false ) {
                    if ( file_exists( $value ) ) {
                        echo "<p class='success'>   ✓ Path exists</p>";
                    } else {
                        echo "<p class='error'>   ✗ Path does NOT exist</p>";
                    }
                }
                
                $this->results["constant_{$constant}"] = true;
            } else {
                echo "<p class='error'>✗ {$constant} is NOT defined</p>";
                $this->results["constant_{$constant}"] = false;
            }
        }
        
        echo "</div>";
    }
    
    /**
     * Test class autoloading
     */
    private function test_class_autoloading() {
        echo "<div class='test-section'><h2>6. Class Autoloading</h2>";
        
        $expected_classes = array(
            'WP_Content_Flow',
            'WP_Content_Flow_Admin_Menu',
            'WP_Content_Flow_Settings_Page'
        );
        
        foreach ( $expected_classes as $class_name ) {
            if ( class_exists( $class_name ) ) {
                echo "<p class='success'>✓ Class '{$class_name}' is loaded</p>";
                
                // Get class file location
                $reflection = new ReflectionClass( $class_name );
                $file = $reflection->getFileName();
                echo "<p>   File: {$file}</p>";
                
                $this->results["class_{$class_name}"] = true;
            } else {
                echo "<p class='error'>✗ Class '{$class_name}' is NOT loaded</p>";
                $this->results["class_{$class_name}"] = false;
            }
        }
        
        echo "</div>";
    }
    
    /**
     * Test settings page access
     */
    private function test_settings_page_access() {
        echo "<div class='test-section'><h2>7. Settings Page Access</h2>";
        
        $settings_url = admin_url( 'admin.php?page=wp-content-flow-settings' );
        echo "<p>Settings URL: <a href='{$settings_url}' target='_blank'>{$settings_url}</a></p>";
        
        // Check if current user can access settings
        if ( current_user_can( 'manage_options' ) ) {
            echo "<p class='success'>✓ Current user has 'manage_options' capability</p>";
        } else {
            echo "<p class='warning'>⚠ Current user lacks 'manage_options' capability</p>";
        }
        
        // Test settings page rendering (capture output)
        if ( class_exists( 'WP_Content_Flow_Settings_Page' ) ) {
            try {
                ob_start();
                $settings_page = new WP_Content_Flow_Settings_Page();
                $settings_page->render();
                $output = ob_get_clean();
                
                if ( ! empty( $output ) ) {
                    echo "<p class='success'>✓ Settings page renders output (" . strlen( $output ) . " chars)</p>";
                } else {
                    echo "<p class='warning'>⚠ Settings page renders no output</p>";
                }
            } catch ( Exception $e ) {
                echo "<p class='error'>✗ Error rendering settings page: " . $e->getMessage() . "</p>";
            }
        }
        
        echo "</div>";
    }
    
    /**
     * Test API key storage and retrieval
     */
    private function test_api_key_storage() {
        echo "<div class='test-section'><h2>8. API Key Storage</h2>";
        
        $option_name = 'wp_content_flow_settings';
        $current_settings = get_option( $option_name, array() );
        
        echo "<h3>Current Settings:</h3>";
        if ( ! empty( $current_settings ) ) {
            echo "<table class='result-table'>";
            echo "<tr><th>Setting</th><th>Value</th><th>Status</th></tr>";
            
            foreach ( $current_settings as $key => $value ) {
                $display_value = $value;
                $status = 'status-ok';
                
                // Don't display actual API keys for security
                if ( strpos( $key, 'api_key' ) !== false ) {
                    $display_value = empty( $value ) ? 'Not set' : '***CONFIGURED***';
                    $status = empty( $value ) ? 'status-warning' : 'status-ok';
                } elseif ( is_bool( $value ) ) {
                    $display_value = $value ? 'true' : 'false';
                }
                
                echo "<tr>";
                echo "<td><strong>{$key}</strong></td>";
                echo "<td>{$display_value}</td>";
                echo "<td class='{$status}'>" . ( empty( $value ) && $value !== false ? 'Empty' : 'Set' ) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='warning'>⚠ No settings found in database</p>";
        }
        
        // Test settings save functionality (simulation)
        echo "<h3>Test Settings Save:</h3>";
        $test_settings = array(
            'openai_api_key' => 'test_key_openai',
            'anthropic_api_key' => 'test_key_anthropic',
            'default_ai_provider' => 'openai',
            'cache_enabled' => true,
            'requests_per_minute' => 15
        );
        
        $save_result = update_option( $option_name . '_test', $test_settings );
        if ( $save_result ) {
            echo "<p class='success'>✓ Test settings save successful</p>";
            
            // Verify retrieval
            $retrieved = get_option( $option_name . '_test' );
            if ( $retrieved === $test_settings ) {
                echo "<p class='success'>✓ Test settings retrieval matches saved data</p>";
            } else {
                echo "<p class='error'>✗ Test settings retrieval doesn't match saved data</p>";
            }
            
            // Clean up test data
            delete_option( $option_name . '_test' );
        } else {
            echo "<p class='error'>✗ Test settings save failed</p>";
        }
        
        echo "</div>";
    }
    
    /**
     * Test REST API endpoints
     */
    private function test_rest_api_endpoints() {
        echo "<div class='test-section'><h2>9. REST API Endpoints</h2>";
        
        $rest_url = rest_url( 'wp-content-flow/v1/' );
        echo "<p>REST API Base URL: {$rest_url}</p>";
        
        // Check if REST API class exists
        if ( class_exists( 'WP_Content_Flow_REST_API' ) ) {
            echo "<p class='success'>✓ WP_Content_Flow_REST_API class exists</p>";
        } else {
            echo "<p class='error'>✗ WP_Content_Flow_REST_API class NOT found</p>";
        }
        
        // Check registered endpoints
        $server = rest_get_server();
        $endpoints = $server->get_routes();
        
        $our_endpoints = array();
        foreach ( $endpoints as $route => $handlers ) {
            if ( strpos( $route, '/wp-content-flow/v1' ) === 0 ) {
                $our_endpoints[] = $route;
            }
        }
        
        if ( ! empty( $our_endpoints ) ) {
            echo "<p class='success'>✓ Found " . count( $our_endpoints ) . " REST API endpoints:</p>";
            foreach ( $our_endpoints as $endpoint ) {
                echo "<p>   - {$endpoint}</p>";
            }
        } else {
            echo "<p class='error'>✗ No REST API endpoints found for wp-content-flow/v1</p>";
        }
        
        $this->results['rest_endpoints_count'] = count( $our_endpoints );
        
        echo "</div>";
    }
    
    /**
     * Test user capabilities
     */
    private function test_user_capabilities() {
        echo "<div class='test-section'><h2>10. User Capabilities</h2>";
        
        $required_caps = array( 'manage_options', 'edit_posts', 'publish_posts' );
        
        foreach ( $required_caps as $cap ) {
            if ( current_user_can( $cap ) ) {
                echo "<p class='success'>✓ Current user has '{$cap}' capability</p>";
            } else {
                echo "<p class='warning'>⚠ Current user lacks '{$cap}' capability</p>";
            }
        }
        
        // Get current user info
        $user = wp_get_current_user();
        echo "<p>Current User: {$user->display_name} (ID: {$user->ID})</p>";
        echo "<p>User Roles: " . implode( ', ', $user->roles ) . "</p>";
        
        echo "</div>";
    }
    
    /**
     * Test admin assets (JS/CSS)
     */
    private function test_admin_assets() {
        echo "<div class='test-section'><h2>11. Admin Assets</h2>";
        
        $asset_files = array(
            'assets/js/admin.js',
            'assets/js/blocks.js',
            'assets/css/editor.css',
            'assets/css/frontend.css'
        );
        
        foreach ( $asset_files as $asset ) {
            $file_path = WP_CONTENT_FLOW_PLUGIN_DIR . $asset;
            if ( file_exists( $file_path ) ) {
                $size = filesize( $file_path );
                echo "<p class='success'>✓ Asset '{$asset}' exists ({$size} bytes)</p>";
            } else {
                echo "<p class='warning'>⚠ Asset '{$asset}' does NOT exist</p>";
                echo "<p>   Expected path: {$file_path}</p>";
            }
        }
        
        // Check enqueued scripts/styles (this only works in admin context)
        global $wp_scripts, $wp_styles;
        
        $our_scripts = array();
        $our_styles = array();
        
        if ( $wp_scripts ) {
            foreach ( $wp_scripts->registered as $handle => $script ) {
                if ( strpos( $script->src, 'wp-content-flow' ) !== false ) {
                    $our_scripts[] = $handle;
                }
            }
        }
        
        if ( $wp_styles ) {
            foreach ( $wp_styles->registered as $handle => $style ) {
                if ( strpos( $style->src, 'wp-content-flow' ) !== false ) {
                    $our_styles[] = $handle;
                }
            }
        }
        
        echo "<p>Registered Scripts: " . ( empty( $our_scripts ) ? 'None' : implode( ', ', $our_scripts ) ) . "</p>";
        echo "<p>Registered Styles: " . ( empty( $our_styles ) ? 'None' : implode( ', ', $our_styles ) ) . "</p>";
        
        echo "</div>";
    }
    
    /**
     * Test WordPress hooks
     */
    private function test_WordPress_hooks() {
        echo "<div class='test-section'><h2>12. WordPress Hooks</h2>";
        
        global $wp_filter;
        
        $our_hooks = array();
        
        // Check for our plugin's hooks
        $hook_patterns = array( 'wp_content_flow', 'admin_menu', 'admin_init', 'init' );
        
        foreach ( $wp_filter as $tag => $callbacks ) {
            foreach ( $hook_patterns as $pattern ) {
                if ( strpos( $tag, $pattern ) !== false ) {
                    if ( ! isset( $our_hooks[$tag] ) ) {
                        $our_hooks[$tag] = array();
                    }
                    
                    // Count callbacks for this hook
                    $callback_count = 0;
                    foreach ( $callbacks as $priority => $functions ) {
                        $callback_count += count( $functions );
                    }
                    $our_hooks[$tag]['count'] = $callback_count;
                }
            }
        }
        
        if ( ! empty( $our_hooks ) ) {
            echo "<p class='success'>✓ Found plugin hooks:</p>";
            foreach ( $our_hooks as $hook => $info ) {
                echo "<p>   - {$hook}: {$info['count']} callbacks</p>";
            }
        } else {
            echo "<p class='warning'>⚠ No obvious plugin hooks found</p>";
        }
        
        echo "</div>";
    }
    
    /**
     * Display test summary
     */
    private function display_summary() {
        echo "<div class='test-section'><h2>Test Summary</h2>";
        
        $total_tests = count( $this->results );
        $passed_tests = count( array_filter( $this->results ) );
        $failed_tests = $total_tests - $passed_tests;
        
        $pass_percentage = $total_tests > 0 ? round( ( $passed_tests / $total_tests ) * 100, 1 ) : 0;
        
        echo "<table class='result-table'>";
        echo "<tr><th>Metric</th><th>Value</th></tr>";
        echo "<tr><td>Total Tests</td><td>{$total_tests}</td></tr>";
        echo "<tr><td>Passed Tests</td><td class='status-ok'>{$passed_tests}</td></tr>";
        echo "<tr><td>Failed Tests</td><td class='status-error'>{$failed_tests}</td></tr>";
        echo "<tr><td>Success Rate</td><td><strong>{$pass_percentage}%</strong></td></tr>";
        echo "</table>";
        
        if ( ! empty( $this->errors ) ) {
            echo "<h3>PHP Errors Encountered:</h3>";
            foreach ( $this->errors as $error ) {
                echo "<p class='error'>{$error}</p>";
            }
        }
        
        echo "<h3>Detailed Results:</h3>";
        echo "<table class='result-table'>";
        echo "<tr><th>Test</th><th>Result</th></tr>";
        
        foreach ( $this->results as $test => $result ) {
            $status = $result ? 'status-ok' : 'status-error';
            $symbol = $result ? '✓' : '✗';
            echo "<tr><td>{$test}</td><td class='{$status}'>{$symbol}</td></tr>";
        }
        
        echo "</table>";
        
        // Recommendations
        echo "<h3>Recommendations:</h3>";
        
        if ( ! $this->results['plugin_active'] ) {
            echo "<p class='error'><strong>CRITICAL:</strong> Plugin is not active. Activate it first.</p>";
        }
        
        if ( ! $this->results['main_menu_registered'] ) {
            echo "<p class='error'><strong>ISSUE:</strong> Admin menu not registered. Check admin_menu hook.</p>";
        }
        
        if ( ! $this->results['settings_registered'] ) {
            echo "<p class='error'><strong>ISSUE:</strong> Settings not properly registered with WordPress Settings API.</p>";
        }
        
        $table_issues = array_filter( $this->results['database_tables'], function( $exists ) {
            return ! $exists;
        } );
        
        if ( ! empty( $table_issues ) ) {
            echo "<p class='error'><strong>ISSUE:</strong> Some database tables are missing. Re-activate plugin or check database permissions.</p>";
        }
        
        if ( $this->results['rest_endpoints_count'] === 0 ) {
            echo "<p class='warning'><strong>WARNING:</strong> No REST API endpoints found. Blocks may not work.</p>";
        }
        
        echo "</div>";
        
        // Export results for further analysis
        echo "<div class='test-section'><h2>Export Results</h2>";
        echo "<h3>JSON Export:</h3>";
        echo "<div class='code'>" . json_encode( array(
            'timestamp' => time(),
            'results' => $this->results,
            'errors' => $this->errors,
            'summary' => array(
                'total' => $total_tests,
                'passed' => $passed_tests,
                'failed' => $failed_tests,
                'percentage' => $pass_percentage
            )
        ), JSON_PRETTY_PRINT ) . "</div>";
        echo "</div>";
    }
}

// Run the test
new WP_Content_Flow_Admin_Test();