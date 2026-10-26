<?php

namespace App\Providers;

use App\Contracts\BriefWriter;
use App\Services\FakeBriefWriter;
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
        // TODO: Change to API brief writer once done
        $this->app->bind(BriefWriter::class, FakeBriefWriter::class);
    }
}
