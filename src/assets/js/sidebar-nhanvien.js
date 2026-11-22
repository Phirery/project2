(function () {
    'use strict';

    const DEFAULT_DB_AVATAR_KEY = 'samples/paper.png';

    const PAGE_META_MAP = {
        'dashboard-nhanvien.html': {
            title: 'Dashboard',
            icon: 'fas fa-chart-line',
            requiredPermission: 'staff.dashboard.view',
        },
        'check-in.html': {
            title: 'Tiếp nhận bệnh nhân',
            icon: 'fas fa-door-open',
            requiredPermission: 'reception.checkin',
        },
        'dieu-duong.html': {
            title: 'Điều dưỡng sàng lọc',
            icon: 'fas fa-user-nurse',
            requiredPermission: 'nursing.screen',
        },
        'can-lam-sang.html?nhom=xet-nghiem': {
            title: 'Xét nghiệm',
            icon: 'fas fa-vials',
            requiredPermission: 'cls.xet_nghiem.perform',
        },
        'can-lam-sang.html?nhom=chan-doan-hinh-anh': {
            title: 'Chẩn đoán hình ảnh',
            icon: 'fas fa-x-ray',
            requiredPermission: 'cls.cdha.perform',
        },
        'can-lam-sang.html?nhom=noi-soi': {
            title: 'Nội soi',
            icon: 'fas fa-microscope',
            requiredPermission: 'cls.noi_soi.perform',
        },
        'can-lam-sang.html': {
            title: 'Cận lâm sàng',
            icon: 'fas fa-microscope',
            requiredPermission: ['cls.xet_nghiem.perform', 'cls.cdha.perform', 'cls.noi_soi.perform'],
        },
        'phat-thuoc.html': {
            title: 'Phát thuốc',
            icon: 'fas fa-pills',
            requiredPermission: 'pharmacy.dispense',
        },
        'thu-ngan.html': {
            title: 'Thu ngân',
            icon: 'fas fa-file-invoice-dollar',
            requiredPermission: 'billing.collect',
        },
        'tai-khoan-ca-nhan.html': {
            title: 'Tài khoản cá nhân',
            icon: 'fas fa-user-circle',
            requiredPermission: null,
        },
    };

    function getCurrentRouteInfo() {
        const file = window.location.pathname.split('/').pop() || 'dashboard-nhanvien.html';
        const params = new URLSearchParams(window.location.search);
        const nhom = params.get('nhom');
        const fullKey = (file === 'can-lam-sang.html' && nhom) ? `${file}?nhom=${nhom}` : file;
        const meta = PAGE_META_MAP[fullKey] || PAGE_META_MAP[file] || {
            title: 'Cổng nhân viên',
            icon: 'fas fa-hospital-user',
            requiredPermission: null,
        };
        return { file, nhom, fullKey, meta };
    }

    async function loadSidebar() {
        try {
            const response = await fetch(COMPONENT_PATH + 'sidebar-nhanvien.html');
            const html = await response.text();

            const placeholder = document.getElementById('sidebar-placeholder');
            if (placeholder) {
                placeholder.innerHTML = html;
            }

            movePageContent();
            loadSidebarCSS();
            await initSidebar();
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
        if (document.getElementById('sidebar-nhanvien-css')) return;

        const link = document.createElement('link');
        link.id = 'sidebar-nhanvien-css';
        link.rel = 'stylesheet';
        link.href = 'assets/css/sidebar-nhanvien.css';
        document.head.appendChild(link);
    }

    async function initSidebar() {
        setupMobileMenu();
        setupScrollEffects();
        updatePageTitle();
        await loadStaffRbacContext();
    }

    function hasCustomAvatar(avatarUrl) {
        return typeof avatarUrl === 'string'
            && avatarUrl.trim() !== ''
            && !avatarUrl.includes(DEFAULT_DB_AVATAR_KEY);
    }

    function renderStaffHeaderAvatars(avatarUrl, fallbackText) {
        document.querySelectorAll('#sidebarUserAvatar, #topUserAvatar').forEach((el) => {
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

    function formatRoleDisplay(roles, isDemo) {
        if (isDemo) {
            return 'Nhân viên tổng hợp (Demo)';
        }
        if (Array.isArray(roles) && roles.length > 0) {
            return roles.map((r) => r.tenNhomQuyen).join(', ');
        }
        return 'Nhân viên (Chưa gán quyền)';
    }

    function renderSidebarModules(modules) {
        const menu = document.getElementById('staffSidebarMenu');
        const loadingEl = document.getElementById('sidebarMenuLoading');
        const footerEl = document.getElementById('sidebarUserFooter');
        if (!menu) return;

        if (loadingEl) {
            loadingEl.remove();
        }
        menu.querySelectorAll('.sidebar-dynamic-item').forEach((el) => el.remove());

        if (!Array.isArray(modules) || modules.length === 0) {
            const emptyLi = document.createElement('li');
            emptyLi.className = 'sidebar-menu-empty sidebar-dynamic-item';
            emptyLi.textContent = 'Chưa được cấp quyền module';
            menu.insertBefore(emptyLi, footerEl || null);
            return;
        }

        let lastGroup = null;
        modules.forEach((mod) => {
            if (mod.group && mod.group !== lastGroup) {
                const groupLi = document.createElement('li');
                groupLi.className = 'sidebar-group-label sidebar-dynamic-item';
                groupLi.textContent = mod.group;
                menu.insertBefore(groupLi, footerEl || null);
                lastGroup = mod.group;
            }

            const li = document.createElement('li');
            li.className = 'sidebar-dynamic-item';

            const link = document.createElement('a');
            link.href = mod.href;
            link.setAttribute('data-page', mod.pageKey || mod.href);
            link.innerHTML = `<i class="${escapeHtml(mod.icon)}"></i><span>${escapeHtml(mod.label)}</span>`;

            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    closeMobileSidebar();
                }
            });

            li.appendChild(link);
            menu.insertBefore(li, footerEl || null);
        });

        setActiveNavLink();
    }

    function renderDemoBanner(isDemo) {
        const contentArea = document.getElementById('staffContentArea');
        if (!contentArea) return;

        const existing = document.getElementById('staffDemoBanner');
        if (existing) existing.remove();
        if (!isDemo) return;

        const banner = document.createElement('div');
        banner.id = 'staffDemoBanner';
        banner.className = 'staff-demo-banner';
        banner.setAttribute('role', 'status');
        banner.innerHTML = `
            <div>
                <i class="fas fa-flask me-2" aria-hidden="true"></i>
                <strong>Chế độ demo:</strong> Tài khoản tổng hợp có quyền tác nghiệp toàn bộ quy trình nhân viên (không bao gồm quyền quản trị hệ thống).
            </div>
            <span class="badge bg-warning text-dark">RBAC Demo</span>
        `;
        contentArea.prepend(banner);
    }

    function enforceCurrentPageAccess(context) {
        const { meta } = getCurrentRouteInfo();
        const required = meta.requiredPermission;
        if (!required) {
            return true;
        }

        const perms = Array.isArray(context.permissions) ? context.permissions : [];
        const allowed = Array.isArray(required)
            ? required.some((p) => perms.includes(p))
            : perms.includes(required);

        if (allowed) {
            return true;
        }

        const contentArea = document.getElementById('staffContentArea');
        if (contentArea) {
            const fallbackHref = context.defaultRoute || 'dashboard-nhanvien.html';
            const fallbackLabel = fallbackHref === 'dashboard-nhanvien.html' ? 'Quay về Dashboard' : 'Đi tới màn hình được cấp quyền';
            contentArea.innerHTML = `
                <div class="staff-access-denied" role="alert">
                    <div class="staff-access-denied-icon">
                        <i class="fas fa-shield-alt" aria-hidden="true"></i>
                    </div>
                    <h4>403 — Không có quyền truy cập</h4>
                    <p>Tài khoản của bạn chưa được cấp quyền sử dụng chức năng <strong>${escapeHtml(meta.title)}</strong>. Mọi thao tác API tương ứng cũng sẽ bị từ chối ở máy chủ.</p>
                    <a href="${escapeHtml(fallbackHref)}" class="btn btn-primary">
                        <i class="fas fa-arrow-left me-2"></i>${escapeHtml(fallbackLabel)}
                    </a>
                </div>
            `;
        }
        return false;
    }

    async function loadStaffRbacContext() {
        try {
            const response = await fetch(`${API_BASE_STAFF}/get-my-permissions.php`, {
                credentials: 'include'
            });

            if (response.status === 401) {
                handleSessionExpired('Phiên đăng nhập hết hạn');
                renderSidebarModules([]);
                return;
            }

            const payload = await response.json();
            if (!payload.success || !payload.data) {
                handleSessionExpired(payload.message || null);
                renderSidebarModules([]);
                return;
            }

            const ctx = payload.data;
            const staff = ctx.staff || {};
            const userName = staff.tenNhanVien || staff.tenDangNhap || 'Nhân viên';
            const avatarFallback = userName.split(' ').slice(-2).map((w) => w[0]).join('').toUpperCase() || 'NV';
            const roleLabel = formatRoleDisplay(ctx.roles, ctx.isDemo);

            STAFF_INFO = {
                id: staff.id || null,
                maNhanVien: staff.maNhanVien || null,
                tenDangNhap: staff.tenDangNhap || '',
                tenNhanVien: userName,
                loaiNhanVien: staff.loaiNhanVien || null,
                avatar: staff.avatar || '',
                vaiTro: 'nhanvien',
                isDemo: Boolean(ctx.isDemo),
                roles: Array.isArray(ctx.roles) ? ctx.roles : [],
                permissions: Array.isArray(ctx.permissions) ? ctx.permissions : [],
                modules: Array.isArray(ctx.modules) ? ctx.modules : [],
            };

            ['topUserName', 'sidebarUserName'].forEach((id) => {
                const el = document.getElementById(id);
                if (el) el.textContent = userName;
            });

            ['topUserRole', 'sidebarUserRole'].forEach((id) => {
                const el = document.getElementById(id);
                if (el) el.textContent = roleLabel;
            });

            renderStaffHeaderAvatars(staff.avatar, avatarFallback);
            renderSidebarModules(STAFF_INFO.modules);
            const hasAccess = enforceCurrentPageAccess(ctx);
            if (hasAccess) {
                renderDemoBanner(STAFF_INFO.isDemo);
            }

            window.dispatchEvent(new CustomEvent('staff:rbac-ready', {
                detail: { ...STAFF_INFO, hasPageAccess: hasAccess }
            }));
        } catch (error) {
            console.error('Error loading staff RBAC context:', error);
            renderSidebarModules([]);
            handleSessionExpired('fetch_failed');
        }
    }

    function setupMobileMenu() {
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const mobileOverlay = document.getElementById('mobileOverlay');

        if (!menuToggle || !sidebar || !mobileOverlay) return;

        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            mobileOverlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
        });

        mobileOverlay.addEventListener('click', () => {
            closeMobileSidebar();
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
        const { file, nhom, fullKey } = getCurrentRouteInfo();
        const menuLinks = document.querySelectorAll('.sidebar-menu a[data-page]');

        menuLinks.forEach((link) => {
            const linkPage = link.getAttribute('data-page') || '';
            const isExactMatch = linkPage === fullKey || `${linkPage}.html` === fullKey;
            const isNonQueryMatch = !nhom && (linkPage === file || `${linkPage}.html` === file);

            if (isExactMatch || isNonQueryMatch) {
                link.classList.add('active');
                link.setAttribute('aria-current', 'page');
            } else {
                link.classList.remove('active');
                link.removeAttribute('aria-current');
            }
        });
    }

    function updatePageTitle() {
        const pageTitle = document.getElementById('pageTitle');
        if (!pageTitle) return;

        const { meta } = getCurrentRouteInfo();
        pageTitle.innerHTML = `<i class="${escapeHtml(meta.icon)} me-2"></i>${escapeHtml(meta.title)}`;
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
                }, 800);
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
