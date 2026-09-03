<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => 'required|email',
            'password' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'    => 'حقل البريد الإلكتروني مطلوب.',
            'email.email'       => 'يجب إدخال بريد إلكتروني صالح.',
            'password.required' => 'حقل كلمة المرور مطلوب.',
        ];
    }
}
