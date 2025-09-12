"use strict";
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.initializeQueues = initializeQueues;
exports.getAIGenerationQueue = getAIGenerationQueue;
const bull_1 = __importDefault(require("bull"));
const redis_1 = require("./redis");
const logger_1 = require("../utils/logger");
let aiGenerationQueue = null;
async function initializeQueues() {
    const redis = (0, redis_1.getRedis)();
    aiGenerationQueue = new bull_1.default('ai-generation', {
        redis: {
            port: 6379,
            host: process.env.REDIS_HOST || 'localhost',
        },
        defaultJobOptions: {
            attempts: 3,
            backoff: 'exponential',
            delay: 2000,
        }
    });
    // TODO: Add queue processors
    logger_1.queueLogger.info('Job queues initialized successfully');
}
function getAIGenerationQueue() {
    if (!aiGenerationQueue) {
        throw new Error('AI generation queue not initialized');
    }
    return aiGenerationQueue;
}
