import express from 'express';
import cors from 'cors';
import helmet from 'helmet';
import dotenv from 'dotenv';
import { createServer } from 'http';

import { logger } from './utils/logger';
import { connectDatabase } from './services/database';
import { connectRedis } from './services/redis';
import { initializeQueues } from './services/queue';
import { errorHandler } from './middleware/error-handler';
import { rateLimiter } from './middleware/rate-limiter';
import { authMiddleware } from './middleware/auth';

// Import routes
import aiRoutes from './routes/ai';
import workflowRoutes from './routes/workflows';
import knowledgeRoutes from './routes/knowledge';
import healthRoutes from './routes/health';

// Load environment variables
dotenv.config();

const app = express();
const server = createServer(app);
const PORT = process.env.PORT || 3001;

// Security middleware
app.use(helmet());
app.use(cors({
  origin: [
    process.env.WORDPRESS_URL || 'http://localhost:8080',
    process.env.DASHBOARD_URL || 'http://localhost:3000'
  ],
  credentials: true
}));

// Body parsing middleware
app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ extended: true, limit: '10mb' }));

// Rate limiting
app.use(rateLimiter);

// Health check route (no auth required)
app.use('/health', healthRoutes);

// Authentication middleware for protected routes
app.use('/api', authMiddleware);

// API routes
app.use('/api/ai', aiRoutes);
app.use('/api/workflows', workflowRoutes);
app.use('/api/knowledge', knowledgeRoutes);

// Error handling middleware
app.use(errorHandler);

// Initialize services and start server
async function startServer() {
  try {
    // Connect to database
    logger.info('Connecting to database...');
    await connectDatabase();
    
    // Connect to Redis
    logger.info('Connecting to Redis...');
    await connectRedis();
    
    // Initialize job queues
    logger.info('Initializing job queues...');
    await initializeQueues();
    
    // Start HTTP server
    server.listen(PORT, () => {
      logger.info(`🚀 Cloud API server running on port ${PORT}`);
      logger.info(`📊 Health check: http://localhost:${PORT}/health`);
      logger.info(`🤖 AI endpoints: http://localhost:${PORT}/api/ai`);
    });
    
  } catch (error) {
    logger.error('Failed to start server:', error);
    process.exit(1);
  }
}

// Graceful shutdown
process.on('SIGTERM', () => {
  logger.info('SIGTERM received. Shutting down gracefully...');
  server.close(() => {
    logger.info('Server closed');
    process.exit(0);
  });
});

process.on('SIGINT', () => {
  logger.info('SIGINT received. Shutting down gracefully...');
  server.close(() => {
    logger.info('Server closed');
    process.exit(0);
  });
});

// Handle uncaught exceptions
process.on('uncaughtException', (error) => {
  logger.error('Uncaught Exception:', error);
  process.exit(1);
});

process.on('unhandledRejection', (reason, promise) => {
  logger.error('Unhandled Rejection at:', promise, 'reason:', reason);
  process.exit(1);
});

// Start the server
startServer();