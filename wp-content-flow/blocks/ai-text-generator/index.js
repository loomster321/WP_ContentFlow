/**
 * AI Text Generator Block
 * 
 * This Gutenberg block allows users to generate AI content directly in the editor.
 * It implements the block contract from the JavaScript test specifications.
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls, BlockControls } from '@wordpress/block-editor';
import { 
    PanelBody, 
    TextareaControl, 
    SelectControl, 
    Button, 
    ToolbarGroup, 
    ToolbarButton,
    Spinner,
    Notice
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
// Use a simple string icon for now to avoid import issues
const aiIcon = 'admin-generic';

// Block configuration matching contract from test_ai_text_block.js
const AI_TEXT_BLOCK_CONTRACT = {
    name: 'wp-content-flow/ai-text',
    title: __( 'AI Text Generator', 'wp-content-flow' ),
    category: 'text',
    icon: aiIcon,
    description: __( 'Generate content using AI with customizable workflows and prompts.', 'wp-content-flow' ),
    keywords: [ __( 'ai', 'wp-content-flow' ), __( 'content', 'wp-content-flow' ), __( 'generate', 'wp-content-flow' ) ],
    supports: {
        html: false,
        anchor: true,
        customClassName: true,
        spacing: {
            margin: true,
            padding: true
        }
    },
    attributes: {
        content: {
            type: 'string',
            default: ''
        },
        workflowId: {
            type: 'number',
            default: 0
        },
        prompt: {
            type: 'string',
            default: ''
        },
        isGenerating: {
            type: 'boolean',
            default: false
        },
        lastGenerated: {
            type: 'string',
            default: ''
        },
        confidence: {
            type: 'number',
            default: 0
        }
    },
    edit: EditComponent,
    save: SaveComponent
};

/**
 * Block Edit Component
 */
function EditComponent( { attributes, setAttributes, isSelected } ) {
    const { content, workflowId, prompt, isGenerating, confidence } = attributes;
    const [ error, setError ] = useState( '' );
    const [ validationErrors, setValidationErrors ] = useState( {} );
    
    // Get workflows from WordPress data store
    const workflows = useSelect( ( select ) => {
        const { getWorkflows } = select( 'wp-content-flow/workflows' ) || {};
        return getWorkflows ? getWorkflows() : [];
    }, [] );
    
    const { createNotice } = useDispatch( 'core/notices' );
    
    useEffect( () => {
        // Validate required fields
        const errors = {};
        
        if ( ! prompt.trim() ) {
            errors.prompt = __( 'Prompt is required', 'wp-content-flow' );
        }
        
        if ( ! workflowId ) {
            errors.workflow = __( 'Please select a workflow', 'wp-content-flow' );
        }
        
        setValidationErrors( errors );
    }, [ prompt, workflowId ] );
    
    /**
     * Handle AI content generation
     */
    const handleGenerate = async () => {
        if ( Object.keys( validationErrors ).length > 0 ) {
            setError( __( 'Please fix the validation errors before generating content.', 'wp-content-flow' ) );
            return;
        }
        
        setAttributes( { isGenerating: true } );
        setError( '' );
        
        try {
            const response = await apiFetch( {
                path: '/wp-content-flow/v1/ai/generate',
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpContentFlow.nonce
                },
                data: {
                    prompt: prompt,
                    workflow_id: workflowId,
                    post_id: wp.data.select( 'core/editor' )?.getCurrentPostId?.(),
                    parameters: {
                        max_tokens: 1000,
                        temperature: 0.7
                    }
                }
            } );
            
            setAttributes( {
                content: response.suggested_content,
                confidence: response.confidence_score,
                lastGenerated: new Date().toISOString(),
                isGenerating: false
            } );
            
            createNotice( 'success', __( 'AI content generated successfully!', 'wp-content-flow' ), {
                type: 'snackbar',
                isDismissible: true
            } );
            
        } catch ( apiError ) {
            let errorMessage = __( 'Failed to generate content. Please try again.', 'wp-content-flow' );
            
            if ( apiError.code === 'rate_limit_exceeded' ) {
                errorMessage = __( 'Too many requests. Please try again later.', 'wp-content-flow' );
            } else if ( apiError.code === 'workflow_not_found' ) {
                errorMessage = __( 'Selected workflow not found. Please choose a different workflow.', 'wp-content-flow' );
            }
            
            setError( errorMessage );
            setAttributes( { isGenerating: false } );
        }
    };
    
    /**
     * Handle content regeneration
     */
    const handleRegenerate = () => {
        if ( content ) {
            handleGenerate();
        }
    };
    
    /**
     * Handle content improvement
     */
    const handleImprove = async ( improvementType ) => {
        if ( ! content ) {
            return;
        }
        
        setAttributes( { isGenerating: true } );
        setError( '' );
        
        try {
            const response = await apiFetch( {
                path: '/wp-content-flow/v1/ai/improve',
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpContentFlow.nonce
                },
                data: {
                    content: content,
                    workflow_id: workflowId,
                    improvement_type: improvementType
                }
            } );
            
            if ( response.length > 0 ) {
                setAttributes( {
                    content: response[0].suggested_content,
                    confidence: response[0].confidence_score,
                    isGenerating: false
                } );
                
                createNotice( 'success', __( 'Content improved successfully!', 'wp-content-flow' ), {
                    type: 'snackbar',
                    isDismissible: true
                } );
            }
            
        } catch ( apiError ) {
            setError( __( 'Failed to improve content. Please try again.', 'wp-content-flow' ) );
            setAttributes( { isGenerating: false } );
        }
    };
    
    const blockProps = useBlockProps( {
        className: 'wp-content-flow-ai-text-generator'
    } );
    
    // Workflow options for select control
    const workflowOptions = [
        { label: __( 'Select a workflow...', 'wp-content-flow' ), value: 0 },
        ...workflows.map( workflow => ( {
            label: workflow.name,
            value: workflow.id
        } ) )
    ];
    
    return (
        <div { ...blockProps }>
            { /* Block Toolbar Controls */ }
            <BlockControls>
                <ToolbarGroup>
                    <ToolbarButton
                        icon="controls-repeat"
                        label={ __( 'Regenerate', 'wp-content-flow' ) }
                        onClick={ handleRegenerate }
                        disabled={ isGenerating || ! content }
                    />
                    <ToolbarButton
                        icon="edit"
                        label={ __( 'Improve', 'wp-content-flow' ) }
                        onClick={ () => handleImprove( 'style' ) }
                        disabled={ isGenerating || ! content }
                    />
                </ToolbarGroup>
            </BlockControls>
            
            { /* Inspector Controls (Sidebar) */ }
            <InspectorControls>
                <PanelBody title={ __( 'AI Settings', 'wp-content-flow' ) } initialOpen={ true }>
                    <SelectControl
                        label={ __( 'Workflow', 'wp-content-flow' ) }
                        value={ workflowId }
                        options={ workflowOptions }
                        onChange={ ( value ) => setAttributes( { workflowId: parseInt( value ) } ) }
                        help={ validationErrors.workflow }
                    />
                    
                    { workflowId > 0 && (
                        <TextareaControl
                            label={ __( 'Content Prompt', 'wp-content-flow' ) }
                            value={ prompt }
                            onChange={ ( value ) => setAttributes( { prompt: value } ) }
                            placeholder={ __( 'Enter your content prompt...', 'wp-content-flow' ) }
                            rows={ 4 }
                            help={ validationErrors.prompt || __( 'Describe what content you want to generate.', 'wp-content-flow' ) }
                        />
                    ) }
                </PanelBody>
                
                { confidence > 0 && (
                    <PanelBody title={ __( 'Parameters', 'wp-content-flow' ) } initialOpen={ false }>
                        <p>
                            <strong>{ __( 'Confidence Score:', 'wp-content-flow' ) }</strong> { Math.round( confidence * 100 ) }%
                        </p>
                        { confidence < 0.7 && (
                            <Notice status="warning" isDismissible={ false }>
                                { __( 'Low confidence score. Consider regenerating with a more specific prompt.', 'wp-content-flow' ) }
                            </Notice>
                        ) }
                    </PanelBody>
                ) }
            </InspectorControls>
            
            { /* Main Block Content */ }
            <div className="wp-content-flow-ai-block-content">
                { error && (
                    <Notice status="error" isDismissible={ true } onRemove={ () => setError( '' ) }>
                        { error }
                    </Notice>
                ) }
                
                { Object.keys( validationErrors ).length > 0 && isSelected && (
                    <Notice status="warning" isDismissible={ false }>
                        { Object.values( validationErrors ).join( ', ' ) }
                    </Notice>
                ) }
                
                { isGenerating ? (
                    <div className="wp-content-flow-generating">
                        <Spinner />
                        <p>{ __( 'Generating AI content...', 'wp-content-flow' ) }</p>
                    </div>
                ) : (
                    <>
                        { content ? (
                            <div className="wp-content-flow-generated-content">
                                <div 
                                    className="content-display"
                                    contentEditable={ true }
                                    suppressContentEditableWarning={ true }
                                    onBlur={ ( e ) => setAttributes( { content: e.target.textContent } ) }
                                    dangerouslySetInnerHTML={ { __html: content } }
                                />
                                { confidence > 0 && (
                                    <div className="confidence-indicator">
                                        <small>
                                            { __( 'Confidence:', 'wp-content-flow' ) } { Math.round( confidence * 100 ) }%
                                        </small>
                                    </div>
                                ) }
                            </div>
                        ) : (
                            <div className="wp-content-flow-placeholder">
                                { workflowId && prompt ? (
                                    <Button
                                        variant="primary"
                                        onClick={ handleGenerate }
                                        disabled={ Object.keys( validationErrors ).length > 0 }
                                    >
                                        { __( 'Generate Content', 'wp-content-flow' ) }
                                    </Button>
                                ) : (
                                    <p className="placeholder-text">
                                        { __( 'Select a workflow and enter a prompt to generate AI content.', 'wp-content-flow' ) }
                                    </p>
                                ) }
                            </div>
                        ) }
                    </>
                ) }
            </div>
        </div>
    );
}

/**
 * Block Save Component
 */
function SaveComponent( { attributes } ) {
    const { content } = attributes;
    const blockProps = useBlockProps.save();
    
    return (
        <div { ...blockProps }>
            { content && (
                <div 
                    className="wp-content-flow-ai-generated-content"
                    dangerouslySetInnerHTML={ { __html: content } }
                />
            ) }
        </div>
    );
}

// Register the block
registerBlockType( AI_TEXT_BLOCK_CONTRACT.name, AI_TEXT_BLOCK_CONTRACT );

// Export for testing
export { AI_TEXT_BLOCK_CONTRACT, EditComponent, SaveComponent };