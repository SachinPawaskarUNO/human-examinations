<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $reference
 * @property string|null $examiner
 * @property Carbon|null $date
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Observation> $observations
 */
class Examination extends Model
{
    use SoftDeletes;

    protected $table = 'human_examinations';

    protected $fillable = ['reference', 'examiner', 'date', 'notes'];

    /** @return HasMany<Observation, $this> */
    public function observations(): HasMany
    {
        return $this->hasMany(Observation::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }
}
