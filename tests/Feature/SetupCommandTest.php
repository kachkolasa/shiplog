<?php

use App\Services\Credentials;
use App\Services\Settings;

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/shiplog-setup-'.bin2hex(random_bytes(6));
    $this->settings = new Settings($this->directory);
    $this->app->instance(Settings::class, $this->settings);

    $this->savedEnv = [
        'ANTHROPIC_API_KEY' => getenv('ANTHROPIC_API_KEY'),
        'ANTHROPIC_AUTH_TOKEN' => getenv('ANTHROPIC_AUTH_TOKEN'),
    ];
    putenv('ANTHROPIC_API_KEY');
    putenv('ANTHROPIC_AUTH_TOKEN');

    $this->app->instance(Credentials::class, new Credentials($this->directory.'/absent'));

    // In tests the framework deliberately turns prompts on and routes them
    // through Symfony's question helper, which reads STDIN. A test that reaches
    // a prompt without declaring the answer blocks forever under a real
    // terminal, so every test either passes all three flags or uses
    // expectsQuestion.
    $this->allFlags = [
        '--audience' => 'non-technical',
        '--tone' => 'first-person',
        '--max-items' => '6',
        '--deep' => 'false',
    ];
});

afterEach(function () {
    foreach ($this->savedEnv as $variable => $value) {
        is_string($value) ? putenv("{$variable}={$value}") : putenv($variable);
    }

    if (is_dir($this->directory)) {
        array_map('unlink', glob($this->directory.'/*') ?: []);
        rmdir($this->directory);
    }
});

it('saves the values passed as flags', function () {
    $this->artisan('setup', [
        '--audience' => 'technical',
        '--tone' => 'neutral',
        '--max-items' => '4',
        '--deep' => 'true',
    ])->assertExitCode(0);

    expect($this->settings->all())->toEqual([
        'audience' => 'technical',
        'max_items' => 4,
        'tone' => 'neutral',
        'deep' => true,
    ]);
});

it('saves the answers given to the prompts', function () {
    $this->artisan('setup')
        ->expectsQuestion('Who reads the brief?', 'technical')
        ->expectsQuestion('How should it be written?', 'neutral')
        ->expectsQuestion('At most how many items in a brief?', '3')
        ->expectsQuestion('Include code diffs in the brief?', 'true')
        ->assertExitCode(0);

    expect($this->settings->all())->toEqual([
        'audience' => 'technical',
        'max_items' => 3,
        'tone' => 'neutral',
        'deep' => true,
    ]);
});

it('stores max items as an integer, not the string from the flag', function () {
    $this->artisan('setup', array_merge($this->allFlags, ['--max-items' => '9']))
        ->assertExitCode(0);

    expect($this->settings->get('max_items'))->toBe(9);
});

it('writes the defaults when every flag holds its default', function () {
    $this->artisan('setup', $this->allFlags)->assertExitCode(0);

    expect($this->settings->all())->toEqual(Settings::DEFAULTS);
});

it('rejects an unknown audience instead of saving it', function () {
    $this->artisan('setup', ['--audience' => 'nobody'])
        ->expectsOutputToContain('--audience must be one of')
        ->assertExitCode(1);

    expect($this->settings->exists())->toBeFalse();
});

it('rejects an unknown tone', function () {
    $this->artisan('setup', array_merge($this->allFlags, ['--tone' => 'shouty']))
        ->expectsOutputToContain('--tone must be one of')
        ->assertExitCode(1);
});

it('rejects a max items value that is not a number', function () {
    $this->artisan('setup', array_merge($this->allFlags, ['--max-items' => 'lots']))
        ->expectsOutputToContain('Enter a whole number')
        ->assertExitCode(1);
});

it('rejects a max items value out of range', function () {
    $this->artisan('setup', array_merge($this->allFlags, ['--max-items' => '99']))
        ->expectsOutputToContain('20 or fewer')
        ->assertExitCode(1);
});

it('explains how to provide a credential when none is found', function () {
    $this->artisan('setup', $this->allFlags)
        ->expectsOutputToContain('not found')
        ->expectsOutputToContain('ant auth login')
        ->expectsOutputToContain('ANTHROPIC_API_KEY')
        ->expectsOutputToContain('never stores your key')
        ->assertExitCode(0);
});

it('names the source when a credential is found, without printing it', function () {
    putenv('ANTHROPIC_API_KEY=sk-ant-super-secret-value');

    $this->artisan('setup', $this->allFlags)
        ->expectsOutputToContain('found (ANTHROPIC_API_KEY)')
        ->doesntExpectOutputToContain('super-secret')
        ->assertExitCode(0);
});

it('reports where it saved the config', function () {
    $this->artisan('setup', $this->allFlags)
        ->expectsOutputToContain($this->settings->path())
        ->assertExitCode(0);
});

it('warns about a corrupt config but still saves over it', function () {
    mkdir($this->directory, 0700, true);
    file_put_contents($this->settings->path(), 'not json');

    $this->artisan('setup', array_merge($this->allFlags, ['--audience' => 'technical']))
        ->expectsOutputToContain('is not valid JSON')
        ->assertExitCode(0);

    expect($this->settings->get('audience'))->toBe('technical');
});
