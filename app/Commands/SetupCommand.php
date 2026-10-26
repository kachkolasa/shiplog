<?php

namespace App\Commands;

use App\Data\BriefOptions;
use App\Services\Credentials;
use App\Services\Settings;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class SetupCommand extends Command
{
    protected $signature = 'setup
        {--audience= : Who reads the brief: technical or non-technical}
        {--tone= : How it is written: first-person or neutral}
        {--max-items= : How many items a brief may contain}
        {--deep= : Include code diffs in the brief}';

    protected $description = 'Configure shiplog and check your API credentials';

    public function handle(Settings $settings, Credentials $credentials): int
    {
        $this->newLine();
        $this->reportCredentials($credentials);

        $current = $this->currentValues($settings);

        try {
            $audience = $this->validatedOption('audience', BriefOptions::AUDIENCES)
                ?? select(
                    label: 'Who reads the brief?',
                    options: BriefOptions::AUDIENCES,
                    default: $current['audience'],
                );

            $tone = $this->validatedOption('tone', BriefOptions::TONES)
                ?? select(
                    label: 'How should it be written?',
                    options: BriefOptions::TONES,
                    default: $current['tone'],
                );

            $maxItems = $this->option('max-items') ?? text(
                label: 'At most how many items in a brief?',
                default: (string) $current['max_items'],
                validate: fn (string $value) => $this->validateMaxItems($value),
            );

            $deep = $this->validatedBoolean('deep');

            if ($deep === null) {
                $deep = select(
                    label: 'Include code diffs in the brief?',
                    options: [
                        'true' => 'Yes, send the diffs so claims can be checked (default, costs more)',
                        'false' => 'No, summary only (cheaper, claims cannot be checked)',
                    ],
                    default: $current['deep'] ? 'true' : 'false',
                ) === 'true';
            }

            if ($error = $this->validateMaxItems((string) $maxItems)) {
                $this->error($error);

                return self::FAILURE;
            }

            $settings->save([
                'audience' => $audience,
                'tone' => $tone,
                'max_items' => (int) $maxItems,
                'deep' => $deep,
            ]);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Saved to {$settings->path()}");
        $this->line('  Run <fg=cyan>shiplog brief</> in any git repository.');
        $this->newLine();

        return self::SUCCESS;
    }

    private function reportCredentials(Credentials $credentials): void
    {
        if ($source = $credentials->source()) {
            $this->line("  Credentials: <fg=green>found</> ({$source})");
            $this->newLine();

            return;
        }

        $this->line('  Credentials: <fg=yellow>not found</>');
        $this->newLine();
        $this->line('  Briefs need an Anthropic credential. Set one up either way:');
        $this->newLine();
        $this->line('    <fg=cyan>ant auth login</>');
        $this->line('      stores a profile the SDK reads automatically');
        $this->newLine();
        $this->line('    <fg=cyan>export ANTHROPIC_API_KEY=sk-ant-...</>');
        $this->line('      add it to your shell profile to keep it between sessions');
        $this->newLine();
        $this->line('  <fg=gray>shiplog never stores your key; it reads whichever of those you set.</>');
        $this->newLine();
    }

    private function currentValues(Settings $settings): array
    {
        try {
            return $settings->all();
        } catch (RuntimeException $e) {
            $this->warn("  {$e->getMessage()}");
            $this->newLine();

            return Settings::DEFAULTS;
        }
    }

    private function validatedOption(string $option, array $allowed): ?string
    {
        $value = $this->option($option);

        if ($value === null) {
            return null;
        }

        if (! array_key_exists($value, $allowed)) {
            throw new RuntimeException(sprintf(
                '--%s must be one of: %s.',
                $option,
                implode(', ', array_keys($allowed)),
            ));
        }

        return $value;
    }

    private function validatedBoolean(string $option): ?bool
    {
        $value = $this->option($option);

        if ($value === null) {
            return null;
        }

        if (! in_array($value, ['true', 'false'], true)) {
            throw new RuntimeException(sprintf(
                '--%s must be either "true" or "false".',
                $option,
            ));
        }

        return $value === 'true';
    }

    private function validateMaxItems(string $value): ?string
    {
        return match (true) {
            ! ctype_digit($value) => 'Enter a whole number.',
            (int) $value < 1 => 'A brief needs at least one item.',
            (int) $value > 20 => 'Keep it to 20 or fewer.',
            default => null,
        };
    }

    /**
     * Define the command's schedule.
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
