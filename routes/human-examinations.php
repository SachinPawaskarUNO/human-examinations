<?php

declare(strict_types=1);

use HumanExaminations\HumanExaminations\Http\Controllers\ExaminationController;
use HumanExaminations\HumanExaminations\Http\Controllers\ExaminationTransferController;
use HumanExaminations\HumanExaminations\Http\Controllers\ObservationController;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

if (! config('human-examinations.routes.enabled', false)) {
    return;
}

Route::middleware([
    ...(array) config('human-examinations.routes.middleware', ['web', 'auth']),
    SubstituteBindings::class,
])
    ->prefix(config('human-examinations.routes.prefix', 'human-examinations'))
    ->name(config('human-examinations.routes.name_prefix', 'human-examinations.'))
    ->scopeBindings()
    ->group(function (): void {
        Route::post('examinations', [ExaminationController::class, 'store'])->name('examinations.store');

        if (config('human-examinations.features.import', true)) {
            Route::post('examinations/import', [ExaminationTransferController::class, 'import'])->name('examinations.import');
        }

        if (config('human-examinations.features.export', true)) {
            Route::get('examinations/{examination}/export', [ExaminationTransferController::class, 'export'])->name('examinations.export');
        }

        Route::patch('examinations/{examination}', [ExaminationController::class, 'update'])->name('examinations.update');

        Route::post('examinations/{examination}/observations', [ObservationController::class, 'store'])->name('observations.store');
        Route::patch('examinations/{examination}/observations/{observation}', [ObservationController::class, 'update'])->name('observations.update');
        Route::delete('examinations/{examination}/observations/{observation}', [ObservationController::class, 'destroy'])->name('observations.destroy');
        Route::post('examinations/{examination}/observations/{observation}/restore', [ObservationController::class, 'restore'])->name('observations.restore');
    });
