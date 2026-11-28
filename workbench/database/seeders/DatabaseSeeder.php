<?php

namespace Workbench\Database\Seeders;

use HumanExaminations\HumanExaminations\Database\Seeders\HumanExaminationsSeeder;
use HumanExaminations\HumanExaminations\Models\HumanExamination;
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

        // The package's sample examinations, with a demo person linked to the first.
        $this->call(HumanExaminationsSeeder::class);
        $examination = HumanExamination::query()->orderBy('id')->firstOrFail();
        Person::create(['name' => 'Jane Doe'])->humanExamination()->associate($examination)->save();
    }
}
