<?php

namespace App\Imports;

use App\CsvDatas;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;

class CsvDatasImport implements ToModel, WithStartRow
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        //自作アンケート
        $format_date = date("Y/m/d", ($row[0] + $row[1] - 25569) * 60 * 60 * 24);
        $format_time = date("H:i", ($row[0] + $row[1]) * 60 * 60 * 24);
        return new CsvDatas([
            'date' => $format_date,
            'time' => $format_time,
            'location' => $row[2]
        ]);

        //TKTKアンケート
        // return new CsvDatas([
        //     'date' => $row[0],
        //     'time' => $row[1],
        //     'location' => $row[2]
        // ]);
    }

    public function startRow(): int
    {
        return 2;
        // return 10;
    }

    // public function headingRow(): int
    // {
    //     return 11;
    // }
}
