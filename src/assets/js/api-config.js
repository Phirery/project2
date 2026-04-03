(function (global) {
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

    function detectBaseUrl(activeEnv) {
        var origin = window.location.origin;
        var path = window.location.pathname || '/';

        // Cho local: ưu tiên /DO_AN/src, fallback theo origin hiện tại
        if (activeEnv === 'local') {
            return path.indexOf('/DO_AN/src/') === 0 || path === '/DO_AN/src' || path === '/DO_AN/src/'
                ? origin + '/DO_AN/src'
                : origin;
        }

        // Cho host: dùng origin thực tế để không phải sửa cứng domain
        return origin;
    }

    function buildConfig() {
        var activeEnv = localStorage.getItem('APP_ENV') || detectEnv();
        var configuredBaseUrl = trimTrailingSlash(detectBaseUrl(activeEnv));
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
