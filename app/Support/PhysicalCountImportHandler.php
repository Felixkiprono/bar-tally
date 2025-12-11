<?php

namespace App\Support;

use Maatwebsite\Excel\Facades\Excel;

class PhysicalCountImportHandler
{
    public static function loadRows(string $filePath): array
    {
        $data = Excel::toArray([], $filePath)[0];

        // Normalize header
        $header = array_map(fn($h) => strtolower(trim($h)), $data[0]);
        unset($data[0]);

        $rows = [];

        foreach ($data as $row) {
            $mapped = [];
            foreach ($header as $i => $key) {
                $mapped[$key] = $row[$i] ?? null;
            }
            $rows[] = $mapped;
        }

        return $rows;
    }
}
