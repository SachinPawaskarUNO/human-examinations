<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Database\Seeders;

use HumanExaminations\HumanExaminations\Actions\CreateExamination;
use HumanExaminations\HumanExaminations\Actions\CreateObservation;
use HumanExaminations\HumanExaminations\Enums\ExaminationBodyRegion;
use HumanExaminations\HumanExaminations\Models\ExaminationObservation;
use HumanExaminations\HumanExaminations\Models\HumanExamination;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Sample examinations for trying the package in a host app.
 *
 * php artisan db:seed --class="HumanExaminations\HumanExaminations\Database\Seeders\HumanExaminationsSeeder"
 */
class HumanExaminationsSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Skipped sample examinations in production.');

            return;
        }

        DB::transaction(function (): void {
            $this->seedSamples();
        });
    }

    private function seedSamples(): void
    {
        // Reference => [examiner, person name, days ago, notes]
        $examinations = [
            'SAMPLE-001' => ['Sample Examiner', 'Sample Person', 14, 'Sample with marked observations on the front and back.'],
            'SAMPLE-002' => ['Sample Examiner', null, 3, 'Sample with observations not yet placed on a sketch.'],
            'SAMPLE-003' => ['Sample Examiner', null, null, 'Sample draft with no observations yet.'],
        ];
        $records = [];

        foreach ($examinations as $reference => [$examiner, $personName, $daysAgo, $notes]) {
            // The notes identify each sample, because a reference format replaces its reference.
            if (HumanExamination::query()->withTrashed()->where('notes', $notes)->exists()) {
                continue;
            }

            // The actions apply the app's reference format, person name setting, and overrides.
            $records[$reference] = app(CreateExamination::class)->handle([
                'reference' => $reference,
                'examiner' => $examiner,
                'person_name' => $personName,
                'date' => $daysAgo === null ? null : now()->subDays($daysAgo)->toDateString(),
                'notes' => $notes,
            ]);
        }

        // [reference, preferred type, region, label, [sketch, x, y] or null when not placed]
        $observations = [
            ['SAMPLE-001', 'Scar', ExaminationBodyRegion::RightUpperArm, 'Linear scar', ['body-front', 0.23, 0.28]],
            ['SAMPLE-001', 'Birthmark', ExaminationBodyRegion::Abdomen, 'Oval birthmark', ['body-front', 0.5, 0.4]],
            ['SAMPLE-001', 'Tattoo', ExaminationBodyRegion::UpperBack, 'Rose tattoo', ['body-back', 0.5, 0.25]],
            ['SAMPLE-002', 'Bruise', ExaminationBodyRegion::LeftThigh, 'Yellowing bruise', null],
            ['SAMPLE-002', 'Abrasion', ExaminationBodyRegion::RightKnee, 'Graze', null],
        ];

        foreach ($observations as [$reference, $type, $region, $label, $location]) {
            $this->seedObservation($records[$reference] ?? null, $type, $region, $label, $location);
        }
    }

    /** @param array{0: string, 1: float, 2: float}|null $location */
    private function seedObservation(?HumanExamination $examination, string $type, ExaminationBodyRegion $region, string $label, ?array $location): void
    {
        // Skip samples seeded on an earlier run.
        if ($examination === null) {
            return;
        }

        [$sketchId, $x, $y] = $location ?? [null, null, null];

        app(CreateObservation::class)->handle($examination, [
            'type' => $this->type($type),
            'region' => $region->value,
            'label' => $label,
            'sketch_id' => $sketchId,
            'x' => $x,
            'y' => $y,
        ]);
    }

    /** The preferred type when the configured enum has it; every valid enum has Unspecified. */
    private function type(string $preferred): string
    {
        $type = ExaminationObservation::typeEnum()::tryFrom($preferred)?->value;

        return is_string($type) ? $type : 'Unspecified';
    }
}
