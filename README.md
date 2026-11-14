<div align="center">
    <h1>Human Examinations</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/spawaskar/human-examinations"><img src="https://img.shields.io/packagist/v/spawaskar/human-examinations.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/spawaskar/human-examinations"><img src="https://img.shields.io/packagist/php-v/spawaskar/human-examinations.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/spawaskar/human-examinations"><img src="https://badge.laravel.cloud/badge/spawaskar/human-examinations?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/spawaskar/human-examinations/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/spawaskar/human-examinations/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/spawaskar/human-examinations"><img src="https://img.shields.io/packagist/dt/spawaskar/human-examinations.svg?style=flat-square" alt="Total Downloads"></a>
</p>

This package allows you to add human examinations images & resources, components & ui to your laravel/vue project

## Installation

You can install the package via Composer:

```bash
composer require spawaskar/human-examinations
```

You may publish all of the package's resources at once:

```bash
php artisan vendor:publish --tag="human-examinations"
```

Or, you may publish each resource individually:

### Publishing the Configuration File

```bash
php artisan vendor:publish --tag="human-examinations-config"
```

### Publishing and Running the Migrations

```bash
php artisan vendor:publish --tag="human-examinations-migrations"
php artisan migrate
```

### Publishing the Views

```bash
php artisan vendor:publish --tag="human-examinations-views"
```

### Publishing the Translations

```bash
php artisan vendor:publish --tag="human-examinations-lang"
```

### Publishing the Public Assets

```bash
php artisan vendor:publish --tag="human-examinations-assets"
```

## Usage

The package owns two independent tables: `human_examinations` and
`human_examination_observations`. It does not require host person, case, team, or
user tables. Publish and run the migrations before using the models.

```php
use HumanExaminations\HumanExaminations\Models\Examination;

$examination = Examination::create([
    'reference' => 'EX-001',
    'examiner' => 'Dr Smith',
    'date' => '2026-11-12',
    'notes' => 'Initial examination.',
]);

$observation = $examination->observations()->create([
    'number' => 1,
    'type' => 'Scar',
    'label' => 'Linear scar',
    'region' => 'Right upper arm',
    'description' => 'Healed linear scar.',
    'length_mm' => '32.50',
    'width_mm' => '4.25',
    'sketch_id' => 'body-front',
    'x' => 0.31,
    'y' => 0.32,
]);

$observation->examination; // BelongsTo Examination
$examination->observations; // HasMany Observation
```

Examination metadata is nullable to support drafts. `date` is cast to a date and
serialized as `Y-m-d`. References are indexed but need not be unique. Each
observation requires a positive integer `number`, unique within its examination,
including soft-deleted observations. Allocate numbers in the consuming app;
the database rejects duplicates. `type` is cast to an observation type enum and
defaults to `Unspecified` (see [Observation Types](#observation-types)). Label,
region, description, and measurements are nullable. Measurements use millimetres
and decimal casts with two decimal places.

### Observation Types

An observation's `type` is cast to the
`HumanExaminations\HumanExaminations\Enums\ObservationType` enum, a
string-backed enum with these values:

`Unspecified`, `Scar`, `Tattoo`, `Birthmark`, `Injury`, `Gunshot wound`,
`Stab wound`, `Bruise`, `Abrasion`, `Other`

You may assign either the enum case or its string value. Reading `type` always
returns the enum case, and the database stores the string value:

```php
use HumanExaminations\HumanExaminations\Enums\ObservationType;

$examination->observations()->create(['number' => 2, 'type' => ObservationType::GunshotWound]);
$examination->observations()->create(['number' => 3, 'type' => 'Gunshot wound']);

$observation->type;        // ObservationType::GunshotWound
$observation->type->value; // 'Gunshot wound'
```

Assigning a value that is not in the enum throws a `ValueError`.

To use your own list of types, publish the configuration file and point
`observation_type` at your own string-backed enum:

```php
// config/human-examinations.php
'observation_type' => App\Enums\FindingType::class,
```

Your enum must be string-backed and must include a case with the value
`Unspecified`, which is the default type. The `type` column is a plain string,
so changing the enum does not require a migration. However, existing rows whose
values are not in the new enum will throw a `ValueError` when they are loaded.
`Observation::typeEnum()` returns the configured enum class, for example to
build a list of options for a form.

### Vue Workspace

`resources/js/components/HumanExaminationWorkspace.vue` is a Vue 3 component
for your app's Vite build. Pass the observation types from Laravel, for example
as an Inertia prop, to show them in the Type dropdown:

```php
use HumanExaminations\HumanExaminations\Models\Observation;

return Inertia::render('Examination', [
    'observationTypes' => array_map(fn ($type) => $type->value, Observation::typeEnum()::cases()),
]);
```

```vue
<HumanExaminationWorkspace v-model:observations="observations" :types="observationTypes" />
```

Without `types`, the Type field is free text.

A location consists of a stable `sketch_id` (for example `body-front` or
`hand-left-palmar`) and normalized `x`/`y` coordinates in the inclusive range
0–1, measured from the sketch's top-left corner. Coordinates use six decimal
places. Store identifiers, not asset URLs or filenames that change with builds.
Anatomical left/right always refer to the subject. No sketch catalogue or region
inference is imposed by the models.

For an observation without a diagram location, leave `sketch_id`, `x`, and `y`
null. Clear a location by setting all three fields to null together. Eloquent
saves reject incomplete locations, invalid coordinates, negative or non-finite
measurements, and invalid observation numbers with `DomainException`. These
checks run on model saves; query builder and bulk updates bypass model events.
The database enforces the examination foreign key and per-examination number
uniqueness.

Both models use integer IDs, timestamps, and soft deletion. Soft-deleting an
examination preserves its observations so restoration brings them back unchanged;
it does not individually soft-delete them. Query observations through an active,
authorized examination, since a direct observation query does not exclude
observations of a soft-deleted examination. Permanently deleting an examination
cascades to all its observations, including soft-deleted ones.

### Host Integration

The consuming application owns links to existing records, team ownership,
authorization, and user auditing. It can add a foreign key or association table
linking its records to `human_examinations`. The package does not copy, replace,
or automatically create counterparts for existing host examinations. Existing
examination data and observations require an explicit integration decision before
adoption; these migrations do not alter or migrate host tables.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Human Examinations! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Sachin Pawaskar](https://github.com/spawaskar)
- [All Contributors](../../contributors)

## License

Human Examinations is open-sourced software licensed under the [MIT license](LICENSE.md).
