const path = require('path');
const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
    ...defaultConfig,
    entry: {
        editor: './assets/js/editor.js',
        admin: './assets/js/admin.js'
    },
    output: {
        path: path.resolve(__dirname, 'dist'),
        filename: '[name].js'
    },
    externals: {
        '@wordpress/blocks': ['wp', 'blocks'],
        '@wordpress/i18n': ['wp', 'i18n'],
        '@wordpress/element': ['wp', 'element'],
        '@wordpress/components': ['wp', 'components'],
        '@wordpress/data': ['wp', 'data'],
        '@wordpress/plugins': ['wp', 'plugins'],
        '@wordpress/edit-post': ['wp', 'editPost'],
        '@wordpress/api-fetch': ['wp', 'apiFetch']
    }
};