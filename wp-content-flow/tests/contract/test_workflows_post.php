<?php
/**
 * Contract test for POST /wp-json/wp-content-flow/v1/workflows endpoint
 * 
 * This test MUST FAIL until the REST API controller is implemented.
 * Following TDD principles: RED → GREEN → Refactor
 */

class Test_Workflows_Post_Contract extends WP_Content_Flow_Test_Case {

    /**
     * Test POST /workflows endpoint creates new workflow
     * 
     * @test
     */
    public function test_post_workflows_creates_new_workflow() {
        $workflow_data = array(
            'name' => 'Test Blog Post Assistant',
            'description' => 'Helps generate and improve blog post content',
            'ai_provider' => 'openai',
            'settings' => array(
                'model' => 'gpt-4',
                'temperature' => 0.7,
                'max_tokens' => 1500,
                'system_prompt' => 'You are a helpful content writing assistant.'
            )
        );

        $response = $this->mock_api_request( 'workflows', $workflow_data, 'POST' );
        
        // This MUST fail until WP_Content_Flow_Workflows_Controller is implemented
        $this->assertRestResponse( $response, 201, array( 'id', 'name', 'ai_provider', 'status' ) );
    }

    /**
     * Test POST /workflows response structure matches OpenAPI contract
     * 
     * @test
     */
    public function test_post_workflows_response_structure() {
        $workflow_data = $this->create_test_workflow();
        unset( $workflow_data['user_id'] ); // Remove as it should be set automatically

        $response = $this->mock_api_request( 'workflows', $workflow_data, 'POST' );
        
        $this->assertEquals( 201, $response->get_status() );
        $data = $response->get_data();
        
        // Required fields from Workflow schema
        $required_fields = array(
            'id',
            'name',
            'description', 
            'ai_provider',
            'settings',
            'status',
            'created_at',
            'updated_at'
        );
        
        foreach ( $required_fields as $field ) {
            $this->assertArrayHasKey( $field, $data, "Response missing required field: {$field}" );
        }
        
        // Validate created workflow data
        $this->assertEquals( $workflow_data['name'], $data['name'] );
        $this->assertEquals( $workflow_data['ai_provider'], $data['ai_provider'] );
        $this->assertEquals( $workflow_data['settings'], $data['settings'] );
        $this->assertEquals( 'active', $data['status'] ); // Default status
    }

    /**
     * Test POST /workflows validates required fields
     * 
     * @test
     */
    public function test_post_workflows_validates_required_fields() {
        // Missing name
        $response = $this->mock_api_request( 'workflows', array(
            'ai_provider' => 'openai',
            'settings' => array()
        ), 'POST' );
        $this->assertEquals( 400, $response->get_status() );
        
        // Missing ai_provider
        $response = $this->mock_api_request( 'workflows', array(
            'name' => 'Test Workflow',
            'settings' => array()
        ), 'POST' );
        $this->assertEquals( 400, $response->get_status() );
        
        // Missing settings
        $response = $this->mock_api_request( 'workflows', array(
            'name' => 'Test Workflow',
            'ai_provider' => 'openai'
        ), 'POST' );
        $this->assertEquals( 400, $response->get_status() );
    }

    /**
     * Test POST /workflows validates field constraints
     * 
     * @test
     */
    public function test_post_workflows_validates_field_constraints() {
        // Name too long (> 255 characters)
        $long_name = str_repeat( 'A', 256 );
        $response = $this->mock_api_request( 'workflows', array(
            'name' => $long_name,
            'ai_provider' => 'openai',
            'settings' => array()
        ), 'POST' );
        $this->assertEquals( 400, $response->get_status() );
        
        // Invalid ai_provider
        $response = $this->mock_api_request( 'workflows', array(
            'name' => 'Test Workflow',
            'ai_provider' => 'invalid_provider',
            'settings' => array()
        ), 'POST' );
        $this->assertEquals( 400, $response->get_status() );
        
        // Valid providers should work
        $valid_providers = array( 'openai', 'anthropic', 'google', 'azure' );
        foreach ( $valid_providers as $provider ) {
            $response = $this->mock_api_request( 'workflows', array(
                'name' => "Test {$provider} Workflow",
                'ai_provider' => $provider,
                'settings' => array( 'test' => 'value' )
            ), 'POST' );
            $this->assertEquals( 201, $response->get_status() );
        }
    }

    /**
     * Test POST /workflows validates settings as valid JSON object
     * 
     * @test
     */
    public function test_post_workflows_validates_settings_json() {
        // Settings must be object/array
        $response = $this->mock_api_request( 'workflows', array(
            'name' => 'Test Workflow',
            'ai_provider' => 'openai',
            'settings' => 'invalid_json_string'
        ), 'POST' );
        $this->assertEquals( 400, $response->get_status() );
        
        // Valid JSON object should work
        $response = $this->mock_api_request( 'workflows', array(
            'name' => 'Test Workflow',
            'ai_provider' => 'openai',
            'settings' => array(
                'temperature' => 0.7,
                'max_tokens' => 1000,
                'model' => 'gpt-4'
            )
        ), 'POST' );
        $this->assertEquals( 201, $response->get_status() );
    }

    /**
     * Test POST /workflows requires authentication
     * 
     * @test
     */
    public function test_post_workflows_requires_authentication() {
        // Remove authentication
        wp_set_current_user( 0 );
        
        $workflow_data = $this->create_test_workflow();
        $response = $this->mock_api_request( 'workflows', $workflow_data, 'POST' );
        
        // Should return 401 Unauthorized
        $this->assertEquals( 401, $response->get_status() );
    }

    /**
     * Test POST /workflows requires proper WordPress capabilities
     * 
     * @test
     */
    public function test_post_workflows_requires_capabilities() {
        // Create user without edit_posts capability
        $subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
        wp_set_current_user( $subscriber_id );
        
        $workflow_data = $this->create_test_workflow();
        $response = $this->mock_api_request( 'workflows', $workflow_data, 'POST' );
        
        // Should return 403 Forbidden
        $this->assertEquals( 403, $response->get_status() );
        
        $data = $response->get_data();
        $this->assertEquals( 'rest_forbidden', $data['code'] );
    }

    /**
     * Test POST /workflows validates WordPress nonce
     * 
     * @test
     */
    public function test_post_workflows_validates_nonce() {
        $workflow_data = $this->create_test_workflow();
        
        // Create request without proper nonce
        $request = new WP_REST_Request( 'POST', '/wp-content-flow/v1/workflows' );
        $request->set_header( 'content-type', 'application/json' );
        $request->set_body( json_encode( $workflow_data ) );
        // Intentionally omit X-WP-Nonce header
        
        $response = rest_do_request( $request );
        
        // Should return 403 Forbidden due to missing/invalid nonce
        $this->assertEquals( 403, $response->get_status() );
    }

    /**
     * Test POST /workflows stores workflow with correct user association
     * 
     * @test
     */
    public function test_post_workflows_associates_with_current_user() {
        $workflow_data = $this->create_test_workflow();
        unset( $workflow_data['user_id'] ); // Should be set automatically
        
        $response = $this->mock_api_request( 'workflows', $workflow_data, 'POST' );
        
        $this->assertEquals( 201, $response->get_status() );
        $data = $response->get_data();
        
        // Verify workflow is associated with current user
        // This will be validated when we check the database after implementation
        $this->assertIsInt( $data['id'] );
    }

    /**
     * Test POST /workflows returns proper error format for validation failures
     * 
     * @test
     */
    public function test_post_workflows_error_format() {
        // Send invalid data
        $response = $this->mock_api_request( 'workflows', array(), 'POST' );
        
        $this->assertEquals( 400, $response->get_status() );
        $data = $response->get_data();
        
        // Error response structure from rest-api.yaml
        $this->assertArrayHasKey( 'code', $data );
        $this->assertArrayHasKey( 'message', $data );
        $this->assertArrayHasKey( 'data', $data );
        
        $this->assertIsString( $data['code'] );
        $this->assertIsString( $data['message'] );
        $this->assertIsArray( $data['data'] );
    }

    /**
     * Test POST /workflows handles duplicate workflow names per user
     * 
     * @test
     */
    public function test_post_workflows_handles_duplicate_names() {
        $workflow_data = array(
            'name' => 'Duplicate Name Test',
            'ai_provider' => 'openai',
            'settings' => array( 'test' => 'value' )
        );
        
        // First workflow should succeed
        $response1 = $this->mock_api_request( 'workflows', $workflow_data, 'POST' );
        $this->assertEquals( 201, $response1->get_status() );
        
        // Second workflow with same name should fail
        $response2 = $this->mock_api_request( 'workflows', $workflow_data, 'POST' );
        $this->assertEquals( 400, $response2->get_status() );
        
        $data = $response2->get_data();
        $this->assertStringContainsString( 'name', strtolower( $data['message'] ) );
    }
}