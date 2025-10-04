<?php

namespace App\Providers;

use App\Contracts\ReadinessCheckerInterface;
use App\Services\ReadinessChecker;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            ReadinessCheckerInterface::class,
            fn () => new ReadinessChecker()
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
