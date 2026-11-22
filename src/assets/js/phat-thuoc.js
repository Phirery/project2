(function () {
    'use strict';

    const state = {
        tab: 'pending',
        items: [],
        hasAccess: false,
        searchTimer: null,
        selectedItem: null,
        pendingPayload: null,
        clientRequestId: null,
        isSubmitting: false,
    };

    let dispenseModal = null;
    let confirmModal = null;
    let cancelModal = null;
    let printModal = null;

    function formatMoney(amount) {
        const num = Number(amount || 0);
        return `${Math.round(num).toLocaleString('vi-VN')} đ`;
    }

    function generateRequestId(maDonThuoc) {
        const rand = Math.random().toString(36).slice(2, 10);
        return `rx-${maDonThuoc}-${Date.now()}-${rand}`;
    }

    function statusBadgeHtml(status) {
        switch (status) {
            case 'Chờ phát':
                return '<span class="status-pill is-waiting"><i class="fas fa-clock" aria-hidden="true"></i>Chờ phát</span>';
            case 'Đã phát một phần':
                return '<span class="status-pill is-partial"><i class="fas fa-hourglass-half" aria-hidden="true"></i>Đã phát một phần</span>';
            case 'Đã phát đủ':
                return '<span class="status-pill is-done"><i class="fas fa-check-circle" aria-hidden="true"></i>Đã phát đủ</span>';
            case 'Hủy':
                return '<span class="status-pill is-cancelled"><i class="fas fa-ban" aria-hidden="true"></i>Hủy</span>';
            default:
                return `<span class="status-pill is-waiting">${escapeHtml(status || '—')}</span>`;
        }
    }

    function setViewState(viewName, errorMessage = '') {
        const loading = document.getElementById('pharmacyLoadingState');
        const err = document.getElementById('pharmacyErrorState');
        const empty = document.getElementById('pharmacyEmptyState');
        const table = document.getElementById('pharmacyTableWrapper');
        if (!loading || !err || !empty || !table) return;

        loading.classList.toggle('d-none', viewName !== 'loading');
        err.classList.toggle('d-none', viewName !== 'error');
        empty.classList.toggle('d-none', viewName !== 'empty');
        table.classList.toggle('d-none', viewName !== 'data');

        if (viewName === 'error' && errorMessage) {
            const msgEl = document.getElementById('pharmacyErrorMessage');
            if (msgEl) msgEl.textContent = errorMessage;
        }
    }

    function syncTabUI() {
        const tabs = [
            { id: 'tabPendingRxBtn', key: 'pending' },
            { id: 'tabCompletedRxBtn', key: 'completed' },
            { id: 'tabCancelledRxBtn', key: 'cancelled' },
        ];
        tabs.forEach(({ id, key }) => {
            const btn = document.getElementById(id);
            if (!btn) return;
            const active = state.tab === key;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        document.querySelectorAll('#pharmacySummaryBar .summary-chip[data-tab-target]').forEach((chip) => {
            chip.classList.toggle('is-active', chip.getAttribute('data-tab-target') === state.tab);
        });
    }

    function renderStats(stats) {
        const setNum = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = String(val ?? 0);
        };
        setNum('statWaitingCount', stats.choPhat);
        setNum('statPartialCount', stats.phatMotPhan);
        setNum('statCompletedTodayCount', stats.daPhatDuHomNay);
        setNum('statCancelledTodayCount', stats.daHuyHomNay);
        setNum('statLowStockCount', stats.thuocSapHet);
    }

    function renderTable() {
        const tbody = document.getElementById('pharmacyTableBody');
        if (!tbody) return;

        if (!state.items.length) {
            const emptyTitle = document.getElementById('pharmacyEmptyTitle');
            const emptyDesc = document.getElementById('pharmacyEmptyDesc');
            if (state.tab === 'pending') {
                if (emptyTitle) emptyTitle.textContent = 'Không có đơn thuốc chờ phát';
                if (emptyDesc) emptyDesc.textContent = 'Tất cả đơn thuốc đang chờ đã được phát hoặc chưa có đơn mới.';
            } else if (state.tab === 'completed') {
                if (emptyTitle) emptyTitle.textContent = 'Chưa có đơn thuốc đã phát đủ';
                if (emptyDesc) emptyDesc.textContent = 'Không tìm thấy đơn thuốc đã hoàn thành phát ở bộ lọc hiện tại.';
            } else {
                if (emptyTitle) emptyTitle.textContent = 'Không có đơn thuốc đã hủy';
                if (emptyDesc) emptyDesc.textContent = 'Chưa có đơn thuốc nào bị hủy phát ở bộ lọc hiện tại.';
            }
            setViewState('empty');
            return;
        }

        tbody.innerHTML = state.items.map((item) => {
            const totalKe = Number(item.tongSoLuongKe || 0);
            const totalPhat = Number(item.tongSoLuongDaPhat || 0);
            const pct = totalKe > 0 ? Math.min(100, Math.round((totalPhat / totalKe) * 100)) : 0;

            let stockAlertHtml = '<span class="stock-badge is-ok"><i class="fas fa-check" aria-hidden="true"></i>Đủ tồn kho</span>';
            if (item.trangThaiPhat === 'Đã phát đủ') {
                stockAlertHtml = '<span class="text-secondary small">Đã hoàn tất</span>';
            } else if (item.trangThaiPhat === 'Hủy') {
                stockAlertHtml = `<span class="text-danger small">${escapeHtml(truncateText(item.lyDoHuy || 'Đã hủy', 42))}</span>`;
            } else if (item.hasShortage) {
                stockAlertHtml = '<span class="stock-badge is-out"><i class="fas fa-exclamation-circle" aria-hidden="true"></i>Thiếu tồn kho</span>';
            } else if ((item.chiTietThuoc || []).some((m) => m.isLowStock)) {
                stockAlertHtml = '<span class="stock-badge is-low"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i>Sắp hết tồn</span>';
            }

            const canDispense = item.trangThaiPhat === 'Chờ phát' || item.trangThaiPhat === 'Đã phát một phần';
            const canCancel = item.trangThaiPhat === 'Chờ phát' && totalPhat === 0;

            const patientMeta = [
                item.maBenhNhan ? `Mã: ${escapeHtml(item.maBenhNhan)}` : '',
                item.soDienThoai ? `SĐT: ${escapeHtml(item.soDienThoai)}` : '',
            ].filter(Boolean).join(' • ');

            const primaryBtnLabel = canDispense
                ? (item.trangThaiPhat === 'Đã phát một phần' ? 'Phát tiếp' : 'Phát thuốc')
                : 'Chi tiết';
            const primaryBtnClass = canDispense ? 'btn-pharmacy-primary' : 'btn-outline-secondary';

            return `
                <tr>
                    <td>
                        <div class="rx-code-badge">#${item.maDonThuoc}</div>
                        <div class="small text-secondary mt-1 tabular-nums">${escapeHtml(formatDateTime(item.ngayKeDon))}</div>
                    </td>
                    <td>
                        <div class="fw-semibold text-dark">${escapeHtml(item.tenBenhNhan)}</div>
                        <div class="small text-secondary tabular-nums">${patientMeta || '—'}</div>
                        ${item.soTheBHYT ? `<div class="small text-primary tabular-nums">BHYT: ${escapeHtml(item.soTheBHYT)}</div>` : ''}
                    </td>
                    <td>
                        <div class="fw-semibold">${escapeHtml(item.tenBacSi || 'Bác sĩ')} <span class="text-secondary fw-normal">(${escapeHtml(item.tenChuyenKhoa || '')})</span></div>
                        <div class="small text-secondary">${escapeHtml(truncateText(item.chuanDoan || 'Chưa ghi chẩn đoán', 64))}</div>
                    </td>
                    <td>
                        <div class="fw-semibold tabular-nums mb-1">${totalPhat} / ${totalKe} <span class="text-secondary small">(${item.chiTietThuoc.length} loại)</span></div>
                        <div class="progress" style="height:6px;min-width:110px;">
                            <div class="progress-bar ${pct >= 100 ? 'bg-success' : 'bg-primary'}" style="width:${pct}%"></div>
                        </div>
                    </td>
                    <td>${stockAlertHtml}</td>
                    <td>${statusBadgeHtml(item.trangThaiPhat)}</td>
                    <td class="text-end">
                        <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                            <button type="button" class="btn ${primaryBtnClass} btn-sm" data-action="open-dispense" data-rx-id="${item.maDonThuoc}">
                                <i class="fas ${canDispense ? 'fa-pills' : 'fa-eye'} me-1" aria-hidden="true"></i>${primaryBtnLabel}
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-action="print-guide" data-rx-id="${item.maDonThuoc}" aria-label="In hướng dẫn sử dụng đơn #${item.maDonThuoc}">
                                <i class="fas fa-print" aria-hidden="true"></i>
                            </button>
                            ${canCancel ? `
                                <button type="button" class="btn btn-outline-danger btn-sm" data-action="open-cancel" data-rx-id="${item.maDonThuoc}" aria-label="Hủy đơn #${item.maDonThuoc}">
                                    <i class="fas fa-ban" aria-hidden="true"></i>
                                </button>
                            ` : ''}
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

        const search = document.getElementById('pharmacySearchInput')?.value.trim() || '';
        const date = document.getElementById('pharmacyDateInput')?.value.trim() || '';
        const qs = new URLSearchParams({ tab: state.tab });
        if (search) qs.set('search', search);
        if (date) qs.set('date', date);

        try {
            const res = await fetch(`${API_BASE_STAFF}/get-dispensing-queue.php?${qs.toString()}`, {
                credentials: 'include',
            });
            const payload = await res.json().catch(() => ({}));
            if (!res.ok || !payload.success || !payload.data) {
                throw new Error(payload.message || `Lỗi máy chủ (${res.status})`);
            }

            const data = payload.data;
            state.items = Array.isArray(data.items) ? data.items : [];
            renderStats(data.stats || {});
            renderTable();
        } catch (err) {
            setViewState('error', err.message || 'Không thể kết nối máy chủ.');
        }
    }

    function updateDispenseSummaryTotals() {
        const inputs = document.querySelectorAll('#dispenseItemsBody .dispense-qty-input');
        let totalQty = 0;
        let totalMoney = 0;
        inputs.forEach((input) => {
            const max = Number(input.getAttribute('max') || 0);
            let val = parseInt(input.value, 10);
            if (Number.isNaN(val) || val < 0) val = 0;
            if (val > max) val = max;
            input.value = String(val);

            const price = Number(input.getAttribute('data-price') || 0);
            const rowTotal = val * price;
            totalQty += val;
            totalMoney += rowTotal;

            const cell = input.closest('tr')?.querySelector('.dispense-line-total');
            if (cell) cell.textContent = formatMoney(rowTotal);
        });

        const qtyEl = document.getElementById('dispenseRoundQtyTotal');
        const moneyEl = document.getElementById('dispenseRoundMoneyTotal');
        if (qtyEl) qtyEl.textContent = String(totalQty);
        if (moneyEl) moneyEl.textContent = formatMoney(totalMoney);
    }

    function buildDefaultGuide(item) {
        const lines = (item.chiTietThuoc || [])
            .map((m, idx) => {
                const guide = (m.lieuDung || m.cachDungMacDinh || '').trim();
                return guide ? `${idx + 1}. ${m.tenThuoc}: ${guide}` : '';
            })
            .filter(Boolean);
        if (item.loiDanBacSi) {
            lines.push(`Lời dặn bác sĩ: ${item.loiDanBacSi}`);
        }
        return lines.join('\n');
    }

    function openDispenseModal(rxId) {
        const item = state.items.find((x) => Number(x.maDonThuoc) === Number(rxId));
        if (!item) return;

        state.selectedItem = item;
        state.pendingPayload = null;
        state.clientRequestId = generateRequestId(item.maDonThuoc);

        const canDispense = item.trangThaiPhat === 'Chờ phát' || item.trangThaiPhat === 'Đã phát một phần';
        const canCancel = item.trangThaiPhat === 'Chờ phát' && Number(item.tongSoLuongDaPhat || 0) === 0;

        document.getElementById('dispenseModalTitle').innerHTML =
            `<i class="fas fa-prescription-bottle-alt me-2" aria-hidden="true"></i>Đơn thuốc #${item.maDonThuoc} — ${escapeHtml(item.tenBenhNhan)}`;

        document.getElementById('dispensePatientSummary').innerHTML = `
            <div class="row g-2 small">
                <div class="col-md-4">
                    <div><span class="text-secondary">Bệnh nhân:</span> <strong>${escapeHtml(item.tenBenhNhan)}</strong> (${escapeHtml(item.maBenhNhan || '—')})</div>
                    <div class="tabular-nums"><span class="text-secondary">SĐT:</span> ${escapeHtml(item.soDienThoai || '—')} ${item.soTheBHYT ? `• BHYT: ${escapeHtml(item.soTheBHYT)}` : ''}</div>
                </div>
                <div class="col-md-4">
                    <div><span class="text-secondary">Bác sĩ kê:</span> <strong>${escapeHtml(item.tenBacSi || '—')}</strong> (${escapeHtml(item.tenChuyenKhoa || '')})</div>
                    <div class="tabular-nums"><span class="text-secondary">Ngày kê:</span> ${escapeHtml(formatDateTime(item.ngayKeDon))}</div>
                </div>
                <div class="col-md-4">
                    <div><span class="text-secondary">Trạng thái:</span> ${statusBadgeHtml(item.trangThaiPhat)}</div>
                    <div class="mt-1"><span class="text-secondary">Chẩn đoán:</span> ${escapeHtml(item.chuanDoan || '—')}</div>
                </div>
                ${item.loiDanBacSi ? `
                    <div class="col-12 pt-1 border-top">
                        <span class="text-secondary fw-semibold">Lời dặn bác sĩ:</span> ${escapeHtml(item.loiDanBacSi)}
                    </div>
                ` : ''}
                ${item.trangThaiPhat === 'Hủy' ? `
                    <div class="col-12 pt-1 border-top text-danger">
                        <strong>Lý do hủy:</strong> ${escapeHtml(item.lyDoHuy || '—')} (${escapeHtml(item.tenNhanVienHuy || '')} • ${escapeHtml(formatDateTime(item.cancelledAt))})
                    </div>
                ` : ''}
            </div>
        `;

        const errBox = document.getElementById('dispenseGeneralError');
        errBox.classList.add('d-none');
        errBox.textContent = '';

        const warnBanner = document.getElementById('dispenseStockWarningBanner');
        if (canDispense && item.hasShortage) {
            warnBanner.classList.remove('d-none');
            warnBanner.innerHTML = '<i class="fas fa-exclamation-triangle me-2" aria-hidden="true"></i><strong>Cảnh báo tồn kho:</strong> Có thuốc không đủ tồn kho cho phần còn lại. Bạn vẫn có thể phát tối đa theo số tồn hiện tại (hệ thống chuyển trạng thái <em>Đã phát một phần</em>).';
        } else {
            warnBanner.classList.add('d-none');
        }

        const tbody = document.getElementById('dispenseItemsBody');
        tbody.innerHTML = (item.chiTietThuoc || []).map((med) => {
            const defaultQty = canDispense ? Math.max(0, Math.min(med.soLuongConLai, med.soLuongTon)) : 0;
            const maxAllowed = Math.max(0, Math.min(med.soLuongConLai, med.soLuongTon));
            let stockBadge = `<span class="stock-badge is-ok">${med.soLuongTon} ${escapeHtml(med.donViTinh)}</span>`;
            if (med.soLuongTon <= 0) {
                stockBadge = `<span class="stock-badge is-out">Hết kho (0)</span>`;
            } else if (med.soLuongConLai > med.soLuongTon) {
                stockBadge = `<span class="stock-badge is-out">Tồn ${med.soLuongTon} &lt; cần ${med.soLuongConLai}</span>`;
            } else if (med.isLowStock) {
                stockBadge = `<span class="stock-badge is-low">Sắp hết (${med.soLuongTon})</span>`;
            }

            return `
                <tr>
                    <td>
                        <div class="fw-semibold text-dark">${escapeHtml(med.tenThuoc)}</div>
                        <div class="small text-secondary">${escapeHtml(med.lieuDung || med.cachDungMacDinh || 'Theo hướng dẫn bác sĩ')}</div>
                        ${med.hanSuDung ? `<div class="small text-secondary tabular-nums">HSD: ${escapeHtml(formatDate(med.hanSuDung))} • Giá: ${formatMoney(med.giaTien)}/${escapeHtml(med.donViTinh)}</div>` : ''}
                    </td>
                    <td class="text-center tabular-nums fw-semibold">${med.soLuongKe}</td>
                    <td class="text-center tabular-nums text-success fw-semibold">${med.soLuongDaPhat}</td>
                    <td class="text-center tabular-nums fw-bold text-primary">${med.soLuongConLai}</td>
                    <td class="text-center">${stockBadge}</td>
                    <td class="text-end">
                        ${canDispense && med.soLuongConLai > 0 ? `
                            <input type="number"
                                class="form-control form-control-sm ms-auto dispense-qty-input"
                                data-detail-id="${med.chiTietDonThuocId}"
                                data-med-name="${escapeHtml(med.tenThuoc)}"
                                data-price="${med.giaTien}"
                                min="0"
                                max="${maxAllowed}"
                                value="${defaultQty}"
                                ${maxAllowed === 0 ? 'disabled' : ''}
                                aria-label="Số lượng phát ${escapeHtml(med.tenThuoc)}">
                        ` : '<span class="text-secondary small tabular-nums">—</span>'}
                    </td>
                    <td class="text-end fw-semibold tabular-nums dispense-line-total">0 đ</td>
                </tr>
            `;
        }).join('');

        tbody.querySelectorAll('.dispense-qty-input').forEach((inp) => {
            inp.addEventListener('input', updateDispenseSummaryTotals);
        });
        updateDispenseSummaryTotals();

        document.getElementById('dispenseQuickFillGroup').classList.toggle('d-none', !canDispense);
        document.getElementById('dispenseInputFieldsSection').classList.toggle('d-none', !canDispense);
        document.getElementById('btnSubmitDispense').classList.toggle('d-none', !canDispense);
        document.getElementById('btnOpenCancelFromModal').classList.toggle('d-none', !canCancel);

        const guideEl = document.getElementById('inputHuongDanSuDung');
        const noteEl = document.getElementById('inputGhiChuPhat');
        if (guideEl) guideEl.value = buildDefaultGuide(item);
        if (noteEl) noteEl.value = '';

        const histSection = document.getElementById('dispenseHistorySection');
        const histList = document.getElementById('dispenseHistoryList');
        const history = Array.isArray(item.lichSuPhat) ? item.lichSuPhat : [];
        if (history.length > 0) {
            histSection.classList.remove('d-none');
            histList.innerHTML = history.map((v) => `
                <div class="voucher-history-card">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                        <div class="fw-semibold tabular-nums">
                            <span class="badge bg-primary me-1">${escapeHtml(v.maPhieuCode)}</span>
                            <span class="badge ${v.loaiPhat === 'Đủ' ? 'bg-success' : 'bg-info text-dark'}">${escapeHtml(v.loaiPhat)}</span>
                        </div>
                        <div class="small text-secondary tabular-nums">
                            Dược sĩ: <strong>${escapeHtml(v.tenDuocSi || v.maDuocSi)}</strong> • ${escapeHtml(formatDateTime(v.thoiGianPhat))} • <strong>${formatMoney(v.tongTienPhat)}</strong>
                        </div>
                    </div>
                    <ul class="small mb-0 ps-3">
                        ${(v.chiTiet || []).map((c) => `
                            <li class="tabular-nums">${escapeHtml(c.tenThuoc)}: phát <strong>${c.soLuongThucPhat}</strong> ${escapeHtml(c.donViTinh)} (tồn ${c.tonTruocPhat} → ${c.tonSauPhat})</li>
                        `).join('')}
                    </ul>
                    ${v.ghiChu ? `<div class="small text-secondary mt-1">Ghi chú: ${escapeHtml(v.ghiChu)}</div>` : ''}
                </div>
            `).join('');
        } else {
            histSection.classList.add('d-none');
            histList.innerHTML = '';
        }

        if (!dispenseModal) {
            dispenseModal = new bootstrap.Modal(document.getElementById('dispenseModal'));
        }
        dispenseModal.show();
    }

    function openConfirmDispense() {
        const item = state.selectedItem;
        if (!item) return;

        const errBox = document.getElementById('dispenseGeneralError');
        errBox.classList.add('d-none');

        const selectedLines = [];
        let totalQty = 0;
        let totalMoney = 0;
        let willRemain = false;

        const inputs = Array.from(document.querySelectorAll('#dispenseItemsBody .dispense-qty-input'));
        const qtyMap = new Map();
        inputs.forEach((inp) => {
            const detailId = Number(inp.getAttribute('data-detail-id'));
            const val = parseInt(inp.value, 10) || 0;
            qtyMap.set(detailId, val);
        });

        for (const med of item.chiTietThuoc || []) {
            const qty = qtyMap.get(med.chiTietDonThuocId) || 0;
            if (qty > med.soLuongConLai) {
                errBox.textContent = `${med.tenThuoc} vượt quá số lượng còn lại (${med.soLuongConLai}).`;
                errBox.classList.remove('d-none');
                return;
            }
            if (qty > med.soLuongTon) {
                errBox.textContent = `${med.tenThuoc} không đủ tồn kho (tồn hiện tại: ${med.soLuongTon}).`;
                errBox.classList.remove('d-none');
                return;
            }
            if (med.soLuongConLai - qty > 0) {
                willRemain = true;
            }
            if (qty > 0) {
                selectedLines.push({
                    chiTietDonThuocId: med.chiTietDonThuocId,
                    tenThuoc: med.tenThuoc,
                    donViTinh: med.donViTinh,
                    soLuongThucPhat: qty,
                    thanhTien: qty * Number(med.giaTien || 0),
                });
                totalQty += qty;
                totalMoney += qty * Number(med.giaTien || 0);
            }
        }

        if (selectedLines.length === 0) {
            errBox.textContent = 'Vui lòng nhập số lượng phát lớn hơn 0 cho ít nhất một thuốc.';
            errBox.classList.remove('d-none');
            return;
        }

        state.pendingPayload = {
            maDonThuoc: item.maDonThuoc,
            clientRequestId: state.clientRequestId || generateRequestId(item.maDonThuoc),
            huongDanSuDung: document.getElementById('inputHuongDanSuDung')?.value.trim() || '',
            ghiChu: document.getElementById('inputGhiChuPhat')?.value.trim() || '',
            items: selectedLines.map((l) => ({
                chiTietDonThuocId: l.chiTietDonThuocId,
                soLuongThucPhat: l.soLuongThucPhat,
            })),
        };
        state.clientRequestId = state.pendingPayload.clientRequestId;

        const nextLabel = willRemain ? 'Đã phát một phần' : 'Đã phát đủ';
        document.getElementById('confirmDispenseBody').innerHTML = `
            <p class="mb-2">Bạn chuẩn bị xuất kho và phát thuốc cho đơn <strong>#${item.maDonThuoc}</strong> (${escapeHtml(item.tenBenhNhan)}):</p>
            <ul class="small mb-3">
                ${selectedLines.map((l) => `<li class="tabular-nums">${escapeHtml(l.tenThuoc)}: <strong>${l.soLuongThucPhat} ${escapeHtml(l.donViTinh)}</strong> (${formatMoney(l.thanhTien)})</li>`).join('')}
            </ul>
            <div class="p-2 rounded bg-light border small d-flex justify-content-between align-items-center">
                <span>Tổng xuất kho đợt này: <strong class="tabular-nums">${totalQty} đơn vị • ${formatMoney(totalMoney)}</strong></span>
                <span class="badge ${willRemain ? 'bg-primary' : 'bg-success'}">${nextLabel}</span>
            </div>
        `;

        if (!confirmModal) {
            confirmModal = new bootstrap.Modal(document.getElementById('confirmDispenseModal'));
        }
        confirmModal.show();
    }

    async function executeDispense() {
        if (state.isSubmitting || !state.pendingPayload) return;
        state.isSubmitting = true;

        const execBtn = document.getElementById('btnConfirmDispenseExecute');
        const oldHtml = execBtn.innerHTML;
        execBtn.disabled = true;
        execBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang xuất kho...';

        try {
            const res = await fetch(`${API_BASE_STAFF}/dispense-prescription.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify(state.pendingPayload),
            });
            const payload = await res.json().catch(() => ({}));
            if (!res.ok || !payload.success) {
                throw new Error(payload.message || `Phát thuốc thất bại (${res.status})`);
            }

            confirmModal?.hide();
            dispenseModal?.hide();
            state.clientRequestId = null;
            state.pendingPayload = null;

            const resultData = payload.data || {};
            let toastMsg = payload.message || 'Phát thuốc thành công.';
            if (Array.isArray(resultData.lowStockMedicines) && resultData.lowStockMedicines.length > 0) {
                const names = resultData.lowStockMedicines.map((m) => `${m.tenThuoc} (còn ${m.soLuongTon})`).join(', ');
                toastMsg += ` Cảnh báo tồn kho thấp: ${names}.`;
            }
            showAlert(toastMsg, 'success');
            await loadQueue();
        } catch (err) {
            confirmModal?.hide();
            const errBox = document.getElementById('dispenseGeneralError');
            if (errBox) {
                errBox.textContent = err.message || 'Không thể phát thuốc.';
                errBox.classList.remove('d-none');
            }
        } finally {
            state.isSubmitting = false;
            execBtn.disabled = false;
            execBtn.innerHTML = oldHtml;
        }
    }

    function openCancelModal(rxId) {
        const item = state.items.find((x) => Number(x.maDonThuoc) === Number(rxId)) || state.selectedItem;
        if (!item) return;

        state.selectedItem = item;
        document.getElementById('cancelRxSummaryText').innerHTML =
            `Hủy phát đơn thuốc <strong>#${item.maDonThuoc}</strong> của bệnh nhân <strong>${escapeHtml(item.tenBenhNhan)}</strong>.`;
        document.getElementById('inputCancelReason').value = '';
        const errBox = document.getElementById('cancelRxErrorBox');
        errBox.classList.add('d-none');
        errBox.textContent = '';

        if (!cancelModal) {
            cancelModal = new bootstrap.Modal(document.getElementById('cancelRxModal'));
        }
        cancelModal.show();
    }

    async function executeCancelRx() {
        const item = state.selectedItem;
        if (!item) return;
        const reason = document.getElementById('inputCancelReason')?.value.trim() || '';
        const errBox = document.getElementById('cancelRxErrorBox');
        if (reason.length < 3) {
            errBox.textContent = 'Vui lòng nhập lý do hủy tối thiểu 3 ký tự.';
            errBox.classList.remove('d-none');
            return;
        }

        const btn = document.getElementById('btnConfirmCancelRx');
        const oldHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang hủy...';

        try {
            const res = await fetch(`${API_BASE_STAFF}/cancel-dispensing.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ maDonThuoc: item.maDonThuoc, lyDoHuy: reason }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                throw new Error(data.message || `Không thể hủy đơn (${res.status})`);
            }

            cancelModal?.hide();
            dispenseModal?.hide();
            showAlert(data.message || 'Đã hủy phát đơn thuốc.', 'success');
            await loadQueue();
        } catch (err) {
            errBox.textContent = err.message || 'Lỗi khi hủy đơn thuốc.';
            errBox.classList.remove('d-none');
        } finally {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
        }
    }

    function openPrintGuide(rxId) {
        const item = state.items.find((x) => Number(x.maDonThuoc) === Number(rxId)) || state.selectedItem;
        if (!item) return;

        const latestVoucher = Array.isArray(item.lichSuPhat) && item.lichSuPhat.length > 0 ? item.lichSuPhat[0] : null;
        const pharmacistGuide = latestVoucher?.huongDanSuDung || buildDefaultGuide(item);

        document.getElementById('printGuideContent').innerHTML = `
            <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                <div>
                    <h5 class="fw-bold mb-1 text-primary">PHÒNG KHÁM ĐA KHOA EDEN HEALTH</h5>
                    <div class="small text-secondary">Phiếu hướng dẫn sử dụng & cấp phát thuốc</div>
                </div>
                <div class="text-end small tabular-nums">
                    <div><strong>Mã đơn:</strong> #${item.maDonThuoc}</div>
                    <div><strong>Ngày kê:</strong> ${escapeHtml(formatDateTime(item.ngayKeDon))}</div>
                    ${latestVoucher ? `<div><strong>Mã phiếu phát:</strong> ${escapeHtml(latestVoucher.maPhieuCode)}</div>` : ''}
                </div>
            </div>
            <div class="row g-2 small mb-3">
                <div class="col-sm-6"><strong>Bệnh nhân:</strong> ${escapeHtml(item.tenBenhNhan)} (${escapeHtml(item.maBenhNhan || '—')})</div>
                <div class="col-sm-6 tabular-nums"><strong>SĐT:</strong> ${escapeHtml(item.soDienThoai || '—')}</div>
                <div class="col-sm-6"><strong>Bác sĩ kê:</strong> ${escapeHtml(item.tenBacSi || '—')}</div>
                <div class="col-sm-6"><strong>Trạng thái phát:</strong> ${escapeHtml(item.trangThaiPhat)}</div>
                <div class="col-12"><strong>Chẩn đoán:</strong> ${escapeHtml(item.chuanDoan || '—')}</div>
            </div>
            <table class="table table-bordered table-sm small mb-3">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Tên thuốc</th>
                        <th class="text-center">Kê</th>
                        <th class="text-center">Đã phát</th>
                        <th>Liều dùng & Cách dùng</th>
                    </tr>
                </thead>
                <tbody>
                    ${(item.chiTietThuoc || []).map((m, idx) => `
                        <tr>
                            <td class="tabular-nums">${idx + 1}</td>
                            <td class="fw-semibold">${escapeHtml(m.tenThuoc)} (${escapeHtml(m.donViTinh)})</td>
                            <td class="text-center tabular-nums">${m.soLuongKe}</td>
                            <td class="text-center tabular-nums">${m.soLuongDaPhat}</td>
                            <td>${escapeHtml(m.lieuDung || m.cachDungMacDinh || 'Theo hướng dẫn dược sĩ')}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
            <div class="p-3 bg-light border rounded small mb-3">
                <div class="fw-bold mb-1">Hướng dẫn sử dụng & Dặn dò:</div>
                <div style="white-space:pre-line;">${escapeHtml(pharmacistGuide || 'Uống thuốc đúng liều và đúng giờ theo đơn bác sĩ.')}</div>
            </div>
            <div class="d-flex justify-content-between small text-secondary pt-2 border-top">
                <span>Bệnh nhân vui lòng kiểm tra kỹ tên thuốc và hạn sử dụng trước khi rời quầy.</span>
                <span>Dược sĩ cấp phát: <strong>${escapeHtml(latestVoucher?.tenDuocSi || window.STAFF_INFO?.tenNhanVien || 'Quầy Dược')}</strong></span>
            </div>
        `;

        if (!printModal) {
            printModal = new bootstrap.Modal(document.getElementById('printGuideModal'));
        }
        printModal.show();
    }

    function bindEvents() {
        document.getElementById('tabPendingRxBtn')?.addEventListener('click', () => {
            state.tab = 'pending';
            syncTabUI();
            loadQueue();
        });
        document.getElementById('tabCompletedRxBtn')?.addEventListener('click', () => {
            state.tab = 'completed';
            syncTabUI();
            loadQueue();
        });
        document.getElementById('tabCancelledRxBtn')?.addEventListener('click', () => {
            state.tab = 'cancelled';
            syncTabUI();
            loadQueue();
        });

        document.querySelectorAll('#pharmacySummaryBar .summary-chip[data-tab-target]').forEach((chip) => {
            const handler = () => {
                state.tab = chip.getAttribute('data-tab-target') || 'pending';
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

        document.getElementById('pharmacySearchInput')?.addEventListener('input', () => {
            clearTimeout(state.searchTimer);
            state.searchTimer = setTimeout(loadQueue, 250);
        });
        document.getElementById('pharmacyDateInput')?.addEventListener('change', loadQueue);
        document.getElementById('btnRefreshPharmacy')?.addEventListener('click', loadQueue);
        document.getElementById('btnRetryPharmacy')?.addEventListener('click', loadQueue);

        document.getElementById('pharmacyTableBody')?.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-action]');
            if (!btn) return;
            const action = btn.getAttribute('data-action');
            const rxId = Number(btn.getAttribute('data-rx-id'));
            if (action === 'open-dispense') openDispenseModal(rxId);
            if (action === 'print-guide') openPrintGuide(rxId);
            if (action === 'open-cancel') openCancelModal(rxId);
        });

        document.getElementById('btnFillMaxAvailable')?.addEventListener('click', () => {
            document.querySelectorAll('#dispenseItemsBody .dispense-qty-input').forEach((inp) => {
                inp.value = inp.getAttribute('max') || '0';
            });
            updateDispenseSummaryTotals();
        });

        document.getElementById('btnResetDispenseQty')?.addEventListener('click', () => {
            document.querySelectorAll('#dispenseItemsBody .dispense-qty-input').forEach((inp) => {
                inp.value = '0';
            });
            updateDispenseSummaryTotals();
        });

        document.getElementById('btnSubmitDispense')?.addEventListener('click', openConfirmDispense);
        document.getElementById('btnConfirmDispenseExecute')?.addEventListener('click', executeDispense);

        document.getElementById('btnOpenPrintFromModal')?.addEventListener('click', () => {
            if (state.selectedItem) openPrintGuide(state.selectedItem.maDonThuoc);
        });
        document.getElementById('btnOpenCancelFromModal')?.addEventListener('click', () => {
            if (state.selectedItem) openCancelModal(state.selectedItem.maDonThuoc);
        });
        document.getElementById('btnConfirmCancelRx')?.addEventListener('click', executeCancelRx);
        document.getElementById('btnExecutePrintGuide')?.addEventListener('click', () => window.print());
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
