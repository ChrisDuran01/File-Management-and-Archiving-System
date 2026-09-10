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
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ScanReviewController;
use App\Http\Controllers\TrashController;
use App\Http\Controllers\HelpBotController;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
|
| Only what an anonymous visitor genuinely needs: the login/password flows,
| the landing page, and the public student dashboard plus the exact
| endpoints that page uses. Everything else lives behind `auth` below -
| new routes should go in the authenticated group by default.
|
*/

Route::get('/', [PageController::class, 'login']);
Route::get('/landingPage', [PageController::class, 'landingPage']);

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email')->middleware('throttle:5,1');
Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update')->middleware('throttle:10,1');

// Anonymous student dashboard. Its file-preview endpoint enforces its own
// guard (file must be is_public and not inside a restricted folder), and
// announcement attachments are public content by design.
Route::get('stuDashboard', [StudentController::class, 'dashboard'])->name('student.dashboard');
Route::get('/files/{id}/previewStudentDashboard', [StudentController::class, 'previewStudentDashboard'])->name('files.previewStudentDashboard');
Route::get('/announcements/{announcement}/download', [AnnouncementController::class, 'download'])->name('announcements.download');

// Contact form on the student page - throttled since it sends real email.
Route::post('/send-message', [MailController::class, 'sendMessage'])->name('send.message')->middleware('throttle:5,1');

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/session/keep-alive', [AuthController::class, 'keepAlive'])->name('session.keepAlive');

    Route::get('/change-password', [AuthController::class, 'showChangePassword'])->name('password.change');
    Route::post('/change-password', [AuthController::class, 'changePassword'])->name('password.change.submit');

    // Dashboards
    Route::get('/adminDashboard', [FolderController::class, 'adminDashboard'])->name('Admin.adminDashboard');
    Route::get('/superAdminDashboard', [DashboardController::class, 'superAdminDashboard']);

    Route::get('/activityLogs', [SuperAdminController::class, 'activityLogs'])->name('activity.logs');

    // Folders
    Route::resource('folders', FolderController::class);
    Route::get('/folders/{id}', [FolderController::class, 'show'])->name('folders.show');
    Route::put('/folders/{id}', [FolderController::class, 'update'])->name('folders.update');
    Route::delete('/folders/{id}', [FolderController::class, 'destroy'])->name('folders.destroy');
    Route::post('/folders/{id}/access', [FolderController::class, 'updateAccess'])->name('folders.updateAccess');
    Route::post('/folders/archive-selected', [FolderController::class, 'archiveSelected'])->name('folders.archive.selected');
    Route::post('/folders/start-new-year', [FolderController::class, 'startNewSchoolYear'])->name('folders.startNewYear');
    Route::get('/folders/history/{year}', [FolderController::class, 'history'])->name('folders.history');

    // Files
    Route::post('/files', [FileController::class, 'store'])->name('files.store');
    Route::post('/files/prepare-upload', [FileController::class, 'prepareUpload'])->name('files.prepareUpload');
    Route::post('/files/confirm-upload', [FileController::class, 'confirmUpload'])->name('files.confirmUpload');
    Route::get('/files/{id}/preview', [FileController::class, 'preview'])->name('files.preview');
    Route::get('/files/{id}/download', [FileController::class, 'download'])->name('files.download');
    Route::delete('/files/{id}', [FileController::class, 'destroy'])->name('files.destroy');
    Route::post('/files/{id}/rename', [FileController::class, 'rename'])->name('files.rename');
    Route::post('/file/{id}/toggle-access', [FileController::class, 'toggleAccess'])->name('file.toggleAccess');

    // Search
    Route::get('/search', [SearchController::class, 'index'])->name('search');
    Route::get('/search/live', [SearchController::class, 'live'])->name('search.live');

    // Backup
    Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
    Route::post('/backup/create', [BackupController::class, 'createBackup'])->name('backup.create');
    Route::get('/backup/progress', [BackupController::class, 'progress'])->name('backup.progress');
    Route::get('/backup/download/{id}', [BackupController::class, 'download'])->name('backup.download');
    Route::post('/backup/toggle', [BackupController::class, 'toggle'])->name('backup.toggle');
    Route::post('/backup/frequency', [BackupController::class, 'setFrequency'])->name('backup.frequency');

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    // Archives
    Route::get('/archives', [ArchiveController::class, 'index'])->name('archives.index');
    Route::post('/{id}/archives', [ArchiveController::class, 'archiveFolder'])->name('folders.archive');
    Route::post('/archives/{id}/restore', [ArchiveController::class, 'restore'])->name('archives.restore');
    Route::get('/archives/{id}', [ArchiveController::class, 'download'])->name('archives.download');
    Route::delete('/archives/{id}', [ArchiveController::class, 'destroy'])->name('archives.destroy');
    Route::post('/archives/run-auto-archive', [ArchiveController::class, 'runAutoArchive'])->name('archives.runAutoArchive');
    Route::get('/archives/{id}/view', [ArchiveController::class, 'show'])->name('archives.show');
    Route::get('/archives/{id}/files/{filename}', [ArchiveController::class, 'previewFile'])->name('archives.previewFile');
    Route::get('/archives/{id}/files/{filename}/raw', [ArchiveController::class, 'streamFile'])->name('archives.streamFile');

    // Officers - management of officer accounts is the single most
    // sensitive surface in the app (it can hand out SuperAdmin), so unlike
    // most routes here it's restricted server-side, not just hidden from
    // the menu. See App\Http\Middleware\EnsureSuperAdmin.
    Route::middleware('superadmin')->group(function () {
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

    // In-app notification bell (polled JSON + mark-read)
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    // In-app help assistant (Gemini-backed, grounded on the officer guide)
    Route::post('/help-bot/ask', [HelpBotController::class, 'ask'])->name('helpbot.ask')->middleware('throttle:20,1');
    Route::post('/help-bot/feedback', [HelpBotController::class, 'feedback'])->name('helpbot.feedback')->middleware('throttle:30,1');

    // Trash (recycle bin). {type} is file|folder|document; restore is open
    // to any officer with access, permanent deletion is SuperAdmin-only
    // (enforced in the controller).
    Route::get('/trash', [TrashController::class, 'index'])->name('trash.index');
    Route::post('/trash/{type}/{id}/restore', [TrashController::class, 'restore'])
        ->whereIn('type', ['file', 'folder', 'document'])->whereNumber('id')->name('trash.restore');
    Route::delete('/trash/empty', [TrashController::class, 'empty'])->name('trash.empty');
    Route::delete('/trash/{type}/{id}', [TrashController::class, 'destroy'])
        ->whereIn('type', ['file', 'folder', 'document'])->whereNumber('id')->name('trash.destroy');

    // Digitize Hardcopy: scan-inbox review queue + structured document records.
    // The literal /documents/scans* routes are declared before /documents/{document}
    // so the wildcard can't swallow them.
    Route::get('/documents/scans',                      [ScanReviewController::class, 'index'])->name('documents.scans.index');
    Route::get('/documents/scans/status',               [ScanReviewController::class, 'status'])->name('documents.scans.status');
    Route::post('/documents/scans/check-now',           [ScanReviewController::class, 'checkNow'])->name('documents.scans.check-now')->middleware('throttle:10,1');
    Route::get('/documents/scans/{batch}/review',       [ScanReviewController::class, 'review'])->name('documents.scans.review');
    Route::get('/documents/scans/{batch}/pages/{page}', [ScanReviewController::class, 'page'])->whereNumber('page')->name('documents.scans.page');
    Route::post('/documents/scans/{batch}/file',        [ScanReviewController::class, 'file'])->name('documents.scans.file');
    Route::post('/documents/scans/{batch}/reprocess',   [ScanReviewController::class, 'reprocess'])->name('documents.scans.reprocess');
    Route::delete('/documents/scans/{batch}',           [ScanReviewController::class, 'destroyBatch'])->name('documents.scans.destroy');

    Route::get('/documents',                 [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/{document}',      [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/documents/{document}/edit', [DocumentController::class, 'edit'])->name('documents.edit');
    Route::put('/documents/{document}',      [DocumentController::class, 'update'])->name('documents.update');
    Route::delete('/documents/{document}',   [DocumentController::class, 'destroy'])->name('documents.destroy');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Templates & letterheads
    Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::post('/templates/generate', [TemplateController::class, 'generate'])->name('templates.generate');
    Route::get('/letterheads', [LetterheadController::class, 'index'])->name('letterheads.index');
    Route::get('/letterheads/{letterhead}/preview', [LetterheadController::class, 'preview'])->name('letterheads.preview');
    Route::post('/letterheads', [LetterheadController::class, 'store'])->name('letterheads.store');
    Route::put('/letterheads/{letterhead}', [LetterheadController::class, 'update'])->name('letterheads.update');
    Route::delete('/letterheads/{letterhead}', [LetterheadController::class, 'destroy'])->name('letterheads.destroy');

    // Announcements (management; the public download route is declared above)
    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');

    // Site settings
    Route::get('/site-settings', [SiteSettingController::class, 'edit'])->name('site-settings.edit');
    Route::post('/site-settings', [SiteSettingController::class, 'update'])->name('site-settings.update');
});
