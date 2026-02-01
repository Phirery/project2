// API Endpoints
const API_BASE_ADMIN = 'http://localhost/DO_AN/src/backend/api/admin';
const API_BASE_AUTH = 'http://localhost/DO_AN/src/backend/api/auth';

// Component Paths (relative to admin pages)
const COMPONENT_PATH = '../../components/';

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

function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    setTimeout(() => alertDiv.remove(), 5000);
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
        setTimeout(() => window.location.href = '../auth/login.html', 1500);
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