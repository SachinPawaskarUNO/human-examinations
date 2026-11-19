<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations;

use HumanExaminations\HumanExaminations\Actions\CreateExamination;
use HumanExaminations\HumanExaminations\Actions\CreateObservation;
use HumanExaminations\HumanExaminations\Actions\DeleteObservation;
use HumanExaminations\HumanExaminations\Actions\ExportExamination;
use HumanExaminations\HumanExaminations\Actions\ImportExamination;
use HumanExaminations\HumanExaminations\Actions\RestoreObservation;
use HumanExaminations\HumanExaminations\Actions\UpdateExamination;
use HumanExaminations\HumanExaminations\Actions\UpdateObservation;
use HumanExaminations\HumanExaminations\Console\Commands\CreateExaminationCommand;
use HumanExaminations\HumanExaminations\Console\Commands\HumanExaminationsCommand;
use HumanExaminations\HumanExaminations\Console\Commands\InstallCommand;
use HumanExaminations\HumanExaminations\Console\Commands\ListExaminationsCommand;
use HumanExaminations\HumanExaminations\Console\Commands\ShowExaminationCommand;
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

        // Use the app's own action class if the config points at one.
        $this->app->bind(CreateExamination::class, function (): CreateExamination {
            $class = config('human-examinations.actions.create_examination', CreateExamination::class);

            return $class === CreateExamination::class
                ? new CreateExamination
                : app($class);
        });

        $this->app->bind(UpdateExamination::class, function (): UpdateExamination {
            $class = config('human-examinations.actions.update_examination', UpdateExamination::class);

            return $class === UpdateExamination::class
                ? new UpdateExamination
                : app($class);
        });

        $this->app->bind(CreateObservation::class, function (): CreateObservation {
            $class = config('human-examinations.actions.create_observation', CreateObservation::class);

            return $class === CreateObservation::class ? new CreateObservation : app($class);
        });

        $this->app->bind(UpdateObservation::class, function (): UpdateObservation {
            $class = config('human-examinations.actions.update_observation', UpdateObservation::class);

            return $class === UpdateObservation::class ? new UpdateObservation : app($class);
        });

        $this->app->bind(DeleteObservation::class, function (): DeleteObservation {
            $class = config('human-examinations.actions.delete_observation', DeleteObservation::class);

            return $class === DeleteObservation::class ? new DeleteObservation : app($class);
        });
        $this->app->bind(RestoreObservation::class, function (): RestoreObservation {
            $class = config('human-examinations.actions.restore_observation', RestoreObservation::class);

            return $class === RestoreObservation::class ? new RestoreObservation : app($class);
        });
        $this->app->bind(ExportExamination::class, function (): ExportExamination {
            $class = config('human-examinations.actions.export_examination', ExportExamination::class);

            return $class === ExportExamination::class ? new ExportExamination : app($class);
        });
        $this->app->bind(ImportExamination::class, function (): ImportExamination {
            $class = config('human-examinations.actions.import_examination', ImportExamination::class);

            return $class === ImportExamination::class ? new ImportExamination : app($class);
        });
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
            InstallCommand::class,
            CreateExaminationCommand::class,
            ListExaminationsCommand::class,
            ShowExaminationCommand::class,
        ]);
    }
}
