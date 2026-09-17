<?php

namespace App\Services\Reports;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

// Writes one or more sheets to a temporary .xlsx and streams it as a download.
class XlsxRenderer
{
    /**
     * @param  array<string, array{headers: string[], rows: iterable<array>}>  $sheets  sheet name => data
     */
    public function render(array $sheets, string $filename): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $writer = new Writer();
        $writer->openToFile($path);

        $bold = (new Style())->setFontBold();
        $first = true;
        foreach ($sheets as $name => $sheet) {
            if ($first) {
                $writer->getCurrentSheet()->setName($name);
                $first = false;
            } else {
                $writer->addNewSheetAndMakeItCurrent()->setName($name);
            }
            // Excel renders right-to-left when the sheet view says so.
            $writer->getCurrentSheet()->setSheetView((new SheetView())->setRightToLeft(true));
            $writer->addRow(Row::fromValues($sheet['headers'], $bold));
            foreach ($sheet['rows'] as $row) {
                $writer->addRow(Row::fromValues(array_values($row)));
            }
        }
        $writer->close();

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
