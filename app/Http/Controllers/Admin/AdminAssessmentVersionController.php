<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AssessmentVersion;
use App\Services\AdminAuditLogger;
use App\Services\AssessmentVersionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AdminAssessmentVersionController extends Controller
{
    public function __construct(private AssessmentVersionManager $manager) {}

    public function index(): JsonResponse
    {
        $versions = AssessmentVersion::withCount(['pillars', 'questions'])
            ->orderByDesc('version_number')
            ->get()
            ->map(fn ($version) => [
                'id'             => $version->id,
                'version_number' => $version->version_number,
                'status'         => $version->status,
                'notes_ar'       => $version->notes_ar,
                'published_at'   => $version->published_at,
                'axes_count'     => $version->pillars_count,
                'questions_count' => $version->questions_count,
                'is_editable'    => $version->isEditable(),
                'created_at'     => $version->created_at,
            ]);

        return ApiResponse::success([
            'versions'            => $versions,
            'published_version'   => $this->manager->published()?->only(['id', 'version_number']),
            'draft_version'       => $this->manager->draft()?->only(['id', 'version_number']),
        ], 'تم تحميل إصدارات التقييم.');
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'notes_ar' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $version = $this->manager->createDraft($request->user()?->id, $request->notes_ar);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        AdminAuditLogger::record(
            $request->user()?->id,
            'assessment_version.created',
            'assessment_version',
            $version->id,
            ['version_number' => $version->version_number],
            $request
        );

        return ApiResponse::success([
            'id'             => $version->id,
            'version_number' => $version->version_number,
            'status'         => $version->status,
        ], 'تم إنشاء مسودة إصدار جديد بنجاح.', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $version = AssessmentVersion::with(['pillars.questions' => fn ($q) => $q->orderBy('display_order')])
            ->find($id);

        if (! $version) {
            return ApiResponse::error('الإصدار المطلوب غير موجود.', 404);
        }

        return ApiResponse::success([
            'id'             => $version->id,
            'version_number' => $version->version_number,
            'status'         => $version->status,
            'is_editable'    => $version->isEditable(),
            'weight_report'  => $this->manager->weightReport($version),
            'axes'           => $version->pillars->sortBy('display_order')->map(fn ($pillar) => [
                'id'             => $pillar->id,
                'key'            => $pillar->key,
                'name_ar'        => $pillar->name_ar,
                'name_en'        => $pillar->name_en,
                'description_ar' => $pillar->description_ar,
                'description_en' => $pillar->description_en,
                'display_order'  => $pillar->display_order,
                'weight'         => $pillar->weight,
                'is_active'      => $pillar->is_active,
                'questions'      => $pillar->questions->map(fn ($question) => [
                    'id'            => $question->id,
                    'pillar_id'     => $question->pillar_id,
                    'text_ar'       => $question->text_ar,
                    'text_en'       => $question->text_en,
                    'display_order' => $question->display_order,
                    'weight'        => $question->weight,
                    'is_active'     => $question->is_active,
                    'used_in_assessments' => $question->used_in_assessments,
                ])->values(),
            ])->values(),
        ], 'تم تحميل تفاصيل الإصدار.');
    }

    public function publish(Request $request, int $id): JsonResponse
    {
        $version = AssessmentVersion::find($id);

        if (! $version) {
            return ApiResponse::error('الإصدار المطلوب غير موجود.', 404);
        }

        try {
            $published = $this->manager->publish($version, $request->user()?->id);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        AdminAuditLogger::record(
            $request->user()?->id,
            'assessment_version.published',
            'assessment_version',
            $published->id,
            ['version_number' => $published->version_number],
            $request
        );

        return ApiResponse::success([
            'id'             => $published->id,
            'version_number' => $published->version_number,
            'status'         => $published->status,
            'published_at'   => $published->published_at,
        ], 'تم نشر الإصدار بنجاح وأصبح الإصدار المعتمد للتقييمات الجديدة.');
    }
}
