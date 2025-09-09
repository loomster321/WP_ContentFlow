/**
 * WordPress AI Content Flow - Block Editor API Contracts
 * 
 * This file defines the JavaScript API contracts for extending the Gutenberg
 * block editor with AI capabilities.
 */

// =============================================================================
// BLOCK REGISTRATION CONTRACTS
// =============================================================================

/**
 * AI Text Generator Block
 * Provides inline AI content generation within the block editor
 */
const AI_TEXT_BLOCK_CONTRACT = {
    name: 'wp-content-flow/ai-text',
    title: 'AI Text Generator',
    category: 'text',
    icon: 'admin-site-alt3',
    description: 'Generate AI-powered content directly in the editor',
    
    // Block attributes schema
    attributes: {
        content: {
            type: 'string',
            source: 'html',
            selector: '.ai-generated-content',
            default: ''
        },
        prompt: {
            type: 'string',
            default: ''
        },
        workflowId: {
            type: 'number',
            default: null
        },
        aiProvider: {
            type: 'string',
            default: 'openai'
        },
        generationStatus: {
            type: 'string',
            enum: ['idle', 'generating', 'completed', 'error'],
            default: 'idle'
        },
        confidenceScore: {
            type: 'number',
            minimum: 0,
            maximum: 1,
            default: null
        }
    },
    
    // Required functions
    edit: 'EditComponent', // React component
    save: 'SaveComponent'  // React component
};

/**
 * AI Content Enhancer Block
 * Provides AI-powered content improvement suggestions
 */
const AI_ENHANCER_BLOCK_CONTRACT = {
    name: 'wp-content-flow/ai-enhancer',
    title: 'AI Content Enhancer',
    category: 'text',
    
    attributes: {
        originalContent: {
            type: 'string',
            default: ''
        },
        enhancedContent: {
            type: 'string',
            default: ''
        },
        enhancementType: {
            type: 'string',
            enum: ['grammar', 'style', 'clarity', 'engagement', 'seo'],
            default: 'style'
        },
        suggestions: {
            type: 'array',
            items: {
                type: 'object',
                properties: {
                    id: { type: 'number' },
                    text: { type: 'string' },
                    confidence: { type: 'number' },
                    applied: { type: 'boolean' }
                }
            },
            default: []
        }
    }
};

module.exports = {
    AI_TEXT_BLOCK_CONTRACT,
    AI_ENHANCER_BLOCK_CONTRACT
};