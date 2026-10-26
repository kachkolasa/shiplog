<?php

use App\Data\BriefItem;
use App\Data\ClientBrief;
use App\Services\BriefRenderer;
use Illuminate\Support\Carbon;

/**
 * @param  array<int, BriefItem>  $items
 * @param  array<int, string>  $unclear
 */
function renderBrief(array $items = [], array $unclear = [], string $headline = 'A week of work'): string
{
    return (new BriefRenderer)->render(
        new ClientBrief(headline: $headline, items: $items, unclear: $unclear),
        Carbon::parse('2026-10-19'),
        Carbon::parse('2026-10-26'),
    );
}

function briefItem(array $overrides = []): BriefItem
{
    return new BriefItem(
        title: $overrides['title'] ?? 'Date filtering',
        detail: $overrides['detail'] ?? 'Pick any range.',
        area: $overrides['area'] ?? 'Reporting',
        certain: $overrides['certain'] ?? true,
    );
}

it('shows the headline and the date range', function () {
    $html = renderBrief(headline: 'Three changes shipped');

    expect($html)->toContain('Three changes shipped')
        ->and($html)->toContain('Oct 19, 2026 – Oct 26, 2026');
});

it('escapes markup in writer output', function () {
    $html = renderBrief([briefItem([
        'title' => 'Fixed <Foo> rendering',
        'detail' => 'Tags like <b> now survive.',
    ])]);

    // Unescaped, Termwind would parse "<Foo>" as a tag and drop the text.
    expect($html)->toContain('Fixed &lt;Foo&gt; rendering')
        ->and($html)->toContain('Tags like &lt;b&gt; now survive.')
        ->and($html)->not->toContain('<Foo>');
});

it('flags an unverified item', function () {
    $html = renderBrief([briefItem(['certain' => false])]);

    expect($html)->toContain('(unverified)')
        ->and($html)->toContain('text-yellow-400');
});

it('does not flag a verified item', function () {
    $html = renderBrief([briefItem(['certain' => true])]);

    expect($html)->not->toContain('(unverified)')
        ->and($html)->toContain('text-green-400');
});

it('lists the commits that were too vague', function () {
    $html = renderBrief([], ['wip', 'command']);

    expect($html)->toContain('tighten these commit messages')
        ->and($html)->toContain('wip')
        ->and($html)->toContain('command');
});

it('omits the vague-commit block when there is nothing to report', function () {
    expect(renderBrief([briefItem()]))->not->toContain('tighten these commit messages');
});

it('escapes a vague commit subject too', function () {
    expect(renderBrief([], ['fix <script> tag']))->toContain('fix &lt;script&gt; tag');
});
