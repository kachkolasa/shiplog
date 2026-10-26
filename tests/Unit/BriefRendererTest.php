<?php

use App\Data\BriefItem;
use App\Data\BriefUsage;
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

it('says nothing about tokens when there is no usage', function () {
    expect(renderBrief([briefItem()]))->not->toContain('tokens');
});

it('reports tokens and an estimated cost', function () {
    $brief = new ClientBrief(headline: 'A week', items: [briefItem()], unclear: []);
    $html = (new BriefRenderer)->render(
        $brief->withUsage(new BriefUsage('claude-fable-5-1', inputTokens: 23_000, outputTokens: 1_200)),
        Carbon::parse('2026-10-19'),
        Carbon::parse('2026-10-26'),
    );

    expect($html)->toContain('23,000 tokens in, 1,200 out')
        ->toContain('$0.29')
        ->toContain('claude-fable-5-1');
});

it('shows more precision for a run that costs fractions of a cent', function () {
    $brief = new ClientBrief(headline: 'A week', items: [], unclear: []);
    $html = (new BriefRenderer)->render(
        $brief->withUsage(new BriefUsage('claude-haiku-5-5', inputTokens: 20_000, outputTokens: 500)),
        Carbon::parse('2026-10-19'),
        Carbon::parse('2026-10-26'),
    );

    expect($html)->toContain('$0.0023');
});

it('says under a hundredth of a cent rather than printing zero', function () {
    $brief = new ClientBrief(headline: 'A week', items: [], unclear: []);
    $html = (new BriefRenderer)->render(
        $brief->withUsage(new BriefUsage('claude-haiku-5-5', inputTokens: 200, outputTokens: 50)),
        Carbon::parse('2026-10-19'),
        Carbon::parse('2026-10-26'),
    );

    expect($html)->toContain('under $0.0001')
        ->not->toContain('$0.0000');
});

it('omits the cost but keeps the tokens for an unknown model', function () {
    $brief = new ClientBrief(headline: 'A week', items: [], unclear: []);
    $html = (new BriefRenderer)->render(
        $brief->withUsage(new BriefUsage('claude-from-the-future', inputTokens: 100, outputTokens: 10)),
        Carbon::parse('2026-10-19'),
        Carbon::parse('2026-10-26'),
    );

    expect($html)->toContain('100 tokens in, 10 out')
        ->not->toContain('about $');
});
