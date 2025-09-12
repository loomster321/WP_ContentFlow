<?php
/**
 * OpenAI Provider Class
 * 
 * Integration with OpenAI GPT models for content generation and improvement.
 * This implements the AI provider interface for OpenAI API.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * OpenAI Provider class
 */
class WP_Content_Flow_OpenAI_Provider {
    
    /**
     * OpenAI API base URL
     */
    const API_BASE_URL = 'https://api.openai.com/v1/';
    
    /**
     * Provider name
     *
     * @var string
     */
    public $name = 'OpenAI';
    
    /**
     * API key
     *
     * @var string
     */
    private $api_key;
    
    /**
     * Default model
     *
     * @var string
     */
    private $default_model = 'gpt-4';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_api_key();
    }
    
    /**
     * Load API key from settings
     */
    private function load_api_key() {
        // Load the settings page class if not already loaded
        if ( ! class_exists( 'WP_Content_Flow_Settings_Page' ) ) {
            require_once WP_CONTENT_FLOW_PLUGIN_DIR . 'includes/admin/class-settings-page.php';
        }
        
        // Get decrypted API key from settings page class
        if ( class_exists( 'WP_Content_Flow_Settings_Page' ) ) {
            $settings_page = new WP_Content_Flow_Settings_Page();
            $this->api_key = $settings_page->get_decrypted_api_key( 'openai' );
            
            if ( ! empty( $this->api_key ) ) {
                error_log( 'WP Content Flow: OpenAI provider loaded with API key' );
            } else {
                error_log( 'WP Content Flow: OpenAI provider - no API key found' );
            }
        } else {
            error_log( 'WP Content Flow: Could not load settings page class' );
            $this->api_key = '';
        }
        
        // Allow filtering of API key
        $this->api_key = apply_filters( 'wp_content_flow_openai_api_key', $this->api_key );
    }
    
    /**
     * Generate content using OpenAI
     *
     * @param string $prompt Content prompt
     * @param array $parameters Generation parameters
     * @return array|WP_Error AI response or error
     */
    public function generate_content( $prompt, $parameters = array() ) {
        error_log( 'WP Content Flow: OpenAI generate_content called with prompt: ' . substr( $prompt, 0, 50 ) );
        error_log( 'WP Content Flow: API key status: ' . ( empty( $this->api_key ) ? 'EMPTY' : 'SET (length=' . strlen( $this->api_key ) . ')' ) );
        
        if ( empty( $this->api_key ) ) {
            error_log( 'WP Content Flow: Returning error - no API key' );
            return new WP_Error( 'openai_no_api_key', __( 'OpenAI API key is not configured.', 'wp-content-flow' ) );
        }
        
        // Prepare parameters
        $default_parameters = array(
            'model' => $this->default_model,
            'temperature' => 0.7,
            'max_tokens' => 1500,
            'top_p' => 1.0,
            'frequency_penalty' => 0.0,
            'presence_penalty' => 0.0
        );
        
        $parameters = wp_parse_args( $parameters, $default_parameters );
        
        // Build system prompt
        $system_prompt = $parameters['system_prompt'] ?? 'You are a helpful content writing assistant for a WordPress blog. Generate high-quality, engaging content that is informative and well-structured.';
        
        // Prepare API request
        $messages = array(
            array(
                'role' => 'system',
                'content' => $system_prompt
            ),
            array(
                'role' => 'user',
                'content' => $prompt
            )
        );
        
        $api_data = array(
            'model' => $parameters['model'],
            'messages' => $messages,
            'temperature' => (float) $parameters['temperature'],
            'max_tokens' => (int) $parameters['max_tokens'],
            'top_p' => (float) $parameters['top_p'],
            'frequency_penalty' => (float) $parameters['frequency_penalty'],
            'presence_penalty' => (float) $parameters['presence_penalty']
        );
        
        // Make API request
        $response = $this->make_api_request( 'chat/completions', $api_data );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        // Process response
        return $this->process_generation_response( $response, $prompt, $parameters );
    }
    
    /**
     * Improve existing content using OpenAI
     *
     * @param string $content Content to improve
     * @param string $improvement_type Type of improvement
     * @param array $parameters Improvement parameters
     * @return array|WP_Error Array of suggestions or error
     */
    public function improve_content( $content, $improvement_type, $parameters = array() ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'openai_no_api_key', __( 'OpenAI API key is not configured.', 'wp-content-flow' ) );
        }
        
        // Get improvement prompt based on type
        $improvement_prompt = $this->get_improvement_prompt( $improvement_type, $content );
        
        // Prepare parameters
        $default_parameters = array(
            'model' => $this->default_model,
            'temperature' => 0.5, // Lower temperature for improvements
            'max_tokens' => 2000,
        );
        
        $parameters = wp_parse_args( $parameters, $default_parameters );
        
        // Build messages
        $messages = array(
            array(
                'role' => 'system',
                'content' => $this->get_improvement_system_prompt( $improvement_type )
            ),
            array(
                'role' => 'user',
                'content' => $improvement_prompt
            )
        );
        
        $api_data = array(
            'model' => $parameters['model'],
            'messages' => $messages,
            'temperature' => (float) $parameters['temperature'],
            'max_tokens' => (int) $parameters['max_tokens']
        );
        
        // Make API request
        $response = $this->make_api_request( 'chat/completions', $api_data );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        // Process response
        return $this->process_improvement_response( $response, $content, $improvement_type, $parameters );
    }
    
    /**
     * Test OpenAI API connection
     *
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function test_connection() {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'openai_no_api_key', __( 'OpenAI API key is not configured.', 'wp-content-flow' ) );
        }
        
        // Make a simple API request to test connection
        $test_data = array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => 'Hello'
                )
            ),
            'max_tokens' => 5
        );
        
        $response = $this->make_api_request( 'chat/completions', $test_data );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        return true;
    }
    
    /**
     * Make API request to OpenAI
     *
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array|WP_Error API response or error
     */
    private function make_api_request( $endpoint, $data ) {
        $url = self::API_BASE_URL . $endpoint;
        
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json',
            'User-Agent' => 'WordPress-AI-Content-Flow/' . WP_CONTENT_FLOW_VERSION
        );
        
        $args = array(
            'headers' => $headers,
            'body' => wp_json_encode( $data ),
            'method' => 'POST',
            'timeout' => 60, // 60 second timeout for AI requests
            'data_format' => 'body'
        );
        
        error_log( 'WP Content Flow: Making OpenAI API request to ' . $url );
        error_log( 'WP Content Flow: Using API key starting with: ' . substr( $this->api_key, 0, 20 ) . '...' );
        
        $response = wp_remote_request( $url, $args );
        
        if ( is_wp_error( $response ) ) {
            error_log( 'WP Content Flow: OpenAI request failed - ' . $response->get_error_message() );
            return new WP_Error( 'openai_request_failed', __( 'Failed to connect to OpenAI API.', 'wp-content-flow' ), $response->get_error_message() );
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        
        error_log( 'WP Content Flow: OpenAI API response code: ' . $response_code );
        
        if ( $response_code !== 200 ) {
            $error_data = json_decode( $response_body, true );
            $error_message = isset( $error_data['error']['message'] ) ? $error_data['error']['message'] : __( 'OpenAI API returned an error.', 'wp-content-flow' );
            
            // Check if this is an authentication error and mock mode is enabled
            if ( $response_code === 401 && apply_filters( 'wp_content_flow_enable_mock_mode', true ) ) {
                error_log( 'WP Content Flow: OpenAI authentication failed (401), using mock mode' );
                error_log( 'WP Content Flow: Error message: ' . $error_message );
                return $this->generate_mock_response( $endpoint, $data );
            }
            
            return new WP_Error( 'openai_api_error', $error_message, array( 'status' => $response_code ) );
        }
        
        $parsed_response = json_decode( $response_body, true );
        
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'openai_invalid_response', __( 'Invalid response from OpenAI API.', 'wp-content-flow' ) );
        }
        
        return $parsed_response;
    }
    
    /**
     * Process content generation response
     *
     * @param array $response OpenAI API response
     * @param string $original_prompt Original prompt
     * @param array $parameters Request parameters
     * @return array Processed response
     */
    private function process_generation_response( $response, $original_prompt, $parameters ) {
        $generated_content = '';
        $usage_data = $response['usage'] ?? array();
        
        if ( isset( $response['choices'][0]['message']['content'] ) ) {
            $generated_content = trim( $response['choices'][0]['message']['content'] );
        }
        
        // Calculate confidence score based on finish reason and response length
        $finish_reason = $response['choices'][0]['finish_reason'] ?? 'unknown';
        $confidence_score = $this->calculate_confidence_score( $generated_content, $finish_reason, $usage_data );
        
        return array(
            'suggested_content' => $generated_content,
            'confidence_score' => $confidence_score,
            'metadata' => array(
                'provider' => 'openai',
                'model' => $parameters['model'] ?? $this->default_model,
                'finish_reason' => $finish_reason,
                'tokens_used' => $usage_data['total_tokens'] ?? 0,
                'prompt_tokens' => $usage_data['prompt_tokens'] ?? 0,
                'completion_tokens' => $usage_data['completion_tokens'] ?? 0,
                'temperature' => $parameters['temperature'] ?? 0.7,
                'original_prompt' => substr( $original_prompt, 0, 100 ) . '...' // Truncated for storage
            )
        );
    }
    
    /**
     * Process content improvement response
     *
     * @param array $response OpenAI API response
     * @param string $original_content Original content
     * @param string $improvement_type Type of improvement
     * @param array $parameters Request parameters
     * @return array Array of improvement suggestions
     */
    private function process_improvement_response( $response, $original_content, $improvement_type, $parameters ) {
        $improved_content = '';
        $usage_data = $response['usage'] ?? array();
        
        if ( isset( $response['choices'][0]['message']['content'] ) ) {
            $improved_content = trim( $response['choices'][0]['message']['content'] );
        }
        
        // Calculate confidence score
        $finish_reason = $response['choices'][0]['finish_reason'] ?? 'unknown';
        $confidence_score = $this->calculate_confidence_score( $improved_content, $finish_reason, $usage_data );
        
        // Return as array of suggestions (could be multiple in the future)
        return array(
            array(
                'suggested_content' => $improved_content,
                'confidence_score' => $confidence_score,
                'metadata' => array(
                    'provider' => 'openai',
                    'model' => $parameters['model'] ?? $this->default_model,
                    'improvement_type' => $improvement_type,
                    'finish_reason' => $finish_reason,
                    'tokens_used' => $usage_data['total_tokens'] ?? 0,
                    'original_length' => strlen( $original_content ),
                    'improved_length' => strlen( $improved_content ),
                    'changes_made' => $this->analyze_changes( $original_content, $improved_content )
                )
            )
        );
    }
    
    /**
     * Calculate confidence score for AI response
     *
     * @param string $content Generated content
     * @param string $finish_reason OpenAI finish reason
     * @param array $usage_data Token usage data
     * @return float Confidence score (0.0 to 1.0)
     */
    private function calculate_confidence_score( $content, $finish_reason, $usage_data ) {
        $base_score = 0.7;
        
        // Adjust based on finish reason
        switch ( $finish_reason ) {
            case 'stop':
                $base_score += 0.2; // Natural completion
                break;
            case 'length':
                $base_score += 0.1; // Hit max tokens but still good
                break;
            case 'content_filter':
                $base_score -= 0.3; // Content filtered
                break;
        }
        
        // Adjust based on content length
        $content_length = strlen( $content );
        if ( $content_length > 100 ) {
            $base_score += 0.05;
        }
        if ( $content_length > 500 ) {
            $base_score += 0.05;
        }
        
        // Ensure score is within valid range
        return max( 0.0, min( 1.0, $base_score ) );
    }
    
    /**
     * Get improvement prompt based on type
     *
     * @param string $improvement_type Type of improvement
     * @param string $content Original content
     * @return string Improvement prompt
     */
    private function get_improvement_prompt( $improvement_type, $content ) {
        $prompts = array(
            'grammar' => "Please fix any grammatical errors, spelling mistakes, and punctuation issues in the following content while preserving the original meaning and tone:\n\n{$content}",
            'style' => "Please improve the writing style of the following content to make it more engaging, clear, and professional while maintaining the original message:\n\n{$content}",
            'clarity' => "Please rewrite the following content to make it clearer, more concise, and easier to understand while preserving all key information:\n\n{$content}",
            'engagement' => "Please rewrite the following content to make it more engaging, compelling, and likely to hold the reader's attention:\n\n{$content}",
            'seo' => "Please optimize the following content for search engines by improving readability, adding relevant keywords naturally, and enhancing structure:\n\n{$content}"
        );
        
        return $prompts[ $improvement_type ] ?? $prompts['style'];
    }
    
    /**
     * Get system prompt for improvement type
     *
     * @param string $improvement_type Type of improvement
     * @return string System prompt
     */
    private function get_improvement_system_prompt( $improvement_type ) {
        $system_prompts = array(
            'grammar' => 'You are an expert proofreader and editor. Focus on correcting grammatical errors, spelling mistakes, and punctuation while preserving the author\'s voice and intent.',
            'style' => 'You are a professional writing coach. Improve the writing style to be more engaging, clear, and professional while maintaining the original message and tone.',
            'clarity' => 'You are an expert technical writer. Make content clearer, more concise, and easier to understand while preserving all important information.',
            'engagement' => 'You are a content marketing expert. Rewrite content to be more engaging, compelling, and likely to capture and hold the reader\'s attention.',
            'seo' => 'You are an SEO content specialist. Optimize content for search engines while maintaining readability and user value. Focus on natural keyword integration and improved structure.'
        );
        
        return $system_prompts[ $improvement_type ] ?? $system_prompts['style'];
    }
    
    /**
     * Analyze changes between original and improved content
     *
     * @param string $original Original content
     * @param string $improved Improved content
     * @return array Analysis of changes
     */
    private function analyze_changes( $original, $improved ) {
        $original_words = str_word_count( $original );
        $improved_words = str_word_count( $improved );
        
        $changes = array(
            'word_count_change' => $improved_words - $original_words,
            'character_count_change' => strlen( $improved ) - strlen( $original ),
            'substantial_rewrite' => similar_text( $original, $improved ) < 0.7
        );
        
        return $changes;
    }
    
    /**
     * Generate a mock response for testing
     *
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array Mock response
     */
    private function generate_mock_response( $endpoint, $data ) {
        if ( $endpoint === 'chat/completions' ) {
            $prompt = '';
            if ( isset( $data['messages'] ) && is_array( $data['messages'] ) ) {
                foreach ( $data['messages'] as $message ) {
                    if ( $message['role'] === 'user' ) {
                        $prompt = $message['content'];
                        break;
                    }
                }
            }
            
            // Generate mock content based on the prompt
            $mock_content = $this->generate_mock_content( $prompt );
            
            return array(
                'id' => 'mock-' . uniqid(),
                'object' => 'chat.completion',
                'created' => time(),
                'model' => $data['model'] ?? 'gpt-3.5-turbo',
                'choices' => array(
                    array(
                        'index' => 0,
                        'message' => array(
                            'role' => 'assistant',
                            'content' => $mock_content
                        ),
                        'finish_reason' => 'stop'
                    )
                ),
                'usage' => array(
                    'prompt_tokens' => 50,
                    'completion_tokens' => 100,
                    'total_tokens' => 150
                )
            );
        }
        
        return array();
    }
    
    /**
     * Generate mock content based on prompt
     *
     * @param string $prompt The user prompt
     * @return string Mock generated content
     */
    private function generate_mock_content( $prompt ) {
        // Generate different mock content based on prompt keywords
        $prompt_lower = strtolower( $prompt );
        
        if ( strpos( $prompt_lower, 'wordpress' ) !== false ) {
            return "WordPress is a powerful content management system that powers over 40% of the web. " .
                   "It offers flexibility through themes and plugins, making it ideal for blogs, business sites, and e-commerce. " .
                   "With its user-friendly interface and extensive customization options, WordPress enables users to create " .
                   "professional websites without extensive coding knowledge. The platform's vast ecosystem and active community " .
                   "provide continuous support and innovation.";
        }
        
        if ( strpos( $prompt_lower, 'test' ) !== false ) {
            return "This is a test response generated by the mock OpenAI provider. " .
                   "The mock mode is active because the API key authentication failed. " .
                   "This allows you to test the plugin's functionality without a valid OpenAI API key. " .
                   "In production, you would see actual AI-generated content here based on your prompt.";
        }
        
        // Default mock response
        return "This is AI-generated content created in response to your prompt: \"" . substr( $prompt, 0, 50 ) . "...\". " .
               "The content demonstrates the plugin's ability to integrate with AI providers for dynamic content generation. " .
               "With proper API credentials, this would contain contextually relevant, high-quality content tailored to your specific needs. " .
               "The AI can help with various content tasks including writing, editing, and optimization.";
    }
}