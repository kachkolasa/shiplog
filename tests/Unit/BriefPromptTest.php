<?php

use App\Services\BriefPrompt;
use App\Services\Settings;

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/shiplog-prompt-'.bin2hex(random_bytes(6));
    $this->settings = new Settings($this->directory);
});

afterEach(function () {
    if (is_dir($this->directory)) {
        array_map('unlink', glob($this->directory.'/*') ?: []);
        rmdir($this->directory);
    }
});

function promptFor(Settings $settings, array $values = []): string
{
    if ($values !== []) {
        $settings->save($values);
    }

    return (new BriefPrompt($settings))->build();
}

it('always carries the base rules', function () {
    expect(promptFor($this->settings))
        ->toContain('Describe outcomes, not mechanics')
        ->toContain('Never mention file paths')
        ->toContain('at most ten words')
        ->toContain('An omission is recoverable');
});

it('describes a non-technical reader by default', function () {
    expect(promptFor($this->settings))
        ->toContain('the reader is not technical')
        ->toContain('no library, tool or component names');
});

it('describes a technical reader when configured', function () {
    $prompt = promptFor($this->settings, ['audience' => 'technical']);

    expect($prompt)->toContain('the reader is technical')
        ->and($prompt)->not->toContain('the reader is not technical');
});

it('writes in the first person by default', function () {
    expect(promptFor($this->settings))->toContain('We added date filtering');
});

it('writes neutrally when configured', function () {
    $prompt = promptFor($this->settings, ['tone' => 'neutral']);

    expect($prompt)->toContain('Date filtering is now available')
        ->and($prompt)->not->toContain('We added date filtering');
});

it('passes the configured item cap through', function () {
    expect(promptFor($this->settings, ['max_items' => 3]))
        ->toContain('at most 3 items');
});

it('tells the model to merge rather than drop work over the cap', function () {
    expect(promptFor($this->settings))->toContain('rather than leaving them out');
});

it('falls back to the defaults for values it does not recognise', function () {
    $prompt = promptFor($this->settings, ['audience' => 'martian', 'tone' => 'shouty']);

    expect($prompt)->toContain('the reader is not technical')
        ->and($prompt)->toContain('We added date filtering');
});

it('clamps an absurd item cap', function () {
    expect(promptFor($this->settings, ['max_items' => 9999]))->toContain('at most 20 items');
});

it('clamps a nonsensical item cap upward', function () {
    expect(promptFor($this->settings, ['max_items' => 0]))->toContain('at most 1 items');
});

it('uses the default cap when the key holds something unusable', function () {
    expect(promptFor($this->settings, ['max_items' => 'lots']))
        ->toContain('at most '.Settings::DEFAULTS['max_items'].' items');
});

it('reads a numeric string as a number', function () {
    expect(promptFor($this->settings, ['max_items' => '4']))->toContain('at most 4 items');
});

it('says nothing about diffs on the shallow path', function () {
    expect(promptFor($this->settings))->not->toContain('Evidence:');
});

it('tells the model the diff outranks the commit message when deep', function () {
    $prompt = (new BriefPrompt($this->settings))->build(deep: true);

    expect($prompt)->toContain('Evidence:')
        ->toContain('diff is right')
        ->toContain('certain=false');
});

it('keeps the audience and length rules when deep', function () {
    $this->settings->save(['audience' => 'technical', 'max_items' => 2]);
    $prompt = (new BriefPrompt($this->settings))->build(deep: true);

    expect($prompt)->toContain('the reader is technical')
        ->toContain('at most 2 items');
});
