<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Actions;

use HumanExaminations\HumanExaminations\Models\Examination;

class UpdateExamination
{
    /**
     * Change an examination's details. Only the given fields are updated.
     *
     * @param  array{reference?: string|null, examiner?: string|null, date?: string|null, notes?: string|null}  $attributes
     */
    public function handle(Examination $examination, array $attributes): Examination
    {
        $examination->update($attributes);

        return $examination;
    }
}
