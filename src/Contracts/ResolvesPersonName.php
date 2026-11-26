<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Contracts;

use HumanExaminations\HumanExaminations\Models\Examination;

/** Reads an examination's person name from the host app, e.g. through its own relationship. */
interface ResolvesPersonName
{
    public function personName(Examination $examination): ?string;
}
