<?php

namespace Workbench\App\Providers;

use HumanExaminations\HumanExaminations\HumanExaminationsServiceProvider;
use HumanExaminations\HumanExaminations\Models\HumanExamination;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Inertia\Middleware as InertiaMiddleware;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Workbench\App\Models\Person;
use Workbench\App\Policies\ExaminationPolicy;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->register(HumanExaminationsServiceProvider::class);
        $this->app->register(InertiaServiceProvider::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Gate::policy(HumanExamination::class, ExaminationPolicy::class);

        // Lets the demo read `$examination->person`, as a host app would link its own record.
        HumanExamination::resolveRelationUsing('person', fn (HumanExamination $examination): HasOne => $examination->hasOne(Person::class, 'human_examination_id'));
        $this->app->make(Kernel::class)->appendMiddlewareToGroup('web', InertiaMiddleware::class);

        // `npm run dev` writes the Vite dev server URL here (see vite.config.js).
        Vite::useHotFile(dirname(__DIR__, 2).'/hot');
    }
}
