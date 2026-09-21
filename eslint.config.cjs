module.exports = [
    { ignores: ['assets/**', 'node_modules/**'] },
    {
        files: ['src/js/**/*.js', 'tests/**/*.js', 'increment-version.js'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'commonjs',
            globals: {
                jQuery: 'readonly', QRious: 'readonly', rrzeQr: 'readonly',
                document: 'readonly', URL: 'readonly', console: 'readonly',
                global: 'readonly', HTMLImageElement: 'readonly', Uint8ClampedArray: 'readonly',
                Buffer: 'readonly', setTimeout: 'readonly', process: 'readonly', __dirname: 'readonly'
            }
        },
        rules: {
            'no-undef': 'error',
            'no-unreachable': 'error',
            'no-unused-vars': ['error', { caughtErrors: 'none' }]
        }
    }
];
