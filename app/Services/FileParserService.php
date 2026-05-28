<?php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;

class FileParserService
{
    public function parse(UploadedFile $file): array
    {
        $sheets = Excel::toArray(new class implements ToArray {
            public function array(array $array): array
            {
                return $array;
            }
        }, $file);

        if (empty($sheets[0])) {
            return [];
        }

        $rows = $sheets[0];
        $headers = array_map('trim', (array) $rows[0]);
        $result = [];

        foreach (array_slice($rows, 1) as $row) {
            if (empty(array_filter((array) $row, fn($v) => $v !== null && $v !== ''))) {
                continue;
            }

            $record = [];
            foreach ($headers as $i => $header) {
                $record[$header] = $row[$i] ?? null;
            }
            $result[] = $record;
        }

        return $result;
    }

    public function detectColumns(UploadedFile $file): array
    {
        $sheets = Excel::toArray(new class implements ToArray {
            public function array(array $array): array
            {
                return $array;
            }
        }, $file);

        if (empty($sheets[0][0])) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', (array) $sheets[0][0]),
            fn($h) => $h !== null && $h !== ''
        ));
    }
}
