<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Actions;

use HumanExaminations\HumanExaminations\Models\Examination;

class CreateExamination
{
    /**
     * Start a new examination. Every field is optional, so it can begin blank.
     *
     * @param  array{reference?: string|null, examiner?: string|null, date?: string|null, notes?: string|null}  $attributes
     */
    public function handle(array $attributes = []): Examination
    {
        return Examination::create($attributes);
    }
}
