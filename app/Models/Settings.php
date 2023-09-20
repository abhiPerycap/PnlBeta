<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;

use App\Models\Role;
use App\Models\User;

class Settings extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'settings';

    
    public static function getSettingsData()
    {
        if(sizeof(Settings::all())==1){
            $obj = Settings::first()->toArray();
            $data = [
                'cronJobFrequency' => [
                    'frequency' => $obj['cronJobFrequency']['id'],
                    'time' => $obj['cronJobFrequency']['time'],
                ],
                'helpDeskAddress' => $obj['helpDeskAddress'],
                'logoText' => $obj['logoText'],
                'notificationHandlerRole' => Role::find($obj['notificationHandlerRole']['id'])??null,
                'userDefaultRole' => Role::find($obj['userDefaultRole']['id'])??null,
                'timeZone' => $obj['timeZone']['id'],
                'cronJobStatus' => \Carbon\Carbon::createFromTimeStamp($obj['cronJobStatus'])->diffForHumans()??'',
                'cronJobStatusTimeStamp' => $obj['cronJobStatus'],

            ];
            return $data;
        }else
            return null;
    }
}
