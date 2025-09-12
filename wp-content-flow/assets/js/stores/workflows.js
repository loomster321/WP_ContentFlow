/**
 * WordPress Data Store for Workflows
 * 
 * Registers a data store for managing workflows in the block editor
 */

import apiFetch from '@wordpress/api-fetch';
import { createReduxStore, register } from '@wordpress/data';

// Default state
const DEFAULT_STATE = {
    workflows: [],
    isLoading: false,
    error: null,
    activeWorkflow: null
};

// Actions
const actions = {
    setWorkflows(workflows) {
        return {
            type: 'SET_WORKFLOWS',
            workflows
        };
    },
    
    setLoading(isLoading) {
        return {
            type: 'SET_LOADING',
            isLoading
        };
    },
    
    setError(error) {
        return {
            type: 'SET_ERROR',
            error
        };
    },
    
    setActiveWorkflow(workflow) {
        return {
            type: 'SET_ACTIVE_WORKFLOW',
            workflow
        };
    },
    
    // Async action to fetch workflows
    *fetchWorkflows() {
        yield actions.setLoading(true);
        yield actions.setError(null);
        
        try {
            const workflows = yield apiFetch({
                path: '/wp-content-flow/v1/workflows',
                method: 'GET'
            });
            
            // If no workflows returned, provide defaults
            if (!workflows || workflows.length === 0) {
                const defaultWorkflows = [
                    {
                        id: 1,
                        name: 'Blog Post Workflow',
                        description: 'Generate blog posts',
                        ai_provider: 'openai',
                        status: 'active'
                    },
                    {
                        id: 2,
                        name: 'Product Description',
                        description: 'Create product descriptions',
                        ai_provider: 'openai',
                        status: 'active'
                    },
                    {
                        id: 3,
                        name: 'Social Media Content',
                        description: 'Generate social media posts',
                        ai_provider: 'openai',
                        status: 'active'
                    }
                ];
                yield actions.setWorkflows(defaultWorkflows);
            } else {
                yield actions.setWorkflows(workflows);
            }
        } catch (error) {
            console.error('Failed to fetch workflows:', error);
            
            // On error, provide default workflows as fallback
            const fallbackWorkflows = [
                {
                    id: 1,
                    name: 'Blog Post Workflow',
                    description: 'Generate blog posts',
                    ai_provider: 'openai',
                    status: 'active'
                },
                {
                    id: 2,
                    name: 'Product Description',
                    description: 'Create product descriptions',
                    ai_provider: 'openai',
                    status: 'active'
                },
                {
                    id: 3,
                    name: 'Social Media Content',
                    description: 'Generate social media posts',
                    ai_provider: 'openai',
                    status: 'active'
                }
            ];
            
            yield actions.setWorkflows(fallbackWorkflows);
            yield actions.setError(error.message || 'Failed to load workflows');
        } finally {
            yield actions.setLoading(false);
        }
    },
    
    // Async action to create a workflow
    *createWorkflow(workflow) {
        yield actions.setLoading(true);
        
        try {
            const newWorkflow = yield apiFetch({
                path: '/wp-content-flow/v1/workflows',
                method: 'POST',
                data: workflow
            });
            
            const workflows = yield select.getWorkflows();
            yield actions.setWorkflows([...workflows, newWorkflow]);
            
            return newWorkflow;
        } catch (error) {
            yield actions.setError(error.message);
            throw error;
        } finally {
            yield actions.setLoading(false);
        }
    },
    
    // Async action to update a workflow
    *updateWorkflow(id, updates) {
        yield actions.setLoading(true);
        
        try {
            const updatedWorkflow = yield apiFetch({
                path: `/wp-content-flow/v1/workflows/${id}`,
                method: 'PUT',
                data: updates
            });
            
            const workflows = yield select.getWorkflows();
            const updatedWorkflows = workflows.map(w => 
                w.id === id ? updatedWorkflow : w
            );
            yield actions.setWorkflows(updatedWorkflows);
            
            return updatedWorkflow;
        } catch (error) {
            yield actions.setError(error.message);
            throw error;
        } finally {
            yield actions.setLoading(false);
        }
    },
    
    // Async action to delete a workflow
    *deleteWorkflow(id) {
        yield actions.setLoading(true);
        
        try {
            yield apiFetch({
                path: `/wp-content-flow/v1/workflows/${id}`,
                method: 'DELETE'
            });
            
            const workflows = yield select.getWorkflows();
            const filteredWorkflows = workflows.filter(w => w.id !== id);
            yield actions.setWorkflows(filteredWorkflows);
        } catch (error) {
            yield actions.setError(error.message);
            throw error;
        } finally {
            yield actions.setLoading(false);
        }
    }
};

// Selectors
const selectors = {
    getWorkflows(state) {
        return state.workflows;
    },
    
    getActiveWorkflows(state) {
        return state.workflows.filter(w => w.status === 'active');
    },
    
    getWorkflowById(state, id) {
        return state.workflows.find(w => w.id === id);
    },
    
    getWorkflowByName(state, name) {
        return state.workflows.find(w => w.name === name);
    },
    
    isLoading(state) {
        return state.isLoading;
    },
    
    getError(state) {
        return state.error;
    },
    
    getActiveWorkflow(state) {
        return state.activeWorkflow;
    },
    
    hasWorkflows(state) {
        return state.workflows.length > 0;
    }
};

// Reducer
function reducer(state = DEFAULT_STATE, action) {
    switch (action.type) {
        case 'SET_WORKFLOWS':
            return {
                ...state,
                workflows: action.workflows,
                error: null
            };
            
        case 'SET_LOADING':
            return {
                ...state,
                isLoading: action.isLoading
            };
            
        case 'SET_ERROR':
            return {
                ...state,
                error: action.error
            };
            
        case 'SET_ACTIVE_WORKFLOW':
            return {
                ...state,
                activeWorkflow: action.workflow
            };
            
        default:
            return state;
    }
}

// Create and register the store
const store = createReduxStore('wp-content-flow/workflows', {
    reducer,
    actions,
    selectors
});

register(store);

// Export for use in other modules
export default store;