<?php

namespace App\Commands;

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
        {--max-items= : How many items a brief may contain}';

    protected $description = 'Configure shiplog and check your API credentials';

    private const array AUDIENCES = [
        'non-technical' => 'Non-technical (a founder, or a client contact)',
        'technical' => 'Technical (a CTO, or an engineering lead)',
    ];

    private const array TONES = [
        'first-person' => 'First person ("We shipped date filtering")',
        'neutral' => 'Neutral ("Date filtering is now available")',
    ];

    public function handle(Settings $settings, Credentials $credentials): int
    {
        $this->newLine();
        $this->reportCredentials($credentials);

        $current = $this->currentValues($settings);

        try {
            $audience = $this->validatedOption('audience', self::AUDIENCES)
                ?? select(
                    label: 'Who reads the brief?',
                    options: self::AUDIENCES,
                    default: $current['audience'],
                );

            $tone = $this->validatedOption('tone', self::TONES)
                ?? select(
                    label: 'How should it be written?',
                    options: self::TONES,
                    default: $current['tone'],
                );

            $maxItems = $this->option('max-items') ?? text(
                label: 'At most how many items in a brief?',
                default: (string) $current['max_items'],
                validate: fn (string $value) => $this->validateMaxItems($value),
            );

            if ($error = $this->validateMaxItems((string) $maxItems)) {
                $this->error($error);

                return self::FAILURE;
            }

            $settings->save([
                'audience' => $audience,
                'tone' => $tone,
                'max_items' => (int) $maxItems,
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
