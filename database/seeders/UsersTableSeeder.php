<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use DB;
class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $role = DB::table('roles')->insertGetId([
          'name' => 'SysAdmin',
          'authorised' => true,
          'permission' => [
            'manualExecution' => [
              'authorised' => true
            ],
            'defaultCharges' => [
              'authorised' => true,
              'permission' => [
                'canModify' => true,
                'canView' => true
              ]
            ],
            'importExcelData' => [
              'authorised' => true
            ],
            'importServerData' => [
              'authorised' => true
            ],
            'resetData' => [
              'authorised' => true,
              'permission' => [
                'canHardReset' => true,
                'canReCompile' => true
              ]
            ],
            'importPDO' => [
              'authorised' => true
            ],
            'settings' => [
              'authorised' => true,
              'permission' => [
                'canModify' => true,
                'canView' => true
              ]
            ],
            'reportGroup' => [
              'authorised' => true,
              'permission' => [
                'canModify' => true,
                'canDelete' => true,
                'canAdd' => true,
                'canView' => true
              ]
            ],
            'reports' => [
              'authorised'=> true,
              'permission'=> [
                'canView'=> true
              ],
              'canViewDetailed'=> true,
              'canViewOpen'=> true,
              'canViewAdjustment'=> true,
              'canViewSBD'=> true,
              'canViewSBM'=> true,
              'canViewTBD'=> true,
              'canSeeAnyAccountReport'=> true,
              'groupReport'=> true,
              'canViewGSBD'=> true,
              'canViewTBA'=> true,
              'canViewTBS'=> true,
              'canViewTBU'=> true
            ],  
            'preference' => [
              'authorised' => true,
              'permission' => [
                'canAdd' => true,
                'canModify' => true,
                'canDelete' => true,
                'canView' => true
              ]
            ],
            'dailyInterest' => [
              'authorised' => true,
              'permission' => [
                'canAdd' => true,
                'canModify' => true,
                'canDelete' => true,
                'canView' => true
              ]
            ],
            'adjustment' => [
              'authorised' => true,
              'permission' => [
                'canAdd' => true,
                'canModify' => true,
                'canDelete' => true,
                'canView' => true
              ]
            ],
            'manageSymbol' => [
              'authorised' => true,
              'permission' => [
                'canDelete' => true,
                'canVerify' => true,
                'canUploadSymbol' => true,
                'canView' => true
              ]
            ],
            'role' => [
              'authorised' => true,
              'permission' => [
                'canAdd' => true,
                'canModify' => true,
                'canDelete' => true,
                'canView' => true
              ]
            ],
            'user' => [
              'authorised' => true,
              'permission' => [
                'canAdd' => true,
                'canModify' => true,
                'canDelete' => true,
                'canView' => true
              ]
            ],
            'accountMapping' => [
              'authorised' => true,
              'permission' => [
                'canAdd' => true,
                'canView' => true
              ]
            ],
            'accountListing' => [
              'authorised' => true,
              'permission' => [
                'canAdd' => true,
                'canModify' => true,
                'canDelete' => true,
                'canView' => true
              ]
            ],
            'broker' => [
              'authorised' => true,
              'permission' => [
                'canAdd' => true,
                'canModify' => true,
                'canDelete' => true,
                'canView' => true
              ]
            ],
            'inputTradeData' => [
              'authorised' => true,
              'permission' => [
                'canInputDate' => true,
                'canAdd' => true,
                'canRequestSymbol' => true
              ]
            ],
            'userPermission' => [
              'authorised' => true,
              'permission' => [
                'canView' => true,
                'canDelete' => true,
                'canModify' => true
              ]
            ]
          ]
        ]);

        $tradersDefaultRole = DB::table('roles')->insertGetId([
          'name' => 'Traders',
          'authorised' => true,
          'permission' => [
            'manualExecution' => [
              'authorised' => false
            ],
            'defaultCharges' => [
              'authorised' => false,
              'permission' => [
                'canModify' => false,
                'canView' => false
              ]
            ],
            'importExcelData' => [
              'authorised' => false
            ],
            'importServerData' => [
              'authorised' => false
            ],
            'resetData' => [
              'authorised' => false,
              'permission' => [
                'canHardReset' => false,
                'canReCompile' => false
              ]
            ],
            'importPDO' => [
              'authorised' => false
            ],
            'settings' => [
              'authorised' => false,
              'permission' => [
                'canModify' => false,
                'canView' => false
              ]
            ],
            'reportGroup' => [
              'authorised' => false,
              'permission' => [
                'canModify' => false,
                'canDelete' => false,
                'canAdd' => false,
                'canView' => false
              ]
            ],
            'reports' => [
              'authorised'=> true,
              'permission'=> [
                'canView'=> true
              ],
              'canViewDetailed'=> true,
              'canViewOpen'=> true,
              'canViewAdjustment'=> true,
              'canViewSBD'=> true,
              'canViewSBM'=> true,
              'canViewTBD'=> true,
              'canSeeAnyAccountReport'=> true,
              'groupReport'=> true,
              'canViewGSBD'=> true,
              'canViewTBA'=> true,
              'canViewTBS'=> true,
              'canViewTBU'=> true
            ],
            'preference' => [
              'authorised' => false,
              'permission' => [
                'canAdd' => false,
                'canModify' => false,
                'canDelete' => false,
                'canView' => false
              ]
            ],
            'dailyInterest' => [
              'authorised' => false,
              'permission' => [
                'canAdd' => false,
                'canModify' => false,
                'canDelete' => false,
                'canView' => false
              ]
            ],
            'adjustment' => [
              'authorised' => false,
              'permission' => [
                'canAdd' => false,
                'canModify' => false,
                'canDelete' => false,
                'canView' => false
              ]
            ],
            'manageSymbol' => [
              'authorised' => false,
              'permission' => [
                'canDelete' => false,
                'canVerify' => false,
                'canUploadSymbol' => false,
                'canView' => false
              ]
            ],
            'role' => [
              'authorised' => false,
              'permission' => [
                'canAdd' => false,
                'canModify' => false,
                'canDelete' => false,
                'canView' => false
              ]
            ],
            'user' => [
              'authorised' => false,
              'permission' => [
                'canAdd' => false,
                'canModify' => false,
                'canDelete' => false,
                'canView' => false
              ]
            ],
            'accountMapping' => [
              'authorised' => false,
              'permission' => [
                'canAdd' => false,
                'canView' => false
              ]
            ],
            'accountListing' => [
              'authorised' => false,
              'permission' => [
                'canAdd' => false,
                'canModify' => false,
                'canDelete' => false,
                'canView' => false
              ]
            ],
            'broker' => [
              'authorised' => false,
              'permission' => [
                'canAdd' => false,
                'canModify' => false,
                'canDelete' => false,
                'canView' => false
              ]
            ],
            'inputTradeData' => [
              'authorised' => true,
              'permission' => [
                'canInputDate' => false,
                'canAdd' => true,
                'canRequestSymbol' => true
              ]
            ],
            'userPermission' => [
              'authorised' => false,
              'permission' => [
                'canView' => false,
                'canDelete' => false,
                'canModify' => false
              ]
            ]
          ],
          'user_ids' => []
        ]);

        $roleId = (array) $role;
        $roleId = $roleId['oid'];

        $tradersDefaultRoleId = (array) $tradersDefaultRole;
        $tradersDefaultRoleId = $tradersDefaultRoleId['oid'];

        $user = (array) DB::table('users')->insertGetId([
            'name' => 'Administrator',
            'memberId' => 'Administrator',
            'type' => 'sysadmin',
            'email' => 'admin@perycap.com',
            'emailverified' => true,
            'authorised' => false,
            'password' => Hash::make('secret'),
            'helpdeskpass' => 'secret',
            'islocal' => true,
            'role_ids' => [
              $roleId,
            ]
        ]);

        DB::table('roles')->where('_id', $role)->push('user_ids', [$user['oid']], true);

        DB::table('settings')->insert([
            "cronJobFrequency" => [
                "time" => "05:30",
                "id" => "dailyAt",
                "text" => "Run the task every day at [ given ] hours"
            ],
            "helpDeskAddress" => null,
            "logoText" => 'PNL',
            "notificationHandlerRole" => [
                "id" => $roleId,
                "name" => "SysAdmin"
            ],
            "userDefaultRole" => [
                "id" => $tradersDefaultRoleId,
                "name" => "Traders"
            ],
            "timeZone" => [
                "id" => "America/Los_Angeles",
                "text" => "(UTC-08:00) Pacific Time (US &amp; Canada)"
            ]
        ]);
    }
}
