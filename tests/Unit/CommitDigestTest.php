<?php

use App\Services\CommitDigest;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function digestCommit(array $overrides = []): array
{
    return array_merge([
        'hash' => 'b3bfc0e2a076d7dd5db73aa73b7e6a9cbebce53b',
        'date' => '2026-10-25T05:27:31+04:00',
        'subject' => 'do a thing',
        'body' => '',
        'files' => [
            ['file' => 'app/Services/GitLog.php', 'added' => 10, 'deleted' => 2],
        ],
    ], $overrides);
}

it('renders one line per commit, oldest first', function () {
    $digest = (new CommitDigest)->build([
        digestCommit(['date' => '2026-10-25T10:00:00+00:00', 'subject' => 'second']),
        digestCommit(['date' => '2026-10-20T10:00:00+00:00', 'subject' => 'first']),
    ]);

    expect(explode("\n", $digest))->toHaveCount(2)
        ->and($digest)->toStartWith('- 2026-10-20 | first');
});

it('reports the touched areas and total churn', function () {
    $digest = (new CommitDigest)->build([digestCommit([
        'subject' => 'add git log service',
        'files' => [
            ['file' => 'app/Services/GitLog.php', 'added' => 85, 'deleted' => 0],
            ['file' => 'app/Commands/BriefCommand.php', 'added' => 18, 'deleted' => 2],
        ],
    ])]);

    expect($digest)->toBe('- 2026-10-25 | add git log service | app/Commands, app/Services | ~105 lines');
});

it('leaves lockfile churn out of the totals and the areas', function () {
    $digest = (new CommitDigest)->build([digestCommit([
        'subject' => 'install the sdk',
        'files' => [
            ['file' => 'composer.lock', 'added' => 8637, 'deleted' => 0],
            ['file' => 'composer.json', 'added' => 1, 'deleted' => 1],
        ],
    ])]);

    expect($digest)->toBe('- 2026-10-25 | install the sdk | root | ~2 lines');
});

it('leaves vendored paths out', function () {
    $digest = (new CommitDigest)->build([digestCommit([
        'files' => [
            ['file' => 'vendor/anthropic-ai/sdk/src/Client.php', 'added' => 400, 'deleted' => 0],
            ['file' => 'app/Services/GitLog.php', 'added' => 3, 'deleted' => 1],
        ],
    ])]);

    expect($digest)->toContain('app/Services | ~4 lines')
        ->and($digest)->not->toContain('vendor');
});

it('labels a commit whose every file was ignored', function () {
    $digest = (new CommitDigest)->build([digestCommit([
        'subject' => 'bump dependencies',
        'files' => [['file' => 'composer.lock', 'added' => 120, 'deleted' => 90]],
    ])]);

    expect($digest)->toBe('- 2026-10-25 | bump dependencies | dependencies');
});

it('labels a commit that changed no files', function () {
    $digest = (new CommitDigest)->build([digestCommit([
        'subject' => 'start the release',
        'files' => [],
    ])]);

    expect($digest)->toBe('- 2026-10-25 | start the release | no file changes');
});

it('calls repository-root files root', function () {
    $digest = (new CommitDigest)->build([digestCommit([
        'files' => [['file' => 'README.md', 'added' => 42, 'deleted' => 0]],
    ])]);

    expect($digest)->toContain('| root | ~42 lines');
});

it('collapses whitespace in subjects', function () {
    $digest = (new CommitDigest)->build([digestCommit([
        'subject' => "fix   the\n\tparser",
    ])]);

    expect($digest)->toContain('| fix the parser |');
});

it('truncates a long area list', function () {
    $digest = (new CommitDigest)->build([digestCommit([
        'files' => [
            ['file' => 'app/A/x.php', 'added' => 1, 'deleted' => 0],
            ['file' => 'app/B/x.php', 'added' => 1, 'deleted' => 0],
            ['file' => 'app/C/x.php', 'added' => 1, 'deleted' => 0],
            ['file' => 'app/D/x.php', 'added' => 1, 'deleted' => 0],
            ['file' => 'app/E/x.php', 'added' => 1, 'deleted' => 0],
        ],
    ])]);

    expect($digest)->toContain('app/A, app/B, app/C, app/D +1 more');
});

it('never leaks commit hashes', function () {
    $digest = (new CommitDigest)->build([digestCommit(['hash' => 'deadbeefcafe'])]);

    expect($digest)->not->toContain('deadbeef');
});

it('returns an empty digest for no commits', function () {
    expect((new CommitDigest)->build([]))->toBe('');
});

it('leaves the digest unchanged when no patches are supplied', function () {
    $digest = (new CommitDigest)->build([digestCommit()]);

    expect($digest)->not->toContain('```diff')
        ->and($digest)->not->toContain('no diff included');
});

it('appends a fenced diff when a patch is supplied', function () {
    $digest = (new CommitDigest)->build(
        [digestCommit(['hash' => 'aaa'])],
        ['aaa' => "diff --git a/x b/x\n+hello"],
    );

    expect($digest)->toContain("```diff\ndiff --git a/x b/x\n+hello\n```");
});

it('marks a commit that got no patch so the model can distrust it', function () {
    $digest = (new CommitDigest)->build(
        [digestCommit(['hash' => 'aaa']), digestCommit(['hash' => 'bbb', 'subject' => 'other'])],
        ['aaa' => 'diff --git a/x b/x'],
    );

    expect($digest)->toContain('(no diff included for this commit)');
});

it('spends the patch budget on the newest commits first', function () {
    $commits = [
        digestCommit(['hash' => 'old', 'subject' => 'older', 'date' => '2026-10-20T10:00:00+00:00']),
        digestCommit(['hash' => 'new', 'subject' => 'newer', 'date' => '2026-10-25T10:00:00+00:00']),
    ];

    $digest = (new CommitDigest)->build($commits, [
        'old' => str_repeat('a', 60),
        'new' => str_repeat('b', 60),
    ], budget: 60);

    expect($digest)->toContain(str_repeat('b', 60))
        ->and($digest)->not->toContain(str_repeat('a', 60));
});

it('still fits a small older patch once a large newer one is skipped', function () {
    $commits = [
        digestCommit(['hash' => 'small', 'subject' => 'older', 'date' => '2026-10-20T10:00:00+00:00']),
        digestCommit(['hash' => 'huge', 'subject' => 'newer', 'date' => '2026-10-25T10:00:00+00:00']),
    ];

    $digest = (new CommitDigest)->build($commits, [
        'small' => str_repeat('a', 10),
        'huge' => str_repeat('b', 500),
    ], budget: 100);

    expect($digest)->toContain(str_repeat('a', 10))
        ->and($digest)->not->toContain(str_repeat('b', 500));
});

it('never includes a patch for a commit it was not given one for', function () {
    $digest = (new CommitDigest)->build(
        [digestCommit(['hash' => 'aaa'])],
        ['zzz' => 'diff --git a/other b/other'],
    );

    expect($digest)->not->toContain('a/other');
});
