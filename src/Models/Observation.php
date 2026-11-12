<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $examination_id
 * @property int $number
 * @property string $type
 * @property string|null $label
 * @property string|null $region
 * @property string|null $description
 * @property string|null $length_mm
 * @property string|null $width_mm
 * @property string|null $sketch_id
 * @property string|null $x
 * @property string|null $y
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Examination|null $examination
 */
class Observation extends Model
{
    use SoftDeletes;

    protected $table = 'human_examination_observations';

    protected $fillable = [
        'examination_id', 'number', 'type', 'label', 'region', 'description',
        'length_mm', 'width_mm', 'sketch_id', 'x', 'y',
    ];

    protected $attributes = ['type' => 'Unspecified'];

    protected static function booted(): void
    {
        static::saving(function (Observation $observation): void {
            // Validate raw values before decimal and integer casts can round invalid input.
            $attributes = $observation->getAttributes();
            $number = filter_var($attributes['number'] ?? null, FILTER_VALIDATE_INT);

            if ($number === false || $number < 1 || $number > 4294967295) {
                throw new DomainException('Observation numbers must be positive unsigned integers.');
            }

            foreach (['length_mm', 'width_mm'] as $measurement) {
                $value = $attributes[$measurement] ?? null;

                if ($value !== null && (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0 || (float) $value > 99999999.99)) {
                    throw new DomainException('Observation measurements must be finite numbers between 0 and 99999999.99 millimetres.');
                }
            }

            $sketch = $attributes['sketch_id'] ?? null;
            $x = $attributes['x'] ?? null;
            $y = $attributes['y'] ?? null;

            if ($sketch === null && $x === null && $y === null) {
                return;
            }

            if (! is_string($sketch) || trim($sketch) === '' || $x === null || $y === null) {
                throw new DomainException('A sketch location requires a sketch identifier and both coordinates.');
            }

            foreach ([$x, $y] as $coordinate) {
                if (! is_numeric($coordinate) || ! is_finite((float) $coordinate) || (float) $coordinate < 0 || (float) $coordinate > 1) {
                    throw new DomainException('Sketch coordinates must be normalized between zero and one.');
                }
            }
        });
    }

    /** @return BelongsTo<Examination, $this> */
    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'examination_id' => 'integer',
            'number' => 'integer',
            'length_mm' => 'decimal:2',
            'width_mm' => 'decimal:2',
            'x' => 'decimal:6',
            'y' => 'decimal:6',
        ];
    }
}
