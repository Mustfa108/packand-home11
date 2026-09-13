<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AssessmentVersion;
use App\Models\Pillar;
use App\Services\AdminAuditLogger;
use App\Services\AssessmentVersionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin management of axes (pillars). Editing is allowed on draft versions
 * only, or on the legacy global questionnaire when no versions exist yet.
 * Axes are never hard-deleted — they are deactivated instead.
 */
class AdminAxisController extends Controller
{
    public function __construct(private AssessmentVersionManager $manager) {}

    public function index(Request $request): JsonResponse
    {
        $version = $this->resolveVersion($request);
        if ($version === 404) {
            return ApiResponse::error('الإصدار المطلوب غير موجود.', 404);
        }

        $query = Pillar::withCount(['questions' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('display_order');

        $query->where('assessment_version_id', $version?->id);

        $axes = $query->get()->map(fn ($pillar) => [
            'id'             => $pillar->id,
            'key'            => $pillar->key,
            'name_ar'        => $pillar->name_ar,
            'name_en'        => $pillar->name_en,
            'description_ar' => $pillar->description_ar,
            'description_en' => $pillar->description_en,
            'display_order'  => $pillar->display_order,
            'weight'         => $pillar->weight,
            'is_active'      => $pillar->is_active,
            'questions_count' => $pillar->questions_count,
        ]);

        return ApiResponse::success([
            'version'       => $version?->only(['id', 'version_number', 'status']),
            'is_editable'   => $version === null || $version->isEditable(),
            'axes'          => $axes,
            'weight_report' => $version ? $this->manager->weightReport($version) : null,
        ], 'تم تحميل المحاور.');
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
            'key'            => ['required', 'string', 'max:50', 'alpha_dash'],
            'name_ar'        => ['required', 'string', 'max:255'],
            'name_en'        => ['nullable', 'string', 'max:255'],
            'description_ar' => ['required', 'string'],
            'description_en' => ['nullable', 'string'],
            'display_order'  => ['required', 'integer', 'min:0', 'max:255'],
            'weight'         => ['required', 'numeric', 'min:0', 'max:100'],
        ], [
            'key.required'            => 'مفتاح المحور مطلوب.',
            'name_ar.required'        => 'اسم المحور بالعربية مطلوب.',
            'description_ar.required' => 'وصف المحور مطلوب.',
            'weight.required'         => 'وزن المحور مطلوب.',
            'weight.max'              => 'وزن المحور يجب أن يكون بين 0 و 100.',
        ]);

        $duplicate = Pillar::where('assessment_version_id', $version?->id)->where('key', $validated['key'])->exists();
        if ($duplicate) {
            return ApiResponse::error('مفتاح المحور مستخدم مسبقاً في هذا الإصدار.', 422);
        }

        $axis = Pillar::create([
            'assessment_version_id' => $version?->id,
            'key'                   => $validated['key'],
            'name_ar'               => $validated['name_ar'],
            'name_en'               => $validated['name_en'] ?? null,
            'description_ar'        => $validated['description_ar'],
            'description_en'        => $validated['description_en'] ?? null,
            'display_order'         => $validated['display_order'],
            'weight'                => $validated['weight'],
            'is_active'             => true,
        ]);

        AdminAuditLogger::record($request->user()?->id, 'axis.created', 'pillar', $axis->id, ['name_ar' => $axis->name_ar], $request);

        return ApiResponse::success(['id' => $axis->id], 'تم إضافة المحور بنجاح.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $axis = Pillar::find($id);

        if (! $axis) {
            return ApiResponse::error('المحور المطلوب غير موجود.', 404);
        }

        $version = $axis->assessment_version_id
            ? AssessmentVersion::find($axis->assessment_version_id)
            : null;

        if ($version && ! $version->isEditable()) {
            return ApiResponse::error('لا يمكن تعديل محاور الإصدار المنشور. أنشئ مسودة جديدة أولاً.', 403);
        }

        $validated = $request->validate([
            'name_ar'        => ['sometimes', 'string', 'max:255'],
            'name_en'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'description_ar' => ['sometimes', 'string'],
            'description_en' => ['sometimes', 'nullable', 'string'],
            'display_order'  => ['sometimes', 'integer', 'min:0', 'max:255'],
            'weight'         => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ]);

        $axis->update($validated);

        AdminAuditLogger::record($request->user()?->id, 'axis.updated', 'pillar', $axis->id, $validated, $request);

        return ApiResponse::success(['id' => $axis->id], 'تم تحديث المحور بنجاح.');
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        $axis = Pillar::find($id);

        if (! $axis) {
            return ApiResponse::error('المحور المطلوب غير موجود.', 404);
        }

        $version = $axis->assessment_version_id
            ? AssessmentVersion::find($axis->assessment_version_id)
            : null;

        if ($version && ! $version->isEditable()) {
            return ApiResponse::error('لا يمكن تعطيل محاور الإصدار المنشور. أنشئ مسودة جديدة أولاً.', 403);
        }

        $axis->update(['is_active' => ! $axis->is_active]);

        AdminAuditLogger::record(
            $request->user()?->id,
            $axis->is_active ? 'axis.activated' : 'axis.deactivated',
            'pillar',
            $axis->id,
            ['name_ar' => $axis->name_ar],
            $request
        );

        return ApiResponse::success(
            ['id' => $axis->id, 'is_active' => $axis->is_active],
            $axis->is_active ? 'تم تنشيط المحور.' : 'تم تعطيل المحور دون حذفه حفاظاً على النتائج السابقة.'
        );
    }

    /**
     * @return AssessmentVersion|null|404
     */
    private function resolveVersion(Request $request): AssessmentVersion|null|int
    {
        if ($request->filled('version_id')) {
            $version = AssessmentVersion::find($request->integer('version_id'));

            return $version ?? 404;
        }

        return $this->manager->draft() ?? $this->manager->published();
    }
}
