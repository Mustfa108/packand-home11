<?php

namespace App\Http\Controllers\Admin;

use App\Events\CommunityMessageSent;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CommunityMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCommunityChatController extends Controller
{
    public function index(): JsonResponse
    {
        $messages = CommunityMessage::query()
            ->latest('id')
            ->limit(150)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (CommunityMessage $message) => $message->toApiArray());

        return ApiResponse::success([
            'messages' => $messages,
            'channel' => 'community-chat',
        ], 'تم تحميل دردشة المجتمع.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        $admin = $request->user();

        $message = CommunityMessage::create([
            'user_id' => null,
            'admin_id' => $admin->id,
            'is_admin' => true,
            'sender_name' => $admin->name ?: 'الإدارة',
            'body' => trim($validated['body']),
        ]);

        broadcast(new CommunityMessageSent($message))->toOthers();

        return ApiResponse::success($message->toApiArray(), 'تم إرسال رسالة الإدارة.', 201);
    }
}
