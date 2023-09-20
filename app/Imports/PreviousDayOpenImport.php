<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToModel;
use App\Models\User;
use Carbon;

class PreviousDayOpenImport implements ToModel, WithHeadingRow
{
    // use Importable;
    // public function array($file): array
    // {
    //     return [
    //         'accountid' => $file['accountid'],
    //         'symbol' => $file['symbol'],
    //         'date' => $file['date(yyyy-mm-dd)'],
    //         'qty' => $file['qty'],
    //         'price' => $file['price'],
    //         'closeprice' => $file['closeprice'],
    //         'unrealizeddelta' => $file['unrealizeddelta']
    //     ];
    // }

    public function model(array $row)
    {
        return new PreviousDayOpen([
           // 'accountid' => $row['accountid'],
            // 'symbol' => $row['symbol'],
            // 'date' => Carbon::parse($row['date(yyyy-mm-dd)'])->toDateString(),
            // 'qty' => $row['qty'],
            // 'price' => $row['price'],
            // 'closeprice' => $row['closeprice'],
            // 'unrealizeddelta' => $row['unrealizeddelta']
        ]);
    }

    public function headingRow(): int
    {
        return 1;
    }
}
