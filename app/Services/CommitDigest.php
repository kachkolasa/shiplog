<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class CommitDigest
{
    private const int MAX_AREAS = 4;

    private const int MAX_SUBJECT = 120;

    private const int PATCH_BUDGET = 120000;

    public function build(array $commits, array $patches = [], ?int $budget = null): string
    {
        $ordered = collect($commits)
            ->sortBy(fn (array $commit) => Carbon::parse($commit['date'])->getTimestamp())
            ->values();

        $deep = $patches !== [];
        $included = $this->withinBudget($ordered, $patches, $budget ?? self::PATCH_BUDGET);

        return $ordered
            ->map(fn (array $commit) => $this->entry(
                $commit,
                $included[$commit['hash']] ?? null,
                $deep,
            ))
            ->implode("\n");
    }

    private function withinBudget(Collection $ordered, array $patches, int $budget): array
    {
        if ($patches === []) {
            return [];
        }

        $included = [];
        $spent = 0;

        foreach ($ordered->reverse() as $commit) {
            $patch = $patches[$commit['hash']] ?? '';

            if ($patch === '' || $spent + strlen($patch) > $budget) {
                continue;
            }

            $included[$commit['hash']] = $patch;
            $spent += strlen($patch);
        }

        return $included;
    }

    private function entry(array $commit, ?string $patch, bool $deep): string
    {
        $line = $this->line($commit);

        if (! $deep) {
            return $line;
        }

        if ($patch === null) {
            return $line."\n  (no diff included for this commit)";
        }

        return $line."\n```diff\n".$patch."\n```";
    }

    private function line(array $commit): string
    {
        $date = Carbon::parse($commit['date'])->toDateString();
        $subject = $this->subject($commit['subject']);

        if ($commit['files'] === []) {
            return sprintf('- %s | %s | no file changes', $date, $subject);
        }

        $files = $this->relevantFiles($commit['files']);

        if ($files->isEmpty()) {
            return sprintf('- %s | %s | dependencies', $date, $subject);
        }

        return sprintf(
            '- %s | %s | %s | ~%d lines',
            $date,
            $subject,
            $this->areas($files),
            $files->sum(fn (array $file) => $file['added'] + $file['deleted']),
        );
    }

    private function relevantFiles(array $files): Collection
    {
        return collect($files)
            ->reject(fn (array $file) => IgnoredPaths::matches($file['file']))
            ->values();
    }

    private function areas(Collection $files): string
    {
        $areas = $files
            ->map(fn (array $file) => dirname($file['file']))
            ->map(fn (string $directory) => $directory === '.' ? 'root' : $directory)
            ->unique()
            ->sort()
            ->values();

        if ($areas->count() <= self::MAX_AREAS) {
            return $areas->implode(', ');
        }

        return $areas->take(self::MAX_AREAS)->implode(', ')
            .sprintf(' +%d more', $areas->count() - self::MAX_AREAS);
    }

    private function subject(string $subject): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $subject) ?? '');

        return mb_strlen($clean) > self::MAX_SUBJECT
            ? mb_substr($clean, 0, self::MAX_SUBJECT - 1).'…'
            : $clean;
    }
}
