<?php

use App\Http\Controllers\Api\AccountSecurityController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AdmissionController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AttendanceRecordController;
use App\Http\Controllers\Api\AttendanceScanController;
use App\Http\Controllers\Api\AttendanceSessionController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BedController;
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
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\MedicalRecordController;
use App\Http\Controllers\Api\MedicationAdministrationController;
use App\Http\Controllers\Api\MedicineController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\NursingTaskController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PatientAssignmentController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PharmacyDashboardController;
use App\Http\Controllers\Api\PharmacyPosController;
use App\Http\Controllers\Api\PrescriptionController;
use App\Http\Controllers\Api\PublicArticleController;
use App\Http\Controllers\Api\PublicContactMessageController;
use App\Http\Controllers\Api\PublicGalleryController;
use App\Http\Controllers\Api\PublicMediaController;
use App\Http\Controllers\Api\PublicPageController;
use App\Http\Controllers\Api\PublicServiceController;
use App\Http\Controllers\Api\PublicTeamController;
use App\Http\Controllers\Api\PublicWebsiteController;
use App\Http\Controllers\Api\QueueEntryController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\ShiftTemplateController;
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
use App\Http\Controllers\Api\WardController;
use App\Http\Controllers\Api\WebsiteArticleController;
use App\Http\Controllers\Api\WebsiteContactMessageController;
use App\Http\Controllers\Api\WebsiteContentBlockController;
use App\Http\Controllers\Api\WebsiteDashboardController;
use App\Http\Controllers\Api\WebsiteGalleryController;
use App\Http\Controllers\Api\WebsiteMediaController;
use App\Http\Controllers\Api\WebsitePageController;
use App\Http\Controllers\Api\WebsiteServiceController;
use App\Http\Controllers\Api\WebsiteSettingController;
use App\Http\Controllers\Api\WebsiteTeamProfileController;
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

    // Public announcement poster image (served from private storage; 404 when absent)
    Route::get('/announcements/{announcement}/poster', [AnnouncementController::class, 'poster'])
        ->name('announcements.poster')
        ->middleware('throttle:api');

    // Public informational website (unauthenticated; read-only, published content only)
    Route::prefix('website')->middleware('throttle:api')->group(function () {
        Route::get('/bootstrap', [PublicWebsiteController::class, 'bootstrap']);
        Route::get('/home', [PublicWebsiteController::class, 'home']);
        Route::get('/pages', [PublicPageController::class, 'index']);
        Route::get('/pages/{page:slug}', [PublicPageController::class, 'show']);
        Route::get('/services', [PublicServiceController::class, 'index']);
        Route::get('/team', [PublicTeamController::class, 'index']);
        Route::get('/gallery', [PublicGalleryController::class, 'index']);
        Route::get('/articles', [PublicArticleController::class, 'index']);
        Route::get('/articles/{article:slug}', [PublicArticleController::class, 'show']);
        Route::get('/media/{mediaAsset}', [PublicMediaController::class, 'show'])->name('website.media.show');

        // Contact page intake (public, unauthenticated)
        Route::post('/contact', [PublicContactMessageController::class, 'store']);
    });

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

        // Pharmacy dashboard (stock health, expiry risk, dispensing activity)
        Route::get('/pharmacy/dashboard', PharmacyDashboardController::class);
        Route::get('/pharmacy/pos/products', [PharmacyPosController::class, 'products']);
        Route::post('/pharmacy/pos/checkout', [PharmacyPosController::class, 'store']);

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
        Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf']);
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

        // General user activity audit trail (sidebar Audit Logs page)
        Route::get('/audit-logs', [ActivityLogController::class, 'index']);
        Route::get('/audit-logs/{activityLog}', [ActivityLogController::class, 'show']);

        // Clinic announcements (management)
        Route::apiResource('announcements', AnnouncementController::class);
        Route::post('/announcements/{announcement}/publish', [AnnouncementController::class, 'publish']);
        Route::post('/announcements/{announcement}/unpublish', [AnnouncementController::class, 'unpublish']);
        Route::post('/announcements/{announcement}/schedule', [AnnouncementController::class, 'schedule']);
        Route::post('/announcements/{announcement}/archive', [AnnouncementController::class, 'archive']);
        Route::post('/announcements/{announcement}/restore', [AnnouncementController::class, 'restore']);
        Route::get('/announcements/{announcement}/readership', [AnnouncementController::class, 'readership']);
        Route::get('/announcements/{announcement}/audit', [AnnouncementController::class, 'audit']);

        // News & Updates (reader-facing, audience-filtered)
        Route::get('/news', [NewsController::class, 'index']);
        Route::get('/news/unread-count', [NewsController::class, 'unreadCount']);
        Route::post('/news/read-all', [NewsController::class, 'markAllRead']);
        Route::get('/news/{announcement}', [NewsController::class, 'show']);
        Route::post('/news/{announcement}/read', [NewsController::class, 'read']);

        // Website management (CMS)
        Route::prefix('website/admin')->group(function () {
            Route::get('/dashboard', WebsiteDashboardController::class);

            Route::get('/settings', [WebsiteSettingController::class, 'show']);
            Route::match(['put', 'patch'], '/settings', [WebsiteSettingController::class, 'update']);

            Route::apiResource('pages', WebsitePageController::class);
            Route::post('/pages/{page}/publish', [WebsitePageController::class, 'publish']);
            Route::post('/pages/{page}/unpublish', [WebsitePageController::class, 'unpublish']);

            Route::apiResource('services', WebsiteServiceController::class);
            Route::apiResource('team', WebsiteTeamProfileController::class)
                ->parameters(['team' => 'teamProfile']);
            Route::apiResource('gallery', WebsiteGalleryController::class)
                ->parameters(['gallery' => 'galleryItem']);
            Route::apiResource('articles', WebsiteArticleController::class);
            Route::post('/articles/{article}/publish', [WebsiteArticleController::class, 'publish']);
            Route::post('/articles/{article}/unpublish', [WebsiteArticleController::class, 'unpublish']);

            Route::get('/content-blocks', [WebsiteContentBlockController::class, 'index']);
            Route::get('/content-blocks/{contentBlock}', [WebsiteContentBlockController::class, 'show']);
            Route::match(['put', 'patch'], '/content-blocks/{contentBlock}', [WebsiteContentBlockController::class, 'update']);
            Route::post('/content-blocks/bulk', [WebsiteContentBlockController::class, 'bulkUpdate']);

            Route::get('/media', [WebsiteMediaController::class, 'index']);
            Route::post('/media', [WebsiteMediaController::class, 'store']);
            Route::delete('/media/{mediaAsset}', [WebsiteMediaController::class, 'destroy']);

            // Contact page inbox (messages sent from the public website)
            Route::get('/contact-messages', [WebsiteContactMessageController::class, 'index']);
            Route::get('/contact-messages/{contactMessage}', [WebsiteContactMessageController::class, 'show']);
            Route::post('/contact-messages/{contactMessage}/read', [WebsiteContactMessageController::class, 'markRead']);
            Route::post('/contact-messages/{contactMessage}/unread', [WebsiteContactMessageController::class, 'markUnread']);
            Route::delete('/contact-messages/{contactMessage}', [WebsiteContactMessageController::class, 'destroy']);
        });

        // Staff leave requests (self-service with manager approval)
        Route::get('/leave-requests', [LeaveRequestController::class, 'index']);
        Route::post('/leave-requests', [LeaveRequestController::class, 'store']);
        Route::get('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'show']);
        Route::put('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'update']);
        Route::patch('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'update']);
        Route::delete('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'destroy']);
        Route::post('/leave-requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve']);
        Route::post('/leave-requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject']);
        Route::post('/leave-requests/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel']);

        // Outpatient queue (appointment-linked waiting list)
        Route::get('/queue-entries', [QueueEntryController::class, 'index']);
        Route::get('/queue-entries/appointments', [QueueEntryController::class, 'appointments']);
        Route::post('/queue-entries', [QueueEntryController::class, 'store']);
        Route::get('/queue-entries/{queueEntry}', [QueueEntryController::class, 'show']);
        Route::put('/queue-entries/{queueEntry}', [QueueEntryController::class, 'update']);
        Route::patch('/queue-entries/{queueEntry}', [QueueEntryController::class, 'update']);
        Route::delete('/queue-entries/{queueEntry}', [QueueEntryController::class, 'destroy']);
        Route::post('/queue-entries/{queueEntry}/call', [QueueEntryController::class, 'call']);
        Route::post('/queue-entries/{queueEntry}/start', [QueueEntryController::class, 'start']);
        Route::post('/queue-entries/{queueEntry}/complete', [QueueEntryController::class, 'complete']);
        Route::post('/queue-entries/{queueEntry}/skip', [QueueEntryController::class, 'skip']);
        Route::post('/queue-entries/{queueEntry}/cancel', [QueueEntryController::class, 'cancel']);

        // Staff attendance (self-service time clock)
        Route::get('/attendance', [AttendanceRecordController::class, 'index']);
        Route::post('/attendance', [AttendanceRecordController::class, 'store']);
        Route::get('/attendance/export', [AttendanceRecordController::class, 'export']);
        Route::get('/attendance/today', [AttendanceRecordController::class, 'today']);
        Route::post('/attendance/clock-in', [AttendanceRecordController::class, 'clockIn']);
        Route::post('/attendance/clock-out', [AttendanceRecordController::class, 'clockOut']);

        // Rotating QR attendance sessions (administrator display + staff scan)
        Route::get('/attendance/sessions', [AttendanceSessionController::class, 'index']);
        Route::post('/attendance/sessions', [AttendanceSessionController::class, 'store']);
        Route::get('/attendance/sessions/{session}', [AttendanceSessionController::class, 'show']);
        Route::get('/attendance/sessions/{session}/token', [AttendanceSessionController::class, 'token']);
        Route::post('/attendance/sessions/{session}/close', [AttendanceSessionController::class, 'close']);
        Route::post('/attendance/scan', [AttendanceScanController::class, 'store'])->middleware('throttle:attendance-scan');

        Route::get('/attendance/{attendanceRecord}', [AttendanceRecordController::class, 'show']);
        Route::put('/attendance/{attendanceRecord}', [AttendanceRecordController::class, 'update']);
        Route::patch('/attendance/{attendanceRecord}', [AttendanceRecordController::class, 'update']);
        Route::post('/attendance/{attendanceRecord}/correct', [AttendanceRecordController::class, 'correct']);
        Route::post('/attendance/{attendanceRecord}/approve', [AttendanceRecordController::class, 'approve']);
        Route::delete('/attendance/{attendanceRecord}', [AttendanceRecordController::class, 'destroy']);

        // Shift templates (reusable shift definitions)
        Route::get('/shift-templates', [ShiftTemplateController::class, 'index']);
        Route::post('/shift-templates', [ShiftTemplateController::class, 'store']);
        Route::get('/shift-templates/{shiftTemplate}', [ShiftTemplateController::class, 'show']);
        Route::put('/shift-templates/{shiftTemplate}', [ShiftTemplateController::class, 'update']);
        Route::patch('/shift-templates/{shiftTemplate}', [ShiftTemplateController::class, 'update']);
        Route::delete('/shift-templates/{shiftTemplate}', [ShiftTemplateController::class, 'destroy']);

        // Staff scheduling (shift rosters)
        Route::get('/shifts', [ShiftController::class, 'index']);
        Route::post('/shifts', [ShiftController::class, 'store']);
        Route::get('/shifts/board', [ShiftController::class, 'board']);
        Route::get('/shifts/uncovered', [ShiftController::class, 'uncovered']);
        Route::post('/shifts/publish', [ShiftController::class, 'publishBulk']);
        Route::get('/shifts/{shift}', [ShiftController::class, 'show']);
        Route::put('/shifts/{shift}', [ShiftController::class, 'update']);
        Route::patch('/shifts/{shift}', [ShiftController::class, 'update']);
        Route::post('/shifts/{shift}/publish', [ShiftController::class, 'publish']);
        Route::delete('/shifts/{shift}', [ShiftController::class, 'destroy']);

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

        // Inpatient: wards/rooms/beds
        Route::apiResource('wards', WardController::class);
        Route::apiResource('rooms', RoomController::class);
        Route::apiResource('beds', BedController::class);
        Route::post('/beds/{bed}/status', [BedController::class, 'updateStatus']);

        // Admissions
        Route::apiResource('admissions', AdmissionController::class);
        Route::post('/admissions/{admission}/discharge', [AdmissionController::class, 'discharge']);
        Route::post('/admissions/{admission}/transfer', [AdmissionController::class, 'transfer']);
        Route::post('/admissions/{admission}/cancel', [AdmissionController::class, 'cancel']);

        // Nurse-patient assignments
        Route::get('/assignments', [PatientAssignmentController::class, 'index']);
        Route::post('/assignments', [PatientAssignmentController::class, 'store']);
        Route::get('/assignments/{assignment}', [PatientAssignmentController::class, 'show']);
        Route::post('/assignments/{assignment}/end', [PatientAssignmentController::class, 'end']);
        Route::match(['put', 'patch'], '/assignments/{assignment}', [PatientAssignmentController::class, 'update']);
        Route::delete('/assignments/{assignment}', [PatientAssignmentController::class, 'end']);
        Route::get('/nursing/my-patients', [PatientAssignmentController::class, 'myPatients']);

        // Nursing tasks
        Route::apiResource('nursing-tasks', NursingTaskController::class)->parameters(['nursing-tasks' => 'nursingTask']);
        Route::post('/nursing-tasks/{nursingTask}/complete', [NursingTaskController::class, 'complete']);

        // Medication administration (MAR)
        Route::apiResource('medication-administrations', MedicationAdministrationController::class);
        Route::post('/medication-administrations/{medicationAdministration}/administer', [MedicationAdministrationController::class, 'administer']);
        Route::post('/medication-administrations/{medicationAdministration}/status', [MedicationAdministrationController::class, 'updateStatus']);

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

        // Expert system evaluation audit trail (Expert page "Audit History" tab)
        Route::get('/expert-system/audit-logs', [AuditLogController::class, 'index']);
        Route::get('/expert-system/audit-logs/{auditLog}', [AuditLogController::class, 'show']);
    });
});
