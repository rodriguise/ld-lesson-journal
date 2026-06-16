const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		'prompt-group/index': path.resolve( __dirname, 'src/blocks/prompt-group/index.js' ),
		'prompt/index': path.resolve( __dirname, 'src/blocks/prompt/index.js' ),
		'journal-view/index': path.resolve( __dirname, 'src/blocks/journal-view/index.js' ),
		'screen-only/index': path.resolve( __dirname, 'src/blocks/screen-only/index.js' ),
		'grader/index': path.resolve( __dirname, 'src/blocks/grader/index.js' ),
		'journal-browse/index': path.resolve( __dirname, 'src/blocks/journal-browse/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'src/build' ),
	},
};
