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
    .enableSassLoader()
    .enableVersioning()
    .enableStimulusBridge('./assets/controllers.json')
    .addEntry('js/app', './assets/js/app.js')
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
