"use strict";
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
const express_1 = __importDefault(require("express"));
const cors_1 = __importDefault(require("cors"));
const helmet_1 = __importDefault(require("helmet"));
const dotenv_1 = __importDefault(require("dotenv"));
const http_1 = require("http");
const logger_1 = require("./utils/logger");
const database_1 = require("./services/database");
const redis_1 = require("./services/redis");
const queue_1 = require("./services/queue");
const error_handler_1 = require("./middleware/error-handler");
const rate_limiter_1 = require("./middleware/rate-limiter");
const auth_1 = require("./middleware/auth");
// Import routes
const ai_1 = __importDefault(require("./routes/ai"));
const workflows_1 = __importDefault(require("./routes/workflows"));
const knowledge_1 = __importDefault(require("./routes/knowledge"));
const health_1 = __importDefault(require("./routes/health"));
// Load environment variables
dotenv_1.default.config();
const app = (0, express_1.default)();
const server = (0, http_1.createServer)(app);
const PORT = process.env.PORT || 3001;
// Security middleware
app.use((0, helmet_1.default)());
app.use((0, cors_1.default)({
    origin: [
        process.env.WORDPRESS_URL || 'http://localhost:8080',
        process.env.DASHBOARD_URL || 'http://localhost:3000'
    ],
    credentials: true
}));
// Body parsing middleware
app.use(express_1.default.json({ limit: '10mb' }));
app.use(express_1.default.urlencoded({ extended: true, limit: '10mb' }));
// Rate limiting
app.use(rate_limiter_1.rateLimiter);
// Health check route (no auth required)
app.use('/health', health_1.default);
// Authentication middleware for protected routes
app.use('/api', auth_1.authMiddleware);
// API routes
app.use('/api/ai', ai_1.default);
app.use('/api/workflows', workflows_1.default);
app.use('/api/knowledge', knowledge_1.default);
// Error handling middleware
app.use(error_handler_1.errorHandler);
// Initialize services and start server
async function startServer() {
    try {
        // Connect to database
        logger_1.logger.info('Connecting to database...');
        await (0, database_1.connectDatabase)();
        // Connect to Redis
        logger_1.logger.info('Connecting to Redis...');
        await (0, redis_1.connectRedis)();
        // Initialize job queues
        logger_1.logger.info('Initializing job queues...');
        await (0, queue_1.initializeQueues)();
        // Start HTTP server
        server.listen(PORT, () => {
            logger_1.logger.info(`🚀 Cloud API server running on port ${PORT}`);
            logger_1.logger.info(`📊 Health check: http://localhost:${PORT}/health`);
            logger_1.logger.info(`🤖 AI endpoints: http://localhost:${PORT}/api/ai`);
        });
    }
    catch (error) {
        logger_1.logger.error('Failed to start server:', error);
        process.exit(1);
    }
}
// Graceful shutdown
process.on('SIGTERM', () => {
    logger_1.logger.info('SIGTERM received. Shutting down gracefully...');
    server.close(() => {
        logger_1.logger.info('Server closed');
        process.exit(0);
    });
});
process.on('SIGINT', () => {
    logger_1.logger.info('SIGINT received. Shutting down gracefully...');
    server.close(() => {
        logger_1.logger.info('Server closed');
        process.exit(0);
    });
});
// Handle uncaught exceptions
process.on('uncaughtException', (error) => {
    logger_1.logger.error('Uncaught Exception:', error);
    process.exit(1);
});
process.on('unhandledRejection', (reason, promise) => {
    logger_1.logger.error('Unhandled Rejection at:', promise, 'reason:', reason);
    process.exit(1);
});
// Start the server
startServer();
