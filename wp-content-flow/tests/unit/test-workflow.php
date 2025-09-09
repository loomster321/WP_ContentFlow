<?php
/**
 * PHPUnit tests for WP_Content_Flow_Workflow class
 */

class Test_Workflow extends WP_Content_Flow_Test_Case {

    private $workflow;

    public function setUp(): void {
        parent::setUp();
        $this->workflow = new WP_Content_Flow_Workflow();
        
        // Ensure clean database state
        $this->clean_workflow_tables();
    }

    public function tearDown(): void {
        parent::tearDown();
        $this->clean_workflow_tables();
    }

    /**
     * Clean workflow tables for testing
     */
    private function clean_workflow_tables() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wp_content_flow_workflows';
        $wpdb->query("DELETE FROM $table_name WHERE 1=1");
    }

    /**
     * Test workflow creation
     */
    public function test_create_workflow() {
        $workflow_data = [
            'name' => 'Test Blog Post Workflow',
            'description' => 'A workflow for generating blog posts',
            'ai_provider' => 'openai',
            'settings' => [
                'model' => 'gpt-4',
                'temperature' => 0.7,
                'max_tokens' => 1500
            ],
            'status' => 'active'
        ];

        $workflow_id = $this->workflow->create($workflow_data);

        $this->assertIsInt($workflow_id);
        $this->assertGreaterThan(0, $workflow_id);

        // Verify workflow was saved to database
        $saved_workflow = $this->workflow->get_by_id($workflow_id);
        $this->assertIsArray($saved_workflow);
        $this->assertEquals($workflow_data['name'], $saved_workflow['name']);
        $this->assertEquals($workflow_data['ai_provider'], $saved_workflow['ai_provider']);
        $this->assertEquals($workflow_data['status'], $saved_workflow['status']);
    }

    /**
     * Test workflow creation with invalid data
     */
    public function test_create_workflow_validation() {
        // Test missing required fields
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Workflow name is required');

        $this->workflow->create([
            'description' => 'Missing name',
            'ai_provider' => 'openai'
        ]);
    }

    /**
     * Test workflow name validation
     */
    public function test_workflow_name_validation() {
        // Test empty name
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Workflow name cannot be empty');

        $this->workflow->create([
            'name' => '',
            'ai_provider' => 'openai'
        ]);
    }

    /**
     * Test duplicate workflow names
     */
    public function test_duplicate_workflow_names() {
        $workflow_data = [
            'name' => 'Unique Workflow Name',
            'ai_provider' => 'openai'
        ];

        // Create first workflow
        $first_id = $this->workflow->create($workflow_data);
        $this->assertIsInt($first_id);

        // Try to create duplicate
        $this->expectException(WP_Content_Flow_Duplicate_Exception::class);
        $this->expectExceptionMessage('Workflow with this name already exists');

        $this->workflow->create($workflow_data);
    }

    /**
     * Test workflow retrieval by ID
     */
    public function test_get_workflow_by_id() {
        $workflow_data = [
            'name' => 'Retrieval Test Workflow',
            'description' => 'Test workflow for retrieval',
            'ai_provider' => 'anthropic',
            'settings' => ['temperature' => 0.5]
        ];

        $workflow_id = $this->workflow->create($workflow_data);
        $retrieved = $this->workflow->get_by_id($workflow_id);

        $this->assertIsArray($retrieved);
        $this->assertEquals($workflow_id, $retrieved['id']);
        $this->assertEquals($workflow_data['name'], $retrieved['name']);
        $this->assertEquals($workflow_data['description'], $retrieved['description']);
        $this->assertEquals($workflow_data['ai_provider'], $retrieved['ai_provider']);
        
        // Test settings deserialization
        $this->assertIsArray($retrieved['settings']);
        $this->assertEquals(0.5, $retrieved['settings']['temperature']);
        
        // Test timestamps
        $this->assertArrayHasKey('created_at', $retrieved);
        $this->assertArrayHasKey('updated_at', $retrieved);
    }

    /**
     * Test getting non-existent workflow
     */
    public function test_get_nonexistent_workflow() {
        $result = $this->workflow->get_by_id(999);
        $this->assertNull($result);
    }

    /**
     * Test workflow updating
     */
    public function test_update_workflow() {
        // Create workflow first
        $workflow_data = [
            'name' => 'Original Name',
            'description' => 'Original description',
            'ai_provider' => 'openai',
            'settings' => ['temperature' => 0.7]
        ];

        $workflow_id = $this->workflow->create($workflow_data);

        // Update workflow
        $update_data = [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'settings' => ['temperature' => 0.9, 'max_tokens' => 2000]
        ];

        $updated = $this->workflow->update($workflow_id, $update_data);
        $this->assertTrue($updated);

        // Verify updates
        $retrieved = $this->workflow->get_by_id($workflow_id);
        $this->assertEquals('Updated Name', $retrieved['name']);
        $this->assertEquals('Updated description', $retrieved['description']);
        $this->assertEquals(0.9, $retrieved['settings']['temperature']);
        $this->assertEquals(2000, $retrieved['settings']['max_tokens']);
        
        // Verify updated_at timestamp changed
        $this->assertNotEquals($retrieved['created_at'], $retrieved['updated_at']);
    }

    /**
     * Test partial workflow updates
     */
    public function test_partial_workflow_update() {
        $workflow_data = [
            'name' => 'Partial Update Test',
            'description' => 'Original description',
            'ai_provider' => 'openai',
            'status' => 'draft'
        ];

        $workflow_id = $this->workflow->create($workflow_data);

        // Update only status
        $updated = $this->workflow->update($workflow_id, ['status' => 'active']);
        $this->assertTrue($updated);

        $retrieved = $this->workflow->get_by_id($workflow_id);
        $this->assertEquals('active', $retrieved['status']);
        $this->assertEquals('Original description', $retrieved['description']); // Unchanged
    }

    /**
     * Test workflow deletion
     */
    public function test_delete_workflow() {
        $workflow_data = [
            'name' => 'To Delete',
            'ai_provider' => 'openai'
        ];

        $workflow_id = $this->workflow->create($workflow_data);
        
        // Verify it exists
        $this->assertNotNull($this->workflow->get_by_id($workflow_id));

        // Delete it
        $deleted = $this->workflow->delete($workflow_id);
        $this->assertTrue($deleted);

        // Verify it's gone
        $this->assertNull($this->workflow->get_by_id($workflow_id));
    }

    /**
     * Test deleting non-existent workflow
     */
    public function test_delete_nonexistent_workflow() {
        $result = $this->workflow->delete(999);
        $this->assertFalse($result);
    }

    /**
     * Test getting all workflows
     */
    public function test_get_all_workflows() {
        // Create multiple workflows
        $workflows_data = [
            ['name' => 'Workflow 1', 'ai_provider' => 'openai', 'status' => 'active'],
            ['name' => 'Workflow 2', 'ai_provider' => 'anthropic', 'status' => 'draft'],
            ['name' => 'Workflow 3', 'ai_provider' => 'openai', 'status' => 'active']
        ];

        $created_ids = [];
        foreach ($workflows_data as $data) {
            $created_ids[] = $this->workflow->create($data);
        }

        $all_workflows = $this->workflow->get_all();

        $this->assertIsArray($all_workflows);
        $this->assertCount(3, $all_workflows);

        // Verify all workflows are present
        $retrieved_names = array_column($all_workflows, 'name');
        $this->assertContains('Workflow 1', $retrieved_names);
        $this->assertContains('Workflow 2', $retrieved_names);
        $this->assertContains('Workflow 3', $retrieved_names);
    }

    /**
     * Test getting workflows with pagination
     */
    public function test_get_workflows_with_pagination() {
        // Create 5 workflows
        for ($i = 1; $i <= 5; $i++) {
            $this->workflow->create([
                'name' => "Pagination Test Workflow $i",
                'ai_provider' => 'openai'
            ]);
        }

        // Test first page
        $page1 = $this->workflow->get_all(['limit' => 2, 'offset' => 0]);
        $this->assertCount(2, $page1);

        // Test second page
        $page2 = $this->workflow->get_all(['limit' => 2, 'offset' => 2]);
        $this->assertCount(2, $page2);

        // Test third page
        $page3 = $this->workflow->get_all(['limit' => 2, 'offset' => 4]);
        $this->assertCount(1, $page3);

        // Verify no overlap
        $all_ids = array_merge(
            array_column($page1, 'id'),
            array_column($page2, 'id'),
            array_column($page3, 'id')
        );
        $this->assertCount(5, array_unique($all_ids));
    }

    /**
     * Test workflow filtering by status
     */
    public function test_filter_workflows_by_status() {
        // Create workflows with different statuses
        $active_id = $this->workflow->create([
            'name' => 'Active Workflow',
            'ai_provider' => 'openai',
            'status' => 'active'
        ]);

        $draft_id = $this->workflow->create([
            'name' => 'Draft Workflow',
            'ai_provider' => 'openai',
            'status' => 'draft'
        ]);

        // Filter by active status
        $active_workflows = $this->workflow->get_by_status('active');
        $this->assertCount(1, $active_workflows);
        $this->assertEquals($active_id, $active_workflows[0]['id']);

        // Filter by draft status
        $draft_workflows = $this->workflow->get_by_status('draft');
        $this->assertCount(1, $draft_workflows);
        $this->assertEquals($draft_id, $draft_workflows[0]['id']);
    }

    /**
     * Test workflow filtering by AI provider
     */
    public function test_filter_workflows_by_provider() {
        // Create workflows with different providers
        $openai_id = $this->workflow->create([
            'name' => 'OpenAI Workflow',
            'ai_provider' => 'openai'
        ]);

        $anthropic_id = $this->workflow->create([
            'name' => 'Anthropic Workflow',
            'ai_provider' => 'anthropic'
        ]);

        // Filter by OpenAI provider
        $openai_workflows = $this->workflow->get_by_provider('openai');
        $this->assertCount(1, $openai_workflows);
        $this->assertEquals($openai_id, $openai_workflows[0]['id']);

        // Filter by Anthropic provider
        $anthropic_workflows = $this->workflow->get_by_provider('anthropic');
        $this->assertCount(1, $anthropic_workflows);
        $this->assertEquals($anthropic_id, $anthropic_workflows[0]['id']);
    }

    /**
     * Test workflow search functionality
     */
    public function test_search_workflows() {
        // Create workflows with searchable content
        $this->workflow->create([
            'name' => 'Blog Post Generator',
            'description' => 'Generates high-quality blog posts'
        ]);

        $this->workflow->create([
            'name' => 'Product Description Writer',
            'description' => 'Creates compelling product descriptions'
        ]);

        $this->workflow->create([
            'name' => 'Email Newsletter Creator',
            'description' => 'Writes engaging newsletter content'
        ]);

        // Search by name
        $blog_results = $this->workflow->search('blog');
        $this->assertCount(1, $blog_results);
        $this->assertStringContains('Blog Post', $blog_results[0]['name']);

        // Search by description
        $product_results = $this->workflow->search('product');
        $this->assertCount(1, $product_results);
        $this->assertStringContains('Product Description', $product_results[0]['name']);

        // Search with no results
        $no_results = $this->workflow->search('nonexistent');
        $this->assertCount(0, $no_results);
    }

    /**
     * Test workflow execution tracking
     */
    public function test_workflow_execution_tracking() {
        $workflow_id = $this->workflow->create([
            'name' => 'Execution Tracking Test',
            'ai_provider' => 'openai'
        ]);

        // Track an execution
        $execution_data = [
            'prompt' => 'Test execution prompt',
            'generated_content' => 'Generated test content',
            'tokens_used' => 150,
            'execution_time' => 2.5,
            'user_id' => 1
        ];

        $execution_id = $this->workflow->track_execution($workflow_id, $execution_data);
        $this->assertIsInt($execution_id);
        $this->assertGreaterThan(0, $execution_id);

        // Get execution history
        $executions = $this->workflow->get_execution_history($workflow_id);
        $this->assertIsArray($executions);
        $this->assertCount(1, $executions);
        $this->assertEquals($execution_data['tokens_used'], $executions[0]['tokens_used']);
    }

    /**
     * Test workflow statistics
     */
    public function test_workflow_statistics() {
        $workflow_id = $this->workflow->create([
            'name' => 'Statistics Test',
            'ai_provider' => 'openai'
        ]);

        // Add multiple executions
        for ($i = 0; $i < 3; $i++) {
            $this->workflow->track_execution($workflow_id, [
                'tokens_used' => 100 + ($i * 50),
                'execution_time' => 1.0 + ($i * 0.5),
                'user_id' => 1
            ]);
        }

        $stats = $this->workflow->get_statistics($workflow_id);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_executions', $stats);
        $this->assertArrayHasKey('total_tokens_used', $stats);
        $this->assertArrayHasKey('average_execution_time', $stats);
        $this->assertArrayHasKey('last_executed', $stats);

        $this->assertEquals(3, $stats['total_executions']);
        $this->assertEquals(450, $stats['total_tokens_used']); // 100 + 150 + 200
        $this->assertEquals(2.0, $stats['average_execution_time']); // (1.0 + 1.5 + 2.0) / 3
    }

    /**
     * Test workflow export functionality
     */
    public function test_export_workflow() {
        $workflow_data = [
            'name' => 'Export Test Workflow',
            'description' => 'Workflow for testing export',
            'ai_provider' => 'openai',
            'settings' => [
                'model' => 'gpt-4',
                'temperature' => 0.8
            ]
        ];

        $workflow_id = $this->workflow->create($workflow_data);
        $exported = $this->workflow->export($workflow_id);

        $this->assertIsArray($exported);
        $this->assertArrayHasKey('name', $exported);
        $this->assertArrayHasKey('description', $exported);
        $this->assertArrayHasKey('ai_provider', $exported);
        $this->assertArrayHasKey('settings', $exported);
        
        // Should not include database-specific fields
        $this->assertArrayNotHasKey('id', $exported);
        $this->assertArrayNotHasKey('created_at', $exported);
        $this->assertArrayNotHasKey('updated_at', $exported);
    }

    /**
     * Test workflow import functionality
     */
    public function test_import_workflow() {
        $import_data = [
            'name' => 'Imported Workflow',
            'description' => 'A workflow imported from export',
            'ai_provider' => 'anthropic',
            'settings' => [
                'model' => 'claude-3-sonnet',
                'temperature' => 0.6
            ],
            'status' => 'draft'
        ];

        $imported_id = $this->workflow->import($import_data);

        $this->assertIsInt($imported_id);
        $this->assertGreaterThan(0, $imported_id);

        // Verify imported data
        $imported_workflow = $this->workflow->get_by_id($imported_id);
        $this->assertEquals($import_data['name'], $imported_workflow['name']);
        $this->assertEquals($import_data['description'], $imported_workflow['description']);
        $this->assertEquals($import_data['ai_provider'], $imported_workflow['ai_provider']);
        $this->assertEquals($import_data['settings'], $imported_workflow['settings']);
    }

    /**
     * Test workflow cloning
     */
    public function test_clone_workflow() {
        $original_data = [
            'name' => 'Original Workflow',
            'description' => 'Original description',
            'ai_provider' => 'openai',
            'settings' => ['temperature' => 0.7]
        ];

        $original_id = $this->workflow->create($original_data);
        $cloned_id = $this->workflow->clone_workflow($original_id, 'Cloned Workflow');

        $this->assertIsInt($cloned_id);
        $this->assertNotEquals($original_id, $cloned_id);

        // Verify cloned data
        $original = $this->workflow->get_by_id($original_id);
        $cloned = $this->workflow->get_by_id($cloned_id);

        $this->assertEquals('Cloned Workflow', $cloned['name']);
        $this->assertEquals($original['description'], $cloned['description']);
        $this->assertEquals($original['ai_provider'], $cloned['ai_provider']);
        $this->assertEquals($original['settings'], $cloned['settings']);
    }

    /**
     * Test workflow validation
     */
    public function test_workflow_validation() {
        // Test valid workflow data
        $valid_data = [
            'name' => 'Valid Workflow',
            'ai_provider' => 'openai',
            'settings' => ['temperature' => 0.7]
        ];

        $validation_result = $this->workflow->validate($valid_data);
        $this->assertTrue($validation_result['valid']);
        $this->assertEmpty($validation_result['errors']);

        // Test invalid workflow data
        $invalid_data = [
            'name' => '', // Empty name
            'ai_provider' => 'invalid_provider', // Invalid provider
            'settings' => ['temperature' => 5.0] // Invalid temperature
        ];

        $validation_result = $this->workflow->validate($invalid_data);
        $this->assertFalse($validation_result['valid']);
        $this->assertNotEmpty($validation_result['errors']);
        $this->assertContains('Workflow name cannot be empty', $validation_result['errors']);
        $this->assertContains('Invalid AI provider', $validation_result['errors']);
        $this->assertContains('Temperature must be between 0 and 2', $validation_result['errors']);
    }

    /**
     * Test workflow status transitions
     */
    public function test_workflow_status_transitions() {
        $workflow_id = $this->workflow->create([
            'name' => 'Status Test Workflow',
            'ai_provider' => 'openai',
            'status' => 'draft'
        ]);

        // Transition from draft to active
        $activated = $this->workflow->activate($workflow_id);
        $this->assertTrue($activated);

        $workflow = $this->workflow->get_by_id($workflow_id);
        $this->assertEquals('active', $workflow['status']);

        // Transition from active to inactive
        $deactivated = $this->workflow->deactivate($workflow_id);
        $this->assertTrue($deactivated);

        $workflow = $this->workflow->get_by_id($workflow_id);
        $this->assertEquals('inactive', $workflow['status']);
    }

    /**
     * Test workflow permissions
     */
    public function test_workflow_permissions() {
        $workflow_id = $this->workflow->create([
            'name' => 'Permission Test',
            'ai_provider' => 'openai',
            'created_by' => 1
        ]);

        // Test owner permissions
        $this->assertTrue($this->workflow->can_edit($workflow_id, 1));
        $this->assertTrue($this->workflow->can_delete($workflow_id, 1));

        // Test non-owner permissions
        $this->assertFalse($this->workflow->can_edit($workflow_id, 2));
        $this->assertFalse($this->workflow->can_delete($workflow_id, 2));

        // Test admin permissions (assuming user_id 999 is admin)
        $this->assertTrue($this->workflow->can_edit($workflow_id, 999, ['manage_options']));
        $this->assertTrue($this->workflow->can_delete($workflow_id, 999, ['manage_options']));
    }

    /**
     * Test workflow caching
     */
    public function test_workflow_caching() {
        $workflow_data = [
            'name' => 'Cache Test Workflow',
            'ai_provider' => 'openai'
        ];

        $workflow_id = $this->workflow->create($workflow_data);

        // First retrieval should hit database
        $workflow1 = $this->workflow->get_by_id($workflow_id);
        
        // Second retrieval should hit cache
        $workflow2 = $this->workflow->get_by_id($workflow_id);

        $this->assertEquals($workflow1, $workflow2);

        // Update should invalidate cache
        $this->workflow->update($workflow_id, ['name' => 'Updated Name']);
        $workflow3 = $this->workflow->get_by_id($workflow_id);

        $this->assertEquals('Updated Name', $workflow3['name']);
        $this->assertNotEquals($workflow1['name'], $workflow3['name']);
    }
}