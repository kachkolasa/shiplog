<?php

use App\Data\BriefOptions;
use App\Services\BriefPrompt;

function promptFor(array $overrides = []): string
{
    return (new BriefPrompt)->build(new BriefOptions(
        audience: $overrides['audience'] ?? 'non-technical',
        tone: $overrides['tone'] ?? 'first-person',
        maxItems: $overrides['maxItems'] ?? 6,
        deep: $overrides['deep'] ?? false,
    ));
}

it('always carries the base rules', function () {
    expect(promptFor())
        ->toContain('Describe outcomes, not mechanics')
        ->toContain('Never mention file paths')
        ->toContain('at most ten words')
        ->toContain('An omission is recoverable');
});

it('describes a non-technical reader by default', function () {
    expect(promptFor())
        ->toContain('the reader is not technical')
        ->toContain('no library, tool or component names');
});

it('describes a technical reader when configured', function () {
    $prompt = promptFor(['audience' => 'technical']);

    expect($prompt)->toContain('the reader is technical')
        ->and($prompt)->not->toContain('the reader is not technical');
});

it('writes in the first person by default', function () {
    expect(promptFor())->toContain('We added date filtering');
});

it('writes neutrally when configured', function () {
    $prompt = promptFor(['tone' => 'neutral']);

    expect($prompt)->toContain('Date filtering is now available')
        ->and($prompt)->not->toContain('We added date filtering');
});

it('passes the configured item cap through', function () {
    expect(promptFor(['maxItems' => 3]))->toContain('at most 3 items');
});

it('tells the model to merge rather than drop work over the cap', function () {
    expect(promptFor())->toContain('rather than leaving them out');
});

it('falls back to the defaults for values it does not recognise', function () {
    // BriefOptions validates on fromSettings() and with(), but not in the
    // constructor, so the prompt keeps a defensive default arm.
    $prompt = promptFor(['audience' => 'martian', 'tone' => 'shouty']);

    expect($prompt)->toContain('the reader is not technical')
        ->and($prompt)->toContain('We added date filtering');
});

it('clamps an absurd item cap', function () {
    expect(promptFor(['maxItems' => 9999]))->toContain('at most 20 items');
});

it('clamps a nonsensical item cap upward', function () {
    expect(promptFor(['maxItems' => 0]))->toContain('at most 1 items');
});

it('says nothing about diffs on the shallow path', function () {
    expect(promptFor())->not->toContain('Evidence:');
});

it('tells the model the diff outranks the commit message when deep', function () {
    $prompt = promptFor(['deep' => true]);

    expect($prompt)->toContain('Evidence:')
        ->toContain('diff is right')
        ->toContain('certain=false');
});

it('keeps the audience and length rules when deep', function () {
    $prompt = promptFor(['deep' => true, 'audience' => 'technical', 'maxItems' => 2]);

    expect($prompt)->toContain('the reader is technical')
        ->toContain('at most 2 items');
});
