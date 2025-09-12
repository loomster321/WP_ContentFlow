"use strict";
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.connectRedis = connectRedis;
exports.getRedis = getRedis;
const ioredis_1 = __importDefault(require("ioredis"));
const logger_1 = require("../utils/logger");
let redis = null;
async function connectRedis() {
    try {
        const redisUrl = process.env.REDIS_URL || 'redis://localhost:6379';
        redis = new ioredis_1.default(redisUrl, {
            maxRetriesPerRequest: 3,
            retryDelayOnFailover: 100,
            lazyConnect: true,
        });
        await redis.connect();
        // Test connection
        await redis.ping();
        logger_1.logger.info('Redis connected successfully');
    }
    catch (error) {
        logger_1.logger.error('Redis connection failed', { error });
        throw error;
    }
}
function getRedis() {
    if (!redis) {
        throw new Error('Redis not connected');
    }
    return redis;
}
