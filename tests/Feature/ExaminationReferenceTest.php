<?php

declare(strict_types=1);

use HumanExaminations\HumanExaminations\Actions\CreateExamination;

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');

    $this->artisan('migrate', [
        '--path' => realpath(__DIR__.'/../../database/migrations'),
        '--realpath' => true,
    ])->assertSuccessful();
});

it('fills a blank reference from the configured format and examination date', function () {
    config()->set('human-examinations.reference_format', 'EX-{year}-{month}-{day}-{id:4}');

    $examination = app(CreateExamination::class)->handle(['date' => '2026-12-30']);

    expect($examination->fresh()->reference)->toBe('EX-2026-12-30-0001');
});
