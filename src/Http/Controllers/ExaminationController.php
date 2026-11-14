<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Http\Controllers;

use HumanExaminations\HumanExaminations\Actions\CreateExamination;
use HumanExaminations\HumanExaminations\Actions\UpdateExamination;
use HumanExaminations\HumanExaminations\Models\Examination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ExaminationController
{
    public function store(Request $request, CreateExamination $createExamination): RedirectResponse
    {
        $examination = $createExamination->handle($this->validated($request));

        // Send the user to the app's workspace page for the new examination, if one is configured.
        $route = config('human-examinations.workspace_route');

        return is_string($route)
            ? redirect()->route($route, $examination)
            : back();
    }

    public function update(Request $request, Examination $examination, UpdateExamination $updateExamination): RedirectResponse
    {
        $updateExamination->handle($examination, $this->validated($request));

        return back();
    }

    /**
     * The examination fields from the request. All are optional.
     *
     * @return array{reference?: string|null, examiner?: string|null, date?: string|null, notes?: string|null}
     */
    private function validated(Request $request): array
    {
        /** @var array{reference?: string|null, examiner?: string|null, date?: string|null, notes?: string|null} */
        return $request->validate([
            'reference' => ['nullable', 'string', 'max:255'],
            'examiner' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
