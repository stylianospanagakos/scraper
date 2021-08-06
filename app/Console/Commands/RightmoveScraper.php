<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RightmoveScraper extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rightmove:crawl';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crawl rightmove.co.uk to fetch property data for the last 10 years';

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
        return 0;
    }
}
