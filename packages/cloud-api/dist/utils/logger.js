"use strict";
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.apiLogger = exports.ragLogger = exports.dbLogger = exports.queueLogger = exports.aiLogger = exports.createChildLogger = exports.logger = void 0;
const winston_1 = __importDefault(require("winston"));
const logLevel = process.env.LOG_LEVEL || 'info';
const nodeEnv = process.env.NODE_ENV || 'development';
// Create logger configuration
const logFormat = winston_1.default.format.combine(winston_1.default.format.timestamp({ format: 'YYYY-MM-DD HH:mm:ss' }), winston_1.default.format.errors({ stack: true }), winston_1.default.format.json(), winston_1.default.format.prettyPrint());
// Create Winston logger instance
exports.logger = winston_1.default.createLogger({
    level: logLevel,
    format: logFormat,
    defaultMeta: {
        service: 'wp-content-flow-api',
        environment: nodeEnv
    },
    transports: [
        // Console transport for development
        new winston_1.default.transports.Console({
            format: winston_1.default.format.combine(winston_1.default.format.colorize(), winston_1.default.format.simple(), winston_1.default.format.printf(({ timestamp, level, message, ...meta }) => {
                let log = `${timestamp} [${level}] ${message}`;
                // Add metadata if present
                if (Object.keys(meta).length > 0) {
                    log += ` ${JSON.stringify(meta, null, 2)}`;
                }
                return log;
            }))
        })
    ],
    // Handle uncaught exceptions and rejections
    exceptionHandlers: [
        new winston_1.default.transports.Console({
            format: winston_1.default.format.combine(winston_1.default.format.timestamp(), winston_1.default.format.json())
        })
    ],
    rejectionHandlers: [
        new winston_1.default.transports.Console({
            format: winston_1.default.format.combine(winston_1.default.format.timestamp(), winston_1.default.format.json())
        })
    ]
});
// Add file transport for production
if (nodeEnv === 'production') {
    exports.logger.add(new winston_1.default.transports.File({
        filename: 'logs/error.log',
        level: 'error'
    }));
    exports.logger.add(new winston_1.default.transports.File({
        filename: 'logs/combined.log'
    }));
}
// Create child logger for specific components
const createChildLogger = (component) => {
    return exports.logger.child({ component });
};
exports.createChildLogger = createChildLogger;
// Export specific loggers for common components
exports.aiLogger = (0, exports.createChildLogger)('ai-agent');
exports.queueLogger = (0, exports.createChildLogger)('queue');
exports.dbLogger = (0, exports.createChildLogger)('database');
exports.ragLogger = (0, exports.createChildLogger)('rag');
exports.apiLogger = (0, exports.createChildLogger)('api');
