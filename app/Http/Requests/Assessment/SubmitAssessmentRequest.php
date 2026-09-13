<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answers'                => 'required|array|min:1',
            'answers.*.question_id'  => 'required|integer|exists:questions,id|distinct',
            'answers.*.score'        => 'required|integer|min:1|max:5',
        ];
    }

    public function messages(): array
    {
        return [
            'answers.required'               => 'يجب تقديم إجابات التقييم.',
            'answers.array'                  => 'صيغة الإجابات غير صحيحة.',
            'answers.*.question_id.required' => 'معرّف السؤال مطلوب لكل إجابة.',
            'answers.*.question_id.exists'   => 'أحد معرّفات الأسئلة غير موجود.',
            'answers.*.question_id.distinct' => 'لا يمكن تكرار السؤال نفسه.',
            'answers.*.score.required'       => 'الدرجة مطلوبة لكل سؤال.',
            'answers.*.score.min'            => 'الحد الأدنى للدرجة هو 1.',
            'answers.*.score.max'            => 'الحد الأقصى للدرجة هو 5.',
        ];
    }
}
