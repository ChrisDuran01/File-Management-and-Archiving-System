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

Route::get('/manageAdmins', [PageController::class, 'manageAdmins'] );

Route::get('/activityLogs', [PageController::class, 'activityLogs'] );

Route::get('/landingPage', [PageController::class, 'landingPage'] );

Route::get('/backup', [PageController::class, 'backup'] );

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);

Route::get('/adminDashboard', [AuthController::class, 'adminDashboard'])->middleware('auth');
Route::get('/superAdminDashboard', [AuthController::class, 'superAdminDashboard'])->middleware('auth');

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/activityLogs', [SuperAdminController::class, 'activityLogs'])
    ->name('activity.logs')
    ->middleware('auth');

Route::get('/superAdminDashboard', [DashboardController::class, 'superAdminDashboard']);

Route::get('/manageAdmins', [DashboardController::class, 'officers']);


// Show add officer form
Route::get('/addAdmin', [DashboardController::class, 'createOfficer'])->name('officers.create');

// Store officer in DB
Route::post('/manageAdmins', [DashboardController::class, 'storeOfficer'])->name('officers.store');



Route::get('/officers/{id}/edit', [DashboardController::class, 'edit'])->name('officers.edit');
Route::put('/officers/{id}', [DashboardController::class, 'update'])->name('officers.update');

Route::get('/adminDashboard', [FolderController::class, 'adminDashboard'])
    ->name('Admin.adminDashboard');

Route::resource('folders', FolderController::class);

Route::get('/folders/{id}', [FolderController::class, 'show'])
    ->name('folders.show');

Route::post('/files', [FileController::class, 'store'])->name('files.store');

Route::get('/search', [SearchController::class, 'index'])->name('search');

Route::get('/activity-logs', [SuperAdminController::class, 'index'])
    ->name('activity.logs');

Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
Route::post('/backup/create', [BackupController::class, 'createBackup'])->name('backup.create');
Route::get('/backup/download/{id}', [BackupController::class, 'download'])->name('backup.download');
Route::post('/backup/toggle', [BackupController::class, 'toggle'])
    ->name('backup.toggle');

Route::post('/backup/frequency', [BackupController::class, 'setFrequency'])
    ->name('backup.frequency');


Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');



Route::get('stuDashboard', [StudentController::class, 'dashboard'])
    ->name('student.dashboard');

Route::get('/files/{id}/preview', [FileController::class, 'preview'])->name('files.preview');

Route::put('/folders/{id}', [FolderController::class, 'update'])->name('folders.update');
Route::delete('/folders/{id}', [FolderController::class, 'destroy'])->name('folders.destroy');

// Archive routes
Route::get('/archives', [ArchiveController::class, 'index'])->name('archives.index');
Route::post('/{id}/archives', [ArchiveController::class, 'archiveFolder'])->name('folders.archive');
Route::post('/archives/{id}/restore', [ArchiveController::class, 'restore'])->name('archives.restore');
Route::get('/archives/{id}', [ArchiveController::class, 'download'])->name('archives.download');
Route::delete('/archives/{id}', [ArchiveController::class, 'destroy'])->name('archives.destroy');

Route::post('/file/{id}/toggle-access', [FileController::class, 'toggleAccess'])
    ->name('file.toggleAccess');

Route::post('/send-message', [MailController::class, 'sendMessage'])->name('send.message');

// Make sure you have this route with the correct name
Route::get('/manageAdmins', [OfficerController::class, 'index'])->name('officers.index');
Route::get('/officers/create', [OfficerController::class, 'create'])->name('officers.create');
Route::post('/manageAdmins', [OfficerController::class, 'store'])->name('officers.store');
Route::get('/officers/edit/{id}', [OfficerController::class, 'edit'])->name('officers.edit');
Route::put('/officers/update/{id}', [OfficerController::class, 'update'])->name('officers.update');
Route::delete('/officers/destroy/{id}', [OfficerController::class, 'destroy'])->name('officers.destroy');

// Archive and restore routes
Route::delete('/officers/archiveAll', [OfficerController::class, 'archiveAll'])->name('officers.archiveAll');
Route::patch('/officers/reactivate/{id}', [OfficerController::class, 'reactivate'])->name('officers.reactivate');
Route::delete('/officers/force-delete/{id}', [OfficerController::class, 'forceDelete'])->name('officers.forceDelete');

Route::post('/folders/archive-selected', [FolderController::class, 'archiveSelected'])
    ->name('folders.archive.selected');

Route::get('/files/{id}/download', [FileController::class, 'download'])->name('files.download');

Route::delete('/files/{id}', [FileController::class, 'destroy'])->name('files.destroy');

Route::post('/files/{id}/rename', [FileController::class, 'rename'])
    ->name('files.rename');