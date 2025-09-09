import Redis from 'ioredis';
import { logger } from '../utils/logger';

let redis: Redis | null = null;

export async function connectRedis(): Promise<void> {
  try {
    const redisUrl = process.env.REDIS_URL || 'redis://localhost:6379';
    
    redis = new Redis(redisUrl, {
      maxRetriesPerRequest: 3,
      retryDelayOnFailover: 100,
      lazyConnect: true,
    });

    await redis.connect();
    
    // Test connection
    await redis.ping();
    
    logger.info('Redis connected successfully');
  } catch (error) {
    logger.error('Redis connection failed', { error });
    throw error;
  }
}

export function getRedis(): Redis {
  if (!redis) {
    throw new Error('Redis not connected');
  }
  return redis;
}