<?php

namespace Plokko\ActivityLogger;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Monolog\Logger;
use Plokko\ActivityLogger\Logging\ActivLogHandler;

class ActivityLoggerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        ///--- Publish config files ---///
        $this->publishes([
            __DIR__ . '/../config/config.php' => config_path('activity-logger.php'),
        ], 'activity-logger:config');

        ///-- Register custom Log Driver ---///
        Log::extend('activlog', function ($app, array $config) {
            $logger = new Logger('activlog');

            $logger->pushHandler(new ActivLogHandler(
                endpoint: $config['endpoint'] ?? 'http://localhost',
                token: $config['token'] ?? '',
                timeout: $config['timeout'] ?? 2,
            ));

            return $logger;
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Merge default config ///
        $this->mergeConfigFrom(
            __DIR__ . '/../config/config.php',
            'activity-logger'
        );

        ///--- Facade accessor ---///
        $this->app->singleton(ActivityLogger::class, function ($app) {
            return new ActivityLogger($app->config->get('activity-logger', []));
        });
    }

    public function provides()
    {
        return [
            ActivityLogger::class,
        ];
    }
}
