<?php

namespace Plokko\ActivityLogger;

use Illuminate\Support\ServiceProvider;

class ActivityLoggerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        /*//--- Publish migrations ---///
        $this->publishesMigrations([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ]);
        $this->loadMigrationsFrom(
            __DIR__ . '/../database/migrations'
        );
        ///--- Translations ---///
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'activity-logger');
        */
        ///--- Publish config/translation files ---///
        $this->publishes([
            __DIR__ . '/../config/config.php' => config_path('activity-logger.php'),
        ], 'activity-logger:config');
        /*
        $this->publishes([
            __DIR__ . '/../lang' => $this->app->langPath('vendor/activity-logger'),
        ], 'activity-logger:lang');
        ///--- Console commands ---///
        if ($this->app->runningInConsole())
        {
            $this->commands([
                GenerateCommand::class,
            ]);
        }
        */
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
