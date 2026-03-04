<?php
require_once __DIR__ . '/app-env.php';

return [
    'enabled' => true,
    'site_name' => 'Eden Health',
    'site_url' => APP_BASE_URL,
    'events' => [
        'auth_register_success' => true,
        'auth_register_otp' => true,
        'auth_forgot_otp' => true,
        'auth_password_changed' => true,

        'appointment_booked_patient' => true,
        'appointment_booked_doctor' => true,

        'appointment_cancelled_patient' => true,
        'appointment_cancelled_doctor' => true,

        'appointment_reminder_24h' => true,
        'appointment_reminder_2h' => true,

        'appointment_rescheduled_patient' => true,
        'appointment_rescheduled_doctor' => true,

        'contact_received' => true,
        'contact_processed' => true,

        'payment_success' => true,
        'payment_failed' => true,

        'medical_record_ready' => true,

        'account_locked' => true,
        'account_unlocked' => true,
    ],
];
