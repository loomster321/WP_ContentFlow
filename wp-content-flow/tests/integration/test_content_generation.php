<?php
/**
 * Integration test for complete content generation workflow
 * 
 * This test MUST FAIL until the full workflow is implemented.
 * Tests the complete user journey from workflow creation to content generation.
 * Following TDD principles: RED → GREEN → Refactor
 */

class Test_Content_Generation_Integration extends WP_Content_Flow_Test_Case {

    /**
     * Test complete content generation workflow from quickstart.md scenario
     * 
     * This integration test covers the entire user journey:
     * 1. Create workflow
     * 2. Generate AI content 
     * 3. Review and accept suggestions
     * 4. Verify content history
     * 
     * @test
     */
    public function test_complete_blog_post_generation_workflow() {
        // Step 1: Create "Blog Post Assistant" workflow (from quickstart.md)
        $workflow_data = array(
            'name' => 'Blog Post Assistant',
            'description' => 'Helps generate and improve blog post content',
            'ai_provider' => 'openai',
            'settings' => array(
                'model' => 'gpt-4',
                'temperature' => 0.7,
                'max_tokens' => 1500,
                'system_prompt' => 'You are a helpful content writing assistant for a WordPress blog.'
            )
        );
        
        $workflow_response = $this->mock_api_request( 'workflows', $workflow_data, 'POST' );
        
        // This MUST fail until workflow creation is implemented
        $this->assertEquals( 201, $workflow_response->get_status() );
        $workflow = $workflow_response->get_data();
        $workflow_id = $workflow['id'];

        // Step 2: Create a new WordPress post
        $post_id = $this->factory->post->create( array(
            'post_title' => 'Sustainable Gardening Guide',
            'post_content' => '', // Start with empty content
            'post_status' => 'draft'
        ) );

        // Step 3: Generate AI content using the prompt from quickstart.md
        $generate_data = array(
            'prompt' => 'Write an introduction about sustainable gardening',
            'workflow_id' => $workflow_id,
            'post_id' => $post_id,
            'parameters' => array(
                'max_tokens' => 500,
                'temperature' => 0.7
            )
        );
        
        $generate_response = $this->mock_api_request( 'ai/generate', $generate_data, 'POST' );
        
        // This MUST fail until AI generation is implemented
        $this->assertEquals( 200, $generate_response->get_status() );
        $suggestion = $generate_response->get_data();
        
        // Verify suggestion structure
        $this->assertArrayHasKey( 'id', $suggestion );
        $this->assertArrayHasKey( 'suggested_content', $suggestion );
        $this->assertArrayHasKey( 'confidence_score', $suggestion );
        $this->assertEquals( 'generation', $suggestion['suggestion_type'] );
        $this->assertEquals( 'pending', $suggestion['status'] );
        
        // Verify content quality expectations
        $this->assertNotEmpty( $suggestion['suggested_content'] );
        $this->assertGreaterThan( 0.7, $suggestion['confidence_score'], 'AI confidence should be > 0.7 as per quickstart.md' );
        $this->assertStringContainsString( 'sustainable', strtolower( $suggestion['suggested_content'] ) );
        $this->assertStringContainsString( 'gardening', strtolower( $suggestion['suggested_content'] ) );

        // Step 4: Accept the AI suggestion (from quickstart.md user flow)
        $accept_response = $this->mock_api_request( "suggestions/{$suggestion['id']}/accept", array(), 'POST' );
        
        // This MUST fail until suggestion acceptance is implemented
        $this->assertEquals( 200, $accept_response->get_status() );
        $accept_data = $accept_response->get_data();
        
        $this->assertTrue( $accept_data['success'] );
        $this->assertStringContainsString( 'accepted', strtolower( $accept_data['message'] ) );

        // Step 5: Verify post content was updated
        $updated_post = get_post( $post_id );
        $this->assertStringContainsString( $suggestion['suggested_content'], $updated_post->post_content );

        // Step 6: Verify content history was created
        $history_response = $this->mock_api_request( "posts/{$post_id}/history", array(), 'GET' );
        
        // This MUST fail until content history is implemented  
        $this->assertEquals( 200, $history_response->get_status() );
        $history = $history_response->get_data();
        
        $this->assertIsArray( $history );
        $this->assertGreaterThan( 0, count( $history ) );
        
        $latest_history = $history[0];
        $this->assertEquals( 'ai_generated', $latest_history['change_type'] );
        $this->assertEquals( $post_id, $latest_history['post_id'] );
        $this->assertEquals( $suggestion['id'], $latest_history['suggestion_id'] );
    }

    /**
     * Test content improvement workflow (from quickstart.md Step 3)
     * 
     * @test
     */
    public function test_content_improvement_workflow() {
        // Setup: Create workflow and post with existing content
        $workflow_id = $this->create_test_workflow_in_database();
        $post_id = $this->factory->post->create( array(
            'post_content' => 'This sentence has grammer errors and could be improved for readibility.'
        ) );

        // Step 1: Request content improvement (from quickstart.md)
        $improve_data = array(
            'content' => 'This sentence has grammer errors and could be improved for readibility.',
            'workflow_id' => $workflow_id,
            'improvement_type' => 'grammar'
        );
        
        $improve_response = $this->mock_api_request( 'ai/improve', $improve_data, 'POST' );
        
        // This MUST fail until content improvement is implemented
        $this->assertEquals( 200, $improve_response->get_status() );
        $suggestions = $improve_response->get_data();
        
        $this->assertIsArray( $suggestions );
        $this->assertGreaterThan( 0, count( $suggestions ) );
        
        $suggestion = $suggestions[0];
        $this->assertEquals( 'improvement', $suggestion['suggestion_type'] );
        $this->assertStringContainsString( 'grammar', $suggestion['suggested_content'] );
        $this->assertNotContains( 'grammer', $suggestion['suggested_content'] ); // Should fix typo
        $this->assertNotContains( 'readibility', $suggestion['suggested_content'] ); // Should fix typo

        // Step 2: Accept individual suggestion
        $accept_response = $this->mock_api_request( "suggestions/{$suggestion['id']}/accept", array(), 'POST' );
        
        $this->assertEquals( 200, $accept_response->get_status() );
        
        // Verify content history tracks the improvement
        $history_response = $this->mock_api_request( "posts/{$post_id}/history", array(), 'GET' );
        $history = $history_response->get_data();
        
        $improvement_entry = array_filter( $history, function( $entry ) {
            return $entry['change_type'] === 'ai_improved';
        } );
        
        $this->assertNotEmpty( $improvement_entry );
    }

    /**
     * Test workflow automation (from quickstart.md Step 4)
     * 
     * @test
     */
    public function test_automated_workflow_execution() {
        // Create workflow with auto-run enabled
        $workflow_data = array(
            'name' => 'Auto Spell Check',
            'description' => 'Automatically check spelling on save',
            'ai_provider' => 'openai',
            'settings' => array(
                'auto_run_on_save' => true,
                'improvement_types' => array( 'grammar', 'spelling' ),
                'auto_apply_threshold' => 0.9
            )
        );
        
        $workflow_response = $this->mock_api_request( 'workflows', $workflow_data, 'POST' );
        $workflow = $workflow_response->get_data();

        // Create post with intentional spelling errors
        $post_id = $this->factory->post->create( array(
            'post_content' => 'This post has severl speling erors that should be automaticaly detected.'
        ) );

        // Simulate saving the post (triggers automation)
        do_action( 'save_post', $post_id );
        
        // This MUST fail until workflow automation hooks are implemented
        
        // Wait briefly for async processing (if applicable)
        sleep( 1 );
        
        // Verify suggestions were automatically generated
        $history_response = $this->mock_api_request( "posts/{$post_id}/history", array(), 'GET' );
        $history = $history_response->get_data();
        
        $auto_suggestions = array_filter( $history, function( $entry ) {
            return $entry['change_type'] === 'ai_improved';
        } );
        
        $this->assertNotEmpty( $auto_suggestions, 'Automated workflow should create improvement suggestions' );
    }

    /**
     * Test multi-user collaboration workflow (from quickstart.md Advanced Features)
     * 
     * @test
     */
    public function test_multi_user_collaboration_workflow() {
        // Create users with different roles
        $content_creator_id = $this->factory->user->create( array( 'role' => 'author' ) );
        $editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
        $reviewer_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

        // Content Creator: Create workflow with approval process
        wp_set_current_user( $content_creator_id );
        
        $workflow_data = array(
            'name' => 'Team Collaboration Workflow',
            'description' => 'Multi-user content creation with approval',
            'ai_provider' => 'openai',
            'settings' => array(
                'require_approval' => true,
                'approval_roles' => array( 'editor', 'administrator' ),
                'notification_settings' => array(
                    'email_on_suggestions' => true
                )
            )
        );
        
        $workflow_response = $this->mock_api_request( 'workflows', $workflow_data, 'POST' );
        $workflow = $workflow_response->get_data();

        // Content Creator: Generate draft content
        $post_id = $this->factory->post->create( array(
            'post_author' => $content_creator_id,
            'post_status' => 'draft'
        ) );
        
        $generate_response = $this->mock_api_request( 'ai/generate', array(
            'prompt' => 'Write a product description for sustainable coffee',
            'workflow_id' => $workflow['id'],
            'post_id' => $post_id
        ), 'POST' );
        
        $suggestion = $generate_response->get_data();
        
        // This MUST fail until user permission system is implemented
        
        // Editor: Review and get AI improvement suggestions
        wp_set_current_user( $editor_id );
        
        $improve_response = $this->mock_api_request( 'ai/improve', array(
            'content' => $suggestion['suggested_content'],
            'workflow_id' => $workflow['id'],
            'improvement_type' => 'engagement'
        ), 'POST' );
        
        $improvements = $improve_response->get_data();
        $this->assertNotEmpty( $improvements );

        // Reviewer: View complete change history
        wp_set_current_user( $reviewer_id );
        
        $history_response = $this->mock_api_request( "posts/{$post_id}/history", array(), 'GET' );
        $history = $history_response->get_data();
        
        // Verify multi-user collaboration tracking
        $user_ids = array_unique( array_column( $history, 'user_id' ) );
        $this->assertContains( $content_creator_id, $user_ids );
        $this->assertContains( $editor_id, $user_ids );
    }

    /**
     * Test error handling and recovery scenarios
     * 
     * @test  
     */
    public function test_workflow_error_handling_and_recovery() {
        $workflow_id = $this->create_test_workflow_in_database();
        $post_id = $this->factory->post->create();

        // Test AI provider failure scenario
        $this->mock_ai_response( 'openai', array( 'error' => 'Rate limit exceeded' ) );
        
        $generate_response = $this->mock_api_request( 'ai/generate', array(
            'prompt' => 'Test prompt',
            'workflow_id' => $workflow_id,
            'post_id' => $post_id
        ), 'POST' );
        
        // Should handle error gracefully
        $this->assertContains( $generate_response->get_status(), array( 429, 500, 502, 503 ) );
        
        // Test recovery after provider comes back online
        $this->mock_ai_response( 'openai', array(
            'content' => 'Successfully generated content',
            'confidence' => 0.8
        ) );
        
        $retry_response = $this->mock_api_request( 'ai/generate', array(
            'prompt' => 'Test prompt',
            'workflow_id' => $workflow_id,
            'post_id' => $post_id
        ), 'POST' );
        
        $this->assertEquals( 200, $retry_response->get_status() );
    }

    /**
     * Test performance requirements from quickstart.md (< 5 seconds)
     * 
     * @test
     */
    public function test_performance_requirements() {
        $workflow_id = $this->create_test_workflow_in_database();
        
        $start_time = microtime( true );
        
        $generate_response = $this->mock_api_request( 'ai/generate', array(
            'prompt' => 'Write a short paragraph about renewable energy',
            'workflow_id' => $workflow_id,
            'parameters' => array(
                'max_tokens' => 200
            )
        ), 'POST' );
        
        $end_time = microtime( true );
        $execution_time = $end_time - $start_time;
        
        // Performance requirement from quickstart.md: AI operations complete within 5 seconds
        $this->assertLessThan( 5.0, $execution_time, 'AI generation should complete within 5 seconds' );
        $this->assertEquals( 200, $generate_response->get_status() );
    }

    /**
     * Helper method to create workflow in database (will be implemented with database schema)
     * 
     * @return int Workflow ID
     */
    private function create_test_workflow_in_database() {
        // This will be implemented when database tables are created
        // For now, return mock ID
        return 1;
    }
}