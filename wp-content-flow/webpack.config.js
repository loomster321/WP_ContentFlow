const path = require('path');
const defaultConfig = require('@wordpress/scripts/config/webpack.config');

// Customize the default WordPress webpack config
module.exports = {
    ...defaultConfig,
    
    // Entry points for our blocks and scripts
    entry: {
        // Main block editor scripts
        'blocks': path.resolve(__dirname, 'assets/js/blocks.js'),
        
        // Individual blocks
        'blocks/ai-text-generator/index': path.resolve(__dirname, 'blocks/ai-text-generator/index.js'),
        
        // Toolbar and panels
        'improvement-toolbar': path.resolve(__dirname, 'assets/js/improvement-toolbar.js'),
        'workflow-settings': path.resolve(__dirname, 'assets/js/workflow-settings.js'),
        
        // Admin scripts
        'admin': path.resolve(__dirname, 'assets/js/admin.js'),
        
        // Data store
        'workflow-data-store': path.resolve(__dirname, 'assets/js/workflow-data-store.js'),
    },
    
    // Output configuration
    output: {
        ...defaultConfig.output,
        path: path.resolve(__dirname, 'build'),
        filename: '[name].js',
    },
    
    // Resolve WordPress dependencies
    externals: {
        '@wordpress/blocks': ['wp', 'blocks'],
        '@wordpress/block-editor': ['wp', 'blockEditor'],
        '@wordpress/components': ['wp', 'components'],
        '@wordpress/compose': ['wp', 'compose'],
        '@wordpress/data': ['wp', 'data'],
        '@wordpress/element': ['wp', 'element'],
        '@wordpress/i18n': ['wp', 'i18n'],
        '@wordpress/api-fetch': ['wp', 'apiFetch'],
        '@wordpress/url': ['wp', 'url'],
        '@wordpress/plugins': ['wp', 'plugins'],
        '@wordpress/edit-post': ['wp', 'editPost'],
        '@wordpress/editor': ['wp', 'editor'],
        '@wordpress/rich-text': ['wp', 'richText'],
        '@wordpress/icons': ['wp', 'icons'],
        'react': 'React',
        'react-dom': 'ReactDOM',
    },
    
    // Module resolution
    resolve: {
        ...defaultConfig.resolve,
        alias: {
            ...defaultConfig.resolve.alias,
            '@': path.resolve(__dirname),
            '@blocks': path.resolve(__dirname, 'blocks'),
            '@components': path.resolve(__dirname, 'assets/js/components'),
        },
    },
};