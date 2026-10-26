<?php

use App\Contracts\BriefWriter;
use App\Services\Credentials;
use App\Services\FakeBriefWriter;
use Illuminate\Support\Facades\Process;

/**
 * Builds output in the shape GitLog asks git for:
 * --pretty=format:%x1e%H%x1f%ad%x1f%s%x1f%b%x1f followed by --numstat lines.
 *
 * @param  array<int, array{hash: string, date: string, subject: string, files: array<int, array{0: int, 1: int, 2: string}>}>  $commits
 */
function fakeGitLog(array $commits): string
{
    return collect($commits)
        ->map(function (array $commit) {
            $stats = collect($commit['files'])
                ->map(fn (array $file) => "{$file[0]}\t{$file[1]}\t{$file[2]}")
                ->implode("\n");

            return "\x1e{$commit['hash']}\x1f{$commit['date']}\x1f{$commit['subject']}\x1f\x1f\n{$stats}\n";
        })
        ->implode("\n");
}

/**
 * Patterns match Symfony's escaped commandline, where every argument is
 * single-quoted: 'git' 'log' '--branches' ...
 *
 * @param  array<int, array<string, mixed>>  $commits
 */
function fakeGit(array $commits, string $email = 'dev@example.com'): void
{
    Process::fake([
        "*'rev-parse'*" => Process::result('true'),
        "*'config' 'user.email'*" => Process::result($email),
        "*'log'*" => Process::result(fakeGitLog($commits)),
    ]);
}

/**
 * A faked process keeps `command` in the form it was given, which is an array
 * here, so flatten it before matching.
 */
function ranCommand(object $process, string $needle): bool
{
    $command = is_array($process->command)
        ? implode(' ', $process->command)
        : (string) $process->command;

    return str_contains($command, $needle);
}

function gitCommits(): array
{
    return [
        [
            'hash' => 'deadbeefcafe0000000000000000000000000000',
            'date' => '2026-10-20T09:00:00+00:00',
            'subject' => 'add git log service',
            'files' => [[85, 0, 'app/Services/GitLog.php'], [8637, 0, 'composer.lock']],
        ],
        [
            'hash' => 'feedface0000000000000000000000000000beef',
            'date' => '2026-10-22T09:00:00+00:00',
            'subject' => 'render the brief',
            'files' => [[40, 3, 'app/Services/BriefRenderer.php']],
        ],
    ];
}

beforeEach(function () {
    // The command refuses to run without credentials, so the suite controls
    // that rather than depending on whether this machine happens to have an ant
    // profile or an exported key.
    $this->savedEnv = [
        'ANTHROPIC_API_KEY' => getenv('ANTHROPIC_API_KEY'),
        'ANTHROPIC_AUTH_TOKEN' => getenv('ANTHROPIC_AUTH_TOKEN'),
    ];
    putenv('ANTHROPIC_API_KEY');
    putenv('ANTHROPIC_AUTH_TOKEN');

    $this->credentialDirectory = sys_get_temp_dir().'/shiplog-brief-creds-'.bin2hex(random_bytes(6));
    mkdir($this->credentialDirectory, 0700, true);

    $this->app->instance(Credentials::class, new Credentials($this->credentialDirectory));
});

afterEach(function () {
    foreach ($this->savedEnv as $variable => $value) {
        is_string($value) ? putenv("{$variable}={$value}") : putenv($variable);
    }

    if (is_dir($this->credentialDirectory)) {
        rmdir($this->credentialDirectory);
    }
});

it('hands the writer a digest built from the faked history', function () {
    fakeGit(gitCommits());

    $writer = new FakeBriefWriter;
    $this->app->instance(BriefWriter::class, $writer);

    $this->artisan('brief')->assertExitCode(0);

    expect($writer->received)
        ->toContain('add git log service')
        ->toContain('render the brief')
        // Oldest first, lockfile churn and hashes stripped on the way through.
        ->toStartWith('- 2026-10-20 | add git log service | app/Services | ~85 lines')
        ->not->toContain('deadbeef')
        ->not->toContain('8637');
});

it('renders the brief the writer returned', function () {
    fakeGit(gitCommits());

    $this->artisan('brief')
        ->expectsOutputToContain('Three changes shipped this week')
        ->assertExitCode(0);
});

it('warns and succeeds when the range holds no commits', function () {
    fakeGit([]);

    $this->artisan('brief')
        ->expectsOutputToContain('No commits between')
        ->assertExitCode(0);
});

it('fails cleanly outside a git repository', function () {
    Process::fake([
        "*'rev-parse'*" => Process::result('', 'not a git repository', 128),
    ]);

    $this->artisan('brief')
        ->expectsOutputToContain('This folder is not a git repository.')
        ->assertExitCode(1);
});

it('looks up the current author by default', function () {
    fakeGit(gitCommits());

    $this->artisan('brief')->assertExitCode(0);

    Process::assertRan(fn ($process) => ranCommand($process, 'user.email'));
});

it('skips the author lookup with --all-authors', function () {
    fakeGit(gitCommits());

    $this->artisan('brief', ['--all-authors' => true])->assertExitCode(0);

    Process::assertDidntRun(fn ($process) => ranCommand($process, 'user.email'));
});

it('refuses to run without credentials', function () {
    Process::fake();
    $this->app->instance(Credentials::class, new Credentials($this->credentialDirectory.'/absent'));

    $this->artisan('brief')
        ->expectsOutputToContain('shiplog setup')
        ->assertExitCode(1);

    Process::assertNothingRan();
});
