/**
 * Contract Test: Content Improvement Toolbar
 * 
 * Tests the Gutenberg block editor toolbar integration for content improvement
 * features including AI suggestions, quick actions, and workflow triggers.
 */

import { registerBlockType } from '@wordpress/blocks';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom';

// Mock WordPress dependencies
jest.mock('@wordpress/block-editor', () => ({
    BlockControls: ({ children }) => <div data-testid="block-controls">{children}</div>,
    useBlockProps: () => ({ className: 'wp-block' }),
    RichText: ({ value, onChange }) => (
        <div 
            contentEditable
            data-testid="rich-text"
            onInput={(e) => onChange(e.target.textContent)}
        >
            {value}
        </div>
    ),
}));

jest.mock('@wordpress/components', () => ({
    ToolbarGroup: ({ children }) => <div data-testid="toolbar-group">{children}</div>,
    ToolbarButton: ({ icon, label, onClick, isPressed }) => (
        <button
            data-testid={`toolbar-button-${label}`}
            onClick={onClick}
            aria-pressed={isPressed}
            aria-label={label}
        >
            {label}
        </button>
    ),
    ToolbarDropdownMenu: ({ icon, label, controls }) => (
        <div data-testid={`toolbar-dropdown-${label}`}>
            {controls.map(control => (
                <button
                    key={control.title}
                    data-testid={`dropdown-item-${control.title}`}
                    onClick={control.onClick}
                >
                    {control.title}
                </button>
            ))}
        </div>
    ),
    Popover: ({ children, isOpen }) => 
        isOpen ? <div data-testid="popover">{children}</div> : null,
    Spinner: () => <div data-testid="spinner">Loading...</div>,
}));

jest.mock('@wordpress/data', () => ({
    useSelect: (callback) => callback(() => ({
        getSelectedBlock: () => ({
            clientId: 'test-block-id',
            attributes: { content: 'Test content' }
        }),
        getBlock: () => ({
            attributes: { content: 'Test content' }
        })
    })),
    useDispatch: () => ({
        updateBlockAttributes: jest.fn(),
        createNotice: jest.fn()
    })
}));

// Import the component to test
import ImprovementToolbar from '../../assets/js/improvement-toolbar';

describe('Content Improvement Toolbar Contract Tests', () => {
    let user;
    
    beforeEach(() => {
        user = userEvent.setup();
        jest.clearAllMocks();
        
        // Mock API responses
        global.fetch = jest.fn();
        global.wp = {
            apiFetch: jest.fn(),
            data: {
                select: jest.fn(),
                dispatch: jest.fn()
            }
        };
    });
    
    afterEach(() => {
        jest.restoreAllMocks();
    });
    
    /**
     * Test toolbar renders with correct controls
     */
    test('should render improvement toolbar with all required controls', () => {
        const { container } = render(<ImprovementToolbar />);
        
        // Verify toolbar structure
        expect(screen.getByTestId('block-controls')).toBeInTheDocument();
        expect(screen.getByTestId('toolbar-group')).toBeInTheDocument();
        
        // Verify main improvement button
        expect(screen.getByTestId('toolbar-button-Improve Content')).toBeInTheDocument();
        
        // Verify dropdown menu with improvement options
        expect(screen.getByTestId('toolbar-dropdown-AI Actions')).toBeInTheDocument();
    });
    
    /**
     * Test quick improvement action
     */
    test('should trigger quick improvement when button clicked', async () => {
        global.wp.apiFetch.mockResolvedValue({
            success: true,
            improved_content: 'Enhanced content with better clarity',
            suggestion_id: 123
        });
        
        render(<ImprovementToolbar />);
        
        const improveButton = screen.getByTestId('toolbar-button-Improve Content');
        await user.click(improveButton);
        
        // Should show loading state
        await waitFor(() => {
            expect(screen.getByTestId('spinner')).toBeInTheDocument();
        });
        
        // Should call API
        await waitFor(() => {
            expect(global.wp.apiFetch).toHaveBeenCalledWith({
                path: '/wp-content-flow/v1/ai/improve',
                method: 'POST',
                data: expect.objectContaining({
                    content: 'Test content',
                    improvement_type: 'quick'
                })
            });
        });
        
        // Should show success state
        await waitFor(() => {
            expect(screen.queryByTestId('spinner')).not.toBeInTheDocument();
        });
    });
    
    /**
     * Test improvement type selection
     */
    test('should offer different improvement types in dropdown', async () => {
        render(<ImprovementToolbar />);
        
        const improvementTypes = [
            'Improve Clarity',
            'Enhance Engagement',
            'Optimize for SEO',
            'Fix Grammar',
            'Simplify Language',
            'Add Details',
            'Make Concise'
        ];
        
        // Check all improvement types are available
        improvementTypes.forEach(type => {
            expect(screen.getByTestId(`dropdown-item-${type}`)).toBeInTheDocument();
        });
    });
    
    /**
     * Test targeted improvement with specific focus
     */
    test('should apply targeted improvement based on selection', async () => {
        global.wp.apiFetch.mockResolvedValue({
            success: true,
            improved_content: 'SEO optimized content',
            keywords_added: ['WordPress', 'AI', 'content']
        });
        
        render(<ImprovementToolbar />);
        
        const seoButton = screen.getByTestId('dropdown-item-Optimize for SEO');
        await user.click(seoButton);
        
        await waitFor(() => {
            expect(global.wp.apiFetch).toHaveBeenCalledWith({
                path: '/wp-content-flow/v1/ai/improve',
                method: 'POST',
                data: expect.objectContaining({
                    improvement_type: 'seo',
                    parameters: expect.objectContaining({
                        focus_areas: ['keywords', 'meta', 'readability']
                    })
                })
            });
        });
    });
    
    /**
     * Test improvement preview before applying
     */
    test('should show preview of improvements before applying', async () => {
        global.wp.apiFetch.mockResolvedValue({
            success: true,
            improved_content: 'Preview of improved content',
            changes: [
                { original: 'old text', improved: 'new text', type: 'clarity' }
            ]
        });
        
        render(<ImprovementToolbar />);
        
        const improveButton = screen.getByTestId('toolbar-button-Improve Content');
        await user.click(improveButton);
        
        // Wait for preview to load
        await waitFor(() => {
            expect(screen.getByTestId('popover')).toBeInTheDocument();
        });
        
        // Should show preview content
        expect(screen.getByText(/Preview of improved content/)).toBeInTheDocument();
        
        // Should show accept/reject buttons
        expect(screen.getByRole('button', { name: /Accept/ })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Reject/ })).toBeInTheDocument();
    });
    
    /**
     * Test accepting improvement suggestion
     */
    test('should apply improvement when accepted', async () => {
        const updateBlockAttributes = jest.fn();
        
        global.wp.data.dispatch = jest.fn(() => ({
            updateBlockAttributes
        }));
        
        global.wp.apiFetch.mockResolvedValue({
            success: true,
            improved_content: 'Accepted improved content',
            suggestion_id: 456
        });
        
        render(<ImprovementToolbar />);
        
        // Trigger improvement
        const improveButton = screen.getByTestId('toolbar-button-Improve Content');
        await user.click(improveButton);
        
        // Wait for preview
        await waitFor(() => {
            expect(screen.getByTestId('popover')).toBeInTheDocument();
        });
        
        // Accept improvement
        const acceptButton = screen.getByRole('button', { name: /Accept/ });
        await user.click(acceptButton);
        
        // Should update block content
        await waitFor(() => {
            expect(updateBlockAttributes).toHaveBeenCalledWith(
                'test-block-id',
                { content: 'Accepted improved content' }
            );
        });
    });
    
    /**
     * Test rejecting improvement suggestion
     */
    test('should not apply improvement when rejected', async () => {
        const updateBlockAttributes = jest.fn();
        
        global.wp.data.dispatch = jest.fn(() => ({
            updateBlockAttributes
        }));
        
        global.wp.apiFetch.mockResolvedValue({
            success: true,
            improved_content: 'Rejected content',
            suggestion_id: 789
        });
        
        render(<ImprovementToolbar />);
        
        // Trigger improvement
        const improveButton = screen.getByTestId('toolbar-button-Improve Content');
        await user.click(improveButton);
        
        // Wait for preview
        await waitFor(() => {
            expect(screen.getByTestId('popover')).toBeInTheDocument();
        });
        
        // Reject improvement
        const rejectButton = screen.getByRole('button', { name: /Reject/ });
        await user.click(rejectButton);
        
        // Should NOT update block content
        expect(updateBlockAttributes).not.toHaveBeenCalled();
        
        // Should close popover
        await waitFor(() => {
            expect(screen.queryByTestId('popover')).not.toBeInTheDocument();
        });
    });
    
    /**
     * Test workflow integration
     */
    test('should integrate with workflow system', async () => {
        global.wp.apiFetch
            .mockResolvedValueOnce({
                // Get workflows
                workflows: [
                    { id: 1, name: 'Blog Post Workflow', type: 'content_generation' },
                    { id: 2, name: 'Technical Review', type: 'content_improvement' }
                ]
            })
            .mockResolvedValueOnce({
                // Apply workflow
                success: true,
                content: 'Workflow processed content'
            });
        
        render(<ImprovementToolbar />);
        
        // Should have workflow button
        const workflowButton = screen.getByTestId('dropdown-item-Apply Workflow');
        expect(workflowButton).toBeInTheDocument();
        
        await user.click(workflowButton);
        
        // Should fetch available workflows
        await waitFor(() => {
            expect(global.wp.apiFetch).toHaveBeenCalledWith({
                path: '/wp-content-flow/v1/workflows',
                method: 'GET'
            });
        });
        
        // Should show workflow selection
        await waitFor(() => {
            expect(screen.getByText('Blog Post Workflow')).toBeInTheDocument();
            expect(screen.getByText('Technical Review')).toBeInTheDocument();
        });
    });
    
    /**
     * Test error handling
     */
    test('should handle API errors gracefully', async () => {
        const createNotice = jest.fn();
        global.wp.data.dispatch = jest.fn(() => ({ createNotice }));
        
        global.wp.apiFetch.mockRejectedValue({
            code: 'api_error',
            message: 'AI service unavailable'
        });
        
        render(<ImprovementToolbar />);
        
        const improveButton = screen.getByTestId('toolbar-button-Improve Content');
        await user.click(improveButton);
        
        // Should show error notification
        await waitFor(() => {
            expect(createNotice).toHaveBeenCalledWith(
                'error',
                expect.stringContaining('AI service unavailable')
            );
        });
        
        // Should not show spinner after error
        expect(screen.queryByTestId('spinner')).not.toBeInTheDocument();
    });
    
    /**
     * Test permission-based visibility
     */
    test('should respect user permissions for toolbar visibility', () => {
        // Mock user without edit permissions
        global.wp.data.select = jest.fn(() => ({
            canUser: () => false,
            getSelectedBlock: () => null
        }));
        
        const { container } = render(<ImprovementToolbar />);
        
        // Should not render toolbar for users without permissions
        expect(container.firstChild).toBeNull();
    });
    
    /**
     * Test keyboard shortcuts
     */
    test('should support keyboard shortcuts for quick actions', async () => {
        global.wp.apiFetch.mockResolvedValue({
            success: true,
            improved_content: 'Keyboard shortcut triggered'
        });
        
        render(<ImprovementToolbar />);
        
        // Simulate Ctrl+Shift+I for improvement
        await user.keyboard('{Control>}{Shift>}i{/Shift}{/Control}');
        
        // Should trigger improvement
        await waitFor(() => {
            expect(global.wp.apiFetch).toHaveBeenCalledWith(
                expect.objectContaining({
                    path: '/wp-content-flow/v1/ai/improve'
                })
            );
        });
    });
    
    /**
     * Test multi-language support
     */
    test('should support content improvement in different languages', async () => {
        // Mock detected language
        global.wp.data.select = jest.fn(() => ({
            getSelectedBlock: () => ({
                clientId: 'test-block-id',
                attributes: { 
                    content: 'Contenido en español',
                    language: 'es'
                }
            })
        }));
        
        global.wp.apiFetch.mockResolvedValue({
            success: true,
            improved_content: 'Contenido mejorado en español',
            detected_language: 'es'
        });
        
        render(<ImprovementToolbar />);
        
        const improveButton = screen.getByTestId('toolbar-button-Improve Content');
        await user.click(improveButton);
        
        // Should include language in API call
        await waitFor(() => {
            expect(global.wp.apiFetch).toHaveBeenCalledWith({
                path: '/wp-content-flow/v1/ai/improve',
                method: 'POST',
                data: expect.objectContaining({
                    language: 'es'
                })
            });
        });
    });
});