<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Helpers\ApiResponse;

class LoginController extends Controller
{
    /**
     * تسجيل دخول المستخدم (بدون أي شرط للتحقق من الإيميل)
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // 1. محاولة تسجيل الدخول باستخدام الإيميل والباسوورد
        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            
            // الحصول على بيانات المستخدم الحالي
            $user = Auth::user();

            // 2. تجاهل شرط email_verified_at تماماً
            // إذا كان الإيميل غير مفعل، نقوم بتفعيله فوراً هنا (لن يرفض الدخول)
            if (!$user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
                $user->save(); // حفظ التغيير في قاعدة البيانات
            }

            // 3. إنشاء توكن Sanctum للمستخدم (ضروري لأنك تستخدم API)
            // يفضل استخدام createToken للحصول على توكن صالح
            $token = $user->createToken('auth_token')->plainTextToken;

            // 4. إرجاع النتيجة للفرونت إند بالشكل المطلوب
            return ApiResponse::success([
                'id'            => $user->id,
                'name'          => $user->name,
                'email'         => $user->email,
                'organization_name' => $user->organization_name, // أضفت هذا الحقل في حال كان موجوداً
                'access_token'  => $token, // <--- الفرونت إند يبحث عن هذا الاسم بالضبط
                'token_type'    => 'Bearer',
            ], 'تم تسجيل الدخول بنجاح!');
        }

        // 5. إذا فشل تسجيل الدخول (باسوورد خطأ أو إيميل غير موجود)
        return ApiResponse::error('بيانات الدخول غير صحيحة (الإيميل أو كلمة المرور).', 401);
    }

    /**
     * تسجيل الخروج
     */
    public function logout(Request $request): JsonResponse
    {
        // حذف التوكن الحالي للمستخدم لتسجيل الخروج
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, 'تم تسجيل الخروج بنجاح.');
    }
}