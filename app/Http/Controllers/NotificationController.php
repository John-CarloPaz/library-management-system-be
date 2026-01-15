<?php

namespace App\Http\Controllers;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Notification::where('user_id', $user->id)->orderByDesc('created_at');

        if ($request->boolean('unread_only')) {
            $query->where('is_read', false);
        }

        $perPage = (int) $request->query('per_page', 20);

        return response()->json($query->paginate(max($perPage, 1)));
    }

    public function unreadCount()
    {
        $user = Auth::user();

        $count = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json(['unread_count' => $count]);
    }

    public function markAsRead($id)
    {
        $user = Auth::user();

        $notification = Notification::where('user_id', $user->id)->findOrFail($id);

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json(['message' => 'Notification marked as read']);
    }

    public function bulkMarkAsRead(Request $request)
    {
        $user = Auth::user();

        $ids = $request->input('ids', []);

        if (!is_array($ids) || empty($ids)) {
            return response()->json(['message' => 'No notification IDs provided'], 422);
        }

        Notification::where('user_id', $user->id)
            ->whereIn('id', $ids)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json(['message' => 'Notifications marked as read']);
    }

    public function createAnnouncement(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Access denied: Only Super Admin can create announcements.'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'scope' => 'required|in:all,branches',
            'branch_ids' => 'array',
            'branch_ids.*' => 'integer|exists:branches,id',
        ]);

        $query = User::query()->where('is_active', true);

        if ($validated['scope'] === 'branches') {
            $branchIds = $validated['branch_ids'] ?? [];
            if (empty($branchIds)) {
                return response()->json(['message' => 'branch_ids is required when scope is branches'], 422);
            }
            $query->whereIn('branch_id', $branchIds);
        }

        $targets = $query->pluck('id');

        foreach ($targets as $targetId) {
            $notification = Notification::create([
                'user_id' => $targetId,
                'type' => 'announcement',
                'title' => $validated['title'],
                'message' => $validated['message'],
                'data' => [
                    'created_by' => $user->id,
                    'created_by_name' => $user->username,
                    'scope' => $validated['scope'],
                    'branch_ids' => $validated['branch_ids'] ?? [],
                ],
            ]);

            NotificationCreated::dispatch($notification);
        }

        return response()->json(['message' => 'Announcement sent successfully']);
    }
}
