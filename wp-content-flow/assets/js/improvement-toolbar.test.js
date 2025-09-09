/**
 * @jest-environment jsdom
 */

import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom';

// Mock WordPress dependencies
const mockApiRequest = jest.fn();
const mockCreateNotice = jest.fn();
const mockUseSelect = jest.fn();
const mockUseDispatch = jest.fn();

global.wp = {
  ...global.wp,
  apiFetch: mockApiRequest,
  notices: {
    ...global.wp.notices,
    createNotice: mockCreateNotice,
  },
  data: {
    ...global.wp.data,
    useSelect: mockUseSelect,
    useDispatch: mockUseDispatch,
  },
  blockEditor: {
    ...global.wp.blockEditor,
    BlockControls: ({ children }) => <div className="block-controls">{children}</div>,
    RichTextToolbarButton: ({ icon, title, onClick, isActive }) => (
      <button
        onClick={onClick}
        title={title}
        className={`toolbar-button ${isActive ? 'active' : ''}`}
        aria-pressed={isActive}
      >
        {icon} {title}
      </button>
    ),
  },
  components: {
    ...global.wp.components,
    ToolbarGroup: ({ children }) => <div className="toolbar-group">{children}</div>,
    ToolbarButton: ({ icon, label, onClick, isPressed }) => (
      <button
        onClick={onClick}
        aria-label={label}
        aria-pressed={isPressed}
        className={`toolbar-button ${isPressed ? 'pressed' : ''}`}
      >
        {icon} {label}
      </button>
    ),
    Popover: ({ children, isVisible, onClose }) => 
      isVisible ? (
        <div className="popover">
          <button onClick={onClose}>×</button>
          {children}
        </div>
      ) : null,
    Button: ({ children, onClick, variant, disabled, isPressed }) => (
      <button
        onClick={onClick}
        disabled={disabled}
        className={`button ${variant} ${isPressed ? 'pressed' : ''}`}
      >
        {children}
      </button>
    ),
    SelectControl: ({ label, value, onChange, options }) => (
      <div>
        <label>{label}</label>
        <select value={value || ''} onChange={(e) => onChange(e.target.value)}>
          {options.map(option => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      </div>
    ),
    TextareaControl: ({ label, value, onChange, rows }) => (
      <div>
        <label>{label}</label>
        <textarea
          value={value || ''}
          onChange={(e) => onChange(e.target.value)}
          rows={rows}
        />
      </div>
    ),
  },
};

// Mock improvement toolbar module
const mockImprovementToolbar = {
  init: jest.fn(),
  render: jest.fn(),
  addToBlock: jest.fn(),
  improveContent: jest.fn(),
  showSuggestions: jest.fn(),
  applySuggestion: jest.fn(),
  rejectSuggestion: jest.fn(),
};

jest.mock('./improvement-toolbar.js', () => mockImprovementToolbar);

describe('Improvement Toolbar Component', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    
    // Setup default API responses
    mockApiRequest.mockImplementation((config) => {
      if (config.path?.includes('/ai/improve')) {
        return Promise.resolve({
          success: true,
          data: {
            improved_content: 'This is improved content with better clarity and structure.',
            improvements: [
              { type: 'grammar', description: 'Fixed grammatical errors' },
              { type: 'clarity', description: 'Improved sentence structure' }
            ],
            confidence_score: 0.92,
            provider: 'openai'
          }
        });
      }
      return Promise.resolve({ success: true, data: {} });
    });

    // Setup WordPress data hooks
    mockUseSelect.mockReturnValue({
      selectedBlockClientId: 'block-123',
      selectedBlock: {
        name: 'core/paragraph',
        attributes: { content: 'Original content text.' },
      },
      hasSelectedText: false,
    });

    mockUseDispatch.mockReturnValue({
      updateBlockAttributes: jest.fn(),
      createNotice: mockCreateNotice,
    });
  });

  describe('Toolbar Integration', () => {
    test('should add improvement toolbar to text blocks', () => {
      const ImprovementToolbar = mockImprovementToolbar.render;
      
      const blockProps = {
        name: 'core/paragraph',
        attributes: { content: 'Test content' },
        clientId: 'block-123',
      };
      
      render(<ImprovementToolbar {...blockProps} />);

      expect(screen.getByRole('button', { name: /improve content/i })).toBeInTheDocument();
    });

    test('should not add toolbar to non-text blocks', () => {
      const ImprovementToolbar = mockImprovementToolbar.render;
      
      const blockProps = {
        name: 'core/image',
        attributes: { url: 'image.jpg' },
        clientId: 'block-456',
      };
      
      render(<ImprovementToolbar {...blockProps} />);

      expect(screen.queryByRole('button', { name: /improve content/i })).not.toBeInTheDocument();
    });

    test('should show toolbar only when block has content', () => {
      const ImprovementToolbar = mockImprovementToolbar.render;
      
      const emptyBlockProps = {
        name: 'core/paragraph',
        attributes: { content: '' },
        clientId: 'block-789',
      };
      
      render(<ImprovementToolbar {...emptyBlockProps} />);

      expect(screen.queryByRole('button', { name: /improve content/i })).not.toBeInTheDocument();
    });

    test('should integrate with block editor toolbar', () => {
      const ImprovementToolbar = mockImprovementToolbar.render;
      
      const blockProps = {
        name: 'core/paragraph',
        attributes: { content: 'Test content' },
        clientId: 'block-123',
      };
      
      render(<ImprovementToolbar {...blockProps} />);

      expect(screen.getByClassName('block-controls')).toBeInTheDocument();
      expect(screen.getByClassName('toolbar-group')).toBeInTheDocument();
    });
  });

  describe('Improvement Options', () => {
    test('should show improvement type selector when toolbar clicked', async () => {
      const user = userEvent.setup();
      const ImprovementToolbar = mockImprovementToolbar.render;
      
      const blockProps = {
        name: 'core/paragraph',
        attributes: { content: 'Test content' },
        clientId: 'block-123',
      };
      
      render(<ImprovementToolbar {...blockProps} />);

      const improveButton = screen.getByRole('button', { name: /improve content/i });
      await user.click(improveButton);

      expect(screen.getByText(/improvement type/i)).toBeInTheDocument();
      expect(screen.getByDisplayValue('grammar')).toBeInTheDocument();
      expect(screen.getByText('Clarity')).toBeInTheDocument();
      expect(screen.getByText('Style')).toBeInTheDocument();
      expect(screen.getByText('Tone')).toBeInTheDocument();
    });

    test('should allow selection of improvement type', async () => {
      const user = userEvent.setup();
      const ImprovementToolbar = mockImprovementToolbar.render;
      
      const blockProps = {
        name: 'core/paragraph',
        attributes: { content: 'Test content' },
        clientId: 'block-123',
      };
      
      render(<ImprovementToolbar {...blockProps} />);

      const improveButton = screen.getByRole('button', { name: /improve content/i });
      await user.click(improveButton);

      const typeSelector = screen.getByLabelText(/improvement type/i);
      await user.selectOptions(typeSelector, 'clarity');

      expect(typeSelector).toHaveValue('clarity');
    });

    test('should show custom instruction field for custom improvements', async () => {
      const user = userEvent.setup();
      const ImprovementToolbar = mockImprovementToolbar.render;
      
      const blockProps = {
        name: 'core/paragraph',
        attributes: { content: 'Test content' },
        clientId: 'block-123',
      };
      
      render(<ImprovementToolbar {...blockProps} />);

      const improveButton = screen.getByRole('button', { name: /improve content/i });
      await user.click(improveButton);

      const typeSelector = screen.getByLabelText(/improvement type/i);
      await user.selectOptions(typeSelector, 'custom');

      expect(screen.getByLabelText(/custom instructions/i)).toBeInTheDocument();
    });

    test('should validate custom instructions', async () => {
      const user = userEvent.setup();
      const ImprovementToolbar = mockImprovementToolbar.render;
      
      const blockProps = {
        name: 'core/paragraph',
        attributes: { content: 'Test content' },
        clientId: 'block-123',
      };
      
      render(<ImprovementToolbar {...blockProps} />);

      const improveButton = screen.getByRole('button', { name: /improve content/i });
      await user.click(improveButton);

      const typeSelector = screen.getByLabelText(/improvement type/i);
      await user.selectOptions(typeSelector, 'custom');

      const applyButton = screen.getByRole('button', { name: /apply improvement/i });
      await user.click(applyButton);

      expect(screen.getByText(/custom instructions are required/i)).toBeInTheDocument();
    });
  });

  describe('Content Improvement Process', () => {
    test('should call API with correct parameters for improvement', async () => {
      const user = userEvent.setup();
      const ImprovementToolbar = mockImprovementToolbar.render;
      
      const blockProps = {
        name: 'core/paragraph',
        attributes: { content: 'Test content that needs improvement.' },
        clientId: 'block-123',
      };
      
      render(<ImprovementToolbar {...blockProps} />);

      const improveButton = screen.getByRole('button', { name: /improve content/i });
      await user.click(improveButton);

      const applyButton = screen.getByRole('button', { name: /apply improvement/i });
      await user.click(applyButton);

      await waitFor(() => {
        expect(mockApiRequest).toHaveBeenCalledWith({
          path: '/wp-content-flow/v1/ai/improve',
          method: 'POST',
          data: {
            content: 'Test content that needs improvement.',
            improvement_type: 'grammar',
            provider: expect.any(String),
            block_type: 'core/paragraph',
          },
        });
      });
    });

    test('should show loading state during improvement', async () => {
      const user = userEvent.setup();
      const ImprovementToolbar = mockImprovementToolbar.render;
      
      // Mock API delay
      mockApiRequest.mockImplementationOnce(() => 
        new Promise(resolve => setTimeout(() => resolve({
          success: true,
          data: { improved_content: 'Improved content' }
        }), 100))
      );
      
      const blockProps = {
        name: 'core/paragraph',
        attributes: { content: 'Test content' },
        clientId: 'block-123',
      };
      
      render(<ImprovementToolbar {...blockProps} />);

      const improveButton = screen.getByRole('button', { name: /improve content/i });
      await user.click(improveButton);

      const applyButton = screen.getByRole('button', { name: /apply improvement/i });
      await user.click(applyButton);

      expect(screen.getByText(/improving content/i)).toBeInTheDocument();
      expect(applyButton).toBeDisabled();
    });

    test('should display improved content preview', async () => {
      const user = userEvent.setup();
      const ImprovementToolbar = mockImprovementToolbar.render;
      
      const blockProps = {
        name: 'core/paragraph',
        attributes: { content: 'Original content.' },
        clientId: 'block-123',
      };
      
      render(<ImprovementToolbar {...blockProps} />);

      const improveButton = screen.getByRole('button', { name: /improve content/i });
      await user.click(improveButton);

      const applyButton = screen.getByRole('button', { name: /apply improvement/i });
      await user.click(applyButton);

      await waitFor(() => {
        expect(screen.getByText(/improved content preview/i)).toBeInTheDocument();
        expect(screen.getByText(/this is improved content with better clarity/i)).toBeInTheDocument();
      });
    });

    test('should show confidence score for improvements', async () => {
      const user = userEvent.setup();
      const ImprovementToolbar = mockImprovementToolbar.render;
      
      const blockProps = {
        name: 'core/paragraph',
        attributes: { content: 'Original content.' },
        clientId: 'block-123',
      };
      
      render(<ImprovementToolbar {...blockProps} />);

      const improveButton = screen.getByRole('button', { name: /improve content/i });
      await user.click(improveButton);

      const applyButton = screen.getByRole('button', { name: /apply improvement/i });
      await user.click(applyButton);

      await waitFor(() => {
        expect(screen.getByText(/confidence: 92%/i)).toBeInTheDocument();
      });
    });

    test('should list specific improvements made', async () => {
      const user = userEvent.setup();
      const ImprovementToolbar = mockImprovementToolbar.render;
      
      const blockProps = {
        name: 'core/paragraph',
        attributes: { content: 'Original content.' },
        clientId: 'block-123',
      };
      
      render(<ImprovementToolbar {...blockProps} />);

      const improveButton = screen.getByRole('button', { name: /improve content/i });
      await user.click(improveButton);

      const applyButton = screen.getByRole('button', { name: /apply improvement/i });
      await user.click(applyButton);

      await waitFor(() => {
        expect(screen.getByText(/improvements made/i)).toBeInTheDocument();
        expect(screen.getByText(/fixed grammatical errors/i)).toBeInTheDocument();
        expect(screen.getByText(/improved sentence structure/i)).toBeInTheDocument();
      });
    });
  });

  describe('Content Replacement', () => {
    test('should apply improved content when accepted', async () => {
      const mockUpdateBlock = jest.fn();
      mockUseDispatch.mockReturnValue({
        updateBlockAttributes: mockUpdateBlock,
        createNotice: mockCreateNotice,
      });

      const user = userEvent.setup();
      const ImprovementToolbar = mockImprovementToolbar.render;
      
      const blockProps = {
        name: 'core/paragraph',
        attributes: { content: 'Original content.' },
        clientId: 'block-123',
      };
      
      render(<ImprovementToolbar {...blockProps} />);

      const improveButton = screen.getByRole('button', { name: /improve content/i });
      await user.click(improveButton);

      const applyButton = screen.getByRole('button', { name: /apply improvement/i });
      await user.click(applyButton);

      await waitFor(() => {
        expect(screen.getByText(/this is improved content/i)).toBeInTheDocument();
      });

      const acceptButton = screen.getByRole('button', { name: /accept/i });
      await user.click(acceptButton);

      expect(mockUpdateBlock).toHaveBeenCalledWith(
        'block-123',
        { content: 'This is improved content with better clarity and structure.' }
      );
    });

    test('should keep original content when rejected', async () => {
      const mockUpdateBlock = jest.fn();
      mockUseDispatch.mockReturnValue({
        updateBlockAttributes: mockUpdateBlock,
        createNotice: mockCreateNotice,
      });

      const user = userEvent.setup();
      const ImprovementToolbar = mockImprovementToolbar.render;
      
      const blockProps = {
        name: 'core/paragraph',
        attributes: { content: 'Original content.' },
        clientId: 'block-123',
      };
      
      render(<ImprovementToolbar {...blockProps} />);

      const improveButton = screen.getByRole('button', { name: /improve content/i });
      await user.click(improveButton);

      const applyButton = screen.getByRole('button', { name: /apply improvement/i });
      await user.click(applyButton);

      await waitFor(() => {
        expect(screen.getByText(/this is improved content/i)).toBeInTheDocument();
      });

      const rejectButton = screen.getByRole('button', { name: /reject/i });
      await user.click(rejectButton);

      expect(mockUpdateBlock).not.toHaveBeenCalled();
      expect(screen.queryByText(/improved content preview/i)).not.toBeInTheDocument();
    });

    test('should show success message after accepting improvement', async () => {
      const user = userEvent.setup();
      const ImprovementToolbar = mockImprovementToolbar.render;
      
      const blockProps = {
        name: 'core/paragraph',
        attributes: { content: 'Original content.' },
        clientId: 'block-123',
      };
      
      render(<ImprovementToolbar {...blockProps} />);

      const improveButton = screen.getByRole('button', { name: /improve content/i });
      await user.click(improveButton);

      const applyButton = screen.getByRole('button', { name: /apply improvement/i });
      await user.click(applyButton);

      await waitFor(() => {
        expect(screen.getByText(/this is improved content/i)).toBeInTheDocument();
      });

      const acceptButton = screen.getByRole('button', { name: /accept/i });
      await user.click(acceptButton);

      expect(mockCreateNotice).toHaveBeenCalledWith(
        'success',
        'Content improved successfully'
      );
    });
  });

  describe('Error Handling', () => {
    test('should handle API errors gracefully', async () => {
      const user = userEvent.setup();
      mockApiRequest.mockRejectedValueOnce({
        message: 'API rate limit exceeded'
      });

      const ImprovementToolbar = mockImprovementToolbar.render;
      
      const blockProps = {
        name: 'core/paragraph',
        attributes: { content: 'Test content' },
        clientId: 'block-123',
      };
      
      render(<ImprovementToolbar {...blockProps} />);

      const improveButton = screen.getByRole('button', { name: /improve content/i });
      await user.click(improveButton);

      const applyButton = screen.getByRole('button', { name: /apply improvement/i });
      await user.click(applyButton);

      await waitFor(() => {
        expect(mockCreateNotice).toHaveBeenCalledWith(
          'error',
          'Failed to improve content: API rate limit exceeded'
        );
      });

      expect(screen.queryByText(/improving content/i)).not.toBeInTheDocument();
    });

    test('should handle network errors', async () => {
      const user = userEvent.setup();
      mockApiRequest.mockRejectedValueOnce(new Error('Network error'));

      const ImprovementToolbar = mockImprovementToolbar.render;
      
      const blockProps = {
        name: 'core/paragraph',
        attributes: { content: 'Test content' },
        clientId: 'block-123',
      };
      
      render(<ImprovementToolbar {...blockProps} />);

      const improveButton = screen.getByRole('button', { name: /improve content/i });
      await user.click(improveButton);

      const applyButton = screen.getByRole('button', { name: /apply improvement/i });
      await user.click(applyButton);

      await waitFor(() => {
        expect(mockCreateNotice).toHaveBeenCalledWith(
          'error',
          'Network error. Please check your connection and try again.'
        );
      });
    });

    test('should handle empty API responses', async () => {
      const user = userEvent.setup();
      mockApiRequest.mockResolvedValueOnce({
        success: true,
        data: { improved_content: '' }
      });

      const ImprovementToolbar = mockImprovementToolbar.render;
      
      const blockProps = {
        name: 'core/paragraph',
        attributes: { content: 'Test content' },
        clientId: 'block-123',
      };
      
      render(<ImprovementToolbar {...blockProps} />);

      const improveButton = screen.getByRole('button', { name: /improve content/i });
      await user.click(improveButton);

      const applyButton = screen.getByRole('button', { name: /apply improvement/i });
      await user.click(applyButton);

      await waitFor(() => {
        expect(mockCreateNotice).toHaveBeenCalledWith(
          'warning',
          'No improvements could be made to this content'
        );
      });
    });
  });

  describe('Selected Text Improvements', () => {
    test('should work with selected text instead of full block', async () => {
      const user = userEvent.setup();
      
      mockUseSelect.mockReturnValue({
        selectedBlockClientId: 'block-123',
        selectedBlock: {
          name: 'core/paragraph',
          attributes: { content: 'This is some text with a selected portion.' },
        },
        hasSelectedText: true,
        selectedText: 'selected portion',
      });

      const ImprovementToolbar = mockImprovementToolbar.render;
      
      const blockProps = {
        name: 'core/paragraph',
        attributes: { content: 'This is some text with a selected portion.' },
        clientId: 'block-123',
      };
      
      render(<ImprovementToolbar {...blockProps} />);

      const improveButton = screen.getByRole('button', { name: /improve content/i });
      await user.click(improveButton);

      expect(screen.getByText(/selected text: "selected portion"/i)).toBeInTheDocument();

      const applyButton = screen.getByRole('button', { name: /apply improvement/i });
      await user.click(applyButton);

      await waitFor(() => {
        expect(mockApiRequest).toHaveBeenCalledWith({
          path: '/wp-content-flow/v1/ai/improve',
          method: 'POST',
          data: expect.objectContaining({
            content: 'selected portion',
            improvement_type: 'grammar',
          }),
        });
      });
    });

    test('should replace only selected text when accepted', async () => {
      const mockUpdateBlock = jest.fn();
      mockUseDispatch.mockReturnValue({
        updateBlockAttributes: mockUpdateBlock,
        createNotice: mockCreateNotice,
      });

      mockUseSelect.mockReturnValue({
        selectedBlockClientId: 'block-123',
        selectedBlock: {
          name: 'core/paragraph',
          attributes: { content: 'This is some text with a selected portion.' },
        },
        hasSelectedText: true,
        selectedText: 'selected portion',
        selectionStart: 25,
        selectionEnd: 40,
      });

      mockApiRequest.mockResolvedValueOnce({
        success: true,
        data: {
          improved_content: 'highlighted section',
          improvements: [],
          confidence_score: 0.85
        }
      });

      const user = userEvent.setup();
      const ImprovementToolbar = mockImprovementToolbar.render;
      
      const blockProps = {
        name: 'core/paragraph',
        attributes: { content: 'This is some text with a selected portion.' },
        clientId: 'block-123',
      };
      
      render(<ImprovementToolbar {...blockProps} />);

      const improveButton = screen.getByRole('button', { name: /improve content/i });
      await user.click(improveButton);

      const applyButton = screen.getByRole('button', { name: /apply improvement/i });
      await user.click(applyButton);

      await waitFor(() => {
        expect(screen.getByText(/highlighted section/i)).toBeInTheDocument();
      });

      const acceptButton = screen.getByRole('button', { name: /accept/i });
      await user.click(acceptButton);

      expect(mockUpdateBlock).toHaveBeenCalledWith(
        'block-123',
        { content: 'This is some text with a highlighted section.' }
      );
    });
  });

  describe('Provider Selection', () => {
    test('should allow AI provider selection', async () => {
      const user = userEvent.setup();
      const ImprovementToolbar = mockImprovementToolbar.render;
      
      const blockProps = {
        name: 'core/paragraph',
        attributes: { content: 'Test content' },
        clientId: 'block-123',
      };
      
      render(<ImprovementToolbar {...blockProps} />);

      const improveButton = screen.getByRole('button', { name: /improve content/i });
      await user.click(improveButton);

      const providerSelect = screen.getByLabelText(/ai provider/i);
      await user.selectOptions(providerSelect, 'anthropic');

      expect(providerSelect).toHaveValue('anthropic');
    });

    test('should use selected provider in API call', async () => {
      const user = userEvent.setup();
      const ImprovementToolbar = mockImprovementToolbar.render;
      
      const blockProps = {
        name: 'core/paragraph',
        attributes: { content: 'Test content' },
        clientId: 'block-123',
      };
      
      render(<ImprovementToolbar {...blockProps} />);

      const improveButton = screen.getByRole('button', { name: /improve content/i });
      await user.click(improveButton);

      const providerSelect = screen.getByLabelText(/ai provider/i);
      await user.selectOptions(providerSelect, 'anthropic');

      const applyButton = screen.getByRole('button', { name: /apply improvement/i });
      await user.click(applyButton);

      await waitFor(() => {
        expect(mockApiRequest).toHaveBeenCalledWith(
          expect.objectContaining({
            data: expect.objectContaining({
              provider: 'anthropic',
            }),
          })
        );
      });
    });
  });

  describe('Accessibility', () => {
    test('should have proper ARIA labels and roles', () => {
      const ImprovementToolbar = mockImprovementToolbar.render;
      
      const blockProps = {
        name: 'core/paragraph',
        attributes: { content: 'Test content' },
        clientId: 'block-123',
      };
      
      render(<ImprovementToolbar {...blockProps} />);

      const improveButton = screen.getByRole('button', { name: /improve content/i });
      expect(improveButton).toHaveAttribute('aria-label');
      expect(improveButton).not.toHaveAttribute('aria-pressed', 'true');
    });

    test('should provide screen reader feedback for loading states', async () => {
      const user = userEvent.setup();
      
      mockApiRequest.mockImplementationOnce(() => 
        new Promise(resolve => setTimeout(resolve, 100))
      );

      const ImprovementToolbar = mockImprovementToolbar.render;
      
      const blockProps = {
        name: 'core/paragraph',
        attributes: { content: 'Test content' },
        clientId: 'block-123',
      };
      
      render(<ImprovementToolbar {...blockProps} />);

      const improveButton = screen.getByRole('button', { name: /improve content/i });
      await user.click(improveButton);

      const applyButton = screen.getByRole('button', { name: /apply improvement/i });
      await user.click(applyButton);

      const statusElement = screen.getByRole('status');
      expect(statusElement).toHaveTextContent(/improving content/i);
    });

    test('should support keyboard navigation', async () => {
      const user = userEvent.setup();
      const ImprovementToolbar = mockImprovementToolbar.render;
      
      const blockProps = {
        name: 'core/paragraph',
        attributes: { content: 'Test content' },
        clientId: 'block-123',
      };
      
      render(<ImprovementToolbar {...blockProps} />);

      const improveButton = screen.getByRole('button', { name: /improve content/i });
      
      // Should be keyboard accessible
      improveButton.focus();
      expect(improveButton).toHaveFocus();
      
      // Should activate with Enter key
      fireEvent.keyDown(improveButton, { key: 'Enter', code: 'Enter' });
      
      expect(screen.getByText(/improvement type/i)).toBeInTheDocument();
    });
  });
});