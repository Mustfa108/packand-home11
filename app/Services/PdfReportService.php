<?php

namespace App\Services;

use App\Models\Assessment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PdfReportService
{
    public function generate(Assessment $assessment): string
    {
        // Load all relationships
        $assessment->load([
            'user',
            'pillarResults.pillar',
            'actionPlan.items.pillar',
        ]);

        $pdf = Pdf::loadView('pdf.assessment_report', [
            'assessment' => $assessment,
        ]);

        $pdf->setPaper('A4', 'portrait');
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('defaultFont', 'DejaVu Sans');

        $filename = "assessment_{$assessment->id}_{$assessment->user_id}.pdf";
        $path     = "reports/{$filename}";

        Storage::disk('public')->put($path, $pdf->output());

        return $path;
    }
}
