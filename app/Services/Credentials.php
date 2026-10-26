<?php

namespace App\Services;

final class Credentials
{
    private const array VARIABLES = [
        'ANTHROPIC_API_KEY',
        'ANTHROPIC_AUTH_TOKEN',
    ];

    public function __construct(private readonly string $anthropicConfigDirectory) {}

    public function source(): ?string
    {
        foreach (self::VARIABLES as $variable) {
            if (trim((string) getenv($variable)) !== '') {
                return $variable;
            }
        }

        if (is_dir($this->anthropicConfigDirectory)) {
            return "ant profile ({$this->anthropicConfigDirectory})";
        }

        return null;
    }

    public function configured(): bool
    {
        return $this->source() !== null;
    }
}
