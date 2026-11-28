<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Actions;

use HumanExaminations\HumanExaminations\Models\ExaminationObservation;
use Illuminate\Support\Arr;

class UpdateObservation
{
    /** @param array<string, mixed> $attributes */
    public function handle(ExaminationObservation $observation, array $attributes): ExaminationObservation
    {
        $observation->update(Arr::except($attributes, ['id', 'examination_id', 'number']));

        return $observation;
    }
}
