<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Models;

use HumanExaminations\HumanExaminations\Support\RecordData;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $reference
 * @property string|null $examiner
 * @property string|null $person_name
 * @property Carbon|null $date
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, ExaminationObservation> $observations
 */
class HumanExamination extends Model
{
    use SoftDeletes;

    protected $table = 'human_examinations';

    protected $fillable = ['reference', 'examiner', 'person_name', 'date', 'notes'];

    /** @return HasMany<ExaminationObservation, $this> */
    public function observations(): HasMany
    {
        return $this->hasMany(ExaminationObservation::class, 'examination_id');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        // Follow the `person_name` config.
        return [
            ...Arr::except(parent::toArray(), 'person_name'),
            ...RecordData::personName($this),
        ];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }
}
