<?php

namespace App\Support;

use Maatwebsite\Excel\Facades\Excel;

class SalesImportHandler
{
    public static function loadRows($filePath): array
    {
        $data = Excel::toArray([], $filePath)[0];

        $header = array_map(fn($h) => strtolower(trim($h)), $data[0]);
        unset($data[0]);

        return array_map(
            fn($row) => array_combine($header, $row),
            $data
        );
    }
}
