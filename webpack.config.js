const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		'prompt-group/index': path.resolve( __dirname, 'src/blocks/prompt-group/index.js' ),
		'prompt/index': path.resolve( __dirname, 'src/blocks/prompt/index.js' ),
		'journal-view/index': path.resolve( __dirname, 'src/blocks/journal-view/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'src/build' ),
	},
};
