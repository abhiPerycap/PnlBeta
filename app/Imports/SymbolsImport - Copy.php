<?php

namespace App\Imports;

use App\Models\Symbol;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithUpserts;
use Maatwebsite\Excel\Concerns\WithUpsertColumns;

class SymbolsImport implements ToModel, WithHeadingRow, WithUpserts, WithUpsertColumns
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public $user_id = '';

    public function __construct($user_id) {
        $this->user_id = $user_id;
    }

    public function model(array $row)
    {
        $symbol = new Symbol();

        $symbol['name'] = $row['Symbol']??$row['symbol']??null;
        $symbol['fullName'] = $row['Name']??$row['name']??null; 
        $symbol['status'] = 'approved';
        $symbol['user_id'] = $this->user_id;
        $symbol['verifiedBy'] = $this->user_id;
        $symbol['created_at'] = Carbon::now();
        $symbol['updated_at'] = Carbon::now();
        
        return $symbol;
    }

    public function headingRow(): int
    {
        return 1;
    }

    /**
     * @return string|array
     */
    public function uniqueBy()
    {
        return 'name';
    }

    /**
     * @return array
     */
    public function upsertColumns()
    {
        return ['fullName', 'status', 'user_id', 'verifiedBy', 'updated_at'];
    }
}
