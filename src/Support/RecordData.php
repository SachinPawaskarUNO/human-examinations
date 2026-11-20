<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Support;

use HumanExaminations\HumanExaminations\Models\Examination;
use HumanExaminations\HumanExaminations\Models\Observation;

/** Explicit field projections shared by the workspace and portable documents. */
class RecordData
{
    public const array EXAMINATION_FIELDS = ['reference', 'examiner', 'date', 'notes'];

    public const array OBSERVATION_FIELDS = ['number', 'type', 'label', 'region', 'description', 'length_mm', 'width_mm', 'sketch_id', 'x', 'y'];

    /** @return array<string, mixed> */
    public static function examination(Examination $examination): array
    {
        return [
            ...$examination->only(self::EXAMINATION_FIELDS),
            'date' => $examination->date?->format('Y-m-d'),
        ];
    }

    /** @return array<string, mixed> */
    public static function observation(Observation $observation): array
    {
        return [
            ...$observation->only(self::OBSERVATION_FIELDS),
            'type' => (string) $observation->type->value,
        ];
    }
}
