/**
 * Workflow Settings Panel Component
 * 
 * Provides a settings panel for configuring AI workflows in the Gutenberg editor.
 * Integrates with WordPress data stores and provides live configuration updates.
 */

import { __ } from '@wordpress/i18n';
import { 
    Panel, 
    PanelBody, 
    PanelRow,
    TextControl, 
    TextareaControl,
    SelectControl,
    ToggleControl,
    RangeControl,
    Button,
    ButtonGroup,
    Spinner,
    Notice,
    __experimentalHeading as Heading,
    __experimentalSpacer as Spacer
} from '@wordpress/components';
import { useState, useEffect, useCallback } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { settings, check, close, plus } from '@wordpress/icons';

/**
 * Workflow Settings Panel Component
 */
export function WorkflowSettingsPanel() {
    const [activeTab, setActiveTab] = useState('general');
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const [isDirty, setIsDirty] = useState(false);
    
    // Get workflows and settings from data store
    const { workflows, isLoadingWorkflows, workflowError } = useSelect((select) => {
        const workflowStore = select('wp-content-flow/workflows');
        return {
            workflows: workflowStore?.getWorkflows() || [],
            isLoadingWorkflows: workflowStore?.isLoading() || false,
            workflowError: workflowStore?.getError() || null
        };
    }, []);
    
    const { fetchWorkflows, createWorkflow } = useDispatch('wp-content-flow/workflows') || {};
    const { createNotice } = useDispatch('core/notices');
    
    // Local state for new workflow form
    const [newWorkflow, setNewWorkflow] = useState({
        name: '',
        description: '',
        ai_provider: 'openai',
        model: 'gpt-3.5-turbo',
        parameters: {
            temperature: 0.7,
            max_tokens: 1000,
            top_p: 1,
            frequency_penalty: 0,
            presence_penalty: 0
        },
        status: 'active'
    });
    
    // Settings state
    const [settings, setSettings] = useState({
        cache_enabled: true,
        cache_duration: 1800,
        rate_limit_enabled: true,
        requests_per_minute: 10,
        requests_per_hour: 100,
        daily_token_limit: 50000,
        auto_save_enabled: true,
        debug_mode: false
    });
    
    // Load workflows on mount
    useEffect(() => {
        if (fetchWorkflows && workflows.length === 0 && !isLoadingWorkflows) {
            fetchWorkflows();
        }
    }, [fetchWorkflows, workflows.length, isLoadingWorkflows]);
    
    // Load settings on mount
    useEffect(() => {
        loadSettings();
    }, []);
    
    /**
     * Load plugin settings from API
     */
    const loadSettings = useCallback(async () => {
        try {
            const response = await apiFetch({
                path: '/wp-content-flow/v1/settings'
            });
            setSettings(response);
        } catch (apiError) {
            console.warn('Could not load settings:', apiError);
        }
    }, []);
    
    /**
     * Save settings to API
     */
    const saveSettings = async () => {
        setIsLoading(true);
        setError('');
        
        try {
            await apiFetch({
                path: '/wp-content-flow/v1/settings',
                method: 'POST',
                data: settings
            });
            
            setSuccess(__('Settings saved successfully!', 'wp-content-flow'));
            setIsDirty(false);
            
            createNotice?.('success', __('Settings saved!', 'wp-content-flow'), {
                type: 'snackbar',
                isDismissible: true
            });
            
        } catch (apiError) {
            setError(__('Failed to save settings. Please try again.', 'wp-content-flow'));
        } finally {
            setIsLoading(false);
        }
    };
    
    /**
     * Handle workflow creation
     */
    const handleCreateWorkflow = async () => {
        if (!newWorkflow.name.trim()) {
            setError(__('Workflow name is required.', 'wp-content-flow'));
            return;
        }
        
        setIsLoading(true);
        setError('');
        
        try {
            if (createWorkflow) {
                await createWorkflow(newWorkflow);
            }
            
            // Reset form
            setNewWorkflow({
                name: '',
                description: '',
                ai_provider: 'openai',
                model: 'gpt-3.5-turbo',
                parameters: {
                    temperature: 0.7,
                    max_tokens: 1000,
                    top_p: 1,
                    frequency_penalty: 0,
                    presence_penalty: 0
                },
                status: 'active'
            });
            
            setSuccess(__('Workflow created successfully!', 'wp-content-flow'));
            
            createNotice?.('success', __('New workflow created!', 'wp-content-flow'), {
                type: 'snackbar',
                isDismissible: true
            });
            
        } catch (apiError) {
            setError(__('Failed to create workflow. Please try again.', 'wp-content-flow'));
        } finally {
            setIsLoading(false);
        }
    };
    
    /**
     * Handle settings change
     */
    const handleSettingChange = (key, value) => {
        setSettings(prev => ({ ...prev, [key]: value }));
        setIsDirty(true);
        setSuccess('');
    };
    
    /**
     * Handle workflow parameter change
     */
    const handleParameterChange = (key, value) => {
        setNewWorkflow(prev => ({
            ...prev,
            parameters: {
                ...prev.parameters,
                [key]: value
            }
        }));
    };
    
    /**
     * Get AI provider options
     */
    const getProviderOptions = () => [
        { label: 'OpenAI', value: 'openai' },
        { label: 'Anthropic Claude', value: 'anthropic' },
        { label: 'Google AI', value: 'google' }
    ];
    
    /**
     * Get model options for selected provider
     */
    const getModelOptions = () => {
        switch (newWorkflow.ai_provider) {
            case 'openai':
                return [
                    { label: 'GPT-3.5 Turbo', value: 'gpt-3.5-turbo' },
                    { label: 'GPT-4', value: 'gpt-4' },
                    { label: 'GPT-4 Turbo', value: 'gpt-4-turbo-preview' }
                ];
            case 'anthropic':
                return [
                    { label: 'Claude 3 Haiku', value: 'claude-3-haiku-20240307' },
                    { label: 'Claude 3 Sonnet', value: 'claude-3-sonnet-20240229' },
                    { label: 'Claude 3 Opus', value: 'claude-3-opus-20240229' }
                ];
            case 'google':
                return [
                    { label: 'Gemini Pro', value: 'gemini-pro' },
                    { label: 'Gemini Pro Vision', value: 'gemini-pro-vision' }
                ];
            default:
                return [];
        }
    };
    
    return (
        <Panel className="wp-content-flow-settings-panel">
            <div className="settings-header">
                <Heading level={2}>
                    {__('AI Content Flow Settings', 'wp-content-flow')}
                </Heading>
                <ButtonGroup>
                    <Button
                        variant={activeTab === 'general' ? 'primary' : 'secondary'}
                        onClick={() => setActiveTab('general')}
                    >
                        {__('General', 'wp-content-flow')}
                    </Button>
                    <Button
                        variant={activeTab === 'workflows' ? 'primary' : 'secondary'}
                        onClick={() => setActiveTab('workflows')}
                    >
                        {__('Workflows', 'wp-content-flow')}
                    </Button>
                    <Button
                        variant={activeTab === 'performance' ? 'primary' : 'secondary'}
                        onClick={() => setActiveTab('performance')}
                    >
                        {__('Performance', 'wp-content-flow')}
                    </Button>
                </ButtonGroup>
            </div>
            
            <Spacer />
            
            {error && (
                <Notice status="error" isDismissible onRemove={() => setError('')}>
                    {error}
                </Notice>
            )}
            
            {success && (
                <Notice status="success" isDismissible onRemove={() => setSuccess('')}>
                    {success}
                </Notice>
            )}
            
            {workflowError && (
                <Notice status="warning" isDismissible={false}>
                    {__('Error loading workflows:', 'wp-content-flow')} {workflowError}
                </Notice>
            )}
            
            {activeTab === 'general' && (
                <PanelBody title={__('General Settings', 'wp-content-flow')} initialOpen={true}>
                    <PanelRow>
                        <ToggleControl
                            label={__('Enable Caching', 'wp-content-flow')}
                            checked={settings.cache_enabled}
                            onChange={(value) => handleSettingChange('cache_enabled', value)}
                            help={__('Cache AI responses to improve performance and reduce API costs.', 'wp-content-flow')}
                        />
                    </PanelRow>
                    
                    {settings.cache_enabled && (
                        <PanelRow>
                            <RangeControl
                                label={__('Cache Duration (seconds)', 'wp-content-flow')}
                                value={settings.cache_duration}
                                onChange={(value) => handleSettingChange('cache_duration', value)}
                                min={300}
                                max={86400}
                                step={300}
                                help={__('How long to cache AI responses.', 'wp-content-flow')}
                            />
                        </PanelRow>
                    )}
                    
                    <PanelRow>
                        <ToggleControl
                            label={__('Auto-save Content', 'wp-content-flow')}
                            checked={settings.auto_save_enabled}
                            onChange={(value) => handleSettingChange('auto_save_enabled', value)}
                            help={__('Automatically save posts when AI content is generated.', 'wp-content-flow')}
                        />
                    </PanelRow>
                    
                    <PanelRow>
                        <ToggleControl
                            label={__('Debug Mode', 'wp-content-flow')}
                            checked={settings.debug_mode}
                            onChange={(value) => handleSettingChange('debug_mode', value)}
                            help={__('Enable detailed logging for troubleshooting.', 'wp-content-flow')}
                        />
                    </PanelRow>
                </PanelBody>
            )}
            
            {activeTab === 'workflows' && (
                <>
                    <PanelBody title={__('Create New Workflow', 'wp-content-flow')} initialOpen={false}>
                        <TextControl
                            label={__('Workflow Name', 'wp-content-flow')}
                            value={newWorkflow.name}
                            onChange={(value) => setNewWorkflow(prev => ({ ...prev, name: value }))}
                            placeholder={__('Enter workflow name...', 'wp-content-flow')}
                        />
                        
                        <TextareaControl
                            label={__('Description', 'wp-content-flow')}
                            value={newWorkflow.description}
                            onChange={(value) => setNewWorkflow(prev => ({ ...prev, description: value }))}
                            placeholder={__('Describe this workflow...', 'wp-content-flow')}
                            rows={3}
                        />
                        
                        <SelectControl
                            label={__('AI Provider', 'wp-content-flow')}
                            value={newWorkflow.ai_provider}
                            options={getProviderOptions()}
                            onChange={(value) => setNewWorkflow(prev => ({ ...prev, ai_provider: value }))}
                        />
                        
                        <SelectControl
                            label={__('Model', 'wp-content-flow')}
                            value={newWorkflow.model}
                            options={getModelOptions()}
                            onChange={(value) => setNewWorkflow(prev => ({ ...prev, model: value }))}
                        />
                        
                        <Spacer />
                        <Heading level={4}>{__('AI Parameters', 'wp-content-flow')}</Heading>
                        
                        <RangeControl
                            label={__('Temperature', 'wp-content-flow')}
                            value={newWorkflow.parameters.temperature}
                            onChange={(value) => handleParameterChange('temperature', value)}
                            min={0}
                            max={1}
                            step={0.1}
                            help={__('Controls randomness. Higher values make output more creative.', 'wp-content-flow')}
                        />
                        
                        <RangeControl
                            label={__('Max Tokens', 'wp-content-flow')}
                            value={newWorkflow.parameters.max_tokens}
                            onChange={(value) => handleParameterChange('max_tokens', value)}
                            min={100}
                            max={4000}
                            step={100}
                            help={__('Maximum length of generated content.', 'wp-content-flow')}
                        />
                        
                        <Button
                            variant="primary"
                            onClick={handleCreateWorkflow}
                            disabled={isLoading || !newWorkflow.name.trim()}
                            icon={isLoading ? undefined : plus}
                        >
                            {isLoading ? <Spinner /> : null}
                            {__('Create Workflow', 'wp-content-flow')}
                        </Button>
                    </PanelBody>
                    
                    <PanelBody title={__('Existing Workflows', 'wp-content-flow')} initialOpen={true}>
                        {isLoadingWorkflows ? (
                            <div className="loading-workflows">
                                <Spinner />
                                <span>{__('Loading workflows...', 'wp-content-flow')}</span>
                            </div>
                        ) : workflows.length > 0 ? (
                            <div className="workflows-list">
                                {workflows.map(workflow => (
                                    <div key={workflow.id} className="workflow-item">
                                        <div className="workflow-header">
                                            <strong>{workflow.name}</strong>
                                            <span className={`status-badge status-${workflow.status}`}>
                                                {workflow.status}
                                            </span>
                                        </div>
                                        {workflow.description && (
                                            <p className="workflow-description">{workflow.description}</p>
                                        )}
                                        <div className="workflow-meta">
                                            <span>{workflow.ai_provider} • {workflow.model}</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p>{__('No workflows found. Create your first workflow above.', 'wp-content-flow')}</p>
                        )}
                    </PanelBody>
                </>
            )}
            
            {activeTab === 'performance' && (
                <PanelBody title={__('Performance & Limits', 'wp-content-flow')} initialOpen={true}>
                    <PanelRow>
                        <ToggleControl
                            label={__('Enable Rate Limiting', 'wp-content-flow')}
                            checked={settings.rate_limit_enabled}
                            onChange={(value) => handleSettingChange('rate_limit_enabled', value)}
                            help={__('Limit API requests to prevent quota exhaustion.', 'wp-content-flow')}
                        />
                    </PanelRow>
                    
                    {settings.rate_limit_enabled && (
                        <>
                            <PanelRow>
                                <RangeControl
                                    label={__('Requests per Minute', 'wp-content-flow')}
                                    value={settings.requests_per_minute}
                                    onChange={(value) => handleSettingChange('requests_per_minute', value)}
                                    min={1}
                                    max={60}
                                    step={1}
                                />
                            </PanelRow>
                            
                            <PanelRow>
                                <RangeControl
                                    label={__('Requests per Hour', 'wp-content-flow')}
                                    value={settings.requests_per_hour}
                                    onChange={(value) => handleSettingChange('requests_per_hour', value)}
                                    min={10}
                                    max={1000}
                                    step={10}
                                />
                            </PanelRow>
                            
                            <PanelRow>
                                <RangeControl
                                    label={__('Daily Token Limit', 'wp-content-flow')}
                                    value={settings.daily_token_limit}
                                    onChange={(value) => handleSettingChange('daily_token_limit', value)}
                                    min={1000}
                                    max={100000}
                                    step={1000}
                                    help={__('Maximum tokens to use per day across all workflows.', 'wp-content-flow')}
                                />
                            </PanelRow>
                        </>
                    )}
                </PanelBody>
            )}
            
            <div className="settings-footer">
                <Button
                    variant="primary"
                    onClick={saveSettings}
                    disabled={isLoading || !isDirty}
                    icon={isLoading ? undefined : check}
                >
                    {isLoading ? <Spinner /> : null}
                    {__('Save Settings', 'wp-content-flow')}
                </Button>
                
                {isDirty && (
                    <span className="unsaved-changes">
                        {__('You have unsaved changes', 'wp-content-flow')}
                    </span>
                )}
            </div>
        </Panel>
    );
}

export default WorkflowSettingsPanel;