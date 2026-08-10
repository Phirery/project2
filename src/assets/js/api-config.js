(function (global) {
    function trimTrailingSlash(url) {
        return String(url || '').replace(/\/+$/, '');
    }

    // TỰ ĐỘNG NHẬN DIỆN MÔI TRƯỜNG
    function detectEnv() {
        var hostname = window.location.hostname;
        if (hostname === 'localhost' || hostname === '127.0.0.1') {
            return 'local';
        }
        return 'host';
    }

    function detectBaseUrl(activeEnv) {
        var origin = window.location.origin;
        var hostname = window.location.hostname;
        var port = window.location.port || '';
        var path = window.location.pathname || '/';

        // Local dev:
        // - Nếu chạy UI bằng Live Server (:5500), API phải gọi qua Apache/XAMPP (:80)
        // - Nếu mở trực tiếp qua Apache, vẫn dùng /DO_AN/src
        if (activeEnv === 'local') {
            if (port === '5500') {
                return window.location.protocol + '//' + hostname + '/DO_AN/src';
            }

            return path.indexOf('/DO_AN/src/') === 0 || path === '/DO_AN/src' || path === '/DO_AN/src/'
                ? origin + '/DO_AN/src'
                : (window.location.protocol + '//' + hostname + '/DO_AN/src');
        }

        // Host: dùng origin thực tế để không phải sửa cứng domain
        return origin;
    }

    function buildConfig() {
        var activeEnv = detectEnv();
        var configuredBaseUrl = trimTrailingSlash(detectBaseUrl(activeEnv));
        var apiRoot = configuredBaseUrl + '/api';

        return {
            activeEnv: activeEnv,
            baseUrl: configuredBaseUrl,
            endpoints: Object.freeze({
                admin: apiRoot + '/admin',
                auth: apiRoot + '/auth',
                doctor: apiRoot + '/doctor',
                staff: apiRoot + '/staff',
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