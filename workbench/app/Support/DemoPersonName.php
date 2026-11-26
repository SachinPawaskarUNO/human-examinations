<?php

namespace Workbench\App\Support;

use HumanExaminations\HumanExaminations\Contracts\ResolvesPersonName;
use HumanExaminations\HumanExaminations\Models\Examination;

// Reads the name through the demo's person relationship, as a host app would with its own records.
class DemoPersonName implements ResolvesPersonName
{
    public function personName(Examination $examination): ?string
    {
        return $examination->person?->name;
    }
}
