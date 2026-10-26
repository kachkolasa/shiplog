<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class CommitDigest
{
    private const array IGNORED_FILES = [
        'composer.lock',
        'package-lock.json',
        'yarn.lock',
        'pnpm-lock.yaml',
    ];

    private const array IGNORED_PREFIXES = [
        'vendor/',
        'node_modules/',
    ];

    private const int MAX_AREAS = 4;

    private const int MAX_SUBJECT = 120;

    public function build(array $commits): string
    {
        return collect($commits)
            ->sortBy(fn (array $commit) => Carbon::parse($commit['date'])->getTimestamp())
            ->map(fn (array $commit) => $this->line($commit))
            ->implode("\n");
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
            ->reject(fn (array $file) => in_array(basename($file['file']), self::IGNORED_FILES, true))
            ->reject(fn (array $file) => Str::startsWith($file['file'], self::IGNORED_PREFIXES))
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
