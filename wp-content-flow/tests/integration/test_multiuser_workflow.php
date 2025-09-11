<?php
/**
 * Integration Test: Multi-User Workflow Collaboration
 * 
 * Tests collaborative content workflows with multiple users, including
 * role-based permissions, concurrent editing, and workflow assignments.
 *
 * @package WP_Content_Flow
 * @subpackage Tests\Integration
 */

class Test_Multiuser_Workflow_Integration extends WP_Content_Flow_Test_Case {
    
    /**
     * Test users for different roles
     */
    private $admin_user;
    private $editor_user_1;
    private $editor_user_2;
    private $author_user_1;
    private $author_user_2;
    private $contributor_user;
    private $subscriber_user;
    
    /**
     * Test data
     */
    private $collaborative_post_id;
    private $team_workflow_id;
    private $review_workflow_id;
    private $approval_queue = array();
    
    /**
     * Set up test environment
     */
    public function setUp() {
        parent::setUp();
        
        // Create test users for collaboration
        $this->create_test_users();
        
        // Create collaborative content
        $this->create_collaborative_content();
        
        // Set up team workflows
        $this->setup_team_workflows();
        
        // Configure collaboration settings
        $this->configure_collaboration_settings();
    }
    
    /**
     * Create test users with different roles
     */
    private function create_test_users() {
        $this->admin_user = $this->factory->user->create( array(
            'role' => 'administrator',
            'user_login' => 'admin_collab',
            'display_name' => 'Admin User'
        ) );
        
        $this->editor_user_1 = $this->factory->user->create( array(
            'role' => 'editor',
            'user_login' => 'editor1_collab',
            'display_name' => 'Editor One'
        ) );
        
        $this->editor_user_2 = $this->factory->user->create( array(
            'role' => 'editor',
            'user_login' => 'editor2_collab',
            'display_name' => 'Editor Two'
        ) );
        
        $this->author_user_1 = $this->factory->user->create( array(
            'role' => 'author',
            'user_login' => 'author1_collab',
            'display_name' => 'Author One'
        ) );
        
        $this->author_user_2 = $this->factory->user->create( array(
            'role' => 'author',
            'user_login' => 'author2_collab',
            'display_name' => 'Author Two'
        ) );
        
        $this->contributor_user = $this->factory->user->create( array(
            'role' => 'contributor',
            'user_login' => 'contributor_collab',
            'display_name' => 'Contributor User'
        ) );
        
        $this->subscriber_user = $this->factory->user->create( array(
            'role' => 'subscriber',
            'user_login' => 'subscriber_collab',
            'display_name' => 'Subscriber User'
        ) );
    }
    
    /**
     * Create collaborative content
     */
    private function create_collaborative_content() {
        wp_set_current_user( $this->author_user_1 );
        
        $this->collaborative_post_id = wp_insert_post( array(
            'post_title' => 'Collaborative Article',
            'post_content' => 'Initial draft created by Author One. This content will be improved collaboratively.',
            'post_status' => 'draft',
            'post_author' => $this->author_user_1,
            'meta_input' => array(
                '_collaboration_enabled' => true,
                '_assigned_editors' => array($this->editor_user_1, $this->editor_user_2),
                '_assigned_contributors' => array($this->contributor_user)
            )
        ) );
    }
    
    /**
     * Set up team workflows
     */
    private function setup_team_workflows() {
        global $wpdb;
        
        // Team content generation workflow
        $wpdb->insert(
            $wpdb->prefix . 'content_flow_workflows',
            array(
                'name' => 'Team Content Generation',
                'description' => 'Collaborative content creation workflow',
                'workflow_type' => 'content_generation',
                'trigger_type' => 'manual',
                'ai_provider' => 'openai',
                'prompt_template' => 'Generate collaborative content: {prompt}',
                'parameters' => json_encode(array(
                    'max_tokens' => 2000,
                    'temperature' => 0.8,
                    'collaboration_mode' => true,
                    'require_approval' => true,
                    'min_approvers' => 2
                )),
                'is_active' => true,
                'created_by' => $this->admin_user
            )
        );
        $this->team_workflow_id = $wpdb->insert_id;
        
        // Review and approval workflow
        $wpdb->insert(
            $wpdb->prefix . 'content_flow_workflows',
            array(
                'name' => 'Content Review Workflow',
                'description' => 'Multi-stage review and approval process',
                'workflow_type' => 'review_approval',
                'trigger_type' => 'automatic',
                'ai_provider' => 'anthropic',
                'prompt_template' => 'Review and suggest improvements: {content}',
                'parameters' => json_encode(array(
                    'review_stages' => array('initial', 'technical', 'final'),
                    'reviewers_per_stage' => 2,
                    'consensus_required' => true
                )),
                'is_active' => true,
                'created_by' => $this->admin_user
            )
        );
        $this->review_workflow_id = $wpdb->insert_id;
    }
    
    /**
     * Configure collaboration settings
     */
    private function configure_collaboration_settings() {
        update_option( 'wp_content_flow_collaboration', array(
            'enabled' => true,
            'real_time_notifications' => true,
            'concurrent_editing' => true,
            'version_control' => true,
            'conflict_resolution' => 'manual',
            'activity_tracking' => true
        ) );
        
        // Mock real-time collaboration
        add_filter( 'wp_content_flow_collaboration_update', array( $this, 'mock_collaboration_update' ), 10, 2 );
    }
    
    /**
     * Mock collaboration update
     */
    public function mock_collaboration_update( $data, $user_id ) {
        // Simulate real-time update broadcast
        do_action( 'wp_content_flow_collaboration_broadcast', $data, $user_id );
        return $data;
    }
    
    /**
     * Test multi-user content generation workflow
     */
    public function test_collaborative_content_generation() {
        global $wpdb;
        
        // Step 1: Contributor suggests content
        wp_set_current_user( $this->contributor_user );
        
        $suggestion_request = new WP_REST_Request( 'POST', '/wp-content-flow/v1/ai/generate' );
        $suggestion_request->set_header( 'content-type', 'application/json' );
        $suggestion_request->set_body( json_encode( array(
            'prompt' => 'Write about collaborative workflows in WordPress',
            'workflow_id' => $this->team_workflow_id,
            'post_id' => $this->collaborative_post_id
        ) ) );
        
        $suggestion_response = rest_do_request( $suggestion_request );
        $suggestion_data = $suggestion_response->get_data();
        
        $this->assertEquals( 200, $suggestion_response->get_status() );
        $suggestion_id = $suggestion_data['suggestion_id'];
        
        // Verify suggestion requires approval (contributor permission)
        $suggestion = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}content_flow_suggestions WHERE id = %d",
            $suggestion_id
        ) );
        $this->assertEquals( 'pending_approval', $suggestion->status );
        
        // Step 2: First author reviews and approves
        wp_set_current_user( $this->author_user_1 );
        
        $review_request = new WP_REST_Request( 'POST', '/wp-content-flow/v1/suggestions/' . $suggestion_id . '/review' );
        $review_request->set_body( json_encode( array(
            'action' => 'approve',
            'comments' => 'Good starting point, needs minor adjustments'
        ) ) );
        
        $review_response = rest_do_request( $review_request );
        $this->assertEquals( 200, $review_response->get_status() );
        
        // Step 3: Second author also reviews (consensus required)
        wp_set_current_user( $this->author_user_2 );
        
        $review_request2 = new WP_REST_Request( 'POST', '/wp-content-flow/v1/suggestions/' . $suggestion_id . '/review' );
        $review_request2->set_body( json_encode( array(
            'action' => 'approve',
            'comments' => 'Agreed, ready for editor review'
        ) ) );
        
        $review_response2 = rest_do_request( $review_request2 );
        $this->assertEquals( 200, $review_response2->get_status() );
        
        // Step 4: Editor makes final approval
        wp_set_current_user( $this->editor_user_1 );
        
        $final_request = new WP_REST_Request( 'POST', '/wp-content-flow/v1/suggestions/' . $suggestion_id . '/accept' );
        $final_request->set_body( json_encode( array(
            'apply_to_post' => true
        ) ) );
        
        $final_response = rest_do_request( $final_request );
        $this->assertEquals( 200, $final_response->get_status() );
        
        // Verify collaboration history
        $history = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}content_flow_history WHERE post_id = %d ORDER BY created_at ASC",
            $this->collaborative_post_id
        ) );
        
        $this->assertGreaterThanOrEqual( 3, count($history) );
        
        // Verify all participants are recorded
        $participants = array_unique( array_column($history, 'user_id') );
        $this->assertContains( $this->contributor_user, $participants );
        $this->assertContains( $this->author_user_1, $participants );
        $this->assertContains( $this->author_user_2, $participants );
    }
    
    /**
     * Test concurrent editing detection and resolution
     */
    public function test_concurrent_editing_conflict_resolution() {
        // Simulate two users editing simultaneously
        $edit_data_user1 = array(
            'content' => 'Version edited by Editor One',
            'post_id' => $this->collaborative_post_id,
            'workflow_id' => $this->team_workflow_id
        );
        
        $edit_data_user2 = array(
            'content' => 'Version edited by Editor Two',
            'post_id' => $this->collaborative_post_id,
            'workflow_id' => $this->team_workflow_id
        );
        
        // Editor 1 starts editing
        wp_set_current_user( $this->editor_user_1 );
        $lock_request1 = new WP_REST_Request( 'POST', '/wp-content-flow/v1/posts/' . $this->collaborative_post_id . '/lock' );
        $lock_response1 = rest_do_request( $lock_request1 );
        
        if ( $lock_response1->get_status() === 200 ) {
            // Editor 2 tries to edit while locked
            wp_set_current_user( $this->editor_user_2 );
            $lock_request2 = new WP_REST_Request( 'POST', '/wp-content-flow/v1/posts/' . $this->collaborative_post_id . '/lock' );
            $lock_response2 = rest_do_request( $lock_request2 );
            
            // Should get conflict or wait status
            $this->assertContains( $lock_response2->get_status(), array(409, 423) );
            
            // Editor 1 saves changes
            wp_set_current_user( $this->editor_user_1 );
            $save_request1 = new WP_REST_Request( 'POST', '/wp-content-flow/v1/ai/improve' );
            $save_request1->set_body( json_encode( $edit_data_user1 ) );
            $save_response1 = rest_do_request( $save_request1 );
            
            $this->assertEquals( 200, $save_response1->get_status() );
            
            // Release lock
            $unlock_request = new WP_REST_Request( 'DELETE', '/wp-content-flow/v1/posts/' . $this->collaborative_post_id . '/lock' );
            rest_do_request( $unlock_request );
            
            // Editor 2 can now edit
            wp_set_current_user( $this->editor_user_2 );
            $save_request2 = new WP_REST_Request( 'POST', '/wp-content-flow/v1/ai/improve' );
            $save_request2->set_body( json_encode( $edit_data_user2 ) );
            $save_response2 = rest_do_request( $save_request2 );
            
            $this->assertEquals( 200, $save_response2->get_status() );
        }
    }
    
    /**
     * Test role-based workflow permissions
     */
    public function test_role_based_workflow_permissions() {
        global $wpdb;
        
        // Test permission matrix
        $permission_tests = array(
            array(
                'user' => $this->admin_user,
                'can_create' => true,
                'can_approve' => true,
                'can_delete' => true
            ),
            array(
                'user' => $this->editor_user_1,
                'can_create' => true,
                'can_approve' => true,
                'can_delete' => false
            ),
            array(
                'user' => $this->author_user_1,
                'can_create' => true,
                'can_approve' => false,
                'can_delete' => false
            ),
            array(
                'user' => $this->contributor_user,
                'can_create' => true,
                'can_approve' => false,
                'can_delete' => false
            ),
            array(
                'user' => $this->subscriber_user,
                'can_create' => false,
                'can_approve' => false,
                'can_delete' => false
            )
        );
        
        foreach ( $permission_tests as $test ) {
            wp_set_current_user( $test['user'] );
            
            // Test create permission
            $create_request = new WP_REST_Request( 'POST', '/wp-content-flow/v1/ai/generate' );
            $create_request->set_body( json_encode( array(
                'prompt' => 'Test prompt',
                'workflow_id' => $this->team_workflow_id
            ) ) );
            $create_response = rest_do_request( $create_request );
            
            if ( $test['can_create'] ) {
                $this->assertEquals( 200, $create_response->get_status() );
                $suggestion_id = $create_response->get_data()['suggestion_id'];
                
                // Test approve permission
                if ( $suggestion_id ) {
                    $approve_request = new WP_REST_Request( 'POST', '/wp-content-flow/v1/suggestions/' . $suggestion_id . '/accept' );
                    $approve_response = rest_do_request( $approve_request );
                    
                    if ( $test['can_approve'] ) {
                        $this->assertEquals( 200, $approve_response->get_status() );
                    } else {
                        $this->assertEquals( 403, $approve_response->get_status() );
                    }
                }
                
                // Test delete permission
                $delete_request = new WP_REST_Request( 'DELETE', '/wp-content-flow/v1/workflows/' . $this->team_workflow_id );
                $delete_response = rest_do_request( $delete_request );
                
                if ( $test['can_delete'] ) {
                    $this->assertContains( $delete_response->get_status(), array(204, 403) );
                } else {
                    $this->assertEquals( 403, $delete_response->get_status() );
                }
            } else {
                $this->assertContains( $create_response->get_status(), array(401, 403) );
            }
        }
    }
    
    /**
     * Test workflow assignment and notifications
     */
    public function test_workflow_assignment_notifications() {
        global $wpdb;
        
        // Create workflow assignment
        wp_set_current_user( $this->admin_user );
        
        $assignment_data = array(
            'workflow_id' => $this->review_workflow_id,
            'post_id' => $this->collaborative_post_id,
            'assigned_users' => array(
                $this->editor_user_1,
                $this->editor_user_2,
                $this->author_user_1
            ),
            'due_date' => date('Y-m-d H:i:s', strtotime('+2 days')),
            'priority' => 'high',
            'instructions' => 'Please review and improve this collaborative content'
        );
        
        $assign_request = new WP_REST_Request( 'POST', '/wp-content-flow/v1/workflows/' . $this->review_workflow_id . '/assign' );
        $assign_request->set_body( json_encode( $assignment_data ) );
        $assign_response = rest_do_request( $assign_request );
        
        if ( $assign_response->get_status() === 200 ) {
            $assignment_id = $assign_response->get_data()['assignment_id'];
            
            // Verify assignments were created
            $assignments = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}content_flow_assignments WHERE workflow_id = %d",
                $this->review_workflow_id
            ) );
            
            $this->assertCount( 3, $assignments );
            
            // Verify notifications were sent
            $this->assertTrue( did_action('wp_content_flow_workflow_assigned') > 0 );
            
            // Test assignment completion by each user
            foreach ( $assignment_data['assigned_users'] as $user_id ) {
                wp_set_current_user( $user_id );
                
                $complete_request = new WP_REST_Request( 'POST', '/wp-content-flow/v1/assignments/' . $assignment_id . '/complete' );
                $complete_request->set_body( json_encode( array(
                    'status' => 'completed',
                    'notes' => 'Review completed by user ' . $user_id
                ) ) );
                
                $complete_response = rest_do_request( $complete_request );
                $this->assertEquals( 200, $complete_response->get_status() );
            }
            
            // Verify workflow completion
            $workflow_status = $wpdb->get_var( $wpdb->prepare(
                "SELECT status FROM {$wpdb->prefix}content_flow_assignments WHERE id = %d",
                $assignment_id
            ) );
            
            $this->assertEquals( 'completed', $workflow_status );
        }
    }
    
    /**
     * Test team activity tracking
     */
    public function test_team_activity_tracking() {
        global $wpdb;
        
        // Simulate team activities
        $activities = array(
            array('user' => $this->author_user_1, 'action' => 'draft_created'),
            array('user' => $this->contributor_user, 'action' => 'suggestion_added'),
            array('user' => $this->author_user_2, 'action' => 'content_reviewed'),
            array('user' => $this->editor_user_1, 'action' => 'content_approved'),
            array('user' => $this->editor_user_2, 'action' => 'content_published')
        );
        
        foreach ( $activities as $activity ) {
            wp_set_current_user( $activity['user'] );
            
            // Trigger activity
            do_action( 'wp_content_flow_activity', array(
                'action' => $activity['action'],
                'post_id' => $this->collaborative_post_id,
                'user_id' => $activity['user'],
                'timestamp' => current_time('mysql')
            ) );
        }
        
        // Retrieve team activity report
        $activity_request = new WP_REST_Request( 'GET', '/wp-content-flow/v1/posts/' . $this->collaborative_post_id . '/activity' );
        $activity_response = rest_do_request( $activity_request );
        
        if ( $activity_response->get_status() === 200 ) {
            $activity_data = $activity_response->get_data();
            
            $this->assertArrayHasKey( 'activities', $activity_data );
            $this->assertArrayHasKey( 'participants', $activity_data );
            $this->assertArrayHasKey( 'timeline', $activity_data );
            
            // Verify all participants are tracked
            $this->assertCount( 5, $activity_data['participants'] );
            
            // Verify activity timeline
            $this->assertGreaterThanOrEqual( 5, count($activity_data['activities']) );
        }
    }
    
    /**
     * Test collaborative revision management
     */
    public function test_collaborative_revision_management() {
        // Create multiple revisions by different users
        $revisions = array();
        
        // Author creates initial revision
        wp_set_current_user( $this->author_user_1 );
        wp_update_post( array(
            'ID' => $this->collaborative_post_id,
            'post_content' => 'Revision 1 by Author'
        ) );
        $revisions[] = wp_save_post_revision( $this->collaborative_post_id );
        
        // Editor improves content
        wp_set_current_user( $this->editor_user_1 );
        wp_update_post( array(
            'ID' => $this->collaborative_post_id,
            'post_content' => 'Revision 2 improved by Editor'
        ) );
        $revisions[] = wp_save_post_revision( $this->collaborative_post_id );
        
        // Contributor suggests changes
        wp_set_current_user( $this->contributor_user );
        wp_update_post( array(
            'ID' => $this->collaborative_post_id,
            'post_content' => 'Revision 3 with contributor suggestions'
        ) );
        $revisions[] = wp_save_post_revision( $this->collaborative_post_id );
        
        // Get revision history
        $revision_request = new WP_REST_Request( 'GET', '/wp-content-flow/v1/posts/' . $this->collaborative_post_id . '/revisions' );
        $revision_response = rest_do_request( $revision_request );
        
        if ( $revision_response->get_status() === 200 ) {
            $revision_data = $revision_response->get_data();
            
            // Verify all revisions are tracked
            $this->assertGreaterThanOrEqual( 3, count($revision_data) );
            
            // Verify revision authors
            foreach ( $revision_data as $revision ) {
                $this->assertArrayHasKey( 'author', $revision );
                $this->assertArrayHasKey( 'date', $revision );
                $this->assertArrayHasKey( 'content', $revision );
            }
            
            // Test revision comparison
            $compare_request = new WP_REST_Request( 'GET', '/wp-content-flow/v1/posts/' . $this->collaborative_post_id . '/compare' );
            $compare_request->set_query_params( array(
                'from' => $revisions[0],
                'to' => $revisions[2]
            ) );
            
            $compare_response = rest_do_request( $compare_request );
            
            if ( $compare_response->get_status() === 200 ) {
                $diff_data = $compare_response->get_data();
                $this->assertArrayHasKey( 'diff', $diff_data );
                $this->assertArrayHasKey( 'changes', $diff_data );
            }
        }
    }
    
    /**
     * Clean up after tests
     */
    public function tearDown() {
        // Clean up posts
        wp_delete_post( $this->collaborative_post_id, true );
        
        // Clean up database
        global $wpdb;
        
        $wpdb->delete(
            $wpdb->prefix . 'content_flow_workflows',
            array( 'id' => $this->team_workflow_id )
        );
        
        $wpdb->delete(
            $wpdb->prefix . 'content_flow_workflows',
            array( 'id' => $this->review_workflow_id )
        );
        
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}content_flow_suggestions WHERE post_id = %d",
            $this->collaborative_post_id
        ) );
        
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}content_flow_history WHERE post_id = %d",
            $this->collaborative_post_id
        ) );
        
        $wpdb->query( "DELETE FROM {$wpdb->prefix}content_flow_assignments" );
        
        // Remove filters
        remove_filter( 'wp_content_flow_collaboration_update', array( $this, 'mock_collaboration_update' ) );
        
        // Clean up options
        delete_option( 'wp_content_flow_collaboration' );
        
        parent::tearDown();
    }
}