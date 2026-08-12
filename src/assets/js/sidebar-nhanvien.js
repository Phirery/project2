(function () {
    'use strict';

    const DEFAULT_DB_AVATAR_KEY = 'samples/paper.png';

    async function loadSidebar() {
        try {
            const response = await fetch(COMPONENT_PATH + 'sidebar-nhanvien.html');
            const html = await response.text();

            // Insert sidebar into placeholder
            const placeholder = document.getElementById('sidebar-placeholder');
            if (placeholder) {
                placeholder.innerHTML = html;
            }

            // Move page content into staff content area
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
        const contentArea = document.getElementById('staffContentArea');
        if (pageContent && contentArea) {
            while (pageContent.firstChild) {
                contentArea.appendChild(pageContent.firstChild);
            }
            pageContent.remove();
        }
    }

    function loadSidebarCSS() {
        // Check if CSS already loaded
        if (document.getElementById('sidebar-nhanvien-css')) return;

        const link = document.createElement('link');
        link.id = 'sidebar-nhanvien-css';
        link.rel = 'stylesheet';
        link.href = COMPONENT_PATH + 'sidebar-nhanvien.css';
        document.head.appendChild(link);
    }

    function initSidebar() {
        loadStaffInfo();
        setupMobileMenu();
        setupScrollEffects();
        setActiveNavLink();
        updatePageTitle();
    }

    function hasCustomAvatar(avatarUrl) {
        return typeof avatarUrl === 'string'
            && avatarUrl.trim() !== ''
            && !avatarUrl.includes(DEFAULT_DB_AVATAR_KEY);
    }

    function renderStaffHeaderAvatars(avatarUrl, fallbackText) {
        document.querySelectorAll('#sidebarUserAvatar, #topUserAvatar').forEach(el => {
            if (!el) return;
            el.innerHTML = '';
            el.style.overflow = 'hidden';

            if (hasCustomAvatar(avatarUrl)) {
                const img = document.createElement('img');
                img.src = avatarUrl;
                img.alt = 'Avatar nhân viên';
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

    async function loadStaffInfo() {
        try {
            const response = await fetch(`${API_BASE_STAFF}/get-staff-info.php`, {
                credentials: 'include'
            });

            const data = await response.json();

            if (data.success) {
                const userName = data.data.tenNhanVien || data.data.tenDangNhap || 'Nhân viên';
                const avatarFallback = userName.split(' ').slice(-2).map(w => w[0]).join('').toUpperCase() || 'NV';

                // Store in global variable
                STAFF_INFO = {
                    id: data.data.id,
                    tenDangNhap: data.data.tenDangNhap,
                    tenNhanVien: userName,
                    loaiNhanVien: data.data.loaiNhanVien || null,
                    avatar: data.data.avatar || '',
                    vaiTro: 'nhanvien'
                };

                // Update all username displays
                const userNameElements = [
                    'topUserName',
                    'sidebarUserName'
                ];
                userNameElements.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.textContent = userName;
                });

                // Update avatars
                renderStaffHeaderAvatars(data.data.avatar, avatarFallback);
            } else {
                handleSessionExpired(data.message || null);
            }
        } catch (error) {
            console.error('Error loading staff info:', error);
            handleSessionExpired('fetch_failed');
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
            'dashboard-nhanvien.html': '<i class="fas fa-chart-line me-2"></i>Dashboard',
            'check-in.html': '<i class="fas fa-door-open me-2"></i>Check-in bệnh nhân',
            'tai-khoan-ca-nhan.html': '<i class="fas fa-user-circle me-2"></i>Tài khoản'
        };

        pageTitle.innerHTML = pageTitles[currentPage] || '<i class="fas fa-chart-line me-2"></i>Dashboard';
    }

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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadSidebar);
    } else {
        loadSidebar();
    }

})();

console.log('%c✅ Sidebar Staff loaded', 'color: #4CAF50; font-size: 12px;');