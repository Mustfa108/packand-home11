<?php

namespace App\Jobs;

use App\Models\Assessment;
use App\Services\PdfReportService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Generates the assessment PDF after the HTTP response.
 * Intentionally NOT queued (no ShouldQueue) so it runs reliably without queue:work.
 */
class GenerateAssessmentPdf
{
    use Dispatchable, SerializesModels;

    public function __construct(public Assessment $assessment) {}

    public function handle(PdfReportService $pdfService): void
    {
        try {
            $path = $pdfService->generate($this->assessment->fresh());
            $this->assessment->update(['pdf_path' => $path]);
        } catch (\Throwable $exception) {
            Log::error('GenerateAssessmentPdf failed', [
                'assessment_id' => $this->assessment->id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
