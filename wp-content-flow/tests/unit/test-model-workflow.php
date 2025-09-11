<?php
/**
 * Unit Tests: Workflow Model
 * 
 * Tests the Workflow model including CRUD operations,
 * validation, and relationships.
 *
 * @package WP_Content_Flow
 * @subpackage Tests\Unit
 */

class Test_Model_Workflow extends WP_UnitTestCase {
    
    /**
     * Workflow model instance
     */
    private $model;
    
    /**
     * Test user IDs
     */
    private $admin_user;
    private $editor_user;
    
    /**
     * Set up test environment
     */
    public function setUp() {
        parent::setUp();
        
        // Create test users
        $this->admin_user = $this->factory->user->create( array( 'role' => 'administrator' ) );
        $this->editor_user = $this->factory->user->create( array( 'role' => 'editor' ) );
        
        // Initialize model
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/models/class-workflow-model.php';
        $this->model = new WP_Content_Flow_Workflow_Model();
    }
    
    /**
     * Test workflow creation
     */
    public function test_create_workflow() {
        $workflow_data = array(
            'name' => 'Test Workflow',
            'description' => 'A test workflow for unit testing',
            'type' => 'content_generation',
            'status' => 'active',
            'settings' => array(
                'ai_provider' => 'openai',
                'model' => 'gpt-3.5-turbo',
                'max_tokens' => 500,
                'temperature' => 0.7
            ),
            'steps' => array(
                array(
                    'type' => 'generate',
                    'prompt' => 'Write about {topic}',
                    'order' => 1
                ),
                array(
                    'type' => 'improve',
                    'improvement_type' => 'clarity',
                    'order' => 2
                )
            ),
            'created_by' => $this->admin_user
        );
        
        $workflow_id = $this->model->create( $workflow_data );
        
        $this->assertIsInt( $workflow_id );
        $this->assertGreaterThan( 0, $workflow_id );
        
        // Verify workflow was created
        $workflow = $this->model->get( $workflow_id );
        $this->assertEquals( 'Test Workflow', $workflow['name'] );
        $this->assertEquals( 'content_generation', $workflow['type'] );
        $this->assertEquals( 'active', $workflow['status'] );
    }
    
    /**
     * Test workflow retrieval
     */
    public function test_get_workflow() {
        // Create a workflow
        $workflow_id = $this->model->create( array(
            'name' => 'Retrieval Test',
            'type' => 'content_improvement',
            'created_by' => $this->admin_user
        ) );
        
        // Retrieve the workflow
        $workflow = $this->model->get( $workflow_id );
        
        $this->assertIsArray( $workflow );
        $this->assertEquals( $workflow_id, $workflow['id'] );
        $this->assertEquals( 'Retrieval Test', $workflow['name'] );
        $this->assertEquals( 'content_improvement', $workflow['type'] );
        
        // Test non-existent workflow
        $non_existent = $this->model->get( 999999 );
        $this->assertNull( $non_existent );
    }
    
    /**
     * Test workflow update
     */
    public function test_update_workflow() {
        // Create a workflow
        $workflow_id = $this->model->create( array(
            'name' => 'Original Name',
            'type' => 'content_generation',
            'status' => 'active',
            'created_by' => $this->admin_user
        ) );
        
        // Update the workflow
        $updated = $this->model->update( $workflow_id, array(
            'name' => 'Updated Name',
            'status' => 'inactive',
            'settings' => array(
                'ai_provider' => 'anthropic',
                'model' => 'claude-3'
            )
        ) );
        
        $this->assertTrue( $updated );
        
        // Verify update
        $workflow = $this->model->get( $workflow_id );
        $this->assertEquals( 'Updated Name', $workflow['name'] );
        $this->assertEquals( 'inactive', $workflow['status'] );
        $this->assertEquals( 'anthropic', $workflow['settings']['ai_provider'] );
    }
    
    /**
     * Test workflow deletion
     */
    public function test_delete_workflow() {
        // Create a workflow
        $workflow_id = $this->model->create( array(
            'name' => 'To Delete',
            'type' => 'content_generation',
            'created_by' => $this->admin_user
        ) );
        
        // Delete the workflow
        $deleted = $this->model->delete( $workflow_id );
        $this->assertTrue( $deleted );
        
        // Verify deletion
        $workflow = $this->model->get( $workflow_id );
        $this->assertNull( $workflow );
        
        // Try to delete non-existent workflow
        $deleted_again = $this->model->delete( $workflow_id );
        $this->assertFalse( $deleted_again );
    }
    
    /**
     * Test listing workflows
     */
    public function test_list_workflows() {
        // Create multiple workflows
        $workflows = array();
        for ( $i = 1; $i <= 5; $i++ ) {
            $workflows[] = $this->model->create( array(
                'name' => "Workflow $i",
                'type' => $i % 2 ? 'content_generation' : 'content_improvement',
                'status' => $i <= 3 ? 'active' : 'inactive',
                'created_by' => $i % 2 ? $this->admin_user : $this->editor_user
            ) );
        }
        
        // Test listing all workflows
        $all = $this->model->list();
        $this->assertCount( 5, $all );
        
        // Test filtering by status
        $active = $this->model->list( array( 'status' => 'active' ) );
        $this->assertCount( 3, $active );
        
        // Test filtering by type
        $generation = $this->model->list( array( 'type' => 'content_generation' ) );
        $this->assertCount( 3, $generation );
        
        // Test filtering by user
        $admin_workflows = $this->model->list( array( 'created_by' => $this->admin_user ) );
        $this->assertCount( 3, $admin_workflows );
        
        // Test pagination
        $paginated = $this->model->list( array( 'limit' => 2, 'offset' => 2 ) );
        $this->assertCount( 2, $paginated );
    }
    
    /**
     * Test workflow validation
     */
    public function test_workflow_validation() {
        // Test missing required fields
        $invalid = $this->model->create( array(
            'description' => 'Missing name'
        ) );
        $this->assertWPError( $invalid );
        $this->assertEquals( 'missing_required_field', $invalid->get_error_code() );
        
        // Test invalid type
        $invalid = $this->model->create( array(
            'name' => 'Invalid Type',
            'type' => 'invalid_type',
            'created_by' => $this->admin_user
        ) );
        $this->assertWPError( $invalid );
        $this->assertEquals( 'invalid_workflow_type', $invalid->get_error_code() );
        
        // Test invalid status
        $invalid = $this->model->create( array(
            'name' => 'Invalid Status',
            'type' => 'content_generation',
            'status' => 'invalid_status',
            'created_by' => $this->admin_user
        ) );
        $this->assertWPError( $invalid );
        $this->assertEquals( 'invalid_workflow_status', $invalid->get_error_code() );
    }
    
    /**
     * Test workflow steps
     */
    public function test_workflow_steps() {
        $workflow_id = $this->model->create( array(
            'name' => 'Step Test',
            'type' => 'content_generation',
            'created_by' => $this->admin_user
        ) );
        
        // Add steps
        $steps = array(
            array(
                'type' => 'generate',
                'prompt' => 'Step 1',
                'order' => 1
            ),
            array(
                'type' => 'improve',
                'improvement_type' => 'clarity',
                'order' => 2
            ),
            array(
                'type' => 'translate',
                'target_language' => 'es',
                'order' => 3
            )
        );
        
        $updated = $this->model->update( $workflow_id, array( 'steps' => $steps ) );
        $this->assertTrue( $updated );
        
        // Retrieve and verify steps
        $workflow = $this->model->get( $workflow_id );
        $this->assertCount( 3, $workflow['steps'] );
        $this->assertEquals( 'generate', $workflow['steps'][0]['type'] );
        $this->assertEquals( 'improve', $workflow['steps'][1]['type'] );
        $this->assertEquals( 'translate', $workflow['steps'][2]['type'] );
        
        // Test step validation
        $invalid_steps = array(
            array(
                'type' => 'invalid_step_type',
                'order' => 1
            )
        );
        
        $result = $this->model->update( $workflow_id, array( 'steps' => $invalid_steps ) );
        $this->assertWPError( $result );
    }
    
    /**
     * Test workflow cloning
     */
    public function test_clone_workflow() {
        // Create original workflow
        $original_id = $this->model->create( array(
            'name' => 'Original Workflow',
            'type' => 'content_generation',
            'settings' => array(
                'ai_provider' => 'openai',
                'model' => 'gpt-4'
            ),
            'steps' => array(
                array( 'type' => 'generate', 'prompt' => 'Test', 'order' => 1 )
            ),
            'created_by' => $this->admin_user
        ) );
        
        // Clone the workflow
        $cloned_id = $this->model->clone( $original_id, array(
            'name' => 'Cloned Workflow'
        ) );
        
        $this->assertIsInt( $cloned_id );
        $this->assertNotEquals( $original_id, $cloned_id );
        
        // Verify clone
        $cloned = $this->model->get( $cloned_id );
        $this->assertEquals( 'Cloned Workflow', $cloned['name'] );
        $this->assertEquals( 'content_generation', $cloned['type'] );
        $this->assertEquals( 'openai', $cloned['settings']['ai_provider'] );
        $this->assertCount( 1, $cloned['steps'] );
    }
    
    /**
     * Test workflow permissions
     */
    public function test_workflow_permissions() {
        // Create workflow as admin
        $workflow_id = $this->model->create( array(
            'name' => 'Admin Workflow',
            'type' => 'content_generation',
            'created_by' => $this->admin_user,
            'visibility' => 'private'
        ) );
        
        // Switch to editor user
        wp_set_current_user( $this->editor_user );
        
        // Editor should not see private admin workflow
        $can_view = $this->model->can_user_view( $workflow_id, $this->editor_user );
        $this->assertFalse( $can_view );
        
        // Editor should not be able to edit
        $can_edit = $this->model->can_user_edit( $workflow_id, $this->editor_user );
        $this->assertFalse( $can_edit );
        
        // Make workflow public
        wp_set_current_user( $this->admin_user );
        $this->model->update( $workflow_id, array( 'visibility' => 'public' ) );
        
        // Now editor should see it
        wp_set_current_user( $this->editor_user );
        $can_view = $this->model->can_user_view( $workflow_id, $this->editor_user );
        $this->assertTrue( $can_view );
    }
    
    /**
     * Test workflow statistics
     */
    public function test_workflow_statistics() {
        $workflow_id = $this->model->create( array(
            'name' => 'Stats Test',
            'type' => 'content_generation',
            'created_by' => $this->admin_user
        ) );
        
        // Record usage
        for ( $i = 0; $i < 10; $i++ ) {
            $this->model->record_usage( $workflow_id, array(
                'user_id' => $i % 2 ? $this->admin_user : $this->editor_user,
                'tokens_used' => rand( 100, 500 ),
                'success' => $i !== 5 // One failure
            ) );
        }
        
        // Get statistics
        $stats = $this->model->get_statistics( $workflow_id );
        
        $this->assertEquals( 10, $stats['total_uses'] );
        $this->assertEquals( 9, $stats['successful_uses'] );
        $this->assertEquals( 1, $stats['failed_uses'] );
        $this->assertEquals( 90, $stats['success_rate'] );
        $this->assertGreaterThan( 0, $stats['total_tokens'] );
        $this->assertEquals( 2, $stats['unique_users'] );
    }
    
    /**
     * Test workflow search
     */
    public function test_search_workflows() {
        // Create workflows with different names
        $this->model->create( array(
            'name' => 'Blog Post Generator',
            'type' => 'content_generation',
            'created_by' => $this->admin_user
        ) );
        
        $this->model->create( array(
            'name' => 'Email Template Creator',
            'type' => 'content_generation',
            'created_by' => $this->admin_user
        ) );
        
        $this->model->create( array(
            'name' => 'Product Description Writer',
            'type' => 'content_generation',
            'created_by' => $this->admin_user
        ) );
        
        // Search for "generator"
        $results = $this->model->search( 'generator' );
        $this->assertCount( 1, $results );
        $this->assertContains( 'Generator', $results[0]['name'] );
        
        // Search for "writer"
        $results = $this->model->search( 'writer' );
        $this->assertCount( 1, $results );
        $this->assertContains( 'Writer', $results[0]['name'] );
        
        // Search for partial match
        $results = $this->model->search( 'creat' );
        $this->assertCount( 1, $results );
        $this->assertContains( 'Creator', $results[0]['name'] );
    }
    
    /**
     * Test workflow export/import
     */
    public function test_export_import_workflow() {
        // Create workflow
        $workflow_id = $this->model->create( array(
            'name' => 'Export Test',
            'type' => 'content_generation',
            'settings' => array(
                'ai_provider' => 'openai',
                'model' => 'gpt-4'
            ),
            'steps' => array(
                array( 'type' => 'generate', 'prompt' => 'Test prompt', 'order' => 1 )
            ),
            'created_by' => $this->admin_user
        ) );
        
        // Export workflow
        $exported = $this->model->export( $workflow_id );
        $this->assertIsArray( $exported );
        $this->assertArrayHasKey( 'version', $exported );
        $this->assertArrayHasKey( 'workflow', $exported );
        
        // Delete original
        $this->model->delete( $workflow_id );
        
        // Import workflow
        $imported_id = $this->model->import( $exported );
        $this->assertIsInt( $imported_id );
        
        // Verify import
        $imported = $this->model->get( $imported_id );
        $this->assertEquals( 'Export Test', $imported['name'] );
        $this->assertEquals( 'openai', $imported['settings']['ai_provider'] );
        $this->assertCount( 1, $imported['steps'] );
    }
}