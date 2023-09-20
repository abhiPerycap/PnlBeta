<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use DB;

class RolesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // DB::table('roles')->insert([
        //     'name' => 'Traders',
        //     'active' => 1,
        //     'permission' => [
        //       'option1' => [
        //         'title' => 'User Operations',
        //         'permission' => 'Manual Trade',
        //         'authorised' => true,
        //         'permission' => [
        //           'canInputDate' => true,
        //           'canSeeAccountId' => true,
        //         ],
        //       ],
        //     ],
        // ]);
    }
}
