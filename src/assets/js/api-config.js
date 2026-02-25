(function (global) {
    var ENV_CONFIG = {
        local: 'http://localhost/DO_AN/src',
        host: 'https://domainex.id.vn'
    };

    function trimTrailingSlash(url) {
        return String(url || '').replace(/\/+$/, '');
    }

    function readStoredEnv() {
        var env = localStorage.getItem('APP_ENV');
        return ENV_CONFIG[env] ? env : 'local';
    }

    function readCustomBaseUrl() {
        var value = localStorage.getItem('APP_BASE_URL');
        return value ? trimTrailingSlash(value) : '';
    }

    function buildConfig() {
        var activeEnv = readStoredEnv();
        var configuredBaseUrl = readCustomBaseUrl() || trimTrailingSlash(ENV_CONFIG[activeEnv]);
        var apiRoot = configuredBaseUrl + '/api';

        return {
            activeEnv: activeEnv,
            baseUrl: configuredBaseUrl,
            endpoints: Object.freeze({
                admin: apiRoot + '/admin',
                auth: apiRoot + '/auth',
                doctor: apiRoot + '/doctor',
                patient: apiRoot + '/patient',
                payment: apiRoot + '/payment',
                user: apiRoot + '/user'
            })
        };
    }

    function applyConfig() {
        var config = buildConfig();
        global.APP_ENV = config.activeEnv;
        global.APP_BASE_URL = config.baseUrl;
        global.API_ENDPOINTS = config.endpoints;
    }

    global.setAppEnvironment = function (envName) {
        if (!ENV_CONFIG[envName]) {
            throw new Error('Invalid APP_ENV value. Use "local" or "host".');
        }

        localStorage.setItem('APP_ENV', envName);
        localStorage.removeItem('APP_BASE_URL');
        applyConfig();
    };

    global.setAppBaseUrl = function (baseUrl) {
        var normalized = trimTrailingSlash(baseUrl);
        if (!normalized) {
            throw new Error('Base URL must not be empty.');
        }

        localStorage.setItem('APP_BASE_URL', normalized);
        applyConfig();
    };

    global.clearAppBaseUrl = function () {
        localStorage.removeItem('APP_BASE_URL');
        applyConfig();
    };

    global.getApiConfig = function () {
        return {
            appEnv: global.APP_ENV,
            baseUrl: global.APP_BASE_URL,
            endpoints: global.API_ENDPOINTS
        };
    };

    applyConfig();
})(window);

