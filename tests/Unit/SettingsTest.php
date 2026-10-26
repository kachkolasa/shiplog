<?php

use App\Services\Settings;

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/shiplog-settings-'.bin2hex(random_bytes(6));
    $this->settings = new Settings($this->directory);
});

afterEach(function () {
    if (is_dir($this->directory)) {
        array_map('unlink', glob($this->directory.'/*') ?: []);
        rmdir($this->directory);
    }
});

it('reports the config path inside its directory', function () {
    expect($this->settings->path())->toBe($this->directory.'/config.json');
});

it('returns defaults before anything is saved', function () {
    expect($this->settings->exists())->toBeFalse()
        ->and($this->settings->all())->toBe(Settings::DEFAULTS);
});

it('creates the directory and the file on save', function () {
    $this->settings->save(['audience' => 'technical']);

    expect($this->settings->exists())->toBeTrue()
        ->and($this->settings->get('audience'))->toBe('technical');
});

it('fills omitted keys with their defaults so the file is always whole', function () {
    $this->settings->save(['audience' => 'technical']);

    $stored = json_decode((string) file_get_contents($this->settings->path()), true);

    expect(array_keys($stored))->toEqualCanonicalizing(array_keys(Settings::DEFAULTS))
        ->and($stored['tone'])->toBe(Settings::DEFAULTS['tone'])
        ->and($stored['max_items'])->toBe(Settings::DEFAULTS['max_items']);
});

it('writes keys in a stable order', function () {
    $this->settings->save(['tone' => 'neutral', 'audience' => 'technical', 'max_items' => 3]);

    $keys = array_keys(json_decode((string) file_get_contents($this->settings->path()), true));

    expect($keys)->toBe(['audience', 'max_items', 'tone']);
});

it('locks down the file and directory permissions', function () {
    $this->settings->save(['audience' => 'technical']);

    expect(substr(sprintf('%o', fileperms($this->settings->path())), -4))->toBe('0600')
        ->and(substr(sprintf('%o', fileperms($this->directory)), -4))->toBe('0700');
});

it('leaves no temporary files behind', function () {
    $this->settings->save(['audience' => 'technical']);

    expect(glob($this->directory.'/config.json.*'))->toBe([]);
});

it('replaces previous values rather than accumulating them', function () {
    $this->settings->save(['audience' => 'technical', 'max_items' => 3]);
    $this->settings->save(['audience' => 'non-technical']);

    expect($this->settings->get('audience'))->toBe('non-technical')
        // max_items was omitted the second time, so it returns to its default.
        ->and($this->settings->get('max_items'))->toBe(Settings::DEFAULTS['max_items']);
});

it('returns a fallback for an unknown key', function () {
    expect($this->settings->get('nope', 'fallback'))->toBe('fallback')
        ->and($this->settings->get('nope'))->toBeNull();
});

it('refuses to guess when the file is not valid JSON', function () {
    mkdir($this->directory, 0700, true);
    file_put_contents($this->settings->path(), '{ this is not json');

    expect(fn () => $this->settings->all())
        ->toThrow(RuntimeException::class, 'is not valid JSON');
});

it('can still save over a corrupt file', function () {
    mkdir($this->directory, 0700, true);
    file_put_contents($this->settings->path(), 'broken');

    $this->settings->save(['audience' => 'technical']);

    expect($this->settings->get('audience'))->toBe('technical');
});
