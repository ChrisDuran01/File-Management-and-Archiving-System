<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\OfficerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\LetterheadController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\SiteSettingController;

use Illuminate\Support\Facades\Schema;

Route::get('/', [PageController::class, 'login'] );

Route::get('/home', [PageController::class, 'home'] );

Route::get('/adminDashboard', [PageController::class, 'adminDashboard'] );

Route::get('/stuDashboard', [PageController::class, 'studentDashboard'] );

Route::get('/archives', [PageController::class, 'archives'] );

Route::get('/folders', [PageController::class, 'folders'] );

Route::get('/reports', [PageController::class, 'reports'] );

Route::post('/folders', [FolderController::class, 'store'])->name('folders.store');

Route::get('/folders', [FolderController::class, 'index']);

Route::get('/superAdminDashboard', [PageController::class, 'superAdminDashboard'] );

Route::get('/landingPage', [PageController::class, 'landingPage'] );

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email')->middleware('throttle:5,1');
Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update')->middleware('throttle:10,1');

Route::get('/adminDashboard', [AuthController::class, 'adminDashboard'])->middleware('auth');
Route::get('/superAdminDashboard', [AuthController::class, 'superAdminDashboard'])->middleware('auth');

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/change-password', [AuthController::class, 'showChangePassword'])
    ->name('password.change')
    ->middleware('auth');
Route::post('/change-password', [AuthController::class, 'changePassword'])
    ->name('password.change.submit')
    ->middleware('auth');

Route::get('/activityLogs', [SuperAdminController::class, 'activityLogs'])
    ->name('activity.logs')
    ->middleware('auth');

Route::get('/superAdminDashboard', [DashboardController::class, 'superAdminDashboard']);

Route::get('/adminDashboard', [FolderController::class, 'adminDashboard'])
    ->name('Admin.adminDashboard');

Route::resource('folders', FolderController::class);

Route::get('/folders/{id}', [FolderController::class, 'show'])
    ->name('folders.show');

Route::post('/files', [FileController::class, 'store'])->name('files.store');
Route::post('/files/prepare-upload', [FileController::class, 'prepareUpload'])->name('files.prepareUpload');
Route::post('/files/confirm-upload', [FileController::class, 'confirmUpload'])->name('files.confirmUpload');

Route::middleware('auth')->group(function () {
    Route::get('/search', [SearchController::class, 'index'])->name('search');
    Route::get('/search/live', [SearchController::class, 'live'])->name('search.live');
});

Route::middleware('auth')->group(function () {
    Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
    Route::post('/backup/create', [BackupController::class, 'createBackup'])->name('backup.create');
    Route::get('/backup/download/{id}', [BackupController::class, 'download'])->name('backup.download');
    Route::post('/backup/toggle', [BackupController::class, 'toggle'])->name('backup.toggle');
    Route::post('/backup/frequency', [BackupController::class, 'setFrequency'])->name('backup.frequency');
});


Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');



Route::get('stuDashboard', [StudentController::class, 'dashboard'])
    ->name('student.dashboard');

Route::get('/files/{id}/preview', [FileController::class, 'preview'])->name('files.preview');

Route::put('/folders/{id}', [FolderController::class, 'update'])->name('folders.update');
Route::delete('/folders/{id}', [FolderController::class, 'destroy'])->name('folders.destroy');
Route::post('/folders/{id}/access', [FolderController::class, 'updateAccess'])->name('folders.updateAccess');

// Archive routes
Route::middleware('auth')->group(function () {
    Route::get('/archives', [ArchiveController::class, 'index'])->name('archives.index');
    Route::post('/{id}/archives', [ArchiveController::class, 'archiveFolder'])->name('folders.archive');
    Route::post('/archives/{id}/restore', [ArchiveController::class, 'restore'])->name('archives.restore');
    Route::get('/archives/{id}', [ArchiveController::class, 'download'])->name('archives.download');
    Route::delete('/archives/{id}', [ArchiveController::class, 'destroy'])->name('archives.destroy');
    Route::post('/archives/run-auto-archive', [ArchiveController::class, 'runAutoArchive'])->name('archives.runAutoArchive');

    Route::get('/archives/{id}/view', [ArchiveController::class, 'show'])->name('archives.show');
    Route::get('/archives/{id}/files/{filename}', [ArchiveController::class, 'previewFile'])->name('archives.previewFile');
    Route::get('/archives/{id}/files/{filename}/raw', [ArchiveController::class, 'streamFile'])->name('archives.streamFile');
});

Route::post('/file/{id}/toggle-access', [FileController::class, 'toggleAccess'])
    ->name('file.toggleAccess');

Route::post('/send-message', [MailController::class, 'sendMessage'])->name('send.message');

Route::middleware('auth')->group(function () {
    Route::get('/manageAdmins', [OfficerController::class, 'index'])->name('officers.index');
    Route::get('/addAdmin', [DashboardController::class, 'createOfficer'])->name('officers.create');
    Route::post('/manageAdmins', [OfficerController::class, 'store'])->name('officers.store');
    Route::get('/editAdmin/{id}', [OfficerController::class, 'edit'])->name('officers.edit');
    Route::put('/officers/update/{id}', [OfficerController::class, 'update'])->name('officers.update');
    Route::delete('/officers/destroy/{id}', [OfficerController::class, 'destroy'])->name('officers.destroy');
    Route::delete('/officers/archiveAll', [OfficerController::class, 'archiveAll'])->name('officers.archiveAll');
    Route::patch('/officers/reactivate/{id}', [OfficerController::class, 'reactivate'])->name('officers.reactivate');
    Route::delete('/officers/force-delete/{id}', [OfficerController::class, 'forceDelete'])->name('officers.forceDelete');
    Route::patch('/officers/archive/{id}', [OfficerController::class, 'archiveOfficer'])->name('officers.archiveOfficer');
});

Route::post('/folders/archive-selected', [FolderController::class, 'archiveSelected'])
    ->name('folders.archive.selected');

Route::post('/folders/start-new-year', [FolderController::class, 'startNewSchoolYear'])
    ->name('folders.startNewYear');

Route::get('/folders/history/{year}', [FolderController::class, 'history'])
    ->name('folders.history');

Route::get('/files/{id}/download', [FileController::class, 'download'])->name('files.download');

Route::delete('/files/{id}', [FileController::class, 'destroy'])->name('files.destroy');

Route::post('/files/{id}/rename', [FileController::class, 'rename'])
    ->name('files.rename');

Route::get('/files/{id}/previewStudentDashboard', [StudentController::class, 'previewStudentDashboard'])->name('files.previewStudentDashboard');

Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit')->middleware('auth');
Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update')->middleware('auth');

Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
Route::post('/templates/generate', [TemplateController::class, 'generate'])->name('templates.generate');

Route::get('/letterheads', [LetterheadController::class, 'index'])->name('letterheads.index');
Route::get('/letterheads/{letterhead}/preview', [LetterheadController::class, 'preview'])->name('letterheads.preview');
Route::post('/letterheads', [LetterheadController::class, 'store'])->name('letterheads.store');
Route::put('/letterheads/{letterhead}', [LetterheadController::class, 'update'])->name('letterheads.update');
Route::delete('/letterheads/{letterhead}', [LetterheadController::class, 'destroy'])->name('letterheads.destroy');

Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index')->middleware('auth');
Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store')->middleware('auth');
Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update')->middleware('auth');
Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy')->middleware('auth');
Route::get('/announcements/{announcement}/download', [AnnouncementController::class, 'download'])->name('announcements.download');

Route::get('/site-settings', [SiteSettingController::class, 'edit'])->name('site-settings.edit')->middleware('auth');
Route::post('/site-settings', [SiteSettingController::class, 'update'])->name('site-settings.update')->middleware('auth');