<?php

declare(strict_types=1);

use HumanExaminations\HumanExaminations\Enums\ObservationType;
use HumanExaminations\HumanExaminations\HumanExaminationsServiceProvider;
use HumanExaminations\HumanExaminations\Models\Examination;
use HumanExaminations\HumanExaminations\Models\Observation;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');
    config()->set('database.connections.sqlite.foreign_key_constraints', true);

    $this->artisan('migrate', [
        '--path' => realpath(__DIR__.'/../../database/migrations'),
        '--realpath' => true,
    ])->assertSuccessful();
});

it('creates and rolls back the publishable package tables without altering host tables', function () {
    Schema::create('host_records', function (Blueprint $table): void {
        $table->id();
        $table->string('reference');
    });
    DB::table('host_records')->insert(['reference' => 'HOST-1']);

    expect(Schema::hasColumns('human_examinations', [
        'id', 'reference', 'examiner', 'date', 'notes', 'created_at', 'updated_at', 'deleted_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('human_examination_observations', [
            'id', 'examination_id', 'number', 'type', 'label', 'region', 'description',
            'length_mm', 'width_mm', 'sketch_id', 'x', 'y', 'created_at', 'updated_at', 'deleted_at',
        ]))->toBeTrue()
        ->and(Schema::hasTable('human_examinations_placeholder'))->toBeFalse();

    $paths = ServiceProvider::pathsToPublish(HumanExaminationsServiceProvider::class, 'human-examinations-migrations');
    expect($paths)->toHaveKey(dirname(__DIR__, 2).'/src/../database/migrations');

    $this->artisan('migrate:rollback', [
        '--path' => realpath(__DIR__.'/../../database/migrations'),
        '--realpath' => true,
    ])->assertSuccessful();

    expect(Schema::hasTable('human_examinations'))->toBeFalse()
        ->and(Schema::hasTable('human_examination_observations'))->toBeFalse()
        ->and(DB::table('host_records')->value('reference'))->toBe('HOST-1');

    $this->artisan('migrate', [
        '--path' => realpath(__DIR__.'/../../database/migrations'),
        '--realpath' => true,
    ])->assertSuccessful();

    expect(Schema::hasTable('human_examinations'))->toBeTrue()
        ->and(Schema::hasTable('human_examination_observations'))->toBeTrue();
});

it('persists examination details with date and timestamp casts', function () {
    $examination = Examination::query()->create([
        'reference' => 'EX-001',
        'examiner' => 'Dr Smith',
        'date' => '2026-11-12',
        'notes' => 'Initial examination.',
    ])->fresh();

    expect($examination->reference)->toBe('EX-001')
        ->and($examination->examiner)->toBe('Dr Smith')
        ->and($examination->date)->toBeInstanceOf(Carbon::class)
        ->and($examination->date->toDateString())->toBe('2026-11-12')
        ->and($examination->toArray()['date'])->toBe('2026-11-12')
        ->and($examination->notes)->toBe('Initial examination.')
        ->and($examination->created_at)->toBeInstanceOf(Carbon::class)
        ->and($examination->updated_at)->toBeInstanceOf(Carbon::class);
});

it('supports draft examinations without metadata or observations', function () {
    $examination = Examination::query()->create([])->fresh();

    expect($examination->reference)->toBeNull()
        ->and($examination->examiner)->toBeNull()
        ->and($examination->date)->toBeNull()
        ->and($examination->notes)->toBeNull()
        ->and($examination->observations)->toBeEmpty();
});

it('relates observations to their examination and casts measurements and locations', function () {
    $examination = Examination::query()->create([]);
    $other = Examination::query()->create([]);
    $observation = $examination->observations()->create([
        'number' => 1,
        'type' => 'Scar',
        'label' => 'Linear scar',
        'region' => 'Right upper arm',
        'description' => 'Healed scar.',
        'length_mm' => '32.50',
        'width_mm' => '4.25',
        'sketch_id' => 'body-front',
        'x' => 0.31,
        'y' => 0.32,
    ])->fresh();

    expect($observation->examination->is($examination))->toBeTrue()
        ->and($examination->observations->modelKeys())->toBe([$observation->id])
        ->and($other->observations)->toBeEmpty()
        ->and($observation->number)->toBe(1)
        ->and($observation->type)->toBe(ObservationType::Scar)
        ->and($observation->label)->toBe('Linear scar')
        ->and($observation->region)->toBe('Right upper arm')
        ->and($observation->description)->toBe('Healed scar.')
        ->and($observation->length_mm)->toBe('32.50')
        ->and($observation->width_mm)->toBe('4.25')
        ->and($observation->sketch_id)->toBe('body-front')
        ->and($observation->x)->toBe('0.310000')
        ->and($observation->y)->toBe('0.320000');
});

it('supports observations without measurements or a sketch location and can clear locations', function () {
    $observation = Examination::query()->create([])->observations()->create(['number' => 1])->fresh();

    expect($observation->type)->toBe(ObservationType::Unspecified)
        ->and($observation->label)->toBeNull()
        ->and($observation->region)->toBeNull()
        ->and($observation->description)->toBeNull()
        ->and($observation->length_mm)->toBeNull()
        ->and($observation->width_mm)->toBeNull()
        ->and($observation->sketch_id)->toBeNull()
        ->and($observation->x)->toBeNull()
        ->and($observation->y)->toBeNull();

    $observation->update(['sketch_id' => 'hand-left-palmar', 'x' => 0, 'y' => 1, 'length_mm' => 0]);
    expect($observation->fresh()->x)->toBe('0.000000')
        ->and($observation->fresh()->y)->toBe('1.000000');

    $observation->update(['sketch_id' => null, 'x' => null, 'y' => null]);
    expect($observation->fresh()->sketch_id)->toBeNull()
        ->and($observation->fresh()->x)->toBeNull()
        ->and($observation->fresh()->y)->toBeNull();
});

it('rejects invalid observation locations and measurements on create and update', function (array $attributes) {
    $examination = Examination::query()->create([]);
    expect(fn () => $examination->observations()->create(['number' => 1, ...$attributes]))
        ->toThrow(DomainException::class);

    $observation = $examination->observations()->create(['number' => 1]);
    expect(fn () => $observation->update($attributes))->toThrow(DomainException::class);
})->with([
    'missing y' => [['sketch_id' => 'body-front', 'x' => 0.5]],
    'missing x' => [['sketch_id' => 'body-front', 'y' => 0.5]],
    'missing sketch' => [['x' => 0.5, 'y' => 0.5]],
    'sketch only' => [['sketch_id' => 'body-front']],
    'empty sketch' => [['sketch_id' => ' ', 'x' => 0.5, 'y' => 0.5]],
    'negative x' => [['sketch_id' => 'body-front', 'x' => -0.1, 'y' => 0.5]],
    'large y' => [['sketch_id' => 'body-front', 'x' => 0.5, 'y' => 1.1]],
    'range before rounding' => [['sketch_id' => 'body-front', 'x' => 1.0000001, 'y' => 0.5]],
    'nonnumeric coordinate' => [['sketch_id' => 'body-front', 'x' => 'invalid', 'y' => 0.5]],
    'negative length' => [['length_mm' => -1]],
    'negative width' => [['width_mm' => -0.001]],
    'nonnumeric length' => [['length_mm' => 'invalid']],
    'infinite width' => [['width_mm' => INF]],
    'measurement overflow' => [['length_mm' => 100000000]],
    'missing number' => [['number' => null]],
    'zero number' => [['number' => 0]],
    'fractional number' => [['number' => 1.5]],
    'number overflow' => [['number' => 4294967296]],
]);

it('enforces unique observation numbers within each examination in the database', function () {
    $first = Examination::query()->create([]);
    $second = Examination::query()->create([]);
    $observation = $first->observations()->create(['number' => 1]);
    $second->observations()->create(['number' => 1]);
    $observation->delete();

    expect(fn () => DB::table('human_examination_observations')->insert([
        'examination_id' => $first->id, 'number' => 1,
    ]))->toThrow(QueryException::class);
});

it('rejects observations with a nonexistent examination in the database', function () {
    expect(fn () => DB::table('human_examination_observations')->insert([
        'examination_id' => 999, 'number' => 1,
    ]))->toThrow(QueryException::class);
});

it('soft deletes and restores observations independently', function () {
    $examination = Examination::query()->create([]);
    $observation = $examination->observations()->create(['number' => 1]);
    $observation->delete();

    expect($examination->observations()->count())->toBe(0)
        ->and($examination->observations()->withTrashed()->count())->toBe(1)
        ->and($observation->deleted_at)->toBeInstanceOf(Carbon::class);

    $observation->restore();
    expect($examination->observations()->count())->toBe(1);
});

it('preserves observations when an examination is soft deleted and cascades permanent deletion', function () {
    $examination = Examination::query()->create([]);
    $observation = $examination->observations()->create(['number' => 1]);
    $other = Examination::query()->create([]);
    $other->observations()->create(['number' => 1]);
    $examination->delete();

    expect(Examination::query()->find($examination->id))->toBeNull()
        ->and($observation->fresh())->not->toBeNull()
        ->and($observation->fresh()->examination)->toBeNull();

    $examination->restore();
    expect($examination->observations()->count())->toBe(1);
    $observation->delete();
    $examination->forceDelete();

    expect(Observation::withTrashed()->find($observation->id))->toBeNull()
        ->and($other->fresh())->not->toBeNull()
        ->and($other->observations()->count())->toBe(1);
});
