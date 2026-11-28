<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Http\Controllers;

use HumanExaminations\HumanExaminations\Actions\CreateObservation;
use HumanExaminations\HumanExaminations\Actions\DeleteObservation;
use HumanExaminations\HumanExaminations\Actions\RestoreObservation;
use HumanExaminations\HumanExaminations\Actions\UpdateObservation;
use HumanExaminations\HumanExaminations\Models\ExaminationObservation;
use HumanExaminations\HumanExaminations\Models\HumanExamination;
use HumanExaminations\HumanExaminations\Support\RecordRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class ExaminationObservationController
{
    public function store(Request $request, HumanExamination $examination, CreateObservation $createObservation): RedirectResponse
    {
        Gate::authorize('update', $examination);

        $createObservation->handle($examination, $this->validated($request));

        return back();
    }

    public function update(Request $request, HumanExamination $examination, ExaminationObservation $observation, UpdateObservation $updateObservation): RedirectResponse
    {
        Gate::authorize('update', $examination);

        $updateObservation->handle($observation, $this->validated($request, $observation));

        return back();
    }

    public function destroy(HumanExamination $examination, ExaminationObservation $observation, DeleteObservation $deleteObservation): RedirectResponse
    {
        Gate::authorize('update', $examination);

        $deleteObservation->handle($observation);

        return back();
    }

    public function restore(HumanExamination $examination, string $observation, RestoreObservation $restoreObservation): RedirectResponse
    {
        Gate::authorize('update', $examination);

        // Resolve the deleted child through its active parent; never broaden parent binding.
        $deleted = $examination->observations()->onlyTrashed()->findOrFail($observation);
        $restoreObservation->handle($deleted);

        return back();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?ExaminationObservation $observation = null): array
    {
        $rules = RecordRules::observation();
        $rules['type'] = ['sometimes', ...$rules['type']];
        $attributes = $request->validate([
            ...$rules,
            'id' => ['missing'],
            'examination_id' => ['missing'],
            'number' => ['missing'],
        ]);
        RecordRules::rejectBlankValues($attributes, RecordRules::OBSERVATION_NONBLANK_FIELDS);

        // PATCH may omit location fields, so validate completeness against the stored values.
        $location = array_replace(
            $observation?->only(['sketch_id', 'x', 'y']) ?? ['sketch_id' => null, 'x' => null, 'y' => null],
            Arr::only($attributes, ['sketch_id', 'x', 'y']),
        );
        Validator::make($location, RecordRules::location())->validate();

        return $attributes;
    }
}
