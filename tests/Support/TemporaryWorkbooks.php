<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Writes small workbooks to temporary files, so tests do not depend on committed student data.
 */
final class TemporaryWorkbooks
{
    /** @var list<string> */
    private array $paths = [];

    /**
     * @param array<int, array<int, mixed>> $rows row 1 first; null leaves a cell empty
     */
    public function create(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                if (null !== $value) {
                    $sheet->setCellValue([$columnIndex + 1, $rowIndex + 1], $value);
                }
            }
        }

        $path = $this->newPath('.xlsx');
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public function createTextFile(string $contents, string $extension): string
    {
        $path = $this->newPath($extension);
        file_put_contents($path, $contents);

        return $path;
    }

    public function removeAll(): void
    {
        foreach ($this->paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->paths = [];
    }

    private function newPath(string $extension): string
    {
        $path = sys_get_temp_dir().'/'.uniqid('exam-test-', true).$extension;
        $this->paths[] = $path;

        return $path;
    }
}
