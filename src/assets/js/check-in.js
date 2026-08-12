(function () {
    'use strict';

    const API_BASE_PATIENT = window.API_ENDPOINTS?.patient;

    const state = {
        patient: null,          // Bệnh nhân đang thao tác (tìm thấy hoặc vừa tạo)
        booking: {}              // Lựa chọn khoa/chuyên khoa/bác sĩ/ca/slot khi tạo lịch walk-in
    };

    let qrScanner = null;
    let qrModalInstance = null;

    function todayStr() {
        const d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
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

    // =========================================================
    // TÌM KIẾM BỆNH NHÂN
    // =========================================================

    async function doSearch() {
        const q = document.getElementById('searchInput').value.trim();
        if (!q) {
            showAlert('error', 'Vui lòng nhập mã bệnh nhân hoặc số điện thoại');
            return;
        }

        const resultArea = document.getElementById('resultArea');
        resultArea.innerHTML = '<div class="ci-panel text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Đang tìm...</div>';

        try {
            const data = await staffApi(`search-patient.php?q=${encodeURIComponent(q)}`);
            if (!data.success) {
                showAlert('error', data.message || 'Có lỗi xảy ra');
                resultArea.innerHTML = '';
                return;
            }

            if (!data.found) {
                state.patient = null;
                renderNewPatientForm(q);
                return;
            }

            state.patient = data.patient;
            const pendingAppointments = (data.appointmentsToday || []).filter(a => !a.daCheckIn);
            const checkedInAppointments = (data.appointmentsToday || []).filter(a => a.daCheckIn);

            if (pendingAppointments.length > 0 || checkedInAppointments.length > 0) {
                renderAppointmentsToday(data.patient, pendingAppointments, checkedInAppointments);
            } else {
                renderNoAppointmentYet(data.patient);
            }
        } catch (err) {
            console.error(err);
            showAlert('error', 'Không thể kết nối máy chủ');
            resultArea.innerHTML = '';
        }
    }

    // =========================================================
    // CASE A: CHƯA CÓ TÀI KHOẢN -> TẠO HỒ SƠ MỚI
    // =========================================================

    function renderNewPatientForm(searchedValue) {
        const resultArea = document.getElementById('resultArea');
        const looksLikePhone = /^[0-9+]{8,15}$/.test(searchedValue);

        resultArea.innerHTML = `
            <div class="ci-panel">
                <h5><i class="fas fa-user-plus me-2"></i>Không tìm thấy bệnh nhân — Tạo hồ sơ mới</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Họ và tên *</label>
                        <input type="text" class="form-control" id="npTen">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Số điện thoại *</label>
                        <input type="text" class="form-control" id="npSdt" value="${looksLikePhone ? escapeHtml(searchedValue) : ''}">
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
                            <label class="form-check-label" for="npTaoTaiKhoan">
                                Tạo tài khoản đăng nhập cho bệnh nhân
                            </label>
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
            state.patient = {
                maBenhNhan: data.maBenhNhan,
                tenBenhNhan: payload.tenBenhNhan,
                soDienThoai: payload.soDienThoai
            };
            renderNoAppointmentYet(state.patient);
        } catch (err) {
            console.error(err);
            showAlert('error', 'Không thể kết nối máy chủ');
            btn.disabled = false;
        }
    }

    // =========================================================
    // HIỂN THỊ LỊCH HÔM NAY (Case C) + fallback sang Case B nếu không có
    // =========================================================

    function renderAppointmentsToday(patient, pending, checkedIn) {
        const resultArea = document.getElementById('resultArea');
        let html = `
            <div class="ci-panel">
                <h5><i class="fas fa-user-check me-2"></i>Đã tìm thấy bệnh nhân</h5>
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
                        <div>
                            <strong>BS. ${escapeHtml(a.tenBacSi)}</strong> — ${escapeHtml(a.tenCa)} (${a.gioBatDau} - ${a.gioKetThuc})
                        </div>
                        <button class="btn btn-primary-ci btn-sm" onclick="window.__checkInAppointment(${a.maLichKham}, this)">
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
            <div class="ci-panel">
                <h5><i class="fas fa-user-check me-2"></i>Bệnh nhân chưa có lịch hôm nay</h5>
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
    // CASE C: CHECK-IN LỊCH CÓ SẴN
    // =========================================================

    window.__checkInAppointment = async function (maLichKham, btnEl) {
        btnEl.disabled = true;
        btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        try {
            const data = await staffApi('check-in.php', { method: 'POST', body: JSON.stringify({ maLichKham }) });
            if (!data.success) {
                showAlert('error', data.message || 'Check-in thất bại');
                btnEl.disabled = false;
                btnEl.innerHTML = '<i class="fas fa-door-open me-1"></i> Check-in ngay';
                return;
            }
            showCheckInSuccess(data);
            loadQueueOverview();
        } catch (err) {
            console.error(err);
            showAlert('error', 'Không thể kết nối máy chủ');
            btnEl.disabled = false;
        }
    };

    // =========================================================
    // CASE A/B: TẠO LỊCH WALK-IN (chọn khoa -> chuyên khoa -> bác sĩ -> ca -> slot)
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

            showCheckInSuccess(checkinData);
            loadQueueOverview();
        } catch (err) {
            console.error(err);
            showAlert('error', 'Không thể kết nối máy chủ');
            btn.disabled = false;
        }
    }

    function showCheckInSuccess(data) {
        const resultArea = document.getElementById('resultArea');
        const doctorName = state.booking.tenBacSi || '';
        resultArea.innerHTML = `
            <div class="ci-panel text-center">
                <div class="stt-badge mx-auto mb-3">${data.soThuTu}</div>
                <h5 class="mb-1">Check-in thành công!</h5>
                <p class="text-muted mb-0">
                    ${doctorName ? 'Bác sĩ ' + escapeHtml(doctorName) + ' • ' : ''}
                    Số thứ tự <strong>${data.soThuTu}</strong>
                    ${data.soNguoiTruoc > 0 ? ` — còn <strong>${data.soNguoiTruoc}</strong> người đang chờ trước` : ' — bệnh nhân tiếp theo'}
                </p>
                <button class="btn btn-outline-secondary btn-sm mt-3" onclick="location.reload()">
                    <i class="fas fa-redo me-1"></i> Check-in bệnh nhân khác
                </button>
            </div>
        `;
    }

    // =========================================================
    // HÀNG ĐỢI HÔM NAY
    // =========================================================

    async function loadQueueOverview() {
        const el = document.getElementById('queueOverview');
        try {
            const data = await staffApi('get-queue.php');
            if (!data.success) { el.innerHTML = '<span class="text-danger">Không tải được hàng đợi</span>'; return; }

            if (data.data.length === 0) {
                el.innerHTML = '<span class="text-muted">Chưa có bác sĩ nào</span>';
                return;
            }

            el.innerHTML = `<div class="step-select">` + data.data.map(bs => `
                <div class="choice-chip" data-id="${bs.maBacSi}" onclick="window.__loadQueueDetail('${bs.maBacSi}', this)">
                    BS. ${escapeHtml(bs.tenBacSi)}
                    <span class="badge bg-warning text-dark ms-1">${bs.soDangCho} chờ</span>
                    ${bs.soDangKham > 0 ? `<span class="badge bg-info text-dark ms-1">${bs.soDangKham} đang khám</span>` : ''}
                </div>
            `).join('') + `</div>`;
        } catch (err) {
            console.error(err);
            el.innerHTML = '<span class="text-danger">Không thể kết nối máy chủ</span>';
        }
    }

    window.__loadQueueDetail = async function (maBacSi, el) {
        highlightChip(el);
        const container = document.getElementById('queueDetail');
        container.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        const data = await staffApi(`get-queue.php?maBacSi=${encodeURIComponent(maBacSi)}`);
        if (!data.success) { container.innerHTML = '<span class="text-danger">Lỗi tải hàng đợi</span>'; return; }

        if (data.data.length === 0) {
            container.innerHTML = '<span class="text-muted">Chưa có bệnh nhân nào trong hàng đợi</span>';
            return;
        }

        container.innerHTML = `
            <table class="table queue-table">
                <thead>
                    <tr><th>STT</th><th>Bệnh nhân</th><th>Giờ hẹn</th><th>Nguồn</th><th>Trạng thái</th></tr>
                </thead>
                <tbody>
                    ${data.data.map(q => `
                        <tr>
                            <td><strong>${q.soThuTu}</strong></td>
                            <td>${escapeHtml(q.tenBenhNhan)} <span class="text-muted small">(${escapeHtml(q.maBenhNhan)})</span></td>
                            <td>${q.gioHen}</td>
                            <td>${q.nguon === 'truc_tiep' ? 'Trực tiếp' : 'Online'}</td>
                            <td><span class="badge ${badgeClassFor(q.trangThai)}">${q.trangThai}</span></td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    };

    // =========================================================
    // QUÉT QR
    // =========================================================

    function initQrScanner() {
        const modalEl = document.getElementById('qrScannerModal');
        qrModalInstance = new bootstrap.Modal(modalEl);

        document.getElementById('btnScanQr').addEventListener('click', async () => {
            qrModalInstance.show();
            qrScanner = new Html5Qrcode('qrReader');
            try {
                await qrScanner.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: 220 },
                    (decodedText) => {
                        document.getElementById('searchInput').value = decodedText.trim();
                        stopQrScanner();
                        qrModalInstance.hide();
                        doSearch();
                    },
                    () => {}
                );
            } catch (err) {
                console.error(err);
                showAlert('error', 'Không thể mở camera. Vui lòng kiểm tra quyền truy cập camera.');
            }
        });

        modalEl.addEventListener('hidden.bs.modal', stopQrScanner);
    }

    function stopQrScanner() {
        if (qrScanner) {
            qrScanner.stop().catch(() => {}).finally(() => {
                qrScanner.clear();
                qrScanner = null;
            });
        }
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
        initQrScanner();
        loadQueueOverview();
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