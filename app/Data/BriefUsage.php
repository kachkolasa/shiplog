<?php

namespace App\Data;

final readonly class BriefUsage
{
    private const array RATES = [
        'claude-fable-5-1' => ['in' => 10.00, 'out' => 50.00],
        'claude-fable-5' => ['in' => 10.00, 'out' => 50.00],
        'claude-opus-5-5' => ['in' => 4.00, 'out' => 20.00],
        'claude-opus-5' => ['in' => 5.00, 'out' => 25.00],
        'claude-sonnet-5-5' => ['in' => 2.00, 'out' => 10.00],
        'claude-haiku-5-5' => ['in' => 0.10, 'out' => 0.50],
    ];

    public function __construct(
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
        public int $cachedInputTokens = 0,
    ) {}

    public function estimatedCost(): ?float
    {
        $rate = self::RATES[$this->model] ?? null;

        if ($rate === null) {
            return null;
        }

        return (($this->inputTokens + $this->cachedInputTokens) * $rate['in']
            + $this->outputTokens * $rate['out']) / 1_000_000;
    }
}
