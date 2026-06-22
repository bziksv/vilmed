module.exports = {
	input: './src/index.js',
	output: {
		js: './build.js',
		css: './build.css',
	},
	namespace: 'BX.YandexMarket.Field.Param',
	adjustConfigPhp: false,
	browserslist: true,
	minification: true,
};