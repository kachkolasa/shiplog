<?php

namespace App\Data;

use App\Services\Settings;

final class BriefOptions
{
    public const array AUDIENCES = [
        'non-technical' => 'Non-technical (a founder, or a client contact)',
        'technical' => 'Technical (a CTO, or an engineering lead)',
    ];

    public const array TONES = [
        'first-person' => 'First person ("We shipped date filtering")',
        'neutral' => 'Neutral ("Date filtering is now available")',
    ];

    public const int MIN_ITEMS = 1;

    public const int MAX_ITEMS = 20;

    public function __construct(
        public readonly string $audience,
        public readonly string $tone,
        public readonly int $maxItems,
        public readonly bool $deep = true,
    ) {}

    public static function fromSettings(Settings $settings): self
    {
        self::validate(
            $settings->get('audience'),
            $settings->get('tone'),
            $settings->get('max_items')
        );

        return new self(
            audience: $settings->get('audience'),
            tone: $settings->get('tone'),
            maxItems: $settings->get('max_items'),
            deep: $settings->get('deep'),
        );
    }

    public function with(
        ?string $audience = null,
        ?string $tone = null,
        ?int $maxItems = null,
        ?bool $deep = null
    ): self {
        self::validate($audience, $tone, $maxItems);

        return new self(
            audience: $audience ?? $this->audience,
            tone: $tone ?? $this->tone,
            maxItems: $maxItems ?? $this->maxItems,
            deep: $deep === null ? $this->deep : $deep,
        );
    }

    private static function validate(?string $audience, ?string $tone, ?int $maxItems): void
    {
        if ($audience !== null && ! array_key_exists($audience, self::AUDIENCES)) {
            throw new \InvalidArgumentException("Invalid audience: {$audience}");
        }

        if ($tone !== null && ! array_key_exists($tone, self::TONES)) {
            throw new \InvalidArgumentException("Invalid tone: {$tone}");
        }

        if ($maxItems !== null && ($maxItems < self::MIN_ITEMS || $maxItems > self::MAX_ITEMS)) {
            throw new \InvalidArgumentException('maxItems must be between '.self::MIN_ITEMS.' and '.self::MAX_ITEMS);
        }
    }
}
