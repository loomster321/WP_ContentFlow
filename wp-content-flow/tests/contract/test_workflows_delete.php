<?php
/**
 * Contract Test: DELETE /wp-json/wp-content-flow/v1/workflows/{id}
 * 
 * Tests the workflow deletion endpoint according to the OpenAPI contract.
 * This test validates proper deletion, permissions, cascading deletes,
 * and error handling.
 *
 * @package WP_Content_Flow
 * @subpackage Tests\Contract
 */

class Test_Workflows_Delete extends WP_Content_Flow_Test_Case {
    
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
     * Test workflow IDs
     * @var array
     */
    private $test_workflow_ids = array();
    
    /**
     * Set up test fixtures
     */
    public function setUp() {
        parent::setUp();
        
        // Create test users
        $this->admin_user = $this->factory->user->create( array( 'role' => 'administrator' ) );
        $this->editor_user = $this->factory->user->create( array( 'role' => 'editor' ) );
        $this->contributor_user = $this->factory->user->create( array( 'role' => 'contributor' ) );
        
        // Create multiple test workflows
        wp_set_current_user( $this->admin_user );
        $this->create_test_workflows();
    }
    
    /**
     * Create test workflows with associated data
     */
    private function create_test_workflows() {
        global $wpdb;
        
        // Create active workflow
        $wpdb->insert(
            $wpdb->prefix . 'content_flow_workflows',
            array(
                'name' => 'Active Workflow for Deletion',
                'description' => 'This workflow will be deleted',
                'workflow_type' => 'content_generation',
                'trigger_type' => 'manual',
                'ai_provider' => 'openai',
                'prompt_template' => 'Generate content',
                'parameters' => json_encode(array('max_tokens' => 500)),
                'is_active' => true,
                'created_by' => $this->admin_user
            )
        );
        $this->test_workflow_ids['active'] = $wpdb->insert_id;
        
        // Create workflow with history (to test cascading deletes)
        $wpdb->insert(
            $wpdb->prefix . 'content_flow_workflows',
            array(
                'name' => 'Workflow with History',
                'description' => 'Has associated history records',
                'workflow_type' => 'content_improvement',
                'trigger_type' => 'automatic',
                'ai_provider' => 'anthropic',
                'prompt_template' => 'Improve content',
                'parameters' => json_encode(array('max_tokens' => 1000)),
                'is_active' => false,
                'created_by' => $this->editor_user
            )
        );
        $this->test_workflow_ids['with_history'] = $wpdb->insert_id;
        
        // Add history records for the workflow
        for ($i = 1; $i <= 3; $i++) {
            $wpdb->insert(
                $wpdb->prefix . 'content_flow_history',
                array(
                    'workflow_id' => $this->test_workflow_ids['with_history'],
                    'post_id' => $i,
                    'user_id' => $this->admin_user,
                    'action' => 'content_generated',
                    'content_before' => 'Original content ' . $i,
                    'content_after' => 'AI generated content ' . $i,
                    'ai_provider' => 'anthropic',
                    'prompt_used' => 'Test prompt ' . $i,
                    'parameters' => json_encode(array('test' => $i))
                )
            );
        }
        
        // Create workflow owned by editor
        $wpdb->insert(
            $wpdb->prefix . 'content_flow_workflows',
            array(
                'name' => 'Editor Workflow',
                'description' => 'Owned by editor',
                'workflow_type' => 'content_generation',
                'trigger_type' => 'manual',
                'ai_provider' => 'google_ai',
                'prompt_template' => 'Generate content',
                'parameters' => json_encode(array('max_tokens' => 750)),
                'is_active' => true,
                'created_by' => $this->editor_user
            )
        );
        $this->test_workflow_ids['editor_owned'] = $wpdb->insert_id;
    }
    
    /**
     * Clean up after tests
     */
    public function tearDown() {
        global $wpdb;
        
        // Clean up any remaining test workflows
        foreach ($this->test_workflow_ids as $id) {
            $wpdb->delete(
                $wpdb->prefix . 'content_flow_workflows',
                array('id' => $id)
            );
            $wpdb->delete(
                $wpdb->prefix . 'content_flow_history',
                array('workflow_id' => $id)
            );
        }
        
        parent::tearDown();
    }
    
    /**
     * Test successful workflow deletion
     * Contract: DELETE /workflows/{id} returns 204 No Content on success
     */
    public function test_delete_workflow_success() {
        wp_set_current_user( $this->admin_user );
        
        $request = new WP_REST_Request( 'DELETE', '/wp-content-flow/v1/workflows/' . $this->test_workflow_ids['active'] );
        $response = rest_do_request( $request );
        
        // Assert 204 No Content response
        $this->assertEquals( 204, $response->get_status() );
        $this->assertNull( $response->get_data() );
        
        // Verify workflow was actually deleted
        global $wpdb;
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}content_flow_workflows WHERE id = %d",
            $this->test_workflow_ids['active']
        ) );
        $this->assertEquals( 0, $exists );
    }
    
    /**
     * Test workflow deletion with cascading deletes
     * Contract: Deleting workflow removes associated history records
     */
    public function test_delete_workflow_cascading() {
        wp_set_current_user( $this->admin_user );
        
        global $wpdb;
        
        // Verify history records exist before deletion
        $history_count_before = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}content_flow_history WHERE workflow_id = %d",
            $this->test_workflow_ids['with_history']
        ) );
        $this->assertEquals( 3, $history_count_before );
        
        // Delete the workflow
        $request = new WP_REST_Request( 'DELETE', '/wp-content-flow/v1/workflows/' . $this->test_workflow_ids['with_history'] );
        $response = rest_do_request( $request );
        
        // Assert successful deletion
        $this->assertEquals( 204, $response->get_status() );
        
        // Verify workflow was deleted
        $workflow_exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}content_flow_workflows WHERE id = %d",
            $this->test_workflow_ids['with_history']
        ) );
        $this->assertEquals( 0, $workflow_exists );
        
        // Verify history records were also deleted
        $history_count_after = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}content_flow_history WHERE workflow_id = %d",
            $this->test_workflow_ids['with_history']
        ) );
        $this->assertEquals( 0, $history_count_after );
    }
    
    /**
     * Test deletion of non-existent workflow
     * Contract: DELETE to non-existent workflow returns 404
     */
    public function test_delete_workflow_not_found() {
        wp_set_current_user( $this->admin_user );
        
        $request = new WP_REST_Request( 'DELETE', '/wp-content-flow/v1/workflows/999999' );
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        // Assert 404 response
        $this->assertEquals( 404, $response->get_status() );
        $this->assertEquals( 'workflow_not_found', $data['code'] );
        $this->assertContains( 'Workflow not found', $data['message'] );
    }
    
    /**
     * Test workflow deletion without authentication
     * Contract: DELETE without authentication returns 401
     */
    public function test_delete_workflow_unauthenticated() {
        wp_set_current_user( 0 );
        
        $request = new WP_REST_Request( 'DELETE', '/wp-content-flow/v1/workflows/' . $this->test_workflow_ids['active'] );
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        // Assert 401 response
        $this->assertEquals( 401, $response->get_status() );
        $this->assertEquals( 'rest_forbidden', $data['code'] );
        $this->assertContains( 'logged in', $data['message'] );
        
        // Verify workflow was NOT deleted
        global $wpdb;
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}content_flow_workflows WHERE id = %d",
            $this->test_workflow_ids['active']
        ) );
        $this->assertEquals( 1, $exists );
    }
    
    /**
     * Test workflow deletion with insufficient permissions
     * Contract: Contributors cannot delete workflows (403)
     */
    public function test_delete_workflow_insufficient_permissions() {
        wp_set_current_user( $this->contributor_user );
        
        $request = new WP_REST_Request( 'DELETE', '/wp-content-flow/v1/workflows/' . $this->test_workflow_ids['active'] );
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        // Assert 403 response
        $this->assertEquals( 403, $response->get_status() );
        $this->assertEquals( 'rest_forbidden', $data['code'] );
        $this->assertContains( 'permission', $data['message'] );
        
        // Verify workflow was NOT deleted
        global $wpdb;
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}content_flow_workflows WHERE id = %d",
            $this->test_workflow_ids['active']
        ) );
        $this->assertEquals( 1, $exists );
    }
    
    /**
     * Test workflow deletion by editor
     * Contract: Editors can delete their own workflows
     */
    public function test_delete_workflow_editor_permission() {
        wp_set_current_user( $this->editor_user );
        
        $request = new WP_REST_Request( 'DELETE', '/wp-content-flow/v1/workflows/' . $this->test_workflow_ids['editor_owned'] );
        $response = rest_do_request( $request );
        
        // Assert successful deletion
        $this->assertEquals( 204, $response->get_status() );
        
        // Verify workflow was deleted
        global $wpdb;
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}content_flow_workflows WHERE id = %d",
            $this->test_workflow_ids['editor_owned']
        ) );
        $this->assertEquals( 0, $exists );
    }
    
    /**
     * Test deletion with invalid workflow ID format
     * Contract: Invalid ID format returns 400
     */
    public function test_delete_workflow_invalid_id() {
        wp_set_current_user( $this->admin_user );
        
        $request = new WP_REST_Request( 'DELETE', '/wp-content-flow/v1/workflows/invalid_id' );
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        // Assert 400 or 404 response (depends on implementation)
        $this->assertContains( $response->get_status(), array(400, 404) );
    }
    
    /**
     * Test soft delete vs hard delete
     * Contract: Verify if deletion is soft (marked as deleted) or hard (removed from DB)
     */
    public function test_delete_workflow_type() {
        wp_set_current_user( $this->admin_user );
        
        global $wpdb;
        
        // Check if table has a 'deleted_at' or similar column for soft deletes
        $columns = $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->prefix}content_flow_workflows" );
        $has_soft_delete = false;
        foreach ($columns as $column) {
            if (in_array($column->Field, array('deleted_at', 'is_deleted', 'status'))) {
                $has_soft_delete = true;
                break;
            }
        }
        
        $request = new WP_REST_Request( 'DELETE', '/wp-content-flow/v1/workflows/' . $this->test_workflow_ids['active'] );
        $response = rest_do_request( $request );
        
        $this->assertEquals( 204, $response->get_status() );
        
        if ($has_soft_delete) {
            // Test soft delete behavior
            $workflow = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}content_flow_workflows WHERE id = %d",
                $this->test_workflow_ids['active']
            ) );
            
            if ($workflow) {
                // If row still exists, it should be marked as deleted
                $this->assertTrue( 
                    !empty($workflow->deleted_at) || 
                    !empty($workflow->is_deleted) || 
                    $workflow->status === 'deleted',
                    'Workflow should be marked as deleted'
                );
            }
        } else {
            // Test hard delete behavior
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}content_flow_workflows WHERE id = %d",
                $this->test_workflow_ids['active']
            ) );
            $this->assertEquals( 0, $exists, 'Workflow should be completely removed' );
        }
    }
    
    /**
     * Test bulk workflow deletion
     * Contract: Multiple workflows can be deleted (if supported)
     */
    public function test_delete_workflow_bulk() {
        wp_set_current_user( $this->admin_user );
        
        // Try bulk delete endpoint if it exists
        $bulk_ids = array(
            $this->test_workflow_ids['active'],
            $this->test_workflow_ids['editor_owned']
        );
        
        $request = new WP_REST_Request( 'DELETE', '/wp-content-flow/v1/workflows' );
        $request->set_param( 'ids', $bulk_ids );
        $response = rest_do_request( $request );
        
        // If bulk delete is supported
        if ($response->get_status() === 204 || $response->get_status() === 200) {
            global $wpdb;
            
            // Verify all workflows were deleted
            foreach ($bulk_ids as $id) {
                $exists = $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}content_flow_workflows WHERE id = %d",
                    $id
                ) );
                $this->assertEquals( 0, $exists, "Workflow $id should be deleted" );
            }
        } else {
            // If bulk delete is not supported, delete individually
            foreach ($bulk_ids as $id) {
                $request = new WP_REST_Request( 'DELETE', '/wp-content-flow/v1/workflows/' . $id );
                $response = rest_do_request( $request );
                $this->assertEquals( 204, $response->get_status() );
            }
        }
    }
    
    /**
     * Test deletion triggers proper hooks
     * Contract: Deletion should trigger WordPress actions/filters
     */
    public function test_delete_workflow_hooks() {
        wp_set_current_user( $this->admin_user );
        
        $hook_called = false;
        $deleted_id = null;
        
        // Add test action
        add_action( 'wp_content_flow_workflow_deleted', function($workflow_id) use (&$hook_called, &$deleted_id) {
            $hook_called = true;
            $deleted_id = $workflow_id;
        } );
        
        $request = new WP_REST_Request( 'DELETE', '/wp-content-flow/v1/workflows/' . $this->test_workflow_ids['active'] );
        $response = rest_do_request( $request );
        
        $this->assertEquals( 204, $response->get_status() );
        
        // Check if hook was called (if implemented)
        if (has_action('wp_content_flow_workflow_deleted')) {
            $this->assertTrue( $hook_called, 'Deletion hook should be triggered' );
            $this->assertEquals( $this->test_workflow_ids['active'], $deleted_id );
        }
    }
}