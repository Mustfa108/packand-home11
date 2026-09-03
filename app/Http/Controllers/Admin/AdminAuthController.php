<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $admin = Admin::where('email', $request->email)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return ApiResponse::error('البريد الإلكتروني أو كلمة المرور غير صحيحة.', 401);
        }

        // Delete old tokens
        $admin->tokens()->delete();

        $token = $admin->createToken('admin_token')->plainTextToken;

        return ApiResponse::success([
            'token'      => $token,
            'token_type' => 'Bearer',
            'admin'      => [
                'id'    => $admin->id,
                'name'  => $admin->name,
                'email' => $admin->email,
            ],
        ], 'تم تسجيل دخول المدير بنجاح.');
    }
}
