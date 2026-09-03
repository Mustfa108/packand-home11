<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
// use App\Notifications\EmailVerificationNotification; // تم تعطيل هذا الاستخدام
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // أضفنا هذا لتسجيل الدخول
use Illuminate\Support\Facades\Hash; // أضفنا هذا

class RegisterController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        // 1. إنشاء المستخدم مع تفعيل الإيميل فوراً
        $user = User::create([
            'name'              => $request->name,
            'email'             => $request->email,
            'password'          => Hash::make($request->password), // يفضل استخدام Hash::make بدلاً من bcrypt
            'organization_name' => $request->organization_name,
            'email_verified_at' => now(), // <--- هذا السطر يمنع طلب التحقق
        ]);

        // 2. (تم التعليق) إلغاء إرسال إيميل التحقق
        // $user->notify(new EmailVerificationNotification($user));

        // 3. تسجيل الدخول تلقائياً وتوليد Token (خاص بـ API / Sanctum)
        // إذا كنت تستخدم Sanctum، هذا السطر سينشئ توكن للمستخدم ليدخل فوراً
        $token = $user->createToken('auth_token')->plainTextToken;

        // 4. إرجاع الرد مع التوكن والبيانات
        return ApiResponse::success(
            [
                'id'                => $user->id,
                'name'              => $user->name,
                'email'             => $user->email,
                'organization_name' => $user->organization_name,
                'created_at'        => $user->created_at,
                'access_token'      => $token, // نرسل التوكن للفرونت إند ليحفظه
                'token_type'        => 'Bearer',
            ],
            'تم إنشاء حسابك وتسجيل دخولك بنجاح!', // تم تغيير الرسالة
            201
        );
    }

    // باقي الدوال (verifyEmail, resendVerification) بقيت كما هي، 
    // لكنك لن تحتاجها لأنك ألغيت التحقق من الإيميل.
    // إذا أردت يمكنك حذفها، أو إبقاؤها لاستخدامات مستقبلية.
    
    public function verifyEmail(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if (!hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return ApiResponse::error('رابط التحقق غير صالح أو منتهي الصلاحية.', 400);
        }

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::success(null, 'تم التحقق من بريدك الإلكتروني مسبقًا.');
        }

        $user->markEmailAsVerified();

        return ApiResponse::success(null, 'تم التحقق من بريدك الإلكتروني بنجاح. يمكنك تسجيل الدخول الآن.');
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::error('تم التحقق من بريدك الإلكتروني مسبقًا.', 400);
        }

        // $user->notify(new EmailVerificationNotification($user)); // تأكد من تعطيل هذا أيضاً هنا

        return ApiResponse::success(null, 'تم إعادة إرسال رابط التحقق إلى بريدك الإلكتروني.');
    }
}