(function (global) {
    var ENV_CONFIG = {
        local: 'http://localhost/DO_AN/src',
        host: 'https://domainex.id.vn'
    };

    function trimTrailingSlash(url) {
        return String(url || '').replace(/\/+$/, '');
    }

    // TỰ ĐỘNG NHẬN DIỆN MÔI TRƯỜNG
    function detectEnv() {
        var hostname = window.location.hostname;
        if (hostname === 'localhost' || hostname === 'localhost:5500') {
            return 'local';
        }
        return 'host';
    }

    function buildConfig() {
        var activeEnv = localStorage.getItem('APP_ENV') || detectEnv();
        var configuredBaseUrl = trimTrailingSlash(ENV_CONFIG[activeEnv]);
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
        localStorage.setItem('APP_ENV', envName);
        location.reload();
    };

    applyConfig();
})(window);