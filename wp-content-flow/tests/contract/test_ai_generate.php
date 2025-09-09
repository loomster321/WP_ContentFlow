<?php
/**
 * Contract test for POST /wp-json/wp-content-flow/v1/ai/generate endpoint
 * 
 * This test MUST FAIL until the AI controller is implemented.
 * Following TDD principles: RED → GREEN → Refactor
 */

class Test_AI_Generate_Contract extends WP_Content_Flow_Test_Case {

    /**
     * Test POST /ai/generate endpoint generates content
     * 
     * @test
     */
    public function test_post_ai_generate_creates_suggestion() {
        $generate_data = array(
            'prompt' => 'Write an introduction about sustainable gardening',
            'workflow_id' => 1,
            'post_id' => $this->factory->post->create(),
            'parameters' => array(
                'max_tokens' => 500,
                'temperature' => 0.7
            )
        );

        $response = $this->mock_api_request( 'ai/generate', $generate_data, 'POST' );
        
        // This MUST fail until WP_Content_Flow_AI_Controller is implemented
        $this->assertRestResponse( $response, 200, array( 'id', 'suggested_content', 'confidence_score' ) );
    }

    /**
     * Test POST /ai/generate response structure matches AISuggestion schema
     * 
     * @test
     */
    public function test_post_ai_generate_response_structure() {
        $generate_data = array(
            'prompt' => 'Write about climate change impacts',
            'workflow_id' => 1
        );

        $response = $this->mock_api_request( 'ai/generate', $generate_data, 'POST' );
        
        $this->assertEquals( 200, $response->get_status() );
        $data = $response->get_data();
        
        // Required fields from AISuggestion schema in rest-api.yaml
        $required_fields = array(
            'id',
            'post_id',
            'workflow_id', 
            'original_content',
            'suggested_content',
            'suggestion_type',
            'status',
            'confidence_score',
            'created_at'
        );
        
        foreach ( $required_fields as $field ) {
            $this->assertArrayHasKey( $field, $data, "Response missing required field: {$field}" );
        }
        
        // Validate field types and values
        $this->assertIsInt( $data['id'] );
        $this->assertIsInt( $data['workflow_id'] );
        $this->assertIsString( $data['suggested_content'] );
        $this->assertEquals( 'generation', $data['suggestion_type'] );
        $this->assertEquals( 'pending', $data['status'] );
        $this->assertIsNumeric( $data['confidence_score'] );
        $this->assertGreaterThanOrEqual( 0.0, $data['confidence_score'] );
        $this->assertLessThanOrEqual( 1.0, $data['confidence_score'] );
    }

    /**
     * Test POST /ai/generate validates required fields
     * 
     * @test
     */
    public function test_post_ai_generate_validates_required_fields() {
        // Missing prompt
        $response = $this->mock_api_request( 'ai/generate', array(
            'workflow_id' => 1
        ), 'POST' );
        $this->assertEquals( 400, $response->get_status() );
        
        // Missing workflow_id
        $response = $this->mock_api_request( 'ai/generate', array(
            'prompt' => 'Test prompt'
        ), 'POST' );
        $this->assertEquals( 400, $response->get_status() );
        
        // Empty prompt
        $response = $this->mock_api_request( 'ai/generate', array(
            'prompt' => '',
            'workflow_id' => 1
        ), 'POST' );
        $this->assertEquals( 400, $response->get_status() );
    }

    /**
     * Test POST /ai/generate validates workflow exists and is active
     * 
     * @test
     */
    public function test_post_ai_generate_validates_workflow() {
        // Non-existent workflow
        $response = $this->mock_api_request( 'ai/generate', array(
            'prompt' => 'Test prompt',
            'workflow_id' => 99999
        ), 'POST' );
        $this->assertEquals( 404, $response->get_status() );
        
        // This test assumes we'll have workflow status validation
        // Inactive workflow should return 400
    }

    /**
     * Test POST /ai/generate validates parameters constraints
     * 
     * @test
     */
    public function test_post_ai_generate_validates_parameters() {
        $base_data = array(
            'prompt' => 'Test prompt',
            'workflow_id' => 1
        );
        
        // max_tokens too low
        $response = $this->mock_api_request( 'ai/generate', array_merge( $base_data, array(
            'parameters' => array( 'max_tokens' => 10 )
        )), 'POST' );
        $this->assertEquals( 400, $response->get_status() );
        
        // max_tokens too high  
        $response = $this->mock_api_request( 'ai/generate', array_merge( $base_data, array(
            'parameters' => array( 'max_tokens' => 5000 )
        )), 'POST' );
        $this->assertEquals( 400, $response->get_status() );
        
        // temperature out of range
        $response = $this->mock_api_request( 'ai/generate', array_merge( $base_data, array(
            'parameters' => array( 'temperature' => 3.0 )
        )), 'POST' );
        $this->assertEquals( 400, $response->get_status() );
        
        // Valid parameters should work
        $response = $this->mock_api_request( 'ai/generate', array_merge( $base_data, array(
            'parameters' => array(
                'max_tokens' => 1000,
                'temperature' => 0.7
            )
        )), 'POST' );
        $this->assertEquals( 200, $response->get_status() );
    }

    /**
     * Test POST /ai/generate requires authentication
     * 
     * @test  
     */
    public function test_post_ai_generate_requires_authentication() {
        wp_set_current_user( 0 );
        
        $response = $this->mock_api_request( 'ai/generate', array(
            'prompt' => 'Test prompt',
            'workflow_id' => 1
        ), 'POST' );
        
        $this->assertEquals( 401, $response->get_status() );
    }

    /**
     * Test POST /ai/generate requires edit_posts capability
     * 
     * @test
     */
    public function test_post_ai_generate_requires_edit_capability() {
        $subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
        wp_set_current_user( $subscriber_id );
        
        $response = $this->mock_api_request( 'ai/generate', array(
            'prompt' => 'Test prompt',
            'workflow_id' => 1
        ), 'POST' );
        
        $this->assertEquals( 403, $response->get_status() );
    }

    /**
     * Test POST /ai/generate validates post_id permissions
     * 
     * @test
     */
    public function test_post_ai_generate_validates_post_permissions() {
        // Create post owned by different user
        $other_user_id = $this->factory->user->create();
        $post_id = $this->factory->post->create( array( 'post_author' => $other_user_id ) );
        
        // Create user without permission to edit others' posts
        $editor_id = $this->factory->user->create( array( 'role' => 'author' ) );
        wp_set_current_user( $editor_id );
        
        $response = $this->mock_api_request( 'ai/generate', array(
            'prompt' => 'Test prompt',
            'workflow_id' => 1,
            'post_id' => $post_id
        ), 'POST' );
        
        $this->assertEquals( 403, $response->get_status() );
    }

    /**
     * Test POST /ai/generate handles rate limiting
     * 
     * @test
     */
    public function test_post_ai_generate_rate_limiting() {
        $generate_data = array(
            'prompt' => 'Test prompt',
            'workflow_id' => 1
        );
        
        // Make multiple rapid requests to trigger rate limiting
        // This assumes rate limiting is implemented
        for ( $i = 0; $i < 15; $i++ ) {
            $response = $this->mock_api_request( 'ai/generate', $generate_data, 'POST' );
            
            if ( $response->get_status() === 429 ) {
                // Rate limit hit
                $data = $response->get_data();
                $this->assertEquals( 'rate_limit_exceeded', $data['code'] );
                $this->assertStringContainsString( 'try again', strtolower( $data['message'] ) );
                break;
            }
        }
    }

    /**
     * Test POST /ai/generate creates content history entry
     * 
     * @test
     */
    public function test_post_ai_generate_creates_history_entry() {
        $post_id = $this->factory->post->create();
        
        $response = $this->mock_api_request( 'ai/generate', array(
            'prompt' => 'Test prompt',
            'workflow_id' => 1,
            'post_id' => $post_id
        ), 'POST' );
        
        $this->assertEquals( 200, $response->get_status() );
        
        // Verify history entry was created (will be checked when history table exists)
        // This is part of the contract that AI generation creates audit trail
    }

    /**
     * Test POST /ai/generate returns appropriate error for AI provider failures
     * 
     * @test
     */
    public function test_post_ai_generate_handles_provider_errors() {
        // This test assumes AI provider integration
        // Should handle cases where OpenAI/Claude API is down or returns error
        
        // Mock scenario where AI provider fails
        $this->mock_ai_response( 'openai', array( 'error' => 'API temporarily unavailable' ) );
        
        $response = $this->mock_api_request( 'ai/generate', array(
            'prompt' => 'Test prompt', 
            'workflow_id' => 1
        ), 'POST' );
        
        // Should return 500 or appropriate error status
        $this->assertContains( $response->get_status(), array( 500, 502, 503 ) );
        
        $data = $response->get_data();
        $this->assertArrayHasKey( 'code', $data );
        $this->assertArrayHasKey( 'message', $data );
    }
}