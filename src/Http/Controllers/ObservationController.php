<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Http\Controllers;

use HumanExaminations\HumanExaminations\Actions\CreateObservation;
use HumanExaminations\HumanExaminations\Actions\DeleteObservation;
use HumanExaminations\HumanExaminations\Actions\RestoreObservation;
use HumanExaminations\HumanExaminations\Actions\UpdateObservation;
use HumanExaminations\HumanExaminations\Models\Examination;
use HumanExaminations\HumanExaminations\Models\Observation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ObservationController
{
    public function store(Request $request, Examination $examination, CreateObservation $createObservation): RedirectResponse
    {
        Gate::authorize('update', $examination);

        $createObservation->handle($examination, $this->validated($request));

        return back();
    }

    public function update(Request $request, Examination $examination, Observation $observation, UpdateObservation $updateObservation): RedirectResponse
    {
        Gate::authorize('update', $examination);

        $updateObservation->handle($observation, $this->validated($request, $observation));

        return back();
    }

    public function destroy(Examination $examination, Observation $observation, DeleteObservation $deleteObservation): RedirectResponse
    {
        Gate::authorize('update', $examination);

        $deleteObservation->handle($observation);

        return back();
    }

    public function restore(Examination $examination, string $observation, RestoreObservation $restoreObservation): RedirectResponse
    {
        Gate::authorize('update', $examination);

        // Resolve the deleted child through its active parent; never broaden parent binding.
        $deleted = $examination->observations()->onlyTrashed()->findOrFail($observation);
        $restoreObservation->handle($deleted);

        return back();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Observation $observation = null): array
    {
        $attributes = $request->validate([
            'id' => ['missing'],
            'examination_id' => ['missing'],
            'number' => ['missing'],
            'type' => ['sometimes', 'required', Rule::enum(Observation::typeEnum())],
            'label' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'length_mm' => ['nullable', 'numeric', 'between:0,99999999.99'],
            'width_mm' => ['nullable', 'numeric', 'between:0,99999999.99'],
            'sketch_id' => ['nullable', 'string', 'max:255'],
            'x' => ['nullable', 'numeric', 'between:0,1'],
            'y' => ['nullable', 'numeric', 'between:0,1'],
        ]);

        // PATCH may omit location fields, so validate completeness against the stored values.
        $location = array_replace(
            $observation?->only(['sketch_id', 'x', 'y']) ?? ['sketch_id' => null, 'x' => null, 'y' => null],
            Arr::only($attributes, ['sketch_id', 'x', 'y']),
        );
        Validator::make($location, [
            'sketch_id' => ['nullable', 'required_with:x,y'],
            'x' => ['nullable', 'required_with:sketch_id,y'],
            'y' => ['nullable', 'required_with:sketch_id,x'],
        ])->validate();

        return $attributes;
    }
}
