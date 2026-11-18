<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Actions;

use HumanExaminations\HumanExaminations\Models\Examination;
use HumanExaminations\HumanExaminations\Models\Observation;
use Illuminate\Support\Arr;

class CreateObservation
{
    /** @param array<string, mixed> $attributes */
    public function handle(Examination $examination, array $attributes = []): Observation
    {
        return $examination->getConnection()->transaction(function () use ($examination, $attributes): Observation {
            // Serialize number allocation on the parent, including its first observation.
            $parent = $examination->newQuery()->whereKey($examination->getKey())->lockForUpdate()->firstOrFail();
            $number = (int) $parent->observations()->withTrashed()->max('number') + 1;

            return $parent->observations()->create([
                ...Arr::except($attributes, ['id', 'examination_id', 'number']),
                'number' => $number,
            ]);
        }, 5);
    }
}
