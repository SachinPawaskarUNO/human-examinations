<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Actions;

use HumanExaminations\HumanExaminations\Models\ExaminationObservation;

class RestoreObservation
{
    public function handle(ExaminationObservation $observation): ExaminationObservation
    {
        $observation->restore();

        return $observation;
    }
}
