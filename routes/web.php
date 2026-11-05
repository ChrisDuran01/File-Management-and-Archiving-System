<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FileVersionController;
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
use App\Http\Controllers\ActivityProposalController;
use App\Http\Controllers\TwoFactorController;

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
| Every public page has a limit per IP (throttle:public-pages, -images,
| -downloads; numbers in config/throttle.php), so one script can't keep the
| server busy. A public route added here needs one too.
|
*/

Route::get('/', [PageController::class, 'login'])->middleware('throttle:public-pages');
Route::get('/landingPage', [PageController::class, 'landingPage'])->middleware('throttle:public-pages');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login')->middleware('throttle:public-pages');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::get('/privacy', [PrivacyController::class, 'notice'])->name('privacy.notice')->middleware('throttle:public-pages');

// The authenticator code after a right password (TwoFactorController) -
// nobody is signed in yet, so these sit outside `auth`.
Route::get('/two-factor-challenge', [TwoFactorController::class, 'showChallenge'])->name('two-factor.challenge')->middleware('throttle:public-pages');
Route::post('/two-factor-challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge.submit')->middleware('throttle:10,1');

Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request')->middleware('throttle:public-pages');
Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email')->middleware('throttle:5,1');
Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset')->middleware('throttle:public-pages');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update')->middleware('throttle:10,1');

// Anonymous student dashboard. Its file-preview endpoint enforces its own
// guard (file must be is_public and not inside a restricted folder), and
// announcement attachments are public content by design.
Route::get('stuDashboard', [StudentController::class, 'dashboard'])->name('student.dashboard')->middleware('throttle:public-pages');
Route::get('/files/{id}/previewStudentDashboard', [StudentController::class, 'previewStudentDashboard'])->name('files.previewStudentDashboard')->middleware('throttle:public-downloads');
Route::get('/announcements/{announcement}/download', [AnnouncementController::class, 'download'])->name('announcements.download')->middleware('throttle:public-downloads');
Route::get('/announcements/images/{image}', [AnnouncementController::class, 'image'])->name('announcements.image')->middleware('throttle:public-images');

// One announcement on its own page, for sharing as a link and for search
// engines. Under stuDashboard so the installed app opens it in its own
// window. The slug is optional: a wrong or missing one redirects.
Route::get('stuDashboard/announcements/{announcement}/{slug?}', [StudentController::class, 'announcement'])->name('student.announcement')->middleware('throttle:public-pages');

// One approved activity from Activity Proposals, with its budget per
// category. 404 unless the proposal is switched on and approved.
Route::get('stuDashboard/activities/{proposal}/{slug?}', [StudentController::class, 'activity'])->whereNumber('proposal')->name('student.activity')->middleware('throttle:public-pages');

// For search engines and link previews (Messenger, Facebook): robots.txt
// is served from here, not public/, so its Sitemap line has the real domain.
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap')->middleware('throttle:public-pages');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots')->middleware('throttle:public-pages');
Route::get('/share-card.jpg', [SeoController::class, 'shareCard'])->name('share.card')->middleware('throttle:public-images');

// The browser-tab icon, made from the Site Settings logo (App\Services\Favicon);
// public/ has no favicon.ico, so requests reach this. Not held back by the
// change-password and privacy-notice redirects, or the tabs of those very
// pages would have no icon.
Route::get('/favicon.ico', [SeoController::class, 'favicon'])->name('favicon')->middleware('throttle:public-images')
    ->withoutMiddleware([\App\Http\Middleware\EnsurePasswordIsChanged::class, \App\Http\Middleware\EnsurePrivacyNoticeAcknowledged::class, \App\Http\Middleware\EnsureTwoFactorEnabled::class]);

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

// `officer` signs out anyone whose term ended while they were signed in.
Route::middleware(['auth', 'officer', 'nocache'])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/session/keep-alive', [AuthController::class, 'keepAlive'])->name('session.keepAlive');

    Route::get('/privacy/acknowledge', [PrivacyController::class, 'acknowledge'])->name('privacy.acknowledge');
    Route::post('/privacy/acknowledge', [PrivacyController::class, 'submitAcknowledge'])->name('privacy.acknowledge.submit');

    Route::get('/change-password', [AuthController::class, 'showChangePassword'])->name('password.change');
    Route::post('/change-password', [AuthController::class, 'changePassword'])->name('password.change.submit');

    // Two-factor setup (required - EnsureTwoFactorEnabled), recovery codes, Profile's buttons
    Route::get('/two-factor/setup', [TwoFactorController::class, 'showSetup'])->name('two-factor.setup');
    Route::post('/two-factor/setup', [TwoFactorController::class, 'confirmSetup'])->name('two-factor.setup.confirm')->middleware('throttle:10,1');
    Route::get('/two-factor/recovery-codes', [TwoFactorController::class, 'showRecoveryCodes'])->name('two-factor.recovery-codes');
    Route::post('/profile/two-factor', [TwoFactorController::class, 'manage'])->name('two-factor.manage')->middleware('throttle:10,1');

    // Dashboards (the SuperAdmin one is in the superadmin group below)
    Route::get('/adminDashboard', [DashboardController::class, 'adminDashboard'])->name('Admin.adminDashboard');

    // Folders
    // No create/edit pages: folders are made and renamed from the Folders page.
    Route::resource('folders', FolderController::class)->except(['create', 'edit']);
    Route::get('/folders/{id}', [FolderController::class, 'show'])->name('folders.show');
    Route::put('/folders/{id}', [FolderController::class, 'update'])->name('folders.update');
    Route::delete('/folders/{id}', [FolderController::class, 'destroy'])->name('folders.destroy');
    Route::post('/folders/{id}/access', [FolderController::class, 'updateAccess'])->name('folders.updateAccess');
    Route::get('/folders/history/{year}', [FolderController::class, 'history'])->name('folders.history');

    // Files
    Route::post('/files', [FileController::class, 'store'])->name('files.store');
    Route::post('/files/cancel-upload', [FileController::class, 'cancelUpload'])->middleware('signed')->name('files.cancelUpload');
    Route::get('/files/{id}/preview', [FileController::class, 'preview'])->name('files.preview');
    Route::get('/files/{id}/raw', [FileController::class, 'raw'])->name('files.raw');
    Route::get('/files/{id}/download', [FileController::class, 'download'])->name('files.download');
    Route::delete('/files/{id}', [FileController::class, 'destroy'])->name('files.destroy');
    Route::post('/files/{id}/rename', [FileController::class, 'rename'])->name('files.rename');
    Route::post('/files/{id}/move', [FileController::class, 'move'])->name('files.move');
    Route::post('/files/{id}/keep-here', [FileController::class, 'keepHere'])->name('files.keepHere');
    Route::post('/file/{id}/toggle-access', [FileController::class, 'toggleAccess'])->name('file.toggleAccess');
    Route::post('/file/{id}/approval', [FileController::class, 'setApproval'])->name('file.setApproval');
    Route::post('/file/{id}/not-a-copy', [FileController::class, 'notACopy'])->name('file.notACopy');

    // File versions (a document returned for fixes keeps one row)
    Route::post('/files/name-check', [FileVersionController::class, 'nameCheck'])->name('files.nameCheck');
    Route::get('/files/{id}/versions', [FileVersionController::class, 'index'])->name('files.versions.index');
    Route::post('/files/{id}/versions', [FileVersionController::class, 'store'])->name('files.versions.store');
    Route::get('/files/{id}/versions/{no}', [FileVersionController::class, 'show'])->whereNumber('no')->name('files.versions.show');
    Route::get('/files/{id}/versions/{no}/download', [FileVersionController::class, 'download'])->whereNumber('no')->name('files.versions.download');
    Route::post('/files/{id}/versions/{no}/restore', [FileVersionController::class, 'restore'])->whereNumber('no')->name('files.versions.restore');
    Route::post('/files/{id}/final', [FileVersionController::class, 'markFinal'])->name('files.final');
    Route::delete('/files/{id}/final', [FileVersionController::class, 'clearFinal'])->name('files.final.clear');
    Route::post('/file/{id}/merge-into-original', [FileVersionController::class, 'merge'])->name('file.mergeIntoOriginal');

    // Share links (website Share dialog and the phone app's Share sheet)
    Route::get('/files/{id}/share-links', [ShareLinkController::class, 'index'])->name('share-links.index');
    Route::post('/files/{id}/share-links', [ShareLinkController::class, 'store'])->name('share-links.store')->middleware('throttle:30,1');
    Route::delete('/share-links/{link}', [ShareLinkController::class, 'destroy'])->name('share-links.destroy');

    // Search
    Route::get('/search', [SearchController::class, 'index'])->name('search');
    Route::get('/search/live', [SearchController::class, 'live'])->name('search.live');

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/reports/price-check-settings', [ReportController::class, 'priceSettings'])->name('reports.price-settings');

    // Archives - a whole school year at a time (SchoolYearArchiver); one
    // ZIP per folder inside it. Archiving early and restoring a school year
    // are the Adviser's / President's.
    Route::get('/archives', [ArchiveController::class, 'index'])->name('archives.index');
    Route::get('/archives/school-years/{schoolYear}', [ArchiveController::class, 'schoolYear'])->where('schoolYear', '\d{4}-\d{4}')->name('archives.schoolYear');
    Route::post('/archives/school-years/{schoolYear}', [ArchiveController::class, 'archiveSchoolYear'])->where('schoolYear', '\d{4}-\d{4}')->middleware('superadmin')->name('archives.archiveSchoolYear');
    Route::post('/archives/school-years/{schoolYear}/restore', [ArchiveController::class, 'restoreSchoolYear'])->where('schoolYear', '\d{4}-\d{4}')->middleware('superadmin')->name('archives.restoreSchoolYear');
    Route::get('/archives/{id}', [ArchiveController::class, 'download'])->name('archives.download');
    Route::delete('/archives/{id}', [ArchiveController::class, 'destroy'])->name('archives.destroy');
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
        // There is deliberately no way to delete an officer's term record:
        // an account with no record counts as SuperAdmin (Folder::
        // isSuperAdmin), so deleting a former officer's record would let
        // them back in with full access. Former officers stay as "former".
        Route::get('/manageAdmins', [OfficerController::class, 'index'])->name('officers.index');
        Route::get('/addAdmin', [DashboardController::class, 'createOfficer'])->name('officers.create');
        Route::post('/manageAdmins', [OfficerController::class, 'store'])->name('officers.store');
        Route::get('/editAdmin/{id}', [OfficerController::class, 'edit'])->name('officers.edit');
        Route::put('/officers/update/{id}', [OfficerController::class, 'update'])->name('officers.update');
        Route::patch('/officers/{id}/password', [OfficerController::class, 'resetPassword'])->name('officers.resetPassword');
        Route::patch('/officers/{id}/two-factor', [OfficerController::class, 'resetTwoFactor'])->name('officers.resetTwoFactor');
        Route::post('/officers/next-president', [OfficerController::class, 'storeNextPresident'])->name('officers.nextPresident.store');
        Route::delete('/officers/next-president', [OfficerController::class, 'removeNextPresident'])->name('officers.nextPresident.destroy');
        Route::delete('/officers/archiveAll', [OfficerController::class, 'archiveAll'])->name('officers.archiveAll');
        Route::patch('/officers/reactivate/{id}', [OfficerController::class, 'reactivate'])->name('officers.reactivate');
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
    Route::post('/help-bot/ask', [HelpBotController::class, 'ask'])->name('helpbot.ask')->middleware('throttle:5,1');
    Route::post('/help-bot/feedback', [HelpBotController::class, 'feedback'])->name('helpbot.feedback')->middleware('throttle:30,1');

    // Trash (recycle bin). {type} is file|folder|document; restore is open
    // to any officer with access, permanent deletion is SuperAdmin-only
    // (enforced in the controller).
    Route::get('/trash', [TrashController::class, 'index'])->name('trash.index');
    Route::post('/trash/{type}/{id}/restore', [TrashController::class, 'restore'])
        ->whereIn('type', ['file', 'folder', 'document', 'proposal'])->whereNumber('id')->name('trash.restore');
    Route::delete('/trash/empty', [TrashController::class, 'empty'])->name('trash.empty');
    Route::delete('/trash/{type}/{id}', [TrashController::class, 'destroy'])
        ->whereIn('type', ['file', 'folder', 'document', 'proposal'])->whereNumber('id')->name('trash.destroy');

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
    Route::post('/templates/preview', [TemplateController::class, 'preview'])->name('templates.preview');
    Route::post('/templates/preview-pdf', [TemplateController::class, 'previewPdf'])->name('templates.preview-pdf');
    Route::get('/letterheads', [LetterheadController::class, 'index'])->name('letterheads.index');
    Route::get('/letterheads/{letterhead}/preview', [LetterheadController::class, 'preview'])->name('letterheads.preview');
    Route::post('/letterheads', [LetterheadController::class, 'store'])->name('letterheads.store');
    Route::put('/letterheads/{letterhead}', [LetterheadController::class, 'update'])->name('letterheads.update');
    Route::post('/letterheads/{letterhead}/default', [LetterheadController::class, 'makeDefault'])->name('letterheads.default');
    Route::post('/letterheads/{letterhead}/copy', [LetterheadController::class, 'copy'])->name('letterheads.copy');
    Route::delete('/letterheads/{letterhead}', [LetterheadController::class, 'destroy'])->name('letterheads.destroy');

    // Detailed Activity Proposals. Any officer can read and copy one; only
    // its maker or the Super Admin can change or delete it (checked in the
    // controller).
    Route::get('/proposals', [ActivityProposalController::class, 'index'])->name('proposals.index');
    Route::get('/proposals/create', [ActivityProposalController::class, 'create'])->name('proposals.create');
    Route::get('/proposals/import', [ActivityProposalController::class, 'importForm'])->name('proposals.import');
    Route::post('/proposals/import', [ActivityProposalController::class, 'importRead'])->name('proposals.import.read');
    Route::post('/proposals', [ActivityProposalController::class, 'store'])->name('proposals.store');
    Route::post('/proposals/budget-check', [ActivityProposalController::class, 'budgetCheck'])->name('proposals.budget-check');
    Route::get('/proposals/{proposal}', [ActivityProposalController::class, 'show'])->whereNumber('proposal')->name('proposals.show');
    Route::get('/proposals/{proposal}/edit', [ActivityProposalController::class, 'edit'])->whereNumber('proposal')->name('proposals.edit');
    Route::get('/proposals/{proposal}/pdf', [ActivityProposalController::class, 'pdf'])->whereNumber('proposal')->name('proposals.pdf');
    Route::put('/proposals/{proposal}', [ActivityProposalController::class, 'update'])->whereNumber('proposal')->name('proposals.update');
    Route::post('/proposals/{proposal}/copy', [ActivityProposalController::class, 'copy'])->whereNumber('proposal')->name('proposals.copy');
    Route::delete('/proposals/{proposal}', [ActivityProposalController::class, 'destroy'])->whereNumber('proposal')->name('proposals.destroy');
    Route::post('/proposals/{proposal}/status', [ActivityProposalController::class, 'status'])->whereNumber('proposal')->name('proposals.status');
    Route::post('/proposals/{proposal}/signed-copy', [ActivityProposalController::class, 'attachSigned'])->whereNumber('proposal')->name('proposals.signed');
    Route::post('/proposals/{proposal}/reopen', [ActivityProposalController::class, 'reopen'])->whereNumber('proposal')->name('proposals.reopen');
    Route::post('/proposals/{proposal}/publish', [ActivityProposalController::class, 'publish'])->whereNumber('proposal')->name('proposals.publish');

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

    Route::get('/login', [OfficerAppController::class, 'showLogin'])->name('login')->middleware('throttle:public-pages');
    // The code step at the app's own address, so an installed app stays in its window.
    Route::get('/two-factor', [TwoFactorController::class, 'showChallenge'])->name('two-factor')->middleware('throttle:public-pages');

    Route::middleware(['auth', 'officer', 'nocache'])->group(function () {
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
