"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.connectDatabase = connectDatabase;
exports.getDatabase = getDatabase;
const pg_1 = require("pg");
const logger_1 = require("../utils/logger");
let pool = null;
async function connectDatabase() {
    try {
        pool = new pg_1.Pool({
            host: process.env.DB_HOST || 'localhost',
            port: parseInt(process.env.DB_PORT || '5432'),
            database: process.env.DB_NAME || 'contentflow',
            user: process.env.DB_USER || 'contentflow',
            password: process.env.DB_PASSWORD || 'contentflow',
            max: 20,
            idleTimeoutMillis: 30000,
            connectionTimeoutMillis: 2000,
        });
        // Test connection
        const client = await pool.connect();
        await client.query('SELECT NOW()');
        client.release();
        logger_1.dbLogger.info('Database connected successfully');
    }
    catch (error) {
        logger_1.dbLogger.error('Database connection failed', { error });
        throw error;
    }
}
function getDatabase() {
    if (!pool) {
        throw new Error('Database not connected');
    }
    return pool;
}
