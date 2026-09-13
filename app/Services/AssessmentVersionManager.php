<?php

namespace App\Services;

use App\Models\AssessmentVersion;
use App\Models\Pillar;
use App\Models\Question;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Manages questionnaire versioning: draft creation by copying the latest
 * source, publishing (one published version at a time), and weight-sum
 * validation. Published versions are immutable — admin edits happen on drafts.
 */
class AssessmentVersionManager
{
    public function published(): ?AssessmentVersion
    {
        return AssessmentVersion::where('status', 'published')->orderByDesc('version_number')->first();
    }

    public function draft(): ?AssessmentVersion
    {
        return AssessmentVersion::where('status', 'draft')->orderByDesc('version_number')->first();
    }

    /**
     * Create a new draft version by copying the latest source (published
     * version if any, otherwise the legacy global questionnaire).
     */
    public function createDraft(?int $adminId, ?string $notes = null): AssessmentVersion
    {
        if ($this->draft()) {
            throw new InvalidArgumentException('يوجد إصدار مسودة قيد التحضير بالفعل. أكمله أو انشره أولاً.');
        }

        return DB::transaction(function () use ($adminId, $notes) {
            $source = $this->published();

            $version = AssessmentVersion::create([
                'version_number' => (int) AssessmentVersion::max('version_number') + 1,
                'status'         => 'draft',
                'notes_ar'       => $notes,
                'created_by'     => $adminId,
            ]);

            if ($source) {
                $this->copySource($source->pillars()->with('questions')->orderBy('display_order')->get(), $version);
            } else {
                $this->copySource(
                    Pillar::whereNull('assessment_version_id')->with('questions')->orderBy('display_order')->get(),
                    $version
                );
            }

            return $version;
        });
    }

    /**
     * Validate weight sums: axis weights should total 100 and each axis's
     * question weights should total 100.
     */
    public function weightReport(AssessmentVersion $version): array
    {
        $axes = $version->pillars()->with('questions')->orderBy('display_order')->get();

        $axisWeightSum = round((float) $axes->sum('weight'), 2);

        $axisReports = $axes->map(function ($pillar) {
            $questions = $pillar->questions->where('is_active', true);
            $questionWeightSum = round((float) $questions->sum('weight'), 2);

            return [
                'pillar_id'            => $pillar->id,
                'pillar_name_ar'       => $pillar->name_ar,
                'axis_weight'          => (float) $pillar->weight,
                'questions_count'      => $questions->count(),
                'question_weight_sum'  => $questionWeightSum,
                'question_weights_valid' => $questions->count() > 0 && abs($questionWeightSum - 100) < 0.05,
            ];
        })->values();

        return [
            'axis_weight_sum'    => $axisWeightSum,
            'axis_weights_valid' => $axes->count() > 0 && abs($axisWeightSum - 100) < 0.05,
            'axes'               => $axisReports,
        ];
    }

    /**
     * Publish a draft after validating weights and question coverage.
     * Only one published version may exist at any time.
     */
    public function publish(AssessmentVersion $version, ?int $adminId): AssessmentVersion
    {
        if ($version->status !== 'draft') {
            throw new InvalidArgumentException('يمكن نشر الإصدارات المسودة فقط.');
        }

        $report = $this->weightReport($version);

        if (! $report['axis_weights_valid']) {
            throw new InvalidArgumentException(
                "مجموع أوزان المحاور هو {$report['axis_weight_sum']}% ويجب أن يكون 100%."
            );
        }

        $invalidAxes = collect($report['axes'])->filter(fn ($axis) => ! $axis['question_weights_valid']);
        if ($invalidAxes->isNotEmpty()) {
            $names = $invalidAxes->pluck('pillar_name_ar')->join('، ');
            throw new InvalidArgumentException("مجموع أوزان الأسئلة غير صحيح في المحاور: {$names}.");
        }

        return DB::transaction(function () use ($version, $adminId) {
            AssessmentVersion::where('status', 'published')->update([
                'status' => 'archived',
            ]);

            $version->update([
                'status'       => 'published',
                'published_at' => now(),
            ]);

            return $version->fresh();
        });
    }

    private function copySource($axes, AssessmentVersion $target): void
    {
        $axes = $axes instanceof \Illuminate\Support\Collection ? $axes : collect($axes);
        $axisWeight = $axes->count() > 0 ? round(100 / $axes->count(), 2) : 1;

        foreach ($axes as $axis) {
            $newAxis = Pillar::create([
                'assessment_version_id' => $target->id,
                'key'                   => $axis->key,
                'name_ar'               => $axis->name_ar,
                'name_en'               => $axis->name_en,
                'description_ar'        => $axis->description_ar,
                'description_en'        => $axis->description_en,
                'display_order'         => $axis->display_order,
                'weight'                => $axisWeight,
                'is_active'             => true,
            ]);

            $questions = $axis->questions->where('is_active', true)->values();
            $questionWeight = $questions->count() > 0 ? round(100 / $questions->count(), 2) : 1;

            foreach ($questions as $question) {
                Question::create([
                    'assessment_version_id' => $target->id,
                    'pillar_id'             => $newAxis->id,
                    'text_ar'               => $question->text_ar,
                    'text_en'               => $question->text_en,
                    'display_order'         => $question->display_order,
                    'weight'                => $questionWeight,
                    'is_active'             => true,
                ]);
            }
        }
    }
}
