<?php

namespace Tests;

use LaravelZero\Framework\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Laravel Zero hardcodes config/app.php's env to "development", so
        // runningUnitTests() is false and Illuminate's configurePrompts() leaves
        // Laravel Prompts on its own TTY renderer; under a real terminal that
        // renderer waits for keystrokes and the test blocks forever. Marking the
        // app as testing routes prompts through Symfony's question helper, which
        // expectsQuestion() can drive. It has to run after boot, because the
        // config bootstrapper sets env from config/app.php.
        $this->app->booted(function () {
            $this->app['env'] = 'testing';
        });
    }
}
