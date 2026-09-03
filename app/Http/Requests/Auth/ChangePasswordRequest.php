<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8|confirmed|different:current_password',
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'حقل كلمة المرور الحالية مطلوب.',
            'new_password.required'     => 'حقل كلمة المرور الجديدة مطلوب.',
            'new_password.min'          => 'يجب أن تكون كلمة المرور الجديدة 8 أحرف على الأقل.',
            'new_password.confirmed'    => 'كلمة المرور الجديدة وتأكيدها غير متطابقتين.',
            'new_password.different'    => 'يجب أن تختلف كلمة المرور الجديدة عن الحالية.',
        ];
    }
}
