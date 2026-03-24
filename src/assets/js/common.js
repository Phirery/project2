const currentOrigin = window.location.origin;
const pathSegments = window.location.pathname.split('/');
const projectPath = pathSegments.includes('DO_AN') ? '/DO_AN/src' : '';
const FALLBACK_API_ROOT = `${currentOrigin}${projectPath}/api`;

if (!window.API_ENDPOINTS) {
    console.warn('API config missing! Using automatic fallback.');
}
const API_BASE = window.API_ENDPOINTS || {};

const API_BASE_PATIENT = API_BASE.patient || `${FALLBACK_API_ROOT}/patient`;
const API_BASE_AUTH    = API_BASE.auth    || `${FALLBACK_API_ROOT}/auth`;
const DEFAULT_DB_AVATAR_KEY = 'samples/paper.png';

function hasCustomAvatar(avatarUrl) {
    return typeof avatarUrl === 'string'
        && avatarUrl.trim() !== ''
        && !avatarUrl.includes(DEFAULT_DB_AVATAR_KEY);
}

function renderCircleAvatar(element, fullName, avatarUrl) {
    if (!element) return;

    const displayName = (fullName || '').trim();
    const firstLetter = (displayName.charAt(0) || 'U').toUpperCase();

    element.innerHTML = '';
    element.style.overflow = 'hidden';

    if (hasCustomAvatar(avatarUrl)) {
        const img = document.createElement('img');
        img.src = avatarUrl;
        img.alt = displayName || 'Avatar';
        img.style.width = '100%';
        img.style.height = '100%';
        img.style.objectFit = 'cover';
        img.style.borderRadius = '50%';
        img.onerror = () => {
            element.innerHTML = '';
            element.textContent = firstLetter;
        };
        element.appendChild(img);
        return;
    }

    element.textContent = firstLetter;
}

function normalizeRole(rawRole) {
    if (rawRole == null) return '';

    const normalized = String(rawRole)
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[\s_-]+/g, '');

    if (normalized === 'benhnhan' || normalized === 'patient') return 'benhnhan';
    if (normalized === 'bacsi' || normalized === 'doctor') return 'bacsi';
    if (normalized === 'quantri' || normalized === 'admin' || normalized === 'administrator' || normalized === 'quantrivien') return 'quantri';

    return normalized;
}

function setMenuItemVisible(item, visible) {
    if (!item) return;
    item.style.display = visible ? 'list-item' : 'none';
}

// Load Header và Footer vào trang
async function loadComponents() {
    try {
        // Load Header
        const headerResponse = await fetch('components/header.html');
        if (!headerResponse.ok) {
            throw new Error(`HTTP error! status: ${headerResponse.status}`);
        }
        const headerHTML = await headerResponse.text();
        const headerPlaceholder = document.getElementById('header-placeholder');
        if (headerPlaceholder) {
            headerPlaceholder.innerHTML = headerHTML;
        }

        // Load Footer
        const footerResponse = await fetch('components/footer.html');
        if (!footerResponse.ok) {
            console.warn('Footer not found, skipping...');
        } else {
            const footerHTML = await footerResponse.text();
            const footerElement = document.getElementById('footer-placeholder');
            if (footerElement) {
                footerElement.innerHTML = footerHTML;
            }
        }

        // Init sau khi load xong
        initAfterLoad();
    } catch (error) {
        console.error('Error loading components:', error);
        hidePageLoader();
    }
}

// Initialize sau khi load header/footer
function initAfterLoad() {
    checkLoginStatus();
    setupMobileMenu();
    setupScrollEffects();
    setActiveNavLink();
    
    // Event listeners
    document.getElementById('btnDesktopLogout')?.addEventListener('click', logout);
    document.getElementById('btnMobileLogout')?.addEventListener('click', logout);
}

// Check Login Status
async function checkLoginStatus() {
    try {
        const response = await fetch(`${API_BASE_AUTH}/get-current-user.php`, {
            method: 'GET',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' }
        });

        const data = await response.json();

        // Desktop elements
        const authButtons = document.getElementById('authButtons');
        const logoutHeader = document.getElementById('logoutHeader');
        const navLoggedInItems = document.querySelectorAll('.nav-item-loggedin');
        const userAvatar = document.getElementById('userAvatar');
        const headerUserName = document.getElementById('headerUserName');
        const notificationIconWrapper = document.getElementById('notificationIconWrapper');

        // Mobile elements
        const mobileAuthButtons = document.getElementById('mobileAuthButtons');
        const mobileUserInfo = document.getElementById('mobileUserInfo');
        const mobileMenuLoggedIn = document.querySelectorAll('.mobile-menu-loggedin');
        const mobileUserAvatar = document.getElementById('mobileUserAvatar');
        const mobileUserName = document.getElementById('mobileUserName');

        if (data.success) {
            const fullName = data.data.fullName || data.data.username || 'Người dùng';
            const normalizedRole = normalizeRole(data.data.role);

            // Desktop
            if (authButtons) authButtons.style.display = 'none';
            if (logoutHeader) logoutHeader.classList.add('active');
            navLoggedInItems.forEach(item => setMenuItemVisible(item, true));
            
            renderCircleAvatar(userAvatar, fullName, data.data.avatar);
            if (headerUserName) headerUserName.textContent = fullName;

            // Show notification icon (chỉ cho bệnh nhân)
            if (normalizedRole === 'benhnhan') {
                if (notificationIconWrapper) notificationIconWrapper.style.display = 'block';
                initNotifications();
            }

            // Mobile
            if (mobileAuthButtons) mobileAuthButtons.style.display = 'none';
            if (mobileUserInfo) mobileUserInfo.classList.add('active');
            mobileMenuLoggedIn.forEach(item => setMenuItemVisible(item, true));
            
            renderCircleAvatar(mobileUserAvatar, fullName, data.data.avatar);
            if (mobileUserName) mobileUserName.textContent = fullName;

            applyRoleNavigation(normalizedRole);
            updateProfileLink(normalizedRole);

        } else {
            // Not logged in
            if (authButtons) authButtons.style.display = 'flex';
            if (logoutHeader) logoutHeader.classList.remove('active');
            navLoggedInItems.forEach(item => setMenuItemVisible(item, false));
            if (notificationIconWrapper) notificationIconWrapper.style.display = 'none';

            if (mobileAuthButtons) mobileAuthButtons.style.display = 'flex';
            if (mobileUserInfo) mobileUserInfo.classList.remove('active');
            mobileMenuLoggedIn.forEach(item => setMenuItemVisible(item, false));
            applyRoleNavigation(null);
        }

    } catch (error) {
        console.error('Error checking login:', error);
    } finally {
        hidePageLoader();
    }
}

function hidePageLoader() {
    setTimeout(() => {
        const loader = document.getElementById('pageLoader');
        if (loader) {
            loader.style.opacity = '0';
            loader.style.transition = 'opacity 0.5s ease';
            setTimeout(() => {
                loader.style.display = 'none';
            }, 500);
        }
        // Also add loaded class to body
        document.body.classList.add('loaded');
    }, 300);
}

function ensureShowAlertStyles() {
    if (document.getElementById('showAlertLoginStyle')) return;

    const style = document.createElement('style');
    style.id = 'showAlertLoginStyle';
    style.textContent = `
        .alert-custom {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
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

function showAlert(type, message) {
    ensureShowAlertStyles();

    const alertDiv = document.createElement('div');
    alertDiv.className = `alert-custom alert-${type === 'success' ? 'success' : 'danger'} position-fixed`;
    alertDiv.style.cssText = 'top: 80px; right: 20px; z-index: 99999; min-width: 320px; max-width: 420px; animation: showAlertSlideIn 0.3s;';

    const icon = document.createElement('i');
    icon.className = `fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}`;

    const content = document.createElement('span');
    content.textContent = message;

    alertDiv.appendChild(icon);
    alertDiv.appendChild(content);
    document.body.appendChild(alertDiv);

    setTimeout(() => {
        alertDiv.style.animation = 'showAlertSlideOut 0.3s';
        setTimeout(() => alertDiv.remove(), 300);
    }, 4000);
}

function updateProfileLink(role) {
    const normalizedRole = normalizeRole(role);
    const profileLinks = document.querySelectorAll('a[href="tai-khoan-ca-nhan.html"]');
    if (normalizedRole === 'bacsi') {
        profileLinks.forEach(link => link.href = 'dashboard-doctor.html');
    } else if (normalizedRole === 'quantri') {
        profileLinks.forEach(link => link.href = 'dashboard-admin.html');
    }
}

function applyRoleNavigation(role) {
    const normalizedRole = normalizeRole(role);
    const isDoctor = normalizedRole === 'bacsi';
    const isAdmin = normalizedRole === 'quantri';
    const isStaff = isDoctor || isAdmin;
    const shouldShowPatientItems = Boolean(normalizedRole) && !isStaff;

    const patientDesktopItems = document.querySelectorAll('.nav-item-patient-only');
    const patientMobileItems = document.querySelectorAll('.mobile-menu-patient-only');
    const dashboardDesktopItems = document.querySelectorAll('.nav-item-dashboard');
    const dashboardMobileItems = document.querySelectorAll('.mobile-menu-dashboard');

    patientDesktopItems.forEach(item => {
        setMenuItemVisible(item, shouldShowPatientItems);
    });
    patientMobileItems.forEach(item => {
        setMenuItemVisible(item, shouldShowPatientItems);
    });

    const dashboardHref = isDoctor ? 'dashboard-doctor.html' : (isAdmin ? 'dashboard-admin.html' : '');

    dashboardDesktopItems.forEach(item => {
        setMenuItemVisible(item, isStaff);
        const link = item.querySelector('a');
        if (link && dashboardHref) {
            link.href = dashboardHref;
        }
    });

    dashboardMobileItems.forEach(item => {
        setMenuItemVisible(item, isStaff);
        const link = item.querySelector('a');
        if (link && dashboardHref) {
            link.href = dashboardHref;
        }
    });
}

// Logout
async function logout() {
    try {
        const response = await fetch(`${API_BASE_AUTH}/logout.php`, {
            method: 'POST',
            credentials: 'include'
        });

        const data = await response.json();
        if (data.success) {
            localStorage.clear();
            window.location.reload();
        }
    } catch (error) {
        console.error('Logout error:', error);
        showAlert('error', 'Có lỗi xảy ra khi đăng xuất');
    }
}

// ========================================
// NOTIFICATION SYSTEM
// ========================================

let allNotifications = [];

function initNotifications() {
    const notificationBell = document.getElementById('notificationBell');
    const notificationPanel = document.getElementById('notificationPanel');
    const notificationOverlay = document.getElementById('notificationOverlay');
    const closeNotificationPanel = document.getElementById('closeNotificationPanel');
    const btnMarkAllRead = document.getElementById('btnMarkAllRead');

    // Open panel
    notificationBell?.addEventListener('click', (e) => {
        e.stopPropagation();
        openNotificationPanel();
    });

    // Close panel
    closeNotificationPanel?.addEventListener('click', closeNotificationPanelFn);
    notificationOverlay?.addEventListener('click', closeNotificationPanelFn);

    // Mark all read
    btnMarkAllRead?.addEventListener('click', markAllNotificationsRead);

    // Load notifications
    loadNotifications();
    loadUnreadCount();

    // Auto refresh count every 30s
    setInterval(loadUnreadCount, 30000);
}

function openNotificationPanel() {
    const panel = document.getElementById('notificationPanel');
    const overlay = document.getElementById('notificationOverlay');
    
    panel?.classList.add('active');
    overlay?.classList.add('active');
    document.body.style.overflow = 'hidden';

    loadNotifications(); // Refresh when open
}

function closeNotificationPanelFn() {
    const panel = document.getElementById('notificationPanel');
    const overlay = document.getElementById('notificationOverlay');
    
    panel?.classList.remove('active');
    overlay?.classList.remove('active');
    document.body.style.overflow = '';
}

async function loadNotifications() {
    const loading = document.getElementById('notificationLoading');
    const empty = document.getElementById('notificationEmpty');
    const list = document.getElementById('notificationList');

    if (!loading || !empty || !list) return;

    loading.style.display = 'flex';
    empty.style.display = 'none';
    list.innerHTML = '';

    try {
        const response = await fetch(`${API_BASE_PATIENT}/get-patient-notifications.php`, {
            credentials: 'include'
        });
        const data = await response.json();

        if (data.success && data.data.length > 0) {
            allNotifications = data.data;
            renderNotifications(data.data);
            loading.style.display = 'none';
        } else {
            loading.style.display = 'none';
            empty.style.display = 'flex';
        }
    } catch (error) {
        console.error('Error loading notifications:', error);
        loading.style.display = 'none';
        empty.style.display = 'flex';
    }
}

function renderNotifications(notifications) {
    const list = document.getElementById('notificationList');
    if (!list) return;
    
    list.innerHTML = notifications.map(notif => {
        const isUnread = !notif.daXem;
        const timeAgo = getTimeAgo(notif.thoiGian);
        const iconClass = notif.loai === 'Hệ thống' ? 'type-hethong' : 'type-lichkham';
        const icon = notif.loai === 'Hệ thống' ? 'fa-info-circle' : 'fa-calendar-check';

        return `
            <div class="notification-item ${isUnread ? 'unread' : ''}" 
                 data-id="${notif.maThongBao}" 
                 onclick="handleNotificationClick(${notif.maThongBao})">
                <div class="notification-header">
                    <div class="notification-icon ${iconClass}">
                        <i class="fas ${icon}"></i>
                    </div>
                    <div class="notification-title">${notif.tieuDe}</div>
                    <div class="notification-time">${timeAgo}</div>
                </div>
                <div class="notification-content collapsed" id="content-${notif.maThongBao}">
                    ${notif.noiDung}
                </div>
            </div>
        `;
    }).join('');
}

async function handleNotificationClick(maThongBao) {
    const item = document.querySelector(`.notification-item[data-id="${maThongBao}"]`);
    const content = document.getElementById(`content-${maThongBao}`);

    if (!item || !content) return;

    // Expand/collapse content
    if (content.classList.contains('collapsed')) {
        content.classList.remove('collapsed');
        
        // Mark as read if unread
        if (item.classList.contains('unread')) {
            await markNotificationRead(maThongBao);
            item.classList.remove('unread');
            loadUnreadCount(); // Refresh count
        }
    } else {
        content.classList.add('collapsed');
    }
}

async function markNotificationRead(maThongBao) {
    try {
        await fetch(`${API_BASE_PATIENT}/mark-notification-read.php`, {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ maThongBao })
        });
    } catch (error) {
        console.error('Error marking notification read:', error);
    }
}

async function markAllNotificationsRead() {
    try {
        const response = await fetch(`${API_BASE_PATIENT}/mark-all-notifications-read.php`, {
            method: 'POST',
            credentials: 'include'
        });
        const data = await response.json();

        if (data.success) {
            // Update UI
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
            });
            loadUnreadCount();
        }
    } catch (error) {
        console.error('Error marking all read:', error);
    }
}

async function loadUnreadCount() {
    try {
        const response = await fetch(`${API_BASE_PATIENT}/get-unread-notifications-count.php`, {
            credentials: 'include'
        });
        const data = await response.json();

        const badge = document.getElementById('notificationBadge');
        if (badge) {
            if (data.success && data.count > 0) {
                badge.textContent = data.count;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        }
    } catch (error) {
        console.error('Error loading unread count:', error);
    }
}

function getTimeAgo(dateString) {
    const now = new Date();
    const past = new Date(dateString);
    const diffMs = now - past;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Vừa xong';
    if (diffMins < 60) return `${diffMins} phút trước`;
    if (diffHours < 24) return `${diffHours} giờ trước`;
    if (diffDays === 1) return 'Hôm qua';
    if (diffDays < 7) return `${diffDays} ngày trước`;
    
    return past.toLocaleDateString('vi-VN');
}

function setupMobileMenu() {
    const menuToggle = document.getElementById('menuToggle');
    const mobileSidebar = document.getElementById('mobileSidebar');
    const mobileOverlay = document.getElementById('mobileOverlay');
    const closeSidebar = document.getElementById('closeSidebar');

    if (!menuToggle || !mobileSidebar || !mobileOverlay) return;

    menuToggle.addEventListener('click', () => {
        mobileSidebar.classList.add('active');
        mobileOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    });

    function closeMobileSidebar() {
        mobileSidebar.classList.remove('active');
        mobileOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    closeSidebar?.addEventListener('click', closeMobileSidebar);
    mobileOverlay?.addEventListener('click', closeMobileSidebar);

    document.querySelectorAll('.mobile-menu a').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 992) closeMobileSidebar();
        });
    });
}

function setupScrollEffects() {
    const scrollBtn = document.getElementById('scrollTopBtn');
    
    if (!scrollBtn) return;

    window.addEventListener('scroll', () => {
        scrollBtn.style.display = window.scrollY > 300 ? 'flex' : 'none';
    });
}

function setActiveNavLink() {
    const currentFile = window.location.pathname.split('/').pop() || 'index.html';
    const currentPage = currentFile.replace('.html', '').toLowerCase();
    const pageMap = {
        index: 'index',
        'danh-sach-chuyen-khoa': 'danhsachchuyenkhoa',
        'chi-tiet-chuyen-khoa': 'danhsachchuyenkhoa',
        'danh-sach-bac-si': 'danhsachbacsi',
        'chi-tiet-bac-si': 'danhsachbacsi',
        'dashboard-doctor': 'dashboard',
        'lich-kham-ca-nhan': 'lichkham',
        'dat-lich': 'lichkham',
        'ho-so-benh-an-ca-nhan': 'hosobenhan',
        'tai-khoan-ca-nhan': 'hosobenhan',
        'lien-he': 'lienhe'
    };
    const activePage = pageMap[currentPage];

    document.querySelectorAll('.nav-link-header, .mobile-menu a').forEach(link => {
        link.classList.remove('active');

        const linkPage = link.getAttribute('data-page');
        if (activePage && linkPage === activePage) {
            link.classList.add('active');
        }
    });
}

document.addEventListener('DOMContentLoaded', loadComponents);
