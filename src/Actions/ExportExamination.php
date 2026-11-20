<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Actions;

use HumanExaminations\HumanExaminations\Models\Examination;
use HumanExaminations\HumanExaminations\Models\Observation;
use HumanExaminations\HumanExaminations\Support\ExaminationDocument;
use HumanExaminations\HumanExaminations\Support\RecordData;

class ExportExamination
{
    /** @return array{format: string, version: int, examination: array<string, mixed>, observations: list<array<string, mixed>>} */
    public function handle(Examination $examination): array
    {
        return [
            'format' => ExaminationDocument::FORMAT,
            'version' => ExaminationDocument::VERSION,
            'examination' => RecordData::examination($examination),
            'observations' => array_values($examination->observations()->orderBy('number')->get()
                ->map(fn (Observation $observation): array => RecordData::observation($observation))->all()),
        ];
    }
}
