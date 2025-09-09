// AI Agent Types
export interface AIAgent {
  id: string;
  name: string;
  type: 'content' | 'layout' | 'stock-art' | 'ai-art';
  status: 'idle' | 'processing' | 'error';
  capabilities: string[];
  config: AIAgentConfig;
}

export interface AIAgentConfig {
  provider: 'openai' | 'anthropic' | 'google';
  model: string;
  temperature: number;
  maxTokens: number;
  systemPrompt: string;
}

// Workflow Types
export interface Workflow {
  id: number;
  name: string;
  description?: string;
  agentId: string;
  config: WorkflowConfig;
  isActive: boolean;
  createdAt: Date;
  updatedAt: Date;
}

export interface WorkflowConfig {
  aiProvider: string;
  model: string;
  parameters: Record<string, any>;
  prompts: {
    system: string;
    user?: string;
  };
  autoRun?: boolean;
  approvalRequired?: boolean;
}

// Content Types
export interface ContentSuggestion {
  id: number;
  postId: number;
  workflowId: number;
  originalContent?: string;
  suggestedContent: string;
  suggestionType: 'generation' | 'improvement' | 'correction';
  confidenceScore: number;
  status: 'pending' | 'accepted' | 'rejected';
  createdAt: Date;
}

export interface ContentHistory {
  id: number;
  postId: number;
  changeType: 'ai_generated' | 'ai_improved' | 'manual_edit' | 'ai_rejected';
  contentBefore?: string;
  contentAfter: string;
  userId: number;
  metadata?: Record<string, any>;
  createdAt: Date;
}

// RAG Knowledge Base Types
export interface KnowledgeBase {
  id: string;
  type: 'brand-voice' | 'writing-guidelines' | 'visual-guidelines' | 'stock-art';
  name: string;
  description?: string;
  documents: KnowledgeDocument[];
  vectorStoreId?: string;
  lastUpdated: Date;
}

export interface KnowledgeDocument {
  id: string;
  title: string;
  content: string;
  metadata: {
    source: string;
    category: string;
    tags: string[];
    lastModified: Date;
  };
  embedding?: number[];
}

// AI Request/Response Types
export interface AIGenerationRequest {
  prompt: string;
  workflowId: number;
  context?: {
    postId?: number;
    blockId?: string;
    selectedContent?: string;
  };
  knowledgeBaseIds?: string[];
}

export interface AIGenerationResponse {
  content: string;
  confidenceScore: number;
  tokenUsage: {
    promptTokens: number;
    completionTokens: number;
    totalTokens: number;
  };
  processingTime: number;
  agentUsed: string;
  metadata?: Record<string, any>;
}

// WordPress Integration Types
export interface WordPressBlock {
  name: string;
  attributes: Record<string, any>;
  innerBlocks?: WordPressBlock[];
  clientId: string;
}

export interface WordPressPost {
  id: number;
  title: string;
  content: string;
  status: string;
  blocks: WordPressBlock[];
  meta?: Record<string, any>;
}

// User Preferences
export interface UserPreferences {
  userId: number;
  defaultWorkflowId?: number;
  aiProvider: string;
  showConfidenceScores: boolean;
  autoApplyHighConfidence: boolean;
  maxSuggestions: number;
  notificationSettings: {
    emailOnApproval: boolean;
    emailOnError: boolean;
  };
}

// API Response Types
export interface APIResponse<T> {
  success: boolean;
  data?: T;
  error?: {
    code: string;
    message: string;
    details?: any;
  };
  meta?: {
    total?: number;
    page?: number;
    perPage?: number;
  };
}

// Queue Job Types
export interface QueueJob {
  id: string;
  type: 'ai-generation' | 'content-improvement' | 'image-generation' | 'knowledge-sync';
  payload: any;
  priority: number;
  status: 'pending' | 'processing' | 'completed' | 'failed';
  attempts: number;
  maxAttempts: number;
  createdAt: Date;
  processedAt?: Date;
  completedAt?: Date;
  error?: string;
}