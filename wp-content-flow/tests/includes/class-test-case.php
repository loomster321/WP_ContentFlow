<?php
/**
 * Base test case class for WordPress AI Content Flow Plugin tests
 */

class WP_Content_Flow_Test_Case extends WP_UnitTestCase {
    
    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        
        // Clear any cached data
        wp_cache_flush();
        
        // Reset plugin state
        delete_option( 'wp_content_flow_settings' );
        
        // Create test admin user
        $this->admin_user_id = $this->factory->user->create( array(
            'role' => 'administrator',
        ) );
        
        wp_set_current_user( $this->admin_user_id );
    }
    
    /**
     * Clean up after test
     */
    public function tearDown(): void {
        // Clean up test data
        global $wpdb;
        
        $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_author = {$this->admin_user_id}" );
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%wp_content_flow%'" );
        
        parent::tearDown();
    }
    
    /**
     * Create test workflow
     *
     * @param array $args Workflow arguments
     * @return array Workflow data
     */
    protected function create_test_workflow( $args = array() ) {
        $defaults = array(
            'name' => 'Test Workflow',
            'description' => 'Test workflow description',
            'ai_provider' => 'openai',
            'settings' => array(
                'model' => 'gpt-4',
                'temperature' => 0.7,
                'max_tokens' => 1000,
            ),
            'status' => 'active',
            'user_id' => $this->admin_user_id,
        );
        
        return array_merge( $defaults, $args );
    }
    
    /**
     * Create test AI suggestion
     *
     * @param array $args Suggestion arguments
     * @return array Suggestion data
     */
    protected function create_test_suggestion( $args = array() ) {
        $post_id = $this->factory->post->create();
        
        $defaults = array(
            'post_id' => $post_id,
            'workflow_id' => 1,
            'user_id' => $this->admin_user_id,
            'original_content' => 'Original test content',
            'suggested_content' => 'Improved test content',
            'suggestion_type' => 'improvement',
            'status' => 'pending',
            'confidence_score' => 0.85,
        );
        
        return array_merge( $defaults, $args );
    }
    
    /**
     * Mock REST API request
     *
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param string $method HTTP method
     * @return WP_REST_Response
     */
    protected function mock_api_request( $endpoint, $data = array(), $method = 'POST' ) {
        $request = new WP_REST_Request( $method, '/wp-content-flow/v1/' . $endpoint );
        $request->set_header( 'content-type', 'application/json' );
        
        if ( ! empty( $data ) ) {
            $request->set_body( json_encode( $data ) );
        }
        
        // Mock authentication
        $request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
        
        return rest_do_request( $request );
    }
    
    /**
     * Assert REST API response structure
     *
     * @param WP_REST_Response $response API response
     * @param int $expected_status Expected HTTP status code
     * @param array $required_fields Required response fields
     */
    protected function assertRestResponse( $response, $expected_status = 200, $required_fields = array() ) {
        $this->assertEquals( $expected_status, $response->get_status() );
        
        $data = $response->get_data();
        
        foreach ( $required_fields as $field ) {
            $this->assertArrayHasKey( $field, $data, "Response missing required field: {$field}" );
        }
    }
    
    /**
     * Assert WordPress hook was fired
     *
     * @param string $hook_name Hook name
     * @param int $expected_times Expected number of times fired
     */
    protected function assertHookFired( $hook_name, $expected_times = 1 ) {
        $this->assertEquals( 
            $expected_times, 
            did_action( $hook_name ), 
            "Hook '{$hook_name}' was not fired the expected number of times"
        );
    }
    
    /**
     * Mock AI provider response
     *
     * @param string $provider Provider name
     * @param array $response Mock response data
     */
    protected function mock_ai_response( $provider, $response ) {
        // This will be implemented when AI provider classes are created
        // For now, store in test property for later use
        $this->ai_mock_responses[ $provider ] = $response;
    }
}