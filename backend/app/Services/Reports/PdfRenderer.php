<?php

namespace App\Services\Reports;

use Illuminate\Http\Response;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;

// Renders a Blade view to an A4 RTL PDF. mPDF is pure PHP (no Chrome). The
// documents are set in Thmanyah Sans, the typeface of the apps themselves, so
// what the office prints looks like what it sees on screen; the files under
// resources/fonts are the app's woff2 fonts converted to TrueType outlines,
// which is the only kind mPDF reads.
class PdfRenderer
{
    public function render(string $view, array $data, string $filename): Response
    {
        $tempDir = storage_path('app/mpdf');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $fontDirs = (new ConfigVariables)->getDefaults()['fontDir'];
        $fontData = (new FontVariables)->getDefaults()['fontdata'];

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'fontDir' => array_merge($fontDirs, [resource_path('fonts')]),
            'fontdata' => $fontData + ['thmanyah' => ['R' => 'ThmanyahSans-Regular.ttf', 'B' => 'ThmanyahSans-Bold.ttf', 'useOTL' => 0xFF, 'useKashida' => 75]],
            'default_font' => 'thmanyah',
            'default_font_size' => 9,
            'margin_top' => 14,
            'margin_bottom' => 20,
            'margin_left' => 14,
            'margin_right' => 14,
            'margin_footer' => 8,
            'tempDir' => $tempDir,
        ]);
        $mpdf->SetDirectionality('rtl');
        $mpdf->SetTitle($data['title'] ?? $filename);
        $mpdf->SetAuthor(config('company.name'));
        $mpdf->SetHTMLFooter(view('reports._footer')->render());
        $mpdf->WriteHTML(view($view, $data)->render());

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
