<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Support;

use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;
use stdClass;

/** Portable examination document format; database identities are never imported. */
class ExaminationDocument
{
    public const string FORMAT = 'spawaskar/human-examinations';

    public const int VERSION = 1;

    public const array EXAMINATION_FIELDS = RecordData::EXAMINATION_FIELDS;

    public const array OBSERVATION_FIELDS = RecordData::OBSERVATION_FIELDS;

    /** @return array<string, mixed> */
    public static function decode(string $contents): array
    {
        try {
            $decoded = json_decode(str_starts_with($contents, "\xEF\xBB\xBF") ? substr($contents, 3) : $contents, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['file' => 'The selected file is not valid JSON.']);
        }

        if (! $decoded instanceof stdClass) {
            throw ValidationException::withMessages(['file' => 'The file must contain an examination document object.']);
        }

        // Preserve the object/list distinction before converting to arrays for Laravel validation.
        if (! ($decoded->examination ?? null) instanceof stdClass) {
            throw ValidationException::withMessages(['document.examination' => 'The examination details must be a JSON object.']);
        }

        if (! is_array($decoded->observations ?? null)) {
            throw ValidationException::withMessages(['document.observations' => 'Observations must be a JSON array.']);
        }

        $document = get_object_vars($decoded);
        $document['examination'] = get_object_vars($decoded->examination);
        $document['observations'] = [];
        foreach ($decoded->observations as $index => $observation) {
            if (! $observation instanceof stdClass) {
                throw ValidationException::withMessages(['document.observations.'.$index => 'Each observation must be a JSON object.']);
            }

            $document['observations'][] = get_object_vars($observation);
        }

        return $document;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array{format: string, version: int, examination: array<string, mixed>, observations: list<array<string, mixed>>}
     */
    public static function validate(array $document): array
    {
        $rules = [
            'document' => ['required', 'array:format,version,examination,observations'],
            'document.format' => ['required', 'string', Rule::in([self::FORMAT])],
            'document.version' => ['required', function (string $attribute, mixed $value, Closure $fail): void {
                if ($value !== self::VERSION) {
                    $fail('The examination document version is not supported.');
                }
            }],
            'document.examination' => ['present', 'array:'.implode(',', [...self::EXAMINATION_FIELDS, 'person_name'])],
            'document.observations' => ['present', 'array', 'list', 'max:'.(int) config('human-examinations.transfer.max_observations', 10000)],
            'document.observations.*' => ['required', 'array:'.implode(',', self::OBSERVATION_FIELDS)],
            'document.observations.*.number' => ['required', 'integer', 'min:1', 'max:4294967295', 'distinct', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_int($value)) {
                    $fail('Observation numbers must be JSON integers.');
                }
            }],
        ];
        foreach (RecordRules::examination() as $field => $fieldRules) {
            $rules['document.examination.'.$field] = $fieldRules;
        }
        $prefix = 'document.observations.*.';
        foreach (RecordRules::observation() as $field => $fieldRules) {
            $rules[$prefix.$field] = $fieldRules;
        }
        foreach (RecordRules::location($prefix) as $field => $fieldRules) {
            $rules[$field] = [...$rules[$field], ...$fieldRules];
        }

        $validated = Validator::make(['document' => $document], $rules)->validate();

        /** @var array{format: string, version: int, examination: array<string, mixed>, observations: list<array<string, mixed>>} $result */
        $result = $validated['document'];
        RecordRules::rejectBlankValues($result['examination'], ['date'], 'document.examination.');
        foreach ($result['observations'] as $index => $observation) {
            RecordRules::rejectBlankValues($observation, RecordRules::OBSERVATION_NONBLANK_FIELDS, 'document.observations.'.$index.'.');
        }

        return $result;
    }
}
