<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Support;

use HumanExaminations\HumanExaminations\Models\Observation;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;

class RecordRules
{
    public const array OBSERVATION_NONBLANK_FIELDS = ['length_mm', 'width_mm', 'sketch_id', 'x', 'y'];

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $fields
     */
    public static function rejectBlankValues(array $attributes, array $fields, string $prefix = ''): void
    {
        $errors = [];
        foreach ($fields as $field) {
            $value = $attributes[$field] ?? null;

            if (is_string($value) && trim($value) === '') {
                $errors[$prefix.$field] = "The {$field} field must contain a value or be null.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @return array<string, list<string>> */
    public static function examination(): array
    {
        return [
            'reference' => ['nullable', 'string', 'max:255'],
            'examiner' => ['nullable', 'string', 'max:255'],
            'person_name' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, list<string|Enum>> */
    public static function observation(): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(Observation::typeEnum())],
            'label' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'length_mm' => ['nullable', 'numeric', 'between:0,99999999.99'],
            'width_mm' => ['nullable', 'numeric', 'between:0,99999999.99'],
            'sketch_id' => ['nullable', 'string', 'max:255'],
            'x' => ['nullable', 'numeric', 'between:0,1'],
            'y' => ['nullable', 'numeric', 'between:0,1'],
        ];
    }

    /** @return array<string, list<string>> */
    public static function location(string $prefix = ''): array
    {
        return [
            $prefix.'sketch_id' => ['nullable', "required_with:{$prefix}x,{$prefix}y"],
            $prefix.'x' => ['nullable', "required_with:{$prefix}sketch_id,{$prefix}y"],
            $prefix.'y' => ['nullable', "required_with:{$prefix}sketch_id,{$prefix}x"],
        ];
    }
}
