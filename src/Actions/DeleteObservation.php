<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Actions;

use HumanExaminations\HumanExaminations\Models\ExaminationObservation;

class DeleteObservation
{
    public function handle(ExaminationObservation $observation): void
    {
        $observation->delete();
    }
}
