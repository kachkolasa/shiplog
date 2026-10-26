<?php

namespace App\Commands;

use App\Services\Settings;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class PurgeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'setup:purge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear setup data';

    /**
     * Execute the console command.
     */
    public function handle(
        Settings $settings
    )
    {
        $this->info('Purging setup data...');

        $path = $settings->path();

        if (is_file($path)) {
            $settings->delete();
            $this->info("Deleted config file: {$path}");
        } else {
            $this->info("Config file not found: {$path}");
        }

        $this->info('Setup data purged successfully.');
    }

    /**
     * Define the command's schedule.
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
