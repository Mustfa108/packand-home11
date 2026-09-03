<?php

namespace App\Jobs;

use App\Models\Assessment;
use App\Services\PdfReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateAssessmentPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries   = 3;

    public function __construct(public Assessment $assessment)
    {
    }

    public function handle(PdfReportService $pdfService): void
    {
        $path = $pdfService->generate($this->assessment);
        $this->assessment->update(['pdf_path' => $path]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateAssessmentPdf job failed', [
            'assessment_id' => $this->assessment->id,
            'error'         => $exception->getMessage(),
        ]);
    }
}
