<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Notifications\PasswordResetNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function sendResetLink(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if ($user) {
            // Delete any existing tokens
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            $token = Str::random(64);

            DB::table('password_reset_tokens')->insert([
                'email'      => $user->email,
                'token'      => Hash::make($token),
                'created_at' => now(),
            ]);

            $user->notify(new PasswordResetNotification($token));
        }

        // Always return success — never reveal if email exists
        return ApiResponse::success(
            null,
            'إذا كان البريد مسجلاً لدينا، ستصلك رسالة تحتوي على رابط إعادة تعيين كلمة المرور.'
        );
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record) {
            return ApiResponse::error('رابط إعادة التعيين غير صالح أو منتهي الصلاحية.', 422);
        }

        // Check if token is valid
        if (!Hash::check($request->token, $record->token)) {
            return ApiResponse::error('رابط إعادة التعيين غير صالح أو منتهي الصلاحية.', 422);
        }

        // Check if token is expired (60 minutes)
        if (now()->diffInMinutes($record->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return ApiResponse::error('رابط إعادة التعيين غير صالح أو منتهي الصلاحية.', 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return ApiResponse::error('رابط إعادة التعيين غير صالح أو منتهي الصلاحية.', 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Delete token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        // Revoke all tokens
        $user->tokens()->delete();

        return ApiResponse::success(null, 'تم إعادة تعيين كلمة المرور بنجاح. يمكنك تسجيل الدخول الآن.');
    }
}
