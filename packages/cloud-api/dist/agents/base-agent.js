"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.BaseAgent = void 0;
const logger_1 = require("../utils/logger");
class BaseAgent {
    id;
    name;
    type;
    status = 'idle';
    capabilities;
    config;
    constructor(id, name, type, capabilities, config) {
        this.id = id;
        this.name = name;
        this.type = type;
        this.capabilities = capabilities;
        this.config = config;
    }
    /**
     * Validate if this agent can handle the given request
     */
    canHandle(request) {
        // Default implementation - can be overridden by specific agents
        return true;
    }
    /**
     * Process a request with error handling and status management
     */
    async processRequest(request) {
        if (!this.canHandle(request)) {
            throw new Error(`Agent ${this.name} cannot handle this type of request`);
        }
        this.status = 'processing';
        logger_1.aiLogger.info(`Agent ${this.name} processing request`, {
            agentId: this.id,
            requestId: request.context?.postId,
            prompt: request.prompt.substring(0, 100) + '...'
        });
        const startTime = Date.now();
        try {
            const response = await this.generateContent(request);
            const processingTime = (Date.now() - startTime) / 1000;
            this.status = 'idle';
            logger_1.aiLogger.info(`Agent ${this.name} completed request`, {
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
        }
        catch (error) {
            this.status = 'error';
            logger_1.aiLogger.error(`Agent ${this.name} failed to process request`, {
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
    updateConfig(newConfig) {
        this.config = {
            ...this.config,
            ...newConfig
        };
        logger_1.aiLogger.info(`Agent ${this.name} configuration updated`, {
            agentId: this.id,
            updatedFields: Object.keys(newConfig)
        });
    }
    /**
     * Validate agent configuration
     */
    validateConfig() {
        const requiredFields = ['provider', 'model', 'systemPrompt'];
        for (const field of requiredFields) {
            if (!this.config[field]) {
                logger_1.aiLogger.error(`Agent ${this.name} missing required config field: ${field}`);
                return false;
            }
        }
        return true;
    }
}
exports.BaseAgent = BaseAgent;
