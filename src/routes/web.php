<?php

use Illuminate\Support\Facades\Route;
use Arifur\BookstackBackup\Http\Controllers\BackupController;

// Backups routes - explicit /settings/backups routes take precedence over {category} wildcard.
Route::middleware(['web', 'auth', 'can:settings-manage'])->group(function () {
    Route::get('/settings/backups', [BackupController::class, 'index'])->name('backups.index');
    Route::post('/settings/backups', [BackupController::class, 'updateBackupSettings'])->name('backups.settings.update');

    Route::get('/settings/backups/schedule', [BackupController::class, 'schedule'])->name('backups.schedule');
    Route::post('/settings/backups/schedule', [BackupController::class, 'updateScheduleSettings'])->name('backups.schedule.update');

    Route::get('/settings/backups/backup-settings', [BackupController::class, 'backupSettings'])->name('backups.backup-settings');
    Route::post('/settings/backups/backup-settings', [BackupController::class, 'updateBackupSettingsSection'])->name('backups.backup-settings.update');

    Route::get('/settings/backups/remote', [BackupController::class, 'remote'])->name('backups.remote');
    Route::post('/settings/backups/remote', [BackupController::class, 'updateRemoteSettings'])->name('backups.remote.update');

    Route::post('/settings/backups/create', [BackupController::class, 'create'])->name('backups.create');
    Route::get('/settings/backups/download/{filename}', [BackupController::class, 'downloadBackup'])->name('backups.download');
    Route::get('/settings/backups/delete/{filename}', [BackupController::class, 'confirmDelete'])->name('backups.delete.confirm');
    Route::delete('/settings/backups/delete/{filename}', [BackupController::class, 'delete'])->name('backups.delete');
});
