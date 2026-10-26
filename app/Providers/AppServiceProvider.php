<?php

namespace App\Providers;

use App\Contracts\BriefWriter;
use App\Services\ClaudeBriefWriter;
use App\Services\Credentials;
use App\Services\Settings;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BriefWriter::class, ClaudeBriefWriter::class);

        $this->app->singleton(Settings::class, fn () => new Settings(
            self::configDirectory('shiplog'),
        ));

        $this->app->singleton(Credentials::class, fn () => new Credentials(
            self::configDirectory('anthropic'),
        ));
    }

    private static function configDirectory(string $tool): string
    {
        $xdg = getenv('XDG_CONFIG_HOME');

        if (is_string($xdg) && $xdg !== '') {
            return rtrim($xdg, '/')."/{$tool}";
        }

        $home = getenv('HOME') ?: getenv('USERPROFILE') ?: sys_get_temp_dir();

        return rtrim((string) $home, '/')."/.config/{$tool}";
    }
}
