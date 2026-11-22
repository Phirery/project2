(function () {
    'use strict';

    const GROUP_LABELS = {
        'xet-nghiem': 'Xét nghiệm',
        'chan-doan-hinh-anh': 'Chẩn đoán hình ảnh',
        'noi-soi': 'Nội soi',
    };

    const SECTION_LABELS = {
        moTaHinhAnh: 'Mô tả hình ảnh',
        ketLuan: 'Tóm tắt hình ảnh',
        deNghi: 'Đề nghị',
        ganMat: 'Gan - Mật',
        tuyLachThan: 'Tụy - Lách - Thận',
        bangQuangTuCungTienLiet: 'Bàng quang / Tiểu khung',
        phanLoaiTirads: 'Phân loại TI-RADS',
        tai: 'Tai',
        muiXoang: 'Mũi - Xoang',
        hongThanhQuan: 'Họng - Thanh quản',
        thucQuan: 'Thực quản',
        daDay: 'Dạ dày',
        taTrang: 'Tá tràng',
        trucTrang: 'Trực tràng',
        daiTrangSigma: 'Đại tràng Sigma',
        daiTrangXuongNgangLen: 'Đại tràng xuống - ngang - lên',
        manhTrang: 'Manh tràng',
    };

    const CHECKLIST_LABELS = {
        xacNhanBenhNhan: 'Đã xác nhận đúng danh tính bệnh nhân',
        kiemTraDiUngThuocTe: 'Đã kiểm tra tiền sử dị ứng thuốc tê tại chỗ',
        veSinhDungCu: 'Dụng cụ vô khuẩn sẵn sàng',
        nhinAnDu6Gio: 'Bệnh nhân đã nhịn ăn đủ 6 - 8 giờ',
        kiemTraSinhHieu: 'Đã kiểm tra sinh hiệu trước thủ thuật',
        kyCamKetThuThuat: 'Bệnh nhân đã ký cam kết thực hiện thủ thuật',
        lamSachRuotDatChuan: 'Làm sạch đại tràng đạt chuẩn nội soi',
    };

    const state = {
        nhom: 'xet-nghiem',
        status: 'active',
        items: [],
        hasAccess: false,
        searchTimer: null,
        selectedItem: null,
        attachments: [],
    };

    let resultModalInstance = null;

    function todayLocalIso() {
        const d = new Date();
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    }

    function resolveInitialGroup(rbac) {
        const params = new URLSearchParams(window.location.search);
        const queryNhom = params.get('nhom');
        if (queryNhom && GROUP_LABELS[queryNhom]) {
            return queryNhom;
        }
        const perms = rbac?.permissions || [];
        if (perms.includes('cls.xet_nghiem.perform')) return 'xet-nghiem';
        if (perms.includes('cls.cdha.perform')) return 'chan-doan-hinh-anh';
        if (perms.includes('cls.noi_soi.perform')) return 'noi-soi';
        return 'xet-nghiem';
    }

    function genderLabel(g) {
        return { nam: 'Nam', nu: 'Nữ', khac: 'Khác' }[g] || '—';
    }

    function calculateAge(dob) {
        if (!dob) return '—';
        const d = new Date(dob);
        if (Number.isNaN(d.getTime())) return '—';
        const now = new Date();
        let age = now.getFullYear() - d.getFullYear();
        const m = now.getMonth() - d.getMonth();
        if (m < 0 || (m === 0 && now.getDate() < d.getDate())) age--;
        return age >= 0 ? age : '—';
    }

    function setViewState(viewName, errorMessage = '') {
        const loading = document.getElementById('clsLoadingState');
        const err = document.getElementById('clsErrorState');
        const empty = document.getElementById('clsEmptyState');
        const table = document.getElementById('clsTableWrapper');
        if (!loading || !err || !empty || !table) return;

        loading.classList.toggle('d-none', viewName !== 'loading');
        err.classList.toggle('d-none', viewName !== 'error');
        empty.classList.toggle('d-none', viewName !== 'empty');
        table.classList.toggle('d-none', viewName !== 'data');

        if (viewName === 'error' && errorMessage) {
            const msgEl = document.getElementById('clsErrorMessage');
            if (msgEl) msgEl.textContent = errorMessage;
        }
    }

    function syncFilterControls() {
        const select = document.getElementById('clsStatusSelect');
        if (select) select.value = state.status;

        const tabMap = {
            active: 'active',
            'Đang chờ': 'active',
            'Đang thực hiện': 'active',
            'Bỏ lỡ': 'active',
            completed: 'completed',
            cancelled: 'cancelled',
        };
        const activeTab = tabMap[state.status] || 'active';
        document.querySelectorAll('#clsTabs [data-cls-tab]').forEach((btn) => {
            btn.classList.toggle('active', btn.getAttribute('data-cls-tab') === activeTab);
        });
    }

    function statusBadge(item) {
        if (item.trangThaiChiDinh === 'Hủy' || item.trangThaiHangDoi === 'Hủy') {
            return '<span class="status-pill is-cancelled"><i class="fas fa-ban" aria-hidden="true"></i>Đã hủy</span>';
        }
        const rq = item.ketQua?.trangThai;
        if (rq === 'Đã duyệt' || item.trangThaiChiDinh === 'Đã duyệt') {
            return '<span class="status-pill is-approved"><i class="fas fa-check-circle" aria-hidden="true"></i>Đã duyệt</span>';
        }
        if (rq === 'Yêu cầu làm lại') {
            return '<span class="status-pill is-redo"><i class="fas fa-redo" aria-hidden="true"></i>Yêu cầu làm lại</span>';
        }
        if (rq === 'Chờ duyệt' || item.trangThaiChiDinh === 'Có kết quả') {
            return '<span class="status-pill is-pending-review"><i class="fas fa-hourglass-half" aria-hidden="true"></i>Chờ bác sĩ duyệt</span>';
        }
        if (item.trangThaiHangDoi === 'Đang thực hiện') {
            return '<span class="status-pill is-progress"><i class="fas fa-spinner" aria-hidden="true"></i>Đang thực hiện</span>';
        }
        if (item.trangThaiHangDoi === 'Bỏ lỡ') {
            return '<span class="status-pill is-cancelled"><i class="fas fa-user-times" aria-hidden="true"></i>Bỏ lỡ</span>';
        }
        return '<span class="status-pill is-waiting"><i class="fas fa-clock" aria-hidden="true"></i>Đang chờ</span>';
    }

    function priorityBadge(priority) {
        if (priority === 'Khẩn') return '<span class="status-pill is-urgent">Khẩn</span>';
        if (priority === 'Ưu tiên') return '<span class="status-pill is-priority">Ưu tiên</span>';
        return '<span class="badge bg-light text-dark border">Bình thường</span>';
    }

    function renderTable(items) {
        const tbody = document.getElementById('clsTableBody');
        if (!tbody) return;

        if (!items || items.length === 0) {
            setViewState('empty');
            return;
        }

        tbody.innerHTML = items.map((item) => {
            const isCancelled = item.trangThaiChiDinh === 'Hủy' || item.trangThaiHangDoi === 'Hủy';
            const isApproved = item.ketQua?.trangThai === 'Đã duyệt' || item.trangThaiChiDinh === 'Đã duyệt';
            const qStatus = item.trangThaiHangDoi;

            let actionsHtml = '';
            if (!isCancelled) {
                if (qStatus === 'Đang chờ') {
                    actionsHtml += `
                        <button type="button" class="btn btn-clinical-primary btn-sm" data-cls-queue-action="start" data-queue-id="${item.maHangDoiDichVu}">
                            <i class="fas fa-play me-1"></i>Nhận thực hiện
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm ms-1" data-cls-queue-action="miss" data-queue-id="${item.maHangDoiDichVu}" title="Đánh dấu vắng mặt">
                            Bỏ lỡ
                        </button>
                    `;
                } else if (qStatus === 'Bỏ lỡ') {
                    actionsHtml += `
                        <button type="button" class="btn btn-outline-primary btn-sm" data-cls-queue-action="recall" data-queue-id="${item.maHangDoiDichVu}">
                            <i class="fas fa-undo me-1"></i>Gọi lại
                        </button>
                    `;
                } else if (qStatus === 'Đang thực hiện' || qStatus === 'Hoàn thành') {
                    const label = isApproved ? 'Xem kết quả' : (item.ketQua ? 'Sửa / Xem kết quả' : 'Nhập kết quả');
                    const cls = isApproved ? 'btn-outline-primary' : 'btn-clinical-success';
                    actionsHtml += `
                        <button type="button" class="btn ${cls} btn-sm" data-cls-open-result="${item.maChiTietChiDinh}">
                            <i class="fas ${isApproved ? 'fa-eye' : 'fa-file-medical'} me-1"></i>${label}
                        </button>
                    `;
                }
            }

            return `
                <tr>
                    <td><span class="stt-badge">${item.soThuTu}</span></td>
                    <td>
                        <div class="fw-bold text-dark">${escapeHtml(item.benhNhan.tenBenhNhan)}</div>
                        <div class="small text-secondary tabular-nums">
                            ${escapeHtml(item.benhNhan.maBenhNhan)} · ${genderLabel(item.benhNhan.gioiTinh)} · ${calculateAge(item.benhNhan.ngaySinh)} tuổi
                        </div>
                        <div class="small text-secondary tabular-nums">${escapeHtml(item.benhNhan.soDienThoai || '')}</div>
                    </td>
                    <td>
                        <div class="fw-semibold text-dark">${escapeHtml(item.dichVu.tenDichVu)}</div>
                        <div class="small text-secondary tabular-nums">${escapeHtml(item.maPhieuChiDinh)} · Hồ sơ ${escapeHtml(item.maHoSo)}</div>
                        <div class="mt-1">${priorityBadge(item.doUuTien)}</div>
                    </td>
                    <td>
                        <div class="fw-semibold">${escapeHtml(item.tenBacSiChiDinh)}</div>
                        ${item.ghiChuLamSang ? `<div class="small text-secondary">${escapeHtml(truncateText(item.ghiChuLamSang, 60))}</div>` : ''}
                        ${item.ghiChuChiTiet ? `<div class="small text-muted">Lưu ý: ${escapeHtml(truncateText(item.ghiChuChiTiet, 60))}</div>` : ''}
                    </td>
                    <td>
                        ${statusBadge(item)}
                        ${item.ketQua?.phienBan ? `<div class="small text-secondary mt-1 tabular-nums">Phiên bản v${item.ketQua.phienBan}</div>` : ''}
                        ${isCancelled && item.lyDoHuy ? `<div class="small text-danger mt-1">${escapeHtml(item.lyDoHuy)}</div>` : ''}
                    </td>
                    <td class="text-end">${actionsHtml || '<span class="text-secondary small">—</span>'}</td>
                </tr>
            `;
        }).join('');

        tbody.querySelectorAll('[data-cls-queue-action]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const queueId = Number(btn.getAttribute('data-queue-id'));
                const action = btn.getAttribute('data-cls-queue-action');
                handleQueueAction(queueId, action, btn);
            });
        });

        tbody.querySelectorAll('[data-cls-open-result]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const detailId = Number(btn.getAttribute('data-cls-open-result'));
                const item = state.items.find((x) => x.maChiTietChiDinh === detailId);
                if (item) openResultModal(item);
            });
        });

        setViewState('data');
    }

    async function loadQueue() {
        if (!state.hasAccess) return;
        setViewState('loading');

        const dateVal = document.getElementById('clsDateInput')?.value || todayLocalIso();
        const searchVal = document.getElementById('clsSearchInput')?.value.trim() || '';
        const params = new URLSearchParams({
            nhom: state.nhom,
            date: dateVal,
            status: state.status,
        });
        if (searchVal) params.set('search', searchVal);

        try {
            const res = await fetch(`${API_BASE_STAFF}/get-paraclinical-queue.php?${params.toString()}`, {
                credentials: 'include'
            });
            const payload = await res.json();
            if (!res.ok || !payload.success || !payload.data) {
                setViewState('error', payload.message || 'Không thể tải hàng đợi CLS.');
                return;
            }

            const data = payload.data;
            state.items = Array.isArray(data.items) ? data.items : [];

            document.getElementById('clsStatWaiting').textContent = Number(data.stats?.choThucHien || 0);
            document.getElementById('clsStatProgress').textContent = Number(data.stats?.dangThucHien || 0);
            document.getElementById('clsStatPendingReview').textContent = Number(data.stats?.choDuyet || 0);
            document.getElementById('clsStatApproved').textContent = Number(data.stats?.daDuyet || 0);
            document.getElementById('clsStatMissed').textContent = Number(data.stats?.boLo || 0);

            renderTable(state.items);
        } catch (err) {
            console.error('Load CLS queue error:', err);
            setViewState('error', 'Lỗi kết nối máy chủ khi tải hàng đợi CLS.');
        }
    }

    async function handleQueueAction(maHangDoiDichVu, action, btn) {
        if (btn) btn.disabled = true;
        try {
            const res = await fetch(`${API_BASE_STAFF}/update-paraclinical-queue-status.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ maHangDoiDichVu, action }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                showAlert('error', data.message || 'Không thể cập nhật trạng thái hàng đợi');
                return;
            }
            showAlert('success', data.message || 'Cập nhật thành công');
            await loadQueue();
        } catch (err) {
            console.error(err);
            showAlert('error', 'Lỗi kết nối máy chủ');
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    function renderStructuredFields(item, isLocked) {
        const container = document.getElementById('clsStructuredContainer');
        if (!container) return;

        const tpl = item.dichVu.mauKetQuaMacDinh || {};
        const saved = item.ketQua?.chiSoKetQua || {};
        const disabledAttr = isLocked ? 'disabled' : '';
        let html = '';

        // 1. Checklist chuẩn bị (Nội soi)
        if (Array.isArray(tpl.checklist) && tpl.checklist.length > 0) {
            const savedChecklist = saved.checklist || {};
            html += `
                <div class="cls-section-block">
                    <h6 class="fw-bold mb-2"><i class="fas fa-tasks text-primary me-2"></i>Checklist chuẩn bị thủ thuật</h6>
                    <div class="row g-2">
                        ${tpl.checklist.map((key) => {
                            const checked = Boolean(savedChecklist[key]);
                            const label = CHECKLIST_LABELS[key] || key;
                            return `
                                <div class="col-md-6">
                                    <label class="form-check-label d-flex align-items-center gap-2 p-2 rounded border bg-light">
                                        <input type="checkbox" class="form-check-input mt-0" data-cls-checklist="${escapeHtml(key)}" ${checked ? 'checked' : ''} ${disabledAttr}>
                                        <span class="small">${escapeHtml(label)}</span>
                                    </label>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
        }

        // 2. Bảng chỉ số đo (Xét nghiệm / Thông số đo)
        if (Array.isArray(tpl.fields) && tpl.fields.length > 0) {
            const savedFields = saved.fields || {};
            html += `
                <div class="cls-section-block">
                    <h6 class="fw-bold mb-2"><i class="fas fa-vials text-primary me-2"></i>Chỉ số xét nghiệm / thông số đo</h6>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Chỉ số</th>
                                    <th style="width: 180px;">Kết quả đo</th>
                                    <th>Đơn vị</th>
                                    <th>Khoảng tham chiếu</th>
                                    <th style="width: 130px;">Đánh giá</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${tpl.fields.map((f) => {
                                    const savedVal = savedFields[f.key] || {};
                                    const valStr = typeof savedVal === 'object' ? (savedVal.value ?? '') : savedVal;
                                    const flag = typeof savedVal === 'object' ? (savedVal.flag ?? 'normal') : 'normal';
                                    return `
                                        <tr>
                                            <td class="fw-semibold">${escapeHtml(f.label)}</td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm tabular-nums" data-cls-field-val="${escapeHtml(f.key)}" value="${escapeHtml(valStr)}" placeholder="Nhập giá trị..." ${disabledAttr}>
                                            </td>
                                            <td class="small text-secondary">${escapeHtml(f.unit || '—')}</td>
                                            <td class="small text-secondary tabular-nums">${escapeHtml(f.ref || '—')}</td>
                                            <td>
                                                <select class="form-select form-select-sm" data-cls-field-flag="${escapeHtml(f.key)}" ${disabledAttr}>
                                                    <option value="normal" ${flag === 'normal' ? 'selected' : ''}>Bình thường</option>
                                                    <option value="high" ${flag === 'high' ? 'selected' : ''}>Cao</option>
                                                    <option value="low" ${flag === 'low' ? 'selected' : ''}>Thấp</option>
                                                    <option value="abnormal" ${flag === 'abnormal' ? 'selected' : ''}>Bất thường</option>
                                                </select>
                                            </td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }

        // 3. Mô tả theo từng mục (CĐHA / Nội soi)
        const narrativeSections = Array.isArray(tpl.sections)
            ? tpl.sections.filter((s) => s !== 'ketLuan' && s !== 'deNghi')
            : [];
        if (narrativeSections.length > 0) {
            const savedSections = saved.sections || {};
            html += `
                <div class="cls-section-block">
                    <h6 class="fw-bold mb-2"><i class="fas fa-notes-medical text-primary me-2"></i>Mô tả kết quả chuyên môn</h6>
                    <div class="row g-3">
                        ${narrativeSections.map((secKey) => `
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">${escapeHtml(SECTION_LABELS[secKey] || secKey)}</label>
                                <textarea class="form-control form-control-sm" rows="2" data-cls-section="${escapeHtml(secKey)}" placeholder="Nhập mô tả..." ${disabledAttr}>${escapeHtml(savedSections[secKey] || '')}</textarea>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        container.innerHTML = html;
    }

    function renderAttachments(isLocked) {
        const container = document.getElementById('clsAttachmentsContainer');
        if (!container) return;

        if (!state.attachments.length) {
            container.innerHTML = '<div class="small text-secondary">Chưa có tệp hoặc liên kết ảnh nào được đính kèm.</div>';
            return;
        }

        container.innerHTML = state.attachments.map((att, idx) => `
            <div class="cls-attachment-row">
                <div class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label small mb-1">URL ảnh / PDF <span class="text-danger">*</span></label>
                        <input type="url" class="form-control form-control-sm" data-att-idx="${idx}" data-att-prop="url" value="${escapeHtml(att.url || '')}" placeholder="https://..." ${isLocked ? 'disabled' : ''}>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Tiêu đề / mô tả</label>
                        <input type="text" class="form-control form-control-sm" data-att-idx="${idx}" data-att-prop="title" value="${escapeHtml(att.title || '')}" placeholder="Ảnh X-quang ngực" ${isLocked ? 'disabled' : ''}>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Định dạng</label>
                        <select class="form-select form-select-sm" data-att-idx="${idx}" data-att-prop="mimeType" ${isLocked ? 'disabled' : ''}>
                            <option value="image/jpeg" ${att.mimeType === 'image/jpeg' ? 'selected' : ''}>JPG</option>
                            <option value="image/png" ${att.mimeType === 'image/png' ? 'selected' : ''}>PNG</option>
                            <option value="image/webp" ${att.mimeType === 'image/webp' ? 'selected' : ''}>WEBP</option>
                            <option value="application/pdf" ${att.mimeType === 'application/pdf' ? 'selected' : ''}>PDF</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-1 justify-content-end">
                        ${att.url ? `<a href="${escapeHtml(att.url)}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary btn-sm" title="Mở liên kết"><i class="fas fa-external-link-alt"></i></a>` : ''}
                        ${!isLocked ? `<button type="button" class="btn btn-outline-danger btn-sm" data-att-remove="${idx}" aria-label="Xóa tệp"><i class="fas fa-trash"></i></button>` : ''}
                    </div>
                </div>
            </div>
        `).join('');

        container.querySelectorAll('[data-att-prop]').forEach((el) => {
            el.addEventListener('input', () => {
                const idx = Number(el.getAttribute('data-att-idx'));
                const prop = el.getAttribute('data-att-prop');
                if (state.attachments[idx]) {
                    state.attachments[idx][prop] = el.value;
                }
            });
            el.addEventListener('change', () => {
                const idx = Number(el.getAttribute('data-att-idx'));
                const prop = el.getAttribute('data-att-prop');
                if (state.attachments[idx]) {
                    state.attachments[idx][prop] = el.value;
                }
            });
        });

        container.querySelectorAll('[data-att-remove]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const idx = Number(btn.getAttribute('data-att-remove'));
                state.attachments.splice(idx, 1);
                renderAttachments(isLocked);
            });
        });
    }

    async function loadResultHistory(maChiTietChiDinh) {
        const listEl = document.getElementById('clsHistoryList');
        const countEl = document.getElementById('clsHistoryCountLabel');
        if (!listEl) return;

        listEl.innerHTML = '<div class="text-secondary small">Đang tải lịch sử...</div>';
        try {
            const res = await fetch(`${API_BASE_STAFF}/get-paraclinical-result-history.php?maChiTietChiDinh=${maChiTietChiDinh}`, {
                credentials: 'include'
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                listEl.innerHTML = `<div class="text-danger small">${escapeHtml(data.message || 'Không tải được lịch sử')}</div>`;
                return;
            }
            const history = data.data?.history || [];
            if (countEl) countEl.textContent = `${history.length} sự kiện`;
            if (!history.length) {
                listEl.innerHTML = '<div class="text-secondary small">Chưa có lịch sử thao tác.</div>';
                return;
            }

            listEl.innerHTML = history.map((h) => {
                const actor = h.actorTenNhanVien || h.actorTenBacSi || h.actorMaNhanVien || h.actorMaBacSi || 'Hệ thống';
                return `
                    <div class="cls-history-item">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <strong>${escapeHtml(h.hanhDong)} (v${h.phienBan})</strong>
                            <span class="text-secondary tabular-nums">${escapeHtml(formatDateTime(h.createdAt))}</span>
                        </div>
                        <div class="text-secondary mt-1">
                            ${h.trangThaiCu ? `${escapeHtml(h.trangThaiCu)} → ` : ''}<strong>${escapeHtml(h.trangThaiMoi)}</strong>
                            · Thực hiện: ${escapeHtml(actor)}
                        </div>
                        ${h.ghiChu ? `<div class="text-dark mt-1">${escapeHtml(h.ghiChu)}</div>` : ''}
                    </div>
                `;
            }).join('');
        } catch (err) {
            console.error(err);
            listEl.innerHTML = '<div class="text-danger small">Lỗi tải lịch sử thao tác.</div>';
        }
    }

    function openResultModal(item) {
        state.selectedItem = item;
        const isApproved = item.ketQua?.trangThai === 'Đã duyệt' || item.trangThaiChiDinh === 'Đã duyệt';

        const errBox = document.getElementById('clsModalError');
        if (errBox) {
            errBox.classList.add('d-none');
            errBox.textContent = '';
        }

        const redoBox = document.getElementById('clsRedoWarning');
        if (redoBox) {
            if (item.ketQua?.trangThai === 'Yêu cầu làm lại' && item.ketQua?.lyDoYeuCauLamLai) {
                redoBox.innerHTML = `<strong><i class="fas fa-redo me-1"></i>Bác sĩ yêu cầu làm lại:</strong> ${escapeHtml(item.ketQua.lyDoYeuCauLamLai)}`;
                redoBox.classList.remove('d-none');
            } else {
                redoBox.classList.add('d-none');
                redoBox.textContent = '';
            }
        }

        document.getElementById('clsResultModalTitle').innerHTML = `
            <i class="fas fa-file-medical me-2"></i>${escapeHtml(item.dichVu.tenDichVu)} (STT #${item.soThuTu})
        `;

        document.getElementById('clsModalContext').innerHTML = `
            <div class="row g-2 small">
                <div class="col-md-6">
                    <strong>Bệnh nhân:</strong> ${escapeHtml(item.benhNhan.tenBenhNhan)} (${escapeHtml(item.benhNhan.maBenhNhan)})<br>
                    <strong>Giới tính / Tuổi:</strong> ${genderLabel(item.benhNhan.gioiTinh)} · ${calculateAge(item.benhNhan.ngaySinh)} tuổi<br>
                    <strong>Phiếu chỉ định:</strong> ${escapeHtml(item.maPhieuChiDinh)} · Hồ sơ ${escapeHtml(item.maHoSo)}
                </div>
                <div class="col-md-6">
                    <strong>Bác sĩ chỉ định:</strong> ${escapeHtml(item.tenBacSiChiDinh)}<br>
                    <strong>Ghi chú lâm sàng:</strong> ${escapeHtml(item.ghiChuLamSang || item.ghiChuChiTiet || 'Không có')}<br>
                    ${item.dichVu.chuanBiTruocKhiLam ? `<strong>Chuẩn bị:</strong> ${escapeHtml(item.dichVu.chuanBiTruocKhiLam)}` : ''}
                </div>
            </div>
        `;

        renderStructuredFields(item, isApproved);

        document.getElementById('clsKetLuan').value = item.ketQua?.ketLuan || '';
        document.getElementById('clsNhanXet').value = item.ketQua?.nhanXet || '';
        document.getElementById('clsDeNghi').value = item.ketQua?.deNghi || '';
        document.getElementById('clsKetLuan').disabled = isApproved;
        document.getElementById('clsNhanXet').disabled = isApproved;
        document.getElementById('clsDeNghi').disabled = isApproved;

        state.attachments = Array.isArray(item.ketQua?.tepDinhKem)
            ? item.ketQua.tepDinhKem.map((a) => ({ ...a }))
            : [];
        renderAttachments(isApproved);

        document.getElementById('btnAddAttachmentRow')?.classList.toggle('d-none', isApproved);
        document.getElementById('clsResultModalActions')?.classList.toggle('d-none', isApproved);

        loadResultHistory(item.maChiTietChiDinh);

        if (!resultModalInstance) {
            resultModalInstance = new bootstrap.Modal(document.getElementById('clsResultModal'));
        }
        resultModalInstance.show();
    }

    function collectStructuredPayload() {
        const checklist = {};
        document.querySelectorAll('[data-cls-checklist]').forEach((cb) => {
            checklist[cb.getAttribute('data-cls-checklist')] = Boolean(cb.checked);
        });

        const fields = {};
        document.querySelectorAll('[data-cls-field-val]').forEach((inp) => {
            const key = inp.getAttribute('data-cls-field-val');
            const flagEl = document.querySelector(`[data-cls-field-flag="${key}"]`);
            fields[key] = {
                value: inp.value.trim(),
                flag: flagEl ? flagEl.value : 'normal',
            };
        });

        const sections = {};
        document.querySelectorAll('[data-cls-section]').forEach((ta) => {
            sections[ta.getAttribute('data-cls-section')] = ta.value.trim();
        });

        return { checklist, fields, sections };
    }

    async function saveResult(action) {
        if (!state.selectedItem) return;

        const errBox = document.getElementById('clsModalError');
        const showModalError = (msg) => {
            if (errBox) {
                errBox.textContent = msg;
                errBox.classList.remove('d-none');
            }
        };
        if (errBox) errBox.classList.add('d-none');

        const ketLuan = document.getElementById('clsKetLuan').value.trim();
        if (action === 'submit' && !ketLuan) {
            showModalError('Vui lòng nhập kết luận trước khi trả kết quả để bác sĩ duyệt.');
            document.getElementById('clsKetLuan').focus();
            return;
        }

        const cleanedAttachments = state.attachments
            .map((a) => ({
                url: (a.url || '').trim(),
                title: (a.title || '').trim(),
                mimeType: a.mimeType || 'image/jpeg',
            }))
            .filter((a) => a.url !== '');

        if (cleanedAttachments.length > 5) {
            showModalError('Chỉ được đính kèm tối đa 5 liên kết ảnh/PDF.');
            return;
        }

        const draftBtn = document.getElementById('btnSaveClsDraft');
        const submitBtn = document.getElementById('btnSubmitClsResult');
        draftBtn.disabled = true;
        submitBtn.disabled = true;

        try {
            const res = await fetch(`${API_BASE_STAFF}/save-paraclinical-result.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    maChiTietChiDinh: state.selectedItem.maChiTietChiDinh,
                    action,
                    chiSoKetQua: collectStructuredPayload(),
                    ketLuan,
                    nhanXet: document.getElementById('clsNhanXet').value.trim(),
                    deNghi: document.getElementById('clsDeNghi').value.trim(),
                    tepDinhKem: cleanedAttachments,
                }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                showModalError(data.message || 'Không thể lưu kết quả.');
                return;
            }

            showAlert('success', data.message || 'Lưu kết quả thành công');
            resultModalInstance?.hide();
            await loadQueue();
        } catch (err) {
            console.error(err);
            showModalError('Lỗi kết nối máy chủ khi lưu kết quả.');
        } finally {
            draftBtn.disabled = false;
            submitBtn.disabled = false;
        }
    }

    function bindEvents() {
        const dateEl = document.getElementById('clsDateInput');
        if (dateEl && !dateEl.value) {
            dateEl.value = todayLocalIso();
        }

        document.querySelectorAll('#clsTabs [data-cls-tab]').forEach((btn) => {
            btn.addEventListener('click', () => {
                state.status = btn.getAttribute('data-cls-tab') || 'active';
                syncFilterControls();
                loadQueue();
            });
        });

        document.querySelectorAll('#clsSummaryBar .summary-chip[data-cls-status]').forEach((chip) => {
            const handler = () => {
                state.status = chip.getAttribute('data-cls-status') || 'active';
                syncFilterControls();
                loadQueue();
            };
            chip.addEventListener('click', handler);
            chip.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    handler();
                }
            });
        });

        document.getElementById('clsStatusSelect')?.addEventListener('change', (e) => {
            state.status = e.target.value;
            syncFilterControls();
            loadQueue();
        });
        document.getElementById('clsDateInput')?.addEventListener('change', loadQueue);
        document.getElementById('clsSearchInput')?.addEventListener('input', () => {
            clearTimeout(state.searchTimer);
            state.searchTimer = setTimeout(loadQueue, 250);
        });

        document.getElementById('btnRefreshCls')?.addEventListener('click', loadQueue);
        document.getElementById('btnRetryCls')?.addEventListener('click', loadQueue);

        document.getElementById('btnAddAttachmentRow')?.addEventListener('click', () => {
            if (state.attachments.length >= 5) {
                showAlert('error', 'Tối đa 5 tệp đính kèm cho mỗi kết quả.');
                return;
            }
            state.attachments.push({ url: '', title: '', mimeType: 'image/jpeg' });
            renderAttachments(false);
        });

        document.getElementById('btnSaveClsDraft')?.addEventListener('click', () => saveResult('draft'));
        document.getElementById('btnSubmitClsResult')?.addEventListener('click', () => saveResult('submit'));
    }

    window.addEventListener('staff:rbac-ready', (e) => {
        state.hasAccess = Boolean(e.detail?.hasPageAccess);
        if (state.hasAccess) {
            state.nhom = resolveInitialGroup(e.detail);
            const groupLabel = GROUP_LABELS[state.nhom] || 'Cận lâm sàng';
            const headingEl = document.getElementById('clsPageHeading');
            if (headingEl) headingEl.textContent = `Cận lâm sàng — ${groupLabel}`;
            document.title = `${groupLabel} - Eden Health`;
            window.AdminSummary?.initClearableFilters?.();
            syncFilterControls();
            loadQueue();
        }
    });

    document.addEventListener('DOMContentLoaded', bindEvents);
})();
