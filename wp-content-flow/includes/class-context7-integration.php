<?php
/**
 * WordPress AI Content Flow - Context7 Integration
 *
 * Integrates Context7 debugging and monitoring capabilities with WordPress.
 * Provides advanced error tracking, performance monitoring, and debugging tools.
 *
 * @package WP_Content_Flow
 * @subpackage Debugging
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Context7 Integration Class
 */
class WP_Content_Flow_Context7_Integration {
    
    /**
     * Context7 instance
     *
     * @var mixed
     */
    private static $context7 = null;
    
    /**
     * Debugging enabled flag
     *
     * @var bool
     */
    private static $debug_enabled = false;
    
    /**
     * Performance tracking data
     *
     * @var array
     */
    private static $performance_data = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'init', array( $this, 'init_context7' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_context7_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_context7_assets' ) );
        add_action( 'wp_footer', array( $this, 'output_debug_data' ) );
        add_action( 'admin_footer', array( $this, 'output_debug_data' ) );
        
        // Error handling hooks
        add_action( 'wp_content_flow_api_error', array( $this, 'log_api_error' ), 10, 3 );
        add_action( 'wp_content_flow_ai_error', array( $this, 'log_ai_error' ), 10, 3 );
        add_action( 'wp_content_flow_performance_issue', array( $this, 'log_performance_issue' ), 10, 2 );
        
        // Performance monitoring hooks
        add_action( 'wp_content_flow_performance_start', array( $this, 'start_performance_tracking' ), 10, 2 );
        add_action( 'wp_content_flow_performance_end', array( $this, 'end_performance_tracking' ), 10, 3 );
        
        // WordPress error integration
        add_filter( 'wp_die_handler', array( $this, 'capture_wp_die' ) );
        
        // AJAX error handling
        add_action( 'wp_ajax_wp_content_flow_context7_log', array( $this, 'ajax_log_client_error' ) );
        add_action( 'wp_ajax_nopriv_wp_content_flow_context7_log', array( $this, 'ajax_log_client_error' ) );
    }
    
    /**
     * Initialize Context7 integration
     */
    public function init_context7() {
        // Check if Context7 is available
        if ( ! $this->is_context7_available() ) {
            return;
        }
        
        $settings = get_option( 'wp_content_flow_settings', array() );
        self::$debug_enabled = ! empty( $settings['debug_mode'] ) || defined( 'WP_DEBUG' ) && WP_DEBUG;
        
        if ( self::$debug_enabled ) {
            $this->setup_context7();
        }
    }
    
    /**
     * Check if Context7 is available
     *
     * @return bool
     */
    private function is_context7_available() {
        // Check if Node.js context7 package is available via wp-scripts or similar
        $context7_config = WP_CONTENT_FLOW_PLUGIN_DIR . 'context7.config.js';
        return file_exists( $context7_config );
    }
    
    /**
     * Setup Context7 with WordPress integration
     */
    private function setup_context7() {
        // Configure Context7 for WordPress environment
        $config = array(
            'wordpress' => array(
                'version' => get_bloginfo( 'version' ),
                'plugins' => array( 'wp-content-flow' => WP_CONTENT_FLOW_VERSION ),
                'theme' => get_template(),
                'user_id' => get_current_user_id(),
                'user_roles' => $this->get_current_user_roles(),
                'admin_url' => admin_url(),
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'wp_content_flow_context7_nonce' )
            ),
            'ai' => array(
                'providers' => $this->get_available_ai_providers(),
                'active_provider' => $this->get_active_ai_provider(),
                'workflows' => $this->get_workflow_count()
            ),
            'debug' => array(
                'wp_debug' => defined( 'WP_DEBUG' ) && WP_DEBUG,
                'wp_debug_log' => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
                'wp_debug_display' => defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY,
                'script_debug' => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG
            )
        );
        
        // Store config for JavaScript access
        wp_localize_script( 'wp-content-flow-context7', 'wpContentFlowContext7Config', $config );
        
        // Initialize PHP-side error tracking
        $this->setup_php_error_tracking();
    }
    
    /**
     * Setup PHP error tracking
     */
    private function setup_php_error_tracking() {
        // Custom error handler for plugin errors
        set_error_handler( array( $this, 'handle_php_error' ), E_ALL );
        
        // Exception handler
        set_exception_handler( array( $this, 'handle_php_exception' ) );
        
        // Shutdown function to catch fatal errors
        register_shutdown_function( array( $this, 'handle_php_shutdown' ) );
    }
    
    /**
     * Enqueue Context7 assets
     */
    public function enqueue_context7_assets( $hook ) {
        if ( ! self::$debug_enabled ) {
            return;
        }
        
        // Enqueue Context7 JavaScript
        wp_enqueue_script(
            'wp-content-flow-context7',
            WP_CONTENT_FLOW_PLUGIN_URL . 'assets/js/context7-integration.js',
            array( 'jquery' ),
            WP_CONTENT_FLOW_VERSION,
            true
        );
        
        // Enqueue debugging styles
        wp_enqueue_style(
            'wp-content-flow-context7-debug',
            WP_CONTENT_FLOW_PLUGIN_URL . 'assets/css/context7-debug.css',
            array(),
            WP_CONTENT_FLOW_VERSION
        );
    }
    
    /**
     * Log API errors
     */
    public function log_api_error( $error, $endpoint, $context ) {
        if ( ! self::$debug_enabled ) {
            return;
        }
        
        $error_data = array(
            'type' => 'api_error',
            'error' => $error,
            'endpoint' => $endpoint,
            'context' => $context,
            'timestamp' => current_time( 'mysql' ),
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'stack_trace' => $this->get_stack_trace()
        );
        
        $this->send_to_context7( 'error', $error_data );
        $this->log_to_file( 'api_error', $error_data );
    }
    
    /**
     * Log AI-related errors
     */
    public function log_ai_error( $provider, $error, $context ) {
        if ( ! self::$debug_enabled ) {
            return;
        }
        
        $error_data = array(
            'type' => 'ai_error',
            'provider' => $provider,
            'error' => $error,
            'context' => $context,
            'timestamp' => current_time( 'mysql' ),
            'user_id' => get_current_user_id(),
            'workflows_active' => $this->get_workflow_count(),
            'rate_limit_status' => $this->get_rate_limit_status()
        );
        
        $this->send_to_context7( 'error', $error_data );
        $this->log_to_file( 'ai_error', $error_data );
    }
    
    /**
     * Log performance issues
     */
    public function log_performance_issue( $operation, $duration ) {
        if ( ! self::$debug_enabled ) {
            return;
        }
        
        $performance_data = array(
            'type' => 'performance_issue',
            'operation' => $operation,
            'duration' => $duration,
            'timestamp' => current_time( 'mysql' ),
            'memory_usage' => memory_get_usage( true ),
            'memory_peak' => memory_get_peak_usage( true ),
            'query_count' => get_num_queries(),
            'context' => $this->get_performance_context()
        );
        
        $this->send_to_context7( 'performance', $performance_data );
        $this->log_to_file( 'performance', $performance_data );
    }
    
    /**
     * Start performance tracking
     */
    public function start_performance_tracking( $operation, $context = array() ) {
        if ( ! self::$debug_enabled ) {
            return;
        }
        
        self::$performance_data[ $operation ] = array(
            'start_time' => microtime( true ),
            'start_memory' => memory_get_usage( true ),
            'start_queries' => get_num_queries(),
            'context' => $context
        );
    }
    
    /**
     * End performance tracking
     */
    public function end_performance_tracking( $operation, $success, $context = array() ) {
        if ( ! self::$debug_enabled || ! isset( self::$performance_data[ $operation ] ) ) {
            return;
        }
        
        $start_data = self::$performance_data[ $operation ];
        $end_time = microtime( true );
        $duration = ( $end_time - $start_data['start_time'] ) * 1000; // Convert to milliseconds
        
        $performance_data = array(
            'type' => 'performance_tracking',
            'operation' => $operation,
            'duration' => $duration,
            'success' => $success,
            'memory_used' => memory_get_usage( true ) - $start_data['start_memory'],
            'queries_executed' => get_num_queries() - $start_data['start_queries'],
            'context' => array_merge( $start_data['context'], $context ),
            'timestamp' => current_time( 'mysql' )
        );
        
        // Check performance thresholds
        $thresholds = $this->get_performance_thresholds();
        if ( $duration > ( $thresholds[ $operation ] ?? 5000 ) ) {
            $this->log_performance_issue( $operation, $duration );
        }
        
        $this->send_to_context7( 'performance', $performance_data );
        
        // Clean up tracking data
        unset( self::$performance_data[ $operation ] );
    }
    
    /**
     * Handle PHP errors
     */
    public function handle_php_error( $errno, $errstr, $errfile, $errline ) {
        if ( ! self::$debug_enabled ) {
            return false; // Let WordPress handle it
        }
        
        // Only track errors from our plugin
        if ( strpos( $errfile, 'wp-content-flow' ) === false ) {
            return false;
        }
        
        $error_data = array(
            'type' => 'php_error',
            'severity' => $errno,
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
            'timestamp' => current_time( 'mysql' ),
            'stack_trace' => $this->get_stack_trace()
        );
        
        $this->send_to_context7( 'error', $error_data );
        $this->log_to_file( 'php_error', $error_data );
        
        return false; // Continue with WordPress error handling
    }
    
    /**
     * Handle PHP exceptions
     */
    public function handle_php_exception( $exception ) {
        if ( ! self::$debug_enabled ) {
            return;
        }
        
        $error_data = array(
            'type' => 'php_exception',
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'stack_trace' => $exception->getTraceAsString(),
            'timestamp' => current_time( 'mysql' )
        );
        
        $this->send_to_context7( 'error', $error_data );
        $this->log_to_file( 'php_exception', $error_data );
    }
    
    /**
     * Handle PHP shutdown (catch fatal errors)
     */
    public function handle_php_shutdown() {
        if ( ! self::$debug_enabled ) {
            return;
        }
        
        $error = error_get_last();
        
        if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ) ) ) {
            // Only track fatal errors from our plugin
            if ( strpos( $error['file'], 'wp-content-flow' ) !== false ) {
                $error_data = array(
                    'type' => 'php_fatal_error',
                    'message' => $error['message'],
                    'file' => $error['file'],
                    'line' => $error['line'],
                    'timestamp' => current_time( 'mysql' )
                );
                
                $this->log_to_file( 'php_fatal_error', $error_data );
                // Can't send to Context7 here as it's too late in the process
            }
        }
    }
    
    /**
     * AJAX handler for client-side error logging
     */
    public function ajax_log_client_error() {
        check_ajax_referer( 'wp_content_flow_context7_nonce', 'nonce' );
        
        if ( ! self::$debug_enabled ) {
            wp_die( -1 );
        }
        
        $error_data = array(
            'type' => 'client_error',
            'message' => sanitize_text_field( $_POST['message'] ?? '' ),
            'filename' => sanitize_text_field( $_POST['filename'] ?? '' ),
            'lineno' => intval( $_POST['lineno'] ?? 0 ),
            'colno' => intval( $_POST['colno'] ?? 0 ),
            'stack' => sanitize_textarea_field( $_POST['stack'] ?? '' ),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'url' => esc_url_raw( $_POST['url'] ?? '' ),
            'timestamp' => current_time( 'mysql' ),
            'user_id' => get_current_user_id()
        );
        
        $this->log_to_file( 'client_error', $error_data );
        
        wp_send_json_success();
    }
    
    /**
     * Output debug data to frontend
     */
    public function output_debug_data() {
        if ( ! self::$debug_enabled || ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $debug_data = array(
            'performance' => $this->get_page_performance_data(),
            'errors' => $this->get_recent_errors(),
            'ai_stats' => $this->get_ai_operation_stats(),
            'system_info' => $this->get_system_info()
        );
        
        ?>
        <script type="text/javascript">
        if (typeof window.wpContentFlowDebug === 'undefined') {
            window.wpContentFlowDebug = <?php echo wp_json_encode( $debug_data ); ?>;
        }
        </script>
        
        <div id="wp-content-flow-debug-panel" style="display: none;">
            <div class="debug-header">
                <h3>WP Content Flow Debug Panel</h3>
                <button class="debug-toggle">Toggle</button>
            </div>
            <div class="debug-content">
                <div class="debug-section">
                    <h4>Performance</h4>
                    <pre><?php echo esc_html( wp_json_encode( $debug_data['performance'], JSON_PRETTY_PRINT ) ); ?></pre>
                </div>
                <div class="debug-section">
                    <h4>Recent Errors</h4>
                    <pre><?php echo esc_html( wp_json_encode( $debug_data['errors'], JSON_PRETTY_PRINT ) ); ?></pre>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Send data to Context7 (JavaScript side)
     */
    private function send_to_context7( $type, $data ) {
        // Store in option for JavaScript to pick up
        $context7_queue = get_option( 'wp_content_flow_context7_queue', array() );
        $context7_queue[] = array(
            'type' => $type,
            'data' => $data,
            'timestamp' => time()
        );
        
        // Keep only last 100 items to prevent database bloat
        if ( count( $context7_queue ) > 100 ) {
            $context7_queue = array_slice( $context7_queue, -100 );
        }
        
        update_option( 'wp_content_flow_context7_queue', $context7_queue );
    }
    
    /**
     * Log to file
     */
    private function log_to_file( $type, $data ) {
        $log_file = WP_CONTENT_FLOW_PLUGIN_DIR . 'logs/context7-' . $type . '.log';
        $log_dir = dirname( $log_file );
        
        if ( ! file_exists( $log_dir ) ) {
            wp_mkdir_p( $log_dir );
        }
        
        $log_entry = current_time( 'Y-m-d H:i:s' ) . ' - ' . wp_json_encode( $data ) . PHP_EOL;
        error_log( $log_entry, 3, $log_file );
    }
    
    /**
     * Helper methods
     */
    private function get_current_user_roles() {
        $user = wp_get_current_user();
        return $user ? $user->roles : array();
    }
    
    private function get_available_ai_providers() {
        return array( 'openai', 'anthropic', 'google' ); // This would be dynamic
    }
    
    private function get_active_ai_provider() {
        $settings = get_option( 'wp_content_flow_settings', array() );
        return $settings['ai_provider'] ?? 'openai';
    }
    
    private function get_workflow_count() {
        // This would query the workflows table
        global $wpdb;
        $table_name = $wpdb->prefix . 'wp_content_flow_workflows';
        return $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE status = 'active'" );
    }
    
    private function get_client_ip() {
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    private function get_stack_trace() {
        return debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
    }
    
    private function get_performance_thresholds() {
        return array(
            'ai_generate' => 30000,
            'ai_improve' => 15000,
            'api_call' => 10000,
            'page_load' => 3000
        );
    }
    
    private function get_rate_limit_status() {
        // This would check current rate limiting status
        return array(
            'requests_remaining' => 50,
            'reset_time' => time() + 3600
        );
    }
    
    private function get_performance_context() {
        return array(
            'page' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'user_id' => get_current_user_id(),
            'is_admin' => is_admin()
        );
    }
    
    private function get_page_performance_data() {
        return array(
            'memory_usage' => memory_get_usage( true ),
            'memory_peak' => memory_get_peak_usage( true ),
            'query_count' => get_num_queries(),
            'load_time' => microtime( true ) - $_SERVER['REQUEST_TIME_FLOAT']
        );
    }
    
    private function get_recent_errors() {
        // Return recent errors from log files or database
        return array(); // Placeholder
    }
    
    private function get_ai_operation_stats() {
        // Return AI operation statistics
        return array(
            'generations_today' => 0,
            'improvements_today' => 0,
            'success_rate' => 95.5
        );
    }
    
    private function get_system_info() {
        return array(
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo( 'version' ),
            'plugin_version' => WP_CONTENT_FLOW_VERSION,
            'memory_limit' => ini_get( 'memory_limit' ),
            'max_execution_time' => ini_get( 'max_execution_time' )
        );
    }
}