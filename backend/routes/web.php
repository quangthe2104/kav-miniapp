<?php

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\CatalogController as AdminCatalogController;
use App\Http\Controllers\Admin\ClassFormController as AdminClassFormController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ExportController as AdminExportController;
use App\Http\Controllers\Admin\FormController as AdminFormController;
use App\Http\Controllers\Admin\SchoolController as AdminSchoolController;
use App\Http\Controllers\Admin\TeacherController as AdminTeacherController;
use App\Http\Controllers\Teacher\AuthController as TeacherAuthController;
use App\Http\Controllers\Teacher\ClassFormController;
use App\Http\Controllers\Teacher\ClassProfileController;
use App\Http\Controllers\Teacher\DashboardController as TeacherDashboardController;
use App\Http\Controllers\Teacher\PaperBatchController;
use App\Http\Controllers\VoteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TeacherAuthController::class, 'showLogin'])->name('home');

Route::get('/vote/{token}', [VoteController::class, 'show'])->name('vote.show');
Route::post('/vote/{token}', [VoteController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('vote.store');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::get('class-forms/{classForm}', [AdminClassFormController::class, 'show'])->name('class-forms.show');
    Route::resource('forms', AdminFormController::class)->except(['show', 'destroy']);
    Route::post('forms/{form}/ocr/detect', [AdminFormController::class, 'detectOcr'])
        ->middleware('throttle:10,1')
        ->name('forms.ocr.detect');
    Route::get('forms/{form}/ocr/preview', [AdminFormController::class, 'previewOcr'])
        ->name('forms.ocr.preview');
    Route::get('forms/{form}/consent-pdf', [AdminFormController::class, 'consentPdf'])
        ->name('forms.consent-pdf');
    Route::get('forms/{form}/export', [AdminExportController::class, 'formResponses'])->name('forms.export');
    Route::get('teachers', [AdminTeacherController::class, 'index'])->name('teachers.index');
    Route::patch('teachers/{teacher}', [AdminTeacherController::class, 'update'])->name('teachers.update');
    Route::get('schools', [AdminSchoolController::class, 'index'])->name('schools.index');
    Route::get('schools/import', [AdminSchoolController::class, 'importCreate'])->name('schools.import.create');
    Route::post('schools/import', [AdminSchoolController::class, 'importUpload'])
        ->middleware('throttle:20,1')
        ->name('schools.import.upload');
    Route::get('schools/import/map', [AdminSchoolController::class, 'importMap'])->name('schools.import.map');
    Route::post('schools/import/validate', [AdminSchoolController::class, 'importValidate'])
        ->middleware('throttle:30,1')
        ->name('schools.import.validate');
    Route::get('schools/import/confirm', [AdminSchoolController::class, 'importConfirm'])->name('schools.import.confirm');
    Route::post('schools/import/commit', [AdminSchoolController::class, 'importCommit'])
        ->middleware('throttle:10,1')
        ->name('schools.import.commit');
    Route::post('schools/import/cancel', [AdminSchoolController::class, 'importCancel'])->name('schools.import.cancel');

    Route::get('catalog', [AdminCatalogController::class, 'index'])->name('catalog.index');
    Route::get('catalog/provinces', [AdminCatalogController::class, 'provinces'])->name('catalog.provinces');
    Route::get('catalog/provinces/{province}/wards', [AdminCatalogController::class, 'wards'])->name('catalog.wards');
    Route::get('catalog/wards/{ward}/schools', [AdminCatalogController::class, 'schools'])->name('catalog.schools');
    Route::get('audit', [AdminAuditLogController::class, 'index'])->name('audit.index');
    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('users/create', [AdminUserController::class, 'create'])->name('users.create');
    Route::post('users', [AdminUserController::class, 'store'])->name('users.store');
    Route::get('users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::patch('users/{user}/disable', [AdminUserController::class, 'disable'])->name('users.disable');
});

Route::get('/dashboard', fn () => redirect()->route('admin.dashboard'))
    ->middleware(['auth', 'admin'])
    ->name('dashboard');

Route::prefix('teacher')->name('teacher.')->group(function () {
    Route::get('login', [TeacherAuthController::class, 'showLogin'])->name('login');
    Route::get('zalo/redirect', [TeacherAuthController::class, 'redirectToZalo'])
        ->middleware('throttle:20,1')
        ->name('zalo.redirect');
    Route::get('zalo/callback', [TeacherAuthController::class, 'handleZaloCallback'])
        ->middleware('throttle:30,1')
        ->name('zalo.callback');
    Route::post('dev-login', [TeacherAuthController::class, 'devLogin'])
        ->middleware('throttle:10,1')
        ->name('dev-login');
    Route::post('logout', [TeacherAuthController::class, 'logout'])->name('logout');

    Route::middleware('teacher')->group(function () {
        Route::get('/', TeacherDashboardController::class)->name('dashboard');
        Route::get('profiles/create', [ClassProfileController::class, 'create'])->name('profiles.create');
        Route::post('profiles', [ClassProfileController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('profiles.store');
        Route::get('profiles/{profile}', [ClassProfileController::class, 'show'])->name('profiles.show');
        Route::post('profiles/{profile}/forms/{form}/ensure', [ClassFormController::class, 'ensure'])->name('profiles.forms.ensure');
        Route::get('class-forms/{classForm}', [ClassFormController::class, 'show'])->name('class-forms.show');
        Route::post('class-forms/{classForm}/note', [ClassFormController::class, 'storeNote'])
            ->middleware('throttle:30,1')
            ->name('class-forms.note');
        Route::get('class-form-notes/{note}/file', [ClassFormController::class, 'downloadNoteFile'])
            ->name('class-form-notes.file');
        Route::delete('class-form-notes/{note}', [ClassFormController::class, 'destroyNote'])
            ->name('class-form-notes.destroy');
        Route::get('responses/{response}/paper', [ClassFormController::class, 'showResponsePaper'])
            ->name('responses.paper');
        Route::delete('responses/{response}/paper', [ClassFormController::class, 'destroyPaperResponse'])
            ->name('responses.paper.destroy');
        Route::get('paper-items/{item}/image', [PaperBatchController::class, 'showImage'])
            ->name('paper.items.image');
        Route::post('class-forms/{classForm}/close', [ClassFormController::class, 'close'])->name('class-forms.close');
        Route::post('class-forms/{classForm}/reopen', [ClassFormController::class, 'reopen'])->name('class-forms.reopen');
        Route::get('class-forms/{classForm}/template', [ClassFormController::class, 'downloadTemplate'])
            ->name('class-forms.template');
        Route::get('class-forms/{classForm}/qr.png', [ClassFormController::class, 'downloadQr'])
            ->name('class-forms.qr');
        Route::get('forms/{form}/template', [ClassFormController::class, 'downloadFormTemplate'])
            ->name('forms.template');
        Route::get('class-forms/{classForm}/paper', [PaperBatchController::class, 'create'])->name('paper.create');
        Route::post('class-forms/{classForm}/paper', [PaperBatchController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('paper.store');
        Route::get('paper-batches/{batch}', [PaperBatchController::class, 'show'])->name('paper.show');
        Route::post('paper-batches/{batch}/confirm', [PaperBatchController::class, 'confirm'])
            ->middleware('throttle:30,1')
            ->name('paper.confirm');
        Route::get('catalog/provinces/{province}/wards', [ClassProfileController::class, 'wards']);
        Route::get('catalog/wards/{ward}/schools', [ClassProfileController::class, 'schools']);
    });
});

require __DIR__.'/auth.php';
