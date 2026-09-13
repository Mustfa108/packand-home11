<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AssessmentVersion;
use App\Models\Pillar;
use App\Models\Question;
use App\Services\AdminAuditLogger;
use App\Services\AssessmentVersionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin management of questions. Questions are never deleted once used in a
 * prior assessment — they are deactivated, or a new draft version is created.
 */
class AdminQuestionController extends Controller
{
    public function __construct(private AssessmentVersionManager $manager) {}

    public function index(Request $request): JsonResponse
    {
        $version = $this->resolveVersion($request);
        if ($version === 404) {
            return ApiResponse::error('الإصدار المطلوب غير موجود.', 404);
        }

        $query = Question::with('pillar')->orderBy('display_order');
        $query->where('assessment_version_id', $version?->id);

        $questions = $query->get()->map(fn ($question) => [
            'id'            => $question->id,
            'pillar_id'     => $question->pillar_id,
            'pillar_name_ar' => $question->pillar?->name_ar,
            'text_ar'       => $question->text_ar,
            'text_en'       => $question->text_en,
            'display_order' => $question->display_order,
            'weight'        => $question->weight,
            'is_active'     => $question->is_active,
            'used_in_assessments' => $question->used_in_assessments,
        ]);

        return ApiResponse::success([
            'version'     => $version?->only(['id', 'version_number', 'status']),
            'is_editable' => $version === null || $version->isEditable(),
            'questions'   => $questions,
        ], 'تم تحميل الأسئلة.');
    }

    public function store(Request $request): JsonResponse
    {
        $version = $this->resolveVersion($request);
        if ($version === 404) {
            return ApiResponse::error('الإصدار المطلوب غير موجود.', 404);
        }

        if ($version && ! $version->isEditable()) {
            return ApiResponse::error('لا يمكن تعديل الإصدار المنشور. أنشئ مسودة جديدة أولاً.', 403);
        }

        $validated = $request->validate([
            'pillar_id'     => ['required', 'integer', Rule::exists('pillars', 'id')->where(fn ($q) => $q->where('assessment_version_id', $version?->id))],
            'text_ar'       => ['required', 'string', 'max:1000'],
            'text_en'       => ['nullable', 'string', 'max:1000'],
            'display_order' => ['required', 'integer', 'min:0', 'max:255'],
            'weight'        => ['required', 'numeric', 'min:0', 'max:100'],
        ], [
            'pillar_id.required' => 'يجب ربط السؤال بمحور.',
            'pillar_id.exists'   => 'المحور المحدد غير موجود في هذا الإصدار.',
            'text_ar.required'   => 'نص السؤال مطلوب.',
            'weight.required'    => 'وزن السؤال مطلوب.',
            'weight.max'         => 'وزن السؤال يجب أن يكون بين 0 و 100.',
        ]);

        $question = Question::create([
            'assessment_version_id' => $version?->id,
            'pillar_id'             => $validated['pillar_id'],
            'text_ar'               => $validated['text_ar'],
            'text_en'               => $validated['text_en'] ?? null,
            'display_order'         => $validated['display_order'],
            'weight'                => $validated['weight'],
            'is_active'             => true,
        ]);

        AdminAuditLogger::record($request->user()?->id, 'question.created', 'question', $question->id, ['text_ar' => mb_substr($question->text_ar, 0, 80)], $request);

        return ApiResponse::success(['id' => $question->id], 'تم إضافة السؤال بنجاح.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $question = Question::find($id);

        if (! $question) {
            return ApiResponse::error('السؤال المطلوب غير موجود.', 404);
        }

        $version = $question->assessment_version_id
            ? AssessmentVersion::find($question->assessment_version_id)
            : null;

        if ($version && ! $version->isEditable()) {
            return ApiResponse::error('لا يمكن تعديل أسئلة الإصدار المنشور. أنشئ مسودة جديدة أولاً.', 403);
        }

        $validated = $request->validate([
            'pillar_id'     => ['sometimes', 'integer', Rule::exists('pillars', 'id')->where(fn ($q) => $q->where('assessment_version_id', $question->assessment_version_id))],
            'text_ar'       => ['sometimes', 'string', 'max:1000'],
            'text_en'       => ['sometimes', 'nullable', 'string', 'max:1000'],
            'display_order' => ['sometimes', 'integer', 'min:0', 'max:255'],
            'weight'        => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ]);

        $question->update($validated);

        AdminAuditLogger::record($request->user()?->id, 'question.updated', 'question', $question->id, $validated, $request);

        return ApiResponse::success(['id' => $question->id], 'تم تحديث السؤال بنجاح.');
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        $question = Question::find($id);

        if (! $question) {
            return ApiResponse::error('السؤال المطلوب غير موجود.', 404);
        }

        $version = $question->assessment_version_id
            ? AssessmentVersion::find($question->assessment_version_id)
            : null;

        if ($version && ! $version->isEditable()) {
            return ApiResponse::error('لا يمكن تعطيل أسئلة الإصدار المنشور. أنشئ مسودة جديدة أولاً.', 403);
        }

        $question->update(['is_active' => ! $question->is_active]);

        AdminAuditLogger::record(
            $request->user()?->id,
            $question->is_active ? 'question.activated' : 'question.deactivated',
            'question',
            $question->id,
            null,
            $request
        );

        return ApiResponse::success(
            ['id' => $question->id, 'is_active' => $question->is_active],
            $question->is_active
                ? 'تم تنشيط السؤال.'
                : 'تم تعطيل السؤال دون حذفه حفاظاً على صحة التقييمات السابقة.'
        );
    }

    private function resolveVersion(Request $request): AssessmentVersion|null|int
    {
        if ($request->filled('version_id')) {
            $version = AssessmentVersion::find($request->integer('version_id'));

            return $version ?? 404;
        }

        return $this->manager->draft() ?? $this->manager->published();
    }
}
