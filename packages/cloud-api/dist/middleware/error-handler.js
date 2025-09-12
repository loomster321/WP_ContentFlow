"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.errorHandler = errorHandler;
const logger_1 = require("../utils/logger");
function errorHandler(error, req, res, next) {
    logger_1.logger.error('API Error', {
        error: error.message,
        stack: error.stack,
        url: req.url,
        method: req.method
    });
    res.status(error.status || 500).json({
        success: false,
        error: {
            message: error.message || 'Internal server error',
            code: error.code || 'INTERNAL_ERROR'
        }
    });
}
