<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class PasswordChangeController extends Controller
{
    public function change(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return ApiResponse::error('كلمة المرور الحالية غير صحيحة.', 422);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        // Revoke ALL user tokens
        $user->tokens()->delete();

        return ApiResponse::success(null, 'تم تغيير كلمة المرور بنجاح. يرجى تسجيل الدخول مجدداً.');
    }
}
