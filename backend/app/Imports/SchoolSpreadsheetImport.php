<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

class SchoolSpreadsheetImport implements ToArray
{
    public function array(array $array): void
    {
        // Excel::toArray aggregates sheet rows; body unused.
    }
}
