<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Support;

use HumanExaminations\HumanExaminations\Contracts\ExaminationResolvesPersonName;
use HumanExaminations\HumanExaminations\Models\ExaminationObservation;
use HumanExaminations\HumanExaminations\Models\HumanExamination;
use InvalidArgumentException;

/** Explicit field projections shared by the workspace and portable documents. */
class RecordData
{
    public const array EXAMINATION_FIELDS = ['reference', 'examiner', 'date', 'notes'];

    public const array OBSERVATION_FIELDS = ['number', 'type', 'label', 'region', 'description', 'length_mm', 'width_mm', 'sketch_id', 'x', 'y'];

    /** @return array<string, mixed> */
    public static function examination(HumanExamination $examination): array
    {
        return [
            ...$examination->only(self::EXAMINATION_FIELDS),
            'date' => $examination->date?->format('Y-m-d'),
            ...self::personName($examination),
        ];
    }

    /** @return array<string, mixed> */
    public static function observation(ExaminationObservation $observation): array
    {
        return [
            ...$observation->only(self::OBSERVATION_FIELDS),
            'type' => (string) $observation->type->value,
        ];
    }

    /**
     * The person name from the configured `person_name` source, or nothing when names are off.
     *
     * @return array{person_name?: string|null}
     */
    public static function personName(HumanExamination $examination): array
    {
        $source = config('human-examinations.person_name');

        if ($source === 'typed') {
            return ['person_name' => $examination->person_name];
        }

        if (! is_string($source)) {
            return [];
        }

        $resolver = app($source);

        if (! $resolver instanceof ExaminationResolvesPersonName) {
            throw new InvalidArgumentException($source.' must implement '.ExaminationResolvesPersonName::class.'.');
        }

        return ['person_name' => $resolver->personName($examination)];
    }
}
