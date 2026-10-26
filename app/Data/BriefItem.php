<?php

namespace App\Data;

final readonly class BriefItem
{
    public function __construct(
        public string $title,
        public string $detail,
        public string $area,
        public bool $certain,
    ) {}

    public static function fromArray(array $item): self
    {
        return new self(
            title: (string) ($item['title'] ?? ''),
            detail: (string) ($item['detail'] ?? ''),
            area: (string) ($item['area'] ?? ''),
            certain: (bool) ($item['certain'] ?? false),
        );
    }
}
