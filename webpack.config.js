const CopyWebpackPlugin = require( 'copy-webpack-plugin' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

/**
 * `wp-scripts build --webpack-copy-php` copies every PHP file under src/, which would
 * duplicate the PSR-4 classes into build/ where nothing autoloads them. Only the block's
 * render.php needs to sit beside its built block.json.
 */
module.exports = {
	...defaultConfig,
	plugins: [
		...defaultConfig.plugins,
		new CopyWebpackPlugin( {
			patterns: [
				{
					from: '**/*.php',
					context: 'src/blocks',
					to: 'blocks/[path][name][ext]',
				},
			],
		} ),
	],
};
