<?php

namespace Workbench\App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // `npm run dev` writes the Vite dev server URL here (see vite.config.js).
        Vite::useHotFile(dirname(__DIR__, 2).'/hot');

        // After "New Examination", the package redirects to this route (see routes/web.php).
        config(['human-examinations.workspace_route' => 'workbench.examinations.show']);
    }
}
