"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.LayoutAgent = void 0;
const base_agent_1 = require("./base-agent");
const openai_service_1 = require("../services/openai-service");
const logger_1 = require("../utils/logger");
/**
 * Layout Agent - Specialized for UX/UI design suggestions and layout optimization
 */
class LayoutAgent extends base_agent_1.BaseAgent {
    openaiService;
    constructor(config) {
        super('layout-agent', 'Layout & Design Agent', 'layout', [
            'layout-design',
            'ui-suggestions',
            'ux-improvements',
            'responsive-design',
            'accessibility',
            'visual-hierarchy',
            'color-schemes',
            'typography',
            'spacing',
            'grid-layouts'
        ], config);
        this.openaiService = new openai_service_1.OpenAIService();
    }
    canHandle(request) {
        const prompt = request.prompt.toLowerCase();
        const layoutKeywords = ['layout', 'design', 'ui', 'ux', 'visual', 'style', 'responsive', 'mobile'];
        return layoutKeywords.some(keyword => prompt.includes(keyword));
    }
    async generateContent(request) {
        logger_1.aiLogger.info('Layout agent processing request', {
            prompt: request.prompt.substring(0, 100)
        });
        // TODO: Implement layout-specific generation logic
        // For now, return a placeholder response
        return {
            content: 'Layout suggestions will be implemented in the next phase.',
            confidenceScore: 0.8,
            tokenUsage: { promptTokens: 0, completionTokens: 0, totalTokens: 0 },
            processingTime: 0.5,
            agentUsed: this.id,
            metadata: { type: 'layout-suggestion' }
        };
    }
}
exports.LayoutAgent = LayoutAgent;
