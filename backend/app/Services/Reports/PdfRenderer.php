<?php

namespace App\Services\Reports;

use Illuminate\Http\Response;
use Mpdf\Mpdf;

// Renders a Blade view to an A4 RTL PDF. mPDF is pure PHP (no Chrome) and
// shapes Arabic correctly with the bundled DejaVu Sans font.
class PdfRenderer
{
    public function render(string $view, array $data, string $filename): Response
    {
        $tempDir = storage_path('app/mpdf');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'dejavusans',
            'default_font_size' => 9,
            'margin_top' => 14,
            'margin_bottom' => 14,
            'margin_left' => 12,
            'margin_right' => 12,
            'tempDir' => $tempDir,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);
        $mpdf->SetDirectionality('rtl');
        $mpdf->SetTitle($data['title'] ?? $filename);
        $mpdf->SetAuthor(config('app.name'));
        $mpdf->SetHTMLFooter('<div style="text-align:center;font-size:8pt;color:#777">'.e(config('app.name')).' · صفحة {PAGENO} من {nbpg}</div>');
        $mpdf->WriteHTML(view($view, $data)->render());

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
