<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Actions;

use HumanExaminations\HumanExaminations\Models\Observation;

class DeleteObservation
{
    public function handle(Observation $observation): void
    {
        $observation->delete();
    }
}
