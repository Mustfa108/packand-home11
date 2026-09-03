<?php

namespace App\Http\Requests\Profile;

use App\Enums\UserTheme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'locale' => ['sometimes', 'string', Rule::in(['ar', 'en'])],
            'theme' => ['sometimes', 'string', Rule::in(UserTheme::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'locale.in' => 'اللغة يجب أن تكون ar أو en.',
            'theme.in' => 'السمة يجب أن تكون light أو dark أو system.',
        ];
    }
}
