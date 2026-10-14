<?php

use App\Http\Controllers\Api\AccountSecurityController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DiagnosisController;
use App\Http\Controllers\Api\DiseaseController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\ExpertRuleController;
use App\Http\Controllers\Api\ExpertSystemController;
use App\Http\Controllers\Api\InventoryItemController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\MedicalRecordController;
use App\Http\Controllers\Api\MedicineController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PrescriptionController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\StaffApprovalController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\StaffDocumentController;
use App\Http\Controllers\Api\StaffInvitationController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\SymptomController;
use App\Http\Controllers\Api\TelegramController;
use App\Http\Controllers\Api\TwoFactorController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VitalSignController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Authentication routes
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:otp');
    Route::post('/auth/otp/resend', [AuthController::class, 'resendOtp'])->middleware('throttle:otp');

    // Two-factor challenge (issued after a valid password when mandatory)
    Route::post('/auth/2fa/verify', [AuthController::class, 'verifyTwoFactor'])->middleware('throttle:two-factor');

    // Staff invitation acceptance (public; the token is delivered by email)
    Route::post('/auth/invitation/lookup', [InvitationController::class, 'lookup'])->middleware('throttle:invitation');
    Route::post('/auth/invitation/accept', [InvitationController::class, 'accept'])->middleware('throttle:invitation');

    // Password reset
    Route::post('/auth/forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:password-reset');
    Route::post('/auth/reset-password/verify-code', [PasswordResetController::class, 'verifyCode'])->middleware('throttle:password-reset');
    Route::post('/auth/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:password-reset');

    // Email verification (signed link)
    Route::get('/auth/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:api'])
        ->name('verification.verify');

    // Telegram webhook (public; authenticated via the X-Telegram-Bot-Api-Secret-Token header)
    Route::post('/telegram/webhook', [TelegramController::class, 'webhook'])->middleware('throttle:telegram-webhook');

    // Protected routes
    $protectedMiddleware = ['auth:sanctum', 'throttle:api', 'account.active', 'password.current'];

    if (config('auth.require_verified_email')) {
        $protectedMiddleware[] = 'verified';
    }

    if (config('auth.otp.enforce_trusted_device')) {
        $protectedMiddleware[] = 'device.trust';
    }

    if (config('auth.two_factor.mandatory')) {
        $protectedMiddleware[] = '2fa.enrolled';
    }

    Route::middleware($protectedMiddleware)->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Account security: password change, two-stage email change, step-up
        Route::put('/auth/password', [AccountSecurityController::class, 'changePassword']);
        Route::post('/auth/email/change', [AccountSecurityController::class, 'requestEmailChange']);
        Route::post('/auth/email/change/confirm', [AccountSecurityController::class, 'confirmEmailChange']);
        Route::post('/auth/step-up', [AccountSecurityController::class, 'stepUp']);
        Route::get('/auth/security-events', [AccountSecurityController::class, 'securityEvents']);

        // Two-factor (TOTP) management
        Route::get('/auth/2fa/status', [TwoFactorController::class, 'status']);
        Route::post('/auth/2fa/enroll', [TwoFactorController::class, 'enroll']);
        Route::post('/auth/2fa/confirm', [TwoFactorController::class, 'confirm']);
        Route::delete('/auth/2fa', [TwoFactorController::class, 'disable']);
        Route::post('/auth/2fa/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes']);

        // Staff provisioning
        Route::get('/staff/invitations', [StaffInvitationController::class, 'index']);
        Route::post('/staff/invitations', [StaffInvitationController::class, 'store']);
        Route::post('/staff/invitations/{invitation}/resend', [StaffInvitationController::class, 'resend']);
        Route::delete('/staff/invitations/{invitation}', [StaffInvitationController::class, 'destroy']);

        // Staff registration approval queue (pending_approval accounts)
        Route::get('/staff/approvals', [StaffApprovalController::class, 'index']);

        // Global search (permission-aware per resource)
        Route::get('/search', [SearchController::class, 'index']);

        // Trusted devices ("remember this device")
        Route::get('/auth/devices', [AuthController::class, 'devices']);
        Route::delete('/auth/devices/{device}', [AuthController::class, 'revokeDevice']);
        Route::delete('/auth/devices', [AuthController::class, 'revokeAllDevices']);

        // Email verification resend
        Route::post('/auth/email/verification-notification', [EmailVerificationController::class, 'resend']);

        // User
        Route::apiResource('users', UserController::class);
        Route::post('/users/{user}/suspend', [UserController::class, 'suspend'])->middleware('step.up:users.suspend');
        Route::post('/users/{user}/activate', [UserController::class, 'activate']);
        Route::post('/users/{user}/disable', [UserController::class, 'disable'])->middleware('step.up:users.suspend');
        Route::post('/users/{user}/approve', [StaffApprovalController::class, 'approve'])->middleware('step.up:users.approve');
        Route::post('/users/{user}/reject', [StaffApprovalController::class, 'reject'])->middleware('step.up:users.approve');

        // Staff Directory
        Route::get('/staff/departments', [StaffController::class, 'departments']);
        Route::get('/staff/job-titles', [StaffController::class, 'jobTitles']);
        Route::get('/staff/available-users', [StaffController::class, 'availableUsers']);
        Route::apiResource('staff', StaffController::class);
        Route::post('/staff/{staff}/salary', [StaffController::class, 'updateSalary']);
        Route::post('/staff/{staff}/link-user', [StaffController::class, 'linkUser']);
        Route::delete('/staff/{staff}/unlink-user', [StaffController::class, 'unlinkUser']);

        // Staff Documents
        Route::get('/staff/{staff}/documents', [StaffDocumentController::class, 'index']);
        Route::post('/staff/{staff}/documents', [StaffDocumentController::class, 'store']);
        Route::get('/staff-documents/{document}/download', [StaffDocumentController::class, 'download']);
        Route::delete('/staff-documents/{document}', [StaffDocumentController::class, 'destroy']);

        // Patient
        Route::apiResource('patients', PatientController::class);

        // Patient documents
        Route::get('/patients/{patient}/documents', [DocumentController::class, 'index']);
        Route::post('/patients/{patient}/documents', [DocumentController::class, 'store']);
        Route::get('/documents/{document}/download', [DocumentController::class, 'download']);
        Route::delete('/documents/{document}', [DocumentController::class, 'destroy']);

        // Appointments
        Route::get('/appointments', [AppointmentController::class, 'index']);
        Route::post('/appointments', [AppointmentController::class, 'store']);
        Route::get('/appointments/{appointment}', [AppointmentController::class, 'show']);
        Route::put('/appointments/{appointment}', [AppointmentController::class, 'update']);
        Route::patch('/appointments/{appointment}', [AppointmentController::class, 'update']);
        Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
        Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy']);

        // Vital Signs
        Route::get('/vital-signs', [VitalSignController::class, 'index']);
        Route::post('/vital-signs', [VitalSignController::class, 'store']);
        Route::get('/vital-signs/{vitalSign}', [VitalSignController::class, 'show']);
        Route::put('/vital-signs/{vitalSign}', [VitalSignController::class, 'update']);
        Route::patch('/vital-signs/{vitalSign}', [VitalSignController::class, 'update']);
        Route::delete('/vital-signs/{vitalSign}', [VitalSignController::class, 'destroy']);

        // Consultation
        Route::get('/consultations', [ConsultationController::class, 'index']);
        Route::post('/consultations', [ConsultationController::class, 'store']);
        Route::get('/consultations/{consultation}', [ConsultationController::class, 'show']);
        Route::put('/consultations/{consultation}', [ConsultationController::class, 'update']);
        Route::patch('/consultations/{consultation}', [ConsultationController::class, 'update']);
        Route::delete('/consultations/{consultation}', [ConsultationController::class, 'destroy']);

        // Diagnosis
        Route::get('/diagnoses', [DiagnosisController::class, 'index']);
        Route::post('/diagnoses', [DiagnosisController::class, 'store']);
        Route::get('/diagnoses/{diagnosis}', [DiagnosisController::class, 'show']);
        Route::put('/diagnoses/{diagnosis}', [DiagnosisController::class, 'update']);
        Route::patch('/diagnoses/{diagnosis}', [DiagnosisController::class, 'update']);
        Route::delete('/diagnoses/{diagnosis}', [DiagnosisController::class, 'destroy']);

        // Medical Record
        Route::get('/medical-records', [MedicalRecordController::class, 'index']);
        Route::post('/medical-records', [MedicalRecordController::class, 'store']);
        Route::get('/medical-records/{medicalRecord}', [MedicalRecordController::class, 'show']);
        Route::put('/medical-records/{medicalRecord}', [MedicalRecordController::class, 'update']);
        Route::patch('/medical-records/{medicalRecord}', [MedicalRecordController::class, 'update']);
        Route::delete('/medical-records/{medicalRecord}', [MedicalRecordController::class, 'destroy']);

        // Medicines (pharmacy catalog)
        Route::apiResource('medicines', MedicineController::class);

        // Pharmacy inventory
        Route::get('/inventory-items', [InventoryItemController::class, 'index']);
        Route::post('/inventory-items', [InventoryItemController::class, 'store']);
        Route::get('/inventory-items/{inventoryItem}', [InventoryItemController::class, 'show']);
        Route::put('/inventory-items/{inventoryItem}', [InventoryItemController::class, 'update']);
        Route::patch('/inventory-items/{inventoryItem}', [InventoryItemController::class, 'update']);
        Route::delete('/inventory-items/{inventoryItem}', [InventoryItemController::class, 'destroy']);

        Route::get('/stock-movements', [StockMovementController::class, 'index']);
        Route::post('/stock-movements', [StockMovementController::class, 'store']);

        // Prescriptions
        Route::get('/prescriptions', [PrescriptionController::class, 'index']);
        Route::post('/prescriptions', [PrescriptionController::class, 'store']);
        Route::get('/prescriptions/{prescription}', [PrescriptionController::class, 'show']);
        Route::put('/prescriptions/{prescription}', [PrescriptionController::class, 'update']);
        Route::patch('/prescriptions/{prescription}', [PrescriptionController::class, 'update']);
        Route::post('/prescriptions/{prescription}/cancel', [PrescriptionController::class, 'cancel']);
        Route::post('/prescriptions/{prescription}/dispense', [PrescriptionController::class, 'dispense']);
        Route::delete('/prescriptions/{prescription}', [PrescriptionController::class, 'destroy']);

        // Billing
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::post('/invoices', [InvoiceController::class, 'store']);
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
        Route::put('/invoices/{invoice}', [InvoiceController::class, 'update']);
        Route::patch('/invoices/{invoice}', [InvoiceController::class, 'update']);
        Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment']);
        Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel']);
        Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy']);

        // Dashboard analytics (single endpoint backing the dashboard charts)
        Route::get('/dashboard/analytics', [DashboardController::class, 'analytics']);

        // Reports
        Route::get('/reports/summary', [ReportController::class, 'summary']);
        Route::get('/reports/appointments', [ReportController::class, 'appointments']);
        Route::get('/reports/revenue', [ReportController::class, 'revenue']);
        Route::get('/reports/inventory', [ReportController::class, 'inventory']);
        Route::get('/reports/{report}/export', [ReportController::class, 'export']);
        Route::get('/reports/{report}/pdf', [ReportController::class, 'pdf']);

        // Knowledge Base management
        Route::prefix('knowledge-base')->group(function () {
            Route::apiResource('diseases', DiseaseController::class);
            Route::apiResource('symptoms', SymptomController::class);
            Route::apiResource('expert-rules', ExpertRuleController::class)
                ->parameters(['expert-rules' => 'expertRule']);
        });

        // Expert system evaluation audit trail
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
        Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show']);

        // Staff communication — direct & group conversations
        Route::get('/conversations/recipients', [ConversationController::class, 'recipients']);
        Route::get('/conversations', [ConversationController::class, 'index']);
        Route::post('/conversations', [ConversationController::class, 'store']);
        Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
        Route::get('/conversations/{conversation}/messages', [ConversationController::class, 'messages']);
        Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'sendMessage']);
        Route::get('/conversations/{conversation}/messages/{message}/delivery', [ConversationController::class, 'messageDelivery']);
        Route::post('/conversations/{conversation}/read', [ConversationController::class, 'markRead']);
        Route::post('/conversations/{conversation}/participants', [ConversationController::class, 'addParticipants']);
        Route::delete('/conversations/{conversation}/participants/{user}', [ConversationController::class, 'removeParticipant']);

        // In-app notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);
        Route::get('/notification-preferences', [NotificationController::class, 'preferences']);
        Route::put('/notification-preferences', [NotificationController::class, 'updatePreferences']);

        // Telegram linking & group notifications
        Route::get('/telegram/status', [TelegramController::class, 'status']);
        Route::post('/telegram/link', [TelegramController::class, 'link']);
        Route::delete('/telegram/link', [TelegramController::class, 'unlink']);
        Route::post('/telegram/webhook/register', [TelegramController::class, 'linkWebhook']);
        Route::delete('/telegram/webhook/register', [TelegramController::class, 'unregisterWebhook']);
        Route::get('/telegram/group-config', [TelegramController::class, 'groupConfig']);
        Route::put('/telegram/group-config', [TelegramController::class, 'updateGroupConfig']);

        // Medical Expert System (proxied to the internal Python service)
        Route::post('/expert-system/evaluate', [ExpertSystemController::class, 'evaluate']);
    });
});
