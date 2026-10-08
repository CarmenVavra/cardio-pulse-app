<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\PatientLoginController;
use App\Http\Controllers\Auth\StaffLoginController;
use App\Http\Controllers\Hospital\AlarmController;
use App\Http\Controllers\Hospital\BoardController;
use App\Http\Controllers\Hospital\CallController;
use App\Http\Controllers\Hospital\DemoUploadController;
use App\Http\Controllers\Hospital\ExportController;
use App\Http\Controllers\Hospital\MessageController;
use App\Http\Controllers\Hospital\MonthlyReportController;
use App\Http\Controllers\Hospital\PatientController;
use App\Http\Controllers\Hospital\PrivacyLockController;
use App\Http\Controllers\Patient\CallController as PatientCallController;
use App\Http\Controllers\Patient\DoctorController;
use App\Http\Controllers\Patient\HomeController;
use App\Http\Controllers\Patient\MeasurementController;
use App\Http\Controllers\Patient\MonthController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

/*
|--------------------------------------------------------------------------
| Anmeldung
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [StaffLoginController::class, 'create'])->name('login');
    Route::post('/login', [StaffLoginController::class, 'store'])->middleware('throttle:10,1');

    Route::get('/app/login', [PatientLoginController::class, 'create'])->name('patient.login');
    Route::post('/app/login', [PatientLoginController::class, 'store'])->middleware('throttle:10,1');
});

Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Krankenhaus (Überwachungsscreen, Desktop)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:staff'])->group(function () {
    Route::get('/ueberwachung', [BoardController::class, 'index'])->name('board');
    Route::get('/ueberwachung/live', [BoardController::class, 'live'])->name('board.live');

    Route::post('/sperre', [PrivacyLockController::class, 'store'])->name('lock');
    Route::post('/sperre/aufheben', [PrivacyLockController::class, 'destroy'])->middleware('throttle:10,1')->name('unlock');

    Route::middleware('unlocked')->group(function () {
        Route::post('/alarme/{alarm}/quittieren', [AlarmController::class, 'acknowledge'])->name('alarms.acknowledge');

        Route::get('/patienten', [PatientController::class, 'index'])->name('patients.index');
        Route::get('/patienten/{patient}', [PatientController::class, 'show'])->name('patients.show');
        Route::get('/patienten/{patient}/fhir', [ExportController::class, 'fhir'])->name('patients.fhir');
        Route::get('/patienten/{patient}/bericht', [ExportController::class, 'report'])->name('patients.report');
        Route::post('/patienten/{patient}/nachrichten', [MessageController::class, 'store'])->name('patients.messages.store');
        Route::post('/patienten/{patient}/anrufe', [CallController::class, 'store'])->name('calls.store');

        Route::get('/monatsberichte', [MonthlyReportController::class, 'index'])->name('reports.index');

        Route::get('/anrufe', [CallController::class, 'index'])->name('calls.index');
        Route::get('/anrufe/{call}', [CallController::class, 'show'])->name('calls.show');
        Route::get('/anrufe/{call}/status', [CallController::class, 'status'])->name('calls.status');
        Route::post('/anrufe/{call}/annehmen', [CallController::class, 'answer'])->name('calls.answer');
        Route::post('/anrufe/{call}/beenden', [CallController::class, 'end'])->name('calls.end');
        Route::put('/anrufe/{call}/notiz', [CallController::class, 'note'])->name('calls.note');

        Route::post('/demo/upload', DemoUploadController::class)->name('demo.upload');
    });
});

/*
|--------------------------------------------------------------------------
| Patienten-App (Smartphone)
|--------------------------------------------------------------------------
*/

Route::prefix('app')->name('patient.')->middleware(['auth', 'role:patient'])->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');

    Route::get('/messen', [MeasurementController::class, 'create'])->name('measurements.create');
    Route::post('/messungen', [MeasurementController::class, 'store'])->middleware('throttle:30,1')->name('measurements.store');
    Route::get('/messungen/{measurement}', [MeasurementController::class, 'show'])->name('measurements.show');
    Route::post('/messungen/{measurement}/beschwerdefrei', [MeasurementController::class, 'confirm'])->name('measurements.confirm');

    Route::get('/monat/{month?}', [MonthController::class, 'show'])->where('month', '\d{4}-\d{2}')->name('month');
    Route::post('/monat/{month}/senden', [MonthController::class, 'send'])->where('month', '\d{4}-\d{2}')->name('month.send');
    Route::get('/monat/{month}/pdf', [MonthController::class, 'print'])->where('month', '\d{4}-\d{2}')->name('month.print');

    Route::get('/arzt', [DoctorController::class, 'index'])->name('doctor');

    Route::post('/anrufe', [PatientCallController::class, 'store'])->name('calls.store');
    Route::get('/anrufe/aktiv', [PatientCallController::class, 'active'])->name('calls.active');
    Route::get('/anrufe/{call}', [PatientCallController::class, 'show'])->name('calls.show');
    Route::get('/anrufe/{call}/status', [PatientCallController::class, 'status'])->name('calls.status');
    Route::post('/anrufe/{call}/annehmen', [PatientCallController::class, 'answer'])->name('calls.answer');
    Route::post('/anrufe/{call}/ablehnen', [PatientCallController::class, 'decline'])->name('calls.decline');
    Route::post('/anrufe/{call}/beenden', [PatientCallController::class, 'end'])->name('calls.end');
});
