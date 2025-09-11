/**
 * Contract Test: Workflow Settings Panel
 * 
 * Tests the Gutenberg block editor sidebar panel for workflow configuration,
 * AI provider selection, and content generation parameters.
 */

import { render, screen, fireEvent, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom';

// Mock WordPress dependencies
jest.mock('@wordpress/block-editor', () => ({
    InspectorControls: ({ children }) => <div data-testid="inspector-controls">{children}</div>,
    useBlockProps: () => ({ className: 'wp-block' }),
}));

jest.mock('@wordpress/components', () => ({
    PanelBody: ({ title, children, opened = true }) => (
        <div data-testid={`panel-body-${title}`} aria-expanded={opened}>
            <h3>{title}</h3>
            {opened && children}
        </div>
    ),
    PanelRow: ({ children }) => <div data-testid="panel-row">{children}</div>,
    SelectControl: ({ label, value, onChange, options }) => (
        <div data-testid={`select-${label}`}>
            <label>{label}</label>
            <select value={value} onChange={e => onChange(e.target.value)}>
                {options.map(opt => (
                    <option key={opt.value} value={opt.value}>{opt.label}</option>
                ))}
            </select>
        </div>
    ),
    TextControl: ({ label, value, onChange }) => (
        <div data-testid={`text-${label}`}>
            <label>{label}</label>
            <input type="text" value={value} onChange={e => onChange(e.target.value)} />
        </div>
    ),
    TextareaControl: ({ label, value, onChange }) => (
        <div data-testid={`textarea-${label}`}>
            <label>{label}</label>
            <textarea value={value} onChange={e => onChange(e.target.value)} />
        </div>
    ),
    RangeControl: ({ label, value, onChange, min, max }) => (
        <div data-testid={`range-${label}`}>
            <label>{label}</label>
            <input 
                type="range" 
                value={value} 
                onChange={e => onChange(Number(e.target.value))}
                min={min}
                max={max}
            />
            <span>{value}</span>
        </div>
    ),
    ToggleControl: ({ label, checked, onChange }) => (
        <div data-testid={`toggle-${label}`}>
            <label>
                <input 
                    type="checkbox" 
                    checked={checked} 
                    onChange={e => onChange(e.target.checked)}
                />
                {label}
            </label>
        </div>
    ),
    Button: ({ children, onClick, variant, isDestructive }) => (
        <button 
            onClick={onClick}
            className={variant}
            data-destructive={isDestructive}
        >
            {children}
        </button>
    ),
    Notice: ({ children, status }) => (
        <div data-testid="notice" className={`notice-${status}`}>
            {children}
        </div>
    ),
    Spinner: () => <div data-testid="spinner">Loading...</div>,
}));

jest.mock('@wordpress/data', () => ({
    useSelect: (callback) => callback(() => ({
        getSelectedBlock: () => ({
            clientId: 'test-block-id',
            attributes: {
                workflowId: 1,
                aiProvider: 'openai',
                parameters: {}
            }
        }),
    })),
    useDispatch: () => ({
        updateBlockAttributes: jest.fn(),
        createNotice: jest.fn()
    })
}));

// Import the component to test
import WorkflowSettingsPanel from '../../assets/js/workflow-settings';

describe('Workflow Settings Panel Contract Tests', () => {
    let user;
    
    beforeEach(() => {
        user = userEvent.setup();
        jest.clearAllMocks();
        
        // Mock API
        global.wp = {
            apiFetch: jest.fn(),
            data: {
                select: jest.fn(),
                dispatch: jest.fn()
            }
        };
        
        // Default API responses
        global.wp.apiFetch.mockImplementation(({ path }) => {
            if (path === '/wp-content-flow/v1/workflows') {
                return Promise.resolve([
                    { id: 1, name: 'Blog Post Generation', type: 'content_generation' },
                    { id: 2, name: 'Content Improvement', type: 'content_improvement' },
                    { id: 3, name: 'SEO Optimization', type: 'seo' }
                ]);
            }
            if (path === '/wp-content-flow/v1/providers') {
                return Promise.resolve([
                    { id: 'openai', name: 'OpenAI', models: ['gpt-3.5-turbo', 'gpt-4'] },
                    { id: 'anthropic', name: 'Anthropic', models: ['claude-2', 'claude-instant'] },
                    { id: 'google_ai', name: 'Google AI', models: ['gemini-pro'] }
                ]);
            }
            return Promise.resolve({});
        });
    });
    
    /**
     * Test panel renders with all sections
     */
    test('should render workflow settings panel with all sections', async () => {
        render(<WorkflowSettingsPanel />);
        
        // Wait for data to load
        await waitFor(() => {
            expect(screen.getByTestId('inspector-controls')).toBeInTheDocument();
        });
        
        // Verify main sections
        expect(screen.getByTestId('panel-body-Workflow Settings')).toBeInTheDocument();
        expect(screen.getByTestId('panel-body-AI Provider')).toBeInTheDocument();
        expect(screen.getByTestId('panel-body-Generation Parameters')).toBeInTheDocument();
        expect(screen.getByTestId('panel-body-Advanced Settings')).toBeInTheDocument();
    });
    
    /**
     * Test workflow selection
     */
    test('should allow workflow selection from available workflows', async () => {
        const updateBlockAttributes = jest.fn();
        global.wp.data.dispatch = jest.fn(() => ({ updateBlockAttributes }));
        
        render(<WorkflowSettingsPanel />);
        
        // Wait for workflows to load
        await waitFor(() => {
            expect(screen.getByTestId('select-Workflow')).toBeInTheDocument();
        });
        
        const workflowSelect = within(screen.getByTestId('select-Workflow')).getByRole('combobox');
        
        // Verify options are loaded
        expect(workflowSelect).toHaveValue('1');
        expect(within(workflowSelect).getByText('Blog Post Generation')).toBeInTheDocument();
        expect(within(workflowSelect).getByText('Content Improvement')).toBeInTheDocument();
        expect(within(workflowSelect).getByText('SEO Optimization')).toBeInTheDocument();
        
        // Change workflow
        await user.selectOptions(workflowSelect, '2');
        
        // Should update block attributes
        expect(updateBlockAttributes).toHaveBeenCalledWith(
            'test-block-id',
            { workflowId: 2 }
        );
    });
    
    /**
     * Test AI provider selection
     */
    test('should allow AI provider selection with model options', async () => {
        const updateBlockAttributes = jest.fn();
        global.wp.data.dispatch = jest.fn(() => ({ updateBlockAttributes }));
        
        render(<WorkflowSettingsPanel />);
        
        await waitFor(() => {
            expect(screen.getByTestId('select-AI Provider')).toBeInTheDocument();
        });
        
        const providerSelect = within(screen.getByTestId('select-AI Provider')).getByRole('combobox');
        
        // Verify providers are loaded
        expect(within(providerSelect).getByText('OpenAI')).toBeInTheDocument();
        expect(within(providerSelect).getByText('Anthropic')).toBeInTheDocument();
        expect(within(providerSelect).getByText('Google AI')).toBeInTheDocument();
        
        // Change provider
        await user.selectOptions(providerSelect, 'anthropic');
        
        // Should update attributes
        expect(updateBlockAttributes).toHaveBeenCalledWith(
            'test-block-id',
            { aiProvider: 'anthropic' }
        );
        
        // Should show model selection for new provider
        await waitFor(() => {
            expect(screen.getByTestId('select-Model')).toBeInTheDocument();
        });
        
        const modelSelect = within(screen.getByTestId('select-Model')).getByRole('combobox');
        expect(within(modelSelect).getByText('claude-2')).toBeInTheDocument();
        expect(within(modelSelect).getByText('claude-instant')).toBeInTheDocument();
    });
    
    /**
     * Test generation parameters
     */
    test('should configure generation parameters', async () => {
        const updateBlockAttributes = jest.fn();
        global.wp.data.dispatch = jest.fn(() => ({ updateBlockAttributes }));
        
        render(<WorkflowSettingsPanel />);
        
        await waitFor(() => {
            expect(screen.getByTestId('panel-body-Generation Parameters')).toBeInTheDocument();
        });
        
        // Test temperature control
        const temperatureSlider = within(screen.getByTestId('range-Temperature')).getByRole('slider');
        expect(temperatureSlider).toHaveAttribute('min', '0');
        expect(temperatureSlider).toHaveAttribute('max', '2');
        
        fireEvent.change(temperatureSlider, { target: { value: '0.8' } });
        
        expect(updateBlockAttributes).toHaveBeenCalledWith(
            'test-block-id',
            { 
                parameters: expect.objectContaining({
                    temperature: 0.8
                })
            }
        );
        
        // Test max tokens control
        const maxTokensSlider = within(screen.getByTestId('range-Max Tokens')).getByRole('slider');
        fireEvent.change(maxTokensSlider, { target: { value: '2000' } });
        
        expect(updateBlockAttributes).toHaveBeenCalledWith(
            'test-block-id',
            {
                parameters: expect.objectContaining({
                    max_tokens: 2000
                })
            }
        );
    });
    
    /**
     * Test prompt template customization
     */
    test('should allow prompt template customization', async () => {
        const updateBlockAttributes = jest.fn();
        global.wp.data.dispatch = jest.fn(() => ({ updateBlockAttributes }));
        
        render(<WorkflowSettingsPanel />);
        
        await waitFor(() => {
            expect(screen.getByTestId('textarea-Prompt Template')).toBeInTheDocument();
        });
        
        const promptTextarea = within(screen.getByTestId('textarea-Prompt Template')).getByRole('textbox');
        
        const newPrompt = 'Generate a {type} about {topic} with a {tone} tone';
        await user.clear(promptTextarea);
        await user.type(promptTextarea, newPrompt);
        
        expect(updateBlockAttributes).toHaveBeenCalledWith(
            'test-block-id',
            {
                promptTemplate: newPrompt
            }
        );
        
        // Should show template variables
        expect(screen.getByText(/Available variables:/)).toBeInTheDocument();
        expect(screen.getByText(/\{type\}/)).toBeInTheDocument();
        expect(screen.getByText(/\{topic\}/)).toBeInTheDocument();
        expect(screen.getByText(/\{tone\}/)).toBeInTheDocument();
    });
    
    /**
     * Test advanced settings
     */
    test('should configure advanced settings', async () => {
        const updateBlockAttributes = jest.fn();
        global.wp.data.dispatch = jest.fn(() => ({ updateBlockAttributes }));
        
        render(<WorkflowSettingsPanel />);
        
        await waitFor(() => {
            expect(screen.getByTestId('panel-body-Advanced Settings')).toBeInTheDocument();
        });
        
        // Test caching toggle
        const cacheToggle = within(screen.getByTestId('toggle-Enable Caching')).getByRole('checkbox');
        await user.click(cacheToggle);
        
        expect(updateBlockAttributes).toHaveBeenCalledWith(
            'test-block-id',
            {
                enableCaching: true
            }
        );
        
        // Test retry on failure toggle
        const retryToggle = within(screen.getByTestId('toggle-Retry on Failure')).getByRole('checkbox');
        await user.click(retryToggle);
        
        expect(updateBlockAttributes).toHaveBeenCalledWith(
            'test-block-id',
            {
                retryOnFailure: true
            }
        );
    });
    
    /**
     * Test workflow creation
     */
    test('should allow creating new workflow', async () => {
        global.wp.apiFetch.mockImplementation(({ path, method }) => {
            if (path === '/wp-content-flow/v1/workflows' && method === 'POST') {
                return Promise.resolve({
                    id: 4,
                    name: 'New Custom Workflow',
                    type: 'custom'
                });
            }
            return Promise.resolve([]);
        });
        
        render(<WorkflowSettingsPanel />);
        
        // Click create new workflow button
        const createButton = screen.getByRole('button', { name: /Create New Workflow/ });
        await user.click(createButton);
        
        // Should show workflow creation form
        await waitFor(() => {
            expect(screen.getByTestId('text-Workflow Name')).toBeInTheDocument();
        });
        
        // Fill in workflow details
        const nameInput = within(screen.getByTestId('text-Workflow Name')).getByRole('textbox');
        await user.type(nameInput, 'New Custom Workflow');
        
        // Select workflow type
        const typeSelect = within(screen.getByTestId('select-Workflow Type')).getByRole('combobox');
        await user.selectOptions(typeSelect, 'custom');
        
        // Save workflow
        const saveButton = screen.getByRole('button', { name: /Save Workflow/ });
        await user.click(saveButton);
        
        // Should create workflow via API
        await waitFor(() => {
            expect(global.wp.apiFetch).toHaveBeenCalledWith({
                path: '/wp-content-flow/v1/workflows',
                method: 'POST',
                data: expect.objectContaining({
                    name: 'New Custom Workflow',
                    type: 'custom'
                })
            });
        });
    });
    
    /**
     * Test workflow deletion
     */
    test('should allow workflow deletion with confirmation', async () => {
        global.wp.apiFetch.mockImplementation(({ path, method }) => {
            if (path.includes('/workflows/1') && method === 'DELETE') {
                return Promise.resolve({ success: true });
            }
            return Promise.resolve([]);
        });
        
        render(<WorkflowSettingsPanel />);
        
        // Click delete workflow button
        const deleteButton = screen.getByRole('button', { name: /Delete Workflow/ });
        expect(deleteButton).toHaveAttribute('data-destructive', 'true');
        
        await user.click(deleteButton);
        
        // Should show confirmation dialog
        await waitFor(() => {
            expect(screen.getByText(/Are you sure you want to delete this workflow?/)).toBeInTheDocument();
        });
        
        // Confirm deletion
        const confirmButton = screen.getByRole('button', { name: /Confirm Delete/ });
        await user.click(confirmButton);
        
        // Should delete via API
        await waitFor(() => {
            expect(global.wp.apiFetch).toHaveBeenCalledWith({
                path: '/wp-content-flow/v1/workflows/1',
                method: 'DELETE'
            });
        });
    });
    
    /**
     * Test provider quota display
     */
    test('should display provider quota and usage', async () => {
        global.wp.apiFetch.mockImplementation(({ path }) => {
            if (path === '/wp-content-flow/v1/providers/quota') {
                return Promise.resolve({
                    openai: { used: 5000, limit: 10000, percentage: 50 },
                    anthropic: { used: 2000, limit: 8000, percentage: 25 },
                    google_ai: { used: 100, limit: 12000, percentage: 1 }
                });
            }
            return Promise.resolve([]);
        });
        
        render(<WorkflowSettingsPanel />);
        
        // Should fetch and display quota
        await waitFor(() => {
            expect(screen.getByText(/Quota: 50% used/)).toBeInTheDocument();
        });
        
        // Change provider to see different quota
        const providerSelect = within(screen.getByTestId('select-AI Provider')).getByRole('combobox');
        await user.selectOptions(providerSelect, 'anthropic');
        
        await waitFor(() => {
            expect(screen.getByText(/Quota: 25% used/)).toBeInTheDocument();
        });
    });
    
    /**
     * Test workflow preview
     */
    test('should preview workflow output', async () => {
        global.wp.apiFetch.mockImplementation(({ path, method }) => {
            if (path === '/wp-content-flow/v1/workflows/preview' && method === 'POST') {
                return Promise.resolve({
                    preview: 'This is a preview of the generated content based on your workflow settings.',
                    tokens_used: 150,
                    estimated_cost: 0.003
                });
            }
            return Promise.resolve([]);
        });
        
        render(<WorkflowSettingsPanel />);
        
        // Click preview button
        const previewButton = screen.getByRole('button', { name: /Preview Workflow/ });
        await user.click(previewButton);
        
        // Should show loading state
        expect(screen.getByTestId('spinner')).toBeInTheDocument();
        
        // Should display preview
        await waitFor(() => {
            expect(screen.getByText(/This is a preview of the generated content/)).toBeInTheDocument();
            expect(screen.getByText(/Tokens: 150/)).toBeInTheDocument();
            expect(screen.getByText(/Estimated cost: \$0.003/)).toBeInTheDocument();
        });
    });
    
    /**
     * Test error handling
     */
    test('should handle API errors gracefully', async () => {
        const createNotice = jest.fn();
        global.wp.data.dispatch = jest.fn(() => ({ createNotice }));
        
        global.wp.apiFetch.mockRejectedValue({
            code: 'invalid_api_key',
            message: 'Invalid API key for selected provider'
        });
        
        render(<WorkflowSettingsPanel />);
        
        // Try to preview workflow
        const previewButton = screen.getByRole('button', { name: /Preview Workflow/ });
        await user.click(previewButton);
        
        // Should show error notice
        await waitFor(() => {
            expect(createNotice).toHaveBeenCalledWith(
                'error',
                'Invalid API key for selected provider',
                expect.any(Object)
            );
        });
    });
    
    /**
     * Test workflow import/export
     */
    test('should support workflow import and export', async () => {
        render(<WorkflowSettingsPanel />);
        
        // Test export
        const exportButton = screen.getByRole('button', { name: /Export Workflow/ });
        await user.click(exportButton);
        
        // Should trigger download
        await waitFor(() => {
            expect(global.wp.apiFetch).toHaveBeenCalledWith({
                path: '/wp-content-flow/v1/workflows/1/export',
                method: 'GET'
            });
        });
        
        // Test import
        const importButton = screen.getByRole('button', { name: /Import Workflow/ });
        await user.click(importButton);
        
        // Should show file input
        await waitFor(() => {
            expect(screen.getByLabelText(/Select workflow file/)).toBeInTheDocument();
        });
    });
});