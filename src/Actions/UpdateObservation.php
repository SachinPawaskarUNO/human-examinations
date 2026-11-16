<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Actions;

use HumanExaminations\HumanExaminations\Models\Observation;
use Illuminate\Support\Arr;

class UpdateObservation
{
    /** @param array<string, mixed> $attributes */
    public function handle(Observation $observation, array $attributes): Observation
    {
        $observation->update(Arr::except($attributes, ['id', 'examination_id', 'number']));

        return $observation;
    }
}
