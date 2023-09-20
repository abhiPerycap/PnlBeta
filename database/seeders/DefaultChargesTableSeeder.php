<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use DB;
class DefaultChargesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('defaultCharges')->insert([
            "preferenceCols" => [
                "comm" => 1,
                "ecn_fee" => 1,
                "sec" => 1,
                "orf" => 1,
                "taf" => 1,
                "quotes" => 1,
                "nscc" => 1,
                "acc" => 1,
                "clr" => 1,
                "misc" => 1
            ],
            "diYear" => 365,
            "dailyInterest" => 1,            
        ]);
    }
}
