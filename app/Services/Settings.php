<?php

namespace App\Services;

use RuntimeException;

final class Settings
{
    public const array DEFAULTS = [
        'audience' => 'non-technical',
        'tone' => 'first-person',
        'max_items' => 6,
    ];

    public function __construct(private readonly string $directory) {}

    public function path(): string
    {
        return $this->directory.'/config.json';
    }

    public function exists(): bool
    {
        return is_file($this->path());
    }

    public function all(): array
    {
        if (! $this->exists()) {
            return self::DEFAULTS;
        }

        $contents = file_get_contents($this->path());

        if ($contents === false) {
            throw new RuntimeException("Could not read {$this->path()}.");
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            throw new RuntimeException(
                "{$this->path()} is not valid JSON. Fix it, or delete it and run `shiplog setup` again."
            );
        }

        return array_merge(self::DEFAULTS, $decoded);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function save(array $values): void
    {
        $merged = array_merge(self::DEFAULTS, $values);
        ksort($merged);

        $this->write($merged);
    }

    private function write(array $values): void
    {
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0700, true) && ! is_dir($this->directory)) {
            throw new RuntimeException("Could not create {$this->directory}.");
        }

        $json = json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $temp = $this->directory.'/config.json.'.bin2hex(random_bytes(6));

        if (file_put_contents($temp, $json.PHP_EOL) === false) {
            throw new RuntimeException("Could not write to {$this->directory}.");
        }

        chmod($temp, 0600);

        if (! rename($temp, $this->path())) {
            @unlink($temp);

            throw new RuntimeException("Could not move the new config into {$this->path()}.");
        }
    }
}
