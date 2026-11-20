# Models and API

[Documentation index](README.md) · [Package overview](../README.md)

## Models

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
including soft-deleted observations. Direct relationship creates require the
consuming app to allocate numbers; the database rejects duplicates. The
`CreateObservation` action and HTTP create route allocate the next number on
the server, including soft-deleted observations. `type` is cast to an observation type enum and
defaults to `Unspecified` (see [Observation Types](#observation-types)). Label,
region, description, and measurements are nullable. Measurements use millimetres
and decimal casts with two decimal places.

## Observation Types

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

## Observation Locations

A location consists of a stable `sketch_id` (for example `body-front` or
`hand-left-palmar`) and normalized `x`/`y` coordinates in the inclusive range
0–1, measured from the sketch's top-left corner. Coordinates use six decimal
places. Store identifiers, not asset URLs or filenames that change with builds.
Anatomical left/right always refer to the subject. No sketch catalog or region
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

## Actions and HTTP Endpoints

`ExportExamination::handle($examination)` returns a versioned portable document. `ImportExamination::handle($document)` validates and transactionally creates a new examination with its active observations. Both actions live under `HumanExaminations\HumanExaminations\Actions`; callers perform their own authorization. See [import and export](import-export.md) for JSON fields, CSV downloads, limits, and action overrides.

See [application integration](integration.md#optional-http-routes) for the route table, authorization rules, action overrides, and redirect behavior. The [body-region reference](body-regions.md) lists every standard region and its selection map.

## Serialized Records

The workbench and portable export share an explicit list of examination and
observation fields. Dates are serialized as `YYYY-MM-DD`, observation types as
their enum values, and measurements and coordinates as fixed-precision decimal
strings. Workspace data includes IDs for selecting and saving records. Portable
exports omit IDs. Both omit storage timestamps and any extra model attributes.

Host pages can build the same field projections with
`Support\RecordData::examination($examination)` and
`Support\RecordData::observation($observation)` under the package namespace.
Authorize reads and add the IDs your workspace needs before sending the props.
Regular Eloquent serialization remains available to host code.
