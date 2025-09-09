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
  components: {
    ...global.wp.components,
    Button: ({ children, onClick, variant, disabled }) => (
      <button onClick={onClick} disabled={disabled} className={variant}>
        {children}
      </button>
    ),
    TextControl: ({ label, value, onChange, help }) => (
      <div>
        <label>{label}</label>
        <input
          type="text"
          value={value || ''}
          onChange={(e) => onChange(e.target.value)}
          aria-describedby={help ? 'help' : undefined}
        />
        {help && <div id="help">{help}</div>}
      </div>
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
    ToggleControl: ({ label, checked, onChange }) => (
      <div>
        <label>
          <input
            type="checkbox"
            checked={checked || false}
            onChange={(e) => onChange(e.target.checked)}
          />
          {label}
        </label>
      </div>
    ),
    Panel: ({ children }) => <div className="panel">{children}</div>,
    PanelBody: ({ title, children, opened }) => (
      <div className={`panel-body ${opened ? 'opened' : 'closed'}`}>
        <h3>{title}</h3>
        {children}
      </div>
    ),
    RangeControl: ({ label, value, onChange, min, max, step }) => (
      <div>
        <label>{label}</label>
        <input
          type="range"
          value={value || 0}
          onChange={(e) => onChange(Number(e.target.value))}
          min={min}
          max={max}
          step={step}
        />
        <span>{value}</span>
      </div>
    ),
  },
};

// Mock workflow settings module
const mockWorkflowSettings = {
  init: jest.fn(),
  render: jest.fn(),
  saveSettings: jest.fn(),
  loadWorkflows: jest.fn(),
  createWorkflow: jest.fn(),
  updateWorkflow: jest.fn(),
  deleteWorkflow: jest.fn(),
};

// Import and mock the workflow settings
jest.mock('./workflow-settings.js', () => mockWorkflowSettings);

describe('Workflow Settings Component', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    
    // Setup default API responses
    mockApiRequest.mockImplementation((config) => {
      if (config.path?.includes('/workflows')) {
        return Promise.resolve({
          success: true,
          data: [
            createMockWorkflow({ id: 1, name: 'Blog Post Workflow' }),
            createMockWorkflow({ id: 2, name: 'Product Description Workflow' }),
          ]
        });
      }
      return Promise.resolve({ success: true, data: {} });
    });

    // Setup WordPress data hooks
    mockUseSelect.mockReturnValue({
      workflows: [],
      isLoading: false,
      hasError: false,
    });

    mockUseDispatch.mockReturnValue({
      saveWorkflow: jest.fn(),
      deleteWorkflow: jest.fn(),
      createNotice: mockCreateNotice,
    });
  });

  describe('Component Initialization', () => {
    test('should initialize workflow settings on DOM ready', () => {
      // Mock DOM ready
      const domReadyCallback = jest.fn();
      global.wp.domReady = domReadyCallback;

      require('./workflow-settings.js');

      expect(domReadyCallback).toHaveBeenCalledWith(mockWorkflowSettings.init);
    });

    test('should render workflow settings interface', async () => {
      const WorkflowSettings = mockWorkflowSettings.render;
      
      render(<WorkflowSettings />);

      await waitFor(() => {
        expect(screen.getByText(/workflow settings/i)).toBeInTheDocument();
      });
    });

    test('should load existing workflows on initialization', async () => {
      const WorkflowSettings = mockWorkflowSettings.render;
      
      render(<WorkflowSettings />);

      await waitFor(() => {
        expect(mockApiRequest).toHaveBeenCalledWith({
          path: '/wp-content-flow/v1/workflows',
          method: 'GET',
        });
      });
    });
  });

  describe('Workflow List Display', () => {
    test('should display list of existing workflows', async () => {
      const workflows = [
        createMockWorkflow({ id: 1, name: 'Blog Post Workflow' }),
        createMockWorkflow({ id: 2, name: 'Product Description Workflow' }),
      ];

      mockUseSelect.mockReturnValue({
        workflows,
        isLoading: false,
        hasError: false,
      });

      const WorkflowSettings = mockWorkflowSettings.render;
      render(<WorkflowSettings />);

      expect(screen.getByText('Blog Post Workflow')).toBeInTheDocument();
      expect(screen.getByText('Product Description Workflow')).toBeInTheDocument();
    });

    test('should show loading state while fetching workflows', () => {
      mockUseSelect.mockReturnValue({
        workflows: [],
        isLoading: true,
        hasError: false,
      });

      const WorkflowSettings = mockWorkflowSettings.render;
      render(<WorkflowSettings />);

      expect(screen.getByText(/loading workflows/i)).toBeInTheDocument();
    });

    test('should show error state when workflow loading fails', () => {
      mockUseSelect.mockReturnValue({
        workflows: [],
        isLoading: false,
        hasError: true,
      });

      const WorkflowSettings = mockWorkflowSettings.render;
      render(<WorkflowSettings />);

      expect(screen.getByText(/failed to load workflows/i)).toBeInTheDocument();
    });

    test('should show empty state when no workflows exist', () => {
      mockUseSelect.mockReturnValue({
        workflows: [],
        isLoading: false,
        hasError: false,
      });

      const WorkflowSettings = mockWorkflowSettings.render;
      render(<WorkflowSettings />);

      expect(screen.getByText(/no workflows found/i)).toBeInTheDocument();
      expect(screen.getByText(/create your first workflow/i)).toBeInTheDocument();
    });
  });

  describe('Workflow Creation', () => {
    test('should show create workflow form when button clicked', async () => {
      const user = userEvent.setup();
      const WorkflowSettings = mockWorkflowSettings.render;
      
      render(<WorkflowSettings />);

      const createButton = screen.getByRole('button', { name: /create workflow/i });
      await user.click(createButton);

      expect(screen.getByText(/new workflow/i)).toBeInTheDocument();
      expect(screen.getByLabelText(/workflow name/i)).toBeInTheDocument();
      expect(screen.getByLabelText(/description/i)).toBeInTheDocument();
    });

    test('should validate required fields in create form', async () => {
      const user = userEvent.setup();
      const WorkflowSettings = mockWorkflowSettings.render;
      
      render(<WorkflowSettings />);

      const createButton = screen.getByRole('button', { name: /create workflow/i });
      await user.click(createButton);

      const saveButton = screen.getByRole('button', { name: /save workflow/i });
      await user.click(saveButton);

      expect(screen.getByText(/workflow name is required/i)).toBeInTheDocument();
    });

    test('should create workflow with valid data', async () => {
      const user = userEvent.setup();
      const WorkflowSettings = mockWorkflowSettings.render;

      mockApiRequest.mockResolvedValueOnce({
        success: true,
        data: createMockWorkflow({ id: 3, name: 'New Test Workflow' })
      });
      
      render(<WorkflowSettings />);

      const createButton = screen.getByRole('button', { name: /create workflow/i });
      await user.click(createButton);

      await user.type(screen.getByLabelText(/workflow name/i), 'New Test Workflow');
      await user.type(screen.getByLabelText(/description/i), 'A test workflow');
      await user.selectOptions(screen.getByLabelText(/ai provider/i), 'openai');

      const saveButton = screen.getByRole('button', { name: /save workflow/i });
      await user.click(saveButton);

      await waitFor(() => {
        expect(mockApiRequest).toHaveBeenCalledWith({
          path: '/wp-content-flow/v1/workflows',
          method: 'POST',
          data: expect.objectContaining({
            name: 'New Test Workflow',
            description: 'A test workflow',
            ai_provider: 'openai',
          }),
        });
      });
    });

    test('should show success message after creating workflow', async () => {
      const user = userEvent.setup();
      const WorkflowSettings = mockWorkflowSettings.render;

      mockApiRequest.mockResolvedValueOnce({
        success: true,
        data: createMockWorkflow({ name: 'New Test Workflow' })
      });
      
      render(<WorkflowSettings />);

      const createButton = screen.getByRole('button', { name: /create workflow/i });
      await user.click(createButton);

      await user.type(screen.getByLabelText(/workflow name/i), 'New Test Workflow');
      const saveButton = screen.getByRole('button', { name: /save workflow/i });
      await user.click(saveButton);

      await waitFor(() => {
        expect(mockCreateNotice).toHaveBeenCalledWith(
          'success',
          'Workflow created successfully'
        );
      });
    });

    test('should handle workflow creation errors', async () => {
      const user = userEvent.setup();
      const WorkflowSettings = mockWorkflowSettings.render;

      mockApiRequest.mockRejectedValueOnce({
        message: 'Workflow name already exists'
      });
      
      render(<WorkflowSettings />);

      const createButton = screen.getByRole('button', { name: /create workflow/i });
      await user.click(createButton);

      await user.type(screen.getByLabelText(/workflow name/i), 'Duplicate Name');
      const saveButton = screen.getByRole('button', { name: /save workflow/i });
      await user.click(saveButton);

      await waitFor(() => {
        expect(mockCreateNotice).toHaveBeenCalledWith(
          'error',
          'Failed to create workflow: Workflow name already exists'
        );
      });
    });
  });

  describe('Workflow Editing', () => {
    test('should open edit form when workflow edit button clicked', async () => {
      const user = userEvent.setup();
      const workflows = [createMockWorkflow({ id: 1, name: 'Test Workflow' })];

      mockUseSelect.mockReturnValue({
        workflows,
        isLoading: false,
        hasError: false,
      });

      const WorkflowSettings = mockWorkflowSettings.render;
      render(<WorkflowSettings />);

      const editButton = screen.getByRole('button', { name: /edit/i });
      await user.click(editButton);

      expect(screen.getByDisplayValue('Test Workflow')).toBeInTheDocument();
    });

    test('should update workflow with changes', async () => {
      const user = userEvent.setup();
      const workflows = [createMockWorkflow({ 
        id: 1, 
        name: 'Original Name',
        description: 'Original description'
      })];

      mockUseSelect.mockReturnValue({
        workflows,
        isLoading: false,
        hasError: false,
      });

      mockApiRequest.mockResolvedValueOnce({
        success: true,
        data: createMockWorkflow({ 
          id: 1, 
          name: 'Updated Name',
          description: 'Updated description'
        })
      });

      const WorkflowSettings = mockWorkflowSettings.render;
      render(<WorkflowSettings />);

      const editButton = screen.getByRole('button', { name: /edit/i });
      await user.click(editButton);

      const nameInput = screen.getByDisplayValue('Original Name');
      await user.clear(nameInput);
      await user.type(nameInput, 'Updated Name');

      const saveButton = screen.getByRole('button', { name: /save changes/i });
      await user.click(saveButton);

      await waitFor(() => {
        expect(mockApiRequest).toHaveBeenCalledWith({
          path: '/wp-content-flow/v1/workflows/1',
          method: 'PUT',
          data: expect.objectContaining({
            name: 'Updated Name',
          }),
        });
      });
    });

    test('should cancel editing without saving changes', async () => {
      const user = userEvent.setup();
      const workflows = [createMockWorkflow({ id: 1, name: 'Test Workflow' })];

      mockUseSelect.mockReturnValue({
        workflows,
        isLoading: false,
        hasError: false,
      });

      const WorkflowSettings = mockWorkflowSettings.render;
      render(<WorkflowSettings />);

      const editButton = screen.getByRole('button', { name: /edit/i });
      await user.click(editButton);

      const nameInput = screen.getByDisplayValue('Test Workflow');
      await user.clear(nameInput);
      await user.type(nameInput, 'Modified Name');

      const cancelButton = screen.getByRole('button', { name: /cancel/i });
      await user.click(cancelButton);

      // Should return to list view without saving
      expect(screen.getByText('Test Workflow')).toBeInTheDocument();
      expect(screen.queryByDisplayValue('Modified Name')).not.toBeInTheDocument();
    });
  });

  describe('Workflow Configuration', () => {
    test('should configure AI provider settings', async () => {
      const user = userEvent.setup();
      const WorkflowSettings = mockWorkflowSettings.render;
      
      render(<WorkflowSettings />);

      const createButton = screen.getByRole('button', { name: /create workflow/i });
      await user.click(createButton);

      const providerSelect = screen.getByLabelText(/ai provider/i);
      await user.selectOptions(providerSelect, 'anthropic');

      expect(providerSelect).toHaveValue('anthropic');
    });

    test('should configure model parameters', async () => {
      const user = userEvent.setup();
      const WorkflowSettings = mockWorkflowSettings.render;
      
      render(<WorkflowSettings />);

      const createButton = screen.getByRole('button', { name: /create workflow/i });
      await user.click(createButton);

      // Configure temperature
      const temperatureSlider = screen.getByLabelText(/temperature/i);
      fireEvent.change(temperatureSlider, { target: { value: '0.9' } });
      expect(temperatureSlider).toHaveValue('0.9');

      // Configure max tokens
      const maxTokensInput = screen.getByLabelText(/max tokens/i);
      await user.clear(maxTokensInput);
      await user.type(maxTokensInput, '2000');
      expect(maxTokensInput).toHaveValue('2000');
    });

    test('should configure workflow triggers', async () => {
      const user = userEvent.setup();
      const WorkflowSettings = mockWorkflowSettings.render;
      
      render(<WorkflowSettings />);

      const createButton = screen.getByRole('button', { name: /create workflow/i });
      await user.click(createButton);

      const autoTriggerToggle = screen.getByLabelText(/auto trigger/i);
      await user.click(autoTriggerToggle);
      
      expect(autoTriggerToggle).toBeChecked();
    });

    test('should show advanced settings panel', async () => {
      const user = userEvent.setup();
      const WorkflowSettings = mockWorkflowSettings.render;
      
      render(<WorkflowSettings />);

      const createButton = screen.getByRole('button', { name: /create workflow/i });
      await user.click(createButton);

      const advancedPanel = screen.getByText(/advanced settings/i);
      expect(advancedPanel).toBeInTheDocument();

      await user.click(advancedPanel);

      expect(screen.getByLabelText(/custom prompt template/i)).toBeInTheDocument();
      expect(screen.getByLabelText(/post processing/i)).toBeInTheDocument();
    });
  });

  describe('Workflow Deletion', () => {
    test('should show confirmation dialog when delete button clicked', async () => {
      const user = userEvent.setup();
      const workflows = [createMockWorkflow({ id: 1, name: 'Test Workflow' })];

      mockUseSelect.mockReturnValue({
        workflows,
        isLoading: false,
        hasError: false,
      });

      const WorkflowSettings = mockWorkflowSettings.render;
      render(<WorkflowSettings />);

      const deleteButton = screen.getByRole('button', { name: /delete/i });
      await user.click(deleteButton);

      expect(screen.getByText(/are you sure/i)).toBeInTheDocument();
      expect(screen.getByText(/this action cannot be undone/i)).toBeInTheDocument();
    });

    test('should delete workflow when confirmed', async () => {
      const user = userEvent.setup();
      const workflows = [createMockWorkflow({ id: 1, name: 'Test Workflow' })];

      mockUseSelect.mockReturnValue({
        workflows,
        isLoading: false,
        hasError: false,
      });

      mockApiRequest.mockResolvedValueOnce({
        success: true,
        data: { message: 'Workflow deleted' }
      });

      const WorkflowSettings = mockWorkflowSettings.render;
      render(<WorkflowSettings />);

      const deleteButton = screen.getByRole('button', { name: /delete/i });
      await user.click(deleteButton);

      const confirmButton = screen.getByRole('button', { name: /confirm delete/i });
      await user.click(confirmButton);

      await waitFor(() => {
        expect(mockApiRequest).toHaveBeenCalledWith({
          path: '/wp-content-flow/v1/workflows/1',
          method: 'DELETE',
        });
      });
    });

    test('should cancel deletion when cancelled', async () => {
      const user = userEvent.setup();
      const workflows = [createMockWorkflow({ id: 1, name: 'Test Workflow' })];

      mockUseSelect.mockReturnValue({
        workflows,
        isLoading: false,
        hasError: false,
      });

      const WorkflowSettings = mockWorkflowSettings.render;
      render(<WorkflowSettings />);

      const deleteButton = screen.getByRole('button', { name: /delete/i });
      await user.click(deleteButton);

      const cancelButton = screen.getByRole('button', { name: /cancel/i });
      await user.click(cancelButton);

      // Confirmation dialog should close
      expect(screen.queryByText(/are you sure/i)).not.toBeInTheDocument();
      // Workflow should still be visible
      expect(screen.getByText('Test Workflow')).toBeInTheDocument();
    });
  });

  describe('Workflow Import/Export', () => {
    test('should export workflows as JSON', async () => {
      const user = userEvent.setup();
      const workflows = [
        createMockWorkflow({ id: 1, name: 'Workflow 1' }),
        createMockWorkflow({ id: 2, name: 'Workflow 2' }),
      ];

      mockUseSelect.mockReturnValue({
        workflows,
        isLoading: false,
        hasError: false,
      });

      // Mock URL.createObjectURL and link download
      global.URL.createObjectURL = jest.fn(() => 'blob:url');
      const mockClick = jest.fn();
      const mockLink = { click: mockClick, download: '', href: '' };
      jest.spyOn(document, 'createElement').mockReturnValue(mockLink);

      const WorkflowSettings = mockWorkflowSettings.render;
      render(<WorkflowSettings />);

      const exportButton = screen.getByRole('button', { name: /export workflows/i });
      await user.click(exportButton);

      expect(mockClick).toHaveBeenCalled();
      expect(mockLink.download).toContain('workflows');
      expect(mockLink.download).toContain('.json');
    });

    test('should import workflows from JSON file', async () => {
      const user = userEvent.setup();
      const WorkflowSettings = mockWorkflowSettings.render;
      
      render(<WorkflowSettings />);

      const importInput = screen.getByLabelText(/import workflows/i);
      
      const file = new File(
        [JSON.stringify([createMockWorkflow({ name: 'Imported Workflow' })])],
        'workflows.json',
        { type: 'application/json' }
      );

      await user.upload(importInput, file);

      await waitFor(() => {
        expect(mockApiRequest).toHaveBeenCalledWith({
          path: '/wp-content-flow/v1/workflows/import',
          method: 'POST',
          data: expect.objectContaining({
            workflows: expect.arrayContaining([
              expect.objectContaining({ name: 'Imported Workflow' })
            ])
          }),
        });
      });
    });

    test('should validate imported workflow format', async () => {
      const user = userEvent.setup();
      const WorkflowSettings = mockWorkflowSettings.render;
      
      render(<WorkflowSettings />);

      const importInput = screen.getByLabelText(/import workflows/i);
      
      const invalidFile = new File(['invalid json'], 'invalid.json', { 
        type: 'application/json' 
      });

      await user.upload(importInput, invalidFile);

      await waitFor(() => {
        expect(mockCreateNotice).toHaveBeenCalledWith(
          'error',
          'Invalid workflow file format'
        );
      });
    });
  });

  describe('Search and Filter', () => {
    test('should filter workflows by search term', async () => {
      const user = userEvent.setup();
      const workflows = [
        createMockWorkflow({ id: 1, name: 'Blog Post Generator' }),
        createMockWorkflow({ id: 2, name: 'Product Description' }),
        createMockWorkflow({ id: 3, name: 'Social Media Content' }),
      ];

      mockUseSelect.mockReturnValue({
        workflows,
        isLoading: false,
        hasError: false,
      });

      const WorkflowSettings = mockWorkflowSettings.render;
      render(<WorkflowSettings />);

      const searchInput = screen.getByPlaceholderText(/search workflows/i);
      await user.type(searchInput, 'Blog');

      expect(screen.getByText('Blog Post Generator')).toBeInTheDocument();
      expect(screen.queryByText('Product Description')).not.toBeInTheDocument();
      expect(screen.queryByText('Social Media Content')).not.toBeInTheDocument();
    });

    test('should filter workflows by provider', async () => {
      const user = userEvent.setup();
      const workflows = [
        createMockWorkflow({ id: 1, name: 'OpenAI Workflow', ai_provider: 'openai' }),
        createMockWorkflow({ id: 2, name: 'Claude Workflow', ai_provider: 'anthropic' }),
      ];

      mockUseSelect.mockReturnValue({
        workflows,
        isLoading: false,
        hasError: false,
      });

      const WorkflowSettings = mockWorkflowSettings.render;
      render(<WorkflowSettings />);

      const providerFilter = screen.getByLabelText(/filter by provider/i);
      await user.selectOptions(providerFilter, 'openai');

      expect(screen.getByText('OpenAI Workflow')).toBeInTheDocument();
      expect(screen.queryByText('Claude Workflow')).not.toBeInTheDocument();
    });
  });

  describe('Accessibility', () => {
    test('should have proper ARIA labels', () => {
      const WorkflowSettings = mockWorkflowSettings.render;
      render(<WorkflowSettings />);

      expect(screen.getByRole('button', { name: /create workflow/i })).toBeInTheDocument();
      expect(screen.getByRole('main')).toHaveAttribute('aria-label', 'Workflow Settings');
    });

    test('should support keyboard navigation', async () => {
      const user = userEvent.setup();
      const workflows = [createMockWorkflow({ id: 1, name: 'Test Workflow' })];

      mockUseSelect.mockReturnValue({
        workflows,
        isLoading: false,
        hasError: false,
      });

      const WorkflowSettings = mockWorkflowSettings.render;
      render(<WorkflowSettings />);

      // Should be able to navigate to buttons with keyboard
      await user.tab();
      expect(screen.getByRole('button', { name: /create workflow/i })).toHaveFocus();
      
      await user.tab();
      expect(screen.getByRole('button', { name: /edit/i })).toHaveFocus();
    });

    test('should announce loading states to screen readers', () => {
      mockUseSelect.mockReturnValue({
        workflows: [],
        isLoading: true,
        hasError: false,
      });

      const WorkflowSettings = mockWorkflowSettings.render;
      render(<WorkflowSettings />);

      const loadingStatus = screen.getByRole('status');
      expect(loadingStatus).toHaveTextContent(/loading workflows/i);
    });
  });
});

// Helper function to create mock workflow objects
function createMockWorkflow(overrides = {}) {
  return {
    id: 1,
    name: 'Test Workflow',
    description: 'A test workflow',
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
  };
}