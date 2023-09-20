<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Models\Settings;
use Carbon\Carbon;

class CronStatusUpdater extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates The Database with CronJob Running Status';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // //
        // $words = [
        //     'aberration' => 'a state or condition markedly different from the norm',
        //     'convivial' => 'occupied with or fond of the pleasures of good company',
        //     'diaphanous' => 'so thin as to transmit light',
        //     'elegy' => 'a mournful poem; a lament for the dead',
        //     'ostensible' => 'appearing as such but not necessarily so'
        // ];
         
        // // Finding a random word
        // $key = array_rand($words);
        // $value = $words[$key];
         
        // // $users = User::all();
        // // foreach ($users as $user) {
        //     $user = "abhishek.mywork@gmail.com";
        //     Mail::raw("{$key} -> {$value}", function ($mail) use ($user) {
        //         $mail->from('info@tutsforweb.com');
        //         $mail->to($user)
        //             ->subject('Word of the Day');
        //     });
        // // }
        // //  cd /home/traderspnl/public_html && php artisan report:daily >> /dev/null 2>&1
        // $this->info('Word of the Day sent to All Users');

        $obj = Settings::first();
        $obj['cronJobStatus'] = Carbon::now()->timestamp;
        return $obj->update();
    }
}
