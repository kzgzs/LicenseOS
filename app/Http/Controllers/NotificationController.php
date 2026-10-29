<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * List all notifications for the current user (paginated).
     */
    public function index(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    /**
     * Get recent unread notifications (JSON, for navbar dropdown).
     */
    public function unread(Request $request)
    {
        $unread = $request->user()
            ->unreadNotifications()
            ->latest()
            ->limit(5)
            ->get();

        return response()->json([
            'data' => $unread->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? '',
                'message' => $n->data['message'] ?? '',
                'icon' => $n->data['icon'] ?? 'bell',
                'url' => $n->data['url'] ?? '#',
                'created_at' => $n->created_at->diffForHumans(),
                'read' => $n->read_at !== null,
            ]),
            'count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markRead(Request $request, string $id)
    {
        $request->user()
            ->notifications()
            ->where('id', $id)
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', __('notification.all_marked_read'));
    }
}
