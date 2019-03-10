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
    .addEntry('js/user/admin', './assets/js/user/admin.js')
    .addEntry('js/user/showUser', './assets/js/user/showUser.js')
    .addEntry('js/dashboard', './assets/js/dashboard.js')
    .addEntry('js/search', './assets/js/search.js')
    .addEntry('js/codesheets', './assets/js/codesheets.js')
    .addEntry('js/travelExpense', './assets/js/travelExpense.js')
    .addEntry('js/invoice/new', './assets/js/invoice/new.js')
    .addEntry('js/invoice/list', './assets/js/invoice/list.js')
    .addEntry('js/organization/new', './assets/js/organization/new.js')
    .addEntry('js/organization/show', './assets/js/organization/show.js')
    .addEntry('js/poc.typeahead', './assets/js/poc.typeahead.js')

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


