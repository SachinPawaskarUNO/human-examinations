# Application integration

[Documentation index](README.md) · [Package overview](../README.md)

## Host Integration

The consuming application owns links to existing records, team ownership,
authorization, and user auditing. It can add a foreign key or association table
linking its records to `human_examinations`. The package does not copy, replace,
or automatically create counterparts for existing host examinations. Existing
examination data and observations require an explicit integration decision before
adoption; these migrations do not alter or migrate host tables.

## Optional HTTP Routes

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

## Observation persistence

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

In a repository checkout, the complete workbench examples are [the page component](../workbench/resources/js/Pages/Workspace.vue) and [its read routes](../workbench/routes/web.php). Workbench files are development examples and are excluded from Composer distribution archives. Adapt them to the host's authorization, route names, and page structure. Keep the workbench's permissive guest policy confined to the local demo.

## Examination metadata

The workspace emits `new-examination(details)` and updates its `examination` model when examination details change. The host owns creation and metadata persistence; these events do not use the observation `complete()` callback contract. The current workbench has known metadata-save limitations described in [development notes](development.md#known-limitations).
