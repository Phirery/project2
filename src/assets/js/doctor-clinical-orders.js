(function (global) {
    'use strict';

    const state = {
        catalog: [],
        catalogLoaded: false,
        maHoSo: null,
        orders: [],
        selectedServiceIds: new Set(),
        filterGroup: '',
        filterSearch: '',
        editingOrderItemId: null,
        cancellingOrderItemId: null,
        hooks: {},
    };

    function esc(val) {
        if (typeof global.escapeHtml === 'function') {
            return global.escapeHtml(String(val ?? ''));
        }
        const div = document.createElement('div');
        div.textContent = String(val ?? '');
        return div.innerHTML;
    }

    function fmtMoney(amount) {
        if (typeof global.formatCurrency === 'function') {
            return global.formatCurrency(amount);
        }
        return `${Number(amount || 0).toLocaleString('vi-VN')} đ`;
    }

    function statusBadgeHtml(status) {
        const map = {
            'Mới tạo': { bg: '#F1F5F9', color: '#334155', icon: 'fa-file-alt' },
            'Đang chờ': { bg: '#FFF3CD', color: '#856404', icon: 'fa-clock' },
            'Đang thực hiện': { bg: '#CFE2FF', color: '#084298', icon: 'fa-spinner' },
            'Có kết quả': { bg: '#E0F2FE', color: '#075985', icon: 'fa-file-medical-alt' },
            'Đã duyệt': { bg: '#D1FAE5', color: '#065F46', icon: 'fa-check-circle' },
            'Hủy': { bg: '#F8D7DA', color: '#721C24', icon: 'fa-ban' },
        };
        const cfg = map[status] || { bg: '#F1F5F9', color: '#334155', icon: 'fa-info-circle' };
        return `<span class="cls-status-badge" style="background:${cfg.bg};color:${cfg.color};"><i class="fas ${cfg.icon} me-1"></i>${esc(status)}</span>`;
    }

    function priorityBadgeHtml(priority) {
        if (priority === 'Khẩn') {
            return '<span class="cls-priority-badge" style="background:#F8D7DA;color:#721C24;">Khẩn</span>';
        }
        if (priority === 'Ưu tiên') {
            return '<span class="cls-priority-badge" style="background:#FEF3C7;color:#92400E;">Ưu tiên</span>';
        }
        return '<span class="cls-priority-badge" style="background:#F1F5F9;color:#475569;">Bình thường</span>';
    }

    function groupBadgeHtml(nhomDichVu, tenNhom) {
        const colors = {
            'xet-nghiem': { bg: '#E3F2FD', color: '#084298' },
            'chan-doan-hinh-anh': { bg: '#CCFBF1', color: '#0F766E' },
            'noi-soi': { bg: '#FEF3C7', color: '#92400E' },
        };
        const c = colors[nhomDichVu] || { bg: '#F1F5F9', color: '#334155' };
        return `<span class="cls-group-badge" style="background:${c.bg};color:${c.color};">${esc(tenNhom || nhomDichVu)}</span>`;
    }

    async function ensureCatalogLoaded() {
        if (state.catalogLoaded) return state.catalog;
        const apiBase = global.API_ENDPOINTS?.doctor;
        const res = await fetch(`${apiBase}/get-paraclinical-services.php`, { credentials: 'include' });
        const payload = await res.json();
        if (!res.ok || !payload.success || !payload.data) {
            throw new Error(payload.message || 'Không thể tải danh mục dịch vụ CLS');
        }
        state.catalog = Array.isArray(payload.data.services) ? payload.data.services : [];
        state.catalogLoaded = true;
        return state.catalog;
    }

    async function refreshRecordOrders() {
        if (!state.maHoSo) return;
        const apiBase = global.API_ENDPOINTS?.doctor;
        const res = await fetch(`${apiBase}/get-record-detail.php?maHoSo=${encodeURIComponent(state.maHoSo)}`, {
            credentials: 'include'
        });
        const payload = await res.json();
        if (res.ok && payload.success && payload.data) {
            state.orders = Array.isArray(payload.data.chiDinhCanLamSang) ? payload.data.chiDinhCanLamSang : [];
            renderEditPanel();
            if (typeof state.hooks.onOrdersChanged === 'function') {
                state.hooks.onOrdersChanged(payload.data);
            }
        }
    }

    function renderReadOnlyOrders(orders) {
        const list = Array.isArray(orders) ? orders : [];
        if (list.length === 0) {
            return `
                <div class="col-12 mt-2">
                    <div class="p-3 rounded-3 border bg-light text-secondary small">
                        <i class="fas fa-microscope me-2 text-primary"></i>Chưa có chỉ định cận lâm sàng nào trong hồ sơ này.
                    </div>
                </div>
            `;
        }

        const rowsHtml = list.map((item) => {
            const sttText = item.hangDoi?.soThuTu ? `#${item.hangDoi.soThuTu}` : '—';
            return `
                <tr>
                    <td>
                        <div class="fw-semibold text-dark">${esc(item.tenDichVu)}</div>
                        <div class="small text-secondary">${groupBadgeHtml(item.nhomDichVu, item.tenNhom)} · ${esc(item.maPhieuChiDinh)}</div>
                        ${item.ghiChu ? `<div class="small text-muted mt-1">Ghi chú: ${esc(item.ghiChu)}</div>` : ''}
                        ${item.trangThai === 'Hủy' && item.lyDoHuy ? `<div class="small text-danger mt-1">Lý do hủy: ${esc(item.lyDoHuy)}</div>` : ''}
                    </td>
                    <td class="tabular-nums">${sttText}</td>
                    <td>${priorityBadgeHtml(item.doUuTien)}</td>
                    <td class="tabular-nums">${fmtMoney(item.donGia)}</td>
                    <td>${statusBadgeHtml(item.trangThai)}</td>
                </tr>
            `;
        }).join('');

        return `
            <div class="col-12 mt-2">
                <div class="p-3 rounded-3 border bg-white">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold text-dark"><i class="fas fa-microscope text-primary me-2"></i>Chỉ định cận lâm sàng (${list.length})</span>
                    </div>
                    <div class="table-responsive my-0">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Dịch vụ</th>
                                    <th>STT</th>
                                    <th>Ưu tiên</th>
                                    <th>Chi phí</th>
                                    <th>Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>${rowsHtml}</tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
    }

    function renderExistingOrdersHtml() {
        const list = state.orders || [];
        if (list.length === 0) {
            return `<div class="alert alert-light border mb-3">Chưa có chỉ định cận lâm sàng nào cho hồ sơ này.</div>`;
        }

        const blockingCount = list.filter((o) => o.trangThai !== 'Hủy' && o.trangThai !== 'Đã duyệt').length;
        const warningBanner = blockingCount > 0
            ? `<div class="alert alert-warning py-2 px-3 small mb-3">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Đang có <strong>${blockingCount}</strong> chỉ định chưa được duyệt kết quả. Bạn vẫn có thể <strong>Lưu nháp</strong>, nhưng không thể <strong>Hoàn thành</strong> hồ sơ cho tới khi các chỉ định được duyệt hoặc hủy có lý do.
               </div>`
            : '';

        const itemsHtml = list.map((item) => {
            const canMutate = item.trangThai === 'Mới tạo' || item.trangThai === 'Đang chờ';
            const sttLabel = item.hangDoi?.soThuTu ? `STT #${item.hangDoi.soThuTu}` : 'Chưa xếp hàng';
            const isEditing = state.editingOrderItemId === item.maChiTietChiDinh;
            const isCancelling = state.cancellingOrderItemId === item.maChiTietChiDinh;

            return `
                <div class="cls-order-item">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div>
                            <div class="fw-semibold text-dark">${esc(item.tenDichVu)}</div>
                            <div class="d-flex flex-wrap align-items-center gap-2 mt-1 small">
                                ${groupBadgeHtml(item.nhomDichVu, item.tenNhom)}
                                ${priorityBadgeHtml(item.doUuTien)}
                                <span class="badge bg-light text-dark border tabular-nums">${esc(sttLabel)}</span>
                                <span class="badge ${item.batBuoc ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary'}">${item.batBuoc ? 'Bắt buộc' : 'Tùy chọn'}</span>
                                <span class="text-secondary tabular-nums">${fmtMoney(item.donGia)}</span>
                            </div>
                            ${item.ghiChu ? `<div class="small text-secondary mt-1">Ghi chú mục: ${esc(item.ghiChu)}</div>` : ''}
                            ${item.ghiChuLamSang ? `<div class="small text-muted">Lâm sàng: ${esc(item.ghiChuLamSang)}</div>` : ''}
                            ${item.trangThai === 'Hủy' && item.lyDoHuy ? `<div class="small text-danger mt-1"><i class="fas fa-ban me-1"></i>Lý do hủy: ${esc(item.lyDoHuy)}</div>` : ''}
                        </div>
                        <div class="d-flex flex-column align-items-end gap-2">
                            ${statusBadgeHtml(item.trangThai)}
                            ${canMutate ? `
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-outline-primary btn-sm" data-cls-edit="${item.maChiTietChiDinh}">
                                        <i class="fas fa-pen me-1"></i>Sửa
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm" data-cls-cancel="${item.maChiTietChiDinh}">
                                        <i class="fas fa-times me-1"></i>Hủy
                                    </button>
                                </div>
                            ` : ''}
                        </div>
                    </div>

                    ${isEditing ? `
                        <div class="cls-inline-box mt-3">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label small mb-1">Độ ưu tiên</label>
                                    <select class="form-select form-select-sm" id="clsEditPriority_${item.maChiTietChiDinh}">
                                        <option value="Bình thường" ${item.doUuTien === 'Bình thường' ? 'selected' : ''}>Bình thường</option>
                                        <option value="Ưu tiên" ${item.doUuTien === 'Ưu tiên' ? 'selected' : ''}>Ưu tiên</option>
                                        <option value="Khẩn" ${item.doUuTien === 'Khẩn' ? 'selected' : ''}>Khẩn</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small mb-1">Ghi chú chỉ định</label>
                                    <input type="text" class="form-control form-control-sm" id="clsEditNote_${item.maChiTietChiDinh}" value="${esc(item.ghiChu || '')}" placeholder="Yêu cầu chuyên môn...">
                                </div>
                                <div class="col-md-2">
                                    <div class="form-check mb-1">
                                        <input class="form-check-input" type="checkbox" id="clsEditReq_${item.maChiTietChiDinh}" ${item.batBuoc ? 'checked' : ''}>
                                        <label class="form-check-label small" for="clsEditReq_${item.maChiTietChiDinh}">Bắt buộc</label>
                                    </div>
                                </div>
                                <div class="col-md-2 d-flex gap-1 justify-content-end">
                                    <button type="button" class="btn btn-primary btn-sm" data-cls-save-edit="${item.maChiTietChiDinh}">Lưu</button>
                                    <button type="button" class="btn btn-light btn-sm border" data-cls-close-inline>Đóng</button>
                                </div>
                            </div>
                        </div>
                    ` : ''}

                    ${isCancelling ? `
                        <div class="cls-inline-box mt-3 border-danger-subtle">
                            <label class="form-label small text-danger fw-semibold mb-1">Lý do hủy chỉ định <span class="text-danger">*</span></label>
                            <div class="d-flex flex-wrap gap-2">
                                <input type="text" class="form-control form-control-sm flex-grow-1" id="clsCancelReason_${item.maChiTietChiDinh}" placeholder="Nhập lý do hủy (bắt buộc)...">
                                <button type="button" class="btn btn-danger btn-sm" data-cls-confirm-cancel="${item.maChiTietChiDinh}">Xác nhận hủy</button>
                                <button type="button" class="btn btn-light btn-sm border" data-cls-close-inline>Đóng</button>
                            </div>
                        </div>
                    ` : ''}
                </div>
            `;
        }).join('');

        return `${warningBanner}<div class="cls-orders-list mb-3">${itemsHtml}</div>`;
    }

    function renderCatalogPickerHtml() {
        const q = state.filterSearch.toLowerCase();
        const filtered = state.catalog.filter((s) => {
            if (state.filterGroup && s.nhomDichVu !== state.filterGroup) return false;
            if (q && !s.tenDichVu.toLowerCase().includes(q) && !s.maCode.toLowerCase().includes(q)) return false;
            return true;
        });

        const selectedTotal = state.catalog
            .filter((s) => state.selectedServiceIds.has(s.maDichVu))
            .reduce((sum, s) => sum + Number(s.gia || 0), 0);

        const groupTabs = [
            { key: '', label: 'Tất cả' },
            { key: 'xet-nghiem', label: 'Xét nghiệm' },
            { key: 'chan-doan-hinh-anh', label: 'Chẩn đoán hình ảnh' },
            { key: 'noi-soi', label: 'Nội soi' },
        ];

        const serviceRows = filtered.length === 0
            ? '<div class="text-center text-secondary small py-3">Không tìm thấy dịch vụ phù hợp.</div>'
            : filtered.map((svc) => {
                const checked = state.selectedServiceIds.has(svc.maDichVu);
                return `
                    <label class="cls-catalog-row ${checked ? 'is-selected' : ''}">
                        <input type="checkbox" class="form-check-input mt-1" data-cls-pick="${svc.maDichVu}" ${checked ? 'checked' : ''}>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <span class="fw-semibold text-dark">${esc(svc.tenDichVu)}</span>
                                <span class="fw-semibold text-primary tabular-nums text-nowrap">${fmtMoney(svc.gia)}</span>
                            </div>
                            <div class="small text-secondary mt-1">
                                ${groupBadgeHtml(svc.nhomDichVu, svc.tenNhom)} · <span class="tabular-nums">~${svc.thoiGianDuKienPhut} phút</span>
                                ${svc.chuanBiTruocKhiLam ? ` · ${esc(svc.chuanBiTruocKhiLam)}` : ''}
                            </div>
                        </div>
                    </label>
                `;
            }).join('');

        return `
            <div class="cls-picker-card">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <div class="fw-bold text-dark"><i class="fas fa-plus-circle text-primary me-1"></i>Tạo chỉ định cận lâm sàng mới</div>
                    <span class="badge bg-primary-subtle text-primary tabular-nums">Đã chọn: ${state.selectedServiceIds.size} · ${fmtMoney(selectedTotal)}</span>
                </div>

                <div class="d-flex flex-wrap gap-2 mb-2">
                    ${groupTabs.map((t) => `
                        <button type="button" class="btn btn-sm ${state.filterGroup === t.key ? 'btn-primary' : 'btn-outline-secondary'}" data-cls-group="${t.key}">${t.label}</button>
                    `).join('')}
                    <input type="text" class="form-control form-control-sm ms-auto" style="max-width: 240px;" id="clsCatalogSearchInput" value="${esc(state.filterSearch)}" placeholder="Tìm tên hoặc mã dịch vụ...">
                </div>

                <div class="cls-catalog-list mb-3">${serviceRows}</div>

                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Độ ưu tiên chung</label>
                        <select class="form-select form-select-sm" id="clsNewOrderPriority">
                            <option value="Bình thường">Bình thường</option>
                            <option value="Ưu tiên">Ưu tiên</option>
                            <option value="Khẩn">Khẩn</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small mb-1">Ghi chú lâm sàng gửi bộ phận CLS</label>
                        <input type="text" class="form-control form-control-sm" id="clsNewOrderClinicalNote" placeholder="Triệu chứng nghi ngờ, lưu ý lấy mẫu...">
                    </div>
                    <div class="col-md-2">
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" id="clsNewOrderMandatory" checked>
                            <label class="form-check-label small" for="clsNewOrderMandatory">Bắt buộc</label>
                        </div>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="button" class="btn btn-primary btn-sm" id="btnCreateClinicalOrders" ${state.selectedServiceIds.size === 0 ? 'disabled' : ''}>
                            <i class="fas fa-paper-plane me-1"></i>Tạo chỉ định
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    function renderEditPanel() {
        const container = document.getElementById('editClinicalOrdersBox');
        if (!container) return;

        const badge = document.getElementById('selectedOrdersBadge');
        const activeCount = (state.orders || []).filter((o) => o.trangThai !== 'Hủy').length;
        if (badge) {
            badge.textContent = `${activeCount} chỉ định`;
        }

        container.innerHTML = renderExistingOrdersHtml() + renderCatalogPickerHtml();
        bindEditPanelEvents(container);
    }

    function bindEditPanelEvents(container) {
        container.querySelectorAll('[data-cls-group]').forEach((btn) => {
            btn.addEventListener('click', () => {
                state.filterGroup = btn.getAttribute('data-cls-group') || '';
                renderEditPanel();
            });
        });

        const searchEl = container.querySelector('#clsCatalogSearchInput');
        if (searchEl) {
            searchEl.addEventListener('input', (e) => {
                state.filterSearch = e.target.value;
                renderEditPanel();
                const refocused = document.getElementById('clsCatalogSearchInput');
                if (refocused) {
                    refocused.focus();
                    refocused.setSelectionRange(refocused.value.length, refocused.value.length);
                }
            });
        }

        container.querySelectorAll('[data-cls-pick]').forEach((cb) => {
            cb.addEventListener('change', () => {
                const id = Number(cb.getAttribute('data-cls-pick'));
                if (cb.checked) state.selectedServiceIds.add(id);
                else state.selectedServiceIds.delete(id);
                renderEditPanel();
            });
        });

        container.querySelectorAll('[data-cls-edit]').forEach((btn) => {
            btn.addEventListener('click', () => {
                state.editingOrderItemId = Number(btn.getAttribute('data-cls-edit'));
                state.cancellingOrderItemId = null;
                renderEditPanel();
            });
        });

        container.querySelectorAll('[data-cls-cancel]').forEach((btn) => {
            btn.addEventListener('click', () => {
                state.cancellingOrderItemId = Number(btn.getAttribute('data-cls-cancel'));
                state.editingOrderItemId = null;
                renderEditPanel();
            });
        });

        container.querySelectorAll('[data-cls-close-inline]').forEach((btn) => {
            btn.addEventListener('click', () => {
                state.editingOrderItemId = null;
                state.cancellingOrderItemId = null;
                renderEditPanel();
            });
        });

        container.querySelectorAll('[data-cls-save-edit]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const id = Number(btn.getAttribute('data-cls-save-edit'));
                const doUuTien = document.getElementById(`clsEditPriority_${id}`)?.value || 'Bình thường';
                const ghiChu = document.getElementById(`clsEditNote_${id}`)?.value.trim() || '';
                const batBuoc = Boolean(document.getElementById(`clsEditReq_${id}`)?.checked);
                btn.disabled = true;
                try {
                    const res = await fetch(`${global.API_ENDPOINTS.doctor}/update-order.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'include',
                        body: JSON.stringify({ maChiTietChiDinh: id, doUuTien, ghiChu, batBuoc }),
                    });
                    const data = await res.json();
                    if (!res.ok || !data.success) {
                        global.showAlert?.('error', data.message || 'Không thể cập nhật chỉ định');
                        return;
                    }
                    state.editingOrderItemId = null;
                    global.showAlert?.('success', data.message || 'Đã cập nhật chỉ định');
                    await refreshRecordOrders();
                } catch (err) {
                    console.error(err);
                    global.showAlert?.('error', 'Lỗi kết nối máy chủ');
                } finally {
                    btn.disabled = false;
                }
            });
        });

        container.querySelectorAll('[data-cls-confirm-cancel]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const id = Number(btn.getAttribute('data-cls-confirm-cancel'));
                const lyDoHuy = document.getElementById(`clsCancelReason_${id}`)?.value.trim() || '';
                if (!lyDoHuy) {
                    global.showAlert?.('error', 'Vui lòng nhập lý do hủy chỉ định.');
                    document.getElementById(`clsCancelReason_${id}`)?.focus();
                    return;
                }
                btn.disabled = true;
                try {
                    const res = await fetch(`${global.API_ENDPOINTS.doctor}/cancel-order.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'include',
                        body: JSON.stringify({ maChiTietChiDinh: id, lyDoHuy }),
                    });
                    const data = await res.json();
                    if (!res.ok || !data.success) {
                        global.showAlert?.('error', data.message || 'Không thể hủy chỉ định');
                        return;
                    }
                    state.cancellingOrderItemId = null;
                    global.showAlert?.('success', data.message || 'Đã hủy chỉ định');
                    await refreshRecordOrders();
                } catch (err) {
                    console.error(err);
                    global.showAlert?.('error', 'Lỗi kết nối máy chủ');
                } finally {
                    btn.disabled = false;
                }
            });
        });

        const createBtn = container.querySelector('#btnCreateClinicalOrders');
        if (createBtn) {
            createBtn.addEventListener('click', async () => {
                if (state.selectedServiceIds.size === 0) return;
                const doUuTien = document.getElementById('clsNewOrderPriority')?.value || 'Bình thường';
                const ghiChuLamSang = document.getElementById('clsNewOrderClinicalNote')?.value.trim() || '';
                const batBuoc = Boolean(document.getElementById('clsNewOrderMandatory')?.checked);
                const services = Array.from(state.selectedServiceIds).map((maDichVu) => ({
                    maDichVu,
                    batBuoc,
                    doUuTien,
                }));

                createBtn.disabled = true;
                try {
                    const res = await fetch(`${global.API_ENDPOINTS.doctor}/create-order.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'include',
                        body: JSON.stringify({
                            maHoSo: state.maHoSo,
                            doUuTien,
                            ghiChuLamSang,
                            services,
                        }),
                    });
                    const data = await res.json();
                    if (!res.ok || !data.success) {
                        global.showAlert?.('error', data.message || 'Không thể tạo chỉ định');
                        return;
                    }
                    state.selectedServiceIds.clear();
                    global.showAlert?.('success', data.message || 'Đã tạo chỉ định CLS');
                    await refreshRecordOrders();
                } catch (err) {
                    console.error(err);
                    global.showAlert?.('error', 'Lỗi kết nối máy chủ');
                } finally {
                    createBtn.disabled = false;
                }
            });
        }
    }

    async function initForEditModal(detail, hooks = {}) {
        state.maHoSo = detail.maHoSo;
        state.orders = Array.isArray(detail.chiDinhCanLamSang) ? detail.chiDinhCanLamSang : [];
        state.selectedServiceIds.clear();
        state.filterGroup = '';
        state.filterSearch = '';
        state.editingOrderItemId = null;
        state.cancellingOrderItemId = null;
        state.hooks = hooks || {};

        const box = document.getElementById('editClinicalOrdersBox');
        if (box) {
            box.innerHTML = '<div class="text-secondary small py-2"><i class="fas fa-spinner fa-spin me-2"></i>Đang tải danh mục dịch vụ cận lâm sàng...</div>';
        }

        try {
            await ensureCatalogLoaded();
            renderEditPanel();
        } catch (err) {
            console.error(err);
            if (box) {
                box.innerHTML = `<div class="alert alert-danger mb-0">${esc(err.message || 'Không thể tải danh mục CLS')}</div>`;
            }
        }
    }

    global.DoctorClinicalOrders = {
        initForEditModal,
        renderReadOnlyOrders,
    };
})(window);
