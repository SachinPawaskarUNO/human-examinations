<?php

namespace Workbench\Database\Seeders;

use HumanExaminations\HumanExaminations\Actions\CreateExamination;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Workbench\App\Models\Person;
use Workbench\Database\Factories\UserFactory;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // UserFactory::new()->times(10)->create();

        UserFactory::new()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // A demo person linked to an examination.
        $examination = app(CreateExamination::class)->handle([
            'examiner' => 'Demo Examiner',
            'date' => now()->toDateString(),
        ]);
        $examination->observations()->create([
            'number' => 1, 'type' => 'Scar', 'label' => 'Linear scar', 'region' => 'Right upper arm',
            'sketch_id' => 'body-front', 'x' => 0.23, 'y' => 0.28,
        ]);
        Person::create(['name' => 'Jane Doe'])->humanExamination()->associate($examination)->save();
    }
}
