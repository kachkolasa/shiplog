<?php

use App\Contracts\BriefWriter;
use App\Data\BriefOptions;
use App\Data\ClientBrief;
use App\Services\FakeBriefWriter;

function briefOptions(bool $deep = false): BriefOptions
{
    return new BriefOptions(
        audience: 'non-technical',
        tone: 'first-person',
        maxItems: 6,
        deep: $deep,
    );
}

it('resolves a brief writer from the container', function () {
    expect(app(BriefWriter::class))->toBeInstanceOf(BriefWriter::class);
});

it('returns a typed brief and records the digest it was given', function () {
    $writer = new FakeBriefWriter;

    $brief = $writer->write(
        '- 2026-10-25 | add git log service | app/Services | ~85 lines',
        briefOptions(),
    );

    expect($brief)->toBeInstanceOf(ClientBrief::class)
        ->and($brief->items)->not->toBeEmpty()
        ->and($writer->received)->toContain('2026-10-25');
});

it('can be handed a canned brief', function () {
    $canned = new ClientBrief(headline: 'Nothing shipped', items: [], unclear: []);

    expect((new FakeBriefWriter($canned))->write('', briefOptions())->headline)->toBe('Nothing shipped');
});
