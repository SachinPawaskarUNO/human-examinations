<?php

namespace Workbench\App\Providers;

use HumanExaminations\HumanExaminations\HumanExaminationsServiceProvider;
use HumanExaminations\HumanExaminations\Models\Examination;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Inertia\Middleware as InertiaMiddleware;
use Inertia\ServiceProvider as InertiaServiceProvider;
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

        // The standalone demo opts in without requiring a host authentication setup.
        config([
            'human-examinations.routes.enabled' => true,
            'human-examinations.routes.middleware' => ['web'],
        ]);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Gate::policy(Examination::class, ExaminationPolicy::class);
        $this->app->make(Kernel::class)->appendMiddlewareToGroup('web', InertiaMiddleware::class);

        // `npm run dev` writes the Vite dev server URL here (see vite.config.js).
        Vite::useHotFile(dirname(__DIR__, 2).'/hot');

        // After "New Examination", the package redirects to this route (see routes/web.php).
        config(['human-examinations.workspace_route' => 'workbench.examinations.show']);
    }
}
