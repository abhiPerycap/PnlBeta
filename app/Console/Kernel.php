<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Models\Settings;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */

    protected $commands = [
        //
        // Commands\CronStatusUpdater::class,
        Commands\GenerateReport::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        try{
            $settingsObj = Settings::first();
            $cronFrequency = $settingsObj['cronJobFrequency'];
            $type = $cronFrequency['id'];
            $cronTime = $cronFrequency['time'];
            $timezone = $settingsObj['timeZone']['id'];//'UTC';
            $hourlyAtMin = $cronTime;
            $dailyAtHour = $cronTime;
            $twiceDaily = explode('-',str_replace(' ', '', $cronTime));


            switch ($type) {
                case 'hourly':
                    $schedule->command('report:daily')
                            ->hourly()
                            ->timezone($timezone);
                    break;
                
                case 'hourlyAt':
                    $schedule->command('report:daily')
                            ->hourlyAt($hourlyAtMin)
                            ->timezone($timezone);
                    break;
                
                case 'everyTwoHours':
                    $schedule->command('report:daily')
                            ->everyTwoHours()
                            ->timezone($timezone);
                    break;
                
                case 'everyThreeHours':
                    $schedule->command('report:daily')
                            ->everyThreeHours()
                            ->timezone($timezone);
                    break;
                
                case 'everyFourHours':
                    $schedule->command('report:daily')
                            ->everyFourHours()
                            ->timezone($timezone);
                    break;
                
                case 'everySixHours':
                    $schedule->command('report:daily')
                            ->everySixHours()
                            ->timezone($timezone);
                    break;
                
                case 'daily':   // Run the task every day at midnight
                    $schedule->command('report:daily')
                            ->daily() 
                            ->timezone($timezone);
                    break;
                
                case 'dailyAt':
                    $schedule->command('report:daily')
                            ->dailyAt($dailyAtHour)
                            ->timezone($timezone);
                    break;

                case 'twiceDaily':
                    $schedule->command('report:daily')
                            ->twiceDaily($twiceDaily[0], $twiceDaily[1])
                            ->timezone($timezone);
                    break;
                
                default:
                    $schedule->command('report:daily')
                            ->dailyAt('06:30')
                            ->timezone($timezone);
                    break;
            }
            
            // $schedule->command('report:recheck')->dailyAt('08:30');
            $schedule->command('cron:status')->everyMinute();  
        }catch(\Exception $e){
            echo 'Some ERROR Occurred';
        }
        
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
