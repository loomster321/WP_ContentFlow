<?php
/**
 * Contract test for GET /wp-json/wp-content-flow/v1/workflows endpoint
 * 
 * This test MUST FAIL until the REST API controller is implemented.
 * Following TDD principles: RED → GREEN → Refactor
 */

class Test_Workflows_Get_Contract extends WP_Content_Flow_Test_Case {

    /**
     * Test GET /workflows endpoint contract
     * 
     * @test
     */
    public function test_get_workflows_endpoint_exists() {
        $response = $this->mock_api_request( 'workflows', array(), 'GET' );
        
        // This MUST fail until WP_Content_Flow_Workflows_Controller is implemented
        $this->assertRestResponse( $response, 200, array( 'workflows', 'total', 'pages' ) );
    }

    /**
     * Test GET /workflows response structure matches OpenAPI contract
     * 
     * @test
     */
    public function test_get_workflows_response_structure() {
        $response = $this->mock_api_request( 'workflows', array(), 'GET' );
        
        $this->assertEquals( 200, $response->get_status() );
        $data = $response->get_data();
        
        // Required response fields from rest-api.yaml contract
        $this->assertArrayHasKey( 'workflows', $data );
        $this->assertArrayHasKey( 'total', $data );
        $this->assertArrayHasKey( 'pages', $data );
        
        $this->assertIsArray( $data['workflows'] );
        $this->assertIsInt( $data['total'] );
        $this->assertIsInt( $data['pages'] );
    }

    /**
     * Test GET /workflows with status filter parameter
     * 
     * @test
     */
    public function test_get_workflows_with_status_filter() {
        $response = $this->mock_api_request( 'workflows?status=active', array(), 'GET' );
        
        $this->assertEquals( 200, $response->get_status() );
        $data = $response->get_data();
        
        // All returned workflows should have 'active' status
        foreach ( $data['workflows'] as $workflow ) {
            $this->assertEquals( 'active', $workflow['status'] );
        }
    }

    /**
     * Test GET /workflows with per_page parameter
     * 
     * @test
     */
    public function test_get_workflows_with_per_page_parameter() {
        $response = $this->mock_api_request( 'workflows?per_page=5', array(), 'GET' );
        
        $this->assertEquals( 200, $response->get_status() );
        $data = $response->get_data();
        
        // Should respect per_page limit
        $this->assertLessThanOrEqual( 5, count( $data['workflows'] ) );
    }

    /**
     * Test GET /workflows validates per_page limits (1-100)
     * 
     * @test
     */
    public function test_get_workflows_per_page_validation() {
        // Test minimum limit
        $response = $this->mock_api_request( 'workflows?per_page=0', array(), 'GET' );
        $this->assertEquals( 400, $response->get_status() );
        
        // Test maximum limit
        $response = $this->mock_api_request( 'workflows?per_page=101', array(), 'GET' );
        $this->assertEquals( 400, $response->get_status() );
        
        // Test valid range
        $response = $this->mock_api_request( 'workflows?per_page=50', array(), 'GET' );
        $this->assertEquals( 200, $response->get_status() );
    }

    /**
     * Test GET /workflows individual workflow structure
     * 
     * @test
     */
    public function test_get_workflows_individual_workflow_structure() {
        $response = $this->mock_api_request( 'workflows', array(), 'GET' );
        $data = $response->get_data();
        
        if ( ! empty( $data['workflows'] ) ) {
            $workflow = $data['workflows'][0];
            
            // Required fields from Workflow schema in rest-api.yaml
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
                $this->assertArrayHasKey( $field, $workflow, "Workflow missing required field: {$field}" );
            }
            
            // Validate field types
            $this->assertIsInt( $workflow['id'] );
            $this->assertIsString( $workflow['name'] );
            $this->assertIsString( $workflow['ai_provider'] );
            $this->assertIsArray( $workflow['settings'] );
            $this->assertContains( $workflow['status'], array( 'active', 'inactive', 'archived' ) );
            $this->assertContains( $workflow['ai_provider'], array( 'openai', 'anthropic', 'google', 'azure' ) );
        }
    }

    /**
     * Test GET /workflows requires authentication
     * 
     * @test
     */
    public function test_get_workflows_requires_authentication() {
        // Remove authentication
        wp_set_current_user( 0 );
        
        $response = $this->mock_api_request( 'workflows', array(), 'GET' );
        
        // Should return 401 Unauthorized
        $this->assertEquals( 401, $response->get_status() );
    }

    /**
     * Test GET /workflows returns empty array when no workflows exist
     * 
     * @test
     */
    public function test_get_workflows_empty_response() {
        // Clear any existing workflows
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->prefix}ai_workflows" );
        
        $response = $this->mock_api_request( 'workflows', array(), 'GET' );
        
        $this->assertEquals( 200, $response->get_status() );
        $data = $response->get_data();
        
        $this->assertEquals( array(), $data['workflows'] );
        $this->assertEquals( 0, $data['total'] );
        $this->assertEquals( 0, $data['pages'] );
    }

    /**
     * Test GET /workflows with invalid status filter
     * 
     * @test
     */
    public function test_get_workflows_invalid_status_filter() {
        $response = $this->mock_api_request( 'workflows?status=invalid', array(), 'GET' );
        
        // Should return 400 Bad Request for invalid status
        $this->assertEquals( 400, $response->get_status() );
        
        $data = $response->get_data();
        $this->assertArrayHasKey( 'code', $data );
        $this->assertArrayHasKey( 'message', $data );
    }
}