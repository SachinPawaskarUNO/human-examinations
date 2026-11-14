<?php

use HumanExaminations\HumanExaminations\Models\Examination;
use HumanExaminations\HumanExaminations\Models\Observation;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

$observationTypes = fn () => array_map(fn (BackedEnum $type) => $type->value, Observation::typeEnum()::cases());

// Empty workspace, before an examination has been created.
Route::get('/', fn () => Inertia::render('Workspace', [
    'observationTypes' => $observationTypes(),
    'examination' => null,
]));

// The workspace for one examination. The package redirects here after creating one
// (see `workspace_route`, set in WorkbenchServiceProvider).
Route::get('/examinations/{examination}', fn (Examination $examination) => Inertia::render('Workspace', [
    'observationTypes' => $observationTypes(),
    'examination' => $examination,
]))->name('workbench.examinations.show');
