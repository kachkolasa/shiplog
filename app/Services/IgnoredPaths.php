<?php

namespace App\Services;

use Illuminate\Support\Str;

final class IgnoredPaths
{
    private const array FILES = [
        'composer.lock',
        'package-lock.json',
        'yarn.lock',
        'pnpm-lock.yaml',
    ];

    private const array PREFIXES = [
        'vendor/',
        'node_modules/',
    ];

    public static function matches(string $file): bool
    {
        return in_array(basename($file), self::FILES, true)
            || Str::startsWith($file, self::PREFIXES);
    }

    public static function pathspec(): array
    {
        $pathspec = ['--', '.'];

        foreach (self::FILES as $file) {
            $pathspec[] = ":(exclude,glob)**/{$file}";
        }

        foreach (self::PREFIXES as $prefix) {
            $pathspec[] = ':(exclude)'.rtrim($prefix, '/');
        }

        return $pathspec;
    }
}
