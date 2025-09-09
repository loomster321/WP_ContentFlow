import { Pool } from 'pg';
import { dbLogger } from '../utils/logger';

let pool: Pool | null = null;

export async function connectDatabase(): Promise<void> {
  try {
    pool = new Pool({
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

    dbLogger.info('Database connected successfully');
  } catch (error) {
    dbLogger.error('Database connection failed', { error });
    throw error;
  }
}

export function getDatabase(): Pool {
  if (!pool) {
    throw new Error('Database not connected');
  }
  return pool;
}