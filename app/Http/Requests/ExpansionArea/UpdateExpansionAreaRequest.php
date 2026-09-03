<?php

namespace App\Http\Requests\ExpansionArea;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpansionAreaRequest extends FormRequest
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
            'name_ar' => ['sometimes', 'required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'lat' => ['sometimes', 'required', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'required', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
