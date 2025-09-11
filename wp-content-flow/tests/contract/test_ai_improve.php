<?php
/**
 * Contract Test: POST /wp-json/wp-content-flow/v1/ai/improve
 * 
 * Tests the AI content improvement endpoint according to the OpenAPI contract.
 * This endpoint takes existing content and generates improvement suggestions
 * using AI providers.
 *
 * @package WP_Content_Flow
 * @subpackage Tests\Contract
 */

class Test_AI_Improve extends WP_Content_Flow_Test_Case {
    
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
     * Author user for permission testing
     * @var int
     */
    private $author_user;
    
    /**
     * Contributor user for permission testing
     * @var int
     */
    private $contributor_user;
    
    /**
     * Test post ID
     * @var int
     */
    private $test_post_id;
    
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
        $this->author_user = $this->factory->user->create( array( 'role' => 'author' ) );
        $this->contributor_user = $this->factory->user->create( array( 'role' => 'contributor' ) );
        
        // Create a test post
        wp_set_current_user( $this->author_user );
        $this->test_post_id = wp_insert_post( array(
            'post_title' => 'Test Post for AI Improvement',
            'post_content' => 'This is original content that needs improvement. It lacks detail and could be more engaging.',
            'post_status' => 'draft',
            'post_author' => $this->author_user,
            'post_type' => 'post'
        ) );
        
        // Create a test workflow for improvement
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'content_flow_workflows',
            array(
                'name' => 'Content Improvement Workflow',
                'description' => 'Workflow for improving existing content',
                'workflow_type' => 'content_improvement',
                'trigger_type' => 'manual',
                'ai_provider' => 'openai',
                'prompt_template' => 'Improve the following content for clarity and engagement: {content}',
                'parameters' => json_encode(array(
                    'max_tokens' => 1000,
                    'temperature' => 0.7,
                    'model' => 'gpt-3.5-turbo',
                    'improvement_focus' => array('clarity', 'engagement', 'seo')
                )),
                'is_active' => true,
                'created_by' => $this->admin_user
            )
        );
        $this->test_workflow_id = $wpdb->insert_id;
        
        // Mock AI provider response
        add_filter( 'wp_content_flow_ai_response', array( $this, 'mock_ai_response' ), 10, 3 );
    }
    
    /**
     * Clean up after tests
     */
    public function tearDown() {
        // Clean up test data
        wp_delete_post( $this->test_post_id, true );
        
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'content_flow_workflows',
            array( 'id' => $this->test_workflow_id )
        );
        $wpdb->delete(
            $wpdb->prefix . 'content_flow_suggestions',
            array( 'post_id' => $this->test_post_id )
        );
        
        remove_filter( 'wp_content_flow_ai_response', array( $this, 'mock_ai_response' ) );
        
        parent::tearDown();
    }
    
    /**
     * Mock AI provider response
     */
    public function mock_ai_response( $response, $prompt, $provider ) {
        return array(
            'success' => true,
            'content' => 'This is an enhanced version of your content with improved clarity, better engagement, and optimized for SEO. The content now includes more detailed explanations, compelling language, and strategic keyword placement.',
            'suggestions' => array(
                array(
                    'type' => 'clarity',
                    'original' => 'This is original content that needs improvement.',
                    'suggested' => 'This comprehensive content has been significantly enhanced.',
                    'reason' => 'More specific and descriptive language'
                ),
                array(
                    'type' => 'engagement',
                    'original' => 'It lacks detail',
                    'suggested' => 'It provides rich, detailed insights',
                    'reason' => 'Positive framing increases reader engagement'
                )
            ),
            'metadata' => array(
                'tokens_used' => 250,
                'model' => 'gpt-3.5-turbo',
                'provider' => $provider,
                'processing_time' => 1.5
            )
        );
    }
    
    /**
     * Test successful content improvement
     * Contract: POST /ai/improve with valid content returns improved version
     */
    public function test_improve_content_success() {
        wp_set_current_user( $this->author_user );
        
        $request = new WP_REST_Request( 'POST', '/wp-content-flow/v1/ai/improve' );
        $request->set_header( 'content-type', 'application/json' );
        $request->set_body( json_encode( array(
            'content' => 'This is original content that needs improvement. It lacks detail and could be more engaging.',
            'post_id' => $this->test_post_id,
            'workflow_id' => $this->test_workflow_id,
            'improvement_type' => 'comprehensive',
            'parameters' => array(
                'focus_areas' => array('clarity', 'engagement', 'seo'),
                'tone' => 'professional',
                'preserve_style' => true
            )
        ) ) );
        
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        // Assert response status
        $this->assertEquals( 200, $response->get_status() );
        
        // Assert response structure
        $this->assertArrayHasKey( 'success', $data );
        $this->assertTrue( $data['success'] );
        $this->assertArrayHasKey( 'improved_content', $data );
        $this->assertArrayHasKey( 'suggestions', $data );
        $this->assertArrayHasKey( 'suggestion_id', $data );
        
        // Assert improved content is different and longer
        $this->assertNotEquals( 
            'This is original content that needs improvement.',
            $data['improved_content']
        );
        $this->assertGreaterThan(
            strlen('This is original content that needs improvement.'),
            strlen($data['improved_content'])
        );
        
        // Assert suggestions are provided
        $this->assertIsArray( $data['suggestions'] );
        $this->assertNotEmpty( $data['suggestions'] );
        
        // Assert suggestion was saved to database
        global $wpdb;
        $suggestion = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}content_flow_suggestions WHERE id = %d",
            $data['suggestion_id']
        ) );
        $this->assertNotNull( $suggestion );
        $this->assertEquals( $this->test_post_id, $suggestion->post_id );
        $this->assertEquals( $this->test_workflow_id, $suggestion->workflow_id );
        $this->assertEquals( 'pending', $suggestion->status );
    }
    
    /**
     * Test content improvement with specific focus areas
     * Contract: Improvement respects specified focus areas
     */
    public function test_improve_content_with_focus_areas() {
        wp_set_current_user( $this->editor_user );
        
        $request = new WP_REST_Request( 'POST', '/wp-content-flow/v1/ai/improve' );
        $request->set_header( 'content-type', 'application/json' );
        $request->set_body( json_encode( array(
            'content' => 'Short content.',
            'workflow_id' => $this->test_workflow_id,
            'improvement_type' => 'targeted',
            'parameters' => array(
                'focus_areas' => array('length', 'keywords'),
                'target_length' => 500,
                'keywords' => array('WordPress', 'AI', 'content')
            )
        ) ) );
        
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        // Assert successful response
        $this->assertEquals( 200, $response->get_status() );
        $this->assertTrue( $data['success'] );
        
        // Assert content was expanded
        $this->assertGreaterThan( 
            strlen('Short content.'),
            strlen($data['improved_content'])
        );
        
        // Assert improvement metadata
        $this->assertArrayHasKey( 'metadata', $data );
        $this->assertArrayHasKey( 'improvement_stats', $data['metadata'] );
    }
    
    /**
     * Test content improvement without post_id
     * Contract: Can improve standalone content without post association
     */
    public function test_improve_standalone_content() {
        wp_set_current_user( $this->editor_user );
        
        $request = new WP_REST_Request( 'POST', '/wp-content-flow/v1/ai/improve' );
        $request->set_header( 'content-type', 'application/json' );
        $request->set_body( json_encode( array(
            'content' => 'Standalone content that needs improvement.',
            'workflow_id' => $this->test_workflow_id,
            'improvement_type' => 'comprehensive'
        ) ) );
        
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        // Assert successful response
        $this->assertEquals( 200, $response->get_status() );
        $this->assertTrue( $data['success'] );
        
        // Assert no suggestion saved (no post_id)
        $this->assertArrayNotHasKey( 'suggestion_id', $data );
        
        // Assert improved content is returned
        $this->assertNotEmpty( $data['improved_content'] );
    }
    
    /**
     * Test content improvement with empty content
     * Contract: Empty content returns validation error
     */
    public function test_improve_empty_content() {
        wp_set_current_user( $this->editor_user );
        
        $request = new WP_REST_Request( 'POST', '/wp-content-flow/v1/ai/improve' );
        $request->set_header( 'content-type', 'application/json' );
        $request->set_body( json_encode( array(
            'content' => '',
            'workflow_id' => $this->test_workflow_id
        ) ) );
        
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        // Assert 400 response
        $this->assertEquals( 400, $response->get_status() );
        $this->assertEquals( 'invalid_content', $data['code'] );
        $this->assertContains( 'Content cannot be empty', $data['message'] );
    }
    
    /**
     * Test content improvement with invalid workflow
     * Contract: Invalid workflow_id returns 404
     */
    public function test_improve_invalid_workflow() {
        wp_set_current_user( $this->editor_user );
        
        $request = new WP_REST_Request( 'POST', '/wp-content-flow/v1/ai/improve' );
        $request->set_header( 'content-type', 'application/json' );
        $request->set_body( json_encode( array(
            'content' => 'Content to improve',
            'workflow_id' => 999999
        ) ) );
        
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        // Assert 404 response
        $this->assertEquals( 404, $response->get_status() );
        $this->assertEquals( 'workflow_not_found', $data['code'] );
    }
    
    /**
     * Test content improvement without authentication
     * Contract: Unauthenticated requests return 401
     */
    public function test_improve_unauthenticated() {
        wp_set_current_user( 0 );
        
        $request = new WP_REST_Request( 'POST', '/wp-content-flow/v1/ai/improve' );
        $request->set_header( 'content-type', 'application/json' );
        $request->set_body( json_encode( array(
            'content' => 'Content to improve',
            'workflow_id' => $this->test_workflow_id
        ) ) );
        
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        // Assert 401 response
        $this->assertEquals( 401, $response->get_status() );
        $this->assertEquals( 'rest_forbidden', $data['code'] );
    }
    
    /**
     * Test content improvement with contributor role
     * Contract: Contributors need approval for improvements
     */
    public function test_improve_contributor_permission() {
        wp_set_current_user( $this->contributor_user );
        
        $request = new WP_REST_Request( 'POST', '/wp-content-flow/v1/ai/improve' );
        $request->set_header( 'content-type', 'application/json' );
        $request->set_body( json_encode( array(
            'content' => 'Content to improve',
            'post_id' => $this->test_post_id,
            'workflow_id' => $this->test_workflow_id
        ) ) );
        
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        // Contributors can create suggestions but they require approval
        $this->assertEquals( 200, $response->get_status() );
        $this->assertTrue( $data['success'] );
        
        // Assert suggestion requires approval
        if ( isset($data['suggestion_id']) ) {
            global $wpdb;
            $suggestion = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}content_flow_suggestions WHERE id = %d",
                $data['suggestion_id']
            ) );
            $this->assertEquals( 'pending_approval', $suggestion->status );
        }
    }
    
    /**
     * Test content improvement with rate limiting
     * Contract: Rate limits are enforced per user
     */
    public function test_improve_rate_limiting() {
        wp_set_current_user( $this->author_user );
        
        // Update settings to enable rate limiting
        update_option( 'wp_content_flow_settings', array(
            'rate_limit_enabled' => true,
            'requests_per_minute' => 2
        ) );
        
        $request_body = json_encode( array(
            'content' => 'Content to improve',
            'workflow_id' => $this->test_workflow_id
        ) );
        
        // First request should succeed
        $request1 = new WP_REST_Request( 'POST', '/wp-content-flow/v1/ai/improve' );
        $request1->set_header( 'content-type', 'application/json' );
        $request1->set_body( $request_body );
        $response1 = rest_do_request( $request1 );
        $this->assertEquals( 200, $response1->get_status() );
        
        // Second request should succeed
        $request2 = new WP_REST_Request( 'POST', '/wp-content-flow/v1/ai/improve' );
        $request2->set_header( 'content-type', 'application/json' );
        $request2->set_body( $request_body );
        $response2 = rest_do_request( $request2 );
        $this->assertEquals( 200, $response2->get_status() );
        
        // Third request should be rate limited
        $request3 = new WP_REST_Request( 'POST', '/wp-content-flow/v1/ai/improve' );
        $request3->set_header( 'content-type', 'application/json' );
        $request3->set_body( $request_body );
        $response3 = rest_do_request( $request3 );
        
        // Could be 429 (Too Many Requests) or 403 depending on implementation
        $this->assertContains( $response3->get_status(), array(429, 403) );
        
        // Clean up
        delete_option( 'wp_content_flow_settings' );
    }
    
    /**
     * Test content improvement with multiple providers
     * Contract: Can specify different AI providers
     */
    public function test_improve_multiple_providers() {
        wp_set_current_user( $this->admin_user );
        
        // Test with different providers
        $providers = array('openai', 'anthropic', 'google_ai');
        
        foreach ($providers as $provider) {
            // Update workflow to use different provider
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'content_flow_workflows',
                array('ai_provider' => $provider),
                array('id' => $this->test_workflow_id)
            );
            
            $request = new WP_REST_Request( 'POST', '/wp-content-flow/v1/ai/improve' );
            $request->set_header( 'content-type', 'application/json' );
            $request->set_body( json_encode( array(
                'content' => 'Test content for ' . $provider,
                'workflow_id' => $this->test_workflow_id,
                'provider_override' => $provider
            ) ) );
            
            $response = rest_do_request( $request );
            $data = $response->get_data();
            
            // Assert response based on provider availability
            if ($response->get_status() === 200) {
                $this->assertTrue( $data['success'] );
                $this->assertArrayHasKey( 'provider_used', $data['metadata'] );
                $this->assertEquals( $provider, $data['metadata']['provider_used'] );
            } else {
                // Provider might not be configured
                $this->assertContains( $response->get_status(), array(503, 400) );
            }
        }
    }
    
    /**
     * Test content improvement with long content
     * Contract: Handles content exceeding token limits gracefully
     */
    public function test_improve_long_content() {
        wp_set_current_user( $this->editor_user );
        
        // Generate very long content (10,000 words)
        $long_content = str_repeat('This is a test sentence that will be repeated many times. ', 1000);
        
        $request = new WP_REST_Request( 'POST', '/wp-content-flow/v1/ai/improve' );
        $request->set_header( 'content-type', 'application/json' );
        $request->set_body( json_encode( array(
            'content' => $long_content,
            'workflow_id' => $this->test_workflow_id,
            'parameters' => array(
                'chunking' => true,
                'max_chunk_size' => 1000
            )
        ) ) );
        
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        // Should either succeed with chunking or return appropriate error
        if ($response->get_status() === 200) {
            $this->assertTrue( $data['success'] );
            $this->assertArrayHasKey( 'chunked_processing', $data['metadata'] );
        } else {
            $this->assertEquals( 413, $response->get_status() ); // Payload Too Large
            $this->assertEquals( 'content_too_long', $data['code'] );
        }
    }
}