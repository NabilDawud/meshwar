<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Get list of notifications and unread counter for authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['error' => 'غير مصرح'], 401);
        }

        $unreadCount = $user->unreadNotifications()->count();

        $perPage = max(1, (int) $request->query('per_page', 15));
        $paginated = $user->notifications()
            ->latest()
            ->paginate($perPage);

        $notifications = collect($paginated->items())->map(function ($n) {
            return [
                'id' => $n->id,
                'read' => $n->read(),
                'title' => $n->data['title'] ?? 'إشعار جديد',
                'message' => $n->data['message'] ?? '',
                'type' => $n->data['type'] ?? 'general',
                'icon' => $n->data['icon'] ?? '🔔',
                'action_url' => $n->data['action_url'] ?? null,
                'time_ago' => $n->created_at->diffForHumans(),
                'created_at' => $n->created_at->toISOString(),
            ];
        });

        return response()->json([
            'ok' => true,
            'unread_count' => $unreadCount,
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
            'per_page' => $paginated->perPage(),
            'notifications' => $notifications,
        ]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(string $id): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['error' => 'غير مصرح'], 401);
        }

        $notification = $user->notifications()->where('id', $id)->first();

        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json([
            'ok' => true,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark all notifications as read for authenticated user.
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['error' => 'غير مصرح'], 401);
        }

        $user->unreadNotifications->markAsRead();

        return response()->json([
            'ok' => true,
            'unread_count' => 0,
        ]);
    }
}
