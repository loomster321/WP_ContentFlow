/**
 * Jest setup file for WordPress AI Content Flow Plugin
 */

import '@testing-library/jest-dom';

// Mock WordPress globals
global.wp = {
    blocks: {
        registerBlockType: jest.fn(),
        createBlock: jest.fn(),
    },
    element: {
        createElement: jest.fn(),
        Fragment: jest.fn(),
    },
    components: {
        Button: jest.fn(),
        TextControl: jest.fn(),
        SelectControl: jest.fn(),
        ToggleControl: jest.fn(),
        Panel: jest.fn(),
        PanelBody: jest.fn(),
        PanelRow: jest.fn(),
    },
    data: {
        useSelect: jest.fn(),
        useDispatch: jest.fn(),
        select: jest.fn(),
        dispatch: jest.fn(),
    },
    blockEditor: {
        useBlockProps: jest.fn(),
        InnerBlocks: jest.fn(),
        RichText: jest.fn(),
        BlockControls: jest.fn(),
        InspectorControls: jest.fn(),
    },
    i18n: {
        __: jest.fn((text) => text),
        _x: jest.fn((text) => text),
        sprintf: jest.fn(),
    },
    apiFetch: jest.fn(),
    url: {
        addQueryArgs: jest.fn(),
    },
    notices: {
        createNotice: jest.fn(),
        createSuccessNotice: jest.fn(),
        createErrorNotice: jest.fn(),
    },
    hooks: {
        addFilter: jest.fn(),
        addAction: jest.fn(),
        applyFilters: jest.fn(),
        doAction: jest.fn(),
    },
};

// Mock wpContentFlow global
global.wpContentFlow = {
    apiUrl: 'http://localhost:8080/wp-json/wp-content-flow/v1/',
    nonce: 'test-nonce',
    version: '1.0.0',
};

// Mock fetch for API calls
global.fetch = jest.fn(() =>
    Promise.resolve({
        ok: true,
        status: 200,
        json: () => Promise.resolve({}),
        text: () => Promise.resolve(''),
    })
);

// Mock console methods to reduce test noise
const originalError = console.error;
console.error = jest.fn((...args) => {
    // Still show errors that aren't React warnings
    if (
        args[0] &&
        typeof args[0] === 'string' &&
        !args[0].includes('Warning:')
    ) {
        originalError.call(console, ...args);
    }
});

// Clean up after each test
afterEach(() => {
    jest.clearAllMocks();
    // Clear any DOM changes
    document.body.innerHTML = '';
});

// Global test utilities
global.createMockBlock = (blockName, attributes = {}) => ({
    name: blockName,
    attributes,
    clientId: Math.random().toString(36).substr(2, 9),
    isValid: true,
});

global.createMockWorkflow = (overrides = {}) => ({
    id: 1,
    name: 'Test Workflow',
    description: 'Test workflow description',
    ai_provider: 'openai',
    settings: {
        model: 'gpt-4',
        temperature: 0.7,
        max_tokens: 1000,
    },
    status: 'active',
    created_at: '2023-01-01T00:00:00Z',
    updated_at: '2023-01-01T00:00:00Z',
    ...overrides,
});

global.createMockSuggestion = (overrides = {}) => ({
    id: 1,
    post_id: 123,
    workflow_id: 1,
    original_content: 'Original content',
    suggested_content: 'Improved content',
    suggestion_type: 'improvement',
    status: 'pending',
    confidence_score: 0.85,
    created_at: '2023-01-01T00:00:00Z',
    ...overrides,
});

// Mock ResizeObserver for components that use it
global.ResizeObserver = class ResizeObserver {
    constructor(callback) {
        this.callback = callback;
    }
    observe() {}
    unobserve() {}
    disconnect() {}
};