<?php

use App\Data\BriefUsage;

it('prices a known model', function () {
    // 1M in at $10 + 100k out at $50 = $10 + $5
    $usage = new BriefUsage('claude-fable-5-1', inputTokens: 1_000_000, outputTokens: 100_000);

    expect($usage->estimatedCost())->toBe(15.0);
});

it('prices the cheaper models differently', function () {
    $opus = new BriefUsage('claude-opus-5-5', inputTokens: 1_000_000, outputTokens: 0);
    $haiku = new BriefUsage('claude-haiku-5-5', inputTokens: 1_000_000, outputTokens: 0);

    expect($opus->estimatedCost())->toBe(4.0)
        ->and($haiku->estimatedCost())->toBe(0.1);
});

it('reports no cost for a model it does not know', function () {
    $usage = new BriefUsage('claude-from-the-future', inputTokens: 1000, outputTokens: 100);

    expect($usage->estimatedCost())->toBeNull();
});

it('counts cached reads as input so the figure is never an undercount', function () {
    $plain = new BriefUsage('claude-opus-5-5', inputTokens: 1_000_000, outputTokens: 0);
    $cached = new BriefUsage('claude-opus-5-5', inputTokens: 0, outputTokens: 0, cachedInputTokens: 1_000_000);

    expect($cached->estimatedCost())->toBe($plain->estimatedCost());
});

it('costs a realistic deep run in cents, not dollars', function () {
    $usage = new BriefUsage('claude-fable-5-1', inputTokens: 23_000, outputTokens: 1_200);

    expect($usage->estimatedCost())->toBeGreaterThan(0.2)
        ->and($usage->estimatedCost())->toBeLessThan(0.4);
});
