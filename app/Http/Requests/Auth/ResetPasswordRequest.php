<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => 'required|email',
            'token'    => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'    => 'حقل البريد الإلكتروني مطلوب.',
            'email.email'       => 'يجب إدخال بريد إلكتروني صالح.',
            'token.required'    => 'رمز إعادة التعيين مطلوب.',
            'password.required' => 'حقل كلمة المرور مطلوب.',
            'password.min'      => 'يجب أن تكون كلمة المرور 8 أحرف على الأقل.',
            'password.confirmed'=> 'كلمة المرور وتأكيدها غير متطابقتين.',
        ];
    }
}
