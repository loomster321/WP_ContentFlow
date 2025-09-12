/**
 * Content Improvement Toolbar
 * 
 * This component adds an AI improvement toolbar that appears when text is selected
 * in the Gutenberg editor, providing contextual AI suggestions.
 */

import { __ } from '@wordpress/i18n';
import { Button, Popover, ButtonGroup, Spinner } from '@wordpress/components';
import { useState, useEffect, render, createElement } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { create, insert, toHTMLString } from '@wordpress/rich-text';
import apiFetch from '@wordpress/api-fetch';
// Use simple text icons instead of imports to avoid bundle issues
const brainIcon = '🧠';
const checkIcon = '✓';  
const closeIcon = '✕';

/**
 * Content Improvement Toolbar Component
 */
export function ContentImprovementToolbar() {
    const [ isVisible, setIsVisible ] = useState( false );
    const [ selectedText, setSelectedText ] = useState( '' );
    const [ selectionRange, setSelectionRange ] = useState( null );
    const [ suggestions, setSuggestions ] = useState( [] );
    const [ isLoading, setIsLoading ] = useState( false );
    const [ error, setError ] = useState( '' );
    
    // Get active workflows
    const workflows = useSelect( ( select ) => {
        const workflowStore = select( 'wp-content-flow/workflows' );
        return workflowStore ? workflowStore.getActiveWorkflows() : [];
    }, [] );
    
    const { createNotice } = useDispatch( 'core/notices' );
    
    // Monitor text selection
    useEffect( () => {
        const handleSelectionChange = () => {
            const selection = window.getSelection();
            
            if ( selection.rangeCount > 0 && ! selection.isCollapsed ) {
                const range = selection.getRangeAt( 0 );
                const text = selection.toString().trim();
                
                // Only show toolbar for meaningful text selections
                if ( text.length > 10 && isInBlockEditor( range ) ) {
                    setSelectedText( text );
                    setSelectionRange( range );
                    setIsVisible( true );
                } else {
                    hideToolbar();
                }
            } else {
                hideToolbar();
            }
        };
        
        document.addEventListener( 'selectionchange', handleSelectionChange );
        
        return () => {
            document.removeEventListener( 'selectionchange', handleSelectionChange );
        };
    }, [] );
    
    /**
     * Check if selection is within block editor
     */
    const isInBlockEditor = ( range ) => {
        const container = range.commonAncestorContainer;
        const blockEditor = container.ownerDocument?.querySelector( '.block-editor-writing-flow' );
        return blockEditor && blockEditor.contains( container );
    };
    
    /**
     * Hide the improvement toolbar
     */
    const hideToolbar = () => {
        setIsVisible( false );
        setSelectedText( '' );
        setSelectionRange( null );
        setSuggestions( [] );
        setError( '' );
    };
    
    /**
     * Request AI improvements for selected text
     */
    const requestImprovement = async ( improvementType ) => {
        if ( ! selectedText || workflows.length === 0 ) {
            return;
        }
        
        setIsLoading( true );
        setError( '' );
        setSuggestions( [] );
        
        try {
            // Use the first active workflow (could be made configurable)
            const workflowId = workflows[0].id;
            
            const response = await apiFetch( {
                path: '/wp-content-flow/v1/ai/improve',
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpContentFlow.nonce
                },
                data: {
                    content: selectedText,
                    workflow_id: workflowId,
                    improvement_type: improvementType
                }
            } );
            
            setSuggestions( response );
            
        } catch ( apiError ) {
            setError( __( 'Failed to get AI suggestions. Please try again.', 'wp-content-flow' ) );
        } finally {
            setIsLoading( false );
        }
    };
    
    /**
     * Apply suggestion to the selected text
     */
    const applySuggestion = ( suggestion ) => {
        if ( ! selectionRange || ! suggestion.suggested_content ) {
            return;
        }
        
        try {
            // Replace selected text with improved content
            selectionRange.deleteContents();
            
            const textNode = document.createTextNode( suggestion.suggested_content );
            selectionRange.insertNode( textNode );
            
            // Clear selection and hide toolbar
            window.getSelection().removeAllRanges();
            hideToolbar();
            
            createNotice( 'success', __( 'Text improved successfully!', 'wp-content-flow' ), {
                type: 'snackbar',
                isDismissible: true
            } );
            
        } catch ( applyError ) {
            setError( __( 'Failed to apply suggestion. Please try manually.', 'wp-content-flow' ) );
        }
    };
    
    /**
     * Get toolbar position based on selection
     */
    const getToolbarPosition = () => {
        if ( ! selectionRange ) {
            return null;
        }
        
        const rect = selectionRange.getBoundingClientRect();
        return {
            x: rect.left + ( rect.width / 2 ),
            y: rect.top - 10
        };
    };
    
    if ( ! isVisible || ! selectionRange ) {
        return null;
    }
    
    const position = getToolbarPosition();
    
    return (
        <Popover
            position="top center"
            anchorRef={ {
                getBoundingClientRect: () => ( {
                    x: position.x,
                    y: position.y,
                    width: 0,
                    height: 0,
                    top: position.y,
                    right: position.x,
                    bottom: position.y,
                    left: position.x
                } )
            } }
            onClose={ hideToolbar }
            className="wp-content-flow-improvement-toolbar"
        >
            <div className="improvement-toolbar-content">
                { error && (
                    <div className="toolbar-error">
                        <span>{ error }</span>
                        <Button
                            size="small"
                            onClick={ () => setError( '' ) }
                        >
                            { closeIcon }
                        </Button>
                    </div>
                ) }
                
                { suggestions.length > 0 ? (
                    <div className="suggestions-list">
                        <div className="suggestions-header">
                            <strong>{ __( 'AI Suggestions:', 'wp-content-flow' ) }</strong>
                        </div>
                        { suggestions.map( ( suggestion, index ) => (
                            <div key={ index } className="suggestion-item">
                                <div className="suggestion-content">
                                    { suggestion.suggested_content }
                                </div>
                                <div className="suggestion-actions">
                                    <Button
                                        size="small"
                                        variant="primary"
                                        onClick={ () => applySuggestion( suggestion ) }
                                    >
                                        { checkIcon } { __( 'Apply', 'wp-content-flow' ) }
                                    </Button>
                                    <span className="confidence-score">
                                        { Math.round( suggestion.confidence_score * 100 ) }%
                                    </span>
                                </div>
                            </div>
                        ) ) }
                    </div>
                ) : isLoading ? (
                    <div className="toolbar-loading">
                        <Spinner />
                        <span>{ __( 'Getting AI suggestions...', 'wp-content-flow' ) }</span>
                    </div>
                ) : (
                    <div className="improvement-options">
                        <div className="toolbar-header">
                            <span>{ brainIcon } { __( 'Improve with AI', 'wp-content-flow' ) }</span>
                        </div>
                        <ButtonGroup>
                            <Button
                                size="small"
                                onClick={ () => requestImprovement( 'grammar' ) }
                                disabled={ workflows.length === 0 }
                            >
                                { __( 'Grammar', 'wp-content-flow' ) }
                            </Button>
                            <Button
                                size="small"
                                onClick={ () => requestImprovement( 'style' ) }
                                disabled={ workflows.length === 0 }
                            >
                                { __( 'Style', 'wp-content-flow' ) }
                            </Button>
                            <Button
                                size="small"
                                onClick={ () => requestImprovement( 'clarity' ) }
                                disabled={ workflows.length === 0 }
                            >
                                { __( 'Clarity', 'wp-content-flow' ) }
                            </Button>
                            <Button
                                size="small"
                                onClick={ () => requestImprovement( 'engagement' ) }
                                disabled={ workflows.length === 0 }
                            >
                                { __( 'Engagement', 'wp-content-flow' ) }
                            </Button>
                            <Button
                                size="small"
                                onClick={ () => requestImprovement( 'seo' ) }
                                disabled={ workflows.length === 0 }
                            >
                                { __( 'SEO', 'wp-content-flow' ) }
                            </Button>
                        </ButtonGroup>
                        { workflows.length === 0 && (
                            <div className="no-workflows-notice">
                                { __( 'No active workflows found. Please create a workflow first.', 'wp-content-flow' ) }
                            </div>
                        ) }
                    </div>
                ) }
            </div>
        </Popover>
    );
}

// Initialize the improvement toolbar
document.addEventListener( 'DOMContentLoaded', () => {
    // Only initialize in the block editor
    if ( document.querySelector( '.block-editor-page' ) ) {
        // Create container for the toolbar
        const toolbarContainer = document.createElement( 'div' );
        toolbarContainer.id = 'wp-content-flow-improvement-toolbar';
        document.body.appendChild( toolbarContainer );
        
        // Render the toolbar
        render( createElement( ContentImprovementToolbar ), toolbarContainer );
    }
} );

export default ContentImprovementToolbar;