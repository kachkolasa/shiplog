<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Process;

class GitLog
{
    private const string RECORD = "\x1e";

    private const string FIELD = "\x1f";

    public function ensureRepo(string $path): void
    {
        $results = Process::path($path)->run(['git', 'rev-parse', '--is-inside-work-tree']);

        if ($results->failed()) {
            throw new \RuntimeException('This folder is not a git repository.');
        }
    }

    public function currentEmail(string $path): ?string
    {
        $email = trim(Process::path($path)->run(['git', 'config', 'user.email'])->output());

        return $email !== '' ? $email : null;
    }

    public function commits(string $path, Carbon $since, Carbon $until, ?string $author): array
    {
        $command = [
            'git', 'log',
            '--branches',
            '--no-merges',
            '--since='.$since->toIso8601String(),
            '--until='.$until->toIso8601String(),
            '--date=iso-strict',
            '--pretty=format:%x1e%H%x1f%ad%x1f%s%x1f%b%x1f',
            '--numstat',
        ];

        if ($author) {
            $command[] = '--author='.$author;
        }

        $result = Process::path($path)->run($command);

        if ($result->failed()) {
            throw new \RuntimeException($result->errorOutput());
        }

        return collect(explode(self::RECORD, $result->output()))
            ->filter(fn ($chunk) => trim($chunk) !== '')
            ->map(fn ($chunk) => $this->parseCommit($chunk))
            ->values()
            ->all();
    }

    private function parseCommit(string $chunk): array
    {
        [$hash, $date, $subject, $body, $stats] = array_pad(explode(self::FIELD, $chunk, 5), 5, '');

        $files = collect(preg_split('/\R/', trim($stats)))
            ->filter()
            ->map(function ($line) {
                [$added, $deleted, $file] = explode("\t", $line, 3);

                return [
                    'file' => $file,
                    'added' => $added === '-' ? 0 : (int) $added,
                    'deleted' => $deleted === '-' ? 0 : (int) $deleted,
                ];
            })
            ->values()
            ->all();

        return [
            'hash' => $hash,
            'date' => $date,
            'subject' => trim($subject),
            'body' => trim($body),
            'files' => $files,
        ];
    }
}
