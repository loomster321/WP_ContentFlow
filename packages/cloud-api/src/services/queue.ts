import Queue from 'bull';
import { getRedis } from './redis';
import { queueLogger } from '../utils/logger';

let aiGenerationQueue: Queue.Queue | null = null;

export async function initializeQueues(): Promise<void> {
  const redis = getRedis();
  
  aiGenerationQueue = new Queue('ai-generation', {
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
  queueLogger.info('Job queues initialized successfully');
}

export function getAIGenerationQueue(): Queue.Queue {
  if (!aiGenerationQueue) {
    throw new Error('AI generation queue not initialized');
  }
  return aiGenerationQueue;
}