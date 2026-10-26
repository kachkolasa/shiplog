<?php

namespace App\Commands;

use App\Contracts\BriefWriter;
use App\Services\BriefRenderer;
use App\Services\CommitDigest;
use App\Services\GitLog;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;

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
    protected $description = 'Summarise recent commits as a client-ready update';

    /**
     * Execute the console command.
     */
    public function handle(
        GitLog $git,
        CommitDigest $digest,
        BriefWriter $writer,
        BriefRenderer $renderer,
    ): int {
        $repo = getcwd() ?: '.';
        $start = Carbon::parse($this->option('since'));
        $end = Carbon::parse($this->option('until'));

        if ($end->isStartOfDay()) {
            $end = $end->endOfDay();
        }

        try {
            $git->ensureRepo($repo);
            $author = $this->option('all-authors') ? null : $git->currentEmail($repo);
            $commits = $git->commits($repo, $start, $end, $author);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($commits === []) {
            $this->warn("No commits between {$start->toFormattedDateString()} and {$end->toFormattedDateString()}.");

            return self::SUCCESS;
        }


        render($renderer->render($writer->write($digest->build($commits)), $start, $end));

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
