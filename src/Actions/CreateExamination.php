<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Actions;

use HumanExaminations\HumanExaminations\Models\Examination;
use Illuminate\Support\Str;

class CreateExamination
{
    /**
     * Start a new examination. Every field is optional, so it can begin blank.
     * When the `reference_format` config is set, the reference is always generated from it.
     *
     * @param  array{reference?: string|null, examiner?: string|null, date?: string|null, notes?: string|null}  $attributes
     */
    public function handle(array $attributes = []): Examination
    {
        return (new Examination)->getConnection()->transaction(function () use ($attributes): Examination {
            $examination = Examination::create($attributes);

            // A reference format takes precedence over any reference sent with the request.
            if (($reference = $this->formatReference($examination)) !== null) {
                $examination->update(['reference' => $reference]);
            }

            return $examination;
        });
    }

    /**
     * The reference for a new examination created without one, or null to leave it blank.
     * Override this method in your own CreateExamination to generate references another way.
     */
    protected function formatReference(Examination $examination): ?string
    {
        $format = config('human-examinations.reference_format');

        if (! is_string($format) || $format === '') {
            return null;
        }

        // References follow the examination date, so exams entered later keep their own year.
        $date = $examination->date ?? $examination->created_at ?? now();

        return preg_replace_callback(
            '/\{(id|year|month|day|random)(?::(\d+))?\}/',
            fn (array $token): string => match ($token[1]) {
                'id' => str_pad((string) $examination->getKey(), (int) ($token[2] ?? 0), '0', STR_PAD_LEFT),
                'year' => $date->format('Y'),
                'month' => $date->format('m'),
                'day' => $date->format('d'),
                'random' => Str::upper(Str::random((int) ($token[2] ?? 6))),
            },
            $format,
        );
    }
}
