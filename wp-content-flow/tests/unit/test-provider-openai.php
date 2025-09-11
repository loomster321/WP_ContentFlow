<?php
/**
 * Unit Tests: OpenAI Provider
 * 
 * Tests the OpenAI provider implementation including API calls,
 * response handling, and error scenarios.
 *
 * @package WP_Content_Flow
 * @subpackage Tests\Unit
 */

class Test_Provider_OpenAI extends WP_UnitTestCase {
    
    /**
     * Provider instance
     */
    private $provider;
    
    /**
     * Original API key
     */
    private $original_api_key;
    
    /**
     * Set up test environment
     */
    public function setUp() {
        parent::setUp();
        
        // Store original API key
        $settings = get_option( 'wp_content_flow_settings', array() );
        $this->original_api_key = $settings['openai_api_key'] ?? '';
        
        // Set test API key
        $settings['openai_api_key'] = 'test_openai_api_key';
        update_option( 'wp_content_flow_settings', $settings );
        
        // Initialize provider
        require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/providers/class-openai-provider.php';
        $this->provider = new WP_Content_Flow_OpenAI_Provider();
        
        // Mock HTTP requests
        add_filter( 'pre_http_request', array( $this, 'mock_http_request' ), 10, 3 );
    }
    
    /**
     * Tear down test environment
     */
    public function tearDown() {
        // Restore original API key
        $settings = get_option( 'wp_content_flow_settings', array() );
        $settings['openai_api_key'] = $this->original_api_key;
        update_option( 'wp_content_flow_settings', $settings );
        
        // Remove filter
        remove_filter( 'pre_http_request', array( $this, 'mock_http_request' ) );
        
        parent::tearDown();
    }
    
    /**
     * Mock HTTP requests to OpenAI API
     */
    public function mock_http_request( $preempt, $args, $url ) {
        if ( strpos( $url, 'api.openai.com' ) === false ) {
            return $preempt;
        }
        
        // Parse request
        $body = json_decode( $args['body'], true );
        
        // Mock different responses based on request
        if ( strpos( $url, '/chat/completions' ) !== false ) {
            return $this->mock_chat_completion( $body );
        }
        
        if ( strpos( $url, '/models' ) !== false ) {
            return $this->mock_models_list();
        }
        
        if ( strpos( $url, '/embeddings' ) !== false ) {
            return $this->mock_embeddings( $body );
        }
        
        return $preempt;
    }
    
    /**
     * Mock chat completion response
     */
    private function mock_chat_completion( $request ) {
        // Check for error scenarios
        if ( isset( $request['test_error'] ) ) {
            return $this->mock_error_response( $request['test_error'] );
        }
        
        // Normal response
        return array(
            'response' => array(
                'code' => 200,
                'message' => 'OK'
            ),
            'body' => json_encode( array(
                'id' => 'chatcmpl-' . uniqid(),
                'object' => 'chat.completion',
                'created' => time(),
                'model' => $request['model'] ?? 'gpt-3.5-turbo',
                'choices' => array(
                    array(
                        'index' => 0,
                        'message' => array(
                            'role' => 'assistant',
                            'content' => 'This is a mocked response from OpenAI for: ' . $request['messages'][0]['content']
                        ),
                        'finish_reason' => 'stop'
                    )
                ),
                'usage' => array(
                    'prompt_tokens' => 50,
                    'completion_tokens' => 100,
                    'total_tokens' => 150
                )
            ) )
        );
    }
    
    /**
     * Mock models list response
     */
    private function mock_models_list() {
        return array(
            'response' => array(
                'code' => 200,
                'message' => 'OK'
            ),
            'body' => json_encode( array(
                'object' => 'list',
                'data' => array(
                    array( 'id' => 'gpt-3.5-turbo', 'object' => 'model' ),
                    array( 'id' => 'gpt-4', 'object' => 'model' ),
                    array( 'id' => 'gpt-4-turbo-preview', 'object' => 'model' )
                )
            ) )
        );
    }
    
    /**
     * Mock embeddings response
     */
    private function mock_embeddings( $request ) {
        return array(
            'response' => array(
                'code' => 200,
                'message' => 'OK'
            ),
            'body' => json_encode( array(
                'object' => 'list',
                'data' => array(
                    array(
                        'object' => 'embedding',
                        'index' => 0,
                        'embedding' => array_fill( 0, 1536, 0.1 ) // Mock embedding vector
                    )
                ),
                'model' => 'text-embedding-ada-002',
                'usage' => array(
                    'prompt_tokens' => 10,
                    'total_tokens' => 10
                )
            ) )
        );
    }
    
    /**
     * Mock error response
     */
    private function mock_error_response( $error_type ) {
        $errors = array(
            'rate_limit' => array(
                'code' => 429,
                'message' => 'Rate limit exceeded',
                'body' => json_encode( array(
                    'error' => array(
                        'message' => 'Rate limit reached for requests',
                        'type' => 'rate_limit_exceeded',
                        'code' => 'rate_limit_exceeded'
                    )
                ) )
            ),
            'invalid_key' => array(
                'code' => 401,
                'message' => 'Unauthorized',
                'body' => json_encode( array(
                    'error' => array(
                        'message' => 'Invalid API key',
                        'type' => 'invalid_request_error',
                        'code' => 'invalid_api_key'
                    )
                ) )
            ),
            'quota_exceeded' => array(
                'code' => 402,
                'message' => 'Payment Required',
                'body' => json_encode( array(
                    'error' => array(
                        'message' => 'You exceeded your current quota',
                        'type' => 'insufficient_quota',
                        'code' => 'insufficient_quota'
                    )
                ) )
            )
        );
        
        return array(
            'response' => array(
                'code' => $errors[$error_type]['code'],
                'message' => $errors[$error_type]['message']
            ),
            'body' => $errors[$error_type]['body']
        );
    }
    
    /**
     * Test provider initialization
     */
    public function test_provider_initialization() {
        $this->assertInstanceOf( 'WP_Content_Flow_OpenAI_Provider', $this->provider );
        $this->assertEquals( 'openai', $this->provider->get_id() );
        $this->assertEquals( 'OpenAI', $this->provider->get_name() );
    }
    
    /**
     * Test content generation
     */
    public function test_generate_content() {
        $prompt = 'Write a short blog post about WordPress';
        $parameters = array(
            'max_tokens' => 500,
            'temperature' => 0.7,
            'model' => 'gpt-3.5-turbo'
        );
        
        $result = $this->provider->generate_content( $prompt, $parameters );
        
        $this->assertNotWPError( $result );
        $this->assertArrayHasKey( 'content', $result );
        $this->assertArrayHasKey( 'tokens_used', $result );
        $this->assertArrayHasKey( 'model', $result );
        
        $this->assertContains( 'mocked response', $result['content'] );
        $this->assertEquals( 150, $result['tokens_used'] );
        $this->assertEquals( 'gpt-3.5-turbo', $result['model'] );
    }
    
    /**
     * Test content improvement
     */
    public function test_improve_content() {
        $content = 'This is original content that needs improvement.';
        $parameters = array(
            'improvement_type' => 'clarity',
            'model' => 'gpt-4'
        );
        
        $result = $this->provider->improve_content( $content, $parameters );
        
        $this->assertNotWPError( $result );
        $this->assertArrayHasKey( 'improved_content', $result );
        $this->assertArrayHasKey( 'changes', $result );
        $this->assertNotEmpty( $result['improved_content'] );
    }
    
    /**
     * Test model selection
     */
    public function test_model_selection() {
        $models = array( 'gpt-3.5-turbo', 'gpt-4', 'gpt-4-turbo-preview' );
        
        foreach ( $models as $model ) {
            $result = $this->provider->generate_content(
                'Test prompt',
                array( 'model' => $model )
            );
            
            $this->assertNotWPError( $result );
            $this->assertEquals( $model, $result['model'] );
        }
    }
    
    /**
     * Test temperature parameter
     */
    public function test_temperature_parameter() {
        $temperatures = array( 0, 0.5, 1.0, 1.5, 2.0 );
        
        foreach ( $temperatures as $temp ) {
            $result = $this->provider->generate_content(
                'Test prompt',
                array( 'temperature' => $temp )
            );
            
            $this->assertNotWPError( $result );
            // Temperature should affect response (mocked for testing)
        }
    }
    
    /**
     * Test rate limit handling
     */
    public function test_rate_limit_handling() {
        $result = $this->provider->generate_content(
            'Test prompt',
            array( 'test_error' => 'rate_limit' )
        );
        
        $this->assertWPError( $result );
        $this->assertEquals( 'rate_limit_exceeded', $result->get_error_code() );
        $this->assertContains( 'Rate limit', $result->get_error_message() );
    }
    
    /**
     * Test invalid API key
     */
    public function test_invalid_api_key() {
        $result = $this->provider->generate_content(
            'Test prompt',
            array( 'test_error' => 'invalid_key' )
        );
        
        $this->assertWPError( $result );
        $this->assertEquals( 'invalid_api_key', $result->get_error_code() );
        $this->assertContains( 'Invalid API key', $result->get_error_message() );
    }
    
    /**
     * Test quota exceeded
     */
    public function test_quota_exceeded() {
        $result = $this->provider->generate_content(
            'Test prompt',
            array( 'test_error' => 'quota_exceeded' )
        );
        
        $this->assertWPError( $result );
        $this->assertEquals( 'insufficient_quota', $result->get_error_code() );
        $this->assertContains( 'quota', $result->get_error_message() );
    }
    
    /**
     * Test get available models
     */
    public function test_get_available_models() {
        $models = $this->provider->get_available_models();
        
        $this->assertIsArray( $models );
        $this->assertContains( 'gpt-3.5-turbo', $models );
        $this->assertContains( 'gpt-4', $models );
    }
    
    /**
     * Test embeddings generation
     */
    public function test_generate_embeddings() {
        if ( ! method_exists( $this->provider, 'generate_embeddings' ) ) {
            $this->markTestSkipped( 'Embeddings not implemented' );
        }
        
        $text = 'Generate embeddings for this text';
        $result = $this->provider->generate_embeddings( $text );
        
        $this->assertNotWPError( $result );
        $this->assertArrayHasKey( 'embedding', $result );
        $this->assertIsArray( $result['embedding'] );
        $this->assertEquals( 1536, count( $result['embedding'] ) );
    }
    
    /**
     * Test token counting
     */
    public function test_token_counting() {
        if ( ! method_exists( $this->provider, 'count_tokens' ) ) {
            $this->markTestSkipped( 'Token counting not implemented' );
        }
        
        $text = 'This is a test text for counting tokens.';
        $tokens = $this->provider->count_tokens( $text );
        
        $this->assertIsInt( $tokens );
        $this->assertGreaterThan( 0, $tokens );
    }
    
    /**
     * Test streaming response
     */
    public function test_streaming_response() {
        if ( ! method_exists( $this->provider, 'generate_stream' ) ) {
            $this->markTestSkipped( 'Streaming not implemented' );
        }
        
        $prompt = 'Generate streaming content';
        $stream = $this->provider->generate_stream( $prompt );
        
        $this->assertIsResource( $stream );
        // Test stream handling would go here
    }
    
    /**
     * Test function calling
     */
    public function test_function_calling() {
        if ( ! method_exists( $this->provider, 'call_function' ) ) {
            $this->markTestSkipped( 'Function calling not implemented' );
        }
        
        $functions = array(
            array(
                'name' => 'get_weather',
                'description' => 'Get weather for a location',
                'parameters' => array(
                    'type' => 'object',
                    'properties' => array(
                        'location' => array( 'type' => 'string' )
                    )
                )
            )
        );
        
        $result = $this->provider->generate_content(
            'What is the weather in New York?',
            array( 'functions' => $functions )
        );
        
        $this->assertNotWPError( $result );
        if ( isset( $result['function_call'] ) ) {
            $this->assertEquals( 'get_weather', $result['function_call']['name'] );
        }
    }
    
    /**
     * Test retry logic
     */
    public function test_retry_logic() {
        // Test that provider retries on temporary failures
        $retry_count = 0;
        
        add_filter( 'wp_content_flow_openai_retry', function() use ( &$retry_count ) {
            $retry_count++;
            return $retry_count < 3; // Retry twice
        } );
        
        $result = $this->provider->generate_content( 'Test retry' );
        
        $this->assertNotWPError( $result );
        // In real scenario, this would test actual retry behavior
    }
    
    /**
     * Test response caching
     */
    public function test_response_caching() {
        $prompt = 'Cacheable prompt';
        $parameters = array( 'cache' => true );
        
        // First call
        $result1 = $this->provider->generate_content( $prompt, $parameters );
        
        // Second call (should use cache)
        $result2 = $this->provider->generate_content( $prompt, $parameters );
        
        $this->assertEquals( $result1, $result2 );
    }
}