<?php

namespace App\Providers;

use App\Services\AIDetectionService;
use App\Services\AnalysisOrchestrator;
use App\Services\GitHubService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(GitHubService::class);
        $this->app->singleton(AIDetectionService::class);
        $this->app->singleton(AnalysisOrchestrator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
    }
}
