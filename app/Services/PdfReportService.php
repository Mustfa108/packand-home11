<?php

namespace App\Services;

use App\Models\Assessment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

class PdfReportService
{
    /**
     * Generate an Arabic RTL assessment PDF using mPDF and store it on the public disk.
     *
     * @throws \Mpdf\MpdfException
     */
    public function generate(Assessment $assessment): string
    {
        $assessment->load([
            'user',
            'pillarResults.pillar',
            'actionPlan.items.pillar',
        ]);

        $html = View::make('pdf.assessment_report', [
            'assessment' => $assessment,
        ])->render();

        $tempDir = storage_path('app/mpdf-temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $defaultConfig = (new ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];

        $defaultFontConfig = (new FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 14,
            'margin_bottom' => 14,
            'default_font' => 'dejavusans',
            'default_font_size' => 11,
            'directionality' => 'rtl',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'tempDir' => $tempDir,
            'fontDir' => $fontDirs,
            'fontdata' => $fontData,
        ]);

        $mpdf->SetDirectionality('rtl');
        $mpdf->WriteHTML($html);

        $filename = "assessment_{$assessment->id}_{$assessment->user_id}.pdf";
        $path = "reports/{$filename}";

        Storage::disk('public')->put($path, $mpdf->Output('', 'S'));

        return $path;
    }
}
