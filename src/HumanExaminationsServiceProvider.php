<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations;

use HumanExaminations\HumanExaminations\Console\Commands\HumanExaminationsCommand;
use Illuminate\Support\ServiceProvider;

class HumanExaminationsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/human-examinations.php', 'human-examinations');

        $this->app->singleton(HumanExaminations::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/human-examinations.php');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'human-examinations');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'human-examinations');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/human-examinations.php' => config_path('human-examinations.php'),
        ], ['human-examinations', 'human-examinations-config']);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/human-examinations'),
        ], ['human-examinations', 'human-examinations-views']);

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/human-examinations'),
        ], ['human-examinations', 'human-examinations-lang']);

        $this->publishes([
            __DIR__.'/../public' => public_path('vendor/human-examinations'),
        ], ['human-examinations', 'human-examinations-assets']);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['human-examinations', 'human-examinations-migrations']);

        $this->commands([
            HumanExaminationsCommand::class,
        ]);
    }
}
