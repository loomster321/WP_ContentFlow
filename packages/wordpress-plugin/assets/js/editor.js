/**
 * WordPress Block Editor integration for WP Content Flow
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { Panel, PanelBody, PanelRow, Button, TextareaControl, SelectControl, Spinner } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { select, useSelect, dispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

/**
 * AI Chat Panel Component
 */
const AIChatPanel = () => {
    const [prompt, setPrompt] = useState('');
    const [workflows, setWorkflows] = useState([]);
    const [selectedWorkflow, setSelectedWorkflow] = useState('');
    const [isGenerating, setIsGenerating] = useState(false);
    const [suggestions, setSuggestions] = useState([]);
    const [selectedText, setSelectedText] = useState('');

    // Get the currently selected blocks
    const selectedBlocks = useSelect((select) => {
        return select('core/block-editor').getSelectedBlocks();
    }, []);

    // Load workflows on component mount
    useEffect(() => {
        loadWorkflows();
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
            const response = await apiFetch({
                path: '/wp-content-flow/v1/workflows'
            });
            
            if (response.success) {
                setWorkflows(response.data || []);
                if (response.data && response.data.length > 0) {
                    setSelectedWorkflow(response.data[0].id.toString());
                }
            }
        } catch (error) {
            console.error('Failed to load workflows:', error);
        }
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

    return (
        <Panel>
            <PanelBody title={__('AI Content Generator', 'wp-content-flow')} initialOpen={true}>
                <PanelRow>
                    <SelectControl
                        label={__('Workflow', 'wp-content-flow')}
                        value={selectedWorkflow}
                        onChange={setSelectedWorkflow}
                        options={[
                            { value: '', label: __('Select a workflow...', 'wp-content-flow') },
                            ...workflows.map(workflow => ({
                                value: workflow.id.toString(),
                                label: workflow.name
                            }))
                        ]}
                    />
                </PanelRow>
                
                <PanelRow>
                    <TextareaControl
                        label={__('Prompt', 'wp-content-flow')}
                        value={prompt}
                        onChange={setPrompt}
                        placeholder={__('Describe what content you want to generate...', 'wp-content-flow')}
                        rows={4}
                    />
                </PanelRow>
                
                <PanelRow>
                    <Button
                        variant="primary"
                        onClick={generateContent}
                        disabled={!prompt.trim() || !selectedWorkflow || isGenerating}
                        style={{ width: '100%' }}
                    >
                        {isGenerating ? (
                            <>
                                <Spinner />
                                {__('Generating...', 'wp-content-flow')}
                            </>
                        ) : (
                            __('Generate Content', 'wp-content-flow')
                        )}
                    </Button>
                </PanelRow>
            </PanelBody>

            {selectedText && (
                <PanelBody title={__('Improve Selected Text', 'wp-content-flow')} initialOpen={false}>
                    <PanelRow>
                        <p><strong>{__('Selected:', 'wp-content-flow')}</strong></p>
                    </PanelRow>
                    <PanelRow>
                        <div style={{ 
                            padding: '8px', 
                            background: '#f0f0f0', 
                            borderRadius: '4px',
                            fontSize: '12px',
                            maxHeight: '100px',
                            overflow: 'auto'
                        }}>
                            {selectedText.substring(0, 200)}
                            {selectedText.length > 200 && '...'}
                        </div>
                    </PanelRow>
                    
                    <PanelRow>
                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '8px' }}>
                            <Button
                                variant="secondary"
                                size="small"
                                onClick={() => improveContent('grammar')}
                                disabled={isGenerating}
                            >
                                {__('Fix Grammar', 'wp-content-flow')}
                            </Button>
                            <Button
                                variant="secondary"
                                size="small"
                                onClick={() => improveContent('style')}
                                disabled={isGenerating}
                            >
                                {__('Improve Style', 'wp-content-flow')}
                            </Button>
                            <Button
                                variant="secondary"
                                size="small"
                                onClick={() => improveContent('clarity')}
                                disabled={isGenerating}
                            >
                                {__('Improve Clarity', 'wp-content-flow')}
                            </Button>
                            <Button
                                variant="secondary"
                                size="small"
                                onClick={() => improveContent('seo')}
                                disabled={isGenerating}
                            >
                                {__('SEO Optimize', 'wp-content-flow')}
                            </Button>
                        </div>
                    </PanelRow>
                </PanelBody>
            )}

            {suggestions.length > 0 && (
                <PanelBody title={__('AI Suggestions', 'wp-content-flow')} initialOpen={true}>
                    {suggestions.map(suggestion => (
                        <div key={suggestion.id} style={{
                            border: '1px solid #ddd',
                            borderRadius: '4px',
                            padding: '12px',
                            marginBottom: '12px',
                            background: '#fff'
                        }}>
                            <div style={{ marginBottom: '8px' }}>
                                <strong>
                                    {suggestion.type === 'improvement' 
                                        ? __(`${suggestion.improvement_type} improvement`, 'wp-content-flow')
                                        : __('Generated content', 'wp-content-flow')
                                    }
                                </strong>
                                {wpContentFlow.showConfidenceScores && (
                                    <span style={{ 
                                        float: 'right',
                                        fontSize: '12px',
                                        color: suggestion.confidence_score > 0.8 ? '#4CAF50' : '#FF9800'
                                    }}>
                                        {Math.round(suggestion.confidence_score * 100)}% {__('confidence', 'wp-content-flow')}
                                    </span>
                                )}
                            </div>
                            
                            <div style={{
                                background: '#f9f9f9',
                                padding: '8px',
                                borderRadius: '4px',
                                marginBottom: '8px',
                                fontSize: '14px',
                                maxHeight: '150px',
                                overflow: 'auto'
                            }}>
                                {suggestion.content}
                            </div>
                            
                            <div style={{ display: 'flex', gap: '8px' }}>
                                <Button
                                    variant="primary"
                                    size="small"
                                    onClick={() => applySuggestion(suggestion)}
                                >
                                    {__('Accept', 'wp-content-flow')}
                                </Button>
                                <Button
                                    variant="secondary"
                                    size="small"
                                    onClick={() => rejectSuggestion(suggestion.id)}
                                >
                                    {__('Reject', 'wp-content-flow')}
                                </Button>
                            </div>
                        </div>
                    ))}
                </PanelBody>
            )}
        </Panel>
    );
};

/**
 * Register the AI Chat sidebar plugin
 */
registerPlugin('wp-content-flow-ai-chat', {
    render: () => (
        <>
            <PluginSidebarMoreMenuItem
                target="wp-content-flow-ai-chat"
                icon="admin-site-alt3"
            >
                {__('AI Chat', 'wp-content-flow')}
            </PluginSidebarMoreMenuItem>
            <PluginSidebar
                name="wp-content-flow-ai-chat"
                title={__('AI Content Flow', 'wp-content-flow')}
                icon="admin-site-alt3"
            >
                <AIChatPanel />
            </PluginSidebar>
        </>
    )
});