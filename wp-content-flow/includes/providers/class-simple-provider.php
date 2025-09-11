<?php
/**
 * Simplified AI Provider Class
 * 
 * This class provides a simplified interface for AI providers that works
 * directly with plugin settings without requiring complex workflow infrastructure.
 * It's designed for the Gutenberg block integration.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Simplified AI Provider class
 */
class WP_Content_Flow_Simple_Provider {
    
    /**
     * Provider name (openai, anthropic, google)
     *
     * @var string
     */
    private $provider_name;
    
    /**
     * API key
     *
     * @var string
     */
    private $api_key;
    
    /**
     * Provider API endpoints
     *
     * @var array
     */
    private $endpoints = array(
        'openai' => 'https://api.openai.com/v1/chat/completions',
        'anthropic' => 'https://api.anthropic.com/v1/messages',
        'google' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent'
    );
    
    /**
     * Constructor
     *
     * @param string $provider_name Provider name
     * @param string $api_key API key
     */
    public function __construct( $provider_name, $api_key ) {
        $this->provider_name = $provider_name;
        $this->api_key = $api_key;
    }
    
    /**
     * Generate content
     *
     * @param string $prompt Content prompt
     * @param array $parameters AI parameters
     * @return array|WP_Error AI response or error
     */
    public function generate_content( $prompt, $parameters = array() ) {
        $max_tokens = $parameters['max_tokens'] ?? 1000;
        $temperature = $parameters['temperature'] ?? 0.7;
        
        switch ( $this->provider_name ) {
            case 'openai':
                return $this->generate_openai_content( $prompt, $max_tokens, $temperature );
            case 'anthropic':
                return $this->generate_anthropic_content( $prompt, $max_tokens, $temperature );
            case 'google':
                return $this->generate_google_content( $prompt, $max_tokens, $temperature );
            default:
                return new WP_Error( 'unknown_provider', __( 'Unknown AI provider.', 'wp-content-flow' ) );
        }
    }
    
    /**
     * Improve content
     *
     * @param string $content Content to improve
     * @param string $improvement_type Type of improvement
     * @param array $parameters AI parameters
     * @return array|WP_Error AI response or error
     */
    public function improve_content( $content, $improvement_type, $parameters = array() ) {
        // Create improvement prompt based on type
        $prompts = array(
            'grammar' => "Please correct any grammar errors in the following text while maintaining the original meaning and style:\n\n",
            'style' => "Please improve the writing style of the following text to make it more engaging and professional:\n\n",
            'clarity' => "Please rewrite the following text to make it clearer and easier to understand:\n\n",
            'engagement' => "Please rewrite the following text to make it more engaging and compelling for readers:\n\n",
            'seo' => "Please optimize the following text for SEO while maintaining readability and natural flow:\n\n",
            'general' => "Please improve the following text to make it better written, clearer, and more engaging:\n\n"
        );
        
        $prompt = $prompts[ $improvement_type ] ?? $prompts['general'];
        $prompt .= $content;
        
        $result = $this->generate_content( $prompt, $parameters );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        // Return as array of suggestions (for API compatibility)
        return array( $result );
    }
    
    /**
     * Generate content using OpenAI
     *
     * @param string $prompt Content prompt
     * @param int $max_tokens Maximum tokens
     * @param float $temperature Temperature setting
     * @return array|WP_Error AI response or error
     */
    private function generate_openai_content( $prompt, $max_tokens, $temperature ) {
        $endpoint = $this->endpoints['openai'];
        
        $body = array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => $max_tokens,
            'temperature' => $temperature
        );
        
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json'
        );
        
        $response = $this->make_api_request( $endpoint, $body, $headers );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        // Parse OpenAI response
        if ( isset( $response['choices'][0]['message']['content'] ) ) {
            return array(
                'content' => trim( $response['choices'][0]['message']['content'] ),
                'suggested_content' => trim( $response['choices'][0]['message']['content'] ),
                'confidence_score' => 0.85,
                'tokens_used' => $response['usage']['total_tokens'] ?? 0
            );
        }
        
        return new WP_Error( 'openai_response_error', __( 'Invalid response from OpenAI API.', 'wp-content-flow' ) );
    }
    
    /**
     * Generate content using Anthropic
     *
     * @param string $prompt Content prompt
     * @param int $max_tokens Maximum tokens
     * @param float $temperature Temperature setting
     * @return array|WP_Error AI response or error
     */
    private function generate_anthropic_content( $prompt, $max_tokens, $temperature ) {
        $endpoint = $this->endpoints['anthropic'];
        
        $body = array(
            'model' => 'claude-3-haiku-20240307',
            'max_tokens' => $max_tokens,
            'temperature' => $temperature,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            )
        );
        
        $headers = array(
            'x-api-key' => $this->api_key,
            'Content-Type' => 'application/json',
            'anthropic-version' => '2023-06-01'
        );
        
        $response = $this->make_api_request( $endpoint, $body, $headers );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        // Parse Anthropic response
        if ( isset( $response['content'][0]['text'] ) ) {
            return array(
                'content' => trim( $response['content'][0]['text'] ),
                'suggested_content' => trim( $response['content'][0]['text'] ),
                'confidence_score' => 0.85,
                'tokens_used' => $response['usage']['output_tokens'] ?? 0
            );
        }
        
        return new WP_Error( 'anthropic_response_error', __( 'Invalid response from Anthropic API.', 'wp-content-flow' ) );
    }
    
    /**
     * Generate content using Google AI
     *
     * @param string $prompt Content prompt
     * @param int $max_tokens Maximum tokens
     * @param float $temperature Temperature setting
     * @return array|WP_Error AI response or error
     */
    private function generate_google_content( $prompt, $max_tokens, $temperature ) {
        $endpoint = $this->endpoints['google'] . '?key=' . $this->api_key;
        
        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array( 'text' => $prompt )
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => $temperature,
                'maxOutputTokens' => $max_tokens,
                'topK' => 1,
                'topP' => 1
            )
        );
        
        $headers = array(
            'Content-Type' => 'application/json'
        );
        
        $response = $this->make_api_request( $endpoint, $body, $headers );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        // Parse Google AI response
        if ( isset( $response['candidates'][0]['content']['parts'][0]['text'] ) ) {
            return array(
                'content' => trim( $response['candidates'][0]['content']['parts'][0]['text'] ),
                'suggested_content' => trim( $response['candidates'][0]['content']['parts'][0]['text'] ),
                'confidence_score' => 0.85,
                'tokens_used' => $response['usageMetadata']['totalTokenCount'] ?? 0
            );
        }
        
        return new WP_Error( 'google_response_error', __( 'Invalid response from Google AI API.', 'wp-content-flow' ) );
    }
    
    /**
     * Make API request
     *
     * @param string $endpoint API endpoint
     * @param array $body Request body
     * @param array $headers Request headers
     * @return array|WP_Error Response data or error
     */
    private function make_api_request( $endpoint, $body, $headers ) {
        $args = array(
            'method' => 'POST',
            'headers' => $headers,
            'body' => json_encode( $body ),
            'timeout' => 30,
            'sslverify' => true
        );
        
        $response = wp_remote_request( $endpoint, $args );
        
        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'api_request_failed', sprintf( __( 'API request failed: %s', 'wp-content-flow' ), $response->get_error_message() ) );
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        
        if ( $response_code !== 200 ) {
            $error_data = json_decode( $response_body, true );
            $error_message = $error_data['error']['message'] ?? $error_data['error'] ?? 'Unknown API error';
            
            return new WP_Error( 'api_error', sprintf( __( 'API error (%d): %s', 'wp-content-flow' ), $response_code, $error_message ) );
        }
        
        $data = json_decode( $response_body, true );
        
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'json_decode_error', __( 'Failed to decode API response.', 'wp-content-flow' ) );
        }
        
        return $data;
    }
    
    /**
     * Test connection to the AI provider
     *
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function test_connection() {
        $test_prompt = "Hello, this is a test.";
        $result = $this->generate_content( $test_prompt, array( 'max_tokens' => 10 ) );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        return true;
    }
}