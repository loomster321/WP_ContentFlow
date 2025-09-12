/**
 * WordPress AI Content Flow - Block Editor Integration
 * 
 * Main entry point for all Gutenberg block components and editor integrations.
 * This file orchestrates the AI-powered content creation experience.
 */

// Import WordPress block libraries
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
    Notice,
    TextControl
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

// Import and initialize content improvement toolbar
import './improvement-toolbar.js';

// AI Text Generator Block Definition
const aiIcon = 'admin-generic';

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
    const [ localWorkflows, setLocalWorkflows ] = useState( [] );
    
    // Start with default workflows immediately
    useEffect( () => {
        // Use default workflows from localized script data
        if ( window.wpContentFlow && window.wpContentFlow.defaultWorkflows ) {
            setLocalWorkflows( window.wpContentFlow.defaultWorkflows );
        }
    }, [] );
    
    // Try to get workflows from the data store if available
    const storeWorkflows = useSelect( ( select ) => {
        const store = select( 'wp-content-flow/workflows' );
        if ( store && store.getWorkflows ) {
            const workflows = store.getWorkflows();
            // Only return if we have actual workflows
            if ( workflows && workflows.length > 0 ) {
                return workflows;
            }
        }
        return null;
    }, [] );
    
    // Use store workflows if available, otherwise use local workflows
    const availableWorkflows = storeWorkflows || localWorkflows;
    
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
                data: {
                    prompt: prompt,
                    workflow_id: workflowId,
                    post_id: wp.data && wp.data.select && wp.data.select( 'core/editor' ) ? 
                        wp.data.select( 'core/editor' ).getCurrentPostId() : null,
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
        ...availableWorkflows.map( workflow => ( {
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
                                <div style={{ padding: '20px', background: '#f5f5f5', borderRadius: '4px' }}>
                                    <h3>{ __( 'AI Content Generator', 'wp-content-flow' ) }</h3>
                                    
                                    <SelectControl
                                        label={ __( 'Select Workflow', 'wp-content-flow' ) }
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
                                    
                                    { workflowId > 0 && prompt && (
                                        <Button
                                            variant="primary"
                                            onClick={ handleGenerate }
                                            disabled={ Object.keys( validationErrors ).length > 0 }
                                            style={{ marginTop: '10px' }}
                                        >
                                            { __( 'Generate Content', 'wp-content-flow' ) }
                                        </Button>
                                    ) }
                                    
                                    { !workflowId && (
                                        <p style={{ color: '#666', fontStyle: 'italic' }}>
                                            { __( 'Please select a workflow to begin.', 'wp-content-flow' ) }
                                        </p>
                                    ) }
                                </div>
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

(function() {
    'use strict';
    
    const { registerPlugin } = wp.plugins;
    const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
    const { Panel, PanelBody, PanelRow, Button, TextareaControl, SelectControl, Spinner, ToggleControl, RangeControl } = wp.components;
    const { useState, useEffect } = wp.element;
    const { __ } = wp.i18n;
    const { select, useSelect, dispatch } = wp.data;
    const { apiFetch } = wp;

    /**
     * AI Content Flow Panel Component
     */
    const AIChatPanel = () => {
        const [activeTab, setActiveTab] = useState('generate');
        const [prompt, setPrompt] = useState('');
        const [workflows, setWorkflows] = useState([]);
        const [selectedWorkflow, setSelectedWorkflow] = useState('');
        const [isGenerating, setIsGenerating] = useState(false);
        const [suggestions, setSuggestions] = useState([]);
        const [selectedText, setSelectedText] = useState('');
        
        // Settings state
        const [settings, setSettings] = useState({
            cache_enabled: true,
            cache_duration: 1800,
            rate_limit_enabled: true,
            requests_per_minute: 10,
            auto_save_enabled: true,
            debug_mode: false
        });
        const [isLoadingSettings, setIsLoadingSettings] = useState(false);
        const [isDirty, setIsDirty] = useState(false);

        // Get the currently selected blocks
        const selectedBlocks = useSelect((select) => {
            const editor = select('core/block-editor');
            // Check if the editor store is available and has the method
            if (editor && editor.getSelectedBlock) {
                const block = editor.getSelectedBlock();
                return block ? [block] : [];
            }
            return [];
        }, []);

        // Load workflows on component mount
        useEffect(() => {
            loadWorkflows();
            loadSettings();
        }, []);

        // Update selected text when block selection changes
        useEffect(() => {
            if (selectedBlocks.length === 1) {
                const block = selectedBlocks[0];
                if (block.attributes && block.attributes.content) {
                    setSelectedText(block.attributes.content);
                }
            } else {
                setSelectedText('');
            }
        }, [selectedBlocks]);

        const loadWorkflows = async () => {
            try {
                // Use WordPress apiFetch which handles nonce automatically
                const response = await apiFetch({
                    path: '/wp-content-flow/v1/workflows',
                    method: 'GET'
                });
                
                // Check if response is an array (direct response) or wrapped object
                if (Array.isArray(response)) {
                    setWorkflows(response);
                    if (response.length > 0) {
                        setSelectedWorkflow(response[0].id.toString());
                    }
                } else if (response && response.success) {
                    setWorkflows(response.data || []);
                    if (response.data && response.data.length > 0) {
                        setSelectedWorkflow(response.data[0].id.toString());
                    }
                } else {
                    setWorkflows([]);
                }
            } catch (error) {
                console.warn('Could not load workflows, using defaults:', error);
                // Use default workflows if API fails
                const defaultWorkflows = [
                    { id: 1, name: 'Blog Post Workflow', description: 'For blog content generation' },
                    { id: 2, name: 'Product Description', description: 'For product descriptions' },
                    { id: 3, name: 'Social Media', description: 'For social media posts' }
                ];
                setWorkflows(defaultWorkflows);
                setSelectedWorkflow('1');
            }
        };
        
        const loadSettings = async () => {
            try {
                const response = await apiFetch({
                    path: '/wp-content-flow/v1/settings'
                });
                if (response) {
                    setSettings(response);
                }
            } catch (error) {
                console.warn('Could not load settings:', error);
            }
        };
        
        const saveSettings = async () => {
            setIsLoadingSettings(true);
            try {
                await apiFetch({
                    path: '/wp-content-flow/v1/settings',
                    method: 'POST',
                    data: settings
                });
                setIsDirty(false);
            } catch (error) {
                console.error('Failed to save settings:', error);
            } finally {
                setIsLoadingSettings(false);
            }
        };
        
        const handleSettingChange = (key, value) => {
            setSettings(prev => ({ ...prev, [key]: value }));
            setIsDirty(true);
        };

        const generateContent = async () => {
            if (!prompt.trim() || !selectedWorkflow) return;

            setIsGenerating(true);

            try {
                const postId = select('core/editor').getCurrentPostId();
                
                const response = await apiFetch({
                    path: '/wp-content-flow/v1/ai/generate',
                    method: 'POST',
                    data: {
                        prompt: prompt,
                        workflow_id: parseInt(selectedWorkflow),
                        post_id: postId,
                        selected_content: selectedText
                    }
                });

                if (response.success) {
                    setSuggestions(prev => [...prev, {
                        id: response.data.suggestion_id,
                        content: response.data.content,
                        confidence_score: response.data.confidence_score,
                        type: 'generation',
                        timestamp: new Date()
                    }]);
                    setPrompt(''); // Clear prompt after successful generation
                }
            } catch (error) {
                console.error('Content generation failed:', error);
            } finally {
                setIsGenerating(false);
            }
        };

        const improveContent = async (improvementType) => {
            if (!selectedText || !selectedWorkflow) return;

            setIsGenerating(true);

            try {
                const response = await apiFetch({
                    path: '/wp-content-flow/v1/ai/improve',
                    method: 'POST',
                    data: {
                        content: selectedText,
                        improvement_type: improvementType,
                        workflow_id: parseInt(selectedWorkflow)
                    }
                });

                if (response.success) {
                    setSuggestions(prev => [...prev, {
                        id: response.data.suggestion_id,
                        content: response.data.improved_content,
                        confidence_score: response.data.confidence_score,
                        type: 'improvement',
                        improvement_type: improvementType,
                        original_content: selectedText,
                        timestamp: new Date()
                    }]);
                }
            } catch (error) {
                console.error('Content improvement failed:', error);
            } finally {
                setIsGenerating(false);
            }
        };

        const applySuggestion = (suggestion) => {
            const { replaceBlocks, insertBlocks } = dispatch('core/block-editor');
            const { createBlock } = wp.blocks;

            if (selectedBlocks.length === 1) {
                // Replace content of selected block
                const block = selectedBlocks[0];
                const newBlock = createBlock(block.name, {
                    ...block.attributes,
                    content: suggestion.content
                });
                replaceBlocks(block.clientId, newBlock);
            } else {
                // Insert as new paragraph block
                const newBlock = createBlock('core/paragraph', {
                    content: suggestion.content
                });
                insertBlocks(newBlock);
            }

            // Mark suggestion as accepted
            acceptSuggestion(suggestion.id);
        };

        const acceptSuggestion = async (suggestionId) => {
            try {
                await apiFetch({
                    path: `/wp-content-flow/v1/suggestions/${suggestionId}/accept`,
                    method: 'POST'
                });
                
                // Remove suggestion from list
                setSuggestions(prev => prev.filter(s => s.id !== suggestionId));
            } catch (error) {
                console.error('Failed to accept suggestion:', error);
            }
        };

        const rejectSuggestion = async (suggestionId) => {
            try {
                await apiFetch({
                    path: `/wp-content-flow/v1/suggestions/${suggestionId}/reject`,
                    method: 'POST'
                });
                
                // Remove suggestion from list
                setSuggestions(prev => prev.filter(s => s.id !== suggestionId));
            } catch (error) {
                console.error('Failed to reject suggestion:', error);
            }
        };

        return wp.element.createElement(Panel, null,
            // Tab navigation
            wp.element.createElement('div', { style: { padding: '16px', borderBottom: '1px solid #ddd' } },
                wp.element.createElement('div', { style: { display: 'flex', gap: '8px' } },
                    wp.element.createElement(Button, {
                        variant: activeTab === 'generate' ? 'primary' : 'secondary',
                        size: 'small',
                        onClick: () => setActiveTab('generate')
                    }, __('Generate', 'wp-content-flow')),
                    wp.element.createElement(Button, {
                        variant: activeTab === 'improve' ? 'primary' : 'secondary',
                        size: 'small',
                        onClick: () => setActiveTab('improve')
                    }, __('Improve', 'wp-content-flow')),
                    wp.element.createElement(Button, {
                        variant: activeTab === 'settings' ? 'primary' : 'secondary',
                        size: 'small',
                        onClick: () => setActiveTab('settings')
                    }, __('Settings', 'wp-content-flow'))
                )
            ),
            
            // Tab content
            activeTab === 'generate' &&
            wp.element.createElement(PanelBody, { 
                title: __('AI Content Generator', 'wp-content-flow'), 
                initialOpen: true 
            },
                wp.element.createElement(PanelRow, null,
                    wp.element.createElement(SelectControl, {
                        label: __('Workflow', 'wp-content-flow'),
                        value: selectedWorkflow,
                        onChange: setSelectedWorkflow,
                        options: [
                            { value: '', label: __('Select a workflow...', 'wp-content-flow') },
                            ...workflows.map(workflow => ({
                                value: workflow.id.toString(),
                                label: workflow.name
                            }))
                        ]
                    })
                ),
                
                wp.element.createElement(PanelRow, null,
                    wp.element.createElement(TextareaControl, {
                        label: __('Prompt', 'wp-content-flow'),
                        value: prompt,
                        onChange: setPrompt,
                        placeholder: __('Describe what content you want to generate...', 'wp-content-flow'),
                        rows: 4
                    })
                ),
                
                wp.element.createElement(PanelRow, null,
                    wp.element.createElement(Button, {
                        variant: 'primary',
                        onClick: generateContent,
                        disabled: !prompt.trim() || !selectedWorkflow || isGenerating,
                        style: { width: '100%' }
                    }, isGenerating 
                        ? wp.element.createElement('span', null,
                            wp.element.createElement(Spinner),
                            __('Generating...', 'wp-content-flow')
                          )
                        : __('Generate Content', 'wp-content-flow')
                    )
                )
            ),

            activeTab === 'improve' && selectedText && wp.element.createElement(PanelBody, { 
                title: __('Improve Selected Text', 'wp-content-flow'), 
                initialOpen: true 
            },
                wp.element.createElement(PanelRow, null,
                    wp.element.createElement('p', null,
                        wp.element.createElement('strong', null, __('Selected:', 'wp-content-flow'))
                    )
                ),
                wp.element.createElement(PanelRow, null,
                    wp.element.createElement('div', { 
                        style: { 
                            padding: '8px', 
                            background: '#f0f0f0', 
                            borderRadius: '4px',
                            fontSize: '12px',
                            maxHeight: '100px',
                            overflow: 'auto'
                        }
                    }, selectedText.substring(0, 200) + (selectedText.length > 200 ? '...' : ''))
                ),
                
                wp.element.createElement(PanelRow, null,
                    wp.element.createElement('div', { style: { display: 'flex', flexWrap: 'wrap', gap: '8px' } },
                        wp.element.createElement(Button, {
                            variant: 'secondary',
                            size: 'small',
                            onClick: () => improveContent('grammar'),
                            disabled: isGenerating
                        }, __('Fix Grammar', 'wp-content-flow')),
                        wp.element.createElement(Button, {
                            variant: 'secondary',
                            size: 'small',
                            onClick: () => improveContent('style'),
                            disabled: isGenerating
                        }, __('Improve Style', 'wp-content-flow')),
                        wp.element.createElement(Button, {
                            variant: 'secondary',
                            size: 'small',
                            onClick: () => improveContent('clarity'),
                            disabled: isGenerating
                        }, __('Improve Clarity', 'wp-content-flow')),
                        wp.element.createElement(Button, {
                            variant: 'secondary',
                            size: 'small',
                            onClick: () => improveContent('seo'),
                            disabled: isGenerating
                        }, __('SEO Optimize', 'wp-content-flow'))
                    )
                )
            ),

            (activeTab === 'generate' || activeTab === 'improve') && suggestions.length > 0 && wp.element.createElement(PanelBody, { 
                title: __('AI Suggestions', 'wp-content-flow'), 
                initialOpen: true 
            },
                suggestions.map(suggestion => 
                    wp.element.createElement('div', { 
                        key: suggestion.id,
                        style: {
                            border: '1px solid #ddd',
                            borderRadius: '4px',
                            padding: '12px',
                            marginBottom: '12px',
                            background: '#fff'
                        }
                    },
                        wp.element.createElement('div', { style: { marginBottom: '8px' } },
                            wp.element.createElement('strong', null,
                                suggestion.type === 'improvement' 
                                    ? __(`${suggestion.improvement_type} improvement`, 'wp-content-flow')
                                    : __('Generated content', 'wp-content-flow')
                            ),
                            (window.wpContentFlow && window.wpContentFlow.showConfidenceScores) && 
                            wp.element.createElement('span', { 
                                style: { 
                                    float: 'right',
                                    fontSize: '12px',
                                    color: suggestion.confidence_score > 0.8 ? '#4CAF50' : '#FF9800'
                                }
                            }, Math.round(suggestion.confidence_score * 100) + '% ' + __('confidence', 'wp-content-flow'))
                        ),
                        
                        wp.element.createElement('div', {
                            style: {
                                background: '#f9f9f9',
                                padding: '8px',
                                borderRadius: '4px',
                                marginBottom: '8px',
                                fontSize: '14px',
                                maxHeight: '150px',
                                overflow: 'auto'
                            }
                        }, suggestion.content),
                        
                        wp.element.createElement('div', { style: { display: 'flex', gap: '8px' } },
                            wp.element.createElement(Button, {
                                variant: 'primary',
                                size: 'small',
                                onClick: () => applySuggestion(suggestion)
                            }, __('Accept', 'wp-content-flow')),
                            wp.element.createElement(Button, {
                                variant: 'secondary',
                                size: 'small',
                                onClick: () => rejectSuggestion(suggestion.id)
                            }, __('Reject', 'wp-content-flow'))
                        )
                    )
                )
            ),
            
            // Settings tab
            activeTab === 'settings' && wp.element.createElement('div', null,
                wp.element.createElement(PanelBody, { 
                    title: __('General Settings', 'wp-content-flow'), 
                    initialOpen: true 
                },
                    wp.element.createElement(PanelRow, null,
                        wp.element.createElement(ToggleControl, {
                            label: __('Enable Caching', 'wp-content-flow'),
                            checked: settings.cache_enabled,
                            onChange: (value) => handleSettingChange('cache_enabled', value),
                            help: __('Cache AI responses to improve performance.', 'wp-content-flow')
                        })
                    ),
                    
                    settings.cache_enabled && wp.element.createElement(PanelRow, null,
                        wp.element.createElement(RangeControl, {
                            label: __('Cache Duration (minutes)', 'wp-content-flow'),
                            value: Math.round(settings.cache_duration / 60),
                            onChange: (value) => handleSettingChange('cache_duration', value * 60),
                            min: 5,
                            max: 1440,
                            step: 5,
                            help: __('How long to cache AI responses.', 'wp-content-flow')
                        })
                    ),
                    
                    wp.element.createElement(PanelRow, null,
                        wp.element.createElement(ToggleControl, {
                            label: __('Auto-save Content', 'wp-content-flow'),
                            checked: settings.auto_save_enabled,
                            onChange: (value) => handleSettingChange('auto_save_enabled', value),
                            help: __('Automatically save posts when AI content is generated.', 'wp-content-flow')
                        })
                    ),
                    
                    wp.element.createElement(PanelRow, null,
                        wp.element.createElement(ToggleControl, {
                            label: __('Debug Mode', 'wp-content-flow'),
                            checked: settings.debug_mode,
                            onChange: (value) => handleSettingChange('debug_mode', value),
                            help: __('Enable detailed logging for troubleshooting.', 'wp-content-flow')
                        })
                    )
                ),
                
                wp.element.createElement(PanelBody, { 
                    title: __('Rate Limits', 'wp-content-flow'), 
                    initialOpen: false 
                },
                    wp.element.createElement(PanelRow, null,
                        wp.element.createElement(ToggleControl, {
                            label: __('Enable Rate Limiting', 'wp-content-flow'),
                            checked: settings.rate_limit_enabled,
                            onChange: (value) => handleSettingChange('rate_limit_enabled', value),
                            help: __('Limit API requests to prevent quota exhaustion.', 'wp-content-flow')
                        })
                    ),
                    
                    settings.rate_limit_enabled && wp.element.createElement(PanelRow, null,
                        wp.element.createElement(RangeControl, {
                            label: __('Requests per Minute', 'wp-content-flow'),
                            value: settings.requests_per_minute,
                            onChange: (value) => handleSettingChange('requests_per_minute', value),
                            min: 1,
                            max: 60,
                            step: 1
                        })
                    )
                ),
                
                wp.element.createElement('div', { style: { padding: '16px', borderTop: '1px solid #ddd' } },
                    wp.element.createElement(Button, {
                        variant: 'primary',
                        onClick: saveSettings,
                        disabled: isLoadingSettings || !isDirty
                    }, isLoadingSettings ? wp.element.createElement(Spinner) : '✓ ' + __('Save Settings', 'wp-content-flow')),
                    
                    isDirty && wp.element.createElement('div', { 
                        style: { marginTop: '8px', fontSize: '12px', color: '#f56565' } 
                    }, __('You have unsaved changes', 'wp-content-flow'))
                )
            )
        );
    };

    /**
     * Register the AI Chat sidebar plugin
     */
    registerPlugin('wp-content-flow-ai-chat', {
        render: () => wp.element.createElement('div', null,
            wp.element.createElement(PluginSidebarMoreMenuItem, {
                target: 'wp-content-flow-ai-chat',
                icon: 'admin-site-alt3'
            }, __('AI Chat', 'wp-content-flow')),
            
            wp.element.createElement(PluginSidebar, {
                name: 'wp-content-flow-ai-chat',
                title: __('AI Content Flow', 'wp-content-flow'),
                icon: 'admin-site-alt3'
            }, wp.element.createElement(AIChatPanel))
        )
    });

    console.log('WP Content Flow: AI Chat panel registered successfully');
})();