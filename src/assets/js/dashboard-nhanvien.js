(function () {
    'use strict';

    let lastRbacDetail = null;

    function setDashboardViewState(stateName, errorMsg = '') {
        const loading = document.getElementById('dashboardLoadingState');
        const error = document.getElementById('dashboardErrorState');
        const empty = document.getElementById('dashboardEmptyState');
        const data = document.getElementById('dashboardDataState');
        if (!loading || !error || !empty || !data) return;

        loading.classList.toggle('d-none', stateName !== 'loading');
        error.classList.toggle('d-none', stateName !== 'error');
        empty.classList.toggle('d-none', stateName !== 'empty');
        data.classList.toggle('d-none', stateName !== 'data');

        if (stateName === 'error' && errorMsg) {
            const msgEl = document.getElementById('dashboardErrorMessage');
            if (msgEl) msgEl.textContent = errorMsg;
        }
    }

    function buildSummaryChips(metrics) {
        const chips = [];
        if (metrics.reception) {
            chips.push({
                icon: 'fas fa-calendar-check text-primary',
                label: 'Chờ check-in hôm nay',
                value: metrics.reception.pendingCheckInToday,
            });
            chips.push({
                icon: 'fas fa-users text-success',
                label: 'Đang chờ khám bác sĩ',
                value: metrics.reception.waitingQueueToday,
            });
        }
        if (metrics.nursing) {
            chips.push({
                icon: 'fas fa-user-nurse text-primary',
                label: 'Chờ điều dưỡng sàng lọc',
                value: metrics.nursing.pendingScreeningToday,
            });
            chips.push({
                icon: 'fas fa-notes-medical text-success',
                label: 'Đã sàng lọc hôm nay',
                value: metrics.nursing.screenedToday,
            });
        }
        if (metrics.cls) {
            const labels = {
                'xet-nghiem': 'Xét nghiệm chờ xử lý',
                'chan-doan-hinh-anh': 'CĐHA chờ xử lý',
                'noi-soi': 'Nội soi chờ xử lý',
            };
            Object.entries(metrics.cls).forEach(([groupKey, stat]) => {
                chips.push({
                    icon: 'fas fa-vial text-primary',
                    label: labels[groupKey] || groupKey,
                    value: (stat.waiting || 0) + (stat.inProgress || 0),
                });
            });
        }
        if (metrics.pharmacy) {
            chips.push({
                icon: 'fas fa-pills text-warning',
                label: 'Đơn chờ phát thuốc',
                value: metrics.pharmacy.waitingDispense,
            });
            chips.push({
                icon: 'fas fa-check-circle text-success',
                label: 'Đã phát hôm nay',
                value: metrics.pharmacy.dispensedToday,
            });
        }
        if (metrics.billing) {
            chips.push({
                icon: 'fas fa-file-invoice-dollar text-danger',
                label: 'Hóa đơn chưa thanh toán',
                value: metrics.billing.unpaidInvoices,
            });
            chips.push({
                icon: 'fas fa-receipt text-success',
                label: 'Đã thu hôm nay',
                value: metrics.billing.paidToday,
            });
        }
        return chips;
    }

    function getModuleMetricText(modKey, metrics) {
        if (!metrics) return '';
        if (modKey === 'reception' && metrics.reception) {
            return `Chờ check-in: <strong>${metrics.reception.pendingCheckInToday}</strong> · Đang chờ BS: <strong>${metrics.reception.waitingQueueToday}</strong>`;
        }
        if (modKey === 'nursing' && metrics.nursing) {
            return `Chờ sàng lọc: <strong>${metrics.nursing.pendingScreeningToday}</strong> · Đã sàng lọc: <strong>${metrics.nursing.screenedToday}</strong>`;
        }
        if (modKey === 'cls-lab' && metrics.cls?.['xet-nghiem']) {
            const s = metrics.cls['xet-nghiem'];
            return `Đang chờ: <strong>${s.waiting}</strong> · Đang thực hiện: <strong>${s.inProgress}</strong>`;
        }
        if (modKey === 'cls-imaging' && metrics.cls?.['chan-doan-hinh-anh']) {
            const s = metrics.cls['chan-doan-hinh-anh'];
            return `Đang chờ: <strong>${s.waiting}</strong> · Đang thực hiện: <strong>${s.inProgress}</strong>`;
        }
        if (modKey === 'cls-endoscopy' && metrics.cls?.['noi-soi']) {
            const s = metrics.cls['noi-soi'];
            return `Đang chờ: <strong>${s.waiting}</strong> · Đang thực hiện: <strong>${s.inProgress}</strong>`;
        }
        if (modKey === 'pharmacy' && metrics.pharmacy) {
            return `Đơn chờ phát: <strong>${metrics.pharmacy.waitingDispense}</strong> · Đã phát hôm nay: <strong>${metrics.pharmacy.dispensedToday}</strong>`;
        }
        if (modKey === 'billing' && metrics.billing) {
            return `Chưa thanh toán: <strong>${metrics.billing.unpaidInvoices}</strong> · Đã thu hôm nay: <strong>${metrics.billing.paidToday}</strong>`;
        }
        return '';
    }

    function renderDashboardData(rbac, summaryData) {
        const nameEl = document.getElementById('dashStaffName');
        const codeEl = document.getElementById('dashStaffCode');
        const dateEl = document.getElementById('dashTodayLabel');
        const roleBadgesEl = document.getElementById('dashRoleBadges');
        const permBadgesEl = document.getElementById('dashPermissionBadges');
        const summaryBarEl = document.getElementById('dashSummaryBar');
        const moduleGridEl = document.getElementById('dashModuleGrid');
        const countLabelEl = document.getElementById('dashModuleCountLabel');

        if (nameEl) nameEl.textContent = rbac.tenNhanVien || 'Nhân viên';
        if (codeEl) codeEl.textContent = rbac.maNhanVien || '—';
        if (dateEl) dateEl.textContent = formatDate(summaryData.date || new Date().toISOString());

        if (roleBadgesEl) {
            roleBadgesEl.innerHTML = (rbac.roles || []).map((r) => `
                <span class="role-pill ${r.isDemo ? 'is-demo' : ''}">
                    <i class="fas ${r.isDemo ? 'fa-flask' : 'fa-id-badge'}" aria-hidden="true"></i>
                    ${escapeHtml(r.tenNhomQuyen)}
                </span>
            `).join('');
        }

        if (permBadgesEl) {
            permBadgesEl.innerHTML = (rbac.permissions || []).map((p) => `
                <span class="perm-pill"><i class="fas fa-key text-primary" aria-hidden="true"></i>${escapeHtml(p)}</span>
            `).join('');
        }

        const chips = buildSummaryChips(summaryData.metrics || {});
        if (summaryBarEl) {
            if (chips.length > 0) {
                summaryBarEl.classList.remove('d-none');
                summaryBarEl.innerHTML = chips.map((c) => `
                    <div class="summary-chip">
                        <i class="${escapeHtml(c.icon)}" aria-hidden="true"></i>
                        <span class="summary-label">${escapeHtml(c.label)}:</span>
                        <span class="summary-value tabular-nums">${Number(c.value || 0).toLocaleString('vi-VN')}</span>
                    </div>
                `).join('');
            } else {
                summaryBarEl.classList.add('d-none');
                summaryBarEl.innerHTML = '';
            }
        }

        const workspaceModules = (summaryData.modules || []).filter((m) => m.key !== 'dashboard');
        if (countLabelEl) {
            countLabelEl.textContent = `${workspaceModules.length} khu vực được cấp quyền`;
        }

        if (moduleGridEl) {
            if (workspaceModules.length === 0) {
                moduleGridEl.innerHTML = `
                    <div class="state-box">
                        <div class="state-box-icon"><i class="fas fa-folder-open" aria-hidden="true"></i></div>
                        <h6 class="fw-bold mb-1">Chưa có module nghiệp vụ bổ sung</h6>
                        <p class="text-secondary small mb-0">Tài khoản hiện chỉ có quyền xem tổng quan Dashboard.</p>
                    </div>
                `;
            } else {
                moduleGridEl.innerHTML = workspaceModules.map((mod) => {
                    const metricHtml = getModuleMetricText(mod.key, summaryData.metrics);
                    return `
                        <article class="module-card">
                            <div>
                                <div class="module-card-head">
                                    <div class="module-icon-box">
                                        <i class="${escapeHtml(mod.icon)}" aria-hidden="true"></i>
                                    </div>
                                    <div>
                                        <div class="module-group-tag">${escapeHtml(mod.group || 'Nghiệp vụ')}</div>
                                        <h6 class="module-card-title">${escapeHtml(mod.label)}</h6>
                                    </div>
                                </div>
                                <p class="module-card-desc">${escapeHtml(mod.description || '')}</p>
                            </div>
                            <div>
                                ${metricHtml ? `<div class="module-card-metric tabular-nums">${metricHtml}</div>` : ''}
                                <a href="${escapeHtml(mod.href)}" class="btn btn-clinical-primary w-100">
                                    Mở khu vực <i class="fas fa-arrow-right ms-1" aria-hidden="true"></i>
                                </a>
                            </div>
                        </article>
                    `;
                }).join('');
            }
        }
    }

    async function loadDashboardSummary(rbac) {
        if (!rbac || !rbac.hasPageAccess) return;

        if (!Array.isArray(rbac.permissions) || rbac.permissions.length === 0) {
            setDashboardViewState('empty');
            return;
        }

        setDashboardViewState('loading');
        try {
            const res = await fetch(`${API_BASE_STAFF}/get-dashboard-summary.php`, {
                credentials: 'include'
            });
            const payload = await res.json();
            if (!res.ok || !payload.success || !payload.data) {
                setDashboardViewState('error', payload.message || 'Không thể tải dữ liệu tổng quan.');
                return;
            }
            renderDashboardData(rbac, payload.data);
            setDashboardViewState('data');
        } catch (err) {
            console.error('Dashboard summary error:', err);
            setDashboardViewState('error', 'Lỗi kết nối máy chủ khi tải dữ liệu tổng quan.');
        }
    }

    window.addEventListener('staff:rbac-ready', (e) => {
        lastRbacDetail = e.detail;
        loadDashboardSummary(lastRbacDetail);
    });

    document.addEventListener('DOMContentLoaded', () => {
        const refreshBtn = document.getElementById('btnRefreshDashboard');
        const retryBtn = document.getElementById('btnRetryDashboard');
        const reloadHandler = () => {
            if (lastRbacDetail) {
                loadDashboardSummary(lastRbacDetail);
            } else {
                window.location.reload();
            }
        };
        if (refreshBtn) refreshBtn.addEventListener('click', reloadHandler);
        if (retryBtn) retryBtn.addEventListener('click', reloadHandler);
    });
})();
