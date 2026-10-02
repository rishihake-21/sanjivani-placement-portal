<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->latest()->limit(50)->get()
            ->map(fn ($n) => ['id' => $n->id, 'read' => $n->read_at !== null, 'created_at' => $n->created_at] + $n->data);

        return response()->json([
            'unread' => $request->user()->unreadNotifications()->count(),
            'data' => $notifications,
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return response()->json(['message' => 'Marked as read.']);
    }
}
