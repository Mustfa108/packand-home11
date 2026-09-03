<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'              => 'required|string|max:255',
            'email'             => 'required|email|unique:users,email',
            'password'          => 'required|string|min:8|confirmed',
            'organization_name' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'              => 'حقل الاسم مطلوب.',
            'name.max'                   => 'يجب ألا يتجاوز الاسم 255 حرفًا.',
            'email.required'             => 'حقل البريد الإلكتروني مطلوب.',
            'email.email'                => 'يجب إدخال بريد إلكتروني صالح.',
            'email.unique'               => 'هذا البريد الإلكتروني مسجل بالفعل.',
            'password.required'          => 'حقل كلمة المرور مطلوب.',
            'password.min'               => 'يجب أن تكون كلمة المرور 8 أحرف على الأقل.',
            'password.confirmed'         => 'كلمة المرور وتأكيدها غير متطابقتين.',
            'organization_name.max'      => 'يجب ألا يتجاوز اسم المنظمة 255 حرفًا.',
        ];
    }
}
