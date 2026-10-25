<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use LaravelZero\Framework\Commands\Command;

use function Termwind\render;

class BriefCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'brief
        {--since=7 days ago : Start of the range}
        {--until=now : End of the range}
        {--all-authors : Include everyone, not just you}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $repo = getcwd();
        $start = Carbon::parse($this->option('since'));
        $end = Carbon::parse($this->option('until'));

        dd($start, $end, $repo);
    }

    /**
     * Define the command's schedule.
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
