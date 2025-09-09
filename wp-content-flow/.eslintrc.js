/**
 * ESLint configuration for WordPress AI Content Flow Plugin
 */

module.exports = {
    root: true,
    extends: [
        '@wordpress/eslint-config/recommended',
        '@wordpress/eslint-config/i18n',
    ],
    env: {
        browser: true,
        es6: true,
        node: true,
        jest: true,
    },
    parserOptions: {
        ecmaVersion: 2020,
        sourceType: 'module',
        ecmaFeatures: {
            jsx: true,
        },
    },
    globals: {
        // WordPress globals
        wp: 'readonly',
        wpApiSettings: 'readonly',
        wpContentFlow: 'readonly',
        
        // jQuery (if needed)
        jQuery: 'readonly',
        $: 'readonly',
        
        // Modern browser APIs
        fetch: 'readonly',
        AbortController: 'readonly',
    },
    rules: {
        // WordPress specific rules
        '@wordpress/no-unsafe-wp-apis': 'warn',
        '@wordpress/dependency-group': 'error',
        '@wordpress/valid-sprintf': 'error',
        '@wordpress/i18n-text-domain': [
            'error',
            {
                allowedTextDomain: 'wp-content-flow',
            },
        ],
        '@wordpress/i18n-translator-comments': 'error',
        '@wordpress/i18n-no-variables': 'error',
        '@wordpress/i18n-no-placeholders-only': 'error',
        '@wordpress/i18n-no-collapsible-whitespace': 'error',
        
        // Code style rules
        'indent': ['error', 'tab'],
        'quotes': ['error', 'single'],
        'semi': ['error', 'always'],
        'comma-dangle': ['error', 'always-multiline'],
        'object-curly-spacing': ['error', 'always'],
        'array-bracket-spacing': ['error', 'always'],
        'space-in-parens': ['error', 'always'],
        
        // Best practices
        'no-console': 'warn',
        'no-debugger': 'error',
        'no-unused-vars': 'error',
        'no-undef': 'error',
        'prefer-const': 'error',
        'no-var': 'error',
        
        // React/JSX rules
        'react/jsx-uses-react': 'off',
        'react/react-in-jsx-scope': 'off',
        'react/prop-types': 'off', // We use TypeScript for prop validation
        
        // Import rules
        'import/order': [
            'error',
            {
                'groups': [
                    'builtin',
                    'external',
                    'internal',
                    'parent',
                    'sibling',
                    'index',
                ],
                'newlines-between': 'always',
            },
        ],
    },
    overrides: [
        {
            // Test files
            files: ['**/*.test.js', '**/tests/**/*.js'],
            env: {
                jest: true,
            },
            rules: {
                'no-console': 'off',
                '@wordpress/no-global-get-selection': 'off',
            },
        },
        {
            // Configuration files
            files: [
                '*.config.js',
                '.eslintrc.js',
                'webpack.config.js',
                'jest.config.js',
            ],
            env: {
                node: true,
            },
            rules: {
                'no-console': 'off',
            },
        },
        {
            // Block files
            files: ['blocks/**/*.js', 'assets/js/blocks*.js'],
            rules: {
                '@wordpress/no-unused-vars-before-return': 'error',
                '@wordpress/prefer-type-annotations': 'warn',
            },
        },
    ],
    settings: {
        'import/resolver': {
            node: {
                extensions: ['.js', '.jsx', '.ts', '.tsx'],
            },
        },
    },
};