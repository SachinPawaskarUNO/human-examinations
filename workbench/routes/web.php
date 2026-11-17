<?php

use HumanExaminations\HumanExaminations\Models\Examination;
use HumanExaminations\HumanExaminations\Models\Observation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

$observationTypes = fn () => array_map(fn (BackedEnum $type) => $type->value, Observation::typeEnum()::cases());
$examinationRouteName = config('human-examinations.routes.name_prefix', 'human-examinations.');
$examinationRoutes = fn (?Examination $examination = null): array => [
    'store' => route($examinationRouteName.'examinations.store'),
    'update' => $examination === null ? null : route($examinationRouteName.'examinations.update', $examination),
];

// Empty workspace, before an examination has been created.
Route::get('/', fn () => Inertia::render('Workspace', [
    'observationTypes' => $observationTypes(),
    'examination' => null,
    'examinationRoutes' => $examinationRoutes(),
    'observations' => [],
    'observationRoutes' => ['store' => null, 'update' => [], 'destroy' => [], 'restore' => []],
]));

// The workspace for one examination. The package redirects here after creating one
// (see `workspace_route`, set in WorkbenchServiceProvider).
Route::get('/examinations/{examination}', function (Examination $examination) use ($observationTypes, $examinationRoutes, $examinationRouteName) {
    Gate::authorize('view', $examination);
    $observations = $examination->observations()->orderBy('number')->get();

    return Inertia::render('Workspace', [
        'observationTypes' => $observationTypes(),
        'examination' => $examination,
        'examinationRoutes' => $examinationRoutes($examination),
        'observations' => $observations,
        'observationRoutes' => [
            'store' => route($examinationRouteName.'observations.store', $examination),
            'restore' => $examination->observations()->withTrashed()->get()->mapWithKeys(fn (Observation $observation) => [
                $observation->id => route($examinationRouteName.'observations.restore', [$examination, $observation]),
            ]),
            'update' => $observations->mapWithKeys(fn (Observation $observation) => [
                $observation->id => route($examinationRouteName.'observations.update', [$examination, $observation]),
            ]),
            'destroy' => $observations->mapWithKeys(fn (Observation $observation) => [
                $observation->id => route($examinationRouteName.'observations.destroy', [$examination, $observation]),
            ]),
        ],
    ]);
})->name('workbench.examinations.show');
