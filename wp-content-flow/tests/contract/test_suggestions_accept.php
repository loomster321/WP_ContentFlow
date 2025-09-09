<?php
/**
 * Contract test for POST /wp-json/wp-content-flow/v1/suggestions/{id}/accept endpoint
 * 
 * This test MUST FAIL until the suggestions controller is implemented.
 * Following TDD principles: RED → GREEN → Refactor
 */

class Test_Suggestions_Accept_Contract extends WP_Content_Flow_Test_Case {

    /**
     * Test POST /suggestions/{id}/accept endpoint accepts suggestion
     * 
     * @test
     */
    public function test_post_suggestions_accept_updates_status() {
        $suggestion_id = 123; // Mock suggestion ID
        
        $response = $this->mock_api_request( "suggestions/{$suggestion_id}/accept", array(), 'POST' );
        
        // This MUST fail until WP_Content_Flow_Suggestions_Controller is implemented
        $this->assertRestResponse( $response, 200, array( 'success', 'message' ) );
        
        $data = $response->get_data();
        $this->assertTrue( $data['success'] );
        $this->assertStringContainsString( 'accepted', strtolower( $data['message'] ) );
    }

    /**
     * Test POST /suggestions/{id}/accept validates suggestion exists
     * 
     * @test
     */
    public function test_post_suggestions_accept_validates_existence() {
        $non_existent_id = 99999;
        
        $response = $this->mock_api_request( "suggestions/{$non_existent_id}/accept", array(), 'POST' );
        
        $this->assertEquals( 404, $response->get_status() );
        
        $data = $response->get_data();
        $this->assertEquals( 'rest_not_found', $data['code'] );
        $this->assertStringContainsString( 'not found', strtolower( $data['message'] ) );
    }

    /**
     * Test POST /suggestions/{id}/accept requires authentication
     * 
     * @test
     */
    public function test_post_suggestions_accept_requires_authentication() {
        wp_set_current_user( 0 );
        
        $response = $this->mock_api_request( 'suggestions/123/accept', array(), 'POST' );
        
        $this->assertEquals( 401, $response->get_status() );
    }

    /**
     * Test POST /suggestions/{id}/accept validates user permissions
     * 
     * @test
     */
    public function test_post_suggestions_accept_validates_permissions() {
        // Create suggestion owned by different user
        $other_user_id = $this->factory->user->create();
        $post_id = $this->factory->post->create( array( 'post_author' => $other_user_id ) );
        
        // Current user should not be able to accept suggestions for posts they can't edit
        $subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
        wp_set_current_user( $subscriber_id );
        
        $response = $this->mock_api_request( 'suggestions/123/accept', array(), 'POST' );
        
        $this->assertEquals( 403, $response->get_status() );
        
        $data = $response->get_data();
        $this->assertEquals( 'rest_forbidden', $data['code'] );
    }
}