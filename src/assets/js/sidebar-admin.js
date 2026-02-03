(function() {
    'use strict';

    async function loadSidebar() {
        try {
            const response = await fetch(COMPONENT_PATH + 'sidebar-admin.html');
            const html = await response.text();
            
            // Insert sidebar into placeholder
            const placeholder = document.getElementById('sidebar-placeholder');
            if (placeholder) {
                placeholder.innerHTML = html;
            }

            // Load CSS
            loadSidebarCSS();

            // Initialize after loading
            initSidebar();
        } catch (error) {
            console.error('Error loading sidebar:', error);
        }
    }

    function loadSidebarCSS() {
        // Check if CSS already loaded
        if (document.getElementById('sidebar-admin-css')) return;

        const link = document.createElement('link');
        link.id = 'sidebar-admin-css';
        link.rel = 'stylesheet';
        link.href = COMPONENT_PATH + 'sidebar-admin.css';
        document.head.appendChild(link);
    }

    function initSidebar() {
        loadAdminInfo();
        loadUnreadNotificationsCount();
        setupMobileMenu();
        setupScrollEffects();
        setActiveNavLink();
        updatePageTitle();

        // Auto refresh notification count every 30 seconds
        setInterval(loadUnreadNotificationsCount, 30000);
    }

    async function loadAdminInfo() {
        try {
            const response = await fetch(`${API_BASE_ADMIN}/get-admin-info.php`, {
                credentials: 'include'
            });

            const data = await response.json();

            if (data.success) {
                const userName = data.data.tenDangNhap || 'Admin';
                const firstLetter = userName.charAt(0).toUpperCase();

                // Store in global variable
                ADMIN_INFO = {
                    id: data.data.id,
                    tenDangNhap: userName,
                    vaiTro: 'quantri'
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

                // Update all avatar displays
                const avatarElements = [
                    'topUserAvatar',
                    'sidebarUserAvatar'
                ];
                avatarElements.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.textContent = firstLetter;
                });
            } else {
                handleSessionExpired(data.message || null);
            }
        } catch (error) {
            console.error('Error loading admin info:', error);
            handleSessionExpired('fetch_failed');
        }
    }

    async function loadUnreadNotificationsCount() {
        try {
            const response = await fetch(`${API_BASE_ADMIN}/get-unread-notifications-count.php`, {
                credentials: 'include'
            });
            const data = await response.json();
            
            UNREAD_NOTIFICATION_COUNT = data.count || 0;

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
            'dashboard-admin.html': '<i class="fas fa-chart-line me-2"></i>Dashboard',
            'quan-ly-lich-kham.html': '<i class="fas fa-calendar-check me-2"></i>Quản Lý Lịch Khám',
            'quan-ly-khoa.html': '<i class="fas fa-hospital me-2"></i>Quản Lý Khoa',
            'quan-ly-benh-nhan.html': '<i class="fas fa-user-injured me-2"></i>Quản Lý Bệnh Nhân',
            'quan-ly-bac-si.html': '<i class="fas fa-user-md me-2"></i>Quản Lý Bác Sĩ',
            'quan-ly-tai-khoan.html': '<i class="fas fa-users-cog me-2"></i>Quản Lý Tài Khoản',
            'quan-ly-lien-he.html': '<i class="fas fa-envelope me-2"></i>Quản Lý Liên Hệ',
            'thong-ke.html': '<i class="fas fa-chart-bar me-2"></i>Thống Kê',
            'thong-bao-admin.html': '<i class="fas fa-bell me-2"></i>Thông Báo',
            'tai-khoan-ca-nhan.html': '<i class="fas fa-user-circle me-2"></i>Tài Khoản'
        };

        pageTitle.innerHTML = pageTitles[currentPage] || '<i class="fas fa-chart-line me-2"></i>Dashboard';
    }

    // ============================================
    // LOGOUT HANDLER
    // ============================================
    window.handleLogout = async function() {
        if (!confirm('Bạn có chắc chắn muốn đăng xuất?')) return;

        try {
            const response = await fetch(`${API_BASE_AUTH}/logout.php`, {
                method: 'POST',
                credentials: 'include'
            });

            const data = await response.json();

            if (data.success) {
                showAlert('success', 'Đăng xuất thành công!');
                setTimeout(() => {
                    window.location.href = '../login.html';
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

console.log('%c✅ Sidebar Admin loaded', 'color: #4CAF50; font-size: 12px;');