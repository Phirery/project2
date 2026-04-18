// API Endpoints
if (!window.API_ENDPOINTS) {
    console.error('Missing API config. Please include assets/js/api-config.js before config-admin.js');
}

const currentOrigin = window.location.origin;
const pathSegments = window.location.pathname.split('/');
const projectPath = pathSegments.includes('DO_AN') ? '/DO_AN/src' : '';
const FALLBACK_API_ROOT = `${currentOrigin}${projectPath}/api`;

const API_BASE_ADMIN = window.API_ENDPOINTS?.admin || `${FALLBACK_API_ROOT}/admin`;
const API_BASE_AUTH = window.API_ENDPOINTS?.auth || `${FALLBACK_API_ROOT}/auth`;

// Component Paths (relative to admin pages)
const COMPONENT_PATH = 'components/';

// Admin Info Storage
let ADMIN_INFO = {
    id: null,
    tenDangNhap: 'Admin',
    vaiTro: 'quantri'
};

// Notification Count
let UNREAD_NOTIFICATION_COUNT = 0;

function formatDateTime(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleString('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

function truncateText(text, maxLength) {
    if (!text) return 'N/A';
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function ensureShowAlertStyles() {
    if (document.getElementById('showAlertLoginStyle')) return;

    const style = document.createElement('style');
    style.id = 'showAlertLoginStyle';
    style.textContent = `
        .alert-custom {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }
        .alert-stack-container {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 99999;
            width: min(420px, calc(100vw - 24px));
            pointer-events: none;
        }
        .alert-stack-container .alert-custom {
            min-width: 0;
            max-width: 100%;
            pointer-events: auto;
        }
        .alert-custom.alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        .alert-custom.alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        @keyframes showAlertSlideIn {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes showAlertSlideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(400px); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
}

function getAlertContainer() {
    let container = document.getElementById('alertStackContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'alertStackContainer';
        container.className = 'alert-stack-container';
        document.body.appendChild(container);
    }
    return container;
}

function showAlert(type, message) {
    ensureShowAlertStyles();
    const container = getAlertContainer();

    const alertDiv = document.createElement('div');
    alertDiv.className = `alert-custom alert-${type === 'success' ? 'success' : 'danger'}`;
    alertDiv.style.animation = 'showAlertSlideIn 0.3s';

    const icon = document.createElement('i');
    icon.className = `fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}`;

    const content = document.createElement('span');
    content.textContent = message;

    alertDiv.appendChild(icon);
    alertDiv.appendChild(content);
    container.appendChild(alertDiv);

    setTimeout(() => {
        alertDiv.style.animation = 'showAlertSlideOut 0.3s';
        setTimeout(() => {
            alertDiv.remove();
            if (!container.children.length) container.remove();
        }, 300);
    }, 4000);
}

function animateNumber(elementId, targetNumber) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    let currentNumber = 0;
    const increment = targetNumber / 50;
    const duration = 1000;
    const stepTime = duration / 50;
    
    const timer = setInterval(() => {
        currentNumber += increment;
        if (currentNumber >= targetNumber) {
            element.textContent = targetNumber.toLocaleString('vi-VN');
            clearInterval(timer);
        } else {
            element.textContent = Math.floor(currentNumber).toLocaleString('vi-VN');
        }
    }, stepTime);
}

function handleSessionExpired(msg) {
    if (
        !msg ||
        msg === "fetch_failed" ||
        msg.includes('Phiên đăng nhập') ||
        msg.includes('Không có quyền') ||
        msg.includes('Chưa đăng nhập')
    ) {
        showAlert('error', 'Phiên đăng nhập hết hạn. Vui lòng đăng nhập lại.');
        //setTimeout(() => window.location.href = 'login.html', 1500);
    }
}

function showLoading(show, containerId = 'loadingIndicator') {
    const loading = document.getElementById(containerId);
    if (loading) {
        loading.style.display = show ? 'block' : 'none';
    }
}

// Console branding with new colors
console.log('%c🏥 Eden Health - Admin Panel', 'color: #4A90E2; font-size: 16px; font-weight: bold;');
console.log('%cConfig loaded successfully', 'color: #52c41a; font-size: 12px;');
