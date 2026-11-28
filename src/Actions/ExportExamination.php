<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Actions;

use HumanExaminations\HumanExaminations\Models\ExaminationObservation;
use HumanExaminations\HumanExaminations\Models\HumanExamination;
use HumanExaminations\HumanExaminations\Support\ExaminationDocument;
use HumanExaminations\HumanExaminations\Support\RecordData;

class ExportExamination
{
    /** @return array{format: string, version: int, examination: array<string, mixed>, observations: list<array<string, mixed>>} */
    public function handle(HumanExamination $examination): array
    {
        return [
            'format' => ExaminationDocument::FORMAT,
            'version' => ExaminationDocument::VERSION,
            'examination' => RecordData::examination($examination),
            'observations' => array_values($examination->observations()->orderBy('number')->get()
                ->map(fn (ExaminationObservation $observation): array => RecordData::observation($observation))->all()),
        ];
    }
}
