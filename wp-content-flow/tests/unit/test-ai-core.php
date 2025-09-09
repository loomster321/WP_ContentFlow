<?php
/**
 * PHPUnit tests for AI_Core class
 */

class Test_AI_Core extends WP_Content_Flow_Test_Case {

    private $ai_core;
    private $mock_openai_provider;
    private $mock_anthropic_provider;

    public function setUp(): void {
        parent::setUp();

        // Create mock AI providers
        $this->mock_openai_provider = $this->createMock(WP_Content_Flow_OpenAI_Provider::class);
        $this->mock_anthropic_provider = $this->createMock(WP_Content_Flow_Anthropic_Provider::class);

        // Initialize AI_Core with mocked providers
        $this->ai_core = new WP_Content_Flow_AI_Core();
        
        // Use reflection to inject mock providers for testing
        $reflection = new ReflectionClass($this->ai_core);
        $providers_property = $reflection->getProperty('providers');
        $providers_property->setAccessible(true);
        $providers_property->setValue($this->ai_core, [
            'openai' => $this->mock_openai_provider,
            'anthropic' => $this->mock_anthropic_provider,
        ]);
    }

    public function tearDown(): void {
        parent::tearDown();
        delete_option('wp_content_flow_settings');
    }

    /**
     * Test AI_Core initialization
     */
    public function test_ai_core_initialization() {
        $ai_core = new WP_Content_Flow_AI_Core();
        
        $this->assertInstanceOf(WP_Content_Flow_AI_Core::class, $ai_core);
        $this->assertTrue(method_exists($ai_core, 'generate_content'));
        $this->assertTrue(method_exists($ai_core, 'improve_content'));
        $this->assertTrue(method_exists($ai_core, 'get_available_providers'));
    }

    /**
     * Test provider registration
     */
    public function test_provider_registration() {
        $ai_core = new WP_Content_Flow_AI_Core();
        
        $providers = $ai_core->get_available_providers();
        
        $this->assertIsArray($providers);
        $this->assertArrayHasKey('openai', $providers);
        $this->assertArrayHasKey('anthropic', $providers);
        
        foreach ($providers as $provider_id => $provider_info) {
            $this->assertArrayHasKey('name', $provider_info);
            $this->assertArrayHasKey('description', $provider_info);
            $this->assertArrayHasKey('enabled', $provider_info);
        }
    }

    /**
     * Test default provider selection
     */
    public function test_default_provider_selection() {
        // No settings configured
        $provider = $this->ai_core->get_default_provider();
        $this->assertEquals('openai', $provider);

        // Configure default provider
        update_option('wp_content_flow_settings', [
            'default_ai_provider' => 'anthropic'
        ]);

        $provider = $this->ai_core->get_default_provider();
        $this->assertEquals('anthropic', $provider);
    }

    /**
     * Test content generation with OpenAI provider
     */
    public function test_generate_content_with_openai() {
        $prompt = 'Write a blog post about WordPress development';
        $options = [
            'provider' => 'openai',
            'max_tokens' => 1000,
            'temperature' => 0.7
        ];

        $expected_response = [
            'content' => 'WordPress is a powerful content management system...',
            'usage' => [
                'prompt_tokens' => 15,
                'completion_tokens' => 150,
                'total_tokens' => 165
            ],
            'model' => 'gpt-4',
            'provider' => 'openai'
        ];

        $this->mock_openai_provider
            ->expects($this->once())
            ->method('generate_content')
            ->with($prompt, $options)
            ->willReturn($expected_response);

        $result = $this->ai_core->generate_content($prompt, $options);

        $this->assertIsArray($result);
        $this->assertEquals($expected_response['content'], $result['content']);
        $this->assertEquals($expected_response['usage'], $result['usage']);
        $this->assertEquals('openai', $result['provider']);
    }

    /**
     * Test content generation with Anthropic provider
     */
    public function test_generate_content_with_anthropic() {
        $prompt = 'Explain machine learning concepts';
        $options = [
            'provider' => 'anthropic',
            'max_tokens' => 800,
            'temperature' => 0.5
        ];

        $expected_response = [
            'content' => 'Machine learning is a subset of artificial intelligence...',
            'usage' => [
                'input_tokens' => 12,
                'output_tokens' => 120,
                'total_tokens' => 132
            ],
            'model' => 'claude-3-sonnet',
            'provider' => 'anthropic'
        ];

        $this->mock_anthropic_provider
            ->expects($this->once())
            ->method('generate_content')
            ->with($prompt, $options)
            ->willReturn($expected_response);

        $result = $this->ai_core->generate_content($prompt, $options);

        $this->assertIsArray($result);
        $this->assertEquals($expected_response['content'], $result['content']);
        $this->assertEquals('anthropic', $result['provider']);
    }

    /**
     * Test content improvement functionality
     */
    public function test_improve_content() {
        $original_content = 'This is content that needs improvement.';
        $improvement_type = 'grammar';
        $options = [
            'provider' => 'openai'
        ];

        $expected_response = [
            'improved_content' => 'This is content that has been improved.',
            'improvements' => [
                [
                    'type' => 'grammar',
                    'description' => 'Fixed verb tense consistency',
                    'original' => 'needs improvement',
                    'improved' => 'has been improved'
                ]
            ],
            'confidence_score' => 0.92,
            'provider' => 'openai'
        ];

        $this->mock_openai_provider
            ->expects($this->once())
            ->method('improve_content')
            ->with($original_content, $improvement_type, $options)
            ->willReturn($expected_response);

        $result = $this->ai_core->improve_content($original_content, $improvement_type, $options);

        $this->assertIsArray($result);
        $this->assertEquals($expected_response['improved_content'], $result['improved_content']);
        $this->assertIsArray($result['improvements']);
        $this->assertGreaterThan(0.8, $result['confidence_score']);
    }

    /**
     * Test provider fallback mechanism
     */
    public function test_provider_fallback() {
        $prompt = 'Test prompt for fallback';
        $options = [
            'provider' => 'openai',
            'enable_fallback' => true
        ];

        $fallback_response = [
            'content' => 'Generated with fallback provider',
            'usage' => ['total_tokens' => 50],
            'provider' => 'anthropic'
        ];

        // Mock primary provider failure
        $this->mock_openai_provider
            ->expects($this->once())
            ->method('generate_content')
            ->with($prompt, $options)
            ->willThrowException(new WP_Content_Flow_AI_Exception('API rate limit exceeded'));

        // Mock fallback provider success
        $this->mock_anthropic_provider
            ->expects($this->once())
            ->method('generate_content')
            ->with($prompt, $options)
            ->willReturn($fallback_response);

        $result = $this->ai_core->generate_content($prompt, $options);

        $this->assertIsArray($result);
        $this->assertEquals('anthropic', $result['provider']);
        $this->assertEquals('Generated with fallback provider', $result['content']);
    }

    /**
     * Test invalid provider handling
     */
    public function test_invalid_provider_handling() {
        $prompt = 'Test prompt';
        $options = [
            'provider' => 'invalid_provider'
        ];

        $this->expectException(WP_Content_Flow_AI_Exception::class);
        $this->expectExceptionMessage('Unsupported AI provider: invalid_provider');

        $this->ai_core->generate_content($prompt, $options);
    }

    /**
     * Test empty prompt handling
     */
    public function test_empty_prompt_handling() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Prompt cannot be empty');

        $this->ai_core->generate_content('', ['provider' => 'openai']);
    }

    /**
     * Test parameter validation
     */
    public function test_parameter_validation() {
        $prompt = 'Test prompt';

        // Test invalid temperature
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Temperature must be between 0 and 2');

        $this->ai_core->generate_content($prompt, [
            'provider' => 'openai',
            'temperature' => 3.0
        ]);
    }

    /**
     * Test max tokens validation
     */
    public function test_max_tokens_validation() {
        $prompt = 'Test prompt';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max tokens must be between 1 and 4000');

        $this->ai_core->generate_content($prompt, [
            'provider' => 'openai',
            'max_tokens' => 5000
        ]);
    }

    /**
     * Test rate limiting
     */
    public function test_rate_limiting() {
        $prompt = 'Test rate limiting';
        $options = ['provider' => 'openai'];

        // Enable rate limiting
        update_option('wp_content_flow_settings', [
            'enable_rate_limiting' => true,
            'requests_per_minute' => 2
        ]);

        // Mock successful responses
        $this->mock_openai_provider
            ->method('generate_content')
            ->willReturn([
                'content' => 'Generated content',
                'usage' => ['total_tokens' => 50],
                'provider' => 'openai'
            ]);

        // First two requests should succeed
        $result1 = $this->ai_core->generate_content($prompt, $options);
        $result2 = $this->ai_core->generate_content($prompt, $options);

        $this->assertIsArray($result1);
        $this->assertIsArray($result2);

        // Third request should be rate limited
        $this->expectException(WP_Content_Flow_Rate_Limit_Exception::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        $this->ai_core->generate_content($prompt, $options);
    }

    /**
     * Test content caching
     */
    public function test_content_caching() {
        $prompt = 'Test caching mechanism';
        $options = [
            'provider' => 'openai',
            'enable_caching' => true
        ];

        $expected_response = [
            'content' => 'Cached content response',
            'usage' => ['total_tokens' => 25],
            'provider' => 'openai'
        ];

        // Mock provider should only be called once due to caching
        $this->mock_openai_provider
            ->expects($this->once())
            ->method('generate_content')
            ->with($prompt, $options)
            ->willReturn($expected_response);

        // First call - should hit provider
        $result1 = $this->ai_core->generate_content($prompt, $options);
        
        // Second call - should return cached result
        $result2 = $this->ai_core->generate_content($prompt, $options);

        $this->assertEquals($expected_response['content'], $result1['content']);
        $this->assertEquals($expected_response['content'], $result2['content']);
        $this->assertEquals($result1, $result2);
    }

    /**
     * Test usage tracking
     */
    public function test_usage_tracking() {
        $prompt = 'Test usage tracking';
        $options = ['provider' => 'openai'];

        $this->mock_openai_provider
            ->method('generate_content')
            ->willReturn([
                'content' => 'Generated content',
                'usage' => ['total_tokens' => 100],
                'provider' => 'openai'
            ]);

        // Generate content to accumulate usage
        $this->ai_core->generate_content($prompt, $options);
        $this->ai_core->generate_content($prompt, $options);

        $usage_stats = $this->ai_core->get_usage_stats();

        $this->assertIsArray($usage_stats);
        $this->assertArrayHasKey('total_requests', $usage_stats);
        $this->assertArrayHasKey('total_tokens', $usage_stats);
        $this->assertEquals(2, $usage_stats['total_requests']);
        $this->assertEquals(200, $usage_stats['total_tokens']);
    }

    /**
     * Test daily usage limits
     */
    public function test_daily_usage_limits() {
        $prompt = 'Test daily limits';
        $options = ['provider' => 'openai'];

        // Set daily limit
        update_option('wp_content_flow_settings', [
            'daily_token_limit' => 100
        ]);

        $this->mock_openai_provider
            ->method('generate_content')
            ->willReturn([
                'content' => 'Generated content',
                'usage' => ['total_tokens' => 60],
                'provider' => 'openai'
            ]);

        // First request should succeed
        $result1 = $this->ai_core->generate_content($prompt, $options);
        $this->assertIsArray($result1);

        // Second request should exceed daily limit
        $this->expectException(WP_Content_Flow_Usage_Limit_Exception::class);
        $this->expectExceptionMessage('Daily token limit exceeded');

        $this->ai_core->generate_content($prompt, $options);
    }

    /**
     * Test async content generation
     */
    public function test_async_content_generation() {
        $this->markTestIncomplete('Async functionality requires WordPress background processing');
        
        $prompt = 'Test async generation';
        $options = [
            'provider' => 'openai',
            'async' => true
        ];

        $job_id = $this->ai_core->generate_content_async($prompt, $options);

        $this->assertIsString($job_id);
        $this->assertTrue(strlen($job_id) > 10);

        // Check job status
        $status = $this->ai_core->get_async_job_status($job_id);
        $this->assertEquals('queued', $status['status']);
    }

    /**
     * Test provider health checks
     */
    public function test_provider_health_checks() {
        $this->mock_openai_provider
            ->method('health_check')
            ->willReturn([
                'status' => 'healthy',
                'response_time' => 150,
                'last_checked' => time()
            ]);

        $this->mock_anthropic_provider
            ->method('health_check')
            ->willReturn([
                'status' => 'degraded',
                'response_time' => 500,
                'last_checked' => time()
            ]);

        $health_status = $this->ai_core->check_provider_health();

        $this->assertIsArray($health_status);
        $this->assertEquals('healthy', $health_status['openai']['status']);
        $this->assertEquals('degraded', $health_status['anthropic']['status']);
    }

    /**
     * Test custom prompt templates
     */
    public function test_custom_prompt_templates() {
        $template_name = 'blog_post';
        $variables = [
            'topic' => 'WordPress development',
            'tone' => 'professional',
            'length' => 'medium'
        ];

        $expected_prompt = 'Write a professional blog post about WordPress development with medium length.';

        $processed_prompt = $this->ai_core->process_prompt_template($template_name, $variables);

        $this->assertEquals($expected_prompt, $processed_prompt);
    }

    /**
     * Test content filtering and validation
     */
    public function test_content_filtering() {
        $harmful_content = 'This content contains harmful information...';
        $safe_content = 'This is safe, helpful content.';

        $this->assertTrue($this->ai_core->is_content_safe($safe_content));
        $this->assertFalse($this->ai_core->is_content_safe($harmful_content));
    }

    /**
     * Test error recovery mechanisms
     */
    public function test_error_recovery() {
        $prompt = 'Test error recovery';
        $options = [
            'provider' => 'openai',
            'retry_on_failure' => true,
            'max_retries' => 2
        ];

        // Mock failure then success
        $this->mock_openai_provider
            ->expects($this->exactly(2))
            ->method('generate_content')
            ->withConsecutive(
                [$prompt, $options],
                [$prompt, $options]
            )
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new WP_Content_Flow_Temporary_Exception('Temporary failure')),
                [
                    'content' => 'Recovery successful',
                    'usage' => ['total_tokens' => 30],
                    'provider' => 'openai'
                ]
            );

        $result = $this->ai_core->generate_content($prompt, $options);

        $this->assertEquals('Recovery successful', $result['content']);
    }

    /**
     * Test integration with WordPress hooks
     */
    public function test_wordpress_hooks_integration() {
        $filter_called = false;
        $action_called = false;

        // Add test filters
        add_filter('wp_content_flow_before_generate', function($prompt, $options) use (&$filter_called) {
            $filter_called = true;
            return $prompt;
        }, 10, 2);

        add_action('wp_content_flow_after_generate', function($result) use (&$action_called) {
            $action_called = true;
        });

        $this->mock_openai_provider
            ->method('generate_content')
            ->willReturn([
                'content' => 'Test content',
                'usage' => ['total_tokens' => 20],
                'provider' => 'openai'
            ]);

        $this->ai_core->generate_content('Test prompt', ['provider' => 'openai']);

        $this->assertTrue($filter_called, 'Before generate filter was not called');
        $this->assertTrue($action_called, 'After generate action was not called');
    }
}