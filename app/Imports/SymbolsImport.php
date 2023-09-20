<?php

namespace App\Imports;

use App\Models\Symbol;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithUpserts;
use Maatwebsite\Excel\Concerns\WithUpsertColumns;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;



class SymbolsImport implements ToCollection, WithHeadingRow, WithUpserts, WithUpsertColumns
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

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) 
        {
            $symbolName = $row['Symbol']??$row['symbol']??null;
            $symbolFullName = $row['Name']??$row['name']??null;
            $exchange = $row['Exchange']??$row['exchange']??null;

            if($symbol = Symbol::where('name', $symbolName)->first()){
                if($symbolFullName!=null && $symbolFullName!=$symbol['fullName']){
                    $symbol['fullName'] = $symbolFullName;
                    $symbol['exchange'] = $exchange;
                    $symbol['status'] = 'approved';
                    $symbol['verifiedBy'] = $this->user_id;
                    $symbol['updated_at'] = Carbon::now();
                    $symbol->update();
                }
            }else{
                $symbol = new Symbol();
                $symbol['name'] = $symbolName;
                $symbol['exchange'] = $exchange;
                $symbol['fullName'] = $symbolFullName; 
                $symbol['status'] = 'approved';
                $symbol['user_id'] = $this->user_id;
                $symbol['verifiedBy'] = $this->user_id;
                $symbol['created_at'] = Carbon::now();
                $symbol['updated_at'] = Carbon::now();
                $symbol->save();
            }

        }
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
        return ['fullName', 'exchange', 'status', 'user_id', 'verifiedBy', 'updated_at'];
    }

}
