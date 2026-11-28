<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Contracts;

use HumanExaminations\HumanExaminations\Models\HumanExamination;

/** Reads an examination's person name from the host app, e.g. through its own relationship. */
interface ExaminationResolvesPersonName
{
    public function personName(HumanExamination $examination): ?string;
}
