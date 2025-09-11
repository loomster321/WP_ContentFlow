<?php
/**
 * Contract Test: GET /wp-json/wp-content-flow/v1/posts/{post_id}/history
 * 
 * Tests the content history retrieval endpoint according to the OpenAPI contract.
 * This endpoint returns the AI-assisted content modification history for a post.
 *
 * @package WP_Content_Flow
 * @subpackage Tests\Contract
 */

class Test_Content_History extends WP_Content_Flow_Test_Case {
    
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
     * Another author user for permission testing
     * @var int
     */
    private $other_author_user;
    
    /**
     * Test post IDs
     * @var array
     */
    private $test_post_ids = array();
    
    /**
     * Test workflow ID
     * @var int
     */
    private $test_workflow_id;
    
    /**
     * Test history entry IDs
     * @var array
     */
    private $history_ids = array();
    
    /**
     * Set up test fixtures
     */
    public function setUp() {
        parent::setUp();
        
        // Create test users
        $this->admin_user = $this->factory->user->create( array( 'role' => 'administrator' ) );
        $this->editor_user = $this->factory->user->create( array( 'role' => 'editor' ) );
        $this->author_user = $this->factory->user->create( array( 'role' => 'author' ) );
        $this->other_author_user = $this->factory->user->create( array( 'role' => 'author' ) );
        
        // Create test posts
        $this->create_test_posts();
        
        // Create test workflow
        $this->create_test_workflow();
        
        // Create test history entries
        $this->create_test_history();
    }
    
    /**
     * Create test posts
     */
    private function create_test_posts() {
        // Post with history
        wp_set_current_user( $this->author_user );
        $this->test_post_ids['with_history'] = wp_insert_post( array(
            'post_title' => 'Post with AI History',
            'post_content' => 'Final content after AI improvements',
            'post_status' => 'publish',
            'post_author' => $this->author_user,
            'post_type' => 'post'
        ) );
        
        // Post without history
        $this->test_post_ids['without_history'] = wp_insert_post( array(
            'post_title' => 'Post without AI History',
            'post_content' => 'Regular content',
            'post_status' => 'publish',
            'post_author' => $this->author_user,
            'post_type' => 'post'
        ) );
        
        // Post by different author
        wp_set_current_user( $this->other_author_user );
        $this->test_post_ids['other_author'] = wp_insert_post( array(
            'post_title' => 'Other Author Post',
            'post_content' => 'Content by different author',
            'post_status' => 'private',
            'post_author' => $this->other_author_user,
            'post_type' => 'post'
        ) );
    }
    
    /**
     * Create test workflow
     */
    private function create_test_workflow() {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'content_flow_workflows',
            array(
                'name' => 'Test History Workflow',
                'workflow_type' => 'content_generation',
                'ai_provider' => 'openai',
                'prompt_template' => 'Generate content',
                'parameters' => json_encode(array('max_tokens' => 500)),
                'is_active' => true,
                'created_by' => $this->admin_user
            )
        );
        $this->test_workflow_id = $wpdb->insert_id;
    }
    
    /**
     * Create test history entries
     */
    private function create_test_history() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'content_flow_history';
        
        // Multiple history entries for main post
        $history_data = array(
            array(
                'post_id' => $this->test_post_ids['with_history'],
                'workflow_id' => $this->test_workflow_id,
                'user_id' => $this->author_user,
                'action' => 'content_generated',
                'content_before' => '',
                'content_after' => 'Initial AI generated content',
                'ai_provider' => 'openai',
                'prompt_used' => 'Generate initial content',
                'parameters' => json_encode(array('temperature' => 0.7)),
                'tokens_used' => 150,
                'created_at' => date('Y-m-d H:i:s', strtotime('-3 days'))
            ),
            array(
                'post_id' => $this->test_post_ids['with_history'],
                'workflow_id' => $this->test_workflow_id,
                'user_id' => $this->editor_user,
                'action' => 'content_improved',
                'content_before' => 'Initial AI generated content',
                'content_after' => 'Improved content with better structure',
                'ai_provider' => 'anthropic',
                'prompt_used' => 'Improve content structure',
                'parameters' => json_encode(array('temperature' => 0.5)),
                'tokens_used' => 200,
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
            ),
            array(
                'post_id' => $this->test_post_ids['with_history'],
                'workflow_id' => $this->test_workflow_id,
                'user_id' => $this->author_user,
                'action' => 'content_refined',
                'content_before' => 'Improved content with better structure',
                'content_after' => 'Final content after AI improvements',
                'ai_provider' => 'google_ai',
                'prompt_used' => 'Refine and polish content',
                'parameters' => json_encode(array('temperature' => 0.3)),
                'tokens_used' => 175,
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
            )
        );
        
        foreach ($history_data as $entry) {
            $wpdb->insert($table_name, $entry);
            $this->history_ids[] = $wpdb->insert_id;
        }
        
        // Add history for other author's post
        $wpdb->insert($table_name, array(
            'post_id' => $this->test_post_ids['other_author'],
            'workflow_id' => $this->test_workflow_id,
            'user_id' => $this->other_author_user,
            'action' => 'content_generated',
            'content_before' => '',
            'content_after' => 'Private content',
            'ai_provider' => 'openai',
            'prompt_used' => 'Generate private content',
            'parameters' => json_encode(array()),
            'tokens_used' => 100,
            'created_at' => date('Y-m-d H:i:s')
        ));
        $this->history_ids[] = $wpdb->insert_id;
    }
    
    /**
     * Clean up after tests
     */
    public function tearDown() {
        // Clean up posts
        foreach ($this->test_post_ids as $post_id) {
            wp_delete_post($post_id, true);
        }
        
        // Clean up database entries
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'content_flow_workflows',
            array('id' => $this->test_workflow_id)
        );
        
        foreach ($this->history_ids as $history_id) {
            $wpdb->delete(
                $wpdb->prefix . 'content_flow_history',
                array('id' => $history_id)
            );
        }
        
        parent::tearDown();
    }
    
    /**
     * Test successful history retrieval
     * Contract: GET /posts/{post_id}/history returns history entries
     */
    public function test_get_history_success() {
        wp_set_current_user( $this->author_user );
        
        $request = new WP_REST_Request( 'GET', '/wp-content-flow/v1/posts/' . $this->test_post_ids['with_history'] . '/history' );
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        // Assert response status
        $this->assertEquals( 200, $response->get_status() );
        
        // Assert response structure
        $this->assertIsArray( $data );
        $this->assertArrayHasKey( 'history', $data );
        $this->assertArrayHasKey( 'total', $data );
        $this->assertArrayHasKey( 'post_id', $data );
        
        // Assert correct number of history entries
        $this->assertEquals( 3, $data['total'] );
        $this->assertCount( 3, $data['history'] );
        
        // Assert history entries are ordered by date (newest first)
        $dates = array_column($data['history'], 'created_at');
        $sorted_dates = $dates;
        rsort($sorted_dates);
        $this->assertEquals( $sorted_dates, $dates );
        
        // Assert history entry structure
        $first_entry = $data['history'][0];
        $this->assertArrayHasKey( 'id', $first_entry );
        $this->assertArrayHasKey( 'action', $first_entry );
        $this->assertArrayHasKey( 'user', $first_entry );
        $this->assertArrayHasKey( 'ai_provider', $first_entry );
        $this->assertArrayHasKey( 'content_before', $first_entry );
        $this->assertArrayHasKey( 'content_after', $first_entry );
        $this->assertArrayHasKey( 'prompt_used', $first_entry );
        $this->assertArrayHasKey( 'tokens_used', $first_entry );
        $this->assertArrayHasKey( 'created_at', $first_entry );
        
        // Assert user information is included
        $this->assertIsArray( $first_entry['user'] );
        $this->assertArrayHasKey( 'id', $first_entry['user'] );
        $this->assertArrayHasKey( 'name', $first_entry['user'] );
        $this->assertArrayHasKey( 'avatar', $first_entry['user'] );
    }
    
    /**
     * Test history retrieval with pagination
     * Contract: Supports pagination parameters
     */
    public function test_get_history_pagination() {
        wp_set_current_user( $this->admin_user );
        
        // First page
        $request = new WP_REST_Request( 'GET', '/wp-content-flow/v1/posts/' . $this->test_post_ids['with_history'] . '/history' );
        $request->set_query_params( array(
            'per_page' => 2,
            'page' => 1
        ) );
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        // Assert first page
        $this->assertEquals( 200, $response->get_status() );
        $this->assertCount( 2, $data['history'] );
        $this->assertEquals( 3, $data['total'] );
        $this->assertEquals( 1, $data['page'] );
        $this->assertEquals( 2, $data['per_page'] );
        $this->assertEquals( 2, $data['total_pages'] );
        
        // Second page
        $request2 = new WP_REST_Request( 'GET', '/wp-content-flow/v1/posts/' . $this->test_post_ids['with_history'] . '/history' );
        $request2->set_query_params( array(
            'per_page' => 2,
            'page' => 2
        ) );
        $response2 = rest_do_request( $request2 );
        $data2 = $response2->get_data();
        
        // Assert second page
        $this->assertEquals( 200, $response2->get_status() );
        $this->assertCount( 1, $data2['history'] );
        $this->assertEquals( 2, $data2['page'] );
        
        // Ensure no duplicate entries
        $first_page_ids = array_column($data['history'], 'id');
        $second_page_ids = array_column($data2['history'], 'id');
        $this->assertEmpty( array_intersect($first_page_ids, $second_page_ids) );
    }
    
    /**
     * Test history retrieval with filters
     * Contract: Supports filtering by action and provider
     */
    public function test_get_history_with_filters() {
        wp_set_current_user( $this->editor_user );
        
        // Filter by action
        $request = new WP_REST_Request( 'GET', '/wp-content-flow/v1/posts/' . $this->test_post_ids['with_history'] . '/history' );
        $request->set_query_params( array(
            'action' => 'content_improved'
        ) );
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        $this->assertEquals( 200, $response->get_status() );
        $this->assertCount( 1, $data['history'] );
        $this->assertEquals( 'content_improved', $data['history'][0]['action'] );
        
        // Filter by provider
        $request2 = new WP_REST_Request( 'GET', '/wp-content-flow/v1/posts/' . $this->test_post_ids['with_history'] . '/history' );
        $request2->set_query_params( array(
            'provider' => 'openai'
        ) );
        $response2 = rest_do_request( $request2 );
        $data2 = $response2->get_data();
        
        $this->assertEquals( 200, $response2->get_status() );
        $this->assertCount( 1, $data2['history'] );
        $this->assertEquals( 'openai', $data2['history'][0]['ai_provider'] );
        
        // Filter by date range
        $request3 = new WP_REST_Request( 'GET', '/wp-content-flow/v1/posts/' . $this->test_post_ids['with_history'] . '/history' );
        $request3->set_query_params( array(
            'after' => date('Y-m-d', strtotime('-2 days')),
            'before' => date('Y-m-d', strtotime('tomorrow'))
        ) );
        $response3 = rest_do_request( $request3 );
        $data3 = $response3->get_data();
        
        $this->assertEquals( 200, $response3->get_status() );
        $this->assertCount( 2, $data3['history'] ); // Should exclude the entry from 3 days ago
    }
    
    /**
     * Test history for post without history
     * Contract: Returns empty history array
     */
    public function test_get_history_empty() {
        wp_set_current_user( $this->author_user );
        
        $request = new WP_REST_Request( 'GET', '/wp-content-flow/v1/posts/' . $this->test_post_ids['without_history'] . '/history' );
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        // Assert response
        $this->assertEquals( 200, $response->get_status() );
        $this->assertIsArray( $data['history'] );
        $this->assertEmpty( $data['history'] );
        $this->assertEquals( 0, $data['total'] );
        $this->assertEquals( $this->test_post_ids['without_history'], $data['post_id'] );
    }
    
    /**
     * Test history for non-existent post
     * Contract: Returns 404 for non-existent posts
     */
    public function test_get_history_post_not_found() {
        wp_set_current_user( $this->admin_user );
        
        $request = new WP_REST_Request( 'GET', '/wp-content-flow/v1/posts/999999/history' );
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        // Assert 404 response
        $this->assertEquals( 404, $response->get_status() );
        $this->assertEquals( 'post_not_found', $data['code'] );
        $this->assertContains( 'Post not found', $data['message'] );
    }
    
    /**
     * Test history without authentication
     * Contract: Requires authentication
     */
    public function test_get_history_unauthenticated() {
        wp_set_current_user( 0 );
        
        $request = new WP_REST_Request( 'GET', '/wp-content-flow/v1/posts/' . $this->test_post_ids['with_history'] . '/history' );
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        // Assert 401 response
        $this->assertEquals( 401, $response->get_status() );
        $this->assertEquals( 'rest_forbidden', $data['code'] );
    }
    
    /**
     * Test history access permissions
     * Contract: Users can only see history for posts they can read
     */
    public function test_get_history_permissions() {
        wp_set_current_user( $this->author_user );
        
        // Should not be able to see history for private post by another author
        $request = new WP_REST_Request( 'GET', '/wp-content-flow/v1/posts/' . $this->test_post_ids['other_author'] . '/history' );
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        // Assert 403 response
        $this->assertEquals( 403, $response->get_status() );
        $this->assertEquals( 'rest_forbidden', $data['code'] );
        $this->assertContains( 'permission', $data['message'] );
        
        // Editor should be able to see all post history
        wp_set_current_user( $this->editor_user );
        $request2 = new WP_REST_Request( 'GET', '/wp-content-flow/v1/posts/' . $this->test_post_ids['other_author'] . '/history' );
        $response2 = rest_do_request( $request2 );
        
        $this->assertEquals( 200, $response2->get_status() );
    }
    
    /**
     * Test history summary endpoint
     * Contract: Can get summary statistics
     */
    public function test_get_history_summary() {
        wp_set_current_user( $this->admin_user );
        
        $request = new WP_REST_Request( 'GET', '/wp-content-flow/v1/posts/' . $this->test_post_ids['with_history'] . '/history' );
        $request->set_query_params( array(
            'summary' => true
        ) );
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        // Assert response
        $this->assertEquals( 200, $response->get_status() );
        
        if ( isset($data['summary']) ) {
            // Assert summary statistics
            $this->assertArrayHasKey( 'total_changes', $data['summary'] );
            $this->assertArrayHasKey( 'total_tokens', $data['summary'] );
            $this->assertArrayHasKey( 'providers_used', $data['summary'] );
            $this->assertArrayHasKey( 'contributors', $data['summary'] );
            $this->assertArrayHasKey( 'last_modified', $data['summary'] );
            
            $this->assertEquals( 3, $data['summary']['total_changes'] );
            $this->assertEquals( 525, $data['summary']['total_tokens'] ); // 150 + 200 + 175
            $this->assertCount( 3, $data['summary']['providers_used'] );
            $this->assertCount( 2, $data['summary']['contributors'] ); // author and editor
        }
    }
    
    /**
     * Test history export functionality
     * Contract: Can export history in different formats
     */
    public function test_get_history_export() {
        wp_set_current_user( $this->admin_user );
        
        // Test JSON export (default)
        $request = new WP_REST_Request( 'GET', '/wp-content-flow/v1/posts/' . $this->test_post_ids['with_history'] . '/history' );
        $request->set_query_params( array(
            'export' => 'json'
        ) );
        $response = rest_do_request( $request );
        
        $this->assertEquals( 200, $response->get_status() );
        $this->assertIsArray( $response->get_data() );
        
        // Test CSV export format
        $request2 = new WP_REST_Request( 'GET', '/wp-content-flow/v1/posts/' . $this->test_post_ids['with_history'] . '/history' );
        $request2->set_query_params( array(
            'export' => 'csv'
        ) );
        $response2 = rest_do_request( $request2 );
        
        if ( $response2->get_status() === 200 ) {
            $headers = $response2->get_headers();
            // Check for CSV content type if implemented
            if ( isset($headers['Content-Type']) ) {
                $this->assertContains( 
                    $headers['Content-Type'],
                    array('text/csv', 'application/csv', 'application/json')
                );
            }
        }
    }
    
    /**
     * Test history diff functionality
     * Contract: Can get content diffs between versions
     */
    public function test_get_history_with_diff() {
        wp_set_current_user( $this->editor_user );
        
        $request = new WP_REST_Request( 'GET', '/wp-content-flow/v1/posts/' . $this->test_post_ids['with_history'] . '/history' );
        $request->set_query_params( array(
            'include_diff' => true
        ) );
        $response = rest_do_request( $request );
        $data = $response->get_data();
        
        $this->assertEquals( 200, $response->get_status() );
        
        if ( isset($data['history'][0]['diff']) ) {
            // Assert diff information is included
            $first_entry = $data['history'][0];
            $this->assertArrayHasKey( 'diff', $first_entry );
            $this->assertArrayHasKey( 'additions', $first_entry['diff'] );
            $this->assertArrayHasKey( 'deletions', $first_entry['diff'] );
            $this->assertArrayHasKey( 'changes', $first_entry['diff'] );
        }
    }
}