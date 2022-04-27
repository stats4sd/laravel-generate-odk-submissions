<?php

namespace Stats4sd\GenerateOdkSubmissions;

use Illuminate\Support\ServiceProvider;
use Stats4sd\GenerateOdkSubmissions\Console\Commands\GenerateSubmissionRecords;

class GenerateOdkSubmissionsServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot(): void
    {
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'stats4sd');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'stats4sd');
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-generate-odk-submissions.php', 'laravel-generate-odk-submissions');

        // Register the service the package provides.
        $this->app->singleton('laravel-generate-odk-submissions', function ($app) {
            return new GenerateOdkSubmissions;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['laravel-generate-odk-submissions'];
    }

    /**
     * Console-specific booting.
     *
     * @return void
     */
    protected function bootForConsole(): void
    {
        // Publishing the configuration file.
        $this->publishes([
            __DIR__.'/../config/laravel-generate-odk-submissions.php' => config_path('laravel-generate-odk-submissions.php'),
        ], 'laravel-generate-odk-submissions.config');

        // Publishing the views.
        /*$this->publishes([
            __DIR__.'/../resources/views' => base_path('resources/views/vendor/stats4sd'),
        ], 'laravel-generate-odk-submissions.views');*/

        // Publishing assets.
        /*$this->publishes([
            __DIR__.'/../resources/assets' => public_path('vendor/stats4sd'),
        ], 'laravel-generate-odk-submissions.views');*/

        // Publishing the translation files.
        /*$this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/stats4sd'),
        ], 'laravel-generate-odk-submissions.views');*/

        // Registering package commands.
        $this->commands([
            GenerateSubmissionRecords::class,
        ]);
    }
}
