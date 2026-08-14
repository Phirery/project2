(function () {
    'use strict';

    const API_BASE_PATIENT = window.API_ENDPOINTS?.patient;

    const state = {
        patient: null,        // Bệnh nhân đang thao tác (đã chọn từ kết quả tìm kiếm hoặc vừa tạo)
        booking: {},           // Lựa chọn khoa/chuyên khoa/bác sĩ/ca/slot khi tạo lịch walk-in
        todayList: [],          // Danh sách đầy đủ lịch hôm nay chưa check-in (để lọc realtime)
        doctorQueue: [],        // Danh sách đầy đủ bác sĩ + số lượng hàng đợi hôm nay
        queueDetail: [],        // Hàng đợi chi tiết của bác sĩ đang xem
        queueDetailDoctor: null
    };

    let cameraScanner = null;
    let cameraModalInstance = null;
    let fileScanner = null;

    function todayStr() {
        const d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function genderLabel(g) {
        return { nam: 'Nam', nu: 'Nữ', khac: 'Khác' }[g] || '';
    }

    async function api(base, path, options = {}) {
        const res = await fetch(`${base}/${path}`, {
            credentials: 'include',
            headers: options.body ? { 'Content-Type': 'application/json' } : undefined,
            ...options
        });
        return res.json();
    }

    const staffApi = (path, options) => api(API_BASE_STAFF, path, options);
    const patientApi = (path, options) => api(API_BASE_PATIENT, path, options);

    function syncFilterUI() {
        window.AdminSummary?.refreshClearableFilters?.();
    }

    // =========================================================
    // TÌM KIẾM BỆNH NHÂN (đa tiêu chí -> danh sách kết quả)
    // =========================================================

    async function doSearch() {
        const q = document.getElementById('searchInput').value.trim();
        if (!q) {
            showAlert('error', 'Vui lòng nhập từ khóa tìm kiếm');
            return;
        }

        const listEl = document.getElementById('searchResultsList');
        listEl.innerHTML = '<div class="table-section text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Đang tìm...</div>';
        document.getElementById('resultArea').innerHTML = '';

        try {
            const data = await staffApi(`search-patient.php?q=${encodeURIComponent(q)}`);
            if (!data.success) {
                showAlert('error', data.message || 'Có lỗi xảy ra');
                listEl.innerHTML = '';
                return;
            }

            if (data.patients.length === 0) {
                renderSearchEmpty(q);
                return;
            }

            if (data.patients.length === 1) {
                listEl.innerHTML = '';
                selectPatient(data.patients[0]);
                return;
            }

            renderSearchResultsList(data.patients);
        } catch (err) {
            console.error(err);
            showAlert('error', 'Không thể kết nối máy chủ');
            listEl.innerHTML = '';
        }
    }

    function renderSearchEmpty(q) {
        const listEl = document.getElementById('searchResultsList');
        const looksLikePhone = /^[0-9+]{8,15}$/.test(q);
        listEl.innerHTML = `
            <div class="table-section text-center">
                <p class="text-muted mb-3">Không tìm thấy bệnh nhân phù hợp với "<strong>${escapeHtml(q)}</strong>"</p>
                <button class="btn btn-primary-ci" id="btnCreateFromEmpty">
                    <i class="fas fa-user-plus me-1"></i> Tạo hồ sơ bệnh nhân mới
                </button>
            </div>
        `;
        document.getElementById('btnCreateFromEmpty').addEventListener('click', () => {
            renderNewPatientForm(looksLikePhone ? q : '');
        });
    }

    function renderSearchResultsList(patients) {
        const listEl = document.getElementById('searchResultsList');
        listEl.innerHTML = `
            <div class="table-section">
                <div class="table-header">
                    <h5><i class="fas fa-list me-2"></i>Kết quả tìm kiếm (${patients.length})</h5>
                </div>
                ${patients.map((p, idx) => `
                    <div class="search-result-item" onclick="window.__selectSearchResult(${idx})">
                        <div>
                            <strong>${escapeHtml(p.tenBenhNhan)}</strong>
                            <span class="text-muted small ms-2">${escapeHtml(p.maBenhNhan)} • ${genderLabel(p.gioiTinh)} • ${escapeHtml(p.soDienThoai || '')}</span>
                            ${p.soTheBHYT ? `<span class="text-muted small ms-2">BHYT: ${escapeHtml(p.soTheBHYT)}</span>` : ''}
                        </div>
                        <span class="badge ${p.appointmentsToday.length > 0 ? 'bg-primary-subtle text-primary-emphasis' : 'bg-secondary-subtle text-secondary-emphasis'}">
                            ${p.appointmentsToday.length > 0 ? p.appointmentsToday.length + ' lịch hôm nay' : 'Chưa có lịch hôm nay'}
                        </span>
                    </div>
                `).join('')}
            </div>
        `;
        window.__searchResultsCache = patients;
    }

    window.__selectSearchResult = function (idx) {
        const patient = window.__searchResultsCache[idx];
        document.getElementById('searchResultsList').innerHTML = '';
        selectPatient(patient);
    };

    function selectPatient(patient) {
        state.patient = patient;
        const pending = (patient.appointmentsToday || []).filter(a => !a.daCheckIn);
        const checkedIn = (patient.appointmentsToday || []).filter(a => a.daCheckIn);

        if (pending.length > 0 || checkedIn.length > 0) {
            renderAppointmentsToday(patient, pending, checkedIn);
        } else {
            renderNoAppointmentYet(patient);
        }
    }

    // =========================================================
    // CASE A: TẠO HỒ SƠ BỆNH NHÂN MỚI
    // =========================================================

    function renderNewPatientForm(prefillPhone) {
        const resultArea = document.getElementById('resultArea');
        document.getElementById('searchResultsList').innerHTML = '';

        resultArea.innerHTML = `
            <div class="table-section">
                <div class="table-header"><h5><i class="fas fa-user-plus me-2"></i>Tạo hồ sơ bệnh nhân mới</h5></div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Họ và tên *</label>
                        <input type="text" class="form-control" id="npTen">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Số điện thoại *</label>
                        <input type="text" class="form-control" id="npSdt" value="${escapeHtml(prefillPhone || '')}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Ngày sinh *</label>
                        <input type="date" class="form-control" id="npNgaySinh">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Giới tính *</label>
                        <select class="form-select" id="npGioiTinh">
                            <option value="nam">Nam</option>
                            <option value="nu">Nữ</option>
                            <option value="khac">Khác</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Số thẻ BHYT (nếu có)</label>
                        <input type="text" class="form-control" id="npBhyt">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email (không bắt buộc)</label>
                        <input type="email" class="form-control" id="npEmail">
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="npTaoTaiKhoan">
                            <label class="form-check-label" for="npTaoTaiKhoan">Tạo tài khoản đăng nhập cho bệnh nhân</label>
                        </div>
                    </div>
                    <div class="col-md-6" id="npAccountFields" style="display:none;">
                        <label class="form-label">Tên đăng nhập</label>
                        <input type="text" class="form-control" id="npTenDangNhap">
                    </div>
                    <div class="col-md-6" id="npPasswordField" style="display:none;">
                        <label class="form-label">Mật khẩu</label>
                        <input type="password" class="form-control" id="npMatKhau">
                    </div>
                </div>
                <button class="btn btn-primary-ci mt-3" id="btnSubmitNewPatient">
                    <i class="fas fa-check me-1"></i> Tạo hồ sơ &amp; tiếp tục chọn bác sĩ
                </button>
            </div>
        `;

        document.getElementById('npTaoTaiKhoan').addEventListener('change', (e) => {
            document.getElementById('npAccountFields').style.display = e.target.checked ? 'block' : 'none';
            document.getElementById('npPasswordField').style.display = e.target.checked ? 'block' : 'none';
        });

        document.getElementById('btnSubmitNewPatient').addEventListener('click', submitNewPatient);
    }

    async function submitNewPatient() {
        const taoTaiKhoan = document.getElementById('npTaoTaiKhoan').checked;
        const payload = {
            tenBenhNhan: document.getElementById('npTen').value.trim(),
            soDienThoai: document.getElementById('npSdt').value.trim(),
            ngaySinh: document.getElementById('npNgaySinh').value,
            gioiTinh: document.getElementById('npGioiTinh').value,
            soTheBHYT: document.getElementById('npBhyt').value.trim(),
            email: document.getElementById('npEmail').value.trim(),
            taoTaiKhoan
        };
        if (taoTaiKhoan) {
            payload.tenDangNhap = document.getElementById('npTenDangNhap').value.trim();
            payload.matKhau = document.getElementById('npMatKhau').value;
        }

        const btn = document.getElementById('btnSubmitNewPatient');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Đang tạo...';

        try {
            const data = await staffApi('create-patient.php', { method: 'POST', body: JSON.stringify(payload) });
            if (!data.success) {
                showAlert('error', data.message || 'Không thể tạo hồ sơ');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check me-1"></i> Tạo hồ sơ &amp; tiếp tục chọn bác sĩ';
                return;
            }

            showAlert('success', 'Tạo hồ sơ bệnh nhân thành công!');
            state.patient = { maBenhNhan: data.maBenhNhan, tenBenhNhan: payload.tenBenhNhan, soDienThoai: payload.soDienThoai };
            renderNoAppointmentYet(state.patient);
        } catch (err) {
            console.error(err);
            showAlert('error', 'Không thể kết nối máy chủ');
            btn.disabled = false;
        }
    }

    // =========================================================
    // HIỂN THỊ LỊCH HÔM NAY CỦA BỆNH NHÂN ĐÃ CHỌN
    // =========================================================

    function renderAppointmentsToday(patient, pending, checkedIn) {
        const resultArea = document.getElementById('resultArea');
        let html = `
            <div class="table-section">
                <div class="table-header"><h5><i class="fas fa-user-check me-2"></i>Bệnh nhân đã chọn</h5></div>
                <div class="patient-card">
                    <strong>${escapeHtml(patient.tenBenhNhan)}</strong>
                    <div class="maBenhNhan">Mã BN: ${escapeHtml(patient.maBenhNhan)} • SĐT: ${escapeHtml(patient.soDienThoai || '')}</div>
                </div>
        `;

        if (pending.length > 0) {
            html += `<p class="fw-semibold mb-2">Lịch đã đặt hôm nay:</p>`;
            pending.forEach(a => {
                html += `
                    <div class="appt-card">
                        <div><strong>BS. ${escapeHtml(a.tenBacSi)}</strong> — ${escapeHtml(a.tenCa)} (${a.gioBatDau} - ${a.gioKetThuc})</div>
                        <button class="btn btn-primary-ci btn-sm" onclick="window.__checkInAppointment(${a.maLichKham}, this, '${escapeHtml(a.tenBacSi)}')">
                            <i class="fas fa-door-open me-1"></i> Check-in ngay
                        </button>
                    </div>
                `;
            });
        }

        if (checkedIn.length > 0) {
            html += `<p class="fw-semibold mb-2 mt-3">Đã check-in hôm nay:</p>`;
            checkedIn.forEach(a => {
                html += `
                    <div class="appt-card" style="background:#f1f3f5;border-color:#e1e8ed;">
                        <div>
                            <strong>BS. ${escapeHtml(a.tenBacSi)}</strong> — STT <strong>${a.soThuTu}</strong>
                            <span class="badge ${badgeClassFor(a.trangThaiHangDoi)} ms-2">${a.trangThaiHangDoi}</span>
                        </div>
                    </div>
                `;
            });
        }

        html += `
                <button class="btn btn-outline-secondary btn-sm mt-2" onclick="window.__goToBooking()">
                    <i class="fas fa-plus me-1"></i> Đặt thêm lịch khám khác hôm nay
                </button>
            </div>
        `;

        resultArea.innerHTML = html;
    }

    function renderNoAppointmentYet(patient) {
        const resultArea = document.getElementById('resultArea');
        resultArea.innerHTML = `
            <div class="table-section">
                <div class="table-header"><h5><i class="fas fa-user-check me-2"></i>Bệnh nhân chưa có lịch hôm nay</h5></div>
                <div class="patient-card">
                    <strong>${escapeHtml(patient.tenBenhNhan)}</strong>
                    <div class="maBenhNhan">Mã BN: ${escapeHtml(patient.maBenhNhan)} • SĐT: ${escapeHtml(patient.soDienThoai || '')}</div>
                </div>
                <div id="bookingWizard"></div>
            </div>
        `;
        renderBookingWizard();
    }

    window.__goToBooking = function () {
        renderNoAppointmentYet(state.patient);
    };

    // =========================================================
    // CHECK-IN (dùng chung cho card lịch hôm nay + bảng danh sách hôm nay)
    // =========================================================

    window.__checkInAppointment = async function (maLichKham, btnEl, tenBacSi) {
        const originalHtml = btnEl.innerHTML;
        btnEl.disabled = true;
        btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        try {
            const data = await staffApi('check-in.php', { method: 'POST', body: JSON.stringify({ maLichKham }) });
            if (!data.success) {
                showAlert('error', data.message || 'Check-in thất bại');
                btnEl.disabled = false;
                btnEl.innerHTML = originalHtml;
                return;
            }
            state.todayList = state.todayList.filter(r => r.maLichKham !== maLichKham);
            applyTodayListFilter();
            loadDoctorQueueOverview();
            showCheckInSuccess(data, tenBacSi || '');
        } catch (err) {
            console.error(err);
            showAlert('error', 'Không thể kết nối máy chủ');
            btnEl.disabled = false;
            btnEl.innerHTML = originalHtml;
        }
    };

    function showCheckInSuccess(data, doctorName) {
        const resultArea = document.getElementById('resultArea');
        document.getElementById('searchResultsList').innerHTML = '';
        resultArea.innerHTML = `
            <div class="table-section text-center">
                <div class="stt-badge mx-auto mb-3">${data.soThuTu}</div>
                <h5 class="mb-1">Check-in thành công!</h5>
                <p class="text-muted mb-0">
                    ${doctorName ? 'Bác sĩ ' + escapeHtml(doctorName) + ' • ' : ''}
                    Số thứ tự <strong>${data.soThuTu}</strong>
                    ${data.soNguoiTruoc > 0 ? ` — còn <strong>${data.soNguoiTruoc}</strong> người đang chờ trước` : ' — bệnh nhân tiếp theo'}
                </p>
                <button class="btn btn-outline-secondary btn-sm mt-3" id="btnCheckInAnother">
                    <i class="fas fa-redo me-1"></i> Check-in bệnh nhân khác
                </button>
            </div>
        `;
        document.getElementById('btnCheckInAnother').addEventListener('click', () => {
            document.getElementById('searchInput').value = '';
            syncFilterUI();
            resultArea.innerHTML = '';
            document.getElementById('searchInput').focus();
        });
    }

    // =========================================================
    // TẠO LỊCH WALK-IN (chọn khoa -> chuyên khoa -> bác sĩ -> ca -> slot)
    // =========================================================

    async function renderBookingWizard() {
        state.booking = { ngayKham: todayStr() };
        const container = document.getElementById('bookingWizard');
        if (!container) return;

        container.innerHTML = `
            <hr>
            <p class="fw-semibold">Chọn khoa</p>
            <div class="step-select" id="wizKhoa"><i class="fas fa-spinner fa-spin"></i></div>
            <div id="wizChuyenKhoaBlock" style="display:none;">
                <p class="fw-semibold">Chọn chuyên khoa</p>
                <div class="step-select" id="wizChuyenKhoa"></div>
            </div>
            <div id="wizBacSiBlock" style="display:none;">
                <p class="fw-semibold">Chọn bác sĩ</p>
                <div class="step-select" id="wizBacSi"></div>
            </div>
            <div id="wizCaBlock" style="display:none;">
                <p class="fw-semibold">Chọn ca khám (hôm nay)</p>
                <div class="step-select" id="wizCa"></div>
            </div>
            <div id="wizSlotBlock" style="display:none;">
                <p class="fw-semibold">Chọn giờ khám còn trống</p>
                <div class="step-select" id="wizSlot"></div>
            </div>
            <div id="wizConfirmBlock" style="display:none;">
                <textarea class="form-control mb-2" id="wizGhiChu" placeholder="Ghi chú (không bắt buộc)"></textarea>
                <button class="btn btn-primary-ci" id="btnConfirmBooking">
                    <i class="fas fa-check me-1"></i> Tạo lịch &amp; Check-in ngay
                </button>
            </div>
        `;

        loadDepartments();
    }

    async function loadDepartments() {
        const el = document.getElementById('wizKhoa');
        const data = await patientApi('get-departments.php');
        if (!data.success) { el.innerHTML = '<span class="text-danger">Không tải được danh sách khoa</span>'; return; }
        el.innerHTML = data.data.map(k => `
            <div class="choice-chip" data-id="${k.maKhoa}" onclick="window.__pickKhoa('${k.maKhoa}', this)">${escapeHtml(k.tenKhoa)}</div>
        `).join('');
    }

    window.__pickKhoa = async function (maKhoa, el) {
        highlightChip(el);
        state.booking.maKhoa = maKhoa;
        ['wizChuyenKhoaBlock', 'wizBacSiBlock', 'wizCaBlock', 'wizSlotBlock', 'wizConfirmBlock'].forEach(hideBlock);
        document.getElementById('wizChuyenKhoaBlock').style.display = 'block';
        const target = document.getElementById('wizChuyenKhoa');
        target.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        const data = await patientApi(`get-specialties.php?maKhoa=${encodeURIComponent(maKhoa)}`);
        if (!data.success) { target.innerHTML = '<span class="text-danger">Lỗi tải chuyên khoa</span>'; return; }
        target.innerHTML = data.data.map(ck => `
            <div class="choice-chip" data-id="${ck.maChuyenKhoa}" onclick="window.__pickChuyenKhoa('${ck.maChuyenKhoa}', this)">${escapeHtml(ck.tenChuyenKhoa)}</div>
        `).join('');
    };

    window.__pickChuyenKhoa = async function (maChuyenKhoa, el) {
        highlightChip(el);
        state.booking.maChuyenKhoa = maChuyenKhoa;
        ['wizBacSiBlock', 'wizCaBlock', 'wizSlotBlock', 'wizConfirmBlock'].forEach(hideBlock);
        document.getElementById('wizBacSiBlock').style.display = 'block';
        const target = document.getElementById('wizBacSi');
        target.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        const data = await patientApi(`get-available-doctors.php?maChuyenKhoa=${encodeURIComponent(maChuyenKhoa)}&ngayKham=${state.booking.ngayKham}`);
        if (!data.success) { target.innerHTML = '<span class="text-danger">Lỗi tải bác sĩ</span>'; return; }
        target.innerHTML = data.data.map(bs => `
            <div class="choice-chip ${bs.available ? '' : 'disabled'}" data-id="${bs.maBacSi}"
                 onclick="window.__pickBacSi('${bs.maBacSi}', '${escapeHtml(bs.tenBacSi)}', this)">
                 ${escapeHtml(bs.tenBacSi)} ${bs.available ? '' : '(nghỉ hôm nay)'}
            </div>
        `).join('') || '<span class="text-muted">Không có bác sĩ phù hợp</span>';
    };

    window.__pickBacSi = async function (maBacSi, tenBacSi, el) {
        highlightChip(el);
        state.booking.maBacSi = maBacSi;
        state.booking.tenBacSi = tenBacSi;
        ['wizCaBlock', 'wizSlotBlock', 'wizConfirmBlock'].forEach(hideBlock);
        document.getElementById('wizCaBlock').style.display = 'block';
        const target = document.getElementById('wizCa');
        target.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        const data = await patientApi(`get-available-shifts.php?maBacSi=${encodeURIComponent(maBacSi)}&ngayKham=${state.booking.ngayKham}`);
        if (!data.success) { target.innerHTML = '<span class="text-danger">Lỗi tải ca khám</span>'; return; }
        target.innerHTML = data.data.map(ca => `
            <div class="choice-chip ${ca.available ? '' : 'disabled'}" data-id="${ca.maCa}"
                 onclick="window.__pickCa(${ca.maCa}, this)">
                 ${escapeHtml(ca.tenCa)} ${ca.available ? '' : '(bác sĩ nghỉ)'}
            </div>
        `).join('');
    };

    window.__pickCa = async function (maCa, el) {
        highlightChip(el);
        state.booking.maCa = maCa;
        ['wizSlotBlock', 'wizConfirmBlock'].forEach(hideBlock);
        document.getElementById('wizSlotBlock').style.display = 'block';
        const target = document.getElementById('wizSlot');
        target.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        const data = await patientApi(`get-available-slots.php?maBacSi=${encodeURIComponent(state.booking.maBacSi)}&ngayKham=${state.booking.ngayKham}&maCa=${maCa}`);
        if (!data.success) { target.innerHTML = '<span class="text-danger">Lỗi tải giờ khám</span>'; return; }

        const freeSlots = data.data.filter(s => s.available);
        if (freeSlots.length === 0) {
            target.innerHTML = `<div class="alert alert-warning mb-0">
                Đã hết chỗ trong ca này. Vui lòng báo bệnh nhân quay lại buổi chiều,
                hoặc chờ nếu bác sĩ khám xong sớm (thử tải lại sau ít phút).
            </div>`;
            return;
        }

        target.innerHTML = freeSlots.map(s => `
            <div class="choice-chip" data-id="${s.maSuat}" onclick="window.__pickSlot(${s.maSuat}, '${s.gioBatDau}', '${s.gioKetThuc}', this)">
                ${s.gioBatDau} - ${s.gioKetThuc}
            </div>
        `).join('');
    };

    window.__pickSlot = function (maSuat, gioBatDau, gioKetThuc, el) {
        highlightChip(el);
        state.booking.maSuat = maSuat;
        state.booking.gioBatDau = gioBatDau;
        state.booking.gioKetThuc = gioKetThuc;
        document.getElementById('wizConfirmBlock').style.display = 'block';
        document.getElementById('btnConfirmBooking').onclick = submitBooking;
    };

    async function submitBooking() {
        const btn = document.getElementById('btnConfirmBooking');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Đang xử lý...';

        try {
            const apptData = await staffApi('create-appointment.php', {
                method: 'POST',
                body: JSON.stringify({
                    maBenhNhan: state.patient.maBenhNhan,
                    maBacSi: state.booking.maBacSi,
                    ngayKham: state.booking.ngayKham,
                    maCa: state.booking.maCa,
                    maSuat: state.booking.maSuat,
                    ghiChu: document.getElementById('wizGhiChu').value.trim()
                })
            });

            if (!apptData.success) {
                showAlert('error', apptData.message || 'Không thể tạo lịch khám');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check me-1"></i> Tạo lịch &amp; Check-in ngay';
                return;
            }

            const checkinData = await staffApi('check-in.php', {
                method: 'POST',
                body: JSON.stringify({ maLichKham: apptData.maLichKham })
            });

            if (!checkinData.success) {
                showAlert('error', 'Đã tạo lịch nhưng check-in thất bại: ' + (checkinData.message || ''));
                btn.disabled = false;
                return;
            }

            loadDoctorQueueOverview();
            showCheckInSuccess(checkinData, state.booking.tenBacSi || '');
        } catch (err) {
            console.error(err);
            showAlert('error', 'Không thể kết nối máy chủ');
            btn.disabled = false;
        }
    }

    // =========================================================
    // BẢNG: BỆNH NHÂN CÓ LỊCH HÔM NAY (CHƯA CHECK-IN) - REALTIME FILTER
    // =========================================================

    async function loadTodayList() {
        const data = await staffApi('get-today-list.php');
        if (!data.success) return;
        state.todayList = data.data;
        applyTodayListFilter();
    }

    function applyTodayListFilter() {
        const q = (document.getElementById('todayListFilter').value || '').toLowerCase().trim();
        const filtered = !q ? state.todayList : state.todayList.filter(r =>
            r.tenBenhNhan.toLowerCase().includes(q) ||
            r.maBenhNhan.toLowerCase().includes(q) ||
            (r.soDienThoai || '').toLowerCase().includes(q) ||
            r.tenBacSi.toLowerCase().includes(q)
        );
        renderTodayList(filtered);
    }

    function renderTodayList(list) {
        const tbody = document.getElementById('todayListBody');
        const emptyHint = document.getElementById('todayListEmpty');
        if (list.length === 0) {
            tbody.innerHTML = '';
            emptyHint.style.display = 'block';
            return;
        }
        emptyHint.style.display = 'none';
        tbody.innerHTML = list.map(r => `
            <tr>
                <td><strong>${escapeHtml(r.tenBenhNhan)}</strong><div class="text-muted small">${escapeHtml(r.maBenhNhan)}</div></td>
                <td>${escapeHtml(r.soDienThoai || '')}</td>
                <td>${escapeHtml(r.tenBacSi)}</td>
                <td>${r.gioBatDau} - ${r.gioKetThuc}</td>
                <td>${r.nguon === 'truc_tiep' ? 'Trực tiếp' : 'Online'}</td>
                <td class="text-center">
                    <button class="btn btn-primary-ci btn-sm" onclick="window.__checkInAppointment(${r.maLichKham}, this, '${escapeHtml(r.tenBacSi)}')">
                        <i class="fas fa-door-open"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    }

    // =========================================================
    // TAB HÀNG ĐỢI: DANH SÁCH BÁC SĨ (BẢNG) + CHI TIẾT HÀNG ĐỢI
    // =========================================================

    async function loadDoctorQueueOverview() {
        const data = await staffApi('get-queue.php');
        if (!data.success) return;
        state.doctorQueue = data.data;
        applyDoctorFilter();
    }

    function applyDoctorFilter() {
        const q = (document.getElementById('doctorFilter').value || '').toLowerCase().trim();
        const filtered = !q ? state.doctorQueue : state.doctorQueue.filter(bs => bs.tenBacSi.toLowerCase().includes(q));
        renderDoctorQueueTable(filtered);
    }

    function renderDoctorQueueTable(list) {
        const tbody = document.getElementById('doctorQueueBody');
        const emptyHint = document.getElementById('doctorQueueEmpty');
        if (list.length === 0) {
            tbody.innerHTML = '';
            emptyHint.style.display = 'block';
            return;
        }
        emptyHint.style.display = 'none';
        tbody.innerHTML = list.map(bs => `
            <tr class="is-clickable" onclick="window.__loadQueueDetail('${bs.maBacSi}', '${escapeHtml(bs.tenBacSi)}')">
                <td><strong>${escapeHtml(bs.tenBacSi)}</strong></td>
                <td class="text-center"><span class="badge bg-warning text-dark">${bs.soDangCho}</span></td>
                <td class="text-center">${bs.soDangKham > 0 ? `<span class="badge bg-info text-dark">${bs.soDangKham}</span>` : '—'}</td>
            </tr>
        `).join('');
    }

    window.__loadQueueDetail = async function (maBacSi, tenBacSi) {
        state.queueDetailDoctor = { maBacSi, tenBacSi };
        document.getElementById('queueDetailSection').style.display = 'block';
        document.getElementById('queueDetailTitle').innerHTML = `<i class="fas fa-list-ol me-2"></i>Hàng đợi của BS. ${escapeHtml(tenBacSi)}`;
        document.getElementById('queuePatientFilter').value = '';
        syncFilterUI();

        const data = await staffApi(`get-queue.php?maBacSi=${encodeURIComponent(maBacSi)}`);
        if (!data.success) return;
        state.queueDetail = data.data;
        applyQueueDetailFilter();
        document.getElementById('queueDetailSection').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    function applyQueueDetailFilter() {
        const q = (document.getElementById('queuePatientFilter').value || '').toLowerCase().trim();
        const filtered = !q ? state.queueDetail : state.queueDetail.filter(r =>
            r.tenBenhNhan.toLowerCase().includes(q) || r.maBenhNhan.toLowerCase().includes(q)
        );
        renderQueueDetailTable(filtered);
    }

    function renderQueueDetailTable(list) {
        const tbody = document.getElementById('queueDetailBody');
        const emptyHint = document.getElementById('queueDetailEmpty');
        if (list.length === 0) {
            tbody.innerHTML = '';
            emptyHint.style.display = 'block';
            return;
        }
        emptyHint.style.display = 'none';
        tbody.innerHTML = list.map(q => `
            <tr>
                <td><strong>${q.soThuTu}</strong></td>
                <td>${escapeHtml(q.tenBenhNhan)} <span class="text-muted small">(${escapeHtml(q.maBenhNhan)})</span></td>
                <td>${q.gioHen}</td>
                <td>${q.nguon === 'truc_tiep' ? 'Trực tiếp' : 'Online'}</td>
                <td><span class="badge ${badgeClassFor(q.trangThai)}">${q.trangThai}</span></td>
            </tr>
        `).join('');
    }

    // =========================================================
    // QUÉT QR - CAMERA
    // =========================================================

    function initCameraScanner() {
        const modalEl = document.getElementById('qrScannerModal');
        cameraModalInstance = new bootstrap.Modal(modalEl);

        document.getElementById('btnScanQr').addEventListener('click', async () => {
            cameraModalInstance.show();
            cameraScanner = new Html5Qrcode('qrReader');
            try {
                await cameraScanner.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: 220 },
                    (decodedText) => {
                        document.getElementById('searchInput').value = decodedText.trim();
                        syncFilterUI();
                        stopCameraScanner();
                        cameraModalInstance.hide();
                        doSearch();
                    },
                    () => {}
                );
            } catch (err) {
                console.error(err);
                showAlert('error', 'Không thể mở camera. Vui lòng kiểm tra quyền truy cập camera.');
            }
        });

        modalEl.addEventListener('hidden.bs.modal', stopCameraScanner);
    }

    function stopCameraScanner() {
        if (cameraScanner) {
            cameraScanner.stop().catch(() => {}).finally(() => {
                cameraScanner.clear();
                cameraScanner = null;
            });
        }
    }

    // =========================================================
    // QUÉT QR - TỪ ẢNH (dùng chung thư viện html5-qrcode, không cần tải thêm)
    // =========================================================

    function getFileScanner() {
        if (!fileScanner) {
            fileScanner = new Html5Qrcode('qrFileReader');
        }
        return fileScanner;
    }

    function initFileScanner() {
        document.getElementById('btnImportQr').addEventListener('click', () => {
            document.getElementById('qrFileInput').click();
        });

        document.getElementById('qrFileInput').addEventListener('change', async (e) => {
            const file = e.target.files[0];
            e.target.value = '';
            if (!file) return;

            try {
                const decodedText = await getFileScanner().scanFile(file, false);
                document.getElementById('searchInput').value = decodedText.trim();
                syncFilterUI();
                doSearch();
            } catch (err) {
                console.error(err);
                showAlert('error', 'Không đọc được mã QR từ ảnh này. Vui lòng thử ảnh khác.');
            }
        });
    }

    // =========================================================
    // HELPERS
    // =========================================================

    function hideBlock(id) {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    }

    function highlightChip(el) {
        const parent = el.parentElement;
        parent.querySelectorAll('.choice-chip').forEach(c => c.classList.remove('active'));
        el.classList.add('active');
    }

    function badgeClassFor(trangThai) {
        if (trangThai === 'Đang khám') return 'badge-inprogress';
        if (trangThai === 'Hoàn thành') return 'badge-done';
        return 'badge-waiting';
    }

    // =========================================================
    // INIT
    // =========================================================

    function init() {
        document.getElementById('btnSearch').addEventListener('click', doSearch);
        document.getElementById('searchInput').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') doSearch();
        });
        document.getElementById('searchInput').addEventListener('input', (e) => {
            if (e.target.value.trim() === '') {
                document.getElementById('searchResultsList').innerHTML = '';
                document.getElementById('resultArea').innerHTML = '';
            }
        });
        document.getElementById('btnNewPatient').addEventListener('click', () => renderNewPatientForm(''));

        document.getElementById('todayListFilter').addEventListener('input', applyTodayListFilter);
        document.getElementById('doctorFilter').addEventListener('input', applyDoctorFilter);
        document.getElementById('queuePatientFilter').addEventListener('input', applyQueueDetailFilter);

        document.querySelectorAll('#checkinSection [data-bs-toggle="tab"]').forEach(btn => {
            btn.addEventListener('shown.bs.tab', () => syncFilterUI());
        });

        initCameraScanner();
        initFileScanner();

        loadTodayList();
        loadDoctorQueueOverview();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', waitForSidebarThenInit);
    } else {
        waitForSidebarThenInit();
    }

    function waitForSidebarThenInit() {
        // sidebar-nhanvien.js chèn nội dung page-content vào staffContentArea bất đồng bộ;
        // đợi searchInput xuất hiện trong DOM trước khi gắn sự kiện.
        const check = setInterval(() => {
            if (document.getElementById('searchInput')) {
                clearInterval(check);
                init();
            }
        }, 50);
    }
})();