module.exports = [
    { ignores: ['assets/**', 'node_modules/**'] },
    {
        files: ['src/js/**/*.js', 'tests/**/*.js', 'increment-version.js', 'webpack.config.js'],
        plugins: { react: require('eslint-plugin-react') },
        settings: { react: { pragma: 'createElement' } },
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            parserOptions: { ecmaFeatures: { jsx: true } },
            globals: {
                jQuery: 'readonly', QRious: 'readonly', rrzeQr: 'readonly', rrzeQrAdmin: 'readonly',
                require: 'readonly', module: 'readonly', fetch: 'readonly', AbortController: 'readonly',
                URLSearchParams: 'readonly', clearTimeout: 'readonly',
                document: 'readonly', URL: 'readonly', console: 'readonly',
                global: 'readonly', HTMLImageElement: 'readonly', Uint8ClampedArray: 'readonly',
                Buffer: 'readonly', setTimeout: 'readonly', process: 'readonly', __dirname: 'readonly'
            }
        },
        rules: {
            'react/jsx-uses-react': 'error',
            'react/jsx-uses-vars': 'error',
            'no-undef': 'error',
            'no-unreachable': 'error',
            'no-unused-vars': ['error', { caughtErrors: 'none' }]
        }
    }
];
