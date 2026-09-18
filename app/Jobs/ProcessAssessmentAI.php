<?php

namespace App\Jobs;

use App\Models\ActionPlanItem;
use App\Models\Assessment;
use App\Notifications\AssessmentCompletedNotification;
use App\Services\GeminiAssessmentAnalysisService;
use App\Services\GeminiNlgService;
use App\Services\PdfReportService;
use App\Services\RuleBasedRecommendationService;
use App\Support\GeminiHelpers;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAssessmentAI implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries = 3;

    public array $backoff = [30, 60];

    public function __construct(public Assessment $assessment) {}

    public function handle(
        GeminiNlgService $gemini,
        GeminiAssessmentAnalysisService $analysisService,
        PdfReportService $pdfService,
        RuleBasedRecommendationService $rules
    ): void {
        $summary = $gemini->generateAssessmentSummary($this->assessment);

        if ($summary) {
            $this->assessment->update([
                'ai_summary_ar' => $summary,
                'ai_generated_at' => now(),
            ]);
        } elseif (! $this->assessment->ai_summary_ar) {
            // Always persist a readable summary so the results card and PDF are not empty.
            $this->assessment->update([
                'ai_summary_ar' => $rules->summary($this->assessment),
                'ai_generated_at' => now(),
            ]);
        }

        // Structured analysis — skip if a usable record already exists.
        try {
            $hasAnalysis = \App\Models\AiAnalysis::where('assessment_id', $this->assessment->id)
                ->whereIn('status', ['completed', 'approved'])
                ->exists();

            if (! $hasAnalysis) {
                $analysisService->generate($this->assessment);
            }
        } catch (\Throwable $e) {
            Log::channel('ai')->error('Assessment analysis generation failed', [
                'assessment_id' => $this->assessment->id,
                'error' => GeminiHelpers::redactSecrets($e->getMessage()),
            ]);
        }

        $items = ActionPlanItem::with(['actionPlan', 'pillar'])
            ->whereHas('actionPlan', fn ($q) => $q->where('assessment_id', $this->assessment->id))
            ->get();

        if ($items->isNotEmpty()) {
            $actionsForAi = $items->map(fn ($item) => [
                'id' => $item->id,
                'phase_label_ar' => $item->phase_label_ar,
                'pillar_name_ar' => $item->pillar->name_ar,
                'action_ar' => $item->action_ar,
            ])->toArray();

            $rephrased = $gemini->rephraseActionPlanItems($actionsForAi);

            if ($rephrased) {
                foreach ($items as $index => $item) {
                    if (isset($rephrased[$index]['ai_rephrased_ar'])) {
                        // KPIs stay rule-based; AI may only store rephrased action text.
                        $item->update([
                            'ai_rephrased_ar' => $rephrased[$index]['ai_rephrased_ar'],
                        ]);
                    }
                }
            }
        }

        // Rebuild PDF after AI so the smart summary is included.
        try {
            $path = $pdfService->generate($this->assessment->fresh());
            $this->assessment->update(['pdf_path' => $path]);
        } catch (\Throwable $e) {
            Log::channel('ai')->error('PDF regenerate after AI failed', [
                'assessment_id' => $this->assessment->id,
                'error' => GeminiHelpers::redactSecrets($e->getMessage()),
            ]);
        }

        $this->assessment->user->notify(new AssessmentCompletedNotification($this->assessment));
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('ai')->error('ProcessAssessmentAI job failed', [
            'assessment_id' => $this->assessment->id,
            'error' => GeminiHelpers::redactSecrets($exception->getMessage()),
        ]);

        $this->assessment->user->notify(new AssessmentCompletedNotification($this->assessment));
    }
}
