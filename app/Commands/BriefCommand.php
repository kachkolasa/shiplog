<?php

namespace App\Commands;

use Anthropic\Core\Exceptions\AnthropicException;
use App\Contracts\BriefWriter;
use App\Data\BriefOptions;
use App\Services\BriefRenderer;
use App\Services\CommitDigest;
use App\Services\Credentials;
use App\Services\GitLog;
use App\Services\Settings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;

use function Termwind\render;

class BriefCommand extends Command
{
    protected $signature = 'brief
        {--since=7 days ago : Start of the range}
        {--until=now : End of the range}
        {--all-authors : Include everyone, not just you}
        {--deep : Send the code diffs too, so claims can be checked against what changed}
        {--surface : Only include the summary, not the code diffs}
        {--audience= : Who reads the brief: technical or non-technical}
        {--tone= : How it is written: first-person or neutral}
        {--max-items= : How many items a brief may contain}';

    protected $description = 'Summarise recent commits as a client-ready update';

    public function handle(
        GitLog $git,
        CommitDigest $digest,
        BriefWriter $writer,
        BriefRenderer $renderer,
        Credentials $credentials,
        Settings $settings
    ): int {
        if (! $credentials->configured()) {
            $this->error('Anthropic credentials not found. Please run shiplog setup command.');

            return self::FAILURE;
        }

        $repo = getcwd() ?: '.';
        $start = Carbon::parse($this->option('since'));
        $end = Carbon::parse($this->option('until'));

        if ($end->isStartOfDay()) {
            $end = $end->endOfDay();
        }

        try {
            $options = BriefOptions::fromSettings($settings)
                ->with(
                    audience: $this->option('audience'),
                    tone: $this->option('tone'),
                    maxItems: $this->option('max-items'),
                    deep: $this->option('deep') ? true : ($this->option('surface') ? false : null),
                );
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (\TypeError $e) {
            $this->error('Invalid option type');

            return self::FAILURE;
        }

        if (! $settings->exists()) {
            $this->error('Setup not complete. Please run `shiplog setup` to configure your settings.');

            return self::FAILURE;
        }

        try {
            $git->ensureRepo($repo);
            $author = $this->option('all-authors') ? null : $git->currentEmail($repo);
            $commits = $git->commits($repo, $start, $end, $author);
            $patches = $options->deep ? $git->patches($repo, $start, $end, $author) : [];
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($commits === []) {
            $this->warn("No commits between {$start->toFormattedDateString()} and {$end->toFormattedDateString()}.");

            return self::SUCCESS;
        }

        try {
            $brief = $writer->write($digest->build($commits, $patches), $options);
        } catch (AnthropicException|RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        render($renderer->render($brief, $start, $end));

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
