<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Actions;

use HumanExaminations\HumanExaminations\Models\HumanExamination;
use Illuminate\Support\Arr;

class UpdateExamination
{
    /**
     * Change an examination's details. Only the given fields are updated.
     *
     * @param  array{reference?: string|null, examiner?: string|null, person_name?: string|null, date?: string|null, notes?: string|null}  $attributes
     */
    public function handle(HumanExamination $examination, array $attributes): HumanExamination
    {
        // Person names are saved only when users type them.
        if (config('human-examinations.person_name') !== 'typed') {
            $attributes = Arr::except($attributes, 'person_name');
        }

        // With a reference format, references are generated and cannot be changed.
        if (filled(config('human-examinations.reference_format'))) {
            $attributes = Arr::except($attributes, 'reference');
        }

        $examination->update($attributes);

        return $examination;
    }
}
