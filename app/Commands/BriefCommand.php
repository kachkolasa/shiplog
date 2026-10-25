<?php

namespace App\Commands;

use App\Services\GitLog;
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
    public function handle(GitLog $git): int
    {
        $repo = getcwd();
        $start = Carbon::parse($this->option('since'));
        $end = Carbon::parse($this->option('until'));

        if ($end->isStartOfDay()) {
            $end = $end->endOfDay();
        }

        $git->ensureRepo($repo);
        $author = $this->option('all-authors') ? null : $git->currentEmail($repo);
        $commits = $git->commits($repo, $start, $end, $author);

        if (empty($commits)) {
            $this->warn("No commits between {$start->toFormattedDateString()} and {$end->toFormattedDateString()}.");
            return self::SUCCESS;
        }

        render(json_encode($commits));

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
