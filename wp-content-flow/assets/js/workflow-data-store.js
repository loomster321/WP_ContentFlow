/**
 * WordPress Data Store for AI Workflows
 * 
 * This provides a centralized data store for workflow management
 * in the Gutenberg editor, following WordPress data patterns.
 */

import { register, createReduxStore } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

// Store name
const STORE_NAME = 'wp-content-flow/workflows';

// Action types
const ACTIONS = {
    SET_WORKFLOWS: 'SET_WORKFLOWS',
    SET_LOADING: 'SET_LOADING',
    SET_ERROR: 'SET_ERROR',
    ADD_WORKFLOW: 'ADD_WORKFLOW',
    UPDATE_WORKFLOW: 'UPDATE_WORKFLOW',
    DELETE_WORKFLOW: 'DELETE_WORKFLOW',
    SET_GENERATING: 'SET_GENERATING'
};

// Initial state
const DEFAULT_STATE = {
    workflows: [],
    loading: false,
    error: null,
    generating: {}
};

// Actions
const actions = {
    setWorkflows: ( workflows ) => ( {
        type: ACTIONS.SET_WORKFLOWS,
        workflows
    } ),
    
    setLoading: ( loading ) => ( {
        type: ACTIONS.SET_LOADING,
        loading
    } ),
    
    setError: ( error ) => ( {
        type: ACTIONS.SET_ERROR,
        error
    } ),
    
    addWorkflow: ( workflow ) => ( {
        type: ACTIONS.ADD_WORKFLOW,
        workflow
    } ),
    
    updateWorkflow: ( workflow ) => ( {
        type: ACTIONS.UPDATE_WORKFLOW,
        workflow
    } ),
    
    deleteWorkflow: ( workflowId ) => ( {
        type: ACTIONS.DELETE_WORKFLOW,
        workflowId
    } ),
    
    setGenerating: ( workflowId, generating ) => ( {
        type: ACTIONS.SET_GENERATING,
        workflowId,
        generating
    } ),
    
    // Async actions
    *fetchWorkflows() {
        yield actions.setLoading( true );
        yield actions.setError( null );
        
        try {
            const workflows = yield apiFetch( {
                path: '/wp-content-flow/v1/workflows'
            } );
            
            yield actions.setWorkflows( workflows.workflows || [] );
        } catch ( error ) {
            yield actions.setError( error.message );
        } finally {
            yield actions.setLoading( false );
        }
    },
    
    *createWorkflow( workflowData ) {
        yield actions.setLoading( true );
        yield actions.setError( null );
        
        try {
            const workflow = yield apiFetch( {
                path: '/wp-content-flow/v1/workflows',
                method: 'POST',
                data: workflowData
            } );
            
            yield actions.addWorkflow( workflow );
            return workflow;
        } catch ( error ) {
            yield actions.setError( error.message );
            throw error;
        } finally {
            yield actions.setLoading( false );
        }
    }
};

// Selectors
const selectors = {
    getWorkflows: ( state ) => state.workflows,
    
    getWorkflow: ( state, workflowId ) => {
        return state.workflows.find( workflow => workflow.id === workflowId );
    },
    
    getActiveWorkflows: ( state ) => {
        return state.workflows.filter( workflow => workflow.status === 'active' );
    },
    
    isLoading: ( state ) => state.loading,
    
    getError: ( state ) => state.error,
    
    isGenerating: ( state, workflowId ) => {
        return !! state.generating[ workflowId ];
    }
};

// Reducer
const reducer = ( state = DEFAULT_STATE, action ) => {
    switch ( action.type ) {
        case ACTIONS.SET_WORKFLOWS:
            return {
                ...state,
                workflows: action.workflows
            };
            
        case ACTIONS.SET_LOADING:
            return {
                ...state,
                loading: action.loading
            };
            
        case ACTIONS.SET_ERROR:
            return {
                ...state,
                error: action.error
            };
            
        case ACTIONS.ADD_WORKFLOW:
            return {
                ...state,
                workflows: [ ...state.workflows, action.workflow ]
            };
            
        case ACTIONS.UPDATE_WORKFLOW:
            return {
                ...state,
                workflows: state.workflows.map( workflow => 
                    workflow.id === action.workflow.id ? action.workflow : workflow
                )
            };
            
        case ACTIONS.DELETE_WORKFLOW:
            return {
                ...state,
                workflows: state.workflows.filter( workflow => workflow.id !== action.workflowId )
            };
            
        case ACTIONS.SET_GENERATING:
            return {
                ...state,
                generating: {
                    ...state.generating,
                    [ action.workflowId ]: action.generating
                }
            };
            
        default:
            return state;
    }
};

// Create and register the store
const store = createReduxStore( STORE_NAME, {
    reducer,
    actions,
    selectors,
} );

register( store );

export { STORE_NAME };