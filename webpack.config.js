var Encore = require('@symfony/webpack-encore');

Encore    
    .setOutputPath('public/build/')
    .setPublicPath('/build')
    .cleanupOutputBeforeBuild()
    .autoProvidejQuery()
    .autoProvideVariables({
        "window.Bloodhound": require.resolve('bloodhound-js'),
        "jQuery.tagsinput": "bootstrap-tagsinput"
    })
    .enableSassLoader()
    .enableVersioning()
    .addEntry('js/app', './assets/js/app.js')
    .addEntry('js/admin', './assets/js/admin.js')
    .addEntry('js/dashboard', './assets/js/dashboard.js')
    .addEntry('js/search', './assets/js/search.js')
    .addEntry('js/codesheets', './assets/js/codesheets.js')
    .addEntry('js/travelExpense', './assets/js/travelExpense.js')
    .addEntry('js/invoice', './assets/js/invoice.js')
    .addEntry('js/organization', './assets/js/organization.js')
    .addStyleEntry('css/app', ['./assets/scss/app.scss'])
    .addStyleEntry('css/copyleft', ['./assets/css/copyleft.css'])
    .addStyleEntry('css/admin', ['./assets/scss/admin.scss'])
    .addStyleEntry('css/dashboard', ['./assets/scss/dashboard.scss'])
    .addStyleEntry('css/invoice', ['./assets/scss/invoice.scss'])
    .splitEntryChunks()
    .enableSourceMaps(!Encore.isProduction())
;

module.exports = Encore.getWebpackConfig();
const CopyWebpackPlugin = require('copy-webpack-plugin');


