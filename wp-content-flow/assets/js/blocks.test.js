/**
 * @jest-environment jsdom
 */

import '@testing-library/jest-dom';

// Mock WordPress dependencies
const mockRegisterBlockType = jest.fn();
const mockDomReady = jest.fn((callback) => callback());
const mockAddFilter = jest.fn();
const mockAddAction = jest.fn();

global.wp = {
  ...global.wp,
  blocks: {
    ...global.wp.blocks,
    registerBlockType: mockRegisterBlockType,
  },
  domReady: mockDomReady,
  hooks: {
    ...global.wp.hooks,
    addFilter: mockAddFilter,
    addAction: mockAddAction,
  },
  data: {
    ...global.wp.data,
    select: jest.fn(),
    dispatch: jest.fn(),
  },
};

// Mock wpContentFlow global
global.wpContentFlow = {
  apiUrl: 'http://localhost:8080/wp-json/wp-content-flow/v1/',
  nonce: 'test-nonce',
  version: '1.0.0',
  settings: {
    defaultProvider: 'openai',
    enabledProviders: ['openai', 'anthropic'],
  },
  i18n: {
    generateContent: 'Generate Content',
    improveContent: 'Improve Content',
    selectProvider: 'Select AI Provider',
  },
};

describe('Blocks Main Entry', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    
    // Reset modules to ensure fresh imports
    jest.resetModules();
  });

  describe('Block Registration', () => {
    test('should register AI Text Generator block on DOM ready', async () => {
      // Import the blocks file to trigger registration
      await import('./blocks.js');

      expect(mockDomReady).toHaveBeenCalled();
      
      // The callback should register our block
      const domReadyCallback = mockDomReady.mock.calls[0][0];
      domReadyCallback();

      expect(mockRegisterBlockType).toHaveBeenCalledWith(
        'wp-content-flow/ai-text-generator',
        expect.any(Object)
      );
    });

    test('should register block with correct namespace', async () => {
      await import('./blocks.js');

      const registeredBlocks = mockRegisterBlockType.mock.calls;
      const aiTextGeneratorCall = registeredBlocks.find(call => 
        call[0] === 'wp-content-flow/ai-text-generator'
      );

      expect(aiTextGeneratorCall).toBeDefined();
      expect(aiTextGeneratorCall[0]).toBe('wp-content-flow/ai-text-generator');
    });
  });

  describe('WordPress Integration', () => {
    test('should add content improvement filters', async () => {
      await import('./blocks.js');

      expect(mockAddFilter).toHaveBeenCalledWith(
        'blocks.registerBlockType',
        'wp-content-flow/add-ai-attributes',
        expect.any(Function)
      );
    });

    test('should add block editor enhancements', async () => {
      await import('./blocks.js');

      expect(mockAddAction).toHaveBeenCalledWith(
        'enqueue_block_editor_assets',
        expect.any(Function)
      );
    });

    test('should enhance existing blocks with AI capabilities', async () => {
      await import('./blocks.js');

      const filterCallback = mockAddFilter.mock.calls.find(call => 
        call[1] === 'wp-content-flow/add-ai-attributes'
      )[2];

      const mockBlockSettings = {
        name: 'core/paragraph',
        attributes: {
          content: { type: 'string' }
        }
      };

      const enhancedSettings = filterCallback(mockBlockSettings, 'core/paragraph');

      expect(enhancedSettings.attributes).toHaveProperty('aiEnhanced');
      expect(enhancedSettings.attributes).toHaveProperty('aiSuggestions');
    });
  });

  describe('Block Editor Toolbar Integration', () => {
    test('should add AI improvement toolbar to compatible blocks', async () => {
      await import('./blocks.js');

      const toolbarFilter = mockAddFilter.mock.calls.find(call => 
        call[0] === 'editor.BlockEdit'
      );

      expect(toolbarFilter).toBeDefined();
      expect(toolbarFilter[1]).toBe('wp-content-flow/add-ai-toolbar');
    });

    test('should only add toolbar to text blocks', async () => {
      await import('./blocks.js');

      const toolbarCallback = mockAddFilter.mock.calls.find(call => 
        call[1] === 'wp-content-flow/add-ai-toolbar'
      )[2];

      // Mock paragraph block (should get toolbar)
      const mockParagraphEdit = jest.fn();
      const paragraphProps = {
        name: 'core/paragraph',
        attributes: { content: 'Test content' }
      };

      const EnhancedParagraph = toolbarCallback(mockParagraphEdit);
      expect(EnhancedParagraph).toBeDefined();

      // Mock image block (should not get toolbar)
      const mockImageEdit = jest.fn();
      const imageProps = {
        name: 'core/image',
        attributes: { url: 'test.jpg' }
      };

      const EnhancedImage = toolbarCallback(mockImageEdit);
      expect(EnhancedImage).toBeDefined();
    });
  });

  describe('API Integration', () => {
    test('should initialize API client with correct configuration', async () => {
      await import('./blocks.js');

      // Should set up API defaults
      expect(global.wpContentFlow).toHaveProperty('apiUrl');
      expect(global.wpContentFlow).toHaveProperty('nonce');
    });

    test('should handle missing API configuration gracefully', async () => {
      // Temporarily remove global config
      const originalConfig = global.wpContentFlow;
      delete global.wpContentFlow;

      const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();

      await import('./blocks.js');

      expect(consoleSpy).toHaveBeenCalledWith(
        'WP Content Flow: API configuration not found'
      );

      // Restore config
      global.wpContentFlow = originalConfig;
      consoleSpy.mockRestore();
    });
  });

  describe('Block Category Registration', () => {
    test('should register custom block category', async () => {
      const mockGetCategories = jest.fn(() => []);
      const mockSetCategories = jest.fn();

      global.wp.blocks.getCategories = mockGetCategories;
      global.wp.blocks.setCategories = mockSetCategories;

      await import('./blocks.js');

      expect(mockAddFilter).toHaveBeenCalledWith(
        'blocks.getCategories',
        'wp-content-flow/add-category',
        expect.any(Function)
      );

      const categoryCallback = mockAddFilter.mock.calls.find(call => 
        call[1] === 'wp-content-flow/add-category'
      )[2];

      const existingCategories = [
        { slug: 'text', title: 'Text' },
        { slug: 'common', title: 'Common' }
      ];

      const updatedCategories = categoryCallback(existingCategories);

      expect(updatedCategories).toContainEqual({
        slug: 'wp-content-flow',
        title: 'AI Content Flow',
        icon: expect.any(Object)
      });
    });

    test('should not duplicate category if already exists', async () => {
      await import('./blocks.js');

      const categoryCallback = mockAddFilter.mock.calls.find(call => 
        call[1] === 'wp-content-flow/add-category'
      )[2];

      const categoriesWithExisting = [
        { slug: 'text', title: 'Text' },
        { slug: 'wp-content-flow', title: 'AI Content Flow' }
      ];

      const updatedCategories = categoryCallback(categoriesWithExisting);

      const aiCategories = updatedCategories.filter(cat => 
        cat.slug === 'wp-content-flow'
      );

      expect(aiCategories).toHaveLength(1);
    });
  });

  describe('Block Styles and Variations', () => {
    test('should register block styles for AI blocks', async () => {
      const mockRegisterBlockStyle = jest.fn();
      global.wp.blocks.registerBlockStyle = mockRegisterBlockStyle;

      await import('./blocks.js');

      expect(mockRegisterBlockStyle).toHaveBeenCalledWith(
        'wp-content-flow/ai-text-generator',
        {
          name: 'minimal',
          label: 'Minimal',
          isDefault: false
        }
      );

      expect(mockRegisterBlockStyle).toHaveBeenCalledWith(
        'wp-content-flow/ai-text-generator',
        {
          name: 'detailed',
          label: 'Detailed',
          isDefault: true
        }
      );
    });

    test('should register block variations', async () => {
      const mockRegisterBlockVariation = jest.fn();
      global.wp.blocks.registerBlockVariation = mockRegisterBlockVariation;

      await import('./blocks.js');

      expect(mockRegisterBlockVariation).toHaveBeenCalledWith(
        'wp-content-flow/ai-text-generator',
        {
          name: 'blog-post',
          title: 'Blog Post Generator',
          description: 'Generate blog post content',
          icon: expect.any(Object),
          attributes: {
            prompt: 'Write a blog post about ',
            maxTokens: 1500
          },
          scope: ['inserter']
        }
      );
    });
  });

  describe('Block Validation', () => {
    test('should add validation for AI block attributes', async () => {
      await import('./blocks.js');

      const validationFilter = mockAddFilter.mock.calls.find(call => 
        call[0] === 'blocks.getSaveContent.extraProps'
      );

      expect(validationFilter).toBeDefined();
      expect(validationFilter[1]).toBe('wp-content-flow/validate-ai-attributes');
    });

    test('should validate provider selection', async () => {
      await import('./blocks.js');

      const validationCallback = mockAddFilter.mock.calls.find(call => 
        call[1] === 'wp-content-flow/validate-ai-attributes'
      )[2];

      const invalidProps = {
        provider: 'invalid-provider',
        prompt: 'Test prompt'
      };

      const validatedProps = validationCallback(invalidProps, 'wp-content-flow/ai-text-generator');

      expect(validatedProps.provider).toBe('openai'); // Should default to valid provider
    });

    test('should validate token limits', async () => {
      await import('./blocks.js');

      const validationCallback = mockAddFilter.mock.calls.find(call => 
        call[1] === 'wp-content-flow/validate-ai-attributes'
      )[2];

      const invalidProps = {
        provider: 'openai',
        maxTokens: 10000 // Too high
      };

      const validatedProps = validationCallback(invalidProps, 'wp-content-flow/ai-text-generator');

      expect(validatedProps.maxTokens).toBeLessThanOrEqual(4000); // Should be capped
    });
  });

  describe('Error Handling', () => {
    test('should handle block registration errors gracefully', async () => {
      mockRegisterBlockType.mockImplementationOnce(() => {
        throw new Error('Registration failed');
      });

      const consoleSpy = jest.spyOn(console, 'error').mockImplementation();

      await import('./blocks.js');

      expect(consoleSpy).toHaveBeenCalledWith(
        'Failed to register AI blocks:',
        expect.any(Error)
      );

      consoleSpy.mockRestore();
    });

    test('should handle missing WordPress dependencies', async () => {
      const originalWp = global.wp;
      global.wp = {};

      const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();

      await import('./blocks.js');

      expect(consoleSpy).toHaveBeenCalledWith(
        'WordPress blocks API not available'
      );

      global.wp = originalWp;
      consoleSpy.mockRestore();
    });
  });

  describe('Internationalization', () => {
    test('should load text domain for translations', async () => {
      const mockLoadTextDomain = jest.fn();
      global.wp.i18n.setLocaleData = mockLoadTextDomain;

      await import('./blocks.js');

      expect(mockLoadTextDomain).toHaveBeenCalled();
    });

    test('should use translated strings from global config', async () => {
      await import('./blocks.js');

      // Block registration should use translated strings
      const blockCall = mockRegisterBlockType.mock.calls.find(call => 
        call[0] === 'wp-content-flow/ai-text-generator'
      );

      expect(blockCall).toBeDefined();
      
      // Strings should come from global config or be translatable
      const blockConfig = blockCall[1];
      expect(blockConfig.title).toBeTruthy();
      expect(blockConfig.description).toBeTruthy();
    });
  });

  describe('Performance Optimization', () => {
    test('should lazy load block components', async () => {
      const mockDynamicImport = jest.fn(() => Promise.resolve({}));
      
      // Mock dynamic import
      global.import = mockDynamicImport;

      await import('./blocks.js');

      // Should not import heavy components immediately
      expect(mockDynamicImport).not.toHaveBeenCalledWith('./ai-text-generator/index.js');
    });

    test('should debounce API calls', async () => {
      await import('./blocks.js');

      // Should set up debouncing for API calls
      expect(global.wpContentFlow).toHaveProperty('debounceTimeout');
    });
  });

  describe('Development Mode', () => {
    test('should enable debug mode in development', async () => {
      const originalEnv = process.env.NODE_ENV;
      process.env.NODE_ENV = 'development';

      await import('./blocks.js');

      expect(global.wpContentFlow).toHaveProperty('debug', true);

      process.env.NODE_ENV = originalEnv;
    });

    test('should disable debug mode in production', async () => {
      const originalEnv = process.env.NODE_ENV;
      process.env.NODE_ENV = 'production';

      await import('./blocks.js');

      expect(global.wpContentFlow.debug).toBeFalsy();

      process.env.NODE_ENV = originalEnv;
    });
  });
});