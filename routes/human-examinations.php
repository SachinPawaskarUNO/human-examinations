<?php

declare(strict_types=1);

use HumanExaminations\HumanExaminations\Http\Controllers\ExaminationController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')
    ->prefix('human-examinations')
    ->name('human-examinations.')
    ->group(function (): void {
        Route::post('examinations', [ExaminationController::class, 'store'])->name('examinations.store');
        Route::patch('examinations/{examination}', [ExaminationController::class, 'update'])->name('examinations.update');
    });
