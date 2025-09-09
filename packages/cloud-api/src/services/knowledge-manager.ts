import { KnowledgeBase, KnowledgeDocument } from '@wp-content-flow/shared-types';
import { RAGService } from './rag-service';
import { ragLogger } from '../utils/logger';

/**
 * Knowledge Manager - High-level interface for managing knowledge bases
 * Provides specialized methods for different types of knowledge (brand voice, guidelines, etc.)
 */
export class KnowledgeManager {
  private ragService: RAGService;

  constructor() {
    this.ragService = new RAGService();
  }

  /**
   * Initialize default knowledge bases for a new user/organization
   */
  async initializeDefaultKnowledgeBases(): Promise<{[key: string]: string}> {
    const knowledgeBaseIds: {[key: string]: string} = {};

    try {
      // Create Brand Voice knowledge base
      knowledgeBaseIds.brandVoice = await this.ragService.createKnowledgeBase({
        type: 'brand-voice',
        name: 'Brand Voice Guidelines',
        description: 'Guidelines for maintaining consistent brand voice and tone across all content',
        documents: []
      });

      // Create Writing Guidelines knowledge base
      knowledgeBaseIds.writingGuidelines = await this.ragService.createKnowledgeBase({
        type: 'writing-guidelines',
        name: 'Writing Style Guidelines',
        description: 'Editorial guidelines, style preferences, and content standards',
        documents: []
      });

      // Create Visual Guidelines knowledge base
      knowledgeBaseIds.visualGuidelines = await this.ragService.createKnowledgeBase({
        type: 'visual-guidelines',
        name: 'Visual Design Guidelines',
        description: 'Brand visual identity, color schemes, typography, and design principles',
        documents: []
      });

      // Create Stock Art Collection knowledge base
      knowledgeBaseIds.stockArt = await this.ragService.createKnowledgeBase({
        type: 'stock-art',
        name: 'Curated Stock Art Collection',
        description: 'Pre-approved stock images, photos, and visual assets for content use',
        documents: []
      });

      ragLogger.info('Default knowledge bases initialized', {
        knowledgeBaseIds: Object.keys(knowledgeBaseIds)
      });

      return knowledgeBaseIds;
    } catch (error) {
      ragLogger.error('Failed to initialize default knowledge bases', {
        error: error instanceof Error ? error.message : String(error)
      });
      throw error;
    }
  }

  /**
   * Add brand voice guidelines
   */
  async addBrandVoiceGuideline(knowledgeBaseId: string, title: string, content: string, category: string = 'general'): Promise<string> {
    return this.ragService.addDocument(knowledgeBaseId, {
      title,
      content,
      metadata: {
        source: 'brand-guidelines',
        category,
        tags: ['brand-voice', 'tone', 'style'],
        lastModified: new Date()
      }
    });
  }

  /**
   * Add writing style guidelines
   */
  async addWritingGuideline(knowledgeBaseId: string, title: string, content: string, category: string = 'style'): Promise<string> {
    return this.ragService.addDocument(knowledgeBaseId, {
      title,
      content,
      metadata: {
        source: 'writing-guidelines',
        category,
        tags: ['writing', 'style', 'editorial'],
        lastModified: new Date()
      }
    });
  }

  /**
   * Add visual design guidelines
   */
  async addVisualGuideline(knowledgeBaseId: string, title: string, content: string, category: string = 'design'): Promise<string> {
    return this.ragService.addDocument(knowledgeBaseId, {
      title,
      content,
      metadata: {
        source: 'visual-guidelines',
        category,
        tags: ['visual', 'design', 'branding'],
        lastModified: new Date()
      }
    });
  }

  /**
   * Add stock art asset information
   */
  async addStockArtAsset(knowledgeBaseId: string, title: string, description: string, metadata: {
    imageUrl?: string;
    source?: string;
    license?: string;
    tags?: string[];
    dimensions?: string;
    fileSize?: string;
  }): Promise<string> {
    return this.ragService.addDocument(knowledgeBaseId, {
      title,
      content: description,
      metadata: {
        source: metadata.source || 'stock-library',
        category: 'stock-asset',
        tags: ['stock-art', 'image', ...(metadata.tags || [])],
        lastModified: new Date(),
        imageUrl: metadata.imageUrl,
        license: metadata.license,
        dimensions: metadata.dimensions,
        fileSize: metadata.fileSize
      }
    });
  }

  /**
   * Get context for AI generation based on content type and query
   */
  async getContextForGeneration(
    knowledgeBaseIds: string[], 
    query: string, 
    contentType: 'blog-post' | 'marketing' | 'product-description' | 'social-media' | 'email' = 'blog-post'
  ): Promise<string> {
    try {
      let context = await this.ragService.generateContext(knowledgeBaseIds, query);

      // Add content type specific guidelines
      context += this.getContentTypeGuidelines(contentType);

      ragLogger.info('Context generated for AI generation', {
        knowledgeBaseCount: knowledgeBaseIds.length,
        contentType,
        contextLength: context.length
      });

      return context;
    } catch (error) {
      ragLogger.error('Failed to generate context for AI generation', {
        error: error instanceof Error ? error.message : String(error),
        contentType,
        query: query.substring(0, 100)
      });
      return '';
    }
  }

  /**
   * Bulk import documents from various sources
   */
  async bulkImportDocuments(knowledgeBaseId: string, documents: Array<{
    title: string;
    content: string;
    source: string;
    category?: string;
    tags?: string[];
  }>): Promise<string[]> {
    const importedIds: string[] = [];

    for (const doc of documents) {
      try {
        const docId = await this.ragService.addDocument(knowledgeBaseId, {
          title: doc.title,
          content: doc.content,
          metadata: {
            source: doc.source,
            category: doc.category || 'imported',
            tags: doc.tags || [],
            lastModified: new Date()
          }
        });
        importedIds.push(docId);
      } catch (error) {
        ragLogger.warn('Failed to import document', {
          title: doc.title,
          error: error instanceof Error ? error.message : String(error)
        });
      }
    }

    ragLogger.info('Bulk import completed', {
      knowledgeBaseId,
      attempted: documents.length,
      successful: importedIds.length
    });

    return importedIds;
  }

  /**
   * Search across multiple knowledge bases
   */
  async searchAcrossKnowledgeBases(knowledgeBaseIds: string[], query: string, limit: number = 10): Promise<{
    knowledgeBaseId: string;
    knowledgeBaseName: string;
    documents: KnowledgeDocument[];
  }[]> {
    const results = [];

    for (const kbId of knowledgeBaseIds) {
      try {
        const kb = await this.ragService.getKnowledgeBase(kbId);
        if (!kb) continue;

        const documents = await this.ragService.searchKnowledgeBase(kbId, query, Math.ceil(limit / knowledgeBaseIds.length));
        
        if (documents.length > 0) {
          results.push({
            knowledgeBaseId: kbId,
            knowledgeBaseName: kb.name,
            documents
          });
        }
      } catch (error) {
        ragLogger.warn('Failed to search knowledge base', {
          knowledgeBaseId: kbId,
          error: error instanceof Error ? error.message : String(error)
        });
      }
    }

    return results;
  }

  /**
   * Get content type specific guidelines
   */
  private getContentTypeGuidelines(contentType: string): string {
    const guidelines = {
      'blog-post': `
## Blog Post Guidelines:
- Use engaging headlines that grab attention
- Include an introduction that hooks the reader
- Structure content with clear headings and subheadings
- Write in a conversational, approachable tone
- Include actionable takeaways for readers
- Optimize for SEO with relevant keywords
- End with a clear call-to-action`,

      'marketing': `
## Marketing Content Guidelines:
- Focus on benefits rather than features
- Use compelling, action-oriented language
- Create urgency and scarcity when appropriate
- Include strong calls-to-action
- Address customer pain points directly
- Use social proof and testimonials
- Keep messaging clear and concise`,

      'product-description': `
## Product Description Guidelines:
- Highlight key features and benefits
- Use descriptive, sensory language
- Address common customer questions
- Include technical specifications when relevant
- Optimize for search with relevant keywords
- Use bullet points for easy scanning
- Include size, compatibility, or usage information`,

      'social-media': `
## Social Media Guidelines:
- Keep content concise and engaging
- Use platform-appropriate tone and style
- Include relevant hashtags and mentions
- Optimize for mobile viewing
- Encourage engagement with questions or calls-to-action
- Use emojis and visual elements when appropriate
- Time posts for optimal audience engagement`,

      'email': `
## Email Content Guidelines:
- Write compelling subject lines
- Personalize content when possible
- Keep paragraphs short and scannable
- Include clear calls-to-action
- Optimize for mobile reading
- Maintain consistent brand voice
- Test different versions for best performance`
    };

    return guidelines[contentType] || '';
  }

  /**
   * Get RAG service instance for advanced operations
   */
  getRagService(): RAGService {
    return this.ragService;
  }
}