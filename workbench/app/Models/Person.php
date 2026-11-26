<?php

namespace Workbench\App\Models;

use HumanExaminations\HumanExaminations\Models\Examination;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// A stand-in for a host app's own record=
#[Fillable(['name'])]
class Person extends Model
{
    /** @return BelongsTo<Examination, $this> */
    public function humanExamination(): BelongsTo
    {
        return $this->belongsTo(Examination::class, 'human_examination_id');
    }
}
