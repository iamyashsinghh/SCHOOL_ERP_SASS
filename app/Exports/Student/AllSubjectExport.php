<?php

namespace App\Exports\Student;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class AllSubjectExport implements FromArray, ShouldAutoSize, WithEvents
{
    protected $items;

    protected $maxSubjectsCount;

    public function __construct(array $items, int $maxSubjectsCount)
    {
        $this->items = $items;
        $this->maxSubjectsCount = $maxSubjectsCount;
    }

    public function array(): array
    {
        return $this->items;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Calculate starting column for subjects (after the basic student info columns)
                // Assuming 7 basic columns: code, roll, name, course, gender, category, address
                $startColumn = 8; // Column H (1-indexed: A=1, B=2... H=8)

                // Merge cells for each subject group
                // $this->maxSubjectsCount is the NUMBER OF SUBJECTS, not total columns
                for ($i = 0; $i < $this->maxSubjectsCount; $i++) {
                    $fromCol = $startColumn + ($i * 4);
                    $toCol = $fromCol + 3;

                    // Convert column numbers to letters
                    $fromLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($fromCol);
                    $toLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($toCol);

                    // Merge the header cells
                    $sheet->mergeCells("{$fromLetter}1:{$toLetter}1");

                    // Center align the merged header
                    $sheet->getStyle("{$fromLetter}1:{$toLetter}1")->getAlignment()->setHorizontal('center');
                }

                // Optional: Style the header row
                $sheet->getStyle('1:1')->getFont()->setBold(true);
            },
        ];
    }
}
