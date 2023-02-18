const Encore = require('@symfony/webpack-encore');

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    // directory where compiled assets will be stored
    .setOutputPath('public/build/')
    // public path used by the web server to access the output path
    .setPublicPath('/build')
    // only needed for CDN's or subdirectory deploy
    //.setManifestKeyPrefix('build/')

    /*
     * ENTRY CONFIG
     *
     * Each entry will result in one JavaScript file (e.g. app.js)
     * and one CSS file (e.g. app.css) if your JavaScript imports CSS.
     */
    .addEntry('js/app', './assets/js/app.js')
    .addEntry('js/user/admin', './assets/js/user/admin.js')
    .addEntry('js/user/showUser', './assets/js/user/showUser.js')
    .addEntry('js/dashboard', './assets/js/dashboard.js')
    .addEntry('js/search', './assets/js/search.js')
    .addEntry('js/codesheets', './assets/js/codesheets.js')
    
    //TravelExpense
    .addEntry('js/travelExpense/index', './assets/js/travelExpense/index.js')
    .addEntry('js/travelExpense/new', './assets/js/travelExpense/new.js')
    .addEntry('js/travelExpense/filter', './assets/js/travelExpense/filter.js')

    //LunchExpense
    .addEntry('js/lunchExpense/index', './assets/js/lunchExpense/index.js')
    .addEntry('js/lunchExpense/filter', './assets/js/lunchExpense/filter.js')

    //Invoice
    .addEntry('js/invoice/new', './assets/js/invoice/new.js')
    .addEntry('js/invoice/list', './assets/js/invoice/list.js')
    .addEntry('js/invoice/view', './assets/js/invoice/view.js')

    //IncomingInvoice
    .addEntry('js/incomingInvoice/new', './assets/js/incomingInvoice/new.js')
    .addEntry('js/incomingInvoice/list', './assets/js/incomingInvoice/list.js')
    
    //Organization
    .addEntry('js/organization/new', './assets/js/organization/new.js')
    .addEntry('js/organization/show', './assets/js/organization/show.js')

    //Transaction
    .addEntry('js/transaction/new', './assets/js/transaction/new.js')

    //Geography
    .addEntry('js/geography/country/show', './assets/js/geography/country/show.js')

    //Common
    //  ->filters
    .addEntry('js/common/filters/dateOrgFilter', './assets/js/common/filters/dateOrgFilter.js')

    //POC
    .addEntry('js/poc.typeahead', './assets/js/poc.typeahead.js')

    
    //Styles
    .addStyleEntry('css/app', ['./assets/scss/app.scss'])
    .addStyleEntry('css/copyleft', ['./assets/css/copyleft.css'])
    .addStyleEntry('css/admin', ['./assets/scss/admin.scss'])
    .addStyleEntry('css/dashboard', ['./assets/scss/dashboard.scss'])
    .addStyleEntry('css/invoice', ['./assets/scss/invoice.scss'])
    .addStyleEntry('css/report', ['./assets/scss/report.scss'])    
   
    // enables the Symfony UX Stimulus bridge (used in assets/bootstrap.js)
    .enableStimulusBridge('./assets/controllers.json')

    // When enabled, Webpack "splits" your files into smaller pieces for greater optimization.
    .splitEntryChunks()

    // will require an extra script tag for runtime.js
    // but, you probably want this, unless you're building a single-page app
    .enableSingleRuntimeChunk()

    /*
     * FEATURE CONFIG
     *
     * Enable & configure other features below. For a full
     * list of features, see:
     * https://symfony.com/doc/current/frontend.html#adding-more-features
     */
    .cleanupOutputBeforeBuild()
    .enableBuildNotifications()
    .enableSourceMaps(!Encore.isProduction())
    // enables hashed filenames (e.g. app.abc123.css)
    .enableVersioning(Encore.isProduction())

    // configure Babel
    // .configureBabel((config) => {
    //     config.plugins.push('@babel/a-babel-plugin');
    // })

    // enables and configure @babel/preset-env polyfills
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = '3.23';
    })

    // enables Sass/SCSS support
    .enableSassLoader()

    // uncomment if you use TypeScript
    //.enableTypeScriptLoader()

    // uncomment if you use React
    //.enableReactPreset()

    // uncomment to get integrity="..." attributes on your script & link tags
    // requires WebpackEncoreBundle 1.4 or higher
    //.enableIntegrityHashes(Encore.isProduction())

    // uncomment if you're having problems with a jQuery plugin
    .autoProvidejQuery()
;

module.exports = Encore.getWebpackConfig();
