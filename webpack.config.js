const Encore = require('@symfony/webpack-encore');

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    // Directory where compiled assets will be stored
    .setOutputPath('public/build/')

    // Public path used by the web server to access the output path
    .setPublicPath('/build')

    // Main entry (includes Tailwind)
    .addEntry('app', './assets/app.js')

    // Symfony Stimulus controllers (safe to leave even if you don’t use them yet)
    .enableStimulusBridge('./assets/controllers.json')

    // Enable PostCSS for Tailwind
    .enablePostCssLoader()

    // ✅ Add this line to fix the error
    .enableSingleRuntimeChunk()

    // Optional: enables source maps and versioning in production
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())
;

module.exports = Encore.getWebpackConfig();
