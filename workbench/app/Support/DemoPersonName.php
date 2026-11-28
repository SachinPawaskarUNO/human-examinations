<?php

namespace Workbench\App\Support;

use HumanExaminations\HumanExaminations\Contracts\ExaminationResolvesPersonName;
use HumanExaminations\HumanExaminations\Models\HumanExamination;

// Reads the name through the demo's person relationship, as a host app would with its own records.
class DemoPersonName implements ExaminationResolvesPersonName
{
    public function personName(HumanExamination $examination): ?string
    {
        return $examination->person?->name;
    }
}
