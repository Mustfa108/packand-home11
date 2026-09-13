<?php

namespace App\Jobs;

use App\Models\ActionPlanItem;
use App\Models\Assessment;
use App\Notifications\AssessmentCompletedNotification;
use App\Services\GeminiAssessmentAnalysisService;
use App\Services\GeminiNlgService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAssessmentAI implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    public array $backoff = [30, 60];

    public function __construct(public Assessment $assessment) {}

    public function handle(GeminiNlgService $gemini, GeminiAssessmentAnalysisService $analysisService): void
    {
        $summary = $gemini->generateAssessmentSummary($this->assessment);

        if ($summary) {
            $this->assessment->update([
                'ai_summary_ar' => $summary,
                'ai_generated_at' => now(),
            ]);
        }

        // Structured analysis (saved once; the results page reuses it instead
        // of regenerating on every visit). Falls back to rule-based content
        // when Gemini is unavailable.
        try {
            $analysisService->generate($this->assessment);
        } catch (\Throwable $e) {
            Log::channel('ai')->error('Assessment analysis generation failed', [
                'assessment_id' => $this->assessment->id,
                'error' => $e->getMessage(),
            ]);
        }

        $items = ActionPlanItem::with(['actionPlan', 'pillar'])
            ->whereHas('actionPlan', fn ($q) => $q->where('assessment_id', $this->assessment->id))
            ->get();

        if ($items->isEmpty()) {
            $this->assessment->user->notify(new AssessmentCompletedNotification($this->assessment));

            return;
        }

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

        $this->assessment->user->notify(new AssessmentCompletedNotification($this->assessment));
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('ai')->error('ProcessAssessmentAI job failed', [
            'assessment_id' => $this->assessment->id,
            'error' => $exception->getMessage(),
        ]);

        $this->assessment->user->notify(new AssessmentCompletedNotification($this->assessment));
    }
}
