<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Actions;

use HumanExaminations\HumanExaminations\Models\Observation;

class RestoreObservation
{
    public function handle(Observation $observation): Observation
    {
        $observation->restore();

        return $observation;
    }
}
