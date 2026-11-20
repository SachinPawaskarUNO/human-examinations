<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Actions;

use HumanExaminations\HumanExaminations\Models\Examination;
use Illuminate\Support\Arr;

class UpdateExamination
{
    /**
     * Change an examination's details. Only the given fields are updated.
     *
     * @param  array{reference?: string|null, examiner?: string|null, date?: string|null, notes?: string|null}  $attributes
     */
    public function handle(Examination $examination, array $attributes): Examination
    {
        // With a reference format, references are generated and cannot be changed.
        if (filled(config('human-examinations.reference_format'))) {
            $attributes = Arr::except($attributes, 'reference');
        }

        $examination->update($attributes);

        return $examination;
    }
}
