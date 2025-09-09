import { AIGenerationRequest, AIGenerationResponse, QueueJob } from '@wp-content-flow/shared-types';
import { BaseAgent } from './base-agent';
import { ContentAgent } from './content-agent';
import { LayoutAgent } from './layout-agent';
import { StockArtAgent } from './stock-art-agent';
import { AIArtAgent } from './ai-art-agent';
import { QueueService } from '../services/queue-service';
import { aiLogger } from '../utils/logger';

/**
 * Agent Orchestrator - Manages multiple AI agents and routes requests
 * Handles agent selection, coordination, and multi-agent workflows
 */
export class AgentOrchestrator {
  private agents: Map<string, BaseAgent> = new Map();
  private queueService: QueueService;

  constructor(queueService: QueueService) {
    this.queueService = queueService;
    this.initializeAgents();
  }

  /**
   * Initialize all AI agents with default configurations
   */
  private initializeAgents(): void {
    const defaultConfig = {
      provider: 'openai' as const,
      model: 'gpt-4',
      temperature: 0.7,
      maxTokens: 1500,
      systemPrompt: 'You are a helpful AI assistant.'
    };

    // Initialize Content Agent
    const contentAgent = new ContentAgent({
      ...defaultConfig,
      systemPrompt: 'You are a professional content writer and copywriter. Create engaging, well-structured content that matches the user\'s requirements and brand voice.'
    });
    this.agents.set(contentAgent.id, contentAgent);

    // Initialize Layout Agent
    const layoutAgent = new LayoutAgent({
      ...defaultConfig,
      systemPrompt: 'You are a UX/UI design expert. Provide layout suggestions, design recommendations, and user experience improvements for content presentation.'
    });
    this.agents.set(layoutAgent.id, layoutAgent);

    // Initialize Stock Art Agent
    const stockArtAgent = new StockArtAgent({
      ...defaultConfig,
      systemPrompt: 'You are an image curation specialist. Help find and recommend relevant stock images, photos, and visual assets for content.'
    });
    this.agents.set(stockArtAgent.id, stockArtAgent);

    // Initialize AI Art Generation Agent
    const aiArtAgent = new AIArtAgent({
      ...defaultConfig,
      systemPrompt: 'You are an AI art generation specialist. Create detailed prompts for AI image generation and provide artistic guidance.'
    });
    this.agents.set(aiArtAgent.id, aiArtAgent);

    aiLogger.info('Agent orchestrator initialized', {
      agentCount: this.agents.size,
      agents: Array.from(this.agents.keys())
    });
  }

  /**
   * Process a request by selecting the most appropriate agent
   */
  async processRequest(request: AIGenerationRequest): Promise<AIGenerationResponse> {
    const selectedAgent = this.selectBestAgent(request);
    
    if (!selectedAgent) {
      throw new Error('No suitable agent found for this request');
    }

    aiLogger.info('Request routed to agent', {
      selectedAgent: selectedAgent.id,
      requestId: request.context?.postId,
      prompt: request.prompt.substring(0, 100) + '...'
    });

    return await selectedAgent.processRequest(request);
  }

  /**
   * Process a request asynchronously using the job queue
   */
  async processRequestAsync(request: AIGenerationRequest): Promise<string> {
    const job: QueueJob = {
      id: `ai-gen-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
      type: 'ai-generation',
      payload: request,
      priority: 1,
      status: 'pending',
      attempts: 0,
      maxAttempts: 3,
      createdAt: new Date()
    };

    await this.queueService.addJob(job);
    
    aiLogger.info('Request queued for async processing', {
      jobId: job.id,
      requestId: request.context?.postId
    });

    return job.id;
  }

  /**
   * Select the best agent for a given request
   */
  private selectBestAgent(request: AIGenerationRequest): BaseAgent | null {
    const availableAgents = Array.from(this.agents.values()).filter(
      agent => agent.status !== 'error' && agent.canHandle(request)
    );

    if (availableAgents.length === 0) {
      return null;
    }

    // If multiple agents can handle the request, prioritize by capability match
    if (availableAgents.length === 1) {
      return availableAgents[0];
    }

    // Score agents based on how well they match the request
    const scoredAgents = availableAgents.map(agent => ({
      agent,
      score: this.calculateAgentScore(agent, request)
    }));

    // Sort by score (highest first)
    scoredAgents.sort((a, b) => b.score - a.score);

    return scoredAgents[0].agent;
  }

  /**
   * Calculate how well an agent matches a request (0-1 score)
   */
  private calculateAgentScore(agent: BaseAgent, request: AIGenerationRequest): number {
    let score = 0.5; // Base score

    const prompt = request.prompt.toLowerCase();
    
    // Check if prompt mentions specific agent capabilities
    for (const capability of agent.capabilities) {
      if (prompt.includes(capability.toLowerCase().replace('-', ' '))) {
        score += 0.1;
      }
    }

    // Agent type specific scoring
    switch (agent.type) {
      case 'content':
        if (prompt.includes('write') || prompt.includes('content') || prompt.includes('text')) {
          score += 0.3;
        }
        break;
      case 'layout':
        if (prompt.includes('design') || prompt.includes('layout') || prompt.includes('ui')) {
          score += 0.3;
        }
        break;
      case 'stock-art':
        if (prompt.includes('image') || prompt.includes('photo') || prompt.includes('picture')) {
          score += 0.3;
        }
        break;
      case 'ai-art':
        if (prompt.includes('generate') && (prompt.includes('image') || prompt.includes('art'))) {
          score += 0.3;
        }
        break;
    }

    // Prefer idle agents over busy ones
    if (agent.status === 'idle') {
      score += 0.1;
    }

    return Math.min(score, 1.0);
  }

  /**
   * Execute a multi-agent workflow for complex requests
   */
  async executeWorkflow(request: AIGenerationRequest, agentIds: string[]): Promise<AIGenerationResponse[]> {
    const responses: AIGenerationResponse[] = [];
    
    aiLogger.info('Executing multi-agent workflow', {
      agentIds,
      requestId: request.context?.postId
    });

    for (const agentId of agentIds) {
      const agent = this.agents.get(agentId);
      if (!agent) {
        aiLogger.warn(`Agent not found: ${agentId}`);
        continue;
      }

      try {
        // Modify request based on previous responses
        const contextualRequest = this.enhanceRequestWithContext(request, responses);
        const response = await agent.processRequest(contextualRequest);
        responses.push(response);
      } catch (error) {
        aiLogger.error(`Agent ${agentId} failed in workflow`, {
          error: error instanceof Error ? error.message : String(error)
        });
        // Continue with other agents even if one fails
      }
    }

    return responses;
  }

  /**
   * Enhance request with context from previous agent responses
   */
  private enhanceRequestWithContext(
    originalRequest: AIGenerationRequest, 
    previousResponses: AIGenerationResponse[]
  ): AIGenerationRequest {
    if (previousResponses.length === 0) {
      return originalRequest;
    }

    // Add context from previous responses
    let enhancedPrompt = originalRequest.prompt;
    enhancedPrompt += '\n\nPrevious agent outputs for context:';
    
    previousResponses.forEach((response, index) => {
      enhancedPrompt += `\n${index + 1}. ${response.agentUsed}: ${response.content.substring(0, 200)}...`;
    });

    return {
      ...originalRequest,
      prompt: enhancedPrompt
    };
  }

  /**
   * Get status of all agents
   */
  getAllAgentStatus() {
    return Array.from(this.agents.values()).map(agent => agent.getStatus());
  }

  /**
   * Get specific agent by ID
   */
  getAgent(agentId: string): BaseAgent | undefined {
    return this.agents.get(agentId);
  }

  /**
   * Update agent configuration
   */
  updateAgentConfig(agentId: string, config: Partial<any>): boolean {
    const agent = this.agents.get(agentId);
    if (!agent) {
      return false;
    }

    agent.updateConfig(config);
    return true;
  }

  /**
   * Add a custom agent to the orchestrator
   */
  addAgent(agent: BaseAgent): void {
    this.agents.set(agent.id, agent);
    aiLogger.info(`Agent added: ${agent.name}`, {
      agentId: agent.id,
      type: agent.type
    });
  }

  /**
   * Remove an agent from the orchestrator
   */
  removeAgent(agentId: string): boolean {
    const success = this.agents.delete(agentId);
    if (success) {
      aiLogger.info(`Agent removed: ${agentId}`);
    }
    return success;
  }
}