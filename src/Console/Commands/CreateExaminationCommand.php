<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Console\Commands;

use HumanExaminations\HumanExaminations\Actions\CreateExamination;
use HumanExaminations\HumanExaminations\Support\RecordRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

class CreateExaminationCommand extends Command
{
    protected $signature = 'human-examinations:create
        {--reference= : Examination reference}
        {--examiner= : Examiner name}
        {--date= : Examination date (YYYY-MM-DD)}
        {--notes= : Examination notes}';

    protected $description = 'Create an examination';

    public function handle(CreateExamination $createExamination): int
    {
        $interactive = $this->input->isInteractive();
        $attributes = [
            'reference' => $this->option('reference') ?? ($interactive ? text('Reference (optional)') : null),
            'examiner' => $this->option('examiner') ?? ($interactive ? text('Examiner (optional)') : null),
            'date' => $this->option('date') ?? ($interactive ? text('Date (YYYY-MM-DD, optional)') : null),
            'notes' => $this->option('notes') ?? ($interactive ? text('Notes (optional)') : null),
        ];

        $validator = Validator::make(array_map(
            fn (mixed $value): mixed => is_string($value) && trim($value) === '' ? null : $value,
            $attributes,
        ), RecordRules::examination());

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        if ($interactive && ! confirm('Create this examination?', default: false)) {
            $this->info('Examination creation cancelled.');

            return self::SUCCESS;
        }

        /** @var array{reference: string|null, examiner: string|null, date: string|null, notes: string|null} $validated */
        $validated = $validator->validated();
        $examination = $createExamination->handle($validated);
        $this->info('Examination #'.$examination->id.' created.');

        return self::SUCCESS;
    }
}
