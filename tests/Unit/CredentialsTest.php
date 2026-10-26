<?php

use App\Services\Credentials;

/**
 * These touch real environment variables, so remember and restore them.
 */
beforeEach(function () {
    $this->saved = [
        'ANTHROPIC_API_KEY' => getenv('ANTHROPIC_API_KEY'),
        'ANTHROPIC_AUTH_TOKEN' => getenv('ANTHROPIC_AUTH_TOKEN'),
    ];

    putenv('ANTHROPIC_API_KEY');
    putenv('ANTHROPIC_AUTH_TOKEN');

    $this->missingDirectory = sys_get_temp_dir().'/shiplog-no-anthropic-'.bin2hex(random_bytes(6));
    $this->credentials = new Credentials($this->missingDirectory);
});

afterEach(function () {
    foreach ($this->saved as $variable => $value) {
        is_string($value) ? putenv("{$variable}={$value}") : putenv($variable);
    }
});

it('reports nothing when there is no key and no profile', function () {
    expect($this->credentials->source())->toBeNull()
        ->and($this->credentials->configured())->toBeFalse();
});

it('detects an exported api key', function () {
    putenv('ANTHROPIC_API_KEY=sk-ant-test-value');

    expect($this->credentials->source())->toBe('ANTHROPIC_API_KEY')
        ->and($this->credentials->configured())->toBeTrue();
});

it('detects an auth token', function () {
    putenv('ANTHROPIC_AUTH_TOKEN=oauth-test-value');

    expect($this->credentials->source())->toBe('ANTHROPIC_AUTH_TOKEN');
});

it('prefers the api key, matching the sdk resolution order', function () {
    putenv('ANTHROPIC_API_KEY=sk-ant-test-value');
    putenv('ANTHROPIC_AUTH_TOKEN=oauth-test-value');

    expect($this->credentials->source())->toBe('ANTHROPIC_API_KEY');
});

it('ignores a variable that is set but empty', function () {
    putenv('ANTHROPIC_API_KEY=');
    putenv('ANTHROPIC_AUTH_TOKEN=   ');

    expect($this->credentials->source())->toBeNull();
});

it('falls back to an ant profile directory', function () {
    $directory = sys_get_temp_dir().'/shiplog-anthropic-'.bin2hex(random_bytes(6));
    mkdir($directory, 0700, true);

    expect((new Credentials($directory))->source())->toContain('ant profile');

    rmdir($directory);
});

it('never exposes the credential value itself', function () {
    putenv('ANTHROPIC_API_KEY=sk-ant-super-secret-value');

    expect($this->credentials->source())->not->toContain('super-secret');
});
