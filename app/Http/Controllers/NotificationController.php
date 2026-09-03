<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->paginate(15);
        $unreadCount   = $request->user()->unreadNotifications()->count();

        $items = collect($notifications->items())->map(fn ($n) => [
            'id'         => $n->id,
            'type'       => $n->data['type'] ?? null,
            'title_ar'   => $n->data['title_ar'] ?? null,
            'body_ar'    => $n->data['body_ar'] ?? null,
            'data'       => $n->data,
            'read_at'    => $n->read_at,
            'created_at' => $n->created_at,
        ]);

        return response()->json([
            'success'      => true,
            'message'      => 'تم تحميل الإشعارات بنجاح.',
            'data'         => $items,
            'unread_count' => $unreadCount,
            'meta'         => [
                'current_page' => $notifications->currentPage(),
                'last_page'    => $notifications->lastPage(),
                'per_page'     => $notifications->perPage(),
                'total'        => $notifications->total(),
            ],
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return ApiResponse::success(null, 'تم تعليم الإشعار كمقروء.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return ApiResponse::success(null, 'تم تعليم جميع الإشعارات كمقروءة.');
    }
}
