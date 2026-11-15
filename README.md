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
including soft-deleted observations. Direct relationship creates require the
consuming app to allocate numbers; the database rejects duplicates. The
`CreateObservation` action and HTTP create route allocate the next number on
the server, including soft-deleted observations. `type` is cast to an observation type enum and
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

#### Anatomical Views

The workspace's default area includes an anatomical view selector and responsive
SVG diagrams. It starts with the body front and back displayed together, side
by side when there is room and stacked in narrower areas. The catalogue offers
15 views:

| Group | Views |
| --- | --- |
| Full body | Front and back |
| Head | Front, back, left profile, right profile, and top |
| Hands | Left/right palmar and dorsal |
| Feet | Left/right dorsal and plantar |

The diagrams preserve their original aspect ratios. The paired body drawings
share the same display height and label the subject's anatomical sides. The
selector offers one **Body · Front and back** choice; head, hand, and foot views
remain individual choices. A host's existing default workspace slot still
replaces this area.

For a standalone diagram, import
`resources/js/components/AnatomicalViews.vue` from the installed package into
your Vite build. Its optional `v-model:view` accepts a stable view ID:

```vue
<AnatomicalViews v-model:view="selectedView" />
```

An omitted or unknown ID displays the paired body diagrams. Both `body-front`
and `body-back` select this paired display; each diagram retains its own sketch
ID. Choosing the body option emits `body-front` through `v-model:view`.
The catalogue in
`resources/js/sketches.js` exports `sketchGroups` and the flat `sketches` list.
Each view has `id`, `label`, `width`, `height`, and `src`; body views also have
`sides`, ordered from the image's left to right. Store `id` when referring to a
view. Vite builds the source SVGs into local assets and manages their URLs; the
application does not fetch diagrams from the reference repository at runtime.

View selection changes the display only; pin placement is not included yet.
The workbench saves observations to the database. The reusable workspace keeps
its local `v-model:observations` behavior by default; enable
`persistent-observations` to delegate writes to your host page:

```vue
<HumanExaminationWorkspace
    :examination="examination"
    :observations="observations"
    :types="observationTypes"
    persistent-observations
    @save-observation="saveObservation"
    @delete-observation="deleteObservation"
/>
```

`save-observation` emits `(draft, complete)`, where `draft.id` is null for a new
record and identifies the observation for an edit. Strip `id` from the HTTP
payload and send the editable fields to the generated store or update URL.
`delete-observation` emits `(observation, complete)`. Both handlers must call
`complete()` after a successful response or `complete({ field: 'Message' })`
after validation or transport failure. Pending writes disable observation
controls. Failed writes preserve the draft and display the returned errors;
the host must refresh the `observations` prop from the server on success.
Persistent mode requires an examination with an `id` before adding observations.
The workbench page demonstrates these handlers with Inertia, including an
`observation` error bag and cancellation/network failure handling.

Original SVG metadata and attribution are retained under `resources/sketches`;
see its [source notes](resources/sketches/README.md). The supplied artwork
identifies itself as extracted with permission from INTERPOL DVI Form 2023.

#### Observation Locations

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

### Optional HTTP Routes

The examination and observation write routes are disabled by default. Models and actions remain
available when the routes are disabled. To use the package's HTTP controllers,
publish the configuration and enable the routes:

```bash
php artisan vendor:publish --tag="human-examinations-config"
```

```php
// config/human-examinations.php
'routes' => [
    'enabled' => true,
    'prefix' => 'human-examinations',
    'name_prefix' => 'human-examinations.',
    'middleware' => ['web', 'auth'],
],
```

With these settings, the package registers:

| Method | URL | Route name |
| --- | --- | --- |
| POST | `/human-examinations/examinations` | `human-examinations.examinations.store` |
| PATCH | `/human-examinations/examinations/{examination}` | `human-examinations.examinations.update` |
| POST | `/human-examinations/examinations/{examination}/observations` | `human-examinations.observations.store` |
| PATCH | `/human-examinations/examinations/{examination}/observations/{observation}` | `human-examinations.observations.update` |
| DELETE | `/human-examinations/examinations/{examination}/observations/{observation}` | `human-examinations.observations.destroy` |

`prefix` can be a nested path such as `tools/forensics`, or an empty string.
`name_prefix` includes its trailing dot and can also be empty. `middleware`
replaces the default list; route model binding is always retained, including
when this list is empty. Generate URLs using the configured route names and
pass them to your frontend rather than hardcoding the URL prefix.

The HTTP controllers require host authorization, including when custom middleware
omits `auth`. Register a policy for the package model in your application's
service provider:

```php
use App\Policies\ExaminationPolicy;
use HumanExaminations\HumanExaminations\Models\Examination;
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::policy(Examination::class, ExaminationPolicy::class);
}
```

You can generate the policy with
`php artisan make:policy ExaminationPolicy --model="HumanExaminations\HumanExaminations\Models\Examination"`.
Implement its `create` and `update` methods using your application's permissions
and record or team associations. The package does not define ownership rules.
Without an allowing policy or Gate ability, requests are denied. Authorization
runs before validation and before the action's `handle()` method is called.

Every observation write requires the parent examination's `update` permission.
Nested binding scopes observation IDs to that examination; an unrelated or
soft-deleted observation, or a soft-deleted examination, returns 404. Observation
deletion is a soft delete. All observation writes redirect back; hosts should
submit from their examination workspace. They return validation errors for
invalid enum values, text fields, measurements, and incomplete/out-of-range
locations. PATCH validates locations after merging omitted fields with the
stored values, so clear all three location fields together. `id`, `number`, and
`examination_id` are rejected in HTTP payloads. Number allocation uses a parent
row lock and transaction with retries; the database's unique constraint also
protects each examination's numbers.

Hosts own their read routes. Authorize access to the examination before loading
`$examination->observations()->orderBy('number')->get()` into the workspace.
The package does not register an observation listing endpoint. For Inertia,
include its middleware in the host's web group so redirect validation errors
reach the page. The standalone workbench includes this middleware.

Host code can use `CreateObservation::handle($examination, $attributes)`,
`UpdateObservation::handle($observation, $attributes)`, and
`DeleteObservation::handle($observation)` after its own authorization. These
classes live under `HumanExaminations\HumanExaminations\Actions`. The create
action allocates a number; updates preserve the ID, number, and examination.
To customize them, extend the corresponding action and configure
`actions.create_observation`, `actions.update_observation`, or
`actions.delete_observation`. Keep the parent scope and numbering guarantees in
custom implementations.

Creating an examination redirects to the named `workspace_route`, when
configured, passing the examination as the route parameter. Otherwise, it
redirects back. Updates also redirect back. Host controllers can instead call
the existing `CreateExamination` and `UpdateExamination` actions after performing
their own authorization, leaving the package routes disabled.

Route settings are applied during registration. If your application caches
routes, rebuild its route cache with `php artisan route:cache` after changing
these settings, including when disabling the routes.

## Local Demo and Workbench

From the package repository, install the development dependencies and start the
Laravel workbench using native PHP and Composer:

```bash
composer install
npm ci
composer serve
```

Open **http://127.0.0.1:8000/**, or the address printed by the server. Composer
prepares the SQLite database and builds the Vue assets into the Testbench
application's public directory. The demo works without a separate Vite server.
The standalone workbench explicitly enables its write routes with a permissive
guest policy for demonstration; consuming applications use the policy setup
above.

Create an examination, then use **+** in Observations to add findings. Edits and
removals are saved through the package routes; refresh the workspace to reload
its active observations from the database. Invalid saves keep the form open
with error messages.

`composer serve` and `composer build` reset the workbench database. To restart
the server while keeping existing demo records, use:

```bash
composer exec -- testbench serve
```

After changing frontend code, rebuild only the assets with
`composer build:assets`. For live frontend updates, keep `composer serve`
running and start `npm run dev` in a second terminal.

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
