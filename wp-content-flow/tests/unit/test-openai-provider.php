<?php
/**
 * PHPUnit tests for WP_Content_Flow_OpenAI_Provider class
 */

class Test_OpenAI_Provider extends WP_Content_Flow_Test_Case {

    private $openai_provider;
    private $api_key = 'sk-test1234567890abcdef1234567890abcdef1234567890abcdef';

    public function setUp(): void {
        parent::setUp();

        // Set up mock API key
        update_option('wp_content_flow_settings', [
            'openai_api_key' => $this->api_key,
            'openai_model' => 'gpt-4'
        ]);

        $this->openai_provider = new WP_Content_Flow_OpenAI_Provider();
    }

    public function tearDown(): void {
        parent::tearDown();
        delete_option('wp_content_flow_settings');
        
        // Clean up any HTTP request mocks
        if (isset($GLOBALS['wp_tests_http_response'])) {
            unset($GLOBALS['wp_tests_http_response']);
        }
    }

    /**
     * Test provider initialization
     */
    public function test_provider_initialization() {
        $provider = new WP_Content_Flow_OpenAI_Provider();
        
        $this->assertInstanceOf(WP_Content_Flow_OpenAI_Provider::class, $provider);
        $this->assertTrue(method_exists($provider, 'generate_content'));
        $this->assertTrue(method_exists($provider, 'improve_content'));
        $this->assertTrue(method_exists($provider, 'health_check'));
    }

    /**
     * Test API key validation
     */
    public function test_api_key_validation() {
        // Valid API key format
        $this->assertTrue($this->openai_provider->validate_api_key($this->api_key));
        
        // Invalid API key formats
        $this->assertFalse($this->openai_provider->validate_api_key('invalid-key'));
        $this->assertFalse($this->openai_provider->validate_api_key('sk-short'));
        $this->assertFalse($this->openai_provider->validate_api_key(''));
        $this->assertFalse($this->openai_provider->validate_api_key(null));
    }

    /**
     * Test content generation with successful API response
     */
    public function test_successful_content_generation() {
        $prompt = 'Write a short paragraph about WordPress';
        $options = [
            'max_tokens' => 150,
            'temperature' => 0.7
        ];

        // Mock successful OpenAI API response
        $this->mock_http_response([
            'response' => ['code' => 200],
            'body' => json_encode([
                'id' => 'chatcmpl-123',
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'gpt-4',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'WordPress is a powerful content management system that powers over 40% of all websites on the internet.'
                        ],
                        'finish_reason' => 'stop'
                    ]
                ],
                'usage' => [
                    'prompt_tokens' => 12,
                    'completion_tokens' => 23,
                    'total_tokens' => 35
                ]
            ])
        ]);

        $result = $this->openai_provider->generate_content($prompt, $options);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('usage', $result);
        $this->assertArrayHasKey('model', $result);
        $this->assertArrayHasKey('provider', $result);

        $this->assertEquals('WordPress is a powerful content management system that powers over 40% of all websites on the internet.', $result['content']);
        $this->assertEquals('gpt-4', $result['model']);
        $this->assertEquals('openai', $result['provider']);
        $this->assertEquals(35, $result['usage']['total_tokens']);
    }

    /**
     * Test content improvement functionality
     */
    public function test_content_improvement() {
        $original_content = 'This sentence have grammar errors.';
        $improvement_type = 'grammar';

        // Mock successful improvement response
        $this->mock_http_response([
            'response' => ['code' => 200],
            'body' => json_encode([
                'id' => 'chatcmpl-456',
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'gpt-4',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'This sentence has grammar errors.'
                        ],
                        'finish_reason' => 'stop'
                    ]
                ],
                'usage' => [
                    'prompt_tokens' => 25,
                    'completion_tokens' => 8,
                    'total_tokens' => 33
                ]
            ])
        ]);

        $result = $this->openai_provider->improve_content($original_content, $improvement_type);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('improved_content', $result);
        $this->assertArrayHasKey('improvements', $result);
        $this->assertArrayHasKey('confidence_score', $result);
        $this->assertArrayHasKey('provider', $result);

        $this->assertEquals('This sentence has grammar errors.', $result['improved_content']);
        $this->assertEquals('openai', $result['provider']);
        $this->assertIsArray($result['improvements']);
        $this->assertIsFloat($result['confidence_score']);
        $this->assertGreaterThan(0.5, $result['confidence_score']);
    }

    /**
     * Test API error handling
     */
    public function test_api_error_handling() {
        $prompt = 'Test error handling';
        
        // Mock API error response
        $this->mock_http_response([
            'response' => ['code' => 429],
            'body' => json_encode([
                'error' => [
                    'message' => 'Rate limit reached',
                    'type' => 'rate_limit_exceeded',
                    'code' => null
                ]
            ])
        ]);

        $this->expectException(WP_Content_Flow_AI_Exception::class);
        $this->expectExceptionMessage('OpenAI API Error: Rate limit reached');

        $this->openai_provider->generate_content($prompt);
    }

    /**
     * Test different error status codes
     */
    public function test_different_error_status_codes() {
        $prompt = 'Test error codes';

        // Test 401 Unauthorized
        $this->mock_http_response([
            'response' => ['code' => 401],
            'body' => json_encode([
                'error' => [
                    'message' => 'Invalid API key',
                    'type' => 'invalid_api_key'
                ]
            ])
        ]);

        $this->expectException(WP_Content_Flow_Auth_Exception::class);
        $this->expectExceptionMessage('OpenAI Authentication Error: Invalid API key');

        $this->openai_provider->generate_content($prompt);
    }

    /**
     * Test network timeout handling
     */
    public function test_network_timeout_handling() {
        $prompt = 'Test timeout';

        // Mock network timeout
        $this->mock_http_response(new WP_Error('http_request_timeout', 'Request timeout'));

        $this->expectException(WP_Content_Flow_Network_Exception::class);
        $this->expectExceptionMessage('Network error: Request timeout');

        $this->openai_provider->generate_content($prompt);
    }

    /**
     * Test parameter validation
     */
    public function test_parameter_validation() {
        // Test empty prompt
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Prompt cannot be empty');

        $this->openai_provider->generate_content('');
    }

    /**
     * Test temperature validation
     */
    public function test_temperature_validation() {
        $prompt = 'Test temperature validation';
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Temperature must be between 0 and 2');

        $this->openai_provider->generate_content($prompt, ['temperature' => 3.0]);
    }

    /**
     * Test max tokens validation
     */
    public function test_max_tokens_validation() {
        $prompt = 'Test max tokens validation';
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max tokens must be between 1 and 4096');

        $this->openai_provider->generate_content($prompt, ['max_tokens' => 5000]);
    }

    /**
     * Test model selection
     */
    public function test_model_selection() {
        $prompt = 'Test model selection';
        
        // Test with different models
        $models_to_test = ['gpt-3.5-turbo', 'gpt-4', 'gpt-4-turbo-preview'];

        foreach ($models_to_test as $model) {
            $this->mock_http_response([
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'test-completion',
                    'model' => $model,
                    'choices' => [
                        [
                            'message' => [
                                'content' => 'Test response for ' . $model
                            ],
                            'finish_reason' => 'stop'
                        ]
                    ],
                    'usage' => ['total_tokens' => 20]
                ])
            ]);

            $result = $this->openai_provider->generate_content($prompt, ['model' => $model]);
            $this->assertEquals($model, $result['model']);
        }
    }

    /**
     * Test health check functionality
     */
    public function test_health_check() {
        // Mock successful health check
        $this->mock_http_response([
            'response' => ['code' => 200],
            'body' => json_encode([
                'object' => 'list',
                'data' => [
                    ['id' => 'gpt-3.5-turbo'],
                    ['id' => 'gpt-4']
                ]
            ])
        ]);

        $health_status = $this->openai_provider->health_check();

        $this->assertIsArray($health_status);
        $this->assertArrayHasKey('status', $health_status);
        $this->assertArrayHasKey('response_time', $health_status);
        $this->assertArrayHasKey('last_checked', $health_status);

        $this->assertEquals('healthy', $health_status['status']);
        $this->assertIsInt($health_status['response_time']);
        $this->assertIsInt($health_status['last_checked']);
    }

    /**
     * Test failed health check
     */
    public function test_failed_health_check() {
        // Mock failed health check
        $this->mock_http_response([
            'response' => ['code' => 500],
            'body' => 'Internal Server Error'
        ]);

        $health_status = $this->openai_provider->health_check();

        $this->assertEquals('unhealthy', $health_status['status']);
        $this->assertArrayHasKey('error', $health_status);
    }

    /**
     * Test streaming response handling
     */
    public function test_streaming_response() {
        $this->markTestIncomplete('Streaming functionality requires WebSocket or SSE implementation');
        
        $prompt = 'Test streaming response';
        $options = ['stream' => true];

        $stream_handler = function($chunk) {
            $this->assertIsString($chunk);
            return strlen($chunk);
        };

        $result = $this->openai_provider->generate_content_stream($prompt, $options, $stream_handler);
        $this->assertIsArray($result);
    }

    /**
     * Test retry mechanism
     */
    public function test_retry_mechanism() {
        $prompt = 'Test retry mechanism';
        
        // First call fails, second succeeds
        $responses = [
            [
                'response' => ['code' => 502],
                'body' => 'Bad Gateway'
            ],
            [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'retry-test',
                    'model' => 'gpt-4',
                    'choices' => [
                        [
                            'message' => ['content' => 'Retry successful'],
                            'finish_reason' => 'stop'
                        ]
                    ],
                    'usage' => ['total_tokens' => 15]
                ])
            ]
        ];

        foreach ($responses as $response) {
            $this->mock_http_response($response);
        }

        $result = $this->openai_provider->generate_content($prompt, ['max_retries' => 1]);
        $this->assertEquals('Retry successful', $result['content']);
    }

    /**
     * Test token counting estimation
     */
    public function test_token_counting() {
        $text_samples = [
            'Hello world' => 2,
            'This is a longer sentence with more tokens.' => 8,
            'The quick brown fox jumps over the lazy dog.' => 9
        ];

        foreach ($text_samples as $text => $expected_tokens) {
            $estimated_tokens = $this->openai_provider->estimate_tokens($text);
            
            // Allow for some variance in estimation
            $this->assertGreaterThanOrEqual($expected_tokens - 2, $estimated_tokens);
            $this->assertLessThanOrEqual($expected_tokens + 2, $estimated_tokens);
        }
    }

    /**
     * Test request rate limiting
     */
    public function test_request_rate_limiting() {
        $this->markTestIncomplete('Rate limiting requires time-based testing');
        
        // This would test the built-in rate limiting mechanism
        // to prevent exceeding OpenAI's API limits
    }

    /**
     * Test content moderation integration
     */
    public function test_content_moderation() {
        $inappropriate_prompt = 'Generate harmful content';
        
        // Mock moderation API response
        $this->mock_http_response([
            'response' => ['code' => 200],
            'body' => json_encode([
                'id' => 'modr-123',
                'model' => 'text-moderation-stable',
                'results' => [
                    [
                        'flagged' => true,
                        'categories' => [
                            'hate' => false,
                            'violence' => true,
                            'sexual' => false
                        ],
                        'category_scores' => [
                            'hate' => 0.1,
                            'violence' => 0.8,
                            'sexual' => 0.05
                        ]
                    ]
                ]
            ])
        ]);

        $this->expectException(WP_Content_Flow_Content_Policy_Exception::class);
        $this->expectExceptionMessage('Content violates OpenAI usage policies');

        $this->openai_provider->generate_content($inappropriate_prompt, ['enable_moderation' => true]);
    }

    /**
     * Test custom system prompts
     */
    public function test_custom_system_prompts() {
        $prompt = 'Write about AI';
        $system_prompt = 'You are a helpful AI writing assistant specializing in WordPress content.';
        
        $this->mock_http_response([
            'response' => ['code' => 200],
            'body' => json_encode([
                'id' => 'system-prompt-test',
                'model' => 'gpt-4',
                'choices' => [
                    [
                        'message' => ['content' => 'AI is transforming WordPress development...'],
                        'finish_reason' => 'stop'
                    ]
                ],
                'usage' => ['total_tokens' => 25]
            ])
        ]);

        $result = $this->openai_provider->generate_content($prompt, ['system_prompt' => $system_prompt]);
        
        $this->assertIsArray($result);
        $this->assertStringContains('WordPress development', $result['content']);
    }

    /**
     * Test function calling (tools) support
     */
    public function test_function_calling() {
        $this->markTestIncomplete('Function calling requires complex mock setup');
        
        $prompt = 'Get current weather in New York';
        $functions = [
            [
                'name' => 'get_weather',
                'description' => 'Get current weather for a location',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'location' => ['type' => 'string']
                    ]
                ]
            ]
        ];

        $result = $this->openai_provider->generate_content($prompt, ['functions' => $functions]);
        $this->assertArrayHasKey('function_call', $result);
    }

    /**
     * Test batch request handling
     */
    public function test_batch_requests() {
        $this->markTestIncomplete('Batch processing requires async implementation');
        
        $prompts = [
            'Write about WordPress',
            'Explain PHP basics',
            'Describe REST APIs'
        ];

        $results = $this->openai_provider->generate_content_batch($prompts);
        
        $this->assertIsArray($results);
        $this->assertCount(3, $results);
        
        foreach ($results as $result) {
            $this->assertArrayHasKey('content', $result);
            $this->assertArrayHasKey('usage', $result);
        }
    }

    /**
     * Test cost calculation
     */
    public function test_cost_calculation() {
        $usage = [
            'prompt_tokens' => 100,
            'completion_tokens' => 150,
            'total_tokens' => 250
        ];

        $cost = $this->openai_provider->calculate_cost($usage, 'gpt-4');
        
        $this->assertIsFloat($cost);
        $this->assertGreaterThan(0, $cost);
        
        // GPT-4 should be more expensive than GPT-3.5-turbo
        $cost_gpt35 = $this->openai_provider->calculate_cost($usage, 'gpt-3.5-turbo');
        $this->assertGreaterThan($cost_gpt35, $cost);
    }

    /**
     * Mock HTTP response for testing
     */
    private function mock_http_response($response) {
        $GLOBALS['wp_tests_http_response'] = $response;
        
        add_filter('pre_http_request', function($preempt, $parsed_args, $url) use ($response) {
            if (strpos($url, 'api.openai.com') !== false) {
                if (is_wp_error($response)) {
                    return $response;
                }
                return $response;
            }
            return $preempt;
        }, 10, 3);
    }

    /**
     * Test WordPress integration
     */
    public function test_wordpress_integration() {
        // Test settings integration
        $settings = $this->openai_provider->get_settings();
        $this->assertIsArray($settings);
        $this->assertArrayHasKey('api_key', $settings);
        $this->assertArrayHasKey('model', $settings);

        // Test WordPress hooks
        $hook_fired = false;
        add_action('wp_content_flow_openai_request', function() use (&$hook_fired) {
            $hook_fired = true;
        });

        $this->mock_http_response([
            'response' => ['code' => 200],
            'body' => json_encode([
                'choices' => [['message' => ['content' => 'Test']]],
                'usage' => ['total_tokens' => 5]
            ])
        ]);

        $this->openai_provider->generate_content('Test prompt');
        $this->assertTrue($hook_fired, 'WordPress hook was not fired');
    }
}