/**
 * Unit Tests: AI Text Generator Block
 * 
 * Tests the AI text generator Gutenberg block functionality
 * including content generation, UI interactions, and API calls.
 */

import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom';
import { AITextGenerator } from '../blocks/ai-text-generator';

// Mock WordPress dependencies
global.wp = {
  blocks: {
    registerBlockType: jest.fn(),
  },
  blockEditor: {
    RichText: jest.fn(({ value, onChange }) => (
      <div 
        contentEditable
        onInput={(e) => onChange(e.target.textContent)}
      >
        {value}
      </div>
    )),
    InspectorControls: jest.fn(({ children }) => <div>{children}</div>),
    BlockControls: jest.fn(({ children }) => <div>{children}</div>),
  },
  components: {
    Panel: jest.fn(({ children }) => <div>{children}</div>),
    PanelBody: jest.fn(({ children, title }) => (
      <div>
        <h3>{title}</h3>
        {children}
      </div>
    )),
    SelectControl: jest.fn(({ label, value, onChange, options }) => (
      <div>
        <label>{label}</label>
        <select value={value} onChange={(e) => onChange(e.target.value)}>
          {options.map((opt) => (
            <option key={opt.value} value={opt.value}>
              {opt.label}
            </option>
          ))}
        </select>
      </div>
    )),
    TextControl: jest.fn(({ label, value, onChange }) => (
      <div>
        <label>{label}</label>
        <input 
          type="text" 
          value={value} 
          onChange={(e) => onChange(e.target.value)} 
        />
      </div>
    )),
    Button: jest.fn(({ children, onClick, isPrimary, isBusy }) => (
      <button 
        onClick={onClick} 
        className={isPrimary ? 'is-primary' : ''}
        disabled={isBusy}
      >
        {isBusy ? 'Loading...' : children}
      </button>
    )),
    Spinner: jest.fn(() => <div>Loading...</div>),
  },
  data: {
    select: jest.fn(() => ({
      getSettings: jest.fn(() => ({
        ai_provider: 'openai',
        openai_model: 'gpt-3.5-turbo',
      })),
    })),
  },
  apiFetch: jest.fn(),
  i18n: {
    __: jest.fn((text) => text),
  },
  element: {
    useState: jest.fn((initial) => {
      const state = { current: initial };
      return [
        state.current,
        (newValue) => {
          state.current = newValue;
        },
      ];
    }),
    useEffect: jest.fn((callback, deps) => callback()),
  },
};

describe('AI Text Generator Block', () => {
  let mockProps;

  beforeEach(() => {
    mockProps = {
      attributes: {
        content: '',
        prompt: '',
        aiProvider: 'openai',
        model: 'gpt-3.5-turbo',
        maxTokens: 500,
        temperature: 0.7,
      },
      setAttributes: jest.fn(),
      isSelected: true,
    };

    // Reset mocks
    jest.clearAllMocks();
  });

  describe('Rendering', () => {
    test('renders the AI text generator block', () => {
      render(<AITextGenerator {...mockProps} />);
      
      expect(screen.getByText(/AI Text Generator/i)).toBeInTheDocument();
    });

    test('displays existing content', () => {
      mockProps.attributes.content = 'Existing content';
      render(<AITextGenerator {...mockProps} />);
      
      expect(screen.getByText('Existing content')).toBeInTheDocument();
    });

    test('shows inspector controls when selected', () => {
      render(<AITextGenerator {...mockProps} />);
      
      expect(screen.getByText('AI Settings')).toBeInTheDocument();
      expect(screen.getByText('AI Provider')).toBeInTheDocument();
      expect(screen.getByText('Model')).toBeInTheDocument();
    });
  });

  describe('Content Generation', () => {
    test('generates content when prompt is provided', async () => {
      const mockResponse = {
        success: true,
        content: 'Generated content about AI',
        tokens_used: 150,
      };

      global.wp.apiFetch.mockResolvedValue(mockResponse);

      render(<AITextGenerator {...mockProps} />);
      
      // Enter prompt
      const promptInput = screen.getByLabelText('Prompt');
      await userEvent.type(promptInput, 'Write about AI');
      
      // Click generate button
      const generateButton = screen.getByText('Generate Content');
      fireEvent.click(generateButton);
      
      // Wait for API call
      await waitFor(() => {
        expect(global.wp.apiFetch).toHaveBeenCalledWith({
          path: '/wp-content-flow/v1/ai/generate',
          method: 'POST',
          data: expect.objectContaining({
            prompt: 'Write about AI',
            provider: 'openai',
            model: 'gpt-3.5-turbo',
          }),
        });
      });
      
      // Check content was updated
      await waitFor(() => {
        expect(mockProps.setAttributes).toHaveBeenCalledWith({
          content: 'Generated content about AI',
        });
      });
    });

    test('handles generation errors gracefully', async () => {
      const mockError = {
        success: false,
        message: 'API rate limit exceeded',
      };

      global.wp.apiFetch.mockRejectedValue(mockError);

      render(<AITextGenerator {...mockProps} />);
      
      // Enter prompt and generate
      const promptInput = screen.getByLabelText('Prompt');
      await userEvent.type(promptInput, 'Test prompt');
      
      const generateButton = screen.getByText('Generate Content');
      fireEvent.click(generateButton);
      
      // Wait for error message
      await waitFor(() => {
        expect(screen.getByText(/API rate limit exceeded/i)).toBeInTheDocument();
      });
    });

    test('shows loading state during generation', async () => {
      global.wp.apiFetch.mockImplementation(
        () => new Promise((resolve) => setTimeout(resolve, 1000))
      );

      render(<AITextGenerator {...mockProps} />);
      
      const generateButton = screen.getByText('Generate Content');
      fireEvent.click(generateButton);
      
      // Check loading state
      expect(screen.getByText('Loading...')).toBeInTheDocument();
      expect(generateButton).toBeDisabled();
    });
  });

  describe('AI Provider Selection', () => {
    test('switches between AI providers', async () => {
      render(<AITextGenerator {...mockProps} />);
      
      const providerSelect = screen.getByLabelText('AI Provider');
      
      // Switch to Anthropic
      fireEvent.change(providerSelect, { target: { value: 'anthropic' } });
      
      expect(mockProps.setAttributes).toHaveBeenCalledWith({
        aiProvider: 'anthropic',
      });
      
      // Switch to Google AI
      fireEvent.change(providerSelect, { target: { value: 'google' } });
      
      expect(mockProps.setAttributes).toHaveBeenCalledWith({
        aiProvider: 'google',
      });
    });

    test('updates available models when provider changes', async () => {
      const { rerender } = render(<AITextGenerator {...mockProps} />);
      
      // Change to Anthropic
      mockProps.attributes.aiProvider = 'anthropic';
      rerender(<AITextGenerator {...mockProps} />);
      
      const modelSelect = screen.getByLabelText('Model');
      const options = modelSelect.querySelectorAll('option');
      
      // Should have Anthropic models
      expect(options).toHaveLength(2); // claude-3 and claude-instant
    });
  });

  describe('Settings Controls', () => {
    test('adjusts max tokens setting', async () => {
      render(<AITextGenerator {...mockProps} />);
      
      const maxTokensInput = screen.getByLabelText('Max Tokens');
      
      await userEvent.clear(maxTokensInput);
      await userEvent.type(maxTokensInput, '1000');
      
      expect(mockProps.setAttributes).toHaveBeenCalledWith({
        maxTokens: 1000,
      });
    });

    test('adjusts temperature setting', async () => {
      render(<AITextGenerator {...mockProps} />);
      
      const temperatureInput = screen.getByLabelText('Temperature');
      
      await userEvent.clear(temperatureInput);
      await userEvent.type(temperatureInput, '0.5');
      
      expect(mockProps.setAttributes).toHaveBeenCalledWith({
        temperature: 0.5,
      });
    });

    test('validates temperature range', async () => {
      render(<AITextGenerator {...mockProps} />);
      
      const temperatureInput = screen.getByLabelText('Temperature');
      
      // Try invalid value
      await userEvent.clear(temperatureInput);
      await userEvent.type(temperatureInput, '3.0');
      
      // Should be clamped to max value
      expect(mockProps.setAttributes).toHaveBeenCalledWith({
        temperature: 2.0,
      });
    });
  });

  describe('Content Improvement', () => {
    test('improves existing content', async () => {
      mockProps.attributes.content = 'Original content';
      
      const mockResponse = {
        success: true,
        improved_content: 'Improved and enhanced content',
      };

      global.wp.apiFetch.mockResolvedValue(mockResponse);

      render(<AITextGenerator {...mockProps} />);
      
      const improveButton = screen.getByText('Improve Content');
      fireEvent.click(improveButton);
      
      await waitFor(() => {
        expect(global.wp.apiFetch).toHaveBeenCalledWith({
          path: '/wp-content-flow/v1/ai/improve',
          method: 'POST',
          data: expect.objectContaining({
            content: 'Original content',
            improvement_type: 'clarity',
          }),
        });
      });
      
      await waitFor(() => {
        expect(mockProps.setAttributes).toHaveBeenCalledWith({
          content: 'Improved and enhanced content',
        });
      });
    });

    test('disables improve button when no content', () => {
      mockProps.attributes.content = '';
      render(<AITextGenerator {...mockProps} />);
      
      const improveButton = screen.getByText('Improve Content');
      expect(improveButton).toBeDisabled();
    });
  });

  describe('Workflow Integration', () => {
    test('loads and applies workflow', async () => {
      const mockWorkflow = {
        id: 1,
        name: 'Blog Post Workflow',
        steps: [
          { type: 'generate', prompt: 'Write introduction' },
          { type: 'improve', improvement_type: 'clarity' },
        ],
      };

      global.wp.apiFetch.mockResolvedValue(mockWorkflow);

      render(<AITextGenerator {...mockProps} />);
      
      const workflowSelect = screen.getByLabelText('Select Workflow');
      fireEvent.change(workflowSelect, { target: { value: '1' } });
      
      await waitFor(() => {
        expect(global.wp.apiFetch).toHaveBeenCalledWith({
          path: '/wp-content-flow/v1/workflows/1',
        });
      });
      
      // Should apply workflow settings
      expect(mockProps.setAttributes).toHaveBeenCalledWith(
        expect.objectContaining({
          workflowId: 1,
        })
      );
    });

    test('executes workflow steps sequentially', async () => {
      mockProps.attributes.workflowId = 1;
      mockProps.attributes.workflowSteps = [
        { type: 'generate', prompt: 'Step 1' },
        { type: 'improve', improvement_type: 'tone' },
      ];

      global.wp.apiFetch
        .mockResolvedValueOnce({ success: true, content: 'Step 1 result' })
        .mockResolvedValueOnce({ success: true, improved_content: 'Final result' });

      render(<AITextGenerator {...mockProps} />);
      
      const executeButton = screen.getByText('Execute Workflow');
      fireEvent.click(executeButton);
      
      // Should execute both steps
      await waitFor(() => {
        expect(global.wp.apiFetch).toHaveBeenCalledTimes(2);
      });
      
      // Final content should be set
      await waitFor(() => {
        expect(mockProps.setAttributes).toHaveBeenCalledWith({
          content: 'Final result',
        });
      });
    });
  });

  describe('History and Undo', () => {
    test('maintains content history', async () => {
      const { rerender } = render(<AITextGenerator {...mockProps} />);
      
      // Generate content multiple times
      mockProps.setAttributes.mockImplementation((attrs) => {
        if (attrs.content) {
          mockProps.attributes.content = attrs.content;
        }
      });
      
      // First generation
      mockProps.attributes.content = 'Version 1';
      rerender(<AITextGenerator {...mockProps} />);
      
      // Second generation
      mockProps.attributes.content = 'Version 2';
      rerender(<AITextGenerator {...mockProps} />);
      
      // Should be able to undo
      const undoButton = screen.getByText('Undo');
      expect(undoButton).not.toBeDisabled();
      
      fireEvent.click(undoButton);
      
      expect(mockProps.setAttributes).toHaveBeenCalledWith({
        content: 'Version 1',
      });
    });

    test('disables undo when no history', () => {
      render(<AITextGenerator {...mockProps} />);
      
      const undoButton = screen.getByText('Undo');
      expect(undoButton).toBeDisabled();
    });
  });

  describe('Accessibility', () => {
    test('has proper ARIA labels', () => {
      render(<AITextGenerator {...mockProps} />);
      
      expect(screen.getByLabelText('Prompt')).toBeInTheDocument();
      expect(screen.getByLabelText('AI Provider')).toBeInTheDocument();
      expect(screen.getByLabelText('Model')).toBeInTheDocument();
    });

    test('supports keyboard navigation', async () => {
      render(<AITextGenerator {...mockProps} />);
      
      const promptInput = screen.getByLabelText('Prompt');
      const generateButton = screen.getByText('Generate Content');
      
      // Tab to prompt input
      promptInput.focus();
      expect(document.activeElement).toBe(promptInput);
      
      // Type and press Enter
      await userEvent.type(promptInput, 'Test prompt{enter}');
      
      // Should trigger generation
      expect(global.wp.apiFetch).toHaveBeenCalled();
    });
  });

  describe('Error Handling', () => {
    test('displays network error messages', async () => {
      global.wp.apiFetch.mockRejectedValue(new Error('Network error'));
      
      render(<AITextGenerator {...mockProps} />);
      
      const generateButton = screen.getByText('Generate Content');
      fireEvent.click(generateButton);
      
      await waitFor(() => {
        expect(screen.getByText(/Network error/i)).toBeInTheDocument();
      });
    });

    test('handles invalid API responses', async () => {
      global.wp.apiFetch.mockResolvedValue({
        success: false,
        error: 'Invalid API key',
      });
      
      render(<AITextGenerator {...mockProps} />);
      
      const generateButton = screen.getByText('Generate Content');
      fireEvent.click(generateButton);
      
      await waitFor(() => {
        expect(screen.getByText(/Invalid API key/i)).toBeInTheDocument();
      });
    });
  });
});