<?php

namespace App\Support;

use App\Models\Folder;
use App\Models\ScanBatch;
use App\Models\User;

/**
 * The officer sidebar's pages, in one place, so the menu
 * (partials/app-shell/sidebar), the keyboard shortcut list
 * (partials/app-shell/shortcuts) and the "G then a letter" keys always
 * agree. Administration is the Super Admin's only.
 */
class OfficerMenu
{
    /**
     * Group label => pages. Each page: name, href, icon, active (is the
     * current page), count (shown beside it, e.g. scans waiting), key (the
     * letter pressed after G to open it).
     *
     * @return array<string, array<int, array{name: string, href: string, icon: string, active: bool, count: int, key: string}>>
     */
    public static function groups(User $user): array
    {
        $superAdmin = Folder::isSuperAdmin($user);
        $is = fn (string ...$patterns) => request()->is(...$patterns);
        $page = fn (string $name, string $href, string $icon, string $key, bool $active, int $count = 0) =>
            compact('name', 'href', 'icon', 'active', 'count', 'key');

        $groups = [
            'Overview' => [
                $page('Dashboard', $superAdmin ? '/superAdminDashboard' : '/adminDashboard', 'home', 'h', $is('adminDashboard', 'superAdminDashboard')),
                $page('Reports & Analytics', '/reports', 'chart', 'r', $is('reports')),
            ],
            'Records' => [
                $page('Folders', '/folders', 'folder', 'f', $is('folders', 'folders/*')),
                $page('Documents', '/documents', 'file', 'd', $is('documents') || ($is('documents/*') && ! $is('documents/scans*'))),
                $page('Scan Inbox', '/documents/scans', 'inbox', 'i', $is('documents/scans*'),
                    rescue(fn () => ScanBatch::where('status', 'ready_for_review')->where('is_archived', false)->count(), 0, false)),
                $page('Archives', '/archives', 'archive', 'a', $is('archives', 'archives/*')),
                $page('Trash', '/trash', 'trash', 'b', $is('trash')),
            ],
            'Create' => [
                $page('Document Templates', '/templates', 'template', 't', $is('templates*', 'letterheads*')),
                $page('Activity Proposals', '/proposals', 'clipboard', 'p', $is('proposals*')),
            ],
            'Student page' => [
                $page('Announcements', '/announcements', 'megaphone', 'n', $is('announcements*')),
                $page('Site Content', '/site-settings', 'globe', 'c', $is('site-settings')),
            ],
        ];

        if ($superAdmin) {
            $groups['Administration'] = [
                $page('Manage Admins', '/manageAdmins', 'users', 'm', $is('manageAdmins', 'addAdmin')),
                $page('Activity Logs', '/activityLogs', 'activity', 'l', $is('activityLogs')),
                $page('Backup', '/backup', 'gear', 's', $is('backup')),
            ];
        }

        return $groups;
    }

    public static function dashboard(User $user): string
    {
        return Folder::isSuperAdmin($user) ? '/superAdminDashboard' : '/adminDashboard';
    }
}
