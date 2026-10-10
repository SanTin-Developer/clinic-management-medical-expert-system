<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VitalSignController;
use App\Http\Controllers\Api\DiagnosisController;
use App\Http\Controllers\Api\ExpertSystemController;
use App\Http\Controllers\Api\MedicalRecordController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Authentication routes
    Route::post('/auth/login', [AuthController::class, 'login']);

    //Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // User
        Route::apiResource('users', UserController::class);

        // Patient
        Route::apiResource('patients', PatientController::class);

        // Appointments
        Route::get('/appointments', [AppointmentController::class, 'index']);
        Route::post('/appointments', [AppointmentController::class, 'store']);
        Route::get('/appointments/{appointment}', [AppointmentController::class, 'show']);
        Route::put('/appointments/{appointment}', [AppointmentController::class, 'update']);
        Route::patch('/appointments/{appointment}', [AppointmentController::class, 'update']);
        Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);

        // Vital Signs
        Route::get('/vital-signs', [VitalSignController::class, 'index']);
        Route::post('/vital-signs', [VitalSignController::class, 'store']);
        Route::get('/vital-signs/{vitalSign}', [VitalSignController::class, 'show']);
        Route::put('/vital-signs/{vitalSign}', [VitalSignController::class, 'update']);
        Route::patch('/vital-signs/{vitalSign}', [VitalSignController::class, 'update']);

        // Consultation
        Route::get('/consultations', [ConsultationController::class, 'index']);
        Route::post('/consultations', [ConsultationController::class, 'store']);
        Route::get('/consultations/{consultation}', [ConsultationController::class, 'show']);
        Route::put('/consultations/{consultation}', [ConsultationController::class, 'update']);
        Route::patch('/consultations/{consultation}', [ConsultationController::class, 'update']);

        // Diagnosis
        Route::get('/diagnoses', [DiagnosisController::class, 'index']);
        Route::post('/diagnoses', [DiagnosisController::class, 'store']);
        Route::get('/diagnoses/{diagnosis}', [DiagnosisController::class, 'show']);
        Route::put('/diagnoses/{diagnosis}', [DiagnosisController::class, 'update']);
        Route::patch('/diagnoses/{diagnosis}', [DiagnosisController::class, 'update']);

        // Medical Record
        Route::get('/medical-records', [MedicalRecordController::class, 'index']);
        Route::post('/medical-records', [MedicalRecordController::class, 'store']);
        Route::get('/medical-records/{medicalRecord}', [MedicalRecordController::class, 'show']);
        Route::put('/medical-records/{medicalRecord}', [MedicalRecordController::class, 'update']);
        Route::patch('/medical-records/{medicalRecord}', [MedicalRecordController::class, 'update']);

        // Medical Expert System (proxied to the internal Python service)
        Route::post('/expert-system/evaluate', [ExpertSystemController::class, 'evaluate']);
    });
});
