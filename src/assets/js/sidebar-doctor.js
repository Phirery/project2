(function () {
    'use strict';

    const DEFAULT_DB_AVATAR_KEY = 'samples/paper.png';
    const doctorHelper = window.DoctorPageHelper || {};
    const COMPONENT_PATH = doctorHelper.componentPath || window.COMPONENT_PATH || 'components/';
    const API_BASE_DOCTOR = doctorHelper.doctorApiBase || window.API_BASE_DOCTOR || '';
    const API_BASE_AUTH = doctorHelper.authApiBase || window.API_BASE_AUTH || '';

    window.DOCTOR_INFO = window.DOCTOR_INFO || {};

    async function loadSidebar() {
        try {
            const response = await fetch(COMPONENT_PATH + 'sidebar-doctor.html');
            const html = await response.text();

            // Insert sidebar into placeholder
            const placeholder = document.getElementById('sidebar-placeholder');
            if (placeholder) {
                placeholder.innerHTML = html;
            }

            // Move page content into doctor content area
            movePageContent();

            // Load CSS
            loadSidebarCSS();

            // Initialize after loading
            initSidebar();
        } catch (error) {
            console.error('Error loading sidebar:', error);
        }
    }

    function movePageContent() {
        const pageContent = document.getElementById('page-content');
        const contentArea = document.getElementById('doctorContentArea');
        if (pageContent && contentArea) {
            while (pageContent.firstChild) {
                contentArea.appendChild(pageContent.firstChild);
            }
            pageContent.remove();
        }
    }

    function loadSidebarCSS() {
        // Check if CSS already loaded
        if (document.getElementById('sidebar-doctor-css')) return;

        const link = document.createElement('link');
        link.id = 'sidebar-doctor-css';
        link.rel = 'stylesheet';
        link.href = 'assets/css/sidebar-doctor.css';
        document.head.appendChild(link);
    }

    function initSidebar() {
        loadDoctorInfo();
        loadUnreadNotificationsCount();
        setupMobileMenu();
        setupScrollEffects();
        setActiveNavLink();
        updatePageTitle();

        // Auto refresh notification count every 30 seconds
        setInterval(loadUnreadNotificationsCount, 30000);
    }

    function hasCustomAvatar(avatarUrl) {
        return typeof avatarUrl === 'string'
            && avatarUrl.trim() !== ''
            && !avatarUrl.includes(DEFAULT_DB_AVATAR_KEY);
    }

    function renderDoctorHeaderAvatars(avatarUrl, fallbackText) {
        document.querySelectorAll('#sidebarUserAvatar, #topUserAvatar').forEach(el => {
            el.innerHTML = '';
            el.style.overflow = 'hidden';

            if (hasCustomAvatar(avatarUrl)) {
                const img = document.createElement('img');
                img.src = avatarUrl;
                img.alt = 'Avatar bác sĩ';
                img.style.width = '100%';
                img.style.height = '100%';
                img.style.objectFit = 'cover';
                img.style.borderRadius = '50%';
                img.onerror = () => {
                    el.innerHTML = '';
                    el.textContent = fallbackText;
                };
                el.appendChild(img);
            } else {
                el.textContent = fallbackText;
            }
        });
    }

    async function loadDoctorInfo() {
        try {
            const response = await fetch(`${API_BASE_DOCTOR}/get-doctor-info.php`, {
                credentials: 'include'
            });

            const data = await response.json();

            if (data.success) {
                const doctorName = data.data.tenBacSi || 'Bác sĩ';
                const firstLetters = doctorName.split(' ').slice(-2).map(w => w[0]).join('').toUpperCase() || 'BS';

                // Store in global variable
                window.DOCTOR_INFO = {
                    id: data.data.maBacSi,
                    tenBacSi: doctorName,
                    vaiTro: 'bacsi'
                };

                // Update all username displays
                const userNameElements = [
                    'doctorName',
                    'sidebarDoctorName'
                ];
                userNameElements.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.textContent = doctorName;
                });

                // Update avatar
                renderDoctorHeaderAvatars(data.data.avatar, firstLetters);
            } else {
                handleSessionExpired(data.message || null);
            }
        } catch (error) {
            console.error('Error loading doctor info:', error);
            handleSessionExpired('fetch_failed');
        }
    }

    async function loadUnreadNotificationsCount() {
        try {
            const response = await fetch(`${API_BASE_DOCTOR}/get-unread-notifications-count.php`, {
                credentials: 'include'
            });
            const data = await response.json();

            const badges = [
                'topNotifBadge',
                'sidebarNotifBadge'
            ];

            badges.forEach(id => {
                const badge = document.getElementById(id);
                if (badge) {
                    if (data.success && data.count > 0) {
                        badge.textContent = data.count;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            });
        } catch (error) {
            console.error('Error loading notification count:', error);
        }
    }

    function setupMobileMenu() {
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const mobileOverlay = document.getElementById('mobileOverlay');

        if (!menuToggle || !sidebar || !mobileOverlay) return;

        // Toggle sidebar
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            mobileOverlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
        });

        // Close sidebar on overlay click
        mobileOverlay.addEventListener('click', () => {
            closeMobileSidebar();
        });

        // Close sidebar on menu item click (mobile only)
        const menuLinks = sidebar.querySelectorAll('.sidebar-menu a');
        menuLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    closeMobileSidebar();
                }
            });
        });
    }

    function closeMobileSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mobileOverlay = document.getElementById('mobileOverlay');

        if (sidebar) sidebar.classList.remove('active');
        if (mobileOverlay) mobileOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    function setupScrollEffects() {
        const scrollBtn = document.getElementById('scrollTopBtn');

        if (!scrollBtn) return;

        window.addEventListener('scroll', () => {
            scrollBtn.style.display = window.scrollY > 300 ? 'flex' : 'none';
        });
    }

    function setActiveNavLink() {
        const currentPage = window.location.pathname.split('/').pop();
        const menuLinks = document.querySelectorAll('.sidebar-menu a[data-page]');

        menuLinks.forEach(link => {
            const linkPage = link.getAttribute('data-page');
            if (currentPage.includes(linkPage)) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });
    }

    function updatePageTitle() {
        const currentPage = window.location.pathname.split('/').pop();
        const pageTitle = document.getElementById('pageTitle');

        if (!pageTitle) return;

        const pageTitles = {
            'dashboard-doctor.html': '<i class="fas fa-chart-line me-2"></i>Dashboard',
            'lich-kham.html': '<i class="fas fa-calendar-check me-2"></i>Lịch Khám',
            'benh-nhan.html': '<i class="fas fa-user-injured me-2"></i>Bệnh Nhân',
            'ho-so-benh-an.html': '<i class="fas fa-file-medical me-2"></i>Hồ Sơ Bệnh Án',
            'thong-bao-bac-si.html': '<i class="fas fa-bell me-2"></i>Thông Báo'
        };

        pageTitle.innerHTML = pageTitles[currentPage] || '<i class="fas fa-chart-line me-2"></i>Dashboard';
    }

    // === Global functions ===

    window.handleLogout = async function () {
        try {
            const response = await fetch(`${API_BASE_AUTH}/logout.php`, {
                method: 'POST',
                credentials: 'include'
            });

            const data = await response.json();

            if (data.success) {
                showAlert('success', 'Đăng xuất thành công!');
                setTimeout(() => {
                    window.location.href = 'login.html';
                }, 1000);
            } else {
                showAlert('error', 'Không thể đăng xuất. Vui lòng thử lại.');
            }
        } catch (error) {
            console.error('Logout error:', error);
            showAlert('error', 'Lỗi khi đăng xuất');
        }
    };

    window.handleSessionExpired = function (msg) {
        if (
            !msg ||
            msg === "fetch_failed" ||
            msg.includes('Phiên đăng nhập') ||
            msg.includes('Không có quyền') ||
            msg.includes('Chưa đăng nhập')
        ) {
            showAlert('error', 'Phiên đăng nhập hết hạn. Vui lòng đăng nhập lại.');
            setTimeout(() => window.location.href = 'login.html', 500);
        }
    };

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

    window.showAlert = function (type, message) {
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
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadSidebar);
    } else {
        loadSidebar();
    }

})();

console.log('%c✅ Sidebar Doctor loaded', 'color: #4A90E2; font-size: 12px;');
