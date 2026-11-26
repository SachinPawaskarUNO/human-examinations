<?php

declare(strict_types=1);

use HumanExaminations\HumanExaminations\Actions\CreateExamination;
use HumanExaminations\HumanExaminations\Actions\UpdateExamination;

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');

    $this->artisan('migrate', [
        '--path' => realpath(__DIR__.'/../../database/migrations'),
        '--realpath' => true,
    ])->assertSuccessful();
});

it('saves person names only when users type them', function () {
    $examination = app(CreateExamination::class)->handle(['person_name' => 'Jane Doe']);
    app(UpdateExamination::class)->handle($examination, ['person_name' => 'Jane Doe']);
    expect($examination->fresh()->person_name)->toBeNull();

    config()->set('human-examinations.person_name', 'typed');
    app(UpdateExamination::class)->handle($examination, ['person_name' => 'Jane Doe']);
    expect($examination->fresh()->person_name)->toBe('Jane Doe');
});
