<?php
/**
 * Contract trạng thái và quy tắc chuyển trạng thái cho quy trình khám chữa bệnh.
 * Dùng chung cho các API bác sĩ, nhân viên CLS, dược sĩ, thu ngân và bệnh nhân.
 * Tham chiếu đặc tả đầy đủ tại: docs/clinical-workflow.md
 */

// 1. Trạng thái quy trình hồ sơ bệnh án (hosobenhan.trangThaiQuyTrinh)
const CLINICAL_RECORD_STATUS_EXAMINING = 'Đang khám';
const CLINICAL_RECORD_STATUS_WAITING_CLS = 'Chờ thực hiện CLS';
const CLINICAL_RECORD_STATUS_WAITING_RESULT = 'Chờ kết quả';
const CLINICAL_RECORD_STATUS_WAITING_CONCLUSION = 'Chờ bác sĩ kết luận';
const CLINICAL_RECORD_STATUS_COMPLETED = 'Hoàn tất';
const CLINICAL_RECORD_STATUS_CANCELLED = 'Hủy';

// Trạng thái legacy của hosobenhan.trangThai
const CLINICAL_RECORD_LEGACY_PENDING = 'Chưa hoàn thành';
const CLINICAL_RECORD_LEGACY_COMPLETED = 'Đã hoàn thành';

// 2. Trạng thái chi tiết chỉ định cận lâm sàng (chi_dinh_chi_tiet.trangThai)
const CLINICAL_ORDER_STATUS_CREATED = 'Mới tạo';
const CLINICAL_ORDER_STATUS_WAITING = 'Đang chờ';
const CLINICAL_ORDER_STATUS_IN_PROGRESS = 'Đang thực hiện';
const CLINICAL_ORDER_STATUS_RESULTED = 'Có kết quả';
const CLINICAL_ORDER_STATUS_APPROVED = 'Đã duyệt';
const CLINICAL_ORDER_STATUS_CANCELLED = 'Hủy';

// 3. Trạng thái hàng đợi dịch vụ cận lâm sàng (hangdoi_dichvu.trangThai)
const CLINICAL_QUEUE_STATUS_WAITING = 'Đang chờ';
const CLINICAL_QUEUE_STATUS_IN_PROGRESS = 'Đang thực hiện';
const CLINICAL_QUEUE_STATUS_COMPLETED = 'Hoàn thành';
const CLINICAL_QUEUE_STATUS_MISSED = 'Bỏ lỡ';
const CLINICAL_QUEUE_STATUS_CANCELLED = 'Hủy';

// 4. Trạng thái kết quả cận lâm sàng (ket_qua_can_lam_sang.trangThai)
const CLINICAL_RESULT_STATUS_DRAFT = 'Nháp';
const CLINICAL_RESULT_STATUS_PENDING_REVIEW = 'Chờ duyệt';
const CLINICAL_RESULT_STATUS_APPROVED = 'Đã duyệt';
const CLINICAL_RESULT_STATUS_REDO_REQUESTED = 'Yêu cầu làm lại';

// 5. Trạng thái phát đơn thuốc (donthuoc.trangThaiPhat)
const PRESCRIPTION_DISPENSE_PRESCRIBED = 'Đã kê';
const PRESCRIPTION_DISPENSE_WAITING = 'Chờ phát';
const PRESCRIPTION_DISPENSE_PARTIAL = 'Đã phát một phần';
const PRESCRIPTION_DISPENSE_FULL = 'Đã phát đủ';
const PRESCRIPTION_DISPENSE_CANCELLED = 'Hủy';

// 6. Nhóm dịch vụ cận lâm sàng và quyền thực hiện tương ứng
const CLINICAL_SERVICE_GROUPS = [
    'xet-nghiem' => [
        'code' => 'xet-nghiem',
        'label' => 'Xét nghiệm',
        'permission' => 'cls.xet_nghiem.perform',
    ],
    'chan-doan-hinh-anh' => [
        'code' => 'chan-doan-hinh-anh',
        'label' => 'Chẩn đoán hình ảnh',
        'permission' => 'cls.cdha.perform',
    ],
    'noi-soi' => [
        'code' => 'noi-soi',
        'label' => 'Nội soi',
        'permission' => 'cls.noi_soi.perform',
    ],
];

function clinical_record_transitions(): array
{
    return [
        CLINICAL_RECORD_STATUS_EXAMINING => [
            CLINICAL_RECORD_STATUS_EXAMINING,
            CLINICAL_RECORD_STATUS_WAITING_CLS,
            CLINICAL_RECORD_STATUS_COMPLETED,
            CLINICAL_RECORD_STATUS_CANCELLED,
        ],
        CLINICAL_RECORD_STATUS_WAITING_CLS => [
            CLINICAL_RECORD_STATUS_WAITING_CLS,
            CLINICAL_RECORD_STATUS_WAITING_RESULT,
            CLINICAL_RECORD_STATUS_WAITING_CONCLUSION,
            CLINICAL_RECORD_STATUS_CANCELLED,
        ],
        CLINICAL_RECORD_STATUS_WAITING_RESULT => [
            CLINICAL_RECORD_STATUS_WAITING_RESULT,
            CLINICAL_RECORD_STATUS_WAITING_CLS,
            CLINICAL_RECORD_STATUS_WAITING_CONCLUSION,
            CLINICAL_RECORD_STATUS_CANCELLED,
        ],
        CLINICAL_RECORD_STATUS_WAITING_CONCLUSION => [
            CLINICAL_RECORD_STATUS_WAITING_CONCLUSION,
            CLINICAL_RECORD_STATUS_EXAMINING,
            CLINICAL_RECORD_STATUS_WAITING_CLS,
            CLINICAL_RECORD_STATUS_COMPLETED,
            CLINICAL_RECORD_STATUS_CANCELLED,
        ],
        CLINICAL_RECORD_STATUS_COMPLETED => [],
        CLINICAL_RECORD_STATUS_CANCELLED => [],
    ];
}

function clinical_order_transitions(): array
{
    return [
        CLINICAL_ORDER_STATUS_CREATED => [
            CLINICAL_ORDER_STATUS_WAITING,
            CLINICAL_ORDER_STATUS_CANCELLED,
        ],
        CLINICAL_ORDER_STATUS_WAITING => [
            CLINICAL_ORDER_STATUS_IN_PROGRESS,
            CLINICAL_ORDER_STATUS_CANCELLED,
        ],
        CLINICAL_ORDER_STATUS_IN_PROGRESS => [
            CLINICAL_ORDER_STATUS_RESULTED,
        ],
        CLINICAL_ORDER_STATUS_RESULTED => [
            CLINICAL_ORDER_STATUS_APPROVED,
            CLINICAL_ORDER_STATUS_IN_PROGRESS,
        ],
        CLINICAL_ORDER_STATUS_APPROVED => [],
        CLINICAL_ORDER_STATUS_CANCELLED => [],
    ];
}

function clinical_queue_transitions(): array
{
    return [
        CLINICAL_QUEUE_STATUS_WAITING => [
            CLINICAL_QUEUE_STATUS_IN_PROGRESS,
            CLINICAL_QUEUE_STATUS_MISSED,
            CLINICAL_QUEUE_STATUS_CANCELLED,
        ],
        CLINICAL_QUEUE_STATUS_MISSED => [
            CLINICAL_QUEUE_STATUS_WAITING,
            CLINICAL_QUEUE_STATUS_CANCELLED,
        ],
        CLINICAL_QUEUE_STATUS_IN_PROGRESS => [
            CLINICAL_QUEUE_STATUS_COMPLETED,
        ],
        CLINICAL_QUEUE_STATUS_COMPLETED => [],
        CLINICAL_QUEUE_STATUS_CANCELLED => [],
    ];
}

function clinical_result_transitions(): array
{
    return [
        CLINICAL_RESULT_STATUS_DRAFT => [
            CLINICAL_RESULT_STATUS_DRAFT,
            CLINICAL_RESULT_STATUS_PENDING_REVIEW,
        ],
        CLINICAL_RESULT_STATUS_PENDING_REVIEW => [
            CLINICAL_RESULT_STATUS_APPROVED,
            CLINICAL_RESULT_STATUS_REDO_REQUESTED,
        ],
        CLINICAL_RESULT_STATUS_REDO_REQUESTED => [
            CLINICAL_RESULT_STATUS_PENDING_REVIEW,
        ],
        CLINICAL_RESULT_STATUS_APPROVED => [],
    ];
}

function prescription_dispense_transitions(): array
{
    return [
        PRESCRIPTION_DISPENSE_PRESCRIBED => [
            PRESCRIPTION_DISPENSE_PRESCRIBED,
            PRESCRIPTION_DISPENSE_WAITING,
            PRESCRIPTION_DISPENSE_CANCELLED,
        ],
        PRESCRIPTION_DISPENSE_WAITING => [
            PRESCRIPTION_DISPENSE_PARTIAL,
            PRESCRIPTION_DISPENSE_FULL,
            PRESCRIPTION_DISPENSE_CANCELLED,
        ],
        PRESCRIPTION_DISPENSE_PARTIAL => [
            PRESCRIPTION_DISPENSE_PARTIAL,
            PRESCRIPTION_DISPENSE_FULL,
        ],
        PRESCRIPTION_DISPENSE_FULL => [],
        PRESCRIPTION_DISPENSE_CANCELLED => [],
    ];
}

function clinical_can_transition(array $transitionMap, string $from, string $to): bool
{
    return isset($transitionMap[$from]) && in_array($to, $transitionMap[$from], true);
}

function clinical_map_legacy_record_status(string $workflowStatus): string
{
    return $workflowStatus === CLINICAL_RECORD_STATUS_COMPLETED
        ? CLINICAL_RECORD_LEGACY_COMPLETED
        : CLINICAL_RECORD_LEGACY_PENDING;
}

/**
 * Kiểm tra điều kiện hoàn tất hồ sơ bệnh án trước khi khóa sang Hoàn tất.
 *
 * @param array<int, array{trangThai:string, batBuoc?:int|bool}> $orders
 * @return array{ok:bool, reason:?string}
 */
function clinical_validate_record_completion(array $orders, string $chanDoan, string $dieuTri): array
{
    if (trim($chanDoan) === '' || trim($dieuTri) === '') {
        return [
            'ok' => false,
            'reason' => 'Vui lòng nhập đầy đủ chẩn đoán và hướng điều trị trước khi hoàn tất hồ sơ.',
        ];
    }

    $unapprovedMandatory = 0;
    $unfinishedOptional = 0;

    foreach ($orders as $order) {
        $status = (string)($order['trangThai'] ?? '');
        if ($status === CLINICAL_ORDER_STATUS_CANCELLED || $status === CLINICAL_ORDER_STATUS_APPROVED) {
            continue;
        }

        $isMandatory = !isset($order['batBuoc']) || (bool)$order['batBuoc'];
        if ($isMandatory) {
            $unapprovedMandatory++;
        } else {
            $unfinishedOptional++;
        }
    }

    if ($unapprovedMandatory > 0) {
        return [
            'ok' => false,
            'reason' => "Còn {$unapprovedMandatory} chỉ định cận lâm sàng bắt buộc chưa được duyệt kết quả.",
        ];
    }

    if ($unfinishedOptional > 0) {
        return [
            'ok' => false,
            'reason' => "Còn {$unfinishedOptional} chỉ định cận lâm sàng chưa xử lý; vui lòng duyệt kết quả hoặc hủy chỉ định trước khi hoàn tất.",
        ];
    }

    return ['ok' => true, 'reason' => null];
}

function clinical_service_group_permission(string $nhomDichVu): ?string
{
    return CLINICAL_SERVICE_GROUPS[$nhomDichVu]['permission'] ?? null;
}
