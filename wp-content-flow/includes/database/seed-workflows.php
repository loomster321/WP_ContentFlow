<?php
/**
 * Seed default workflows for the plugin
 * 
 * @package WP_Content_Flow
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Seed default workflows into the database
 * 
 * @return bool True if all workflows seeded successfully
 */
function wp_content_flow_seed_default_workflows() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'ai_workflows';
    
    // Check if workflows already exist
    $existing_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
    
    if ( $existing_count > 0 ) {
        // Workflows already exist, don't override
        return true;
    }
    
    // Default workflows to seed
    $default_workflows = [
        [
            'name' => 'Blog Post Workflow',
            'description' => 'Generate comprehensive blog posts with introduction, body, and conclusion sections. Ideal for content marketing and SEO-optimized articles.',
            'ai_provider' => 'openai',
            'settings' => wp_json_encode([
                'model' => 'gpt-4',
                'max_tokens' => 2000,
                'temperature' => 0.7,
                'prompt_template' => 'Write a detailed blog post about {topic}. Include an engaging introduction, informative body with subheadings, and a compelling conclusion.',
                'tone' => 'professional',
                'style' => 'informative',
                'seo_optimized' => true,
                'include_meta' => true,
                'word_count' => '800-1200'
            ]),
            'status' => 'active',
            'user_id' => 1 // Admin user
        ],
        [
            'name' => 'Product Description',
            'description' => 'Create compelling product descriptions for e-commerce. Focuses on features, benefits, and persuasive copy to drive conversions.',
            'ai_provider' => 'openai',
            'settings' => wp_json_encode([
                'model' => 'gpt-3.5-turbo',
                'max_tokens' => 500,
                'temperature' => 0.8,
                'prompt_template' => 'Write a compelling product description for {product}. Highlight key features, benefits, and unique selling points.',
                'tone' => 'persuasive',
                'style' => 'sales-oriented',
                'include_features' => true,
                'include_benefits' => true,
                'word_count' => '150-300'
            ]),
            'status' => 'active',
            'user_id' => 1
        ],
        [
            'name' => 'Social Media Content',
            'description' => 'Generate engaging social media posts optimized for various platforms including Twitter, Facebook, LinkedIn, and Instagram.',
            'ai_provider' => 'openai',
            'settings' => wp_json_encode([
                'model' => 'gpt-3.5-turbo',
                'max_tokens' => 280,
                'temperature' => 0.9,
                'prompt_template' => 'Create an engaging social media post about {topic}. Make it catchy, shareable, and include relevant hashtags.',
                'tone' => 'casual',
                'style' => 'engaging',
                'include_hashtags' => true,
                'include_emojis' => true,
                'platforms' => ['twitter', 'facebook', 'linkedin'],
                'character_limit' => 280
            ]),
            'status' => 'active',
            'user_id' => 1
        ],
        [
            'name' => 'Email Newsletter',
            'description' => 'Create professional email newsletters with compelling subject lines, body content, and calls-to-action.',
            'ai_provider' => 'anthropic',
            'settings' => wp_json_encode([
                'model' => 'claude-3-sonnet',
                'max_tokens' => 1500,
                'temperature' => 0.6,
                'prompt_template' => 'Write an email newsletter about {topic}. Include a catchy subject line, engaging body content, and clear call-to-action.',
                'tone' => 'professional',
                'style' => 'conversational',
                'include_subject' => true,
                'include_cta' => true,
                'personalization' => true
            ]),
            'status' => 'active',
            'user_id' => 1
        ],
        [
            'name' => 'FAQ Generator',
            'description' => 'Generate frequently asked questions and comprehensive answers for products, services, or topics.',
            'ai_provider' => 'openai',
            'settings' => wp_json_encode([
                'model' => 'gpt-4',
                'max_tokens' => 1500,
                'temperature' => 0.5,
                'prompt_template' => 'Generate 5-10 frequently asked questions and detailed answers about {topic}.',
                'tone' => 'helpful',
                'style' => 'clear',
                'question_count' => '5-10',
                'answer_depth' => 'detailed'
            ]),
            'status' => 'active',
            'user_id' => 1
        ],
        [
            'name' => 'Press Release',
            'description' => 'Create professional press releases following journalism standards with headline, lead, body, and boilerplate.',
            'ai_provider' => 'anthropic',
            'settings' => wp_json_encode([
                'model' => 'claude-3-opus',
                'max_tokens' => 1000,
                'temperature' => 0.4,
                'prompt_template' => 'Write a professional press release about {announcement}. Follow AP style guidelines.',
                'tone' => 'formal',
                'style' => 'journalistic',
                'include_headline' => true,
                'include_dateline' => true,
                'include_boilerplate' => true,
                'follow_ap_style' => true
            ]),
            'status' => 'active',
            'user_id' => 1
        ],
        [
            'name' => 'Technical Documentation',
            'description' => 'Generate clear technical documentation, user guides, and API documentation with proper formatting.',
            'ai_provider' => 'openai',
            'settings' => wp_json_encode([
                'model' => 'gpt-4',
                'max_tokens' => 3000,
                'temperature' => 0.3,
                'prompt_template' => 'Write technical documentation for {feature}. Include clear explanations, code examples, and step-by-step instructions.',
                'tone' => 'technical',
                'style' => 'precise',
                'include_examples' => true,
                'include_code' => true,
                'formatting' => 'markdown'
            ]),
            'status' => 'active',
            'user_id' => 1
        ],
        [
            'name' => 'Creative Story',
            'description' => 'Generate creative fiction, short stories, or narrative content with character development and plot.',
            'ai_provider' => 'anthropic',
            'settings' => wp_json_encode([
                'model' => 'claude-3-opus',
                'max_tokens' => 4000,
                'temperature' => 1.0,
                'prompt_template' => 'Write a creative story about {premise}. Include character development, dialogue, and vivid descriptions.',
                'tone' => 'creative',
                'style' => 'narrative',
                'genre' => 'fiction',
                'include_dialogue' => true,
                'include_descriptions' => true
            ]),
            'status' => 'active',
            'user_id' => 1
        ]
    ];
    
    $success = true;
    $inserted_count = 0;
    
    // Insert each workflow
    foreach ( $default_workflows as $workflow ) {
        // Check if this workflow name already exists for this user
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE name = %s AND user_id = %d",
            $workflow['name'],
            $workflow['user_id']
        ) );
        
        if ( ! $exists ) {
            $result = $wpdb->insert(
                $table_name,
                $workflow,
                [
                    '%s', // name
                    '%s', // description
                    '%s', // ai_provider
                    '%s', // settings (JSON)
                    '%s', // status
                    '%d'  // user_id
                ]
            );
            
            if ( $result === false ) {
                error_log( 'WP Content Flow: Failed to seed workflow "' . $workflow['name'] . '" - ' . $wpdb->last_error );
                $success = false;
            } else {
                $inserted_count++;
            }
        }
    }
    
    if ( $inserted_count > 0 ) {
        error_log( 'WP Content Flow: Successfully seeded ' . $inserted_count . ' default workflows' );
    }
    
    // Fire action for extensibility
    do_action( 'wp_content_flow_workflows_seeded', $inserted_count );
    
    return $success;
}

/**
 * Remove all default workflows (for cleanup/reset)
 * 
 * @return bool True on success
 */
function wp_content_flow_remove_default_workflows() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'ai_workflows';
    
    // Get list of default workflow names
    $default_names = [
        'Blog Post Workflow',
        'Product Description',
        'Social Media Content',
        'Email Newsletter',
        'FAQ Generator',
        'Press Release',
        'Technical Documentation',
        'Creative Story'
    ];
    
    // Build placeholders for IN clause
    $placeholders = implode( ', ', array_fill( 0, count( $default_names ), '%s' ) );
    
    // Delete default workflows
    $query = $wpdb->prepare(
        "DELETE FROM {$table_name} WHERE name IN ($placeholders) AND user_id = 1",
        ...$default_names
    );
    
    $result = $wpdb->query( $query );
    
    if ( $result === false ) {
        error_log( 'WP Content Flow: Failed to remove default workflows - ' . $wpdb->last_error );
        return false;
    }
    
    error_log( 'WP Content Flow: Removed ' . $result . ' default workflows' );
    
    return true;
}

/**
 * Reset workflows to default state
 * 
 * @return bool True on success
 */
function wp_content_flow_reset_workflows() {
    // Remove existing default workflows
    wp_content_flow_remove_default_workflows();
    
    // Re-seed defaults
    return wp_content_flow_seed_default_workflows();
}