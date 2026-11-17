<div align="center">
    <h1>Human Examinations</h1>
</div>

<p align="center">
    <a href="composer.json"><img src="https://img.shields.io/badge/PHP-%5E8.3-777BB4?style=flat-square" alt="PHP ^8.3"></a>
    <a href="composer.json"><img src="https://img.shields.io/badge/Laravel-12%20%7C%2013-FF2D20?style=flat-square" alt="Laravel 12 | 13"></a>
    <a href="https://github.com/SachinPawaskarUNO/human-examinations/actions/workflows/tests.yml?query=branch%3Amain"><img src="https://img.shields.io/github/actions/workflow/status/SachinPawaskarUNO/human-examinations/tests.yml?branch=main&amp;label=Tests&amp;style=flat-square" alt="Tests (main)"></a>
    <a href="LICENSE.md"><img src="https://img.shields.io/badge/License-MIT-blue?style=flat-square" alt="License: MIT"></a>
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

#### Region Suggestions

The Region field offers standard choices in a full-width dropdown styled to
match Type, and accepts custom text. Typing filters the choices; use the arrow
keys and Enter to select, Escape to close, or Tab to keep the current text and
continue. Clicking a recognized area of a sketch suggests a region such as
**Right upper arm**.
Suggestions follow an unsaved marker, including keyboard adjustments, until
you edit the field. Typing a custom value, selecting a standard choice, or
clearing the field preserves that choice through subsequent marker adjustments
and failed saves. Existing saved regions, including blank values, are preserved.

All 15 views cover their filled anatomical outlines. Specific feature zones take
priority, with broader anatomical regions filling the spaces between them.
The SVG surface bounds exclude background and gaps between limbs or digits.
Head and foot zones include ear rims, top-view ears, and toe edges. Nostrils
suggest **Nose**. Nipple zones follow the front SVG circles with 50% larger radii.
Full-body front/back views also distinguish left/right ears, while the remaining
head area retains the broader **Head** suggestion.
Review or refine the text as needed. The body front and back mappings use the
subject's anatomical left and right; detailed head, hand, and foot sketches
have their own mappings.

The catalogue includes 99 standard regions, including groin, left/right inner
thighs, nipples, collarbones, armpits, upper chest, and left/right buttocks;
shoulder blades, flanks, hips, elbows, wrists, ankles, palms, soles, and heels;
each finger and toe on both sides; upper/lower abdomen; and facial features
such as eyes, ears, cheeks, nose, mouth, forehead, and chin. Finger and toe
suggestions use the detailed hand and foot views. Broader labels such as
**Face** and **Left hand (palmar)** remain available. The general `Buttocks` enum
case/choice is replaced by `Groin`; left/right buttocks remain separate choices.
Existing saved region text, including `Buttocks`, is preserved when edited.

`BodyRegion` provides standard string values for PHP consumers:

```php
use HumanExaminations\HumanExaminations\Enums\BodyRegion;

$observation->update(['region' => BodyRegion::RightUpperArm->value]);
$observation->update(['region' => BodyRegion::LeftButtock->value]);
$observation->update(['region' => BodyRegion::Groin->value]);
$observation->update(['region' => BodyRegion::RightIndexFinger->value]);
$observation->update(['region' => 'Posterior right upper arm, near elbow']);
```

`region` remains a nullable string, with no enum cast or migration. The Vue
workspace includes the choices by default. For custom interfaces,
`resources/js/observationRegions.js` exports the `bodyRegions` catalogue and
`suggestRegion({ sketch_id, x, y })`, which returns a standard label or `null`.
The JSON catalogue in `resources/body-regions.json` is checked against the PHP
enum. Suggestions are a frontend convenience; the models and HTTP routes
preserve the region string supplied by the caller.

#### Anatomical Views

The workspace's default area includes **Full body**, **Head**, **Hands**, and
**Feet** tabs with responsive SVG diagrams. Body front and back start together,
side by side when there is room and stacked in narrower areas. The catalogue offers
15 views:

| Group | Views |
| --- | --- |
| Full body | Front and back |
| Head | Front, back, left profile, right profile, and top |
| Hands | Left/right palmar and dorsal |
| Feet | Left/right dorsal and plantar |

The diagrams preserve their original aspect ratios. The paired body drawings
share the same display height and label the subject's anatomical sides. The
selected tab shows all sketches in that category in two columns when there is
room, stacking in narrow panels. Dots mark categories containing mapped
observations. Arrow Left/Right, Home, and End navigate and select tabs; Tab
continues to the diagram panel. Selecting a mapped observation opens its
category. A host's existing default workspace slot still replaces this area.

For a standalone diagram, import
`resources/js/components/AnatomicalViews.vue` from the installed package into
your Vite build. Its optional `v-model:view` accepts a stable view ID:

```vue
<AnatomicalViews v-model:view="selectedView" />
```

An omitted or unknown ID displays the paired body diagrams. Both `body-front`
and `body-back` select this paired display; each diagram retains its own sketch
ID. Every stable view ID opens its category and displays the other views in that
category too. Choosing a tab emits its first view ID through `v-model:view`
(`body-front`, `head-front`, `hand-left-palmar`, or `foot-left-dorsal`).
The catalogue in
`resources/js/sketches.js` exports `sketchGroups` and the flat `sketches` list.
Each view has `id`, `label`, `width`, `height`, and `src`; body views also have
`sides`, ordered from the image's left to right. Store `id` when referring to a
view. Vite builds the source SVGs into local assets and manages their URLs; the
application does not fetch diagrams from the reference repository at runtime.

#### Zoom, Pan, and Diagram Height

Use the **− / +** buttons or scroll over a sketch to zoom from **100% to 500%**.
Each sketch has a small overlay with magnifying-glass zoom buttons, its current
percentage, and a reset button. Scrolling zooms around the pointer in that sketch.
Zoom and pan are independent for each view, so enlarging the front leaves the
back unchanged. Drag with the left or right mouse button to pan. Dragging does
not place or select a marker. **Reset view** restores only that sketch to 100%
and centers it. Changing the anatomical category resets zoom and pan.

The shared **Height** slider resizes all diagram viewports from **280 to 900 px**.
Drawings retain their aspect ratios, paired body views keep the same height,
and marker badges stay the same size. Browser and panel resizing refit the
drawings and constrain panning to keep them reachable.

Focus a viewport to use **+ / −** for zoom, **arrow keys** for pan, and **0** or
**Home** to reset. The draft marker's arrow keys continue to adjust its anatomical
location. Selecting or focusing an off-screen marker brings it into view.
Placement uses the transformed image bounds, so saved coordinates remain
normalized and accurate at any zoom, pan, or viewport size.

#### Density View

Switch **Pins / Density** above the diagrams to inspect mapped observation
concentrations. Density replaces numbered pins with a smooth blue-to-red heatmap;
overlapping locations appear warmer. All visible sketches use the same relative
scale, so front/back concentrations can be compared. The scale is recalculated
for the selected category and its observations; it is not an absolute count scale.

Only complete mapped observations contribute. Unmapped findings and the unsaved
marker preview are excluded. Heat is clipped to the anatomical outline, and the
legend reports the mapped count or an empty state. Density represents location
concentration, not observation type or severity.

Zoom, pan, and the shared Height slider work in either mode. Switching modes
preserves framing and draft form values. Density does not place or select markers;
switch to Pins to edit the map. Selecting an observation or starting placement
from the workspace sidebar returns to Pins automatically.

Standalone hosts may use optional `v-model:mode` with `pins` (the default) or
`density`. Mode changes emit `update:mode` and require no additional routes or
database fields.

#### Observation Markers

Saved observations appear as numbered markers on their own sketches. Click a
marker or its list entry to select the observation and open its form; selecting
a mapped list entry also opens the corresponding view.

Markers and list numbers share a muted color for each observation type. The
palette separates tattoos in deep blue, stab wounds in forest green, and
unspecified findings in neutral gray. Gunshot wounds use deep wine, injuries
use copper, bruises use lavender, and abrasions use ochre.
The **Observation types** legend below the sketches
labels the types used in the examination, including observations on other views
or without locations and the current marker preview. Custom type names get
stable generated colors; missing types use `Unspecified`. Numbers and accessible
type labels remain available alongside color.

With a saved examination, click or tap inside an anatomical outline to start an
observation at that location. The bundled Vue component checks the SVG's filled
anatomy, so surrounding space, gaps between limbs, and spaces between fingers
cannot receive a new marker. An invalid click keeps the current draft unchanged
and displays a warning toast at the bottom-right of the screen. Dismiss it with
its close button; it also disappears after five seconds.
Placement waits for the outline to load; if loading fails, the view shows a retry
message. Observations can still be recorded without markers using **+**.
For an existing observation without a marker, use **Place marker on a
sketch** in the list, then choose a location. Unsaved markers have a dashed
outline and remain previews until you save the observation. A failed save keeps
both the form values and preview; Cancel discards the unsaved location. Editing
an already mapped observation preserves its location.

For keyboard placement, focus a sketch and press Enter or Space to place a
preview at its center when that point is on the anatomy, or at an interior
starting point otherwise. The draft marker receives focus. Arrow keys adjust it
by 0.5% of the image; Shift reduces the step to 0.1%. Adjustments that leave the
anatomical outline are rejected. Coordinates are measured against the image
bounds, clamped to the image, and saved with six decimal
places, so markers remain aligned when the diagrams resize or stack.

Standalone `AnatomicalViews.vue` accepts `observations`, `selectedNumber`,
`draftLocation`, `placementEnabled` (default false), and `disabled` props. It
emits `place(location)` with `{ sketch_id, x, y }` and `select(number)`; the host
owns the form, selection, and persistence. `draftLocation` may include a
`number` for an existing unmarked observation and `type` to color the preview
and include its type in the legend. Persisted markers remain
selectable when placement is disabled, unless `disabled` is true. Incomplete or
invalid locations are not drawn, and observations on other sketches remain in
the list. Moving or clearing a saved marker remains follow-up work.

The outline check applies to placing and adjusting drafts in the bundled Vue
component. Models and write routes continue to validate the coordinate tuple
and numeric range, allowing consuming applications to manage their own sketches.

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
    @restore-observation="restoreObservation"
/>
```

`save-observation` emits `(draft, complete)`, where `draft.id` is null for a new
record and identifies the observation for an edit. Strip `id` from the HTTP
payload and send the editable fields, including `sketch_id`, `x`, and `y`, to
the generated store or update URL. Keep all three location fields together.
`delete-observation` and `restore-observation` emit `(observation, complete)`.
Restoration posts to the observation's restore URL with an empty payload.
All three handlers must refresh the `observations` prop before calling
`complete()` after a successful response, or call `complete({ field: 'Message' })`
after validation or transport failure. Pending writes disable observation
controls. Failed writes preserve the draft and display the returned errors.
Persistent mode requires an examination with an `id` before adding observations.
The workbench page demonstrates these handlers with Inertia, including an
`observation` error bag and cancellation/network failure handling.

Original SVG metadata and attribution are retained under `resources/sketches`;
see its [source notes](resources/sketches/README.md). The supplied artwork
identifies itself as extracted with permission from INTERPOL DVI Form 2023.

#### Undo and Redo

**Undo / Redo** reverses the last 50 saved observation additions, edits, removals,
and initial marker placements in the current examination. A new change clears
redo; saving an unchanged observation does not. Text inputs retain native undo.
Controls sit beside the anatomical category tabs, above the shared Height slider.
With focus outside editable fields, use **Ctrl/Cmd+Z** to undo,
**Ctrl/Cmd+Shift+Z** or **Ctrl+Y** to redo.
Shortcuts work across the page when one workspace is mounted; with multiple
workspaces, focus must be inside the intended workspace. Custom workspace slots
keep their history controls in the workspace toolbar. Standalone
`AnatomicalViews.vue` exposes an optional `actions` slot beside its category tabs.

Save or cancel an open observation form before using history. Controls are also
disabled during requests. Failed writes keep the history position and can be
retried. Switching examinations or reloading the page starts a fresh history;
examination details, navigation, and unsaved drafts are outside this history.

Local mode updates `v-model:observations`. Persistent mode uses the same save,
delete, and restore callbacks and records changes only after success and the
updated observation list. Undoing a removal restores the original soft-deleted
record with its ID, number, measurements, region, and location. Redoing an undone
creation also restores that record rather than allocating another number.
Hosts must wire `restore-observation` and supply restoration URLs for deleted
records; the workbench demonstrates this. A missing handler reports an unavailable
action without leaving the workspace waiting.

History compares editable fields before replaying a change. If the target differs
from its expected state, history is cleared rather than replacing that state.
This is session-local history, not a multi-user revision log or server-side
optimistic locking.

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
| POST | `/human-examinations/examinations/{examination}/observations/{observation}/restore` | `human-examinations.observations.restore` |

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
`DeleteObservation::handle($observation)`, and
`RestoreObservation::handle($observation)` after its own authorization. These
classes live under `HumanExaminations\HumanExaminations\Actions`. The create
action allocates a number; updates preserve the ID, number, and examination.
To customize them, extend the corresponding action and configure
`actions.create_observation`, `actions.update_observation`, or
`actions.delete_observation`, or `actions.restore_observation`. The restore route
requires `update` authorization on an active parent and resolves only that
parent's soft-deleted observations. Restore ignores replacement field payloads;
active, missing, and other-parent records return 404. Keep the parent scope and numbering guarantees in
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
with error messages. Click either body sketch to add a marker and finding, or
use the list's placement action for an existing unmarked observation.

`composer serve` and `composer build` reset the workbench database. To restart
the server while keeping existing demo records, use:

```bash
composer exec -- testbench serve
```

After changing frontend code, rebuild only the assets with
`composer build:assets`. For live frontend updates, keep `composer serve`
running and start `npm run dev` in a second terminal.

## Changelog

Please see [CHANGELOG][changelog] for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Human Examinations! Please review our [contributing guide][contributing-guide]
to get started.

## Security Vulnerabilities

Please review [our security policy][our-security-policy] on how to report security vulnerabilities.

## Credits

- [Sachin Pawaskar][sachin-pawaskar]
- [All Contributors][all-contributors]

## License

Human Examinations is open-sourced software licensed under the [MIT license][mit-license].

[changelog]: CHANGELOG.md
[sachin-pawaskar]: https://github.com/SachinPawaskarUNO

[all-contributors]: ../../contributors

[our-security-policy]: .github/SECURITY.md

[contributing-guide]: .github/CONTRIBUTING.md
[mit-license]: LICENSE.md
