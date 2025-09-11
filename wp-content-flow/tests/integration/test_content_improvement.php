<?php
/**
 * Integration Test: Content Improvement Workflow
 * 
 * Tests the complete content improvement workflow from request to approval,
 * including AI provider integration, suggestion management, and user permissions.
 *
 * @package WP_Content_Flow
 * @subpackage Tests\Integration
 */

class Test_Content_Improvement_Integration extends WP_Content_Flow_Test_Case {
    
    /**
     * Test users
     */
    private $admin_user;
    private $editor_user;
    private $author_user;
    private $contributor_user;
    
    /**
     * Test data
     */
    private $test_post_id;
    private $test_workflow_id;
    private $test_suggestion_id;
    
    /**
     * Set up test environment
     */
    public function setUp() {
        parent::setUp();
        
        // Create test users with different roles
        $this->admin_user = $this->factory->user->create( array( 'role' => 'administrator' ) );
        $this->editor_user = $this->factory->user->create( array( 'role' => 'editor' ) );
        $this->author_user = $this->factory->user->create( array( 'role' => 'author' ) );
        $this->contributor_user = $this->factory->user->create( array( 'role' => 'contributor' ) );
        
        // Create test post with original content
        wp_set_current_user( $this->author_user );
        $this->test_post_id = wp_insert_post( array(
            'post_title' => 'Original Post for Improvement',
            'post_content' => 'This is the original content. It needs improvement for better clarity and engagement.',
            'post_status' => 'draft',
            'post_author' => $this->author_user
        ) );
        
        // Set up improvement workflow
        $this->setup_improvement_workflow();
        
        // Configure AI provider settings
        $this->configure_ai_providers();
    }
    
    /**
     * Set up improvement workflow
     */
    private function setup_improvement_workflow() {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'content_flow_workflows',
            array(
                'name' => 'Content Improvement Integration Test',
                'description' => 'Workflow for testing content improvement',
                'workflow_type' => 'content_improvement',
                'trigger_type' => 'manual',
                'ai_provider' => 'openai',
                'prompt_template' => 'Improve the following content for clarity, engagement, and SEO: {content}',
                'parameters' => json_encode(array(
                    'max_tokens' => 1500,
                    'temperature' => 0.7,
                    'model' => 'gpt-3.5-turbo',
                    'improvement_focus' => array('clarity', 'engagement', 'seo'),
                    'preserve_tone' => true,
                    'target_audience' => 'general'
                )),
                'is_active' => true,
                'created_by' => $this->admin_user
            )
        );
        $this->test_workflow_id = $wpdb->insert_id;
    }
    
    /**
     * Configure AI providers
     */
    private function configure_ai_providers() {
        // Set up mock API keys (these would be real in production)
        update_option( 'wp_content_flow_settings', array(
            'openai_api_key' => 'test_openai_key',
            'anthropic_api_key' => 'test_anthropic_key',
            'google_ai_api_key' => 'test_google_key',
            'default_provider' => 'openai',
            'fallback_provider' => 'anthropic',
            'rate_limit_enabled' => true,
            'requests_per_minute' => 10,
            'cache_enabled' => true,
            'cache_ttl' => 3600
        ) );
        
        // Mock AI provider responses
        add_filter( 'wp_content_flow_ai_response', array( $this, 'mock_ai_improvement_response' ), 10, 3 );
    }
    
    /**
     * Mock AI improvement response
     */
    public function mock_ai_improvement_response( $response, $prompt, $provider ) {
        return array(
            'success' => true,
            'improved_content' => 'This enhanced content delivers superior clarity and engagement. The narrative has been restructured for maximum impact, incorporating SEO best practices while maintaining an authentic voice that resonates with readers.',
            'suggestions' => array(
                array(
                    'type' => 'clarity',
                    'original' => 'This is the original content.',
                    'improved' => 'This enhanced content delivers superior clarity.',
                    'confidence' => 0.92
                ),
                array(
                    'type' => 'engagement',
                    'original' => 'It needs improvement',
                    'improved' => 'The narrative has been restructured for maximum impact',
                    'confidence' => 0.88
                ),
                array(
                    'type' => 'seo',
                    'keywords_added' => array('enhanced', 'superior', 'impact'),
                    'readability_score' => 78,
                    'confidence' => 0.85
                )
            ),
            'metadata' => array(
                'provider' => $provider,
                'model' => 'gpt-3.5-turbo',
                'tokens_used' => 450,
                'processing_time' => 2.3
            )
        );
    }
    
    /**
     * Test complete content improvement workflow
     */
    public function test_complete_improvement_workflow() {
        // Step 1: Author requests content improvement
        wp_set_current_user( $this->author_user );
        
        $improvement_request = array(
            'content' => get_post_field( 'post_content', $this->test_post_id ),
            'post_id' => $this->test_post_id,
            'workflow_id' => $this->test_workflow_id,
            'improvement_type' => 'comprehensive',
            'parameters' => array(
                'focus_areas' => array('clarity', 'engagement'),
                'preserve_style' => true
            )
        );
        
        // Make improvement request via REST API
        $request = new WP_REST_Request( 'POST', '/wp-content-flow/v1/ai/improve' );
        $request->set_header( 'content-type', 'application/json' );
        $request->set_body( json_encode( $improvement_request ) );
        
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        // Assert improvement was generated
        $this->assertEquals( 200, $response->get_status() );
        $this->assertTrue( $data['success'] );
        $this->assertNotEmpty( $data['improved_content'] );
        $this->assertArrayHasKey( 'suggestion_id', $data );
        
        $this->test_suggestion_id = $data['suggestion_id'];
        
        // Step 2: Verify suggestion was saved
        global $wpdb;
        $suggestion = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}content_flow_suggestions WHERE id = %d",
            $this->test_suggestion_id
        ) );
        
        $this->assertNotNull( $suggestion );
        $this->assertEquals( $this->test_post_id, $suggestion->post_id );
        $this->assertEquals( $this->test_workflow_id, $suggestion->workflow_id );
        $this->assertEquals( 'pending', $suggestion->status );
        
        // Step 3: Editor reviews the suggestion
        wp_set_current_user( $this->editor_user );
        
        $review_request = new WP_REST_Request( 'GET', '/wp-content-flow/v1/suggestions/' . $this->test_suggestion_id );
        $review_response = rest_do_request( $review_request );
        $review_data = $review_response->get_data();
        
        $this->assertEquals( 200, $review_response->get_status() );
        $this->assertEquals( $data['improved_content'], $review_data['suggested_content'] );
        
        // Step 4: Editor approves the suggestion
        $approve_request = new WP_REST_Request( 'POST', '/wp-content-flow/v1/suggestions/' . $this->test_suggestion_id . '/accept' );
        $approve_request->set_header( 'content-type', 'application/json' );
        $approve_request->set_body( json_encode( array(
            'apply_to_post' => true,
            'create_revision' => true
        ) ) );
        
        $approve_response = rest_do_request( $approve_request );
        $approve_data = $approve_response->get_data();
        
        $this->assertEquals( 200, $approve_response->get_status() );
        $this->assertTrue( $approve_data['success'] );
        
        // Step 5: Verify post was updated
        $updated_post = get_post( $this->test_post_id );
        $this->assertContains( 'enhanced content', $updated_post->post_content );
        
        // Step 6: Verify history was recorded
        $history = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}content_flow_history WHERE post_id = %d ORDER BY created_at DESC",
            $this->test_post_id
        ) );
        
        $this->assertNotEmpty( $history );
        $latest_history = $history[0];
        $this->assertEquals( 'content_improved', $latest_history->action );
        $this->assertEquals( $this->editor_user, $latest_history->user_id );
        
        // Step 7: Verify WordPress hooks fired
        $this->assertTrue( did_action( 'wp_content_flow_content_improved' ) > 0 );
        $this->assertTrue( did_action( 'wp_content_flow_suggestion_accepted' ) > 0 );
    }
    
    /**
     * Test improvement workflow with multiple iterations
     */
    public function test_iterative_improvement_workflow() {
        wp_set_current_user( $this->author_user );
        
        $original_content = get_post_field( 'post_content', $this->test_post_id );
        $improved_versions = array();
        
        // Perform 3 iterations of improvement
        for ( $i = 1; $i <= 3; $i++ ) {
            $request = new WP_REST_Request( 'POST', '/wp-content-flow/v1/ai/improve' );
            $request->set_header( 'content-type', 'application/json' );
            $request->set_body( json_encode( array(
                'content' => $i === 1 ? $original_content : $improved_versions[$i - 1],
                'post_id' => $this->test_post_id,
                'workflow_id' => $this->test_workflow_id,
                'improvement_type' => 'iterative',
                'parameters' => array(
                    'iteration' => $i,
                    'focus' => $i === 1 ? 'clarity' : ($i === 2 ? 'engagement' : 'polish')
                )
            ) ) );
            
            $response = rest_do_request( $request );
            $data = $response->get_data();
            
            $this->assertEquals( 200, $response->get_status() );
            $this->assertTrue( $data['success'] );
            
            $improved_versions[$i] = $data['improved_content'];
            
            // Verify each version is different
            if ( $i > 1 ) {
                $this->assertNotEquals( $improved_versions[$i], $improved_versions[$i - 1] );
            }
        }
        
        // Verify all iterations were tracked
        global $wpdb;
        $suggestions = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}content_flow_suggestions WHERE post_id = %d ORDER BY created_at ASC",
            $this->test_post_id
        ) );
        
        $this->assertCount( 3, $suggestions );
        
        // Verify each iteration has different metadata
        foreach ( $suggestions as $index => $suggestion ) {
            $metadata = json_decode( $suggestion->metadata, true );
            $this->assertEquals( $index + 1, $metadata['iteration'] );
        }
    }
    
    /**
     * Test improvement with contributor permissions
     */
    public function test_contributor_improvement_requires_approval() {
        wp_set_current_user( $this->contributor_user );
        
        // Contributor creates improvement suggestion
        $request = new WP_REST_Request( 'POST', '/wp-content-flow/v1/ai/improve' );
        $request->set_header( 'content-type', 'application/json' );
        $request->set_body( json_encode( array(
            'content' => get_post_field( 'post_content', $this->test_post_id ),
            'post_id' => $this->test_post_id,
            'workflow_id' => $this->test_workflow_id
        ) ) );
        
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        $this->assertEquals( 200, $response->get_status() );
        
        // Verify suggestion requires approval
        global $wpdb;
        $suggestion = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}content_flow_suggestions WHERE id = %d",
            $data['suggestion_id']
        ) );
        
        $this->assertEquals( 'pending_approval', $suggestion->status );
        
        // Contributor cannot self-approve
        wp_set_current_user( $this->contributor_user );
        $approve_request = new WP_REST_Request( 'POST', '/wp-content-flow/v1/suggestions/' . $data['suggestion_id'] . '/accept' );
        $approve_response = rest_do_request( $approve_request );
        
        $this->assertEquals( 403, $approve_response->get_status() );
        
        // Editor can approve contributor's suggestion
        wp_set_current_user( $this->editor_user );
        $approve_request2 = new WP_REST_Request( 'POST', '/wp-content-flow/v1/suggestions/' . $data['suggestion_id'] . '/accept' );
        $approve_response2 = rest_do_request( $approve_request2 );
        
        $this->assertEquals( 200, $approve_response2->get_status() );
    }
    
    /**
     * Test improvement with caching
     */
    public function test_improvement_caching() {
        wp_set_current_user( $this->author_user );
        
        $request_body = json_encode( array(
            'content' => 'Test content for caching',
            'workflow_id' => $this->test_workflow_id
        ) );
        
        // First request - should hit AI provider
        $request1 = new WP_REST_Request( 'POST', '/wp-content-flow/v1/ai/improve' );
        $request1->set_header( 'content-type', 'application/json' );
        $request1->set_body( $request_body );
        
        $start_time = microtime( true );
        $response1 = rest_do_request( $request1 );
        $time1 = microtime( true ) - $start_time;
        
        $this->assertEquals( 200, $response1->get_status() );
        
        // Second identical request - should hit cache
        $request2 = new WP_REST_Request( 'POST', '/wp-content-flow/v1/ai/improve' );
        $request2->set_header( 'content-type', 'application/json' );
        $request2->set_body( $request_body );
        
        $start_time = microtime( true );
        $response2 = rest_do_request( $request2 );
        $time2 = microtime( true ) - $start_time;
        
        $this->assertEquals( 200, $response2->get_status() );
        
        // Cached response should be faster
        $this->assertLessThan( $time1, $time2 );
        
        // Content should be identical
        $this->assertEquals(
            $response1->get_data()['improved_content'],
            $response2->get_data()['improved_content']
        );
    }
    
    /**
     * Test improvement with rate limiting
     */
    public function test_improvement_rate_limiting() {
        wp_set_current_user( $this->author_user );
        
        // Update rate limit to 2 per minute for testing
        update_option( 'wp_content_flow_settings', array_merge(
            get_option( 'wp_content_flow_settings' ),
            array( 'requests_per_minute' => 2 )
        ) );
        
        // Make 3 rapid requests
        for ( $i = 1; $i <= 3; $i++ ) {
            $request = new WP_REST_Request( 'POST', '/wp-content-flow/v1/ai/improve' );
            $request->set_header( 'content-type', 'application/json' );
            $request->set_body( json_encode( array(
                'content' => 'Test content ' . $i,
                'workflow_id' => $this->test_workflow_id
            ) ) );
            
            $response = rest_do_request( $request );
            
            if ( $i <= 2 ) {
                // First 2 requests should succeed
                $this->assertEquals( 200, $response->get_status() );
            } else {
                // Third request should be rate limited
                $this->assertContains( $response->get_status(), array(429, 403) );
            }
        }
    }
    
    /**
     * Test improvement rollback functionality
     */
    public function test_improvement_rollback() {
        wp_set_current_user( $this->author_user );
        
        $original_content = get_post_field( 'post_content', $this->test_post_id );
        
        // Create and apply improvement
        $request = new WP_REST_Request( 'POST', '/wp-content-flow/v1/ai/improve' );
        $request->set_header( 'content-type', 'application/json' );
        $request->set_body( json_encode( array(
            'content' => $original_content,
            'post_id' => $this->test_post_id,
            'workflow_id' => $this->test_workflow_id
        ) ) );
        
        $response = rest_do_request( $request );
        $suggestion_id = $response->get_data()['suggestion_id'];
        
        // Apply the improvement
        wp_set_current_user( $this->editor_user );
        $apply_request = new WP_REST_Request( 'POST', '/wp-content-flow/v1/suggestions/' . $suggestion_id . '/accept' );
        $apply_request->set_body( json_encode( array( 'apply_to_post' => true ) ) );
        rest_do_request( $apply_request );
        
        // Verify content was changed
        $improved_content = get_post_field( 'post_content', $this->test_post_id );
        $this->assertNotEquals( $original_content, $improved_content );
        
        // Rollback the improvement
        $rollback_request = new WP_REST_Request( 'POST', '/wp-content-flow/v1/suggestions/' . $suggestion_id . '/rollback' );
        $rollback_response = rest_do_request( $rollback_request );
        
        if ( $rollback_response->get_status() === 200 ) {
            // Verify content was restored
            $restored_content = get_post_field( 'post_content', $this->test_post_id );
            $this->assertEquals( $original_content, $restored_content );
            
            // Verify rollback was recorded in history
            global $wpdb;
            $history = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}content_flow_history WHERE post_id = %d ORDER BY created_at DESC LIMIT 1",
                $this->test_post_id
            ) );
            
            $this->assertEquals( 'content_rolled_back', $history->action );
        }
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
        
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}content_flow_suggestions WHERE post_id = %d",
            $this->test_post_id
        ) );
        
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}content_flow_history WHERE post_id = %d",
            $this->test_post_id
        ) );
        
        // Remove filters
        remove_filter( 'wp_content_flow_ai_response', array( $this, 'mock_ai_improvement_response' ) );
        
        // Clean up options
        delete_option( 'wp_content_flow_settings' );
        
        parent::tearDown();
    }
}