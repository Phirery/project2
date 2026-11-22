(function () {
    'use strict';

    const state = {
        tab: 'pending',
        items: [],
        doctors: [],
        hasAccess: false,
        searchTimer: null,
        selectedItem: null,
    };

    let screeningModalInstance = null;

    const VITAL_RULES = [
        { id: 'inputMach', field: 'mach', label: 'Mạch', min: 30, max: 220, integer: true },
        { id: 'inputTamThu', field: 'huyetApTamThu', label: 'Huyết áp tâm thu', min: 50, max: 260, integer: true },
        { id: 'inputTamTruong', field: 'huyetApTamTruong', label: 'Huyết áp tâm trương', min: 30, max: 160, integer: true },
        { id: 'inputNhietDo', field: 'nhietDo', label: 'Nhiệt độ', min: 34.0, max: 42.5, integer: false },
        { id: 'inputSpo2', field: 'spo2', label: 'SpO2', min: 50, max: 100, integer: true },
        { id: 'inputNhipTho', field: 'nhipTho', label: 'Nhịp thở', min: 6, max: 60, integer: true },
        { id: 'inputCanNang', field: 'canNang', label: 'Cân nặng', min: 1.0, max: 300.0, integer: false },
        { id: 'inputChieuCao', field: 'chieuCao', label: 'Chiều cao', min: 30.0, max: 250.0, integer: false },
    ];

    function todayLocalIso() {
        const d = new Date();
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
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
        const loading = document.getElementById('nursingLoadingState');
        const err = document.getElementById('nursingErrorState');
        const empty = document.getElementById('nursingEmptyState');
        const table = document.getElementById('nursingTableWrapper');
        if (!loading || !err || !empty || !table) return;

        loading.classList.toggle('d-none', viewName !== 'loading');
        err.classList.toggle('d-none', viewName !== 'error');
        empty.classList.toggle('d-none', viewName !== 'empty');
        table.classList.toggle('d-none', viewName !== 'data');

        if (viewName === 'error' && errorMessage) {
            const msgEl = document.getElementById('nursingErrorMessage');
            if (msgEl) msgEl.textContent = errorMessage;
        }
    }

    function syncTabUI() {
        const pendingBtn = document.getElementById('tabPendingBtn');
        const transferredBtn = document.getElementById('tabTransferredBtn');
        if (pendingBtn) {
            pendingBtn.classList.toggle('active', state.tab === 'pending');
            pendingBtn.setAttribute('aria-selected', state.tab === 'pending' ? 'true' : 'false');
        }
        if (transferredBtn) {
            transferredBtn.classList.toggle('active', state.tab === 'transferred');
            transferredBtn.setAttribute('aria-selected', state.tab === 'transferred' ? 'true' : 'false');
        }
        document.querySelectorAll('#nursingSummaryBar .summary-chip[data-tab-target]').forEach((chip) => {
            chip.classList.toggle('is-active', chip.getAttribute('data-tab-target') === state.tab);
        });
    }

    function updateDoctorFilterOptions(doctors) {
        const select = document.getElementById('nursingDoctorFilter');
        if (!select) return;
        const current = select.value;
        const options = ['<option value="">Tất cả bác sĩ</option>'];
        (doctors || []).forEach((doc) => {
            options.push(`<option value="${escapeHtml(doc.maBacSi)}">${escapeHtml(doc.tenBacSi)}</option>`);
        });
        select.innerHTML = options.join('');
        if (current && Array.from(select.options).some((o) => o.value === current)) {
            select.value = current;
        }
        window.AdminSummary?.refreshClearableFilters?.();
    }

    function renderVitalsPreview(sangLoc) {
        if (!sangLoc) {
            return '<span class="text-secondary small">Chưa đo sinh hiệu</span>';
        }
        const chips = [];
        if (sangLoc.mach !== null) chips.push(`<span class="vital-mini-chip">Mạch: <strong>${sangLoc.mach}</strong> l/p</span>`);
        if (sangLoc.huyetApTamThu !== null && sangLoc.huyetApTamTruong !== null) {
            chips.push(`<span class="vital-mini-chip">HA: <strong>${sangLoc.huyetApTamThu}/${sangLoc.huyetApTamTruong}</strong></span>`);
        }
        if (sangLoc.nhietDo !== null) chips.push(`<span class="vital-mini-chip">T°: <strong>${sangLoc.nhietDo}°C</strong></span>`);
        if (sangLoc.spo2 !== null) chips.push(`<span class="vital-mini-chip">SpO2: <strong>${sangLoc.spo2}%</strong></span>`);

        let alertHtml = '';
        if (sangLoc.diUng) {
            alertHtml += `<span class="status-pill is-alert mt-1 me-1"><i class="fas fa-allergies" aria-hidden="true"></i>Dị ứng: ${escapeHtml(truncateText(sangLoc.diUng, 32))}</span>`;
        }
        if (sangLoc.canhBaoLamSang) {
            alertHtml += `<span class="status-pill is-waiting mt-1"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i>${escapeHtml(truncateText(sangLoc.canhBaoLamSang, 32))}</span>`;
        }

        return `
            <div class="vitals-mini-list">${chips.length ? chips.join('') : '<span class="text-secondary small">Đã ghi chú sàng lọc</span>'}</div>
            ${alertHtml ? `<div>${alertHtml}</div>` : ''}
        `;
    }

    function renderStatusAndLog(item) {
        const sl = item.sangLoc;
        if (!sl) {
            return '<span class="status-pill is-waiting"><i class="fas fa-clock" aria-hidden="true"></i>Chờ sàng lọc</span>';
        }
        if (sl.trangThai === 'Đã chuyển bác sĩ') {
            return `
                <span class="status-pill is-transferred"><i class="fas fa-check-circle" aria-hidden="true"></i>Đã chuyển bác sĩ</span>
                <div class="small text-secondary mt-1 tabular-nums">
                    ${escapeHtml(sl.tenNhanVienSangLoc || '')}<br>
                    Chuyển lúc: ${escapeHtml(formatDateTime(sl.thoiGianChuyenBacSi || sl.thoiGianSangLoc))}
                </div>
            `;
        }
        return `
            <span class="status-pill is-draft"><i class="fas fa-save" aria-hidden="true"></i>Đã lưu nháp</span>
            <div class="small text-secondary mt-1 tabular-nums">
                ${escapeHtml(sl.tenNhanVienSangLoc || '')} · ${escapeHtml(formatDateTime(sl.thoiGianSangLoc))}
            </div>
        `;
    }

    function renderTable(items) {
        const tbody = document.getElementById('nursingTableBody');
        if (!tbody) return;

        if (!items || items.length === 0) {
            const titleEl = document.getElementById('nursingEmptyTitle');
            const descEl = document.getElementById('nursingEmptyDesc');
            if (state.tab === 'pending') {
                if (titleEl) titleEl.textContent = 'Không có bệnh nhân chờ sàng lọc';
                if (descEl) descEl.textContent = 'Chỉ các bệnh nhân đã check-in và chưa chuyển hàng đợi bác sĩ mới hiển thị tại đây.';
            } else {
                if (titleEl) titleEl.textContent = 'Chưa có bệnh nhân nào được chuyển hôm nay';
                if (descEl) descEl.textContent = 'Các ca đã hoàn tất sàng lọc và chuyển hàng đợi bác sĩ sẽ hiển thị kèm log thao tác.';
            }
            setViewState('empty');
            return;
        }

        tbody.innerHTML = items.map((item) => {
            const isTransferred = item.sangLoc?.trangThai === 'Đã chuyển bác sĩ';
            const btnLabel = isTransferred ? 'Xem phiếu' : (item.sangLoc ? 'Cập nhật & Chuyển BS' : 'Sàng lọc');
            const btnClass = isTransferred ? 'btn btn-outline-primary btn-sm' : 'btn btn-clinical-primary btn-sm';
            const btnIcon = isTransferred ? 'fa-eye' : 'fa-notes-medical';

            return `
                <tr>
                    <td><span class="stt-badge">${item.soThuTu}</span></td>
                    <td>
                        <div class="fw-bold text-dark">${escapeHtml(item.tenBenhNhan)}</div>
                        <div class="small text-secondary tabular-nums">
                            ${escapeHtml(item.maBenhNhan)} · ${genderLabel(item.gioiTinh)} · ${calculateAge(item.ngaySinh)} tuổi
                        </div>
                        <div class="small text-secondary tabular-nums">${escapeHtml(item.soDienThoai || '')}</div>
                    </td>
                    <td>
                        <div class="fw-semibold">${escapeHtml(item.tenBacSi)}</div>
                        <div class="small text-secondary">${escapeHtml(item.tenChuyenKhoa)}</div>
                        <div class="small text-secondary tabular-nums">${escapeHtml(item.tenCa)} (${escapeHtml(item.gioHen || '—')})</div>
                    </td>
                    <td class="tabular-nums small">
                        <div>${escapeHtml(formatDateTime(item.thoiGianCheckIn))}</div>
                        <span class="badge bg-light text-dark border mt-1">${item.nguon === 'truc_tiep' ? 'Trực tiếp' : 'Online'}</span>
                    </td>
                    <td>${renderVitalsPreview(item.sangLoc)}</td>
                    <td>${renderStatusAndLog(item)}</td>
                    <td class="text-end">
                        <button type="button" class="${btnClass}" data-open-screening="${item.maHangDoi}">
                            <i class="fas ${btnIcon} me-1" aria-hidden="true"></i>${btnLabel}
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        tbody.querySelectorAll('[data-open-screening]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const id = Number(btn.getAttribute('data-open-screening'));
                const found = state.items.find((x) => x.maHangDoi === id);
                if (found) openScreeningModal(found);
            });
        });

        setViewState('data');
    }

    async function loadQueue() {
        if (!state.hasAccess) return;

        setViewState('loading');
        const dateVal = document.getElementById('nursingDateInput')?.value || todayLocalIso();
        const searchVal = document.getElementById('nursingSearchInput')?.value.trim() || '';
        const doctorVal = document.getElementById('nursingDoctorFilter')?.value || '';

        const params = new URLSearchParams({
            date: dateVal,
            tab: state.tab,
        });
        if (searchVal) params.set('search', searchVal);
        if (doctorVal) params.set('maBacSi', doctorVal);

        try {
            const res = await fetch(`${API_BASE_STAFF}/get-nursing-queue.php?${params.toString()}`, {
                credentials: 'include'
            });
            const payload = await res.json();
            if (!res.ok || !payload.success || !payload.data) {
                setViewState('error', payload.message || 'Không thể tải danh sách bệnh nhân.');
                return;
            }

            const data = payload.data;
            state.items = Array.isArray(data.items) ? data.items : [];
            state.doctors = Array.isArray(data.doctors) ? data.doctors : [];

            document.getElementById('statPendingCount').textContent = Number(data.stats?.choSangLoc || 0);
            document.getElementById('statDraftCount').textContent = Number(data.stats?.daLuuTam || 0);
            document.getElementById('statTransferredCount').textContent = Number(data.stats?.daChuyenHomNay || 0);

            updateDoctorFilterOptions(state.doctors);
            renderTable(state.items);
        } catch (err) {
            console.error('Load nursing queue error:', err);
            setViewState('error', 'Lỗi kết nối máy chủ khi tải hàng đợi điều dưỡng.');
        }
    }

    function clearFormErrors() {
        const genErr = document.getElementById('screeningGeneralError');
        if (genErr) {
            genErr.classList.add('d-none');
            genErr.textContent = '';
        }
        document.querySelectorAll('#screeningForm .field-error-text, #err_huyetAp').forEach((el) => {
            el.textContent = '';
            el.classList.remove('is-visible');
        });
        document.querySelectorAll('#screeningForm .form-control').forEach((input) => {
            input.classList.remove('is-invalid-clinical');
        });
    }

    function showFieldErrors(errors) {
        clearFormErrors();
        const fieldToInput = {
            mach: 'inputMach',
            huyetApTamThu: 'inputTamThu',
            huyetApTamTruong: 'inputTamTruong',
            nhietDo: 'inputNhietDo',
            spo2: 'inputSpo2',
            nhipTho: 'inputNhipTho',
            canNang: 'inputCanNang',
            chieuCao: 'inputChieuCao',
        };

        Object.entries(errors || {}).forEach(([field, msg]) => {
            if (field === 'general') {
                const genErr = document.getElementById('screeningGeneralError');
                if (genErr) {
                    genErr.textContent = msg;
                    genErr.classList.remove('d-none');
                }
                return;
            }
            const errEl = document.getElementById(`err_${field}`);
            if (errEl) {
                errEl.textContent = msg;
                errEl.classList.add('is-visible');
            }
            const inputEl = fieldToInput[field] ? document.getElementById(fieldToInput[field]) : null;
            if (inputEl) {
                inputEl.classList.add('is-invalid-clinical');
            }
            if (field === 'huyetAp') {
                document.getElementById('inputTamThu')?.classList.add('is-invalid-clinical');
                document.getElementById('inputTamTruong')?.classList.add('is-invalid-clinical');
            }
        });
    }

    function updateBmiPreview() {
        const w = parseFloat(document.getElementById('inputCanNang')?.value || '');
        const h = parseFloat(document.getElementById('inputChieuCao')?.value || '');
        const badge = document.getElementById('bmiPreviewBadge');
        if (!badge) return;

        if (Number.isFinite(w) && Number.isFinite(h) && w > 0 && h > 0) {
            const hm = h / 100;
            const bmi = w / (hm * hm);
            badge.textContent = `BMI: ${bmi.toFixed(1)} kg/m²`;
        } else {
            badge.textContent = 'BMI: —';
        }
    }

    function validateClientForm() {
        const errors = {};
        const payload = {
            maHangDoi: Number(document.getElementById('screenMaHangDoi').value),
            diUng: document.getElementById('inputDiUng').value.trim(),
            canhBaoLamSang: document.getElementById('inputCanhBao').value.trim(),
            ghiChu: document.getElementById('inputGhiChu').value.trim(),
        };

        VITAL_RULES.forEach((rule) => {
            const raw = document.getElementById(rule.id)?.value.trim() ?? '';
            if (raw === '') {
                payload[rule.field] = null;
                return;
            }
            const num = Number(raw);
            if (!Number.isFinite(num)) {
                errors[rule.field] = `${rule.label} phải là số hợp lệ.`;
                return;
            }
            if (rule.integer && !Number.isInteger(num)) {
                errors[rule.field] = `${rule.label} phải là số nguyên.`;
                return;
            }
            if (num < rule.min || num > rule.max) {
                errors[rule.field] = `${rule.label} phải từ ${rule.min} đến ${rule.max}.`;
                return;
            }
            payload[rule.field] = num;
        });

        if ((payload.huyetApTamThu !== null && payload.huyetApTamTruong === null)
            || (payload.huyetApTamThu === null && payload.huyetApTamTruong !== null)) {
            errors.huyetAp = 'Vui lòng nhập đầy đủ cả huyết áp tâm thu và tâm trương.';
        } else if (payload.huyetApTamThu !== null && payload.huyetApTamTruong !== null && payload.huyetApTamThu <= payload.huyetApTamTruong) {
            errors.huyetAp = 'Huyết áp tâm thu phải lớn hơn huyết áp tâm trương.';
        }

        const hasAny = VITAL_RULES.some((r) => payload[r.field] !== null)
            || payload.diUng !== ''
            || payload.canhBaoLamSang !== ''
            || payload.ghiChu !== '';

        if (!hasAny) {
            errors.general = 'Vui lòng nhập ít nhất một chỉ số sinh tồn hoặc ghi chú sàng lọc.';
        }

        return { valid: Object.keys(errors).length === 0, errors, payload };
    }

    function openScreeningModal(item) {
        state.selectedItem = item;
        clearFormErrors();

        const sl = item.sangLoc || {};
        const isTransferred = sl.trangThai === 'Đã chuyển bác sĩ';

        document.getElementById('screenMaHangDoi').value = item.maHangDoi;
        document.getElementById('screeningModalTitle').innerHTML = `
            <i class="fas fa-heartbeat me-2"></i>${isTransferred ? 'Chi tiết phiếu sàng lọc (Đã chuyển BS)' : 'Phiếu sàng lọc điều dưỡng'}
        `;

        document.getElementById('modalPatientSummary').innerHTML = `
            <div class="row g-2 small">
                <div class="col-md-6">
                    <strong>Bệnh nhân:</strong> ${escapeHtml(item.tenBenhNhan)} (${escapeHtml(item.maBenhNhan)})<br>
                    <strong>Giới tính / Tuổi:</strong> ${genderLabel(item.gioiTinh)} · ${calculateAge(item.ngaySinh)} tuổi
                </div>
                <div class="col-md-6">
                    <strong>Bác sĩ khám:</strong> ${escapeHtml(item.tenBacSi)} (${escapeHtml(item.tenChuyenKhoa)})<br>
                    <strong>STT / Giờ check-in:</strong> <span class="tabular-nums">#${item.soThuTu} · ${escapeHtml(formatDateTime(item.thoiGianCheckIn))}</span>
                </div>
            </div>
        `;

        document.getElementById('inputMach').value = sl.mach ?? '';
        document.getElementById('inputTamThu').value = sl.huyetApTamThu ?? '';
        document.getElementById('inputTamTruong').value = sl.huyetApTamTruong ?? '';
        document.getElementById('inputNhietDo').value = sl.nhietDo ?? '';
        document.getElementById('inputSpo2').value = sl.spo2 ?? '';
        document.getElementById('inputNhipTho').value = sl.nhipTho ?? '';
        document.getElementById('inputCanNang').value = sl.canNang ?? '';
        document.getElementById('inputChieuCao').value = sl.chieuCao ?? '';
        document.getElementById('inputDiUng').value = sl.diUng ?? '';
        document.getElementById('inputCanhBao').value = sl.canhBaoLamSang ?? '';
        document.getElementById('inputGhiChu').value = sl.ghiChu ?? '';

        document.querySelectorAll('#screeningForm input, #screeningForm textarea').forEach((el) => {
            if (el.type !== 'hidden') {
                el.disabled = isTransferred;
            }
        });

        const actionsEl = document.getElementById('screeningModalActions');
        if (actionsEl) {
            actionsEl.classList.toggle('d-none', isTransferred);
        }

        const auditMetaEl = document.getElementById('screeningAuditMeta');
        if (auditMetaEl) {
            if (sl.maSangLoc) {
                auditMetaEl.classList.remove('d-none');
                auditMetaEl.innerHTML = `
                    <i class="fas fa-user-check me-1"></i>
                    Điều dưỡng thao tác: <strong>${escapeHtml(sl.tenNhanVienSangLoc || sl.maNhanVienSangLoc || '—')}</strong>
                    · Lưu lúc <span class="tabular-nums">${escapeHtml(formatDateTime(sl.thoiGianSangLoc))}</span>
                    ${sl.thoiGianChuyenBacSi ? ` · Chuyển bác sĩ lúc <span class="tabular-nums">${escapeHtml(formatDateTime(sl.thoiGianChuyenBacSi))}</span>` : ''}
                `;
            } else {
                auditMetaEl.classList.add('d-none');
                auditMetaEl.innerHTML = '';
            }
        }

        updateBmiPreview();
        if (!screeningModalInstance) {
            screeningModalInstance = new bootstrap.Modal(document.getElementById('screeningModal'));
        }
        screeningModalInstance.show();
    }

    async function submitScreening(action) {
        const { valid, errors, payload } = validateClientForm();
        if (!valid) {
            showFieldErrors(errors);
            return;
        }

        if (action === 'transfer' && state.selectedItem) {
            const ok = window.confirm(
                `Xác nhận hoàn tất sàng lọc và chuyển bệnh nhân ${state.selectedItem.tenBenhNhan} (#${state.selectedItem.soThuTu}) sang hàng đợi bác sĩ ${state.selectedItem.tenBacSi}?`
            );
            if (!ok) return;
        }

        payload.action = action;
        const draftBtn = document.getElementById('btnSaveDraftScreening');
        const transferBtn = document.getElementById('btnTransferToDoctor');
        draftBtn.disabled = true;
        transferBtn.disabled = true;

        try {
            const res = await fetch(`${API_BASE_STAFF}/save-nursing-screening.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                if (data.errors) {
                    showFieldErrors(data.errors);
                } else {
                    showFieldErrors({ general: data.message || 'Không thể lưu phiếu sàng lọc.' });
                }
                return;
            }

            showAlert('success', data.message || 'Lưu phiếu sàng lọc thành công!');
            screeningModalInstance?.hide();
            await loadQueue();
        } catch (err) {
            console.error('Save screening error:', err);
            showFieldErrors({ general: 'Lỗi kết nối máy chủ khi lưu phiếu sàng lọc.' });
        } finally {
            draftBtn.disabled = false;
            transferBtn.disabled = false;
        }
    }

    function bindEvents() {
        const dateEl = document.getElementById('nursingDateInput');
        if (dateEl && !dateEl.value) {
            dateEl.value = todayLocalIso();
        }

        document.getElementById('tabPendingBtn')?.addEventListener('click', () => {
            state.tab = 'pending';
            syncTabUI();
            loadQueue();
        });
        document.getElementById('tabTransferredBtn')?.addEventListener('click', () => {
            state.tab = 'transferred';
            syncTabUI();
            loadQueue();
        });

        document.querySelectorAll('#nursingSummaryBar .summary-chip[data-tab-target]').forEach((chip) => {
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

        document.getElementById('nursingSearchInput')?.addEventListener('input', () => {
            clearTimeout(state.searchTimer);
            state.searchTimer = setTimeout(loadQueue, 250);
        });
        document.getElementById('nursingDoctorFilter')?.addEventListener('change', loadQueue);
        document.getElementById('nursingDateInput')?.addEventListener('change', loadQueue);
        document.getElementById('btnRefreshNursing')?.addEventListener('click', loadQueue);
        document.getElementById('btnRetryNursing')?.addEventListener('click', loadQueue);

        document.getElementById('inputCanNang')?.addEventListener('input', updateBmiPreview);
        document.getElementById('inputChieuCao')?.addEventListener('input', updateBmiPreview);
        document.getElementById('btnSaveDraftScreening')?.addEventListener('click', () => submitScreening('draft'));
        document.getElementById('btnTransferToDoctor')?.addEventListener('click', () => submitScreening('transfer'));
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
