<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Actions;

use HumanExaminations\HumanExaminations\Models\HumanExamination;
use HumanExaminations\HumanExaminations\Support\ExaminationDocument;

class ImportExamination
{
    /** @param array<string, mixed> $document */
    public function handle(array $document): HumanExamination
    {
        $data = ExaminationDocument::validate($document);

        return (new HumanExamination)->getConnection()->transaction(function () use ($data): HumanExamination {
            /** @var array{reference?: string|null, examiner?: string|null, person_name?: string|null, date?: string|null, notes?: string|null} $metadata */
            $metadata = $data['examination'];
            $examination = app(CreateExamination::class)->handle($metadata);
            foreach ($data['observations'] as $observation) {
                $examination->observations()->create($observation);
            }

            return $examination;
        }, 5);
    }
}
