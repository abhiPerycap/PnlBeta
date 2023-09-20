<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'report:daily {daterange=null}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compile Day to Day Account wise Report from Api Call';

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
     * @return int
     */
    public function handle()
    {
        // return Command::SUCCESS;
        // echo 'hehe';
        // return 'LUKI LUKI';
        $reportGenerator = new \App\Http\Controllers\CornJobManagerController();

        if($this->argument('daterange')!='null'){
            echo '1';
            return $reportGenerator->generateReport($this->argument('daterange'));
        }else{
            echo '2';
            return $reportGenerator->generateReport(null);
        }
    }
}
