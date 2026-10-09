<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\PatientLoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\StaffLoginController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\CallSignalController;
use App\Http\Controllers\Hospital\AccountController;
use App\Http\Controllers\Hospital\AlarmController;
use App\Http\Controllers\Hospital\AppointmentController;
use App\Http\Controllers\Hospital\AuditLogController;
use App\Http\Controllers\Hospital\BoardController;
use App\Http\Controllers\Hospital\CallController;
use App\Http\Controllers\Hospital\DemoUploadController;
use App\Http\Controllers\Hospital\DoctorController as HospitalDoctorController;
use App\Http\Controllers\Hospital\ExportController;
use App\Http\Controllers\Hospital\MedicationController;
use App\Http\Controllers\Hospital\MessageController;
use App\Http\Controllers\Hospital\MonthlyReportController;
use App\Http\Controllers\Hospital\PatientController;
use App\Http\Controllers\Hospital\PrivacyLockController;
use App\Http\Controllers\Hospital\TwoFactorController;
use App\Http\Controllers\Patient\AccountController as PatientAccountController;
use App\Http\Controllers\Patient\AppointmentController as PatientAppointmentController;
use App\Http\Controllers\Patient\CallController as PatientCallController;
use App\Http\Controllers\Patient\DoctorController;
use App\Http\Controllers\Patient\EmergencyController;
use App\Http\Controllers\Patient\HomeController;
use App\Http\Controllers\Patient\MeasurementController;
use App\Http\Controllers\Patient\MedicationController as PatientMedicationController;
use App\Http\Controllers\Patient\MessageController as PatientMessageController;
use App\Http\Controllers\Patient\MonthController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

/*
|--------------------------------------------------------------------------
| Anmeldung
|--------------------------------------------------------------------------
*/

// Hinweisseite ohne Internet – der Service Worker der installierten App speichert sie vorab.
Route::view('/app/offline', 'patient.offline')->name('patient.offline');

Route::middleware('guest')->group(function () {
    Route::get('/login', [StaffLoginController::class, 'create'])->name('login');
    Route::post('/login', [StaffLoginController::class, 'store'])->middleware('throttle:10,1');

    Route::get('/login/zwei-faktor', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
    Route::post('/login/zwei-faktor', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:10,1');

    Route::get('/app/login', [PatientLoginController::class, 'create'])->name('patient.login');
    Route::post('/app/login', [PatientLoginController::class, 'store'])->middleware('throttle:10,1');

    // Passwort vergessen: Link per E-Mail (Ärzte und Patienten mit eigener Ansicht).
    Route::get('/passwort-vergessen', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/passwort-vergessen', [ForgotPasswordController::class, 'store'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/passwort-zuruecksetzen/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/passwort-zuruecksetzen', [ResetPasswordController::class, 'store'])->middleware('throttle:10,1')->name('password.update');

    Route::get('/app/passwort-vergessen', [ForgotPasswordController::class, 'create'])->name('patient.password.request');
    Route::post('/app/passwort-vergessen', [ForgotPasswordController::class, 'store'])->middleware('throttle:5,1')->name('patient.password.email');
    Route::get('/app/passwort-zuruecksetzen/{token}', [ResetPasswordController::class, 'create'])->name('patient.password.reset');
    Route::post('/app/passwort-zuruecksetzen', [ResetPasswordController::class, 'store'])->middleware('throttle:10,1')->name('patient.password.update');
});

Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Krankenhaus (Überwachungsscreen, Desktop)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:staff'])->group(function () {
    // Zwei-Faktor-Anmeldung einrichten – auch erreichbar, solange sie vorgeschrieben, aber noch nicht eingerichtet ist.
    Route::middleware('unlocked')->group(function () {
        Route::get('/konto/zwei-faktor', [TwoFactorController::class, 'create'])->name('account.two-factor.create');
        Route::post('/konto/zwei-faktor', [TwoFactorController::class, 'store'])->middleware('throttle:6,1')->name('account.two-factor.store');
    });

    Route::middleware('two-factor')->group(function () {
        Route::get('/ueberwachung', [BoardController::class, 'index'])->name('board');
        Route::get('/ueberwachung/live', [BoardController::class, 'live'])->name('board.live');

        Route::post('/sperre', [PrivacyLockController::class, 'store'])->name('lock');
        Route::post('/sperre/aufheben', [PrivacyLockController::class, 'destroy'])->middleware('throttle:10,1')->name('unlock');

        Route::middleware('unlocked')->group(function () {
            Route::post('/alarme/{alarm}/quittieren', [AlarmController::class, 'acknowledge'])->name('alarms.acknowledge');
            Route::post('/alarme/{alarm}/uebernehmen', [AlarmController::class, 'claim'])->name('alarms.claim');

            Route::get('/patienten', [PatientController::class, 'index'])->name('patients.index');
            Route::get('/patienten/neu', [PatientController::class, 'create'])->name('patients.create');
            Route::post('/patienten', [PatientController::class, 'store'])->name('patients.store');
            Route::get('/patienten/{patient}', [PatientController::class, 'show'])->name('patients.show');
            Route::get('/patienten/{patient}/bearbeiten', [PatientController::class, 'edit'])->name('patients.edit');
            Route::put('/patienten/{patient}', [PatientController::class, 'update'])->name('patients.update');
            Route::delete('/patienten/{patient}', [PatientController::class, 'destroy'])->name('patients.destroy');

            Route::scopeBindings()->group(function () {
                Route::post('/patienten/{patient}/medikation', [MedicationController::class, 'store'])->name('patients.medications.store');
                Route::put('/patienten/{patient}/medikation/{medication}', [MedicationController::class, 'update'])->name('patients.medications.update');
                Route::delete('/patienten/{patient}/medikation/{medication}', [MedicationController::class, 'destroy'])->name('patients.medications.destroy');
            });
            Route::get('/patienten/{patient}/fhir', [ExportController::class, 'fhir'])->name('patients.fhir');
            Route::get('/patienten/{patient}/bericht', [ExportController::class, 'report'])->name('patients.report');
            Route::post('/patienten/{patient}/nachrichten', [MessageController::class, 'store'])->name('patients.messages.store');
            Route::post('/patienten/{patient}/anrufe', [CallController::class, 'store'])->name('calls.store');
            Route::post('/patienten/{patient}/termine', [AppointmentController::class, 'store'])->name('patients.appointments.store');
            Route::post('/termine/{appointment}/absagen', [AppointmentController::class, 'cancel'])->name('appointments.cancel');
            Route::post('/termine/{appointment}/starten', [AppointmentController::class, 'start'])->name('appointments.start');

            Route::get('/monatsberichte', [MonthlyReportController::class, 'index'])->name('reports.index');

            Route::middleware('can:manage-doctors')->group(function () {
                Route::get('/aerzte', [HospitalDoctorController::class, 'index'])->name('doctors.index');
                Route::get('/aerzte/neu', [HospitalDoctorController::class, 'create'])->name('doctors.create');
                Route::post('/aerzte', [HospitalDoctorController::class, 'store'])->name('doctors.store');
                Route::get('/aerzte/{doctor}/bearbeiten', [HospitalDoctorController::class, 'edit'])->name('doctors.edit');
                Route::put('/aerzte/{doctor}', [HospitalDoctorController::class, 'update'])->name('doctors.update');
                Route::delete('/aerzte/{doctor}', [HospitalDoctorController::class, 'destroy'])->name('doctors.destroy');
                Route::delete('/aerzte/{doctor}/zwei-faktor', [HospitalDoctorController::class, 'resetTwoFactor'])->name('doctors.two-factor.destroy');
            });

            Route::middleware('can:view-audit-log')->group(function () {
                Route::get('/protokoll', [AuditLogController::class, 'index'])->name('audit.index');
                Route::get('/protokoll/export', [AuditLogController::class, 'export'])->middleware('throttle:10,1')->name('audit.export');
            });

            Route::get('/anrufe', [CallController::class, 'index'])->name('calls.index');
            Route::get('/anrufe/{call}', [CallController::class, 'show'])->name('calls.show');
            Route::get('/anrufe/{call}/status', [CallController::class, 'status'])->name('calls.status');
            Route::post('/anrufe/{call}/annehmen', [CallController::class, 'answer'])->name('calls.answer');
            Route::post('/anrufe/{call}/beenden', [CallController::class, 'end'])->name('calls.end');
            Route::put('/anrufe/{call}/notiz', [CallController::class, 'note'])->name('calls.note');
            Route::get('/anrufe/{call}/signale', [CallSignalController::class, 'index'])->middleware('throttle:video')->name('calls.signals.index');
            Route::post('/anrufe/{call}/signale', [CallSignalController::class, 'store'])->middleware('throttle:video')->name('calls.signals.store');

            Route::get('/konto', [AccountController::class, 'edit'])->name('account.edit');
            Route::put('/konto/passwort', [AccountController::class, 'updatePassword'])->middleware('throttle:6,1')->name('account.password');
            Route::put('/konto/pin', [AccountController::class, 'updatePin'])->middleware('throttle:6,1')->name('account.pin');
            Route::post('/konto/zwei-faktor/codes', [TwoFactorController::class, 'regenerate'])->middleware('throttle:6,1')->name('account.two-factor.recovery-codes');
            Route::post('/konto/zwei-faktor/abschalten', [TwoFactorController::class, 'destroy'])->middleware('throttle:6,1')->name('account.two-factor.destroy');

            Route::post('/demo/upload', DemoUploadController::class)->name('demo.upload');
        });
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
    Route::post('/termine/{appointment}/absagen', [PatientAppointmentController::class, 'cancel'])->name('appointments.cancel');

    Route::get('/konto', [PatientAccountController::class, 'edit'])->name('account.edit');
    Route::put('/konto/passwort', [PatientAccountController::class, 'updatePassword'])->middleware('throttle:6,1')->name('account.password');
    Route::put('/konto/standort', [PatientAccountController::class, 'updateLocationConsent'])->name('account.location');

    Route::get('/notfall', [EmergencyController::class, 'show'])->name('sos');
    Route::post('/notfall', [EmergencyController::class, 'store'])->middleware('throttle:20,1')->name('sos.store');
    Route::get('/notfall/status', [EmergencyController::class, 'status'])->name('sos.status');
    Route::post('/notfall/standort', [EmergencyController::class, 'location'])->middleware('throttle:30,1')->name('sos.location');
    Route::post('/notfall/fehlalarm', [EmergencyController::class, 'falseAlarm'])->name('sos.false-alarm');
    Route::post('/nachrichten', [PatientMessageController::class, 'store'])->middleware('throttle:20,1')->name('messages.store');

    Route::get('/medikation', [PatientMedicationController::class, 'index'])->name('medications.index');
    Route::post('/medikation', [PatientMedicationController::class, 'store'])->name('medications.store');
    Route::put('/medikation/{medication}', [PatientMedicationController::class, 'update'])->name('medications.update');
    Route::delete('/medikation/{medication}', [PatientMedicationController::class, 'destroy'])->name('medications.destroy');

    Route::post('/anrufe', [PatientCallController::class, 'store'])->name('calls.store');
    Route::get('/anrufe/aktiv', [PatientCallController::class, 'active'])->name('calls.active');
    Route::get('/anrufe/{call}', [PatientCallController::class, 'show'])->name('calls.show');
    Route::get('/anrufe/{call}/status', [PatientCallController::class, 'status'])->name('calls.status');
    Route::post('/anrufe/{call}/annehmen', [PatientCallController::class, 'answer'])->name('calls.answer');
    Route::post('/anrufe/{call}/ablehnen', [PatientCallController::class, 'decline'])->name('calls.decline');
    Route::post('/anrufe/{call}/beenden', [PatientCallController::class, 'end'])->name('calls.end');
    Route::get('/anrufe/{call}/signale', [CallSignalController::class, 'index'])->middleware('throttle:video')->name('calls.signals.index');
    Route::post('/anrufe/{call}/signale', [CallSignalController::class, 'store'])->middleware('throttle:video')->name('calls.signals.store');
});
