"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.RAGService = void 0;
const logger_1 = require("../utils/logger");
const database_1 = require("./database");
/**
 * RAG (Retrieval Augmented Generation) Service
 * Manages knowledge bases for brand voice, writing guidelines, visual guidelines, and stock art
 */
class RAGService {
    /**
     * Create a new knowledge base
     */
    async createKnowledgeBase(data) {
        const id = `kb_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
        const knowledgeBase = {
            id,
            ...data,
            documents: [],
            lastUpdated: new Date()
        };
        try {
            const db = (0, database_1.getDatabase)();
            await db.query('INSERT INTO knowledge_bases (id, type, name, description, data, created_at) VALUES ($1, $2, $3, $4, $5, $6)', [id, data.type, data.name, data.description, JSON.stringify(knowledgeBase), new Date()]);
            logger_1.ragLogger.info('Knowledge base created', {
                id,
                type: data.type,
                name: data.name
            });
            return id;
        }
        catch (error) {
            logger_1.ragLogger.error('Failed to create knowledge base', {
                error: error instanceof Error ? error.message : String(error),
                type: data.type,
                name: data.name
            });
            throw error;
        }
    }
    /**
     * Add document to knowledge base
     */
    async addDocument(knowledgeBaseId, document) {
        const documentId = `doc_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
        try {
            // Get existing knowledge base
            const knowledgeBase = await this.getKnowledgeBase(knowledgeBaseId);
            if (!knowledgeBase) {
                throw new Error(`Knowledge base not found: ${knowledgeBaseId}`);
            }
            // Create document with embedding (placeholder for now)
            const newDocument = {
                id: documentId,
                ...document,
                embedding: await this.generateEmbedding(document.content)
            };
            // Add document to knowledge base
            knowledgeBase.documents.push(newDocument);
            knowledgeBase.lastUpdated = new Date();
            // Update database
            const db = (0, database_1.getDatabase)();
            await db.query('UPDATE knowledge_bases SET data = $1, updated_at = $2 WHERE id = $3', [JSON.stringify(knowledgeBase), new Date(), knowledgeBaseId]);
            logger_1.ragLogger.info('Document added to knowledge base', {
                knowledgeBaseId,
                documentId,
                title: document.title,
                contentLength: document.content.length
            });
            return documentId;
        }
        catch (error) {
            logger_1.ragLogger.error('Failed to add document to knowledge base', {
                error: error instanceof Error ? error.message : String(error),
                knowledgeBaseId,
                title: document.title
            });
            throw error;
        }
    }
    /**
     * Search knowledge base for relevant documents
     */
    async searchKnowledgeBase(knowledgeBaseId, query, limit = 5) {
        try {
            const knowledgeBase = await this.getKnowledgeBase(knowledgeBaseId);
            if (!knowledgeBase) {
                throw new Error(`Knowledge base not found: ${knowledgeBaseId}`);
            }
            // Generate embedding for search query
            const queryEmbedding = await this.generateEmbedding(query);
            // Calculate similarity scores and sort
            const rankedDocuments = knowledgeBase.documents
                .map(doc => ({
                document: doc,
                similarity: this.calculateSimilarity(queryEmbedding, doc.embedding || [])
            }))
                .sort((a, b) => b.similarity - a.similarity)
                .slice(0, limit)
                .map(item => item.document);
            logger_1.ragLogger.info('Knowledge base searched', {
                knowledgeBaseId,
                query: query.substring(0, 100),
                resultsCount: rankedDocuments.length
            });
            return rankedDocuments;
        }
        catch (error) {
            logger_1.ragLogger.error('Knowledge base search failed', {
                error: error instanceof Error ? error.message : String(error),
                knowledgeBaseId,
                query: query.substring(0, 100)
            });
            throw error;
        }
    }
    /**
     * Get knowledge base by ID
     */
    async getKnowledgeBase(id) {
        try {
            const db = (0, database_1.getDatabase)();
            const result = await db.query('SELECT data FROM knowledge_bases WHERE id = $1', [id]);
            if (result.rows.length === 0) {
                return null;
            }
            return result.rows[0].data;
        }
        catch (error) {
            logger_1.ragLogger.error('Failed to get knowledge base', {
                error: error instanceof Error ? error.message : String(error),
                id
            });
            return null;
        }
    }
    /**
     * List all knowledge bases of a specific type
     */
    async listKnowledgeBases(type) {
        try {
            const db = (0, database_1.getDatabase)();
            let query = 'SELECT data FROM knowledge_bases';
            let params = [];
            if (type) {
                query += ' WHERE type = $1';
                params.push(type);
            }
            query += ' ORDER BY created_at DESC';
            const result = await db.query(query, params);
            return result.rows.map(row => row.data);
        }
        catch (error) {
            logger_1.ragLogger.error('Failed to list knowledge bases', {
                error: error instanceof Error ? error.message : String(error),
                type
            });
            return [];
        }
    }
    /**
     * Generate context for AI prompts from relevant documents
     */
    async generateContext(knowledgeBaseIds, query) {
        let context = '';
        for (const kbId of knowledgeBaseIds) {
            try {
                const relevantDocs = await this.searchKnowledgeBase(kbId, query, 3);
                if (relevantDocs.length > 0) {
                    const kb = await this.getKnowledgeBase(kbId);
                    context += `\n\n## ${kb?.type.toUpperCase()} Guidelines:\n`;
                    relevantDocs.forEach(doc => {
                        context += `\n### ${doc.title}\n${doc.content.substring(0, 500)}...\n`;
                    });
                }
            }
            catch (error) {
                logger_1.ragLogger.warn('Failed to get context from knowledge base', {
                    knowledgeBaseId: kbId,
                    error: error instanceof Error ? error.message : String(error)
                });
            }
        }
        return context;
    }
    /**
     * Update document in knowledge base
     */
    async updateDocument(knowledgeBaseId, documentId, updates) {
        try {
            const knowledgeBase = await this.getKnowledgeBase(knowledgeBaseId);
            if (!knowledgeBase) {
                throw new Error(`Knowledge base not found: ${knowledgeBaseId}`);
            }
            const docIndex = knowledgeBase.documents.findIndex(doc => doc.id === documentId);
            if (docIndex === -1) {
                throw new Error(`Document not found: ${documentId}`);
            }
            // Update document
            knowledgeBase.documents[docIndex] = {
                ...knowledgeBase.documents[docIndex],
                ...updates,
                metadata: {
                    ...knowledgeBase.documents[docIndex].metadata,
                    lastModified: new Date()
                }
            };
            // Regenerate embedding if content changed
            if (updates.content) {
                knowledgeBase.documents[docIndex].embedding = await this.generateEmbedding(updates.content);
            }
            knowledgeBase.lastUpdated = new Date();
            // Update database
            const db = (0, database_1.getDatabase)();
            await db.query('UPDATE knowledge_bases SET data = $1, updated_at = $2 WHERE id = $3', [JSON.stringify(knowledgeBase), new Date(), knowledgeBaseId]);
            logger_1.ragLogger.info('Document updated in knowledge base', {
                knowledgeBaseId,
                documentId,
                updatedFields: Object.keys(updates)
            });
            return true;
        }
        catch (error) {
            logger_1.ragLogger.error('Failed to update document', {
                error: error instanceof Error ? error.message : String(error),
                knowledgeBaseId,
                documentId
            });
            return false;
        }
    }
    /**
     * Delete document from knowledge base
     */
    async deleteDocument(knowledgeBaseId, documentId) {
        try {
            const knowledgeBase = await this.getKnowledgeBase(knowledgeBaseId);
            if (!knowledgeBase) {
                throw new Error(`Knowledge base not found: ${knowledgeBaseId}`);
            }
            const originalLength = knowledgeBase.documents.length;
            knowledgeBase.documents = knowledgeBase.documents.filter(doc => doc.id !== documentId);
            if (knowledgeBase.documents.length === originalLength) {
                throw new Error(`Document not found: ${documentId}`);
            }
            knowledgeBase.lastUpdated = new Date();
            // Update database
            const db = (0, database_1.getDatabase)();
            await db.query('UPDATE knowledge_bases SET data = $1, updated_at = $2 WHERE id = $3', [JSON.stringify(knowledgeBase), new Date(), knowledgeBaseId]);
            logger_1.ragLogger.info('Document deleted from knowledge base', {
                knowledgeBaseId,
                documentId
            });
            return true;
        }
        catch (error) {
            logger_1.ragLogger.error('Failed to delete document', {
                error: error instanceof Error ? error.message : String(error),
                knowledgeBaseId,
                documentId
            });
            return false;
        }
    }
    /**
     * Generate embedding for text (placeholder implementation)
     * In production, this would use OpenAI embeddings or similar
     */
    async generateEmbedding(text) {
        // TODO: Implement actual embedding generation using OpenAI or similar service
        // For now, return a simple hash-based pseudo-embedding
        const hash = this.simpleHash(text);
        const embedding = new Array(384).fill(0).map((_, i) => Math.sin(hash * (i + 1)) * Math.cos(hash * (i + 2)));
        return embedding;
    }
    /**
     * Calculate similarity between two embeddings using cosine similarity
     */
    calculateSimilarity(embedding1, embedding2) {
        if (embedding1.length !== embedding2.length) {
            return 0;
        }
        let dotProduct = 0;
        let norm1 = 0;
        let norm2 = 0;
        for (let i = 0; i < embedding1.length; i++) {
            dotProduct += embedding1[i] * embedding2[i];
            norm1 += embedding1[i] * embedding1[i];
            norm2 += embedding2[i] * embedding2[i];
        }
        if (norm1 === 0 || norm2 === 0) {
            return 0;
        }
        return dotProduct / (Math.sqrt(norm1) * Math.sqrt(norm2));
    }
    /**
     * Simple hash function for pseudo-embedding generation
     */
    simpleHash(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash; // Convert to 32-bit integer
        }
        return Math.abs(hash);
    }
}
exports.RAGService = RAGService;
