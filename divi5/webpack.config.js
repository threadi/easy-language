/**
 * Webpack configuration for the Divi 5 modules of Easy Language.
 *
 * Extends the configuration of @wordpress/scripts: the scripts of the Divi 5 visual builder are available as globals
 * (window.divi.*, window.vendor.*), so they are used as externals instead of the WordPress script dependencies.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
// eslint-disable-next-line import/no-extraneous-dependencies -- part of @wordpress/scripts.
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

module.exports = {
	...defaultConfig,
	externals: {
		react: [ 'vendor', 'React' ],
		'react-dom': [ 'vendor', 'ReactDOM' ],
		'@wordpress/hooks': [ 'vendor', 'wp', 'hooks' ],
		'@wordpress/i18n': [ 'vendor', 'wp', 'i18n' ],
		'@divi/module': [ 'divi', 'module' ],
		'@divi/module-library': [ 'divi', 'moduleLibrary' ],
	},
	plugins: [
		// replace the dependency extraction: only create the asset file with the version, without WordPress handles.
		...defaultConfig.plugins.filter(
			( plugin ) =>
				! ( plugin instanceof DependencyExtractionWebpackPlugin )
		),
		new DependencyExtractionWebpackPlugin( {
			requestToExternal: () => false,
			requestToHandle: () => false,
		} ),
	],
};
