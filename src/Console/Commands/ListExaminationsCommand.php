<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Console\Commands;

use HumanExaminations\HumanExaminations\Models\HumanExamination;
use Illuminate\Console\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;

class ListExaminationsCommand extends Command
{
    protected $signature = 'human-examinations:list
        {--limit=20 : Maximum number of rows (1–100)}';

    protected $description = 'List recent active examinations';

    public function handle(): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);

        if ($limit === false || $limit < 1 || $limit > 100) {
            $this->error('The limit must be a whole number between 1 and 100.');

            return self::FAILURE;
        }

        $examinations = HumanExamination::query()->withCount('observations')->orderByDesc('id')->limit($limit)->get();

        if ($examinations->isEmpty()) {
            $this->info('No active examinations found.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Reference', 'Examiner', 'Date', 'Observations'], $examinations->map(fn (HumanExamination $examination): array => [
            $examination->id,
            OutputFormatter::escape($examination->reference ?? ''),
            OutputFormatter::escape($examination->examiner ?? ''),
            $examination->date?->format('Y-m-d'),
            $examination->observations_count,
        ])->all());

        return self::SUCCESS;
    }
}
