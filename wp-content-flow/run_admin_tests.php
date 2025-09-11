<?php
/**
 * WordPress Admin Tests Runner
 * 
 * This script runs a comprehensive suite of admin functionality tests
 * and generates a complete report with recommendations.
 * 
 * Usage:
 * - Place this file in your WordPress root directory
 * - Access via browser: http://yoursite.com/run_admin_tests.php
 * - Or run via WP-CLI: wp eval-file run_admin_tests.php
 * 
 * @package WP_Content_Flow
 */

// WordPress environment bootstrap
if ( ! defined( 'ABSPATH' ) ) {
    if ( file_exists( dirname( __FILE__ ) . '/../../../wp-config.php' ) ) {
        require_once( dirname( __FILE__ ) . '/../../../wp-config.php' );
    } elseif ( file_exists( dirname( __FILE__ ) . '/../../../../wp-config.php' ) ) {
        require_once( dirname( __FILE__ ) . '/../../../../wp-config.php' );
    } else {
        die( 'WordPress environment not found. Please run this script from WordPress root or plugin directory.' );
    }
}

class WP_Content_Flow_Admin_Tests_Runner {
    
    private $start_time;
    private $test_results = array();
    private $critical_issues = array();
    private $warnings = array();
    private $recommendations = array();
    
    public function __construct() {
        $this->start_time = microtime( true );
        
        // Set up error capture
        set_error_handler( array( $this, 'capture_error' ) );
        
        $this->render_header();
        $this->run_complete_test_suite();
        $this->render_footer();
    }
    
    /**
     * Capture PHP errors
     */
    public function capture_error( $errno, $errstr, $errfile, $errline ) {
        $this->test_results['php_errors'][] = "PHP Error [{$errno}]: {$errstr} in {$errfile} on line {$errline}";
        return false;
    }
    
    /**
     * Render HTML header
     */
    private function render_header() {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>WP Content Flow - Admin Functionality Test Report</title>
            <style>
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
                    margin: 0; 
                    padding: 20px; 
                    background: #f6f7f7;
                    color: #23282d;
                    line-height: 1.6;
                }
                .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { text-align: center; border-bottom: 2px solid #2271b1; padding-bottom: 20px; margin-bottom: 30px; }
                .test-section { 
                    background: #f9f9f9; 
                    padding: 20px; 
                    margin: 20px 0; 
                    border-left: 4px solid #2271b1; 
                    border-radius: 4px;
                }
                .success { color: #007017; background: #f0f8f0; border-left-color: #00a32a; }
                .warning { color: #8c4400; background: #fff8e5; border-left-color: #ffb900; }
                .error { color: #d63638; background: #f9f0f0; border-left-color: #d63638; }
                .code { 
                    background: #f1f1f1; 
                    padding: 15px; 
                    font-family: Monaco, Consolas, 'Courier New', monospace; 
                    border-radius: 4px; 
                    white-space: pre-wrap;
                    font-size: 12px;
                    overflow-x: auto;
                }
                .result-table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin: 15px 0; 
                    background: white;
                }
                .result-table th, .result-table td { 
                    border: 1px solid #ddd; 
                    padding: 12px; 
                    text-align: left; 
                }
                .result-table th { 
                    background: #2271b1; 
                    color: white; 
                    font-weight: 600;
                }
                .result-table tr:nth-child(even) { background: #f9f9f9; }
                .status-ok { color: #007017; font-weight: bold; }
                .status-warning { color: #8c4400; font-weight: bold; }
                .status-error { color: #d63638; font-weight: bold; }
                .summary-grid { 
                    display: grid; 
                    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
                    gap: 20px; 
                    margin: 20px 0; 
                }
                .summary-card { 
                    background: white; 
                    padding: 20px; 
                    border-radius: 8px; 
                    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                    text-align: center;
                }
                .metric-value { font-size: 2em; font-weight: bold; margin-bottom: 10px; }
                .metric-label { color: #666; text-transform: uppercase; font-size: 0.9em; letter-spacing: 1px; }
                .progress-bar { 
                    width: 100%; 
                    height: 10px; 
                    background: #e5e5e5; 
                    border-radius: 5px; 
                    overflow: hidden; 
                    margin: 10px 0;
                }
                .progress-fill { 
                    height: 100%; 
                    background: linear-gradient(90deg, #00a32a 0%, #007017 100%); 
                    transition: width 0.3s ease;
                }
                .action-item { 
                    background: white; 
                    border-left: 4px solid #2271b1; 
                    padding: 15px; 
                    margin: 10px 0; 
                    border-radius: 4px;
                }
                .critical { border-left-color: #d63638; }
                .warning-item { border-left-color: #ffb900; }
                h1 { color: #2271b1; margin: 0; }
                h2 { color: #2271b1; border-bottom: 2px solid #e5e5e5; padding-bottom: 10px; }
                h3 { color: #23282d; margin-top: 25px; }
                .timestamp { color: #666; font-size: 0.9em; }
                .test-nav { 
                    position: sticky; 
                    top: 20px; 
                    background: white; 
                    padding: 15px; 
                    border-radius: 5px; 
                    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                    margin-bottom: 20px;
                }
                .test-nav ul { list-style: none; padding: 0; margin: 0; }
                .test-nav li { display: inline-block; margin-right: 15px; }
                .test-nav a { color: #2271b1; text-decoration: none; padding: 5px 10px; border-radius: 3px; }
                .test-nav a:hover { background: #f0f6fc; }
                .expandable { cursor: pointer; }
                .expandable:hover { background: #f0f6fc; }
                .collapsible { display: none; }
                .expanded .collapsible { display: block; }
            </style>
            <script>
                function toggleSection(element) {
                    element.classList.toggle('expanded');
                }
                
                function scrollToSection(sectionId) {
                    document.getElementById(sectionId).scrollIntoView({ behavior: 'smooth' });
                }
            </script>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>WP Content Flow Plugin</h1>
                    <h2>Admin Functionality Test Report</h2>
                    <p class="timestamp">Generated: <?php echo date( 'Y-m-d H:i:s T' ); ?></p>
                </div>
        <?php
    }
    
    /**
     * Run complete test suite
     */
    private function run_complete_test_suite() {
        echo "<div class='test-nav'>";
        echo "<strong>Quick Navigation:</strong> ";
        echo "<ul>";
        echo "<li><a href='#summary' onclick=\"scrollToSection('summary')\">Summary</a></li>";
        echo "<li><a href='#plugin-status' onclick=\"scrollToSection('plugin-status')\">Plugin Status</a></li>";
        echo "<li><a href='#admin-menu' onclick=\"scrollToSection('admin-menu')\">Admin Menu</a></li>";
        echo "<li><a href='#settings' onclick=\"scrollToSection('settings')\">Settings</a></li>";
        echo "<li><a href='#database' onclick=\"scrollToSection('database')\">Database</a></li>";
        echo "<li><a href='#rest-api' onclick=\"scrollToSection('rest-api')\">REST API</a></li>";
        echo "<li><a href='#recommendations' onclick=\"scrollToSection('recommendations')\">Recommendations</a></li>";
        echo "</ul>";
        echo "</div>";
        
        // Run all test categories
        $this->test_plugin_activation_status();
        $this->test_admin_menu_functionality();
        $this->test_settings_system();
        $this->test_database_operations();
        $this->test_rest_api_endpoints();
        $this->test_user_permissions();
        $this->test_file_structure();
        $this->test_wordpress_integration();
        
        // Generate summary and recommendations
        $this->generate_test_summary();
        $this->generate_recommendations();
    }
    
    /**
     * Test plugin activation status
     */
    private function test_plugin_activation_status() {
        echo "<div class='test-section' id='plugin-status'>";
        echo "<h2 onclick='toggleSection(this.parentElement)' class='expandable'>1. Plugin Activation Status 📊</h2>";
        echo "<div class='collapsible'>";
        
        $tests = array();
        
        // Check plugin activation
        $plugin_file = 'wp-content-flow/wp-content-flow.php';
        $active_plugins = get_option( 'active_plugins', array() );
        $is_active = in_array( $plugin_file, $active_plugins );
        
        $tests['plugin_active'] = $is_active;
        
        if ( $is_active ) {
            echo "<p class='success'>✅ Plugin is ACTIVE</p>";
        } else {
            echo "<p class='error'>❌ Plugin is NOT ACTIVE - This is the root cause of many issues!</p>";
            $this->critical_issues[] = "Plugin is not activated in WordPress admin";
        }
        
        // Check plugin constants
        $constants = array(
            'WP_CONTENT_FLOW_VERSION' => 'Plugin version',
            'WP_CONTENT_FLOW_PLUGIN_FILE' => 'Plugin file path',
            'WP_CONTENT_FLOW_PLUGIN_DIR' => 'Plugin directory path',
            'WP_CONTENT_FLOW_PLUGIN_URL' => 'Plugin URL',
            'WP_CONTENT_FLOW_PLUGIN_BASENAME' => 'Plugin basename'
        );
        
        echo "<h3>Plugin Constants:</h3>";
        echo "<table class='result-table'>";
        echo "<tr><th>Constant</th><th>Description</th><th>Value</th><th>Status</th></tr>";
        
        foreach ( $constants as $constant => $description ) {
            $defined = defined( $constant );
            $value = $defined ? constant( $constant ) : 'NOT DEFINED';
            $status = $defined ? 'status-ok' : 'status-error';
            
            $tests["constant_{$constant}"] = $defined;
            
            echo "<tr>";
            echo "<td><code>{$constant}</code></td>";
            echo "<td>{$description}</td>";
            echo "<td>" . esc_html( $value ) . "</td>";
            echo "<td class='{$status}'>" . ( $defined ? '✅' : '❌' ) . "</td>";
            echo "</tr>";
            
            if ( ! $defined ) {
                $this->critical_issues[] = "Plugin constant {$constant} is not defined";
            }
        }
        echo "</table>";
        
        // Check main plugin class
        if ( class_exists( 'WP_Content_Flow' ) ) {
            echo "<p class='success'>✅ Main plugin class 'WP_Content_Flow' exists</p>";
            $tests['main_class_exists'] = true;
        } else {
            echo "<p class='error'>❌ Main plugin class 'WP_Content_Flow' NOT found</p>";
            $tests['main_class_exists'] = false;
            $this->critical_issues[] = "Main plugin class WP_Content_Flow is not loaded";
        }
        
        $this->test_results['plugin_status'] = $tests;
        
        echo "</div></div>";
    }
    
    /**
     * Test admin menu functionality
     */
    private function test_admin_menu_functionality() {
        echo "<div class='test-section' id='admin-menu'>";
        echo "<h2 onclick='toggleSection(this.parentElement)' class='expandable'>2. Admin Menu Functionality 🔗</h2>";
        echo "<div class='collapsible'>";
        
        global $menu, $submenu;
        
        $tests = array();
        
        // Check for main menu
        $main_menu_found = false;
        if ( is_array( $menu ) ) {
            foreach ( $menu as $menu_item ) {
                if ( isset( $menu_item[2] ) && $menu_item[2] === 'wp-content-flow' ) {
                    $main_menu_found = true;
                    echo "<p class='success'>✅ Main menu 'Content Flow' is registered</p>";
                    echo "<p><strong>Menu Title:</strong> {$menu_item[0]}</p>";
                    echo "<p><strong>Capability:</strong> {$menu_item[1]}</p>";
                    echo "<p><strong>Menu Slug:</strong> {$menu_item[2]}</p>";
                    echo "<p><strong>Icon:</strong> {$menu_item[6]}</p>";
                    break;
                }
            }
        }
        
        if ( ! $main_menu_found ) {
            echo "<p class='error'>❌ Main menu 'Content Flow' NOT registered</p>";
            $this->critical_issues[] = "Admin menu is not registered - users cannot access plugin settings";
        }
        
        $tests['main_menu_registered'] = $main_menu_found;
        
        // Check submenu items
        $submenu_count = 0;
        if ( isset( $submenu['wp-content-flow'] ) && is_array( $submenu['wp-content-flow'] ) ) {
            $submenu_count = count( $submenu['wp-content-flow'] );
            echo "<p class='success'>✅ Found {$submenu_count} submenu items</p>";
            
            echo "<h3>Submenu Items:</h3>";
            echo "<table class='result-table'>";
            echo "<tr><th>Title</th><th>Capability</th><th>Slug</th></tr>";
            
            foreach ( $submenu['wp-content-flow'] as $submenu_item ) {
                echo "<tr>";
                echo "<td>{$submenu_item[0]}</td>";
                echo "<td>{$submenu_item[1]}</td>";
                echo "<td>{$submenu_item[2]}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='error'>❌ No submenu items found</p>";
            $this->warnings[] = "No submenu items found - limited navigation options";
        }
        
        $tests['submenu_items'] = $submenu_count;
        
        // Test admin menu class
        if ( class_exists( 'WP_Content_Flow_Admin_Menu' ) ) {
            echo "<p class='success'>✅ WP_Content_Flow_Admin_Menu class exists</p>";
            $tests['admin_menu_class'] = true;
        } else {
            echo "<p class='error'>❌ WP_Content_Flow_Admin_Menu class NOT found</p>";
            $tests['admin_menu_class'] = false;
            $this->critical_issues[] = "Admin menu class is missing";
        }
        
        $this->test_results['admin_menu'] = $tests;
        
        echo "</div></div>";
    }
    
    /**
     * Test settings system
     */
    private function test_settings_system() {
        echo "<div class='test-section' id='settings'>";
        echo "<h2 onclick='toggleSection(this.parentElement)' class='expandable'>3. Settings System 🔧</h2>";
        echo "<div class='collapsible'>";
        
        $tests = array();
        
        // Check settings registration
        global $allowed_options;
        $settings_group = 'wp_content_flow_settings_group';
        $option_name = 'wp_content_flow_settings';
        
        $settings_registered = isset( $allowed_options[$settings_group] ) && 
                              in_array( $option_name, $allowed_options[$settings_group] );
        
        if ( $settings_registered ) {
            echo "<p class='success'>✅ Settings are properly registered with WordPress Settings API</p>";
        } else {
            echo "<p class='error'>❌ Settings are NOT registered - This is why settings cannot be saved!</p>";
            $this->critical_issues[] = "Settings not registered with WordPress Settings API - settings form will not save";
        }
        
        $tests['settings_registered'] = $settings_registered;
        
        // Check current settings
        $current_settings = get_option( $option_name );
        if ( $current_settings !== false ) {
            echo "<p class='success'>✅ Settings exist in database</p>";
            
            echo "<h3>Current Settings:</h3>";
            echo "<table class='result-table'>";
            echo "<tr><th>Setting</th><th>Status</th><th>Value</th></tr>";
            
            $api_keys = array( 'openai_api_key', 'anthropic_api_key', 'google_api_key' );
            $configured_providers = 0;
            
            foreach ( $api_keys as $key ) {
                $is_set = isset( $current_settings[$key] ) && ! empty( $current_settings[$key] );
                if ( $is_set ) $configured_providers++;
                
                echo "<tr>";
                echo "<td><strong>" . ucwords( str_replace( '_', ' ', $key ) ) . "</strong></td>";
                echo "<td class='" . ( $is_set ? 'status-ok' : 'status-warning' ) . "'>";
                echo $is_set ? '✅ Configured' : '⚠️ Not Set';
                echo "</td>";
                echo "<td>" . ( $is_set ? '***HIDDEN***' : 'Empty' ) . "</td>";
                echo "</tr>";
            }
            
            // Other settings
            $other_settings = array(
                'default_ai_provider' => $current_settings['default_ai_provider'] ?? 'Not Set',
                'cache_enabled' => isset( $current_settings['cache_enabled'] ) ? ( $current_settings['cache_enabled'] ? 'Yes' : 'No' ) : 'Not Set',
                'requests_per_minute' => $current_settings['requests_per_minute'] ?? 'Not Set'
            );
            
            foreach ( $other_settings as $key => $value ) {
                echo "<tr>";
                echo "<td><strong>" . ucwords( str_replace( '_', ' ', $key ) ) . "</strong></td>";
                echo "<td class='status-ok'>✅ Set</td>";
                echo "<td>" . esc_html( $value ) . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
            
            echo "<p><strong>AI Providers Configured:</strong> {$configured_providers}/3</p>";
            
            if ( $configured_providers === 0 ) {
                $this->warnings[] = "No AI provider API keys are configured - plugin functionality will be limited";
            }
            
        } else {
            echo "<p class='warning'>⚠️ No settings found in database (never been saved)</p>";
            $this->warnings[] = "Settings have never been saved - plugin may not be configured";
        }
        
        $tests['settings_exist'] = ( $current_settings !== false );
        
        // Test settings page class
        if ( class_exists( 'WP_Content_Flow_Settings_Page' ) ) {
            echo "<p class='success'>✅ WP_Content_Flow_Settings_Page class exists</p>";
            $tests['settings_page_class'] = true;
        } else {
            echo "<p class='error'>❌ WP_Content_Flow_Settings_Page class NOT found</p>";
            $tests['settings_page_class'] = false;
            $this->critical_issues[] = "Settings page class is missing";
        }
        
        // Test settings sections and fields
        global $wp_settings_sections, $wp_settings_fields;
        
        $sections_exist = isset( $wp_settings_sections['wp-content-flow'] );
        $fields_exist = isset( $wp_settings_fields['wp-content-flow'] );
        
        if ( $sections_exist && $fields_exist ) {
            $section_count = count( $wp_settings_sections['wp-content-flow'] );
            $field_count = 0;
            foreach ( $wp_settings_fields['wp-content-flow'] as $section_fields ) {
                $field_count += count( $section_fields );
            }
            
            echo "<p class='success'>✅ Settings sections and fields registered ({$section_count} sections, {$field_count} fields)</p>";
        } else {
            echo "<p class='error'>❌ Settings sections/fields not properly registered</p>";
            $this->critical_issues[] = "Settings form fields are not registered - form will be empty";
        }
        
        $tests['settings_ui_registered'] = ( $sections_exist && $fields_exist );
        
        // Test a settings save simulation
        echo "<h3>Settings Save Test:</h3>";
        $test_settings = array(
            'openai_api_key' => 'test_key_simulation',
            'default_ai_provider' => 'openai',
            'cache_enabled' => true,
            'requests_per_minute' => 15
        );
        
        $test_option = $option_name . '_test_save';
        $save_result = update_option( $test_option, $test_settings );
        
        if ( $save_result ) {
            $retrieved = get_option( $test_option );
            if ( $retrieved === $test_settings ) {
                echo "<p class='success'>✅ Database save/retrieve test PASSED</p>";
                $tests['database_save_test'] = true;
            } else {
                echo "<p class='error'>❌ Database save/retrieve test FAILED - data corruption</p>";
                $tests['database_save_test'] = false;
                $this->critical_issues[] = "Database save/retrieve test failed - possible data corruption";
            }
            delete_option( $test_option );
        } else {
            echo "<p class='error'>❌ Database save test FAILED</p>";
            $tests['database_save_test'] = false;
            $this->critical_issues[] = "Cannot save settings to database";
        }
        
        $this->test_results['settings'] = $tests;
        
        echo "</div></div>";
    }
    
    /**
     * Test database operations
     */
    private function test_database_operations() {
        echo "<div class='test-section' id='database'>";
        echo "<h2 onclick='toggleSection(this.parentElement)' class='expandable'>4. Database Operations 🗃️</h2>";
        echo "<div class='collapsible'>";
        
        global $wpdb;
        
        $tests = array();
        
        // Check expected tables
        $expected_tables = array(
            'wp_content_flow_workflows' => 'ai_workflows',
            'wp_content_flow_suggestions' => 'ai_suggestions',
            'wp_content_flow_history' => 'ai_content_history'
        );
        
        echo "<h3>Database Tables:</h3>";
        echo "<table class='result-table'>";
        echo "<tr><th>Expected Table</th><th>Actual Table</th><th>Exists</th><th>Columns</th><th>Rows</th></tr>";
        
        foreach ( $expected_tables as $expected => $actual ) {
            $table_name = $wpdb->prefix . $actual;
            $exists = ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name );
            
            $tests["table_{$actual}"] = $exists;
            
            if ( $exists ) {
                $columns = $wpdb->get_results( "DESCRIBE {$table_name}" );
                $column_count = count( $columns );
                $row_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
                
                echo "<tr>";
                echo "<td>{$expected}</td>";
                echo "<td>{$table_name}</td>";
                echo "<td class='status-ok'>✅ Yes</td>";
                echo "<td>{$column_count}</td>";
                echo "<td>{$row_count}</td>";
                echo "</tr>";
            } else {
                echo "<tr>";
                echo "<td>{$expected}</td>";
                echo "<td>{$table_name}</td>";
                echo "<td class='status-error'>❌ No</td>";
                echo "<td>-</td>";
                echo "<td>-</td>";
                echo "</tr>";
                
                $this->critical_issues[] = "Database table {$table_name} is missing - plugin data storage will fail";
            }
        }
        
        echo "</table>";
        
        // Check database version
        $db_version = get_option( 'wp_content_flow_db_version' );
        if ( $db_version ) {
            echo "<p class='success'>✅ Database version recorded: {$db_version}</p>";
            $tests['db_version_recorded'] = true;
        } else {
            echo "<p class='warning'>⚠️ Database version not recorded</p>";
            $tests['db_version_recorded'] = false;
            $this->warnings[] = "Database version not tracked - may cause upgrade issues";
        }
        
        // Test database schema files
        $schema_files = array(
            'schema-workflows.php',
            'schema-suggestions.php',
            'schema-history.php'
        );
        
        echo "<h3>Database Schema Files:</h3>";
        foreach ( $schema_files as $schema_file ) {
            $file_path = WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/database/' . $schema_file;
            if ( file_exists( $file_path ) ) {
                echo "<p class='success'>✅ {$schema_file} exists</p>";
                $tests["schema_{$schema_file}"] = true;
            } else {
                echo "<p class='error'>❌ {$schema_file} missing</p>";
                $tests["schema_{$schema_file}"] = false;
                $this->critical_issues[] = "Database schema file {$schema_file} is missing";
            }
        }
        
        $this->test_results['database'] = $tests;
        
        echo "</div></div>";
    }
    
    /**
     * Test REST API endpoints
     */
    private function test_rest_api_endpoints() {
        echo "<div class='test-section' id='rest-api'>";
        echo "<h2 onclick='toggleSection(this.parentElement)' class='expandable'>5. REST API Endpoints 🌐</h2>";
        echo "<div class='collapsible'>";
        
        $tests = array();
        
        // Check REST API class
        if ( class_exists( 'WP_Content_Flow_REST_API' ) ) {
            echo "<p class='success'>✅ WP_Content_Flow_REST_API class exists</p>";
            $tests['rest_api_class'] = true;
        } else {
            echo "<p class='error'>❌ WP_Content_Flow_REST_API class NOT found</p>";
            $tests['rest_api_class'] = false;
            $this->critical_issues[] = "REST API class is missing - Gutenberg blocks will not work";
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
            echo "<p class='success'>✅ Found " . count( $our_endpoints ) . " REST API endpoints</p>";
            
            echo "<h3>Available Endpoints:</h3>";
            echo "<ul>";
            foreach ( $our_endpoints as $endpoint ) {
                echo "<li><code>{$endpoint}</code></li>";
            }
            echo "</ul>";
            
            $tests['endpoints_registered'] = true;
            $tests['endpoint_count'] = count( $our_endpoints );
        } else {
            echo "<p class='error'>❌ No REST API endpoints found for wp-content-flow/v1</p>";
            $tests['endpoints_registered'] = false;
            $tests['endpoint_count'] = 0;
            $this->critical_issues[] = "No REST API endpoints registered - blocks and AJAX functionality will not work";
        }
        
        // Test API status endpoint
        $api_url = rest_url( 'wp-content-flow/v1/status' );
        echo "<p><strong>API Base URL:</strong> <a href='{$api_url}' target='_blank'>{$api_url}</a></p>";
        
        $this->test_results['rest_api'] = $tests;
        
        echo "</div></div>";
    }
    
    /**
     * Test user permissions
     */
    private function test_user_permissions() {
        echo "<div class='test-section'>";
        echo "<h2 onclick='toggleSection(this.parentElement)' class='expandable'>6. User Permissions 👤</h2>";
        echo "<div class='collapsible'>";
        
        $tests = array();
        
        // Get current user info
        $user = wp_get_current_user();
        
        echo "<h3>Current User Information:</h3>";
        echo "<table class='result-table'>";
        echo "<tr><th>Property</th><th>Value</th></tr>";
        echo "<tr><td>User ID</td><td>{$user->ID}</td></tr>";
        echo "<tr><td>Username</td><td>{$user->user_login}</td></tr>";
        echo "<tr><td>Display Name</td><td>{$user->display_name}</td></tr>";
        echo "<tr><td>Email</td><td>{$user->user_email}</td></tr>";
        echo "<tr><td>Roles</td><td>" . implode( ', ', $user->roles ) . "</td></tr>";
        echo "</table>";
        
        // Test required capabilities
        $required_caps = array(
            'manage_options' => 'Access plugin settings',
            'edit_posts' => 'Create and edit posts',
            'publish_posts' => 'Publish content'
        );
        
        echo "<h3>Required Capabilities:</h3>";
        echo "<table class='result-table'>";
        echo "<tr><th>Capability</th><th>Description</th><th>Status</th></tr>";
        
        foreach ( $required_caps as $cap => $description ) {
            $has_cap = current_user_can( $cap );
            $tests["capability_{$cap}"] = $has_cap;
            
            echo "<tr>";
            echo "<td><code>{$cap}</code></td>";
            echo "<td>{$description}</td>";
            echo "<td class='" . ( $has_cap ? 'status-ok' : 'status-error' ) . "'>";
            echo $has_cap ? '✅ Yes' : '❌ No';
            echo "</td>";
            echo "</tr>";
            
            if ( ! $has_cap ) {
                if ( $cap === 'manage_options' ) {
                    $this->critical_issues[] = "User lacks 'manage_options' capability - cannot access plugin settings";
                } else {
                    $this->warnings[] = "User lacks '{$cap}' capability - some features may be limited";
                }
            }
        }
        echo "</table>";
        
        $this->test_results['user_permissions'] = $tests;
        
        echo "</div></div>";
    }
    
    /**
     * Test file structure
     */
    private function test_file_structure() {
        echo "<div class='test-section'>";
        echo "<h2 onclick='toggleSection(this.parentElement)' class='expandable'>7. File Structure 📁</h2>";
        echo "<div class='collapsible'>";
        
        $tests = array();
        
        // Check critical files
        $critical_files = array(
            'wp-content-flow.php' => 'Main plugin file',
            'includes/class-ai-core.php' => 'AI Core class',
            'includes/admin/class-admin-menu.php' => 'Admin menu handler',
            'includes/admin/class-settings-page.php' => 'Settings page handler',
            'includes/api/class-rest-api.php' => 'REST API main class'
        );
        
        echo "<h3>Critical Plugin Files:</h3>";
        echo "<table class='result-table'>";
        echo "<tr><th>File</th><th>Description</th><th>Exists</th><th>Size</th></tr>";
        
        foreach ( $critical_files as $file => $description ) {
            $file_path = WP_CONTENT_FLOW_PLUGIN_DIR . $file;
            $exists = file_exists( $file_path );
            $size = $exists ? filesize( $file_path ) : 0;
            
            $tests["file_{$file}"] = $exists;
            
            echo "<tr>";
            echo "<td><code>{$file}</code></td>";
            echo "<td>{$description}</td>";
            echo "<td class='" . ( $exists ? 'status-ok' : 'status-error' ) . "'>";
            echo $exists ? '✅ Yes' : '❌ No';
            echo "</td>";
            echo "<td>" . ( $exists ? number_format( $size ) . ' bytes' : '-' ) . "</td>";
            echo "</tr>";
            
            if ( ! $exists ) {
                $this->critical_issues[] = "Critical file {$file} is missing";
            }
        }
        echo "</table>";
        
        // Check asset files
        $asset_files = array(
            'assets/js/admin.js',
            'assets/js/blocks.js',
            'assets/css/editor.css',
            'assets/css/frontend.css'
        );
        
        echo "<h3>Asset Files:</h3>";
        $asset_issues = 0;
        foreach ( $asset_files as $asset ) {
            $file_path = WP_CONTENT_FLOW_PLUGIN_DIR . $asset;
            if ( file_exists( $file_path ) ) {
                $size = filesize( $file_path );
                echo "<p class='success'>✅ {$asset} (" . number_format( $size ) . " bytes)</p>";
                $tests["asset_{$asset}"] = true;
            } else {
                echo "<p class='warning'>⚠️ {$asset} missing</p>";
                $tests["asset_{$asset}"] = false;
                $asset_issues++;
            }
        }
        
        if ( $asset_issues > 0 ) {
            $this->warnings[] = "{$asset_issues} asset files are missing - some UI features may not work properly";
        }
        
        $this->test_results['file_structure'] = $tests;
        
        echo "</div></div>";
    }
    
    /**
     * Test WordPress integration
     */
    private function test_wordpress_integration() {
        echo "<div class='test-section'>";
        echo "<h2 onclick='toggleSection(this.parentElement)' class='expandable'>8. WordPress Integration 🔗</h2>";
        echo "<div class='collapsible'>";
        
        $tests = array();
        
        // Check WordPress version compatibility
        $wp_version = get_bloginfo( 'version' );
        $min_wp_version = '6.0';
        $wp_compatible = version_compare( $wp_version, $min_wp_version, '>=' );
        
        echo "<h3>WordPress Environment:</h3>";
        echo "<table class='result-table'>";
        echo "<tr><th>Property</th><th>Value</th><th>Status</th></tr>";
        
        echo "<tr>";
        echo "<td>WordPress Version</td>";
        echo "<td>{$wp_version}</td>";
        echo "<td class='" . ( $wp_compatible ? 'status-ok' : 'status-error' ) . "'>";
        echo $wp_compatible ? '✅ Compatible' : '❌ Incompatible';
        echo "</td>";
        echo "</tr>";
        
        $php_version = PHP_VERSION;
        $min_php_version = '8.1';
        $php_compatible = version_compare( $php_version, $min_php_version, '>=' );
        
        echo "<tr>";
        echo "<td>PHP Version</td>";
        echo "<td>{$php_version}</td>";
        echo "<td class='" . ( $php_compatible ? 'status-ok' : 'status-error' ) . "'>";
        echo $php_compatible ? '✅ Compatible' : '❌ Incompatible';
        echo "</td>";
        echo "</tr>";
        
        $is_multisite = is_multisite();
        echo "<tr>";
        echo "<td>Multisite</td>";
        echo "<td>" . ( $is_multisite ? 'Yes' : 'No' ) . "</td>";
        echo "<td class='status-ok'>ℹ️ Info</td>";
        echo "</tr>";
        
        $debug_enabled = defined( 'WP_DEBUG' ) && WP_DEBUG;
        echo "<tr>";
        echo "<td>Debug Mode</td>";
        echo "<td>" . ( $debug_enabled ? 'Enabled' : 'Disabled' ) . "</td>";
        echo "<td class='status-ok'>ℹ️ Info</td>";
        echo "</tr>";
        
        echo "</table>";
        
        $tests['wp_compatible'] = $wp_compatible;
        $tests['php_compatible'] = $php_compatible;
        
        if ( ! $wp_compatible ) {
            $this->critical_issues[] = "WordPress version {$wp_version} is below minimum required {$min_wp_version}";
        }
        
        if ( ! $php_compatible ) {
            $this->critical_issues[] = "PHP version {$php_version} is below minimum required {$min_php_version}";
        }
        
        // Check WordPress hooks integration
        global $wp_filter;
        $hook_count = 0;
        
        foreach ( $wp_filter as $tag => $callbacks ) {
            if ( strpos( $tag, 'wp_content_flow' ) !== false || 
                 strpos( $tag, 'admin_menu' ) !== false ||
                 strpos( $tag, 'rest_api_init' ) !== false ) {
                $hook_count++;
            }
        }
        
        echo "<h3>WordPress Hooks:</h3>";
        echo "<p>Found {$hook_count} relevant WordPress hooks with our plugin callbacks</p>";
        
        $tests['hooks_registered'] = ( $hook_count > 0 );
        
        if ( $hook_count === 0 ) {
            $this->critical_issues[] = "No WordPress hooks found - plugin is not properly integrated";
        }
        
        $this->test_results['wordpress_integration'] = $tests;
        
        echo "</div></div>";
    }
    
    /**
     * Generate test summary
     */
    private function generate_test_summary() {
        echo "<div class='test-section success' id='summary'>";
        echo "<h2>📊 Test Summary</h2>";
        
        // Calculate overall statistics
        $total_tests = 0;
        $passed_tests = 0;
        
        foreach ( $this->test_results as $category => $tests ) {
            if ( is_array( $tests ) ) {
                foreach ( $tests as $test => $result ) {
                    if ( is_bool( $result ) ) {
                        $total_tests++;
                        if ( $result ) $passed_tests++;
                    } elseif ( is_numeric( $result ) && $test === 'endpoint_count' ) {
                        $total_tests++;
                        if ( $result > 0 ) $passed_tests++;
                    }
                }
            }
        }
        
        $failed_tests = $total_tests - $passed_tests;
        $pass_percentage = $total_tests > 0 ? round( ( $passed_tests / $total_tests ) * 100, 1 ) : 0;
        
        // Determine overall status
        $overall_status = 'error';
        $status_message = 'Critical Issues Found';
        $status_color = '#d63638';
        
        if ( empty( $this->critical_issues ) ) {
            if ( empty( $this->warnings ) ) {
                $overall_status = 'success';
                $status_message = 'All Tests Passed';
                $status_color = '#007017';
            } else {
                $overall_status = 'warning';
                $status_message = 'Minor Issues Found';
                $status_color = '#8c4400';
            }
        }
        
        // Display summary cards
        echo "<div class='summary-grid'>";
        
        echo "<div class='summary-card'>";
        echo "<div class='metric-value' style='color: {$status_color};'>{$pass_percentage}%</div>";
        echo "<div class='metric-label'>Success Rate</div>";
        echo "<div class='progress-bar'>";
        echo "<div class='progress-fill' style='width: {$pass_percentage}%; background-color: {$status_color};'></div>";
        echo "</div>";
        echo "</div>";
        
        echo "<div class='summary-card'>";
        echo "<div class='metric-value' style='color: #007017;'>{$passed_tests}</div>";
        echo "<div class='metric-label'>Tests Passed</div>";
        echo "</div>";
        
        echo "<div class='summary-card'>";
        echo "<div class='metric-value' style='color: #d63638;'>{$failed_tests}</div>";
        echo "<div class='metric-label'>Tests Failed</div>";
        echo "</div>";
        
        echo "<div class='summary-card'>";
        echo "<div class='metric-value' style='color: #d63638;'>" . count( $this->critical_issues ) . "</div>";
        echo "<div class='metric-label'>Critical Issues</div>";
        echo "</div>";
        
        echo "<div class='summary-card'>";
        echo "<div class='metric-value' style='color: #8c4400;'>" . count( $this->warnings ) . "</div>";
        echo "<div class='metric-label'>Warnings</div>";
        echo "</div>";
        
        echo "<div class='summary-card'>";
        echo "<div class='metric-value' style='color: {$status_color};'>{$status_message}</div>";
        echo "<div class='metric-label'>Overall Status</div>";
        echo "</div>";
        
        echo "</div>";
        
        // Test execution time
        $execution_time = round( microtime( true ) - $this->start_time, 2 );
        echo "<p><strong>Test Execution Time:</strong> {$execution_time} seconds</p>";
        
        echo "</div>";
    }
    
    /**
     * Generate recommendations
     */
    private function generate_recommendations() {
        echo "<div class='test-section' id='recommendations'>";
        echo "<h2>🔧 Recommendations & Action Items</h2>";
        
        if ( ! empty( $this->critical_issues ) ) {
            echo "<h3 style='color: #d63638;'>🚨 Critical Issues (Fix Immediately)</h3>";
            foreach ( $this->critical_issues as $issue ) {
                echo "<div class='action-item critical'>";
                echo "<strong>❌ {$issue}</strong>";
                echo "</div>";
            }
        }
        
        if ( ! empty( $this->warnings ) ) {
            echo "<h3 style='color: #8c4400;'>⚠️ Warnings (Should Fix)</h3>";
            foreach ( $this->warnings as $warning ) {
                echo "<div class='action-item warning-item'>";
                echo "<strong>⚠️ {$warning}</strong>";
                echo "</div>";
            }
        }
        
        // Generate specific recommendations based on test results
        echo "<h3>📋 Specific Action Plan</h3>";
        
        $action_plan = array();
        
        // Plugin activation issues
        if ( ! $this->test_results['plugin_status']['plugin_active'] ) {
            $action_plan[] = array(
                'priority' => 'critical',
                'title' => 'Activate Plugin',
                'description' => 'Go to WordPress Admin → Plugins and activate the "WordPress AI Content Flow" plugin.',
                'file' => 'WordPress Admin Dashboard'
            );
        }
        
        // Settings registration issues
        if ( ! $this->test_results['settings']['settings_registered'] ) {
            $action_plan[] = array(
                'priority' => 'critical',
                'title' => 'Fix Settings Registration',
                'description' => 'The settings are not registered with WordPress Settings API. Check the WP_Content_Flow_Settings_Page class constructor and ensure register_settings() is called on admin_init hook.',
                'file' => '/wp-content-flow/includes/admin/class-settings-page.php'
            );
        }
        
        // Database table issues
        $db_issues = 0;
        if ( isset( $this->test_results['database'] ) ) {
            foreach ( $this->test_results['database'] as $key => $result ) {
                if ( strpos( $key, 'table_' ) === 0 && ! $result ) {
                    $db_issues++;
                }
            }
        }
        
        if ( $db_issues > 0 ) {
            $action_plan[] = array(
                'priority' => 'critical',
                'title' => 'Fix Database Tables',
                'description' => "{$db_issues} database tables are missing. Deactivate and reactivate the plugin to trigger table creation, or check database permissions.",
                'file' => '/wp-content-flow/includes/database/'
            );
        }
        
        // Admin menu issues
        if ( ! $this->test_results['admin_menu']['main_menu_registered'] ) {
            $action_plan[] = array(
                'priority' => 'critical',
                'title' => 'Fix Admin Menu',
                'description' => 'Admin menu is not registered. Check that WP_Content_Flow_Admin_Menu class is loaded and register_admin_menu() method is hooked to admin_menu action.',
                'file' => '/wp-content-flow/includes/admin/class-admin-menu.php'
            );
        }
        
        // REST API issues
        if ( ! $this->test_results['rest_api']['endpoints_registered'] ) {
            $action_plan[] = array(
                'priority' => 'high',
                'title' => 'Fix REST API Endpoints',
                'description' => 'No REST API endpoints are registered. Gutenberg blocks will not work. Check that WP_Content_Flow_REST_API class is instantiated and register_rest_routes() is hooked to rest_api_init.',
                'file' => '/wp-content-flow/includes/api/class-rest-api.php'
            );
        }
        
        // User permission issues
        if ( isset( $this->test_results['user_permissions']['capability_manage_options'] ) && 
             ! $this->test_results['user_permissions']['capability_manage_options'] ) {
            $action_plan[] = array(
                'priority' => 'medium',
                'title' => 'User Permissions',
                'description' => 'Current user lacks manage_options capability. Log in as an administrator or assign proper roles.',
                'file' => 'User Management'
            );
        }
        
        // Display action plan
        if ( ! empty( $action_plan ) ) {
            echo "<div class='summary-grid'>";
            
            $priority_colors = array(
                'critical' => '#d63638',
                'high' => '#ffb900',
                'medium' => '#8c4400',
                'low' => '#2271b1'
            );
            
            foreach ( $action_plan as $action ) {
                $color = $priority_colors[$action['priority']] ?? '#2271b1';
                
                echo "<div class='action-item' style='border-left-color: {$color}; margin-bottom: 20px;'>";
                echo "<h4 style='margin-top: 0; color: {$color};'>" . strtoupper( $action['priority'] ) . ": {$action['title']}</h4>";
                echo "<p>{$action['description']}</p>";
                echo "<p><strong>File:</strong> <code>{$action['file']}</code></p>";
                echo "</div>";
            }
            
            echo "</div>";
        } else {
            echo "<div class='action-item success'>";
            echo "<h4>🎉 Great Job!</h4>";
            echo "<p>No critical issues found. The plugin appears to be properly configured and functional.</p>";
            echo "</div>";
        }
        
        // Additional recommendations
        echo "<h3>💡 Additional Recommendations</h3>";
        
        echo "<div class='action-item'>";
        echo "<h4>Test the Admin Interface</h4>";
        echo "<p>Visit the plugin settings page at: <a href='" . admin_url( 'admin.php?page=wp-content-flow-settings' ) . "' target='_blank'>" . admin_url( 'admin.php?page=wp-content-flow-settings' ) . "</a></p>";
        echo "<p>Try saving some test settings to verify the form works properly.</p>";
        echo "</div>";
        
        echo "<div class='action-item'>";
        echo "<h4>Test Block Editor Integration</h4>";
        echo "<p>Create a new post and check if the AI Content Flow blocks are available in the block inserter.</p>";
        echo "<p>If blocks are missing, verify REST API endpoints are working and JavaScript files are loaded.</p>";
        echo "</div>";
        
        echo "<div class='action-item'>";
        echo "<h4>Configure API Keys</h4>";
        echo "<p>Once the settings page is working, configure at least one AI provider (OpenAI, Anthropic, or Google AI) to enable content generation features.</p>";
        echo "</div>";
        
        echo "</div>";
    }
    
    /**
     * Render HTML footer
     */
    private function render_footer() {
        $execution_time = round( microtime( true ) - $this->start_time, 2 );
        
        echo "<div class='test-section' style='text-align: center; background: #f6f7f7; border: none;'>";
        echo "<p><strong>Test completed in {$execution_time} seconds</strong></p>";
        echo "<p>Generated by WP Content Flow Admin Tests Runner</p>";
        echo "<p><em>Save this report for reference and share it with developers for troubleshooting.</em></p>";
        echo "</div>";
        
        echo "</div>"; // Close container
        echo "</body></html>";
    }
}

// Run the test suite
new WP_Content_Flow_Admin_Tests_Runner();