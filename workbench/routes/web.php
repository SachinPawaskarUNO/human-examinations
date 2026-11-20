<?php

use HumanExaminations\HumanExaminations\Models\Examination;
use HumanExaminations\HumanExaminations\Models\Observation;
use HumanExaminations\HumanExaminations\Support\RecordData;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

$observationTypes = fn () => array_map(fn (BackedEnum $type) => $type->value, Observation::typeEnum()::cases());
$examinationRouteName = config('human-examinations.routes.name_prefix', 'human-examinations.');
$examinationRoutes = fn (?Examination $examination = null): array => [
    'store' => route($examinationRouteName.'examinations.store'),
    'import' => Route::has($examinationRouteName.'examinations.import') ? route($examinationRouteName.'examinations.import') : null,
    'export' => $examination === null || ! Route::has($examinationRouteName.'examinations.export') ? [] : [
        'json' => route($examinationRouteName.'examinations.export', ['examination' => $examination, 'format' => 'json']),
        'csv' => route($examinationRouteName.'examinations.export', ['examination' => $examination, 'format' => 'csv']),
    ],
    'update' => $examination === null ? null : route($examinationRouteName.'examinations.update', $examination),
];
$transferLimits = fn (): array => [
    'maxFileBytes' => (int) config('human-examinations.transfer.max_file_kilobytes', 10240) * 1024,
    'maxObservations' => (int) config('human-examinations.transfer.max_observations', 10000),
];
$workspaceOptions = fn (): array => [
    'features' => (object) config('human-examinations.features', []),
    'markerColors' => (object) config('human-examinations.marker_colors', []),
    'workspaceDefaults' => (object) config('human-examinations.workspace', []),
];

// Empty workspace, before an examination has been created.
Route::get('/', fn () => Inertia::render('Workspace', [
    ...$workspaceOptions(),
    'observationTypes' => $observationTypes(),
    'examination' => null,
    'examinationRoutes' => $examinationRoutes(),
    'transferLimits' => $transferLimits(),
    'observations' => [],
    'observationRoutes' => ['store' => null, 'update' => [], 'destroy' => [], 'restore' => []],
]));

// The workspace for one examination. The package redirects here after creating one
// (see `workspace_route`, set in WorkbenchServiceProvider).
Route::get('/examinations/{examination}', function (Examination $examination) use ($observationTypes, $examinationRoutes, $examinationRouteName, $transferLimits, $workspaceOptions) {
    Gate::authorize('view', $examination);
    $observations = $examination->observations()->orderBy('number')->get();

    return Inertia::render('Workspace', [
        ...$workspaceOptions(),
        'observationTypes' => $observationTypes(),
        'examination' => ['id' => $examination->id, ...RecordData::examination($examination)],
        'examinationRoutes' => $examinationRoutes($examination),
        'transferLimits' => $transferLimits(),
        'observations' => $observations->map(fn (Observation $observation): array => [
            'id' => $observation->id,
            'examination_id' => $observation->examination_id,
            ...RecordData::observation($observation),
        ])->all(),
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
