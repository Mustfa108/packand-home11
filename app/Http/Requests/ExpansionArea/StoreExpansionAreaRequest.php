<?php

namespace App\Http\Requests\ExpansionArea;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpansionAreaRequest extends FormRequest
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
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name_ar.required' => 'اسم المنطقة بالعربية مطلوب.',
            'lat.required' => 'خط العرض مطلوب.',
            'lng.required' => 'خط الطول مطلوب.',
            'lat.between' => 'خط العرض يجب أن يكون بين -90 و 90.',
            'lng.between' => 'خط الطول يجب أن يكون بين -180 و 180.',
        ];
    }
}
