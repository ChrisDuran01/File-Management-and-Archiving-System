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
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\OfficerAppController;
use App\Http\Controllers\ShareLinkController;
use App\Http\Controllers\SeoController;

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

Route::get('/privacy', [PrivacyController::class, 'notice'])->name('privacy.notice');

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
Route::get('/announcements/images/{image}', [AnnouncementController::class, 'image'])->name('announcements.image');

// One announcement on its own page, for sharing as a link and for search
// engines. Under stuDashboard so the installed app opens it in its own
// window. The slug is optional: a wrong or missing one redirects.
Route::get('stuDashboard/announcements/{announcement}/{slug?}', [StudentController::class, 'announcement'])->name('student.announcement');

// For search engines and link previews (Messenger, Facebook): robots.txt
// is served from here, not public/, so its Sitemap line has the real domain.
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('/share-card.jpg', [SeoController::class, 'shareCard'])->name('share.card');

// The browser-tab icon, made from the Site Settings logo (App\Services\Favicon);
// public/ has no favicon.ico, so requests reach this. Not held back by the
// change-password and privacy-notice redirects, or the tabs of those very
// pages would have no icon.
Route::get('/favicon.ico', [SeoController::class, 'favicon'])->name('favicon')
    ->withoutMiddleware([\App\Http\Middleware\EnsurePasswordIsChanged::class, \App\Http\Middleware\EnsurePrivacyNoticeAcknowledged::class]);

// Browser push opt-in from the student page. Anonymous by design (no account),
// so it's throttled and the controller only accepts known push-service hosts.
Route::post('/push/subscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'subscribe'])->name('push.subscribe')->middleware('throttle:20,1');
Route::post('/push/unsubscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'unsubscribe'])->name('push.unsubscribe')->middleware('throttle:20,1');

// Anonymous usage events from the student page, for Reports & Analytics.
// CSRF-exempt (see bootstrap/app.php): sendBeacon can't send the token, and
// a tab left open past the session lifetime would otherwise lose events.
// The limit is generous because a whole campus can share one Wi-Fi IP.
Route::post('/engagement', [\App\Http\Controllers\EngagementController::class, 'store'])->name('engagement.track')->middleware('throttle:240,1');

// Contact form on the student page - throttled since it sends real email.
// Posts to the page's own address so the installed app (whose scope is
// stuDashboard) never hands the send off to a browser window.
Route::post('stuDashboard', [MailController::class, 'sendMessage'])->name('send.message')->middleware('throttle:5,1');

// Share links: one file, for anyone holding the link, until it expires or an
// officer turns it off. The controller checks the link on every request
// (FileShareLink::opensFile); a 40-character random token can't be guessed.
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/s/{token}', [ShareLinkController::class, 'show'])->where('token', '[A-Za-z0-9]{40}')->name('share.show');
    Route::get('/s/{token}/file', [ShareLinkController::class, 'file'])->where('token', '[A-Za-z0-9]{40}')->name('share.file');
});

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'nocache'])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/session/keep-alive', [AuthController::class, 'keepAlive'])->name('session.keepAlive');

    Route::get('/privacy/acknowledge', [PrivacyController::class, 'acknowledge'])->name('privacy.acknowledge');
    Route::post('/privacy/acknowledge', [PrivacyController::class, 'submitAcknowledge'])->name('privacy.acknowledge.submit');

    Route::get('/change-password', [AuthController::class, 'showChangePassword'])->name('password.change');
    Route::post('/change-password', [AuthController::class, 'changePassword'])->name('password.change.submit');

    // Dashboards (the SuperAdmin one is in the superadmin group below)
    Route::get('/adminDashboard', [FolderController::class, 'adminDashboard'])->name('Admin.adminDashboard');

    // Folders
    Route::resource('folders', FolderController::class);
    Route::get('/folders/{id}', [FolderController::class, 'show'])->name('folders.show');
    Route::put('/folders/{id}', [FolderController::class, 'update'])->name('folders.update');
    Route::delete('/folders/{id}', [FolderController::class, 'destroy'])->name('folders.destroy');
    Route::post('/folders/{id}/access', [FolderController::class, 'updateAccess'])->name('folders.updateAccess');
    Route::post('/folders/archive-selected', [FolderController::class, 'archiveSelected'])->name('folders.archive.selected');
    Route::get('/folders/history/{year}', [FolderController::class, 'history'])->name('folders.history');

    // Files
    Route::post('/files', [FileController::class, 'store'])->name('files.store');
    Route::get('/files/{id}/preview', [FileController::class, 'preview'])->name('files.preview');
    Route::get('/files/{id}/raw', [FileController::class, 'raw'])->name('files.raw');
    Route::get('/files/{id}/download', [FileController::class, 'download'])->name('files.download');
    Route::delete('/files/{id}', [FileController::class, 'destroy'])->name('files.destroy');
    Route::post('/files/{id}/rename', [FileController::class, 'rename'])->name('files.rename');
    Route::post('/file/{id}/toggle-access', [FileController::class, 'toggleAccess'])->name('file.toggleAccess');
    Route::post('/file/{id}/approval', [FileController::class, 'setApproval'])->name('file.setApproval');

    // Share links (website Share dialog and the phone app's Share sheet)
    Route::get('/files/{id}/share-links', [ShareLinkController::class, 'index'])->name('share-links.index');
    Route::post('/files/{id}/share-links', [ShareLinkController::class, 'store'])->name('share-links.store')->middleware('throttle:30,1');
    Route::delete('/share-links/{link}', [ShareLinkController::class, 'destroy'])->name('share-links.destroy');

    // Search
    Route::get('/search', [SearchController::class, 'index'])->name('search');
    Route::get('/search/live', [SearchController::class, 'live'])->name('search.live');

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

    // SuperAdmin-only pages - every page that is only in the SuperAdmin
    // menu belongs here, restricted server-side, not just hidden from the
    // menu (typing the address used to be enough to open it). See
    // App\Http\Middleware\EnsureSuperAdmin.
    Route::middleware('superadmin')->group(function () {
        Route::get('/superAdminDashboard', [DashboardController::class, 'superAdminDashboard']);

        // Every officer's actions, with their IP addresses.
        Route::get('/activityLogs', [SuperAdminController::class, 'activityLogs'])->name('activity.logs');

        // Backups - a backup holds the whole database and every file, and
        // turning scheduled backups off (or down to monthly) is a quiet way
        // to lose data.
        Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
        Route::post('/backup/create', [BackupController::class, 'createBackup'])->name('backup.create');
        Route::get('/backup/progress', [BackupController::class, 'progress'])->name('backup.progress');
        Route::get('/backup/download/{id}', [BackupController::class, 'download'])->name('backup.download');
        Route::post('/backup/toggle', [BackupController::class, 'toggle'])->name('backup.toggle');
        Route::post('/backup/frequency', [BackupController::class, 'setFrequency'])->name('backup.frequency');

        // Officers - management of officer accounts is the single most
        // sensitive surface in the app (it can hand out SuperAdmin).
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

        // System restore - rebuilds the live database AND every cloud file
        // from a backup ZIP, overwriting current data wholesale.
        Route::post('/backup/{id}/restore', [BackupController::class, 'restore'])->name('backup.restore');
        Route::get('/backup/restore/progress', [BackupController::class, 'restoreProgress'])->name('backup.restore.progress');
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
    Route::post('/site-settings/display-officers', [SiteSettingController::class, 'storeDisplayOfficer'])->name('site-settings.display-officers.store');
    Route::delete('/site-settings/display-officers/{displayOfficer}', [SiteSettingController::class, 'destroyDisplayOfficer'])->name('site-settings.display-officers.destroy');
});

/*
|--------------------------------------------------------------------------
| Officer phone app
|--------------------------------------------------------------------------
|
| Phone-sized screens over the same folders and files, for officers only
| (see OfficerAppController). Everything sits under /app so the installed
| app and its service worker (public/officer-sw.js) have a scope of their
| own, separate from the website and from the student app. `phone` keeps it
| to phones - config('officer_app.phones_only').
|
| There is one sign-in page: /app/login shows the website's own, so the
| lockout, disabled-account and audit rules are the same single copy, and
| a phone goes on to the app after signing in from either address.
|
*/

Route::prefix('app')->name('app.')->middleware('phone')->group(function () {

    Route::get('/login', [OfficerAppController::class, 'showLogin'])->name('login');

    Route::middleware(['auth', 'nocache'])->group(function () {
        Route::get('/', [OfficerAppController::class, 'home'])->name('home');
        Route::get('/account', [OfficerAppController::class, 'account'])->name('account');

        Route::get('/folders', [OfficerAppController::class, 'folders'])->name('folders');
        Route::get('/years/{year}', [OfficerAppController::class, 'year'])->name('year');
        Route::get('/folders/{id}', [OfficerAppController::class, 'folder'])->whereNumber('id')->name('folder');
        Route::get('/folders/{id}/add', [OfficerAppController::class, 'addForm'])->whereNumber('id')->name('folder.add');
        Route::post('/folders/{id}/files', [OfficerAppController::class, 'storeFile'])->whereNumber('id')->name('folder.store');

        Route::get('/files/{id}', [OfficerAppController::class, 'file'])->whereNumber('id')->name('file');
        Route::get('/files/{id}/raw', [OfficerAppController::class, 'raw'])->whereNumber('id')->name('file.raw');

        Route::get('/search', [OfficerAppController::class, 'search'])->name('search');
        Route::get('/archives/{id}', [OfficerAppController::class, 'archive'])->whereNumber('id')->name('archive');
        Route::get('/archives/{id}/files/{filename}', [OfficerAppController::class, 'archivedFile'])->whereNumber('id')->name('archive.file');

        Route::get('/offline', [OfficerAppController::class, 'offline'])->name('offline');
        Route::get('/notifications', [OfficerAppController::class, 'notifications'])->name('notifications');

        Route::post('/sync', [OfficerAppController::class, 'sync'])->name('sync');
        Route::post('/sync/saved', [OfficerAppController::class, 'syncSaved'])->name('sync.saved');
    });
});
