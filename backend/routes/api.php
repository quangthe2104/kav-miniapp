<?php

use App\Http\Controllers\Api\Miniapp\AuthController as MiniappAuthController;
use App\Http\Controllers\Api\Miniapp\TeacherController as MiniappTeacherController;
use App\Http\Controllers\Api\Miniapp\VoteController as MiniappVoteController;
use Illuminate\Support\Facades\Route;

Route::prefix('miniapp/v1')->group(function () {
    Route::post('auth', [MiniappAuthController::class, 'store'])->middleware('throttle:30,1');

    Route::get('vote/{token}', [MiniappVoteController::class, 'show'])->middleware('throttle:60,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [MiniappAuthController::class, 'me']);
        Route::post('vote/{token}', [MiniappVoteController::class, 'store'])->middleware('throttle:30,1');

        Route::get('teacher/catalog/provinces', [MiniappTeacherController::class, 'provinces']);
        Route::get('teacher/catalog/provinces/{province}/wards', [MiniappTeacherController::class, 'wards']);
        Route::get('teacher/catalog/wards/{ward}/schools', [MiniappTeacherController::class, 'schools']);

        Route::get('teacher/profiles', [MiniappTeacherController::class, 'profiles']);
        Route::post('teacher/profiles', [MiniappTeacherController::class, 'storeProfile'])
            ->middleware('throttle:20,1');
        Route::get('teacher/profiles/{profile}', [MiniappTeacherController::class, 'showProfile']);
        Route::post('teacher/profiles/{profile}/forms/{form}/ensure', [MiniappTeacherController::class, 'ensureLink']);
        Route::get('teacher/class-forms/{classFormId}', [MiniappTeacherController::class, 'showClassForm']);
        Route::post('teacher/class-forms/{classFormId}/close', [MiniappTeacherController::class, 'close']);
        Route::post('teacher/class-forms/{classFormId}/reopen', [MiniappTeacherController::class, 'reopen']);
        Route::post('teacher/class-forms/{classFormId}/notes', [MiniappTeacherController::class, 'storeNote'])
            ->middleware('throttle:30,1');
        Route::get('teacher/class-forms/{classFormId}/template', [MiniappTeacherController::class, 'downloadTemplate']);
        Route::get('teacher/class-forms/{classFormId}/qr', [MiniappTeacherController::class, 'downloadQr']);
        Route::get('teacher/class-form-notes/{noteId}/file', [MiniappTeacherController::class, 'downloadNoteFile']);
        Route::delete('teacher/class-form-notes/{noteId}', [MiniappTeacherController::class, 'destroyNote']);
        Route::get('teacher/responses/{responseId}/paper', [MiniappTeacherController::class, 'showResponsePaper']);
        Route::delete('teacher/responses/{responseId}/paper', [MiniappTeacherController::class, 'destroyPaperResponse']);
        Route::get('teacher/paper-batches/{batchId}', [MiniappTeacherController::class, 'showPaperBatch']);
        Route::get('teacher/paper-items/{itemId}/image', [MiniappTeacherController::class, 'showPaperItemImage']);
        Route::post('teacher/paper-batches/{batchId}/confirm', [MiniappTeacherController::class, 'confirmPaperBatch'])
            ->middleware('throttle:20,1');
        Route::get('teacher/class-forms/{classFormId}/responses', [MiniappTeacherController::class, 'responses']);
    });
});
