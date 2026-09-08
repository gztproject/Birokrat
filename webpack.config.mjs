import { createRequire } from 'node:module';
import Encore from '@symfony/webpack-encore';

const require = createRequire(import.meta.url);

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')
    .cleanupOutputBeforeBuild()
    .autoProvidejQuery()
    .autoProvideVariables({
        'window.Bloodhound': require.resolve('bloodhound-js'),
        'jQuery.tagsinput': 'bootstrap-tagsinput',
    })
    .enableSassLoader()
    .enableVersioning()

    .addEntry('js/app', './assets/js/app.js')
    .addEntry('js/user/admin', './assets/js/user/admin.js')
    .addEntry('js/user/showUser', './assets/js/user/showUser.js')
    .addEntry('js/dashboard', './assets/js/dashboard.js')
    .addEntry('js/search', './assets/js/search.js')
    .addEntry('js/codesheets', './assets/js/codesheets.js')

    .addEntry('js/travelExpense/index', './assets/js/travelExpense/index.js')
    .addEntry('js/travelExpense/new', './assets/js/travelExpense/new.js')
    .addEntry('js/travelExpense/filter', './assets/js/travelExpense/filter.js')

    .addEntry('js/lunchExpense/index', './assets/js/lunchExpense/index.js')
    .addEntry('js/lunchExpense/filter', './assets/js/lunchExpense/filter.js')

    .addEntry('js/invoice/new', './assets/js/invoice/new.js')
    .addEntry('js/invoice/list', './assets/js/invoice/list.js')
    .addEntry('js/invoice/view', './assets/js/invoice/view.js')

    .addEntry('js/incomingInvoice/new', './assets/js/incomingInvoice/new.js')
    .addEntry('js/incomingInvoice/list', './assets/js/incomingInvoice/list.js')

    .addEntry('js/organization/new', './assets/js/organization/new.js')
    .addEntry('js/organization/show', './assets/js/organization/show.js')

    .addEntry('js/transaction/new', './assets/js/transaction/new.js')

    .addEntry('js/geography/country/show', './assets/js/geography/country/show.js')

    .addEntry('js/common/filters/dateOrgFilter', './assets/js/common/filters/dateOrgFilter.js')

    .addEntry('js/poc.typeahead', './assets/js/poc.typeahead.js')

    .addStyleEntry('css/app', ['./assets/scss/app.scss'])
    .addStyleEntry('css/copyleft', ['./assets/css/copyleft.css'])
    .addStyleEntry('css/admin', ['./assets/scss/admin.scss'])
    .addStyleEntry('css/dashboard', ['./assets/scss/dashboard.scss'])
    .addStyleEntry('css/invoice', ['./assets/scss/invoice.scss'])
    .addStyleEntry('css/report', ['./assets/scss/report.scss'])

    .splitEntryChunks()
    .enableSourceMaps(!Encore.isProduction())
    .enableSingleRuntimeChunk()
;

const config = await Encore.getWebpackConfig();
export default config;
