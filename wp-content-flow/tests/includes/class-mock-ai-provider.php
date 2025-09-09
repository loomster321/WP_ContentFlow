<?php
/**
 * Mock AI Provider for testing
 * 
 * This provides predictable AI responses for contract and integration tests
 * without making real API calls to external services.
 */

class WP_Content_Flow_Mock_AI_Provider {
    
    /**
     * Mock responses for different scenarios
     *
     * @var array
     */
    private static $mock_responses = array();
    
    /**
     * Request history for testing
     *
     * @var array
     */
    private static $request_history = array();
    
    /**
     * Set mock response for specific provider
     *
     * @param string $provider Provider name (openai, anthropic, etc.)
     * @param array $response Mock response data
     */
    public static function set_mock_response( $provider, $response ) {
        self::$mock_responses[ $provider ] = $response;
    }
    
    /**
     * Generate mock AI content based on prompt
     *
     * @param string $prompt Input prompt
     * @param array $parameters AI parameters
     * @param string $provider AI provider name
     * @return array Mock AI response
     */
    public static function generate_content( $prompt, $parameters = array(), $provider = 'openai' ) {
        // Record request for testing verification
        self::$request_history[] = array(
            'type' => 'generate',
            'prompt' => $prompt,
            'parameters' => $parameters,
            'provider' => $provider,
            'timestamp' => current_time( 'mysql' )
        );
        
        // Return mock response if set
        if ( isset( self::$mock_responses[ $provider ] ) ) {
            $mock = self::$mock_responses[ $provider ];
            
            // Handle error scenarios
            if ( isset( $mock['error'] ) ) {
                return array(
                    'error' => $mock['error'],
                    'code' => $mock['code'] ?? 'ai_provider_error'
                );
            }
            
            return $mock;
        }
        
        // Generate realistic mock content based on prompt
        $generated_content = self::generate_mock_content_from_prompt( $prompt );
        
        return array(
            'id' => wp_rand( 1000, 9999 ),
            'suggested_content' => $generated_content,
            'confidence_score' => self::calculate_mock_confidence( $prompt, $parameters ),
            'suggestion_type' => 'generation',
            'status' => 'pending',
            'metadata' => array(
                'provider' => $provider,
                'model' => $parameters['model'] ?? 'mock-model',
                'tokens_used' => strlen( $generated_content ) / 4, // Rough estimate
                'processing_time_ms' => wp_rand( 500, 2000 )
            ),
            'created_at' => current_time( 'mysql', true )
        );
    }
    
    /**
     * Improve existing content with mock AI
     *
     * @param string $content Content to improve
     * @param string $improvement_type Type of improvement
     * @param array $parameters AI parameters
     * @param string $provider AI provider name
     * @return array Mock improvement suggestions
     */
    public static function improve_content( $content, $improvement_type, $parameters = array(), $provider = 'openai' ) {
        // Record request
        self::$request_history[] = array(
            'type' => 'improve',
            'content' => $content,
            'improvement_type' => $improvement_type,
            'parameters' => $parameters,
            'provider' => $provider,
            'timestamp' => current_time( 'mysql' )
        );
        
        // Return mock response if set
        if ( isset( self::$mock_responses[ $provider ] ) ) {
            $mock = self::$mock_responses[ $provider ];
            if ( isset( $mock['error'] ) ) {
                return array( 'error' => $mock['error'] );
            }
            return array( $mock ); // Wrap in array for multiple suggestions
        }
        
        // Generate mock improvements based on type
        $improvements = self::generate_mock_improvements( $content, $improvement_type );
        
        return $improvements;
    }
    
    /**
     * Get request history for test verification
     *
     * @param string $type Optional filter by request type
     * @return array Request history
     */
    public static function get_request_history( $type = null ) {
        if ( $type ) {
            return array_filter( self::$request_history, function( $request ) use ( $type ) {
                return $request['type'] === $type;
            } );
        }
        
        return self::$request_history;
    }
    
    /**
     * Clear request history and mock responses
     */
    public static function reset() {
        self::$request_history = array();
        self::$mock_responses = array();
    }
    
    /**
     * Generate mock content based on prompt keywords
     *
     * @param string $prompt Input prompt
     * @return string Generated mock content
     */
    private static function generate_mock_content_from_prompt( $prompt ) {
        $prompt_lower = strtolower( $prompt );
        
        // Content templates based on common keywords
        if ( strpos( $prompt_lower, 'sustainable gardening' ) !== false ) {
            return 'Sustainable gardening practices help create environmentally friendly outdoor spaces while reducing resource consumption. By implementing organic methods, composting systems, and native plant selection, gardeners can create thriving ecosystems that support local wildlife and minimize environmental impact.';
        }
        
        if ( strpos( $prompt_lower, 'climate change' ) !== false ) {
            return 'Climate change represents one of the most significant challenges facing our planet today. Rising global temperatures, shifting weather patterns, and increasing extreme weather events are already impacting communities worldwide, requiring urgent action from individuals, organizations, and governments.';
        }
        
        if ( strpos( $prompt_lower, 'product description' ) !== false ) {
            return 'This innovative product combines cutting-edge technology with user-friendly design to deliver exceptional performance. Crafted with premium materials and rigorous quality standards, it offers reliable functionality that meets the demanding needs of modern users.';
        }
        
        if ( strpos( $prompt_lower, 'blog post' ) !== false || strpos( $prompt_lower, 'introduction' ) !== false ) {
            return 'In today\'s rapidly evolving digital landscape, understanding key concepts and best practices has become essential for success. This comprehensive guide explores important strategies and actionable insights that can help you navigate challenges and achieve your goals.';
        }
        
        // Generic content for other prompts
        return 'This is AI-generated content based on your prompt. The mock AI provider has analyzed your request and created relevant, engaging text that addresses the key themes and requirements specified in your prompt.';
    }
    
    /**
     * Calculate mock confidence score based on prompt quality
     *
     * @param string $prompt Input prompt
     * @param array $parameters AI parameters
     * @return float Confidence score (0.0 to 1.0)
     */
    private static function calculate_mock_confidence( $prompt, $parameters ) {
        $base_confidence = 0.7;
        
        // Longer prompts generally yield higher confidence
        if ( strlen( $prompt ) > 50 ) {
            $base_confidence += 0.1;
        }
        
        // Specific keywords increase confidence
        $high_confidence_keywords = array( 'write', 'create', 'generate', 'explain', 'describe' );
        foreach ( $high_confidence_keywords as $keyword ) {
            if ( strpos( strtolower( $prompt ), $keyword ) !== false ) {
                $base_confidence += 0.05;
                break;
            }
        }
        
        // Temperature affects confidence (lower temp = higher confidence)
        if ( isset( $parameters['temperature'] ) && $parameters['temperature'] < 0.5 ) {
            $base_confidence += 0.1;
        }
        
        // Cap at 0.95 for realistic simulation
        return min( $base_confidence, 0.95 );
    }
    
    /**
     * Generate mock content improvements
     *
     * @param string $content Original content
     * @param string $improvement_type Type of improvement
     * @return array Array of improvement suggestions
     */
    private static function generate_mock_improvements( $content, $improvement_type ) {
        $improvements = array();
        
        switch ( $improvement_type ) {
            case 'grammar':
                $improvements[] = array(
                    'id' => wp_rand( 1000, 9999 ),
                    'original_content' => $content,
                    'suggested_content' => self::fix_mock_grammar_errors( $content ),
                    'suggestion_type' => 'improvement',
                    'status' => 'pending',
                    'confidence_score' => 0.92,
                    'metadata' => array(
                        'improvement_type' => 'grammar',
                        'changes_made' => array( 'spelling_corrections', 'grammar_fixes' )
                    )
                );
                break;
                
            case 'style':
                $improvements[] = array(
                    'id' => wp_rand( 1000, 9999 ),
                    'original_content' => $content,
                    'suggested_content' => self::improve_mock_style( $content ),
                    'suggestion_type' => 'improvement', 
                    'status' => 'pending',
                    'confidence_score' => 0.85,
                    'metadata' => array(
                        'improvement_type' => 'style',
                        'changes_made' => array( 'tone_adjustment', 'clarity_improvement' )
                    )
                );
                break;
                
            case 'engagement':
                $improvements[] = array(
                    'id' => wp_rand( 1000, 9999 ),
                    'original_content' => $content,
                    'suggested_content' => self::improve_mock_engagement( $content ),
                    'suggestion_type' => 'improvement',
                    'status' => 'pending', 
                    'confidence_score' => 0.88,
                    'metadata' => array(
                        'improvement_type' => 'engagement',
                        'changes_made' => array( 'added_questions', 'stronger_opening' )
                    )
                );
                break;
                
            default:
                $improvements[] = array(
                    'id' => wp_rand( 1000, 9999 ),
                    'original_content' => $content,
                    'suggested_content' => $content . ' This content has been improved by the mock AI provider.',
                    'suggestion_type' => 'improvement',
                    'status' => 'pending',
                    'confidence_score' => 0.75
                );
        }
        
        return $improvements;
    }
    
    /**
     * Fix mock grammar errors in content
     *
     * @param string $content Original content
     * @return string Content with grammar fixes
     */
    private static function fix_mock_grammar_errors( $content ) {
        // Common error corrections for testing
        $corrections = array(
            'grammer' => 'grammar',
            'readibility' => 'readability', 
            'severl' => 'several',
            'speling' => 'spelling',
            'erors' => 'errors',
            'automaticaly' => 'automatically',
            'occuring' => 'occurring',
            'recieve' => 'receive'
        );
        
        $improved_content = $content;
        foreach ( $corrections as $error => $correction ) {
            $improved_content = str_ireplace( $error, $correction, $improved_content );
        }
        
        return $improved_content;
    }
    
    /**
     * Improve mock content style
     *
     * @param string $content Original content
     * @return string Content with style improvements
     */
    private static function improve_mock_style( $content ) {
        // Add more engaging language
        $improved = $content;
        
        // Replace weak words with stronger alternatives
        $style_improvements = array(
            'very good' => 'excellent',
            'really nice' => 'outstanding',
            'pretty cool' => 'impressive',
            'kind of' => 'somewhat',
            'a lot of' => 'numerous'
        );
        
        foreach ( $style_improvements as $weak => $strong ) {
            $improved = str_ireplace( $weak, $strong, $improved );
        }
        
        return $improved;
    }
    
    /**
     * Improve mock content engagement
     *
     * @param string $content Original content
     * @return string Content with engagement improvements
     */
    private static function improve_mock_engagement( $content ) {
        // Add engaging elements
        if ( ! empty( $content ) ) {
            return "Have you ever wondered about this topic? {$content} What are your thoughts on this approach?";
        }
        
        return $content;
    }
}