<?php
/**
 * Google AI Provider Class
 * 
 * Integration with Google AI (Gemini) models for content generation and improvement.
 * This implements the AI provider interface for Google AI API.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Google AI Provider class
 */
class WP_Content_Flow_Google_AI_Provider {
    
    /**
     * Google AI API base URL
     */
    const API_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/';
    
    /**
     * Provider name
     *
     * @var string
     */
    public $name = 'Google AI';
    
    /**
     * Provider slug
     *
     * @var string
     */
    public $slug = 'google-ai';
    
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
    public $default_model = 'gemini-1.5-pro';
    
    /**
     * Available models
     *
     * @var array
     */
    public $available_models = [
        'gemini-1.5-pro' => [
            'name' => 'Gemini 1.5 Pro',
            'description' => 'Most capable model with advanced reasoning',
            'max_tokens' => 8192,
            'input_cost_per_1k' => 0.00125,
            'output_cost_per_1k' => 0.00375
        ],
        'gemini-1.5-flash' => [
            'name' => 'Gemini 1.5 Flash',
            'description' => 'Fast and efficient for most tasks',
            'max_tokens' => 8192,
            'input_cost_per_1k' => 0.000075,
            'output_cost_per_1k' => 0.0003
        ],
        'gemini-pro' => [
            'name' => 'Gemini Pro',
            'description' => 'Best model for text tasks',
            'max_tokens' => 4096,
            'input_cost_per_1k' => 0.0005,
            'output_cost_per_1k' => 0.0015
        ],
        'gemini-pro-vision' => [
            'name' => 'Gemini Pro Vision',
            'description' => 'Multimodal model for text and images',
            'max_tokens' => 4096,
            'input_cost_per_1k' => 0.00025,
            'output_cost_per_1k' => 0.0005
        ]
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key = $this->get_api_key();
        
        // Register provider hooks
        add_filter( 'wp_content_flow_ai_providers', array( $this, 'register_provider' ) );
        add_action( 'wp_content_flow_validate_api_keys', array( $this, 'validate_api_key' ) );
    }
    
    /**
     * Register this provider with the AI core
     *
     * @param array $providers Existing providers
     * @return array Updated providers
     */
    public function register_provider( $providers ) {
        $providers[ $this->slug ] = $this;
        return $providers;
    }
    
    /**
     * Get API key from options
     *
     * @return string API key
     */
    private function get_api_key() {
        // Try to get decrypted API key from settings page class for consistency
        if ( class_exists( 'WP_Content_Flow_Settings_Page' ) ) {
            $settings_page = new WP_Content_Flow_Settings_Page();
            $api_key = $settings_page->get_decrypted_api_key( 'google_ai' );
            if ( !empty( $api_key ) ) {
                return $api_key;
            }
        }
        
        // Fallback to direct settings access
        $settings = get_option( 'wp_content_flow_settings', array() );
        
        // Try encrypted storage first
        if ( isset( $settings['google_ai_api_key_encrypted'] ) ) {
            return $this->decrypt_api_key( $settings['google_ai_api_key_encrypted'] );
        }
        
        // Fallback to plain storage (legacy)
        if ( isset( $settings['google_ai_api_key'] ) ) {
            return $settings['google_ai_api_key'];
        }
        
        // Check environment variable
        if ( defined( 'GOOGLE_AI_API_KEY' ) ) {
            return GOOGLE_AI_API_KEY;
        }
        
        return '';
    }
    
    /**
     * Generate content using Google AI
     *
     * @param string $prompt The text prompt
     * @param array $options Generation options
     * @return array Response array with content, usage stats, etc.
     */
    public function generate_content( $prompt, $options = array() ) {
        if ( empty( $this->api_key ) ) {
            return array(
                'success' => false,
                'error' => 'Google AI API key not configured'
            );
        }
        
        // Parse options
        $defaults = array(
            'model' => $this->default_model,
            'max_tokens' => 1024,
            'temperature' => 0.7,
            'top_p' => 0.9,
            'top_k' => 40,
            'safety_settings' => array()
        );
        
        $options = wp_parse_args( $options, $defaults );
        
        // Prepare request data
        $request_data = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array( 'text' => $prompt )
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => floatval( $options['temperature'] ),
                'topP' => floatval( $options['top_p'] ),
                'topK' => intval( $options['top_k'] ),
                'maxOutputTokens' => intval( $options['max_tokens'] )
            )
        );
        
        // Add safety settings if provided
        if ( ! empty( $options['safety_settings'] ) ) {
            $request_data['safetySettings'] = $options['safety_settings'];
        } else {
            // Default safety settings
            $request_data['safetySettings'] = array(
                array(
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ),
                array(
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ),
                array(
                    'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ),
                array(
                    'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                )
            );
        }
        
        $start_time = microtime( true );
        
        // Make API request
        $response = $this->make_api_request( 
            'models/' . $options['model'] . ':generateContent',
            'POST',
            $request_data
        );
        
        $execution_time = microtime( true ) - $start_time;
        
        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'error' => $response->get_error_message(),
                'execution_time' => $execution_time
            );
        }
        
        // Parse response
        if ( isset( $response['candidates'] ) && ! empty( $response['candidates'] ) ) {
            $candidate = $response['candidates'][0];
            
            // Check for safety filters
            if ( isset( $candidate['finishReason'] ) && $candidate['finishReason'] === 'SAFETY' ) {
                return array(
                    'success' => false,
                    'error' => 'Content filtered by safety settings',
                    'execution_time' => $execution_time
                );
            }
            
            $content = '';
            if ( isset( $candidate['content']['parts'] ) ) {
                foreach ( $candidate['content']['parts'] as $part ) {
                    if ( isset( $part['text'] ) ) {
                        $content .= $part['text'];
                    }
                }
            }
            
            // Calculate token usage (approximate)
            $prompt_tokens = $this->count_tokens( $prompt );
            $completion_tokens = $this->count_tokens( $content );
            $total_tokens = $prompt_tokens + $completion_tokens;
            
            // Calculate cost
            $model_info = $this->available_models[ $options['model'] ] ?? array();
            $input_cost = ( $prompt_tokens / 1000 ) * ( $model_info['input_cost_per_1k'] ?? 0 );
            $output_cost = ( $completion_tokens / 1000 ) * ( $model_info['output_cost_per_1k'] ?? 0 );
            $total_cost = $input_cost + $output_cost;
            
            return array(
                'success' => true,
                'content' => $content,
                'model' => $options['model'],
                'usage' => array(
                    'prompt_tokens' => $prompt_tokens,
                    'completion_tokens' => $completion_tokens,
                    'total_tokens' => $total_tokens
                ),
                'cost_estimate' => $total_cost,
                'execution_time' => $execution_time,
                'provider' => $this->slug,
                'finish_reason' => $candidate['finishReason'] ?? 'stop'
            );
        }
        
        return array(
            'success' => false,
            'error' => 'No content generated',
            'execution_time' => $execution_time
        );
    }
    
    /**
     * Improve existing content
     *
     * @param string $content Content to improve
     * @param array $options Improvement options
     * @return array Response array
     */
    public function improve_content( $content, $options = array() ) {
        $defaults = array(
            'improvement_type' => 'general',
            'model' => $this->default_model,
            'temperature' => 0.5
        );
        
        $options = wp_parse_args( $options, $defaults );
        
        // Create improvement prompt based on type
        $improvement_prompts = array(
            'grammar' => 'Please fix any grammar, spelling, and punctuation errors in the following text while keeping the original meaning and tone:',
            'clarity' => 'Please rewrite the following text to make it clearer, more concise, and easier to understand:',
            'seo' => 'Please optimize the following content for SEO while maintaining readability and natural flow:',
            'tone' => 'Please adjust the tone of the following text to be more professional and engaging:',
            'general' => 'Please improve the following text by fixing any errors, improving clarity, and enhancing overall quality:'
        );
        
        $improvement_type = $options['improvement_type'];
        $base_prompt = $improvement_prompts[ $improvement_type ] ?? $improvement_prompts['general'];
        
        $full_prompt = $base_prompt . "\n\n" . $content . "\n\nImproved version:";
        
        return $this->generate_content( $full_prompt, $options );
    }
    
    /**
     * Validate API key
     *
     * @return array Validation result
     */
    public function validate_api_key() {
        if ( empty( $this->api_key ) ) {
            return array(
                'valid' => false,
                'message' => 'API key not provided'
            );
        }
        
        // Test with a simple request
        $response = $this->make_api_request(
            'models/gemini-pro:generateContent',
            'POST',
            array(
                'contents' => array(
                    array(
                        'parts' => array(
                            array( 'text' => 'Hello, world!' )
                        )
                    )
                )
            )
        );
        
        if ( is_wp_error( $response ) ) {
            return array(
                'valid' => false,
                'message' => $response->get_error_message()
            );
        }
        
        return array(
            'valid' => true,
            'message' => 'API key is valid'
        );
    }
    
    /**
     * Get available models
     *
     * @return array Available models
     */
    public function get_available_models() {
        return $this->available_models;
    }
    
    /**
     * Make API request to Google AI
     *
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param array $data Request data
     * @return array|WP_Error Response or error
     */
    private function make_api_request( $endpoint, $method = 'POST', $data = array() ) {
        $url = self::API_BASE_URL . $endpoint . '?key=' . $this->api_key;
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'WP-Content-Flow/' . WP_CONTENT_FLOW_VERSION
            ),
            'timeout' => 60,
            'body' => wp_json_encode( $data )
        );
        
        $response = wp_remote_request( $url, $args );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        
        if ( $response_code !== 200 ) {
            $error_data = json_decode( $response_body, true );
            $error_message = isset( $error_data['error']['message'] ) 
                ? $error_data['error']['message'] 
                : 'HTTP ' . $response_code . ' error';
                
            return new WP_Error( 'google_ai_api_error', $error_message );
        }
        
        $data = json_decode( $response_body, true );
        
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'json_decode_error', 'Invalid JSON response from Google AI API' );
        }
        
        return $data;
    }
    
    /**
     * Count tokens in text (approximate)
     *
     * @param string $text Text to count tokens for
     * @return int Approximate token count
     */
    private function count_tokens( $text ) {
        // Rough approximation: 1 token ≈ 0.75 words for English text
        $word_count = str_word_count( $text );
        return intval( $word_count / 0.75 );
    }
    
    /**
     * Encrypt API key for secure storage
     *
     * @param string $api_key Plain text API key
     * @return string Encrypted API key
     */
    private function encrypt_api_key( $api_key ) {
        // Use WordPress salts for encryption
        $key = wp_salt( 'auth' );
        $iv = substr( wp_salt( 'secure_auth' ), 0, 16 );
        
        return base64_encode( openssl_encrypt( $api_key, 'aes-256-cbc', $key, 0, $iv ) );
    }
    
    /**
     * Decrypt API key from storage
     *
     * @param string $encrypted_key Encrypted API key
     * @return string Plain text API key
     */
    private function decrypt_api_key( $encrypted_key ) {
        // Use WordPress salts for decryption
        $key = wp_salt( 'auth' );
        $iv = substr( wp_salt( 'secure_auth' ), 0, 16 );
        
        return openssl_decrypt( base64_decode( $encrypted_key ), 'aes-256-cbc', $key, 0, $iv );
    }
    
    /**
     * Get provider status for admin display
     *
     * @return array Provider status
     */
    public function get_status() {
        $status = array(
            'name' => $this->name,
            'slug' => $this->slug,
            'configured' => ! empty( $this->api_key ),
            'models' => count( $this->available_models ),
            'default_model' => $this->default_model
        );
        
        if ( $status['configured'] ) {
            $validation = $this->validate_api_key();
            $status['valid'] = $validation['valid'];
            $status['message'] = $validation['message'];
        } else {
            $status['valid'] = false;
            $status['message'] = 'API key not configured';
        }
        
        return $status;
    }
}