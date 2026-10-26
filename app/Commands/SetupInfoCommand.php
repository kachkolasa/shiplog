<?php

namespace App\Commands;

use App\Data\BriefOptions;
use App\Services\Settings;
use Illuminate\Console\Scheduling\Schedule;
use InvalidArgumentException;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;
use TypeError;

class SetupInfoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'setup:info';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Information on your current setup configuration';

    /**
     * Execute the console command.
     */
    public function handle(Settings $settings): int
    {
        if (! $settings->exists()) {
            $this->warn('No setup configuration found. Please run the setup command.');

            return self::SUCCESS;
        }

        try {
            $options = BriefOptions::fromSettings($settings);
        } catch (RuntimeException|InvalidArgumentException|TypeError $e) {
            $this->error($e->getMessage());
            $this->line("Config file: {$settings->path()}");

            return self::FAILURE;
        }

        $this->info('Current setup configuration:');
        $this->line('------------------------------');
        $this->line("Config file: {$settings->path()}");
        $this->line("Audience: {$options->audience}");
        $this->line("Tone: {$options->tone}");
        $this->line("Max items: {$options->maxItems}");
        $this->line('Include code diffs (deep): '.($options->deep ? 'Yes' : 'No'));

        return self::SUCCESS;
    }

    /**
     * Define the command's schedule.
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
