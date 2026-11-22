(function () {
    'use strict';

    const state = {
        tab: 'unpaid',
        items: [],
        hasAccess: false,
        searchTimer: null,
        selectedEncounter: null,
        pendingPayload: null,
        clientRequestId: null,
        isSubmitting: false,
    };

    let billingModal = null;
    let confirmModal = null;
    let printModal = null;

    function formatMoney(amount) {
        const num = Number(amount || 0);
        return `${Math.round(num).toLocaleString('vi-VN')} đ`;
    }

    function generateRequestId(maLichKham) {
        const rand = Math.random().toString(36).slice(2, 10);
        return `bill-${maLichKham}-${Date.now()}-${rand}`;
    }

    function billingStatusBadge(status) {
        switch (status) {
            case 'Chưa thanh toán':
                return '<span class="status-pill is-unpaid"><i class="fas fa-clock" aria-hidden="true"></i>Chưa thanh toán</span>';
            case 'Thanh toán một phần':
                return '<span class="status-pill is-partial"><i class="fas fa-adjust" aria-hidden="true"></i>Thanh toán một phần</span>';
            case 'Đã thanh toán đủ':
                return '<span class="status-pill is-paid"><i class="fas fa-check-circle" aria-hidden="true"></i>Đã thanh toán đủ</span>';
            default:
                return `<span class="status-pill is-neutral">${escapeHtml(status || 'Chưa phát sinh')}</span>`;
        }
    }

    function setViewState(viewName, errorMessage = '') {
        const loading = document.getElementById('billingLoadingState');
        const err = document.getElementById('billingErrorState');
        const empty = document.getElementById('billingEmptyState');
        const table = document.getElementById('billingTableWrapper');
        if (!loading || !err || !empty || !table) return;

        loading.classList.toggle('d-none', viewName !== 'loading');
        err.classList.toggle('d-none', viewName !== 'error');
        empty.classList.toggle('d-none', viewName !== 'empty');
        table.classList.toggle('d-none', viewName !== 'data');

        if (viewName === 'error' && errorMessage) {
            const msgEl = document.getElementById('billingErrorMessage');
            if (msgEl) msgEl.textContent = errorMessage;
        }
    }

    function syncTabUI() {
        const tabs = [
            { id: 'tabUnpaidBillingBtn', key: 'unpaid' },
            { id: 'tabPaidBillingBtn', key: 'paid' },
            { id: 'tabAllBillingBtn', key: 'all' },
        ];
        tabs.forEach(({ id, key }) => {
            const btn = document.getElementById(id);
            if (!btn) return;
            const active = state.tab === key;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        document.querySelectorAll('#billingSummaryBar .summary-chip[data-tab-target]').forEach((chip) => {
            chip.classList.toggle('is-active', chip.getAttribute('data-tab-target') === state.tab);
        });
    }

    function renderStats(stats) {
        const setEl = (id, txt) => {
            const el = document.getElementById(id);
            if (el) el.textContent = txt;
        };
        setEl('statUnpaidCount', String(stats.choThu ?? 0));
        setEl('statPartialPayCount', String(stats.thanhToanMotPhan ?? 0));
        setEl('statPaidFullCount', String(stats.daThuDu ?? 0));
        setEl('statTotalOutstanding', formatMoney(stats.tongConPhaiThu ?? 0));
        setEl('statTodayRevenue', formatMoney(stats.doanhThuHomNay ?? 0));
    }

    function renderTable() {
        const tbody = document.getElementById('billingTableBody');
        if (!tbody) return;

        if (!state.items.length) {
            const title = document.getElementById('billingEmptyTitle');
            const desc = document.getElementById('billingEmptyDesc');
            if (state.tab === 'unpaid') {
                if (title) title.textContent = 'Không có lượt khám còn công nợ';
                if (desc) desc.textContent = 'Tất cả lượt khám phù hợp bộ lọc đều đã hoàn tất thanh toán.';
            } else if (state.tab === 'paid') {
                if (title) title.textContent = 'Chưa có lượt khám đã thu đủ';
                if (desc) desc.textContent = 'Không tìm thấy lượt khám đã thanh toán đủ theo bộ lọc hiện tại.';
            } else {
                if (title) title.textContent = 'Không có lượt khám phù hợp';
                if (desc) desc.textContent = 'Thử thay đổi từ khóa tìm kiếm hoặc ngày khám.';
            }
            setViewState('empty');
            return;
        }

        tbody.innerHTML = state.items.map((item) => {
            const g = item.groups || {};
            const t = item.totals || {};
            const hasUnpaid = Number(t.conPhaiThu || 0) > 0;

            return `
                <tr>
                    <td>
                        <div class="encounter-badge">#LK-${item.maLichKham}</div>
                        <div class="small text-secondary mt-1 tabular-nums">${escapeHtml(formatDate(item.ngayKham))} ${item.gioHen ? `• ${escapeHtml(item.gioHen)}` : ''}</div>
                    </td>
                    <td>
                        <div class="fw-semibold text-dark">${escapeHtml(item.tenBenhNhan)}</div>
                        <div class="small text-secondary tabular-nums">${escapeHtml(item.maBenhNhan)} ${item.soDienThoai ? `• ${escapeHtml(item.soDienThoai)}` : ''}</div>
                        <div class="small text-secondary">BS: ${escapeHtml(item.tenBacSi || '—')}</div>
                    </td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            <span class="group-mini-chip">Khám: <strong>${formatMoney(g.kham?.total)}</strong></span>
                            <span class="group-mini-chip">CLS: <strong>${formatMoney(g.cls?.total)}</strong></span>
                            <span class="group-mini-chip">Thuốc: <strong>${formatMoney(g.thuoc?.total)}</strong></span>
                        </div>
                    </td>
                    <td class="text-end tabular-nums">
                        <div class="fw-bold text-dark">${formatMoney(t.tongCong)}</div>
                        <div class="small text-success">Đã thu: ${formatMoney(t.daThanhToan)}</div>
                        ${hasUnpaid ? `<div class="small fw-semibold text-danger">Còn thu: ${formatMoney(t.conPhaiThu)}</div>` : ''}
                    </td>
                    <td>${billingStatusBadge(item.billingStatus)}</td>
                    <td class="text-end">
                        <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                            <button type="button" class="btn ${hasUnpaid ? 'btn-cashier-primary' : 'btn-outline-secondary'} btn-sm" data-action="open-billing" data-lk-id="${item.maLichKham}">
                                <i class="fas ${hasUnpaid ? 'fa-cash-register' : 'fa-eye'} me-1" aria-hidden="true"></i>${hasUnpaid ? 'Thu tiền' : 'Chi tiết'}
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-action="print-invoice" data-lk-id="${item.maLichKham}" aria-label="In hóa đơn lượt khám #${item.maLichKham}">
                                <i class="fas fa-print" aria-hidden="true"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        setViewState('data');
    }

    async function loadQueue() {
        if (!state.hasAccess) return;
        setViewState('loading');

        const search = document.getElementById('billingSearchInput')?.value.trim() || '';
        const date = document.getElementById('billingDateInput')?.value.trim() || '';
        const qs = new URLSearchParams({ tab: state.tab });
        if (search) qs.set('search', search);
        if (date) qs.set('date', date);

        try {
            const res = await fetch(`${API_BASE_STAFF}/get-billing-queue.php?${qs.toString()}`, {
                credentials: 'include',
            });
            const payload = await res.json().catch(() => ({}));
            if (!res.ok || !payload.success || !payload.data) {
                throw new Error(payload.message || `Lỗi máy chủ (${res.status})`);
            }

            state.items = Array.isArray(payload.data.items) ? payload.data.items : [];
            renderStats(payload.data.stats || {});
            renderTable();
        } catch (err) {
            setViewState('error', err.message || 'Không thể kết nối máy chủ.');
        }
    }

    function updateSelectedTotals() {
        const checkboxes = document.querySelectorAll('#billingItemsBody .billing-item-checkbox:not(:disabled)');
        let total = 0;
        let count = 0;
        checkboxes.forEach((cb) => {
            if (cb.checked) {
                total += Number(cb.getAttribute('data-amount') || 0);
                count++;
            }
        });
        document.getElementById('billingSelectedTotal').textContent = formatMoney(total);
        document.getElementById('billingSelectedCountText').textContent = `${count} khoản chưa thu`;
    }

    function renderEncounterModal(detail, vnpayBannerInfo = null) {
        state.selectedEncounter = detail;
        if (!state.clientRequestId) {
            state.clientRequestId = generateRequestId(detail.maLichKham);
        }

        document.getElementById('billingModalTitle').innerHTML =
            `<i class="fas fa-file-invoice-dollar me-2" aria-hidden="true"></i>Hóa đơn lượt khám #LK-${detail.maLichKham} — ${escapeHtml(detail.tenBenhNhan)}`;

        document.getElementById('billingPatientSummary').innerHTML = `
            <div class="row g-2 small">
                <div class="col-md-4">
                    <div><span class="text-secondary">Bệnh nhân:</span> <strong>${escapeHtml(detail.tenBenhNhan)}</strong> (${escapeHtml(detail.maBenhNhan)})</div>
                    <div class="tabular-nums"><span class="text-secondary">SĐT:</span> ${escapeHtml(detail.soDienThoai || '—')} ${detail.soTheBHYT ? `• BHYT: ${escapeHtml(detail.soTheBHYT)}` : ''}</div>
                </div>
                <div class="col-md-4">
                    <div><span class="text-secondary">Bác sĩ:</span> <strong>${escapeHtml(detail.tenBacSi)}</strong> (${escapeHtml(detail.tenChuyenKhoa)})</div>
                    <div class="tabular-nums"><span class="text-secondary">Lịch khám:</span> ${escapeHtml(formatDate(detail.ngayKham))} ${detail.gioHen ? `(${escapeHtml(detail.gioHen)})` : ''}</div>
                </div>
                <div class="col-md-4">
                    <div><span class="text-secondary">Trạng thái:</span> ${billingStatusBadge(detail.billingStatus)}</div>
                    <div class="mt-1"><span class="text-secondary">Chẩn đoán:</span> ${escapeHtml(detail.chanDoan || '—')}</div>
                </div>
            </div>
        `;

        const g = detail.groups || {};
        document.getElementById('billingGroupSummaryCards').innerHTML = ['kham', 'cls', 'thuoc'].map((key) => {
            const grp = g[key] || { label: key, total: 0, paid: 0, unpaid: 0 };
            return `
                <div class="col-md-4">
                    <div class="group-kpi-card">
                        <div class="group-kpi-title">${escapeHtml(grp.label)}</div>
                        <div class="fs-6 fw-bold text-dark tabular-nums">${formatMoney(grp.total)}</div>
                        <div class="d-flex justify-content-between small tabular-nums mt-1">
                            <span class="text-success">Đã thu: ${formatMoney(grp.paid)}</span>
                            <span class="${grp.unpaid > 0 ? 'text-danger fw-semibold' : 'text-secondary'}">Còn: ${formatMoney(grp.unpaid)}</span>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        document.getElementById('billingGeneralError').classList.add('d-none');

        const vnpBanner = document.getElementById('billingVnpayResultBanner');
        if (vnpayBannerInfo && vnpayBannerInfo.paymentUrl) {
            vnpBanner.classList.remove('d-none');
            vnpBanner.innerHTML = `
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <strong>Đã tạo hóa đơn VNPAY #${vnpayBannerInfo.maHoaDon}</strong> (${formatMoney(vnpayBannerInfo.soTien)}). Chuyển hướng hoặc gửi liên kết cho bệnh nhân thanh toán:
                    </div>
                    <div class="d-flex gap-2">
                        <a href="${escapeHtml(vnpayBannerInfo.paymentUrl)}" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm">
                            <i class="fas fa-external-link-alt me-1" aria-hidden="true"></i>Mở cổng VNPAY
                        </a>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btnReloadDetailAfterVnpay">
                            <i class="fas fa-sync-alt me-1" aria-hidden="true"></i>Cập nhật trạng thái
                        </button>
                    </div>
                </div>
            `;
            document.getElementById('btnReloadDetailAfterVnpay')?.addEventListener('click', () => reloadEncounterDetail(detail.maLichKham));
        } else {
            vnpBanner.classList.add('d-none');
            vnpBanner.innerHTML = '';
        }

        const items = Array.isArray(detail.items) ? detail.items : [];
        const tbody = document.getElementById('billingItemsBody');
        tbody.innerHTML = items.map((it) => {
            const p = it.paymentInfo;
            let payStatusHtml = '<span class="badge bg-warning text-dark">Chưa thanh toán</span>';
            if (it.isPaid && p) {
                const txnText = p.vnp_TransactionNo ? ` • GD: ${escapeHtml(p.vnp_TransactionNo)}` : '';
                payStatusHtml = `<span class="badge bg-success">Đã thu (${escapeHtml(p.phuongThuc)} • HĐ #${p.maHoaDon}${txnText})</span>`;
            }

            return `
                <tr class="${it.isPaid ? 'table-light' : ''}">
                    <td class="text-center">
                        <input type="checkbox"
                            class="form-check-input billing-item-checkbox"
                            data-item-key="${escapeHtml(it.itemKey)}"
                            data-amount="${it.thanhTien}"
                            ${it.isPaid ? 'disabled' : 'checked'}
                            aria-label="Chọn thanh toán ${escapeHtml(it.moTa)}">
                    </td>
                    <td><span class="badge bg-light text-dark border">${escapeHtml(it.nhomLabel)}</span></td>
                    <td>
                        <div class="fw-semibold text-dark">${escapeHtml(it.moTa)}</div>
                        <div class="small text-secondary">Trạng thái nguồn: ${escapeHtml(it.sourceStatus || '—')}</div>
                    </td>
                    <td class="text-center tabular-nums">${it.soLuong}</td>
                    <td class="text-end tabular-nums">${formatMoney(it.donGia)}</td>
                    <td class="text-end fw-semibold tabular-nums">${formatMoney(it.thanhTien)}</td>
                    <td>${payStatusHtml}</td>
                </tr>
            `;
        }).join('');

        tbody.querySelectorAll('.billing-item-checkbox:not(:disabled)').forEach((cb) => {
            cb.addEventListener('change', updateSelectedTotals);
        });
        updateSelectedTotals();

        const hasUnpaid = Number(detail.totals?.unpaidItemCount || 0) > 0;
        document.getElementById('billingSelectionActions').classList.toggle('d-none', !hasUnpaid);
        document.getElementById('billingCheckoutFormSection').classList.toggle('d-none', !hasUnpaid);
        document.getElementById('btnRequestCollectPayment').classList.toggle('d-none', !hasUnpaid);

        const excluded = Array.isArray(detail.excludedItems) ? detail.excludedItems : [];
        const exSection = document.getElementById('billingExcludedSection');
        if (excluded.length > 0) {
            exSection.classList.remove('d-none');
            document.getElementById('billingExcludedList').innerHTML = excluded.map((ex) =>
                `<li class="tabular-nums"><strong>${escapeHtml(ex.moTa)}</strong> — ${escapeHtml(ex.lyDo)} (không tính ${formatMoney(ex.soTienKhongTinh)})</li>`
            ).join('');
        } else {
            exSection.classList.add('d-none');
        }

        const invoices = Array.isArray(detail.invoices) ? detail.invoices : [];
        const recSection = document.getElementById('billingReceiptsSection');
        if (invoices.length > 0) {
            recSection.classList.remove('d-none');
            document.getElementById('billingReceiptsList').innerHTML = invoices.map((inv) => `
                <div class="receipt-card">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div class="fw-semibold tabular-nums">
                            <span class="badge bg-primary me-1">HĐ #${inv.maHoaDon}</span>
                            <span class="badge ${inv.trangThai === 'Đã thanh toán' ? 'bg-success' : 'bg-warning text-dark'} me-1">${escapeHtml(inv.trangThai)}</span>
                            <span class="badge bg-light text-dark border">${escapeHtml(inv.phuongThuc)}</span>
                            ${inv.vnp_TransactionNo ? `<span class="badge bg-info text-dark ms-1">VNPAY: ${escapeHtml(inv.vnp_TransactionNo)}</span>` : ''}
                        </div>
                        <div class="small text-secondary tabular-nums">
                            <strong class="text-dark">${formatMoney(inv.soTien)}</strong>
                            ${inv.tenThuNgan ? ` • Thu ngân: ${escapeHtml(inv.tenThuNgan)}` : ''}
                            • ${escapeHtml(formatDateTime(inv.thoiGianThanhToan || inv.ngayTao))}
                        </div>
                    </div>
                    ${inv.ghiChu ? `<div class="small text-secondary mt-1">Ghi chú: ${escapeHtml(inv.ghiChu)}</div>` : ''}
                </div>
            `).join('');
        } else {
            recSection.classList.add('d-none');
        }
    }

    async function reloadEncounterDetail(maLichKham, vnpayBannerInfo = null) {
        const res = await fetch(`${API_BASE_STAFF}/get-invoice-detail.php?maLichKham=${maLichKham}`, {
            credentials: 'include',
        });
        const payload = await res.json().catch(() => ({}));
        if (!res.ok || !payload.success || !payload.data) {
            throw new Error(payload.message || 'Không thể tải chi tiết hóa đơn.');
        }
        renderEncounterModal(payload.data, vnpayBannerInfo);
    }

    async function openBillingModal(maLichKham) {
        state.clientRequestId = generateRequestId(maLichKham);
        state.pendingPayload = null;
        document.getElementById('inputBillingNote').value = '';
        document.getElementById('selectPaymentMethod').value = 'TienMat';

        try {
            await reloadEncounterDetail(maLichKham);
            if (!billingModal) {
                billingModal = new bootstrap.Modal(document.getElementById('billingModal'));
            }
            billingModal.show();
        } catch (err) {
            showAlert(err.message || 'Không thể mở chi tiết hóa đơn.', 'danger');
        }
    }

    function openConfirmPayment() {
        const detail = state.selectedEncounter;
        if (!detail) return;

        const errBox = document.getElementById('billingGeneralError');
        errBox.classList.add('d-none');

        const selectedItems = [];
        let total = 0;
        const checkedBoxes = document.querySelectorAll('#billingItemsBody .billing-item-checkbox:not(:disabled):checked');
        const mapByKey = new Map((detail.items || []).map((i) => [i.itemKey, i]));

        checkedBoxes.forEach((cb) => {
            const key = cb.getAttribute('data-item-key');
            const obj = mapByKey.get(key);
            if (obj && !obj.isPaid) {
                selectedItems.push(obj);
                total += Number(obj.thanhTien || 0);
            }
        });

        if (selectedItems.length === 0) {
            errBox.textContent = 'Vui lòng chọn ít nhất một khoản mục chưa thanh toán.';
            errBox.classList.remove('d-none');
            return;
        }

        const method = document.getElementById('selectPaymentMethod')?.value || 'TienMat';
        const note = document.getElementById('inputBillingNote')?.value.trim() || '';

        state.pendingPayload = {
            maLichKham: detail.maLichKham,
            itemKeys: selectedItems.map((i) => i.itemKey),
            phuongThuc: method,
            clientRequestId: state.clientRequestId || generateRequestId(detail.maLichKham),
            ghiChu: note,
        };
        state.clientRequestId = state.pendingPayload.clientRequestId;

        document.getElementById('confirmPayBody').innerHTML = `
            <p class="mb-2">Xác nhận thu tiền cho lượt khám <strong>#LK-${detail.maLichKham}</strong> (${escapeHtml(detail.tenBenhNhan)}):</p>
            <ul class="small mb-3">
                ${selectedItems.map((i) => `<li class="tabular-nums">${escapeHtml(i.moTa)}: <strong>${formatMoney(i.thanhTien)}</strong></li>`).join('')}
            </ul>
            <div class="p-2 rounded bg-light border d-flex justify-content-between align-items-center">
                <span>Phương thức: <strong>${method === 'VNPAY' ? 'Cổng VNPAY' : 'Tiền mặt'}</strong></span>
                <span class="fw-bold text-primary tabular-nums fs-6">${formatMoney(total)}</span>
            </div>
        `;

        if (!confirmModal) {
            confirmModal = new bootstrap.Modal(document.getElementById('confirmPayModal'));
        }
        confirmModal.show();
    }

    async function executePayment() {
        if (state.isSubmitting || !state.pendingPayload) return;
        state.isSubmitting = true;

        const btn = document.getElementById('btnConfirmPayExecute');
        const oldHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang xử lý...';

        try {
            const res = await fetch(`${API_BASE_STAFF}/pay-invoice.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify(state.pendingPayload),
            });
            const payload = await res.json().catch(() => ({}));
            if (!res.ok || !payload.success) {
                throw new Error(payload.message || `Thanh toán thất bại (${res.status})`);
            }

            confirmModal?.hide();
            state.clientRequestId = null;
            state.pendingPayload = null;

            const result = payload.data || {};
            if (result.summary) {
                renderEncounterModal(
                    result.summary,
                    result.paymentUrl ? { maHoaDon: result.maHoaDon, soTien: result.soTien, paymentUrl: result.paymentUrl } : null
                );
            }

            showAlert(payload.message || 'Thanh toán thành công.', 'success');
            await loadQueue();
        } catch (err) {
            confirmModal?.hide();
            const errBox = document.getElementById('billingGeneralError');
            if (errBox) {
                errBox.textContent = err.message || 'Không thể xử lý thanh toán.';
                errBox.classList.remove('d-none');
            }
        } finally {
            state.isSubmitting = false;
            btn.disabled = false;
            btn.innerHTML = oldHtml;
        }
    }

    async function openPrintInvoice(maLichKham) {
        try {
            let detail = state.selectedEncounter && Number(state.selectedEncounter.maLichKham) === Number(maLichKham)
                ? state.selectedEncounter
                : null;
            if (!detail) {
                const res = await fetch(`${API_BASE_STAFF}/get-invoice-detail.php?maLichKham=${maLichKham}`, {
                    credentials: 'include',
                });
                const payload = await res.json().catch(() => ({}));
                if (!res.ok || !payload.success || !payload.data) {
                    throw new Error(payload.message || 'Không thể tải dữ liệu in hóa đơn.');
                }
                detail = payload.data;
            }

            const g = detail.groups || {};
            const t = detail.totals || {};

            document.getElementById('printInvoiceContent').innerHTML = `
                <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                    <div>
                        <h5 class="fw-bold text-primary mb-1">PHÒNG KHÁM ĐA KHOA EDEN HEALTH</h5>
                        <div class="small text-secondary">Bảng kê & Hóa đơn tổng hợp chi phí khám chữa bệnh</div>
                    </div>
                    <div class="text-end small tabular-nums">
                        <div><strong>Lượt khám:</strong> #LK-${detail.maLichKham}</div>
                        <div><strong>Ngày khám:</strong> ${escapeHtml(formatDate(detail.ngayKham))}</div>
                        <div><strong>Trạng thái:</strong> ${escapeHtml(detail.billingStatus)}</div>
                    </div>
                </div>
                <div class="row g-2 small mb-3">
                    <div class="col-sm-6"><strong>Bệnh nhân:</strong> ${escapeHtml(detail.tenBenhNhan)} (${escapeHtml(detail.maBenhNhan)})</div>
                    <div class="col-sm-6 tabular-nums"><strong>SĐT:</strong> ${escapeHtml(detail.soDienThoai || '—')} ${detail.soTheBHYT ? `• BHYT: ${escapeHtml(detail.soTheBHYT)}` : ''}</div>
                    <div class="col-sm-6"><strong>Bác sĩ:</strong> ${escapeHtml(detail.tenBacSi)} (${escapeHtml(detail.tenChuyenKhoa)})</div>
                    <div class="col-sm-6"><strong>Chẩn đoán:</strong> ${escapeHtml(detail.chanDoan || '—')}</div>
                </div>
                <table class="table table-bordered table-sm small mb-3">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Nhóm</th>
                            <th>Khoản mục</th>
                            <th class="text-center">SL</th>
                            <th class="text-end">Đơn giá</th>
                            <th class="text-end">Thành tiền</th>
                            <th>Thanh toán</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${(detail.items || []).map((it, idx) => `
                            <tr>
                                <td class="tabular-nums">${idx + 1}</td>
                                <td>${escapeHtml(it.nhomLabel)}</td>
                                <td>${escapeHtml(it.moTa)}</td>
                                <td class="text-center tabular-nums">${it.soLuong}</td>
                                <td class="text-end tabular-nums">${formatMoney(it.donGia)}</td>
                                <td class="text-end fw-semibold tabular-nums">${formatMoney(it.thanhTien)}</td>
                                <td>${it.isPaid ? `Đã thu (HĐ #${it.paymentInfo?.maHoaDon || ''} • ${escapeHtml(it.paymentInfo?.phuongThuc || '')})` : 'Chưa thu'}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
                <div class="row g-2 small border-top pt-2 mb-3 tabular-nums">
                    <div class="col-sm-4"><strong>Phí khám:</strong> ${formatMoney(g.kham?.total)}</div>
                    <div class="col-sm-4"><strong>Cận lâm sàng:</strong> ${formatMoney(g.cls?.total)}</div>
                    <div class="col-sm-4"><strong>Thuốc đã phát:</strong> ${formatMoney(g.thuoc?.total)}</div>
                    <div class="col-sm-4 fw-bold">Tổng chi phí: ${formatMoney(t.tongCong)}</div>
                    <div class="col-sm-4 fw-bold text-success">Đã thanh toán: ${formatMoney(t.daThanhToan)}</div>
                    <div class="col-sm-4 fw-bold text-danger">Còn phải thu: ${formatMoney(t.conPhaiThu)}</div>
                </div>
                <div class="d-flex justify-content-between small text-secondary pt-2 border-top">
                    <span>Cảm ơn quý khách đã sử dụng dịch vụ tại Eden Health.</span>
                    <span>Thu ngân: <strong>${escapeHtml(window.STAFF_INFO?.tenNhanVien || 'Quầy Thu Ngân')}</strong></span>
                </div>
            `;

            if (!printModal) {
                printModal = new bootstrap.Modal(document.getElementById('printInvoiceModal'));
            }
            printModal.show();
        } catch (err) {
            showAlert(err.message || 'Không thể mở bản in hóa đơn.', 'danger');
        }
    }

    function bindEvents() {
        document.getElementById('tabUnpaidBillingBtn')?.addEventListener('click', () => {
            state.tab = 'unpaid';
            syncTabUI();
            loadQueue();
        });
        document.getElementById('tabPaidBillingBtn')?.addEventListener('click', () => {
            state.tab = 'paid';
            syncTabUI();
            loadQueue();
        });
        document.getElementById('tabAllBillingBtn')?.addEventListener('click', () => {
            state.tab = 'all';
            syncTabUI();
            loadQueue();
        });

        document.querySelectorAll('#billingSummaryBar .summary-chip[data-tab-target]').forEach((chip) => {
            const handler = () => {
                state.tab = chip.getAttribute('data-tab-target') || 'unpaid';
                syncTabUI();
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

        document.getElementById('billingSearchInput')?.addEventListener('input', () => {
            clearTimeout(state.searchTimer);
            state.searchTimer = setTimeout(loadQueue, 250);
        });
        document.getElementById('billingDateInput')?.addEventListener('change', loadQueue);
        document.getElementById('btnRefreshBilling')?.addEventListener('click', loadQueue);
        document.getElementById('btnRetryBilling')?.addEventListener('click', loadQueue);

        document.getElementById('billingTableBody')?.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-action]');
            if (!btn) return;
            const action = btn.getAttribute('data-action');
            const lkId = Number(btn.getAttribute('data-lk-id'));
            if (action === 'open-billing') openBillingModal(lkId);
            if (action === 'print-invoice') openPrintInvoice(lkId);
        });

        document.getElementById('btnSelectAllUnpaid')?.addEventListener('click', () => {
            document.querySelectorAll('#billingItemsBody .billing-item-checkbox:not(:disabled)').forEach((cb) => {
                cb.checked = true;
            });
            updateSelectedTotals();
        });
        document.getElementById('btnUnselectAll')?.addEventListener('click', () => {
            document.querySelectorAll('#billingItemsBody .billing-item-checkbox:not(:disabled)').forEach((cb) => {
                cb.checked = false;
            });
            updateSelectedTotals();
        });

        document.getElementById('btnRequestCollectPayment')?.addEventListener('click', openConfirmPayment);
        document.getElementById('btnConfirmPayExecute')?.addEventListener('click', executePayment);
        document.getElementById('btnOpenPrintInvoice')?.addEventListener('click', () => {
            if (state.selectedEncounter) openPrintInvoice(state.selectedEncounter.maLichKham);
        });
        document.getElementById('btnExecutePrintInvoice')?.addEventListener('click', () => window.print());
    }

    window.addEventListener('staff:rbac-ready', (e) => {
        state.hasAccess = Boolean(e.detail?.hasPageAccess);
        if (state.hasAccess) {
            window.AdminSummary?.initClearableFilters?.();
            loadQueue();
        }
    });

    document.addEventListener('DOMContentLoaded', bindEvents);
})();
