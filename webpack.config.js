const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const TerserPlugin = require('terser-webpack-plugin');

module.exports = {
  entry: './src/index.js',
  output: {
    path: path.resolve(__dirname, 'assets'),
    filename: 'cp-wpml-auto-translate-admin.js',
  },
  optimization: {
    minimizer: [
      new TerserPlugin({
        extractComments: false, // Disable license file extraction
      }),
    ],
  },
  module: {
    rules: [
      {
        test: /\.(js|jsx)$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: ['@babel/preset-env', '@babel/preset-react'],
          },
        },
      },
      {
        test: /\.css$/,
        use: [MiniCssExtractPlugin.loader, 'css-loader'],
      },
    ],
  },
  plugins: [
    new MiniCssExtractPlugin({
      filename: 'cp-wpml-auto-translate-admin.css',
    }),
  ],
  resolve: {
    extensions: ['.js', '.jsx'],
  },
  externals: {
    jquery: 'jQuery',
  },
};

