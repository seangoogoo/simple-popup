const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		editor: path.resolve( process.cwd(), 'src/editor', 'index.js' ),
		public: path.resolve( process.cwd(), 'src/public', 'index.js' ),
	},
};
