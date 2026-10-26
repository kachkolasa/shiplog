<?php

namespace App\Data;

final readonly class ClientBrief
{
    public function __construct(
        public string $headline,
        public array $items,
        public array $unclear,
        public ?BriefUsage $usage = null,
    ) {}

    public function withUsage(BriefUsage $usage): self
    {
        return new self($this->headline, $this->items, $this->unclear, $usage);
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            headline: (string) ($payload['headline'] ?? ''),
            items: array_map(
                BriefItem::fromArray(...),
                array_values($payload['items'] ?? []),
            ),
            unclear: array_values(array_map('strval', $payload['unclear'] ?? [])),
        );
    }
}
