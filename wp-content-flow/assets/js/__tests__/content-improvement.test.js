/**
 * Unit Tests: Content Improvement Features
 * 
 * Tests the content improvement functionality including
 * text selection, improvement suggestions, and toolbar interactions.
 */

import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom';
import { 
  ContentImprovementToolbar, 
  ImprovementSuggestions,
  TextSelectionHandler 
} from '../components/content-improvement';

// Mock WordPress dependencies
global.wp = {
  apiFetch: jest.fn(),
  i18n: {
    __: jest.fn((text) => text),
  },
  data: {
    dispatch: jest.fn(() => ({
      createNotice: jest.fn(),
    })),
  },
};

// Mock window selection API
global.window.getSelection = jest.fn(() => ({
  toString: jest.fn(() => 'Selected text for improvement'),
  getRangeAt: jest.fn(() => ({
    getBoundingClientRect: jest.fn(() => ({
      top: 100,
      left: 200,
      width: 300,
      height: 50,
    })),
  })),
  rangeCount: 1,
}));

describe('Content Improvement Toolbar', () => {
  let mockProps;

  beforeEach(() => {
    mockProps = {
      selectedText: 'Selected text for improvement',
      position: { top: 100, left: 200 },
      onImprove: jest.fn(),
      onClose: jest.fn(),
    };

    jest.clearAllMocks();
  });

  describe('Rendering', () => {
    test('renders toolbar when text is selected', () => {
      render(<ContentImprovementToolbar {...mockProps} />);
      
      expect(screen.getByText('Improve')).toBeInTheDocument();
      expect(screen.getByText('Simplify')).toBeInTheDocument();
      expect(screen.getByText('Expand')).toBeInTheDocument();
      expect(screen.getByText('Fix Grammar')).toBeInTheDocument();
    });

    test('does not render when no text selected', () => {
      mockProps.selectedText = '';
      const { container } = render(<ContentImprovementToolbar {...mockProps} />);
      
      expect(container.firstChild).toBeNull();
    });

    test('positions toolbar correctly', () => {
      const { container } = render(<ContentImprovementToolbar {...mockProps} />);
      const toolbar = container.firstChild;
      
      expect(toolbar).toHaveStyle({
        position: 'absolute',
        top: '100px',
        left: '200px',
      });
    });
  });

  describe('Improvement Actions', () => {
    test('triggers clarity improvement', async () => {
      const mockResponse = {
        success: true,
        improved_content: 'Clearer and more concise text',
        changes: ['Simplified complex sentences', 'Removed redundancies'],
      };

      global.wp.apiFetch.mockResolvedValue(mockResponse);

      render(<ContentImprovementToolbar {...mockProps} />);
      
      const improveButton = screen.getByText('Improve');
      fireEvent.click(improveButton);
      
      await waitFor(() => {
        expect(global.wp.apiFetch).toHaveBeenCalledWith({
          path: '/wp-content-flow/v1/ai/improve',
          method: 'POST',
          data: {
            content: 'Selected text for improvement',
            improvement_type: 'clarity',
          },
        });
      });
      
      expect(mockProps.onImprove).toHaveBeenCalledWith(mockResponse);
    });

    test('triggers simplification', async () => {
      const mockResponse = {
        success: true,
        improved_content: 'Simplified text',
      };

      global.wp.apiFetch.mockResolvedValue(mockResponse);

      render(<ContentImprovementToolbar {...mockProps} />);
      
      const simplifyButton = screen.getByText('Simplify');
      fireEvent.click(simplifyButton);
      
      await waitFor(() => {
        expect(global.wp.apiFetch).toHaveBeenCalledWith({
          path: '/wp-content-flow/v1/ai/improve',
          method: 'POST',
          data: {
            content: 'Selected text for improvement',
            improvement_type: 'simplify',
          },
        });
      });
    });

    test('triggers expansion', async () => {
      const mockResponse = {
        success: true,
        improved_content: 'Expanded text with more details',
      };

      global.wp.apiFetch.mockResolvedValue(mockResponse);

      render(<ContentImprovementToolbar {...mockProps} />);
      
      const expandButton = screen.getByText('Expand');
      fireEvent.click(expandButton);
      
      await waitFor(() => {
        expect(global.wp.apiFetch).toHaveBeenCalledWith({
          path: '/wp-content-flow/v1/ai/improve',
          method: 'POST',
          data: {
            content: 'Selected text for improvement',
            improvement_type: 'expand',
          },
        });
      });
    });

    test('triggers grammar fix', async () => {
      const mockResponse = {
        success: true,
        improved_content: 'Text with corrected grammar',
        changes: ['Fixed subject-verb agreement', 'Corrected punctuation'],
      };

      global.wp.apiFetch.mockResolvedValue(mockResponse);

      render(<ContentImprovementToolbar {...mockProps} />);
      
      const grammarButton = screen.getByText('Fix Grammar');
      fireEvent.click(grammarButton);
      
      await waitFor(() => {
        expect(global.wp.apiFetch).toHaveBeenCalledWith({
          path: '/wp-content-flow/v1/ai/improve',
          method: 'POST',
          data: {
            content: 'Selected text for improvement',
            improvement_type: 'grammar',
          },
        });
      });
    });
  });

  describe('Loading States', () => {
    test('shows loading indicator during improvement', async () => {
      global.wp.apiFetch.mockImplementation(
        () => new Promise((resolve) => setTimeout(resolve, 1000))
      );

      render(<ContentImprovementToolbar {...mockProps} />);
      
      const improveButton = screen.getByText('Improve');
      fireEvent.click(improveButton);
      
      expect(screen.getByText('Improving...')).toBeInTheDocument();
      expect(improveButton).toBeDisabled();
    });

    test('re-enables buttons after completion', async () => {
      global.wp.apiFetch.mockResolvedValue({
        success: true,
        improved_content: 'Improved text',
      });

      render(<ContentImprovementToolbar {...mockProps} />);
      
      const improveButton = screen.getByText('Improve');
      fireEvent.click(improveButton);
      
      await waitFor(() => {
        expect(improveButton).not.toBeDisabled();
      });
    });
  });

  describe('Error Handling', () => {
    test('displays error message on failure', async () => {
      global.wp.apiFetch.mockRejectedValue(new Error('API error'));

      render(<ContentImprovementToolbar {...mockProps} />);
      
      const improveButton = screen.getByText('Improve');
      fireEvent.click(improveButton);
      
      await waitFor(() => {
        expect(screen.getByText(/API error/i)).toBeInTheDocument();
      });
    });

    test('handles rate limiting gracefully', async () => {
      global.wp.apiFetch.mockRejectedValue({
        code: 'rate_limit_exceeded',
        message: 'Too many requests',
      });

      render(<ContentImprovementToolbar {...mockProps} />);
      
      const improveButton = screen.getByText('Improve');
      fireEvent.click(improveButton);
      
      await waitFor(() => {
        expect(screen.getByText(/Too many requests/i)).toBeInTheDocument();
      });
    });
  });
});

describe('Improvement Suggestions', () => {
  let mockProps;

  beforeEach(() => {
    mockProps = {
      originalContent: 'Original text',
      improvedContent: 'Improved text with changes',
      changes: [
        'Simplified complex sentences',
        'Improved word choice',
        'Fixed grammar issues',
      ],
      onAccept: jest.fn(),
      onReject: jest.fn(),
      onModify: jest.fn(),
    };

    jest.clearAllMocks();
  });

  describe('Rendering', () => {
    test('displays improvement suggestions', () => {
      render(<ImprovementSuggestions {...mockProps} />);
      
      expect(screen.getByText('Improved text with changes')).toBeInTheDocument();
      expect(screen.getByText('Simplified complex sentences')).toBeInTheDocument();
      expect(screen.getByText('Improved word choice')).toBeInTheDocument();
      expect(screen.getByText('Fixed grammar issues')).toBeInTheDocument();
    });

    test('shows comparison view', () => {
      render(<ImprovementSuggestions {...mockProps} />);
      
      const compareButton = screen.getByText('Compare');
      fireEvent.click(compareButton);
      
      expect(screen.getByText('Original')).toBeInTheDocument();
      expect(screen.getByText('Improved')).toBeInTheDocument();
      expect(screen.getByText('Original text')).toBeInTheDocument();
      expect(screen.getByText('Improved text with changes')).toBeInTheDocument();
    });

    test('highlights differences in comparison', () => {
      render(<ImprovementSuggestions {...mockProps} />);
      
      const compareButton = screen.getByText('Compare');
      fireEvent.click(compareButton);
      
      const diffElements = screen.getAllByClassName('diff-highlight');
      expect(diffElements.length).toBeGreaterThan(0);
    });
  });

  describe('User Actions', () => {
    test('accepts improvements', () => {
      render(<ImprovementSuggestions {...mockProps} />);
      
      const acceptButton = screen.getByText('Accept');
      fireEvent.click(acceptButton);
      
      expect(mockProps.onAccept).toHaveBeenCalledWith('Improved text with changes');
    });

    test('rejects improvements', () => {
      render(<ImprovementSuggestions {...mockProps} />);
      
      const rejectButton = screen.getByText('Reject');
      fireEvent.click(rejectButton);
      
      expect(mockProps.onReject).toHaveBeenCalled();
    });

    test('allows modification of suggestions', async () => {
      render(<ImprovementSuggestions {...mockProps} />);
      
      const editButton = screen.getByText('Edit');
      fireEvent.click(editButton);
      
      const textarea = screen.getByRole('textbox');
      await userEvent.clear(textarea);
      await userEvent.type(textarea, 'Modified improved text');
      
      const saveButton = screen.getByText('Save');
      fireEvent.click(saveButton);
      
      expect(mockProps.onModify).toHaveBeenCalledWith('Modified improved text');
    });
  });

  describe('Selective Application', () => {
    test('applies individual changes', () => {
      render(<ImprovementSuggestions {...mockProps} />);
      
      const changeCheckboxes = screen.getAllByRole('checkbox');
      
      // Uncheck second change
      fireEvent.click(changeCheckboxes[1]);
      
      const applySelectedButton = screen.getByText('Apply Selected');
      fireEvent.click(applySelectedButton);
      
      expect(mockProps.onModify).toHaveBeenCalled();
    });

    test('disables apply when no changes selected', () => {
      render(<ImprovementSuggestions {...mockProps} />);
      
      const changeCheckboxes = screen.getAllByRole('checkbox');
      
      // Uncheck all changes
      changeCheckboxes.forEach((checkbox) => {
        if (checkbox.checked) {
          fireEvent.click(checkbox);
        }
      });
      
      const applySelectedButton = screen.getByText('Apply Selected');
      expect(applySelectedButton).toBeDisabled();
    });
  });
});

describe('Text Selection Handler', () => {
  let mockProps;

  beforeEach(() => {
    mockProps = {
      onTextSelected: jest.fn(),
      onSelectionCleared: jest.fn(),
      containerRef: { current: document.createElement('div') },
    };

    jest.clearAllMocks();
  });

  describe('Selection Detection', () => {
    test('detects text selection', () => {
      render(<TextSelectionHandler {...mockProps} />);
      
      // Simulate text selection
      const selectionEvent = new Event('mouseup');
      mockProps.containerRef.current.dispatchEvent(selectionEvent);
      
      expect(mockProps.onTextSelected).toHaveBeenCalledWith({
        text: 'Selected text for improvement',
        position: expect.objectContaining({
          top: expect.any(Number),
          left: expect.any(Number),
        }),
      });
    });

    test('ignores empty selections', () => {
      global.window.getSelection.mockReturnValueOnce({
        toString: jest.fn(() => ''),
        rangeCount: 0,
      });

      render(<TextSelectionHandler {...mockProps} />);
      
      const selectionEvent = new Event('mouseup');
      mockProps.containerRef.current.dispatchEvent(selectionEvent);
      
      expect(mockProps.onTextSelected).not.toHaveBeenCalled();
    });

    test('clears selection on click outside', () => {
      render(<TextSelectionHandler {...mockProps} />);
      
      // Click outside
      const clickEvent = new Event('mousedown');
      document.dispatchEvent(clickEvent);
      
      expect(mockProps.onSelectionCleared).toHaveBeenCalled();
    });
  });

  describe('Selection Range', () => {
    test('handles multi-paragraph selections', () => {
      global.window.getSelection.mockReturnValueOnce({
        toString: jest.fn(() => 'First paragraph.\n\nSecond paragraph.'),
        getRangeAt: jest.fn(() => ({
          getBoundingClientRect: jest.fn(() => ({
            top: 100,
            left: 200,
            width: 400,
            height: 100,
          })),
        })),
        rangeCount: 1,
      });

      render(<TextSelectionHandler {...mockProps} />);
      
      const selectionEvent = new Event('mouseup');
      mockProps.containerRef.current.dispatchEvent(selectionEvent);
      
      expect(mockProps.onTextSelected).toHaveBeenCalledWith({
        text: 'First paragraph.\n\nSecond paragraph.',
        position: expect.any(Object),
      });
    });

    test('trims whitespace from selection', () => {
      global.window.getSelection.mockReturnValueOnce({
        toString: jest.fn(() => '  Selected text  '),
        getRangeAt: jest.fn(() => ({
          getBoundingClientRect: jest.fn(() => ({
            top: 100,
            left: 200,
            width: 300,
            height: 50,
          })),
        })),
        rangeCount: 1,
      });

      render(<TextSelectionHandler {...mockProps} />);
      
      const selectionEvent = new Event('mouseup');
      mockProps.containerRef.current.dispatchEvent(selectionEvent);
      
      expect(mockProps.onTextSelected).toHaveBeenCalledWith({
        text: 'Selected text',
        position: expect.any(Object),
      });
    });
  });

  describe('Keyboard Shortcuts', () => {
    test('triggers improvement on keyboard shortcut', () => {
      render(<TextSelectionHandler {...mockProps} />);
      
      // Select text first
      const selectionEvent = new Event('mouseup');
      mockProps.containerRef.current.dispatchEvent(selectionEvent);
      
      // Press Ctrl+I
      const keyEvent = new KeyboardEvent('keydown', {
        key: 'i',
        ctrlKey: true,
      });
      document.dispatchEvent(keyEvent);
      
      expect(mockProps.onTextSelected).toHaveBeenCalled();
    });

    test('cancels selection on Escape', () => {
      render(<TextSelectionHandler {...mockProps} />);
      
      // Select text
      const selectionEvent = new Event('mouseup');
      mockProps.containerRef.current.dispatchEvent(selectionEvent);
      
      // Press Escape
      const keyEvent = new KeyboardEvent('keydown', {
        key: 'Escape',
      });
      document.dispatchEvent(keyEvent);
      
      expect(mockProps.onSelectionCleared).toHaveBeenCalled();
    });
  });
});