import { AIAgent, AIAgentConfig, AIGenerationRequest, AIGenerationResponse } from '@wp-content-flow/shared-types';
import { aiLogger } from '../utils/logger';

export abstract class BaseAgent implements AIAgent {
  public id: string;
  public name: string;
  public type: 'content' | 'layout' | 'stock-art' | 'ai-art';
  public status: 'idle' | 'processing' | 'error' = 'idle';
  public capabilities: string[];
  public config: AIAgentConfig;

  constructor(id: string, name: string, type: 'content' | 'layout' | 'stock-art' | 'ai-art', capabilities: string[], config: AIAgentConfig) {
    this.id = id;
    this.name = name;
    this.type = type;
    this.capabilities = capabilities;
    this.config = config;
  }

  /**
   * Abstract method that each agent must implement for content generation
   */
  abstract generateContent(request: AIGenerationRequest): Promise<AIGenerationResponse>;

  /**
   * Validate if this agent can handle the given request
   */
  canHandle(request: AIGenerationRequest): boolean {
    // Default implementation - can be overridden by specific agents
    return true;
  }

  /**
   * Process a request with error handling and status management
   */
  async processRequest(request: AIGenerationRequest): Promise<AIGenerationResponse> {
    if (!this.canHandle(request)) {
      throw new Error(`Agent ${this.name} cannot handle this type of request`);
    }

    this.status = 'processing';
    aiLogger.info(`Agent ${this.name} processing request`, {
      agentId: this.id,
      requestId: request.context?.postId,
      prompt: request.prompt.substring(0, 100) + '...'
    });

    const startTime = Date.now();

    try {
      const response = await this.generateContent(request);
      
      const processingTime = (Date.now() - startTime) / 1000;
      
      this.status = 'idle';
      
      aiLogger.info(`Agent ${this.name} completed request`, {
        agentId: this.id,
        processingTime,
        confidenceScore: response.confidenceScore,
        tokenUsage: response.tokenUsage
      });

      return {
        ...response,
        processingTime,
        agentUsed: this.id
      };

    } catch (error) {
      this.status = 'error';
      
      aiLogger.error(`Agent ${this.name} failed to process request`, {
        agentId: this.id,
        error: error instanceof Error ? error.message : String(error),
        stack: error instanceof Error ? error.stack : undefined
      });

      throw error;
    }
  }

  /**
   * Get agent status and health information
   */
  getStatus() {
    return {
      id: this.id,
      name: this.name,
      type: this.type,
      status: this.status,
      capabilities: this.capabilities,
      config: {
        provider: this.config.provider,
        model: this.config.model
        // Don't expose sensitive config like API keys
      }
    };
  }

  /**
   * Update agent configuration
   */
  updateConfig(newConfig: Partial<AIAgentConfig>): void {
    this.config = {
      ...this.config,
      ...newConfig
    };
    
    aiLogger.info(`Agent ${this.name} configuration updated`, {
      agentId: this.id,
      updatedFields: Object.keys(newConfig)
    });
  }

  /**
   * Validate agent configuration
   */
  validateConfig(): boolean {
    const requiredFields = ['provider', 'model', 'systemPrompt'];
    
    for (const field of requiredFields) {
      if (!this.config[field as keyof AIAgentConfig]) {
        aiLogger.error(`Agent ${this.name} missing required config field: ${field}`);
        return false;
      }
    }

    return true;
  }
}