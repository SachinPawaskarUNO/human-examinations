<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Support;

use HumanExaminations\HumanExaminations\Contracts\ResolvesPersonName;
use HumanExaminations\HumanExaminations\Models\Examination;
use HumanExaminations\HumanExaminations\Models\Observation;
use InvalidArgumentException;

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
            ...self::personName($examination),
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

    /**
     * The person name from the configured `person_name` source, or nothing when names are off.
     *
     * @return array{person_name?: string|null}
     */
    private static function personName(Examination $examination): array
    {
        $source = config('human-examinations.person_name');

        if ($source === 'typed') {
            return ['person_name' => $examination->person_name];
        }

        if (! is_string($source)) {
            return [];
        }

        $resolver = app($source);

        if (! $resolver instanceof ResolvesPersonName) {
            throw new InvalidArgumentException($source.' must implement '.ResolvesPersonName::class.'.');
        }

        return ['person_name' => $resolver->personName($examination)];
    }
}
