import { AIGenerationRequest, AIGenerationResponse, AIAgentConfig } from '@wp-content-flow/shared-types';
import { BaseAgent } from './base-agent';
import { OpenAIService } from '../services/openai-service';
import { AnthropicService } from '../services/anthropic-service';
import { aiLogger } from '../utils/logger';

/**
 * Content Agent - Specialized for copywriting and content generation
 * Handles blog posts, articles, marketing copy, and general text content
 */
export class ContentAgent extends BaseAgent {
  private openaiService: OpenAIService;
  private anthropicService: AnthropicService;

  constructor(config: AIAgentConfig) {
    super(
      'content-agent',
      'Content Creation Agent',
      'content',
      [
        'blog-posts',
        'articles',
        'marketing-copy',
        'product-descriptions',
        'social-media',
        'email-content',
        'headlines',
        'meta-descriptions',
        'content-improvement',
        'grammar-correction',
        'tone-adjustment',
        'seo-optimization'
      ],
      config
    );

    this.openaiService = new OpenAIService();
    this.anthropicService = new AnthropicService();
  }

  /**
   * Check if this agent can handle the specific request
   */
  canHandle(request: AIGenerationRequest): boolean {
    const contentTypes = [
      'blog-post',
      'article',
      'marketing',
      'product-description',
      'social-media',
      'email',
      'headline',
      'meta-description',
      'content-improvement',
      'grammar',
      'seo'
    ];

    // Check if prompt contains content-related keywords
    const prompt = request.prompt.toLowerCase();
    const hasContentKeywords = contentTypes.some(type => 
      prompt.includes(type.replace('-', ' ')) || 
      prompt.includes(type)
    );

    // Check for content generation indicators
    const contentIndicators = [
      'write',
      'create',
      'generate',
      'improve',
      'edit',
      'rewrite',
      'optimize',
      'content',
      'copy',
      'text',
      'article',
      'blog',
      'post'
    ];

    const hasContentIndicators = contentIndicators.some(indicator =>
      prompt.includes(indicator)
    );

    return hasContentKeywords || hasContentIndicators;
  }

  /**
   * Generate content using the configured AI provider
   */
  async generateContent(request: AIGenerationRequest): Promise<AIGenerationResponse> {
    const { prompt, context, knowledgeBaseIds } = request;
    
    // Build context-aware system prompt
    let systemPrompt = this.config.systemPrompt;
    
    // Add context-specific instructions
    if (context?.selectedContent) {
      systemPrompt += `\n\nYou are improving existing content. Original content: "${context.selectedContent}"`;
    }

    // Add knowledge base context if available
    if (knowledgeBaseIds && knowledgeBaseIds.length > 0) {
      // TODO: Retrieve and inject knowledge base content
      systemPrompt += '\n\nUse the brand guidelines and writing style from the knowledge base.';
    }

    const messages = [
      { role: 'system', content: systemPrompt },
      { role: 'user', content: prompt }
    ];

    let response: AIGenerationResponse;

    try {
      switch (this.config.provider) {
        case 'openai':
          response = await this.openaiService.generateContent({
            model: this.config.model,
            messages,
            temperature: this.config.temperature,
            maxTokens: this.config.maxTokens
          });
          break;
          
        case 'anthropic':
          response = await this.anthropicService.generateContent({
            model: this.config.model,
            messages,
            temperature: this.config.temperature,
            maxTokens: this.config.maxTokens
          });
          break;
          
        default:
          throw new Error(`Unsupported AI provider: ${this.config.provider}`);
      }

      // Enhance response with content-specific metadata
      response.metadata = {
        ...response.metadata,
        contentType: this.detectContentType(prompt),
        wordCount: response.content.split(' ').length,
        estimatedReadingTime: Math.ceil(response.content.split(' ').length / 200), // words per minute
        seoScore: this.calculateSEOScore(response.content, prompt)
      };

      aiLogger.info('Content agent generated response', {
        contentType: response.metadata.contentType,
        wordCount: response.metadata.wordCount,
        confidenceScore: response.confidenceScore
      });

      return response;
      
    } catch (error) {
      aiLogger.error('Content agent generation failed', {
        provider: this.config.provider,
        model: this.config.model,
        error: error instanceof Error ? error.message : String(error)
      });
      
      throw error;
    }
  }

  /**
   * Detect the type of content being generated
   */
  private detectContentType(prompt: string): string {
    const prompt_lower = prompt.toLowerCase();
    
    const typeMap = [
      { keywords: ['blog', 'post', 'article'], type: 'blog-post' },
      { keywords: ['product', 'description', 'features'], type: 'product-description' },
      { keywords: ['marketing', 'advertisement', 'promo'], type: 'marketing-copy' },
      { keywords: ['social', 'twitter', 'facebook', 'instagram'], type: 'social-media' },
      { keywords: ['email', 'newsletter', 'subject line'], type: 'email-content' },
      { keywords: ['headline', 'title', 'heading'], type: 'headline' },
      { keywords: ['meta', 'description', 'seo'], type: 'meta-description' }
    ];

    for (const { keywords, type } of typeMap) {
      if (keywords.some(keyword => prompt_lower.includes(keyword))) {
        return type;
      }
    }

    return 'general-content';
  }

  /**
   * Calculate basic SEO score for generated content
   */
  private calculateSEOScore(content: string, prompt: string): number {
    let score = 0.5; // Base score

    // Check for keyword presence (extracted from prompt)
    const promptWords = prompt.toLowerCase().split(' ');
    const contentLower = content.toLowerCase();
    
    // Simple keyword density check
    const keywordMatches = promptWords.filter(word => 
      word.length > 3 && contentLower.includes(word)
    ).length;
    
    if (keywordMatches > 0) {
      score += 0.2;
    }

    // Check content length (ideal for SEO)
    const wordCount = content.split(' ').length;
    if (wordCount >= 300 && wordCount <= 1500) {
      score += 0.2;
    }

    // Check for headings (simple check for capitalized sentences)
    const sentences = content.split(/[.!?]+/);
    const hasStructure = sentences.some(sentence => 
      sentence.trim().length > 0 && sentence.trim().length < 50
    );
    
    if (hasStructure) {
      score += 0.1;
    }

    return Math.min(score, 1.0);
  }
}