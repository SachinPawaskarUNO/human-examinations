<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Console\Commands;

use HumanExaminations\HumanExaminations\Models\ExaminationObservation;
use HumanExaminations\HumanExaminations\Models\HumanExamination;
use Illuminate\Console\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;

use function Laravel\Prompts\text;

class ShowExaminationCommand extends Command
{
    protected $signature = 'human-examinations:show {examination? : Examination ID}';

    protected $description = 'Show an active examination and its observations';

    public function handle(): int
    {
        $argument = $this->argument('examination');

        if ($argument === null && $this->input->isInteractive()) {
            $argument = text('Examination ID', required: true);
        }

        $id = filter_var($argument, FILTER_VALIDATE_INT);

        if ($id === false || $id < 1) {
            $this->error('Provide a positive examination ID. Use human-examinations:list to look up IDs.');

            return self::FAILURE;
        }

        $examination = HumanExamination::query()->find($id);

        if ($examination === null) {
            $this->error('No active examination found for ID '.$id.'.');

            return self::FAILURE;
        }

        $this->table(['Field', 'Value'], [
            ['ID', $examination->id],
            ['Reference', OutputFormatter::escape($examination->reference ?? '')],
            ['Examiner', OutputFormatter::escape($examination->examiner ?? '')],
            ['Date', $examination->date?->format('Y-m-d')],
            ['Notes', OutputFormatter::escape($examination->notes ?? '')],
        ]);

        $observations = $examination->observations()->orderBy('number')->get();

        if ($observations->isEmpty()) {
            $this->info('No observations recorded.');

            return self::SUCCESS;
        }

        $this->table(['No.', 'Type', 'Label', 'Region', 'Sketch'], $observations->map(fn (ExaminationObservation $observation): array => [
            $observation->number,
            OutputFormatter::escape((string) $observation->type->value),
            OutputFormatter::escape($observation->label ?? ''),
            OutputFormatter::escape($observation->region ?? ''),
            OutputFormatter::escape($observation->sketch_id ?? ''),
        ])->all());

        return self::SUCCESS;
    }
}
