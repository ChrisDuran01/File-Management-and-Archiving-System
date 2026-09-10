<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * JSON backend for the in-app notification bell in both layouts. The bell
 * polls index() for the unread badge + recent list; clicking an item (or
 * "mark all read") posts to markRead().
 */
class NotificationController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $notifications = $user->notifications()
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn ($n) => [
                'id'      => $n->id,
                'title'   => $n->data['title'] ?? 'Notification',
                'message' => $n->data['message'] ?? '',
                'link'    => $n->data['link'] ?? '#',
                'icon'    => $n->data['icon'] ?? 'bx bx-bell',
                'color'   => $n->data['color'] ?? '#64748b',
                'read'    => $n->read_at !== null,
                'when'    => $n->created_at->diffForHumans(),
            ]);

        return response()->json([
            'unread'        => $user->unreadNotifications()->count(),
            'notifications' => $notifications,
        ]);
    }

    /**
     * Marks one notification (id in the body) or everything unread (no id)
     * as read. Only ever touches the signed-in user's own rows.
     */
    public function markRead(Request $request)
    {
        $user = Auth::user();

        if ($request->filled('id')) {
            $user->unreadNotifications()->where('id', $request->input('id'))->update(['read_at' => now()]);
        } else {
            $user->unreadNotifications()->update(['read_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }
}
