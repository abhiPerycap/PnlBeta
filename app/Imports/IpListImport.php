<?php

namespace App\Imports;

use App\Models\IpList;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithUpserts;
use Maatwebsite\Excel\Concerns\WithUpsertColumns;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;



class IpListImport implements ToCollection, WithHeadingRow, WithUpserts, WithUpsertColumns
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
            $symbolName = $row['ip']??$row['ip address']??$row['address']??$row['ipAddress']??$row['Ip address']??$row['Ip Address']??null;
            $branch = $row['branch']??$row['Branch']??$row['BRANCH']??null;
            $symbolFullName = $row['comments']??$row['Comments']??$row['comment']??$row['Comment']??null;

            if($symbol = IpList::where('ipAddress', $symbolName)->first()){
                // if($symbolFullName!=null && $symbolFullName!=$symbol['fullName']){
                //     $symbol['fullName'] = $symbolFullName;
                //     $symbol['status'] = 'approved';
                //     $symbol['verifiedBy'] = $this->user_id;
                //     $symbol['updated_at'] = Carbon::now();
                //     $symbol->update();
                // }
            }else{
                $symbol = new IpList();
                $symbol['ipAddress'] = $symbolName;
                $symbol['branch'] = $branch; 
                $symbol['comments'] = $symbolFullName; 
                $symbol['authorised'] = true;
                $symbol['createdBy'] = $this->user_id;
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
        return 'ipAddress';
    }


    /**
     * @return array
     */
    public function upsertColumns()
    {
        return ['comments', 'authorised', 'createdBy', 'updated_at'];
    }

}
