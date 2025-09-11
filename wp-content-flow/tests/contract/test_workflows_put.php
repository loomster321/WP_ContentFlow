<?php
/**
 * Contract Test: PUT /wp-json/wp-content-flow/v1/workflows/{id}
 * 
 * Tests the workflow update endpoint according to the OpenAPI contract.
 * This test validates that the endpoint correctly updates existing workflows
 * with proper validation, permissions, and error handling.
 *
 * @package WP_Content_Flow
 * @subpackage Tests\Contract
 */

class Test_Workflows_Put extends WP_Content_Flow_Test_Case {
    
    /**
     * Admin user for authenticated requests
     * @var int
     */
    private $admin_user;
    
    /**
     * Editor user for permission testing
     * @var int
     */
    private $editor_user;
    
    /**
     * Contributor user for permission testing
     * @var int
     */
    private $contributor_user;
    
    /**
     * Test workflow ID
     * @var int
     */
    private $test_workflow_id;
    
    /**
     * Set up test fixtures
     */
    public function setUp() {
        parent::setUp();
        
        // Create test users
        $this->admin_user = $this->factory->user->create( array( 'role' => 'administrator' ) );
        $this->editor_user = $this->factory->user->create( array( 'role' => 'editor' ) );
        $this->contributor_user = $this->factory->user->create( array( 'role' => 'contributor' ) );
        
        // Create a test workflow
        wp_set_current_user( $this->admin_user );
        $workflow_data = array(
            'name' => 'Test Workflow for Update',
            'description' => 'Original description',
            'workflow_type' => 'content_generation',
            'trigger_type' => 'manual',
            'ai_provider' => 'openai',
            'prompt_template' => 'Generate content about: {topic}',
            'parameters' => array(
                'max_tokens' => 500,
                'temperature' => 0.7,
                'model' => 'gpt-3.5-turbo'
            ),
            'is_active' => true,
            'created_by' => $this->admin_user
        );
        
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'content_flow_workflows',
            $workflow_data
        );
        $this->test_workflow_id = $wpdb->insert_id;
    }
    
    /**
     * Clean up after tests
     */
    public function tearDown() {
        // Clean up test data
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'content_flow_workflows',
            array( 'id' => $this->test_workflow_id )
        );
        
        parent::tearDown();
    }
    
    /**
     * Test successful workflow update
     * Contract: PUT /workflows/{id} with valid data returns 200 and updated workflow
     */
    public function test_put_workflow_success() {
        wp_set_current_user( $this->admin_user );
        
        $request = new WP_REST_Request( 'PUT', '/wp-content-flow/v1/workflows/' . $this->test_workflow_id );
        $request->set_header( 'content-type', 'application/json' );
        $request->set_body( json_encode( array(
            'name' => 'Updated Workflow Name',
            'description' => 'Updated description with more details',
            'workflow_type' => 'content_improvement',
            'trigger_type' => 'automatic',
            'ai_provider' => 'anthropic',
            'prompt_template' => 'Improve this content: {content}',
            'parameters' => array(
                'max_tokens' => 1000,
                'temperature' => 0.5,
                'model' => 'claude-2'
            ),
            'is_active' => false
        ) ) );
        
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        // Assert response status
        $this->assertEquals( 200, $response->get_status() );
        
        // Assert workflow was updated
        $this->assertEquals( $this->test_workflow_id, $data['id'] );
        $this->assertEquals( 'Updated Workflow Name', $data['name'] );
        $this->assertEquals( 'Updated description with more details', $data['description'] );
        $this->assertEquals( 'content_improvement', $data['workflow_type'] );
        $this->assertEquals( 'automatic', $data['trigger_type'] );
        $this->assertEquals( 'anthropic', $data['ai_provider'] );
        $this->assertEquals( 'Improve this content: {content}', $data['prompt_template'] );
        $this->assertFalse( $data['is_active'] );
        
        // Assert parameters were updated
        $this->assertEquals( 1000, $data['parameters']['max_tokens'] );
        $this->assertEquals( 0.5, $data['parameters']['temperature'] );
        $this->assertEquals( 'claude-2', $data['parameters']['model'] );
        
        // Assert metadata
        $this->assertArrayHasKey( 'updated_at', $data );
        $this->assertEquals( $this->admin_user, $data['updated_by'] );
    }
    
    /**
     * Test partial workflow update
     * Contract: PUT with partial data only updates provided fields
     */
    public function test_put_workflow_partial_update() {
        wp_set_current_user( $this->admin_user );
        
        $request = new WP_REST_Request( 'PUT', '/wp-content-flow/v1/workflows/' . $this->test_workflow_id );
        $request->set_header( 'content-type', 'application/json' );
        $request->set_body( json_encode( array(
            'name' => 'Partially Updated Name',
            'is_active' => false
        ) ) );
        
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        // Assert response status
        $this->assertEquals( 200, $response->get_status() );
        
        // Assert only specified fields were updated
        $this->assertEquals( 'Partially Updated Name', $data['name'] );
        $this->assertFalse( $data['is_active'] );
        
        // Assert other fields remain unchanged
        $this->assertEquals( 'Original description', $data['description'] );
        $this->assertEquals( 'content_generation', $data['workflow_type'] );
        $this->assertEquals( 'manual', $data['trigger_type'] );
        $this->assertEquals( 'openai', $data['ai_provider'] );
    }
    
    /**
     * Test workflow update with invalid ID
     * Contract: PUT to non-existent workflow returns 404
     */
    public function test_put_workflow_not_found() {
        wp_set_current_user( $this->admin_user );
        
        $request = new WP_REST_Request( 'PUT', '/wp-content-flow/v1/workflows/999999' );
        $request->set_header( 'content-type', 'application/json' );
        $request->set_body( json_encode( array(
            'name' => 'Should Not Update'
        ) ) );
        
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        // Assert 404 response
        $this->assertEquals( 404, $response->get_status() );
        $this->assertEquals( 'workflow_not_found', $data['code'] );
    }
    
    /**
     * Test workflow update with invalid data
     * Contract: PUT with invalid data returns 400 with validation errors
     */
    public function test_put_workflow_validation_error() {
        wp_set_current_user( $this->admin_user );
        
        $request = new WP_REST_Request( 'PUT', '/wp-content-flow/v1/workflows/' . $this->test_workflow_id );
        $request->set_header( 'content-type', 'application/json' );
        $request->set_body( json_encode( array(
            'name' => '', // Empty name should fail validation
            'workflow_type' => 'invalid_type', // Invalid workflow type
            'ai_provider' => 'unsupported_provider', // Invalid provider
            'parameters' => 'not_an_array' // Invalid parameters type
        ) ) );
        
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        // Assert 400 response
        $this->assertEquals( 400, $response->get_status() );
        $this->assertEquals( 'rest_invalid_param', $data['code'] );
        
        // Assert validation error details
        $this->assertArrayHasKey( 'data', $data );
        $this->assertArrayHasKey( 'params', $data['data'] );
    }
    
    /**
     * Test workflow update without authentication
     * Contract: PUT without authentication returns 401
     */
    public function test_put_workflow_unauthenticated() {
        wp_set_current_user( 0 );
        
        $request = new WP_REST_Request( 'PUT', '/wp-content-flow/v1/workflows/' . $this->test_workflow_id );
        $request->set_header( 'content-type', 'application/json' );
        $request->set_body( json_encode( array(
            'name' => 'Should Not Update'
        ) ) );
        
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        // Assert 401 response
        $this->assertEquals( 401, $response->get_status() );
        $this->assertEquals( 'rest_forbidden', $data['code'] );
    }
    
    /**
     * Test workflow update with insufficient permissions
     * Contract: Contributors cannot update workflows (403)
     */
    public function test_put_workflow_insufficient_permissions() {
        wp_set_current_user( $this->contributor_user );
        
        $request = new WP_REST_Request( 'PUT', '/wp-content-flow/v1/workflows/' . $this->test_workflow_id );
        $request->set_header( 'content-type', 'application/json' );
        $request->set_body( json_encode( array(
            'name' => 'Should Not Update'
        ) ) );
        
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        // Assert 403 response
        $this->assertEquals( 403, $response->get_status() );
        $this->assertEquals( 'rest_forbidden', $data['code'] );
    }
    
    /**
     * Test workflow update by editor
     * Contract: Editors can update workflows
     */
    public function test_put_workflow_editor_permission() {
        wp_set_current_user( $this->editor_user );
        
        $request = new WP_REST_Request( 'PUT', '/wp-content-flow/v1/workflows/' . $this->test_workflow_id );
        $request->set_header( 'content-type', 'application/json' );
        $request->set_body( json_encode( array(
            'name' => 'Editor Updated Workflow'
        ) ) );
        
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        // Assert successful update
        $this->assertEquals( 200, $response->get_status() );
        $this->assertEquals( 'Editor Updated Workflow', $data['name'] );
        $this->assertEquals( $this->editor_user, $data['updated_by'] );
    }
    
    /**
     * Test workflow update with complex parameters
     * Contract: Complex nested parameters are properly handled
     */
    public function test_put_workflow_complex_parameters() {
        wp_set_current_user( $this->admin_user );
        
        $complex_params = array(
            'max_tokens' => 2000,
            'temperature' => 0.8,
            'top_p' => 0.95,
            'frequency_penalty' => 0.5,
            'presence_penalty' => 0.3,
            'model' => 'gpt-4',
            'custom_instructions' => array(
                'tone' => 'professional',
                'style' => 'concise',
                'format' => 'markdown'
            ),
            'content_filters' => array(
                'exclude_topics' => array('politics', 'religion'),
                'required_keywords' => array('WordPress', 'AI'),
                'max_length' => 5000
            )
        );
        
        $request = new WP_REST_Request( 'PUT', '/wp-content-flow/v1/workflows/' . $this->test_workflow_id );
        $request->set_header( 'content-type', 'application/json' );
        $request->set_body( json_encode( array(
            'parameters' => $complex_params
        ) ) );
        
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        // Assert response status
        $this->assertEquals( 200, $response->get_status() );
        
        // Assert complex parameters were saved correctly
        $this->assertEquals( $complex_params['max_tokens'], $data['parameters']['max_tokens'] );
        $this->assertEquals( $complex_params['temperature'], $data['parameters']['temperature'] );
        $this->assertEquals( $complex_params['custom_instructions'], $data['parameters']['custom_instructions'] );
        $this->assertEquals( $complex_params['content_filters'], $data['parameters']['content_filters'] );
    }
    
    /**
     * Test concurrent workflow updates
     * Contract: Last update wins in concurrent scenarios
     */
    public function test_put_workflow_concurrent_updates() {
        wp_set_current_user( $this->admin_user );
        
        // First update
        $request1 = new WP_REST_Request( 'PUT', '/wp-content-flow/v1/workflows/' . $this->test_workflow_id );
        $request1->set_header( 'content-type', 'application/json' );
        $request1->set_body( json_encode( array(
            'name' => 'First Update',
            'description' => 'First description'
        ) ) );
        
        $response1 = rest_do_request( $request1 );
        $this->assertEquals( 200, $response1->get_status() );
        
        // Second update (should override first)
        $request2 = new WP_REST_Request( 'PUT', '/wp-content-flow/v1/workflows/' . $this->test_workflow_id );
        $request2->set_header( 'content-type', 'application/json' );
        $request2->set_body( json_encode( array(
            'name' => 'Second Update',
            'description' => 'Second description'
        ) ) );
        
        $response2 = rest_do_request( $request2 );
        $data = $response2->get_data();
        
        // Assert second update wins
        $this->assertEquals( 200, $response2->get_status() );
        $this->assertEquals( 'Second Update', $data['name'] );
        $this->assertEquals( 'Second description', $data['description'] );
    }
}