/**
 * @jest-environment jsdom
 */

import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom';

// Mock WordPress dependencies
const mockRegisterBlockType = jest.fn();
const mockCreateBlock = jest.fn();
const mockUseBlockProps = jest.fn(() => ({ className: 'wp-block-ai-text-generator' }));
const mockApiRequest = jest.fn();
const mockCreateNotice = jest.fn();

global.wp = {
  ...global.wp,
  blocks: {
    ...global.wp.blocks,
    registerBlockType: mockRegisterBlockType,
    createBlock: mockCreateBlock,
  },
  blockEditor: {
    ...global.wp.blockEditor,
    useBlockProps: mockUseBlockProps,
  },
  apiFetch: mockApiRequest,
  notices: {
    ...global.wp.notices,
    createNotice: mockCreateNotice,
  },
};

// Import the block after mocking
import './index.js';

describe('AI Text Generator Block', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockApiRequest.mockResolvedValue({
      success: true,
      data: {
        content: 'Generated AI content',
        usage: { tokens: 50 },
        provider: 'openai'
      }
    });
  });

  describe('Block Registration', () => {
    test('should register block with correct configuration', () => {
      expect(mockRegisterBlockType).toHaveBeenCalledWith(
        'wp-content-flow/ai-text-generator',
        expect.objectContaining({
          title: 'AI Text Generator',
          description: 'Generate content using AI providers like OpenAI and Claude',
          category: 'text',
          icon: expect.any(Object),
          keywords: expect.arrayContaining(['ai', 'content', 'generate']),
          attributes: expect.objectContaining({
            prompt: { type: 'string', default: '' },
            provider: { type: 'string', default: 'openai' },
            temperature: { type: 'number', default: 0.7 },
            maxTokens: { type: 'number', default: 1000 },
            generatedContent: { type: 'string', default: '' },
            isGenerating: { type: 'boolean', default: false }
          }),
          supports: expect.objectContaining({
            html: false,
            align: true,
            anchor: true
          }),
          edit: expect.any(Function),
          save: expect.any(Function)
        })
      );
    });

    test('should have correct block metadata', () => {
      const blockConfig = mockRegisterBlockType.mock.calls[0][1];
      
      expect(blockConfig.title).toBe('AI Text Generator');
      expect(blockConfig.category).toBe('text');
      expect(blockConfig.keywords).toContain('ai');
      expect(blockConfig.keywords).toContain('content');
      expect(blockConfig.keywords).toContain('generate');
    });
  });

  describe('Block Attributes', () => {
    test('should define all required attributes with correct types', () => {
      const blockConfig = mockRegisterBlockType.mock.calls[0][1];
      const { attributes } = blockConfig;

      expect(attributes.prompt).toEqual({ type: 'string', default: '' });
      expect(attributes.provider).toEqual({ type: 'string', default: 'openai' });
      expect(attributes.temperature).toEqual({ type: 'number', default: 0.7 });
      expect(attributes.maxTokens).toEqual({ type: 'number', default: 1000 });
      expect(attributes.generatedContent).toEqual({ type: 'string', default: '' });
      expect(attributes.isGenerating).toEqual({ type: 'boolean', default: false });
    });

    test('should have appropriate default values', () => {
      const blockConfig = mockRegisterBlockType.mock.calls[0][1];
      const { attributes } = blockConfig;

      expect(attributes.prompt.default).toBe('');
      expect(attributes.provider.default).toBe('openai');
      expect(attributes.temperature.default).toBe(0.7);
      expect(attributes.maxTokens.default).toBe(1000);
    });
  });

  describe('Edit Component', () => {
    let EditComponent;
    let mockSetAttributes;

    beforeEach(() => {
      const blockConfig = mockRegisterBlockType.mock.calls[0][1];
      EditComponent = blockConfig.edit;
      mockSetAttributes = jest.fn();
    });

    const createMockProps = (attributes = {}) => ({
      attributes: {
        prompt: '',
        provider: 'openai',
        temperature: 0.7,
        maxTokens: 1000,
        generatedContent: '',
        isGenerating: false,
        ...attributes
      },
      setAttributes: mockSetAttributes,
      clientId: 'test-client-id',
      isSelected: true
    });

    test('should render prompt input field', () => {
      const props = createMockProps();
      render(<EditComponent {...props} />);

      const promptInput = screen.getByPlaceholderText(/enter your prompt/i);
      expect(promptInput).toBeInTheDocument();
      expect(promptInput).toHaveValue('');
    });

    test('should render AI provider selector', () => {
      const props = createMockProps();
      render(<EditComponent {...props} />);

      const providerSelect = screen.getByLabelText(/ai provider/i);
      expect(providerSelect).toBeInTheDocument();
      expect(providerSelect).toHaveValue('openai');
    });

    test('should render generate button', () => {
      const props = createMockProps();
      render(<EditComponent {...props} />);

      const generateButton = screen.getByRole('button', { name: /generate/i });
      expect(generateButton).toBeInTheDocument();
      expect(generateButton).not.toBeDisabled();
    });

    test('should update prompt when input changes', async () => {
      const user = userEvent.setup();
      const props = createMockProps();
      render(<EditComponent {...props} />);

      const promptInput = screen.getByPlaceholderText(/enter your prompt/i);
      await user.type(promptInput, 'Test prompt');

      expect(mockSetAttributes).toHaveBeenCalledWith({ prompt: 'Test prompt' });
    });

    test('should update provider when selection changes', async () => {
      const user = userEvent.setup();
      const props = createMockProps();
      render(<EditComponent {...props} />);

      const providerSelect = screen.getByLabelText(/ai provider/i);
      await user.selectOptions(providerSelect, 'anthropic');

      expect(mockSetAttributes).toHaveBeenCalledWith({ provider: 'anthropic' });
    });

    test('should disable generate button when prompt is empty', () => {
      const props = createMockProps({ prompt: '' });
      render(<EditComponent {...props} />);

      const generateButton = screen.getByRole('button', { name: /generate/i });
      expect(generateButton).toBeDisabled();
    });

    test('should enable generate button when prompt is provided', () => {
      const props = createMockProps({ prompt: 'Test prompt' });
      render(<EditComponent {...props} />);

      const generateButton = screen.getByRole('button', { name: /generate/i });
      expect(generateButton).not.toBeDisabled();
    });
  });

  describe('Content Generation', () => {
    let EditComponent;
    let mockSetAttributes;

    beforeEach(() => {
      const blockConfig = mockRegisterBlockType.mock.calls[0][1];
      EditComponent = blockConfig.edit;
      mockSetAttributes = jest.fn();
    });

    const createMockProps = (attributes = {}) => ({
      attributes: {
        prompt: 'Test prompt',
        provider: 'openai',
        temperature: 0.7,
        maxTokens: 1000,
        generatedContent: '',
        isGenerating: false,
        ...attributes
      },
      setAttributes: mockSetAttributes,
      clientId: 'test-client-id',
      isSelected: true
    });

    test('should call API when generate button is clicked', async () => {
      const user = userEvent.setup();
      const props = createMockProps();
      render(<EditComponent {...props} />);

      const generateButton = screen.getByRole('button', { name: /generate/i });
      await user.click(generateButton);

      expect(mockApiRequest).toHaveBeenCalledWith({
        path: '/wp-content-flow/v1/ai/generate',
        method: 'POST',
        data: {
          prompt: 'Test prompt',
          provider: 'openai',
          temperature: 0.7,
          max_tokens: 1000
        }
      });
    });

    test('should show loading state during generation', async () => {
      const user = userEvent.setup();
      const props = createMockProps();
      
      // Mock API delay
      mockApiRequest.mockImplementation(() => 
        new Promise(resolve => setTimeout(() => resolve({
          success: true,
          data: { content: 'Generated content', usage: { tokens: 50 } }
        }), 100))
      );

      render(<EditComponent {...props} />);

      const generateButton = screen.getByRole('button', { name: /generate/i });
      await user.click(generateButton);

      // Check loading state is set
      expect(mockSetAttributes).toHaveBeenCalledWith({ isGenerating: true });
      
      // Check button shows loading state
      expect(screen.getByText(/generating/i)).toBeInTheDocument();
      expect(generateButton).toBeDisabled();
    });

    test('should display generated content on successful API response', async () => {
      const user = userEvent.setup();
      const props = createMockProps();
      render(<EditComponent {...props} />);

      const generateButton = screen.getByRole('button', { name: /generate/i });
      
      await act(async () => {
        await user.click(generateButton);
      });

      await waitFor(() => {
        expect(mockSetAttributes).toHaveBeenCalledWith({
          generatedContent: 'Generated AI content',
          isGenerating: false
        });
      });
    });

    test('should handle API errors gracefully', async () => {
      const user = userEvent.setup();
      const props = createMockProps();

      mockApiRequest.mockRejectedValueOnce(new Error('API Error'));
      
      render(<EditComponent {...props} />);

      const generateButton = screen.getByRole('button', { name: /generate/i });
      
      await act(async () => {
        await user.click(generateButton);
      });

      await waitFor(() => {
        expect(mockSetAttributes).toHaveBeenCalledWith({ isGenerating: false });
        expect(mockCreateNotice).toHaveBeenCalledWith(
          'error',
          'Failed to generate content. Please try again.'
        );
      });
    });

    test('should handle rate limiting errors', async () => {
      const user = userEvent.setup();
      const props = createMockProps();

      mockApiRequest.mockRejectedValueOnce({
        status: 429,
        message: 'Rate limit exceeded'
      });
      
      render(<EditComponent {...props} />);

      const generateButton = screen.getByRole('button', { name: /generate/i });
      
      await act(async () => {
        await user.click(generateButton);
      });

      await waitFor(() => {
        expect(mockCreateNotice).toHaveBeenCalledWith(
          'warning',
          'Rate limit exceeded. Please wait before trying again.'
        );
      });
    });
  });

  describe('Content Management', () => {
    let EditComponent;
    let mockSetAttributes;

    beforeEach(() => {
      const blockConfig = mockRegisterBlockType.mock.calls[0][1];
      EditComponent = blockConfig.edit;
      mockSetAttributes = jest.fn();
    });

    const createMockProps = (attributes = {}) => ({
      attributes: {
        prompt: 'Test prompt',
        provider: 'openai',
        generatedContent: 'This is generated AI content',
        isGenerating: false,
        ...attributes
      },
      setAttributes: mockSetAttributes,
      clientId: 'test-client-id',
      isSelected: true
    });

    test('should display generated content when available', () => {
      const props = createMockProps();
      render(<EditComponent {...props} />);

      const contentDisplay = screen.getByText('This is generated AI content');
      expect(contentDisplay).toBeInTheDocument();
    });

    test('should show accept and reject buttons for generated content', () => {
      const props = createMockProps();
      render(<EditComponent {...props} />);

      const acceptButton = screen.getByRole('button', { name: /accept/i });
      const rejectButton = screen.getByRole('button', { name: /reject/i });

      expect(acceptButton).toBeInTheDocument();
      expect(rejectButton).toBeInTheDocument();
    });

    test('should clear content when reject button is clicked', async () => {
      const user = userEvent.setup();
      const props = createMockProps();
      render(<EditComponent {...props} />);

      const rejectButton = screen.getByRole('button', { name: /reject/i });
      await user.click(rejectButton);

      expect(mockSetAttributes).toHaveBeenCalledWith({
        generatedContent: '',
        prompt: ''
      });
    });

    test('should create paragraph block when accept button is clicked', async () => {
      const user = userEvent.setup();
      const props = createMockProps();
      render(<EditComponent {...props} />);

      const acceptButton = screen.getByRole('button', { name: /accept/i });
      await user.click(acceptButton);

      expect(mockCreateBlock).toHaveBeenCalledWith(
        'core/paragraph',
        { content: 'This is generated AI content' }
      );
    });
  });

  describe('Block Settings Panel', () => {
    let EditComponent;
    let mockSetAttributes;

    beforeEach(() => {
      const blockConfig = mockRegisterBlockType.mock.calls[0][1];
      EditComponent = blockConfig.edit;
      mockSetAttributes = jest.fn();
    });

    const createMockProps = (attributes = {}) => ({
      attributes: {
        prompt: '',
        provider: 'openai',
        temperature: 0.7,
        maxTokens: 1000,
        ...attributes
      },
      setAttributes: mockSetAttributes,
      clientId: 'test-client-id',
      isSelected: true
    });

    test('should render temperature control in sidebar', () => {
      const props = createMockProps();
      render(<EditComponent {...props} />);

      const temperatureControl = screen.getByLabelText(/temperature/i);
      expect(temperatureControl).toBeInTheDocument();
      expect(temperatureControl).toHaveValue(0.7);
    });

    test('should render max tokens control in sidebar', () => {
      const props = createMockProps();
      render(<EditComponent {...props} />);

      const maxTokensControl = screen.getByLabelText(/max tokens/i);
      expect(maxTokensControl).toBeInTheDocument();
      expect(maxTokensControl).toHaveValue(1000);
    });

    test('should update temperature when control changes', async () => {
      const user = userEvent.setup();
      const props = createMockProps();
      render(<EditComponent {...props} />);

      const temperatureControl = screen.getByLabelText(/temperature/i);
      await user.clear(temperatureControl);
      await user.type(temperatureControl, '0.9');

      expect(mockSetAttributes).toHaveBeenCalledWith({ temperature: 0.9 });
    });

    test('should validate temperature range', async () => {
      const user = userEvent.setup();
      const props = createMockProps();
      render(<EditComponent {...props} />);

      const temperatureControl = screen.getByLabelText(/temperature/i);
      
      // Test maximum value
      await user.clear(temperatureControl);
      await user.type(temperatureControl, '2.5');
      
      expect(mockSetAttributes).toHaveBeenCalledWith({ temperature: 2.0 });
      
      // Test minimum value
      await user.clear(temperatureControl);
      await user.type(temperatureControl, '-0.5');
      
      expect(mockSetAttributes).toHaveBeenCalledWith({ temperature: 0.0 });
    });
  });

  describe('Save Function', () => {
    test('should return null for dynamic block', () => {
      const blockConfig = mockRegisterBlockType.mock.calls[0][1];
      const saveFunction = blockConfig.save;
      
      const result = saveFunction();
      expect(result).toBeNull();
    });
  });

  describe('Block Supports', () => {
    test('should define correct block supports', () => {
      const blockConfig = mockRegisterBlockType.mock.calls[0][1];
      const { supports } = blockConfig;

      expect(supports.html).toBe(false);
      expect(supports.align).toBe(true);
      expect(supports.anchor).toBe(true);
    });
  });

  describe('Accessibility', () => {
    let EditComponent;
    let mockSetAttributes;

    beforeEach(() => {
      const blockConfig = mockRegisterBlockType.mock.calls[0][1];
      EditComponent = blockConfig.edit;
      mockSetAttributes = jest.fn();
    });

    const createMockProps = (attributes = {}) => ({
      attributes: {
        prompt: '',
        provider: 'openai',
        temperature: 0.7,
        maxTokens: 1000,
        ...attributes
      },
      setAttributes: mockSetAttributes,
      clientId: 'test-client-id',
      isSelected: true
    });

    test('should have proper ARIA labels', () => {
      const props = createMockProps();
      render(<EditComponent {...props} />);

      expect(screen.getByLabelText(/prompt/i)).toBeInTheDocument();
      expect(screen.getByLabelText(/ai provider/i)).toBeInTheDocument();
      expect(screen.getByLabelText(/temperature/i)).toBeInTheDocument();
    });

    test('should provide screen reader feedback for generation state', () => {
      const props = createMockProps({ isGenerating: true });
      render(<EditComponent {...props} />);

      const status = screen.getByRole('status');
      expect(status).toHaveTextContent(/generating/i);
    });
  });

  describe('Keyboard Navigation', () => {
    let EditComponent;
    let mockSetAttributes;

    beforeEach(() => {
      const blockConfig = mockRegisterBlockType.mock.calls[0][1];
      EditComponent = blockConfig.edit;
      mockSetAttributes = jest.fn();
    });

    const createMockProps = (attributes = {}) => ({
      attributes: {
        prompt: 'Test prompt',
        provider: 'openai',
        generatedContent: 'Generated content',
        ...attributes
      },
      setAttributes: mockSetAttributes,
      clientId: 'test-client-id',
      isSelected: true
    });

    test('should support keyboard navigation between controls', () => {
      const props = createMockProps();
      render(<EditComponent {...props} />);

      const promptInput = screen.getByPlaceholderText(/enter your prompt/i);
      const generateButton = screen.getByRole('button', { name: /generate/i });
      const acceptButton = screen.getByRole('button', { name: /accept/i });

      // All interactive elements should be keyboard accessible
      expect(promptInput).not.toHaveAttribute('tabindex', '-1');
      expect(generateButton).not.toHaveAttribute('tabindex', '-1');
      expect(acceptButton).not.toHaveAttribute('tabindex', '-1');
    });

    test('should support Enter key for generation', async () => {
      const user = userEvent.setup();
      const props = createMockProps({ prompt: 'Test prompt' });
      render(<EditComponent {...props} />);

      const promptInput = screen.getByPlaceholderText(/enter your prompt/i);
      
      await user.click(promptInput);
      await user.keyboard('{Enter}');

      expect(mockApiRequest).toHaveBeenCalled();
    });
  });
});