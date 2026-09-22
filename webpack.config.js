const path = require('path');
const config = require('@wordpress/scripts/config/webpack.config');

module.exports = {
    ...config,
    entry: {
        'admin.min': './src/js/admin.js'
    },
    output: { ...config.output, path: path.resolve(__dirname, 'assets/js'), filename: '[name].js' }
};
