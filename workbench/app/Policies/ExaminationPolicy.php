<?php

declare(strict_types=1);

namespace Workbench\App\Policies;

use HumanExaminations\HumanExaminations\Models\Examination;
use Illuminate\Contracts\Auth\Authenticatable;

// Guest access is intentional for the standalone development workbench only.
class ExaminationPolicy
{
    public function view(?Authenticatable $user, Examination $examination): bool
    {
        return true;
    }

    public function create(?Authenticatable $user): bool
    {
        return true;
    }

    public function update(?Authenticatable $user, Examination $examination): bool
    {
        return true;
    }
}
