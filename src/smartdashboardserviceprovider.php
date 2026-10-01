<?php

namespace AuraTech\SmartDashboard;

use AuraTech\SmartDashboard\Console\DoctorCommand;
use AuraTech\SmartDashboard\Support\LaravelConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

class SmartDashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/smart-dashboard.php', 'smart-dashboard');

        $this->app->singleton(DashboardService::class, function ($app) {
            $cfg = $app['config']->get('smart-dashboard');
            $store = $cfg['cache_store'] ?? null;
            $cache = function (string $key, int $ttl, callable $compute) use ($store) {
                $repo = Cache::store($store);
                $hit = $repo->get($key);
                if ($hit !== null) return $hit;
                $value = $compute();
                if (($value['ok'] ?? false) === true) $repo->put($key, $value, $ttl);   // never cache errors
                return $value;
            };
            return new DashboardService(new LaravelConnection($cfg['connection'] ?? null), $cfg, $cache);
        });
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'smart-dashboard');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([DoctorCommand::class]);
            $this->publishes([__DIR__ . '/../config/smart-dashboard.php' => config_path('smart-dashboard.php')], 'smart-dashboard-config');
            $this->publishes([__DIR__ . '/../resources/views' => resource_path('views/vendor/smart-dashboard')], 'smart-dashboard-views');
        }
    }
}
