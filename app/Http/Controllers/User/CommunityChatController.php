<?php

namespace App\Http\Controllers\User;

use App\Events\CommunityMessageSent;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CommunityMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $messages = CommunityMessage::query()
            ->latest('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (CommunityMessage $message) => $message->toApiArray());

        return ApiResponse::success([
            'messages' => $messages,
            'channel' => 'community-chat',
        ], 'تم تحميل رسائل الدردشة المجتمعية.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:2000'],
        ], [
            'body.required' => 'نص الرسالة مطلوب.',
            'body.max' => 'الرسالة طويلة جداً.',
        ]);

        $user = $request->user();

        $message = CommunityMessage::create([
            'user_id' => $user->id,
            'admin_id' => null,
            'is_admin' => false,
            'sender_name' => $user->name ?: ($user->organization_name ?: 'مستخدم'),
            'body' => trim($validated['body']),
        ]);

        broadcast(new CommunityMessageSent($message))->toOthers();

        return ApiResponse::success($message->toApiArray(), 'تم إرسال الرسالة.', 201);
    }
}
