/**
 * @jest-environment jsdom
 */

import '@testing-library/jest-dom';

// Mock WordPress dependencies
const mockRegisterStore = jest.fn();
const mockSelect = jest.fn();
const mockDispatch = jest.fn();
const mockApiRequest = jest.fn();
const mockCreateNotice = jest.fn();

global.wp = {
  ...global.wp,
  data: {
    ...global.wp.data,
    registerStore: mockRegisterStore,
    select: mockSelect,
    dispatch: mockDispatch,
    combineReducers: jest.fn((reducers) => (state = {}, action) => {
      const newState = {};
      Object.keys(reducers).forEach(key => {
        newState[key] = reducers[key](state[key], action);
      });
      return newState;
    }),
  },
  apiFetch: mockApiRequest,
  notices: {
    ...global.wp.notices,
    createNotice: mockCreateNotice,
  },
};

// Mock workflow data store module
let workflowDataStore;

describe('Workflow Data Store', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    jest.resetModules();
    
    // Setup default API responses
    mockApiRequest.mockImplementation((config) => {
      if (config.path?.includes('/workflows')) {
        if (config.method === 'POST') {
          return Promise.resolve({
            success: true,
            data: createMockWorkflow({ 
              id: Math.floor(Math.random() * 1000),
              name: config.data.name 
            })
          });
        }
        return Promise.resolve({
          success: true,
          data: [
            createMockWorkflow({ id: 1, name: 'Blog Post Workflow' }),
            createMockWorkflow({ id: 2, name: 'Product Description' }),
          ]
        });
      }
      return Promise.resolve({ success: true, data: {} });
    });

    // Import the data store after mocking
    workflowDataStore = require('./workflow-data-store.js');
  });

  describe('Store Registration', () => {
    test('should register workflow data store with WordPress', () => {
      expect(mockRegisterStore).toHaveBeenCalledWith(
        'wp-content-flow/workflows',
        expect.objectContaining({
          reducer: expect.any(Function),
          actions: expect.any(Object),
          selectors: expect.any(Object),
          controls: expect.any(Object),
          resolvers: expect.any(Object),
        })
      );
    });

    test('should register with correct store name', () => {
      const storeCall = mockRegisterStore.mock.calls[0];
      expect(storeCall[0]).toBe('wp-content-flow/workflows');
    });

    test('should have all required store properties', () => {
      const storeConfig = mockRegisterStore.mock.calls[0][1];
      
      expect(storeConfig).toHaveProperty('reducer');
      expect(storeConfig).toHaveProperty('actions');
      expect(storeConfig).toHaveProperty('selectors');
      expect(storeConfig).toHaveProperty('controls');
      expect(storeConfig).toHaveProperty('resolvers');
    });
  });

  describe('Reducer', () => {
    let reducer;

    beforeEach(() => {
      const storeConfig = mockRegisterStore.mock.calls[0][1];
      reducer = storeConfig.reducer;
    });

    test('should have correct initial state', () => {
      const initialState = reducer(undefined, { type: '@@INIT' });
      
      expect(initialState).toEqual({
        workflows: {
          items: [],
          isLoading: false,
          hasError: false,
          lastError: null,
        },
        suggestions: {
          items: [],
          isLoading: false,
        },
        ui: {
          selectedWorkflow: null,
          isCreating: false,
          isEditing: false,
        },
      });
    });

    test('should handle SET_WORKFLOWS action', () => {
      const workflows = [
        createMockWorkflow({ id: 1, name: 'Test Workflow 1' }),
        createMockWorkflow({ id: 2, name: 'Test Workflow 2' }),
      ];

      const state = reducer(undefined, {
        type: 'SET_WORKFLOWS',
        workflows,
      });

      expect(state.workflows.items).toEqual(workflows);
      expect(state.workflows.isLoading).toBe(false);
      expect(state.workflows.hasError).toBe(false);
    });

    test('should handle ADD_WORKFLOW action', () => {
      const initialState = {
        workflows: {
          items: [createMockWorkflow({ id: 1, name: 'Existing Workflow' })],
          isLoading: false,
          hasError: false,
          lastError: null,
        },
      };

      const newWorkflow = createMockWorkflow({ id: 2, name: 'New Workflow' });

      const state = reducer(initialState, {
        type: 'ADD_WORKFLOW',
        workflow: newWorkflow,
      });

      expect(state.workflows.items).toHaveLength(2);
      expect(state.workflows.items[1]).toEqual(newWorkflow);
    });

    test('should handle UPDATE_WORKFLOW action', () => {
      const initialState = {
        workflows: {
          items: [
            createMockWorkflow({ id: 1, name: 'Original Name' }),
            createMockWorkflow({ id: 2, name: 'Other Workflow' }),
          ],
          isLoading: false,
          hasError: false,
          lastError: null,
        },
      };

      const updatedWorkflow = createMockWorkflow({ 
        id: 1, 
        name: 'Updated Name' 
      });

      const state = reducer(initialState, {
        type: 'UPDATE_WORKFLOW',
        workflow: updatedWorkflow,
      });

      expect(state.workflows.items[0].name).toBe('Updated Name');
      expect(state.workflows.items[1].name).toBe('Other Workflow');
    });

    test('should handle REMOVE_WORKFLOW action', () => {
      const initialState = {
        workflows: {
          items: [
            createMockWorkflow({ id: 1, name: 'To Delete' }),
            createMockWorkflow({ id: 2, name: 'To Keep' }),
          ],
          isLoading: false,
          hasError: false,
          lastError: null,
        },
      };

      const state = reducer(initialState, {
        type: 'REMOVE_WORKFLOW',
        workflowId: 1,
      });

      expect(state.workflows.items).toHaveLength(1);
      expect(state.workflows.items[0].name).toBe('To Keep');
    });

    test('should handle SET_LOADING action', () => {
      const state = reducer(undefined, {
        type: 'SET_LOADING',
        isLoading: true,
      });

      expect(state.workflows.isLoading).toBe(true);
    });

    test('should handle SET_ERROR action', () => {
      const error = new Error('Test error');

      const state = reducer(undefined, {
        type: 'SET_ERROR',
        error,
      });

      expect(state.workflows.hasError).toBe(true);
      expect(state.workflows.lastError).toBe(error);
      expect(state.workflows.isLoading).toBe(false);
    });

    test('should handle SELECT_WORKFLOW action', () => {
      const workflow = createMockWorkflow({ id: 1, name: 'Selected Workflow' });

      const state = reducer(undefined, {
        type: 'SELECT_WORKFLOW',
        workflow,
      });

      expect(state.ui.selectedWorkflow).toEqual(workflow);
    });

    test('should handle SET_CREATING action', () => {
      const state = reducer(undefined, {
        type: 'SET_CREATING',
        isCreating: true,
      });

      expect(state.ui.isCreating).toBe(true);
    });

    test('should handle SET_EDITING action', () => {
      const state = reducer(undefined, {
        type: 'SET_EDITING',
        isEditing: true,
      });

      expect(state.ui.isEditing).toBe(true);
    });
  });

  describe('Actions', () => {
    let actions;

    beforeEach(() => {
      const storeConfig = mockRegisterStore.mock.calls[0][1];
      actions = storeConfig.actions;
    });

    test('should have setWorkflows action creator', () => {
      const workflows = [createMockWorkflow()];
      const action = actions.setWorkflows(workflows);

      expect(action).toEqual({
        type: 'SET_WORKFLOWS',
        workflows,
      });
    });

    test('should have addWorkflow action creator', () => {
      const workflow = createMockWorkflow();
      const action = actions.addWorkflow(workflow);

      expect(action).toEqual({
        type: 'ADD_WORKFLOW',
        workflow,
      });
    });

    test('should have updateWorkflow action creator', () => {
      const workflow = createMockWorkflow({ id: 1, name: 'Updated' });
      const action = actions.updateWorkflow(workflow);

      expect(action).toEqual({
        type: 'UPDATE_WORKFLOW',
        workflow,
      });
    });

    test('should have removeWorkflow action creator', () => {
      const action = actions.removeWorkflow(123);

      expect(action).toEqual({
        type: 'REMOVE_WORKFLOW',
        workflowId: 123,
      });
    });

    test('should have setLoading action creator', () => {
      const action = actions.setLoading(true);

      expect(action).toEqual({
        type: 'SET_LOADING',
        isLoading: true,
      });
    });

    test('should have setError action creator', () => {
      const error = new Error('Test error');
      const action = actions.setError(error);

      expect(action).toEqual({
        type: 'SET_ERROR',
        error,
      });
    });

    test('should have selectWorkflow action creator', () => {
      const workflow = createMockWorkflow();
      const action = actions.selectWorkflow(workflow);

      expect(action).toEqual({
        type: 'SELECT_WORKFLOW',
        workflow,
      });
    });

    test('should have createWorkflow thunk action', () => {
      const createWorkflow = actions.createWorkflow;
      expect(typeof createWorkflow).toBe('function');
      
      const workflowData = { 
        name: 'New Workflow',
        description: 'Test description' 
      };

      const thunk = createWorkflow(workflowData);
      expect(typeof thunk).toBe('object');
      expect(thunk.type).toBe('API_REQUEST');
    });

    test('should have updateWorkflowById thunk action', () => {
      const updateWorkflowById = actions.updateWorkflowById;
      expect(typeof updateWorkflowById).toBe('function');
      
      const thunk = updateWorkflowById(1, { name: 'Updated Name' });
      expect(typeof thunk).toBe('object');
      expect(thunk.type).toBe('API_REQUEST');
    });

    test('should have deleteWorkflow thunk action', () => {
      const deleteWorkflow = actions.deleteWorkflow;
      expect(typeof deleteWorkflow).toBe('function');
      
      const thunk = deleteWorkflow(1);
      expect(typeof thunk).toBe('object');
      expect(thunk.type).toBe('API_REQUEST');
    });
  });

  describe('Selectors', () => {
    let selectors;
    let mockState;

    beforeEach(() => {
      const storeConfig = mockRegisterStore.mock.calls[0][1];
      selectors = storeConfig.selectors;

      mockState = {
        workflows: {
          items: [
            createMockWorkflow({ id: 1, name: 'Active Workflow', status: 'active' }),
            createMockWorkflow({ id: 2, name: 'Draft Workflow', status: 'draft' }),
          ],
          isLoading: false,
          hasError: false,
          lastError: null,
        },
        suggestions: {
          items: [],
          isLoading: false,
        },
        ui: {
          selectedWorkflow: createMockWorkflow({ id: 1 }),
          isCreating: false,
          isEditing: false,
        },
      };
    });

    test('should select all workflows', () => {
      const workflows = selectors.getWorkflows(mockState);
      expect(workflows).toEqual(mockState.workflows.items);
      expect(workflows).toHaveLength(2);
    });

    test('should select workflow by id', () => {
      const workflow = selectors.getWorkflowById(mockState, 1);
      expect(workflow).toEqual(mockState.workflows.items[0]);
      expect(workflow.name).toBe('Active Workflow');
    });

    test('should return null for non-existent workflow id', () => {
      const workflow = selectors.getWorkflowById(mockState, 999);
      expect(workflow).toBeNull();
    });

    test('should select workflows by status', () => {
      const activeWorkflows = selectors.getWorkflowsByStatus(mockState, 'active');
      expect(activeWorkflows).toHaveLength(1);
      expect(activeWorkflows[0].name).toBe('Active Workflow');

      const draftWorkflows = selectors.getWorkflowsByStatus(mockState, 'draft');
      expect(draftWorkflows).toHaveLength(1);
      expect(draftWorkflows[0].name).toBe('Draft Workflow');
    });

    test('should select loading state', () => {
      const isLoading = selectors.isLoadingWorkflows(mockState);
      expect(isLoading).toBe(false);
    });

    test('should select error state', () => {
      const hasError = selectors.hasWorkflowsError(mockState);
      expect(hasError).toBe(false);

      const lastError = selectors.getLastError(mockState);
      expect(lastError).toBeNull();
    });

    test('should select UI state', () => {
      const selectedWorkflow = selectors.getSelectedWorkflow(mockState);
      expect(selectedWorkflow).toEqual(mockState.ui.selectedWorkflow);

      const isCreating = selectors.isCreatingWorkflow(mockState);
      expect(isCreating).toBe(false);

      const isEditing = selectors.isEditingWorkflow(mockState);
      expect(isEditing).toBe(false);
    });

    test('should get workflow count', () => {
      const count = selectors.getWorkflowCount(mockState);
      expect(count).toBe(2);
    });

    test('should check if workflows exist', () => {
      const hasWorkflows = selectors.hasWorkflows(mockState);
      expect(hasWorkflows).toBe(true);

      const emptyState = {
        workflows: { items: [] }
      };
      const hasNoWorkflows = selectors.hasWorkflows(emptyState);
      expect(hasNoWorkflows).toBe(false);
    });
  });

  describe('Controls', () => {
    let controls;

    beforeEach(() => {
      const storeConfig = mockRegisterStore.mock.calls[0][1];
      controls = storeConfig.controls;
    });

    test('should have API_REQUEST control', () => {
      expect(controls).toHaveProperty('API_REQUEST');
      expect(typeof controls.API_REQUEST).toBe('function');
    });

    test('should handle API_REQUEST control for GET requests', async () => {
      const action = {
        type: 'API_REQUEST',
        path: '/wp-content-flow/v1/workflows',
        method: 'GET',
      };

      const result = await controls.API_REQUEST(action);
      
      expect(mockApiRequest).toHaveBeenCalledWith({
        path: '/wp-content-flow/v1/workflows',
        method: 'GET',
      });
      expect(result).toEqual({
        success: true,
        data: expect.any(Array),
      });
    });

    test('should handle API_REQUEST control for POST requests', async () => {
      const workflowData = { name: 'New Workflow' };
      const action = {
        type: 'API_REQUEST',
        path: '/wp-content-flow/v1/workflows',
        method: 'POST',
        data: workflowData,
      };

      const result = await controls.API_REQUEST(action);
      
      expect(mockApiRequest).toHaveBeenCalledWith({
        path: '/wp-content-flow/v1/workflows',
        method: 'POST',
        data: workflowData,
      });
      expect(result.success).toBe(true);
      expect(result.data).toHaveProperty('name', 'New Workflow');
    });

    test('should handle API errors in control', async () => {
      mockApiRequest.mockRejectedValueOnce(new Error('API Error'));

      const action = {
        type: 'API_REQUEST',
        path: '/wp-content-flow/v1/workflows/invalid',
        method: 'GET',
      };

      await expect(controls.API_REQUEST(action)).rejects.toThrow('API Error');
    });
  });

  describe('Resolvers', () => {
    let resolvers;

    beforeEach(() => {
      const storeConfig = mockRegisterStore.mock.calls[0][1];
      resolvers = storeConfig.resolvers;
    });

    test('should have getWorkflows resolver', () => {
      expect(resolvers).toHaveProperty('getWorkflows');
      expect(typeof resolvers.getWorkflows).toBe('function');
    });

    test('should have getWorkflowById resolver', () => {
      expect(resolvers).toHaveProperty('getWorkflowById');
      expect(typeof resolvers.getWorkflowById).toBe('function');
    });

    test('should resolve workflows from API', () => {
      const resolver = resolvers.getWorkflows();
      expect(resolver.type).toBe('API_REQUEST');
      expect(resolver.path).toBe('/wp-content-flow/v1/workflows');
      expect(resolver.method).toBe('GET');
    });

    test('should resolve specific workflow by ID', () => {
      const resolver = resolvers.getWorkflowById(123);
      expect(resolver.type).toBe('API_REQUEST');
      expect(resolver.path).toBe('/wp-content-flow/v1/workflows/123');
      expect(resolver.method).toBe('GET');
    });
  });

  describe('Integration with WordPress Data', () => {
    test('should work with wp.data.select', () => {
      mockSelect.mockReturnValue({
        getWorkflows: jest.fn(() => [createMockWorkflow()]),
        isLoadingWorkflows: jest.fn(() => false),
        hasWorkflowsError: jest.fn(() => false),
      });

      const workflows = mockSelect('wp-content-flow/workflows').getWorkflows();
      expect(workflows).toHaveLength(1);
    });

    test('should work with wp.data.dispatch', () => {
      const mockActions = {
        setWorkflows: jest.fn(),
        setLoading: jest.fn(),
        setError: jest.fn(),
        createWorkflow: jest.fn(),
      };

      mockDispatch.mockReturnValue(mockActions);

      const dispatch = mockDispatch('wp-content-flow/workflows');
      dispatch.setLoading(true);

      expect(mockActions.setLoading).toHaveBeenCalledWith(true);
    });
  });

  describe('Error Handling', () => {
    test('should handle API errors gracefully', async () => {
      const storeConfig = mockRegisterStore.mock.calls[0][1];
      const controls = storeConfig.controls;

      mockApiRequest.mockRejectedValueOnce({
        message: 'Network error',
        status: 500,
      });

      const action = {
        type: 'API_REQUEST',
        path: '/wp-content-flow/v1/workflows',
        method: 'GET',
      };

      try {
        await controls.API_REQUEST(action);
      } catch (error) {
        expect(error.message).toBe('Network error');
        expect(error.status).toBe(500);
      }
    });

    test('should handle malformed API responses', async () => {
      const storeConfig = mockRegisterStore.mock.calls[0][1];
      const controls = storeConfig.controls;

      mockApiRequest.mockResolvedValueOnce({
        success: false,
        error: 'Invalid data format',
      });

      const action = {
        type: 'API_REQUEST',
        path: '/wp-content-flow/v1/workflows',
        method: 'GET',
      };

      const result = await controls.API_REQUEST(action);
      expect(result.success).toBe(false);
      expect(result.error).toBe('Invalid data format');
    });
  });

  describe('Performance Optimizations', () => {
    test('should memoize selector results', () => {
      const storeConfig = mockRegisterStore.mock.calls[0][1];
      const selectors = storeConfig.selectors;

      const state = {
        workflows: {
          items: [createMockWorkflow({ id: 1 })],
        },
      };

      // Call selector multiple times with same state
      const result1 = selectors.getWorkflows(state);
      const result2 = selectors.getWorkflows(state);

      // Should return the same reference (memoized)
      expect(result1).toBe(result2);
    });

    test('should handle large datasets efficiently', () => {
      const storeConfig = mockRegisterStore.mock.calls[0][1];
      const reducer = storeConfig.reducer;

      // Create a large dataset
      const workflows = Array.from({ length: 1000 }, (_, i) => 
        createMockWorkflow({ id: i + 1, name: `Workflow ${i + 1}` })
      );

      const startTime = Date.now();
      const state = reducer(undefined, {
        type: 'SET_WORKFLOWS',
        workflows,
      });
      const endTime = Date.now();

      // Should process large dataset quickly (under 50ms)
      expect(endTime - startTime).toBeLessThan(50);
      expect(state.workflows.items).toHaveLength(1000);
    });
  });

  describe('Data Synchronization', () => {
    test('should maintain data consistency across actions', () => {
      const storeConfig = mockRegisterStore.mock.calls[0][1];
      const reducer = storeConfig.reducer;

      let state = reducer(undefined, { type: '@@INIT' });

      // Add workflows
      const workflows = [
        createMockWorkflow({ id: 1, name: 'Workflow 1' }),
        createMockWorkflow({ id: 2, name: 'Workflow 2' }),
      ];

      state = reducer(state, {
        type: 'SET_WORKFLOWS',
        workflows,
      });

      expect(state.workflows.items).toHaveLength(2);

      // Update workflow
      const updatedWorkflow = createMockWorkflow({ 
        id: 1, 
        name: 'Updated Workflow 1' 
      });

      state = reducer(state, {
        type: 'UPDATE_WORKFLOW',
        workflow: updatedWorkflow,
      });

      expect(state.workflows.items[0].name).toBe('Updated Workflow 1');
      expect(state.workflows.items[1].name).toBe('Workflow 2');

      // Remove workflow
      state = reducer(state, {
        type: 'REMOVE_WORKFLOW',
        workflowId: 1,
      });

      expect(state.workflows.items).toHaveLength(1);
      expect(state.workflows.items[0].name).toBe('Workflow 2');
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