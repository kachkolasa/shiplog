<?php

use App\Data\ClientBrief;

it('hydrates a brief from an api payload', function () {
    $brief = ClientBrief::fromArray([
        'headline' => 'Two changes shipped',
        'items' => [
            ['title' => 'Date filtering', 'detail' => 'Pick any range.', 'area' => 'Reporting', 'certain' => true],
        ],
        'unclear' => ['wip', 'command'],
    ]);

    expect($brief->headline)->toBe('Two changes shipped')
        ->and($brief->items)->toHaveCount(1)
        ->and($brief->items[0]->title)->toBe('Date filtering')
        ->and($brief->items[0]->certain)->toBeTrue()
        ->and($brief->unclear)->toBe(['wip', 'command']);
});

it('treats a missing certainty flag as uncertain', function () {
    $brief = ClientBrief::fromArray([
        'headline' => 'One change',
        'items' => [['title' => 'Something', 'detail' => 'Vague.', 'area' => 'Core']],
        'unclear' => [],
    ]);

    expect($brief->items[0]->certain)->toBeFalse();
});

it('tolerates a payload missing the optional keys', function () {
    $brief = ClientBrief::fromArray(['headline' => 'Quiet week']);

    expect($brief->items)->toBe([])
        ->and($brief->unclear)->toBe([]);
});

it('reindexes items and unclear entries', function () {
    $brief = ClientBrief::fromArray([
        'headline' => 'Gaps',
        'items' => [3 => ['title' => 'A', 'detail' => 'B', 'area' => 'C', 'certain' => true]],
        'unclear' => [7 => 'wip'],
    ]);

    expect(array_keys($brief->items))->toBe([0])
        ->and(array_keys($brief->unclear))->toBe([0]);
});
