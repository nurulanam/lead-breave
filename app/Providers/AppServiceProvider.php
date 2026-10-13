<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Every queue the app dispatches to, highest priority first.
     */
    public const QUEUES = 'search,enrich,pagespeed,mail,default';

    /**
     * Register any application services.
     *
     * Telescope is a dev-only dependency, so it is only loaded locally and when installed.
     */
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        $this->registerDevWorker();
    }

    /**
     * `composer dev` runs Horizon, which needs Redis, and Laravel's own listener
     * only watches the default queue. Without Redis, run a listener on every
     * queue instead, so searches are processed locally.
     */
    private function registerDevWorker(): void
    {
        if (config('queue.default') === 'redis') {
            return;
        }

        DevCommands::artisan('queue:listen --queue='.self::QUEUES.' --tries=1 --timeout=0', 'worker');
        DevCommands::except('horizon', 'queue');
    }
}
