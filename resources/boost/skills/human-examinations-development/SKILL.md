---
name: human-examinations-development
description: >
  Configure and apply the Human Examinations package in Laravel applications.
license: MIT
metadata:
  author: Sachin Pawaskar
---

# Human Examinations

Use this skill when a Laravel application needs to integrate the Human Examinations package.

## Primary Goal

- apply the `spawaskar/human-examinations` package's public API in the smallest correct way

## Workflow

### 1. Inspect the Laravel app context

- confirm the app is a Laravel project
- inspect the target code paths where the package should be applied

### 2. Apply the package's public API

- Install `spawaskar/human-examinations`, publish `human-examinations-migrations`, and run `php artisan migrate`.
- Use `HumanExaminations\HumanExaminations\Models\Examination` for standalone examination records in `human_examinations`.
- Optional HTTP write routes are disabled by default. Publish `human-examinations-config`, enable `routes.enabled`, and configure `routes.prefix`, `routes.name_prefix`, and `routes.middleware`. Defaults are `human-examinations`, `human-examinations.`, and `['web', 'auth']`; middleware overrides replace the default list, while route model binding is always retained, even with an empty list.
- Register a host policy with `Gate::policy(Examination::class, App\Policies\ExaminationPolicy::class)`. Implement `create` and `update` using the host application's permissions and associations. The controllers always authorize before validation or action execution, even if custom middleware omits `auth`; authenticated users are not automatically authorized.
- Enabled routes accept POST `/{prefix}/examinations` and PATCH `/{prefix}/examinations/{examination}`, named `{name_prefix}examinations.store` and `{name_prefix}examinations.update`. URL and name prefixes can be empty; a nonempty name prefix normally ends in a dot. Generate URLs by the configured route names and pass them to the frontend.
- Observation writes use POST `/{prefix}/examinations/{examination}/observations` and PATCH/DELETE `/{prefix}/examinations/{examination}/observations/{observation}`, named `{name_prefix}observations.store`, `.update`, and `.destroy`. They require the parent Examination's `update` permission before validation. Scoped binding rejects children from another examination and soft-deleted parents/children. All writes redirect back; deletion soft-deletes.
- HTTP observation payloads may contain `type`, `label`, `region`, `description`, `length_mm`, `width_mm`, `sketch_id`, `x`, and `y`. Do not send `id`, `number`, or `examination_id`. Creates allocate the next per-examination number, including deleted records. PATCH preserves omitted fields and validates location completeness after merging stored values.
- `CreateObservation::handle($examination, $attributes)`, `UpdateObservation::handle($observation, $attributes)`, and `DeleteObservation::handle($observation)` are available under the Actions namespace after host authorization. Override with `actions.create_observation`, `actions.update_observation`, and `actions.delete_observation`; preserve parent scoping and numbering guarantees.
- Authorize the host's workspace read route before loading `$examination->observations()->orderBy('number')->get()`. There is no package observation listing endpoint. Hosts using Inertia must include its middleware to share redirect validation errors.
- Set `workspace_route` to a host route name accepting the examination to redirect there after creation; otherwise creation redirects back. Updates redirect back. These controllers return redirects, not JSON records. Rebuild an existing route cache after changing route settings.
- Hosts providing their own HTTP controllers can leave package routes disabled and call `CreateExamination::handle()` or `UpdateExamination::handle()` after their own authorization. The existing `actions.create_examination` and `actions.update_examination` config entries accept subclasses overriding `handle()`.
- Create observations through `$examination->observations()->create([...])`; `Observation::examination()` is the inverse relationship. Observations live in `human_examination_observations`.
- Examination fields are nullable `reference`, `examiner`, `date`, and `notes`. The date serializes as `Y-m-d`.
- Direct relationship creates require a positive observation `number`, unique within the examination even across soft-deleted observations. The create action and HTTP route allocate it automatically. Other fields are `type` (default `Unspecified`), `label`, `region`, `description`, `length_mm`, and `width_mm`.
- Observation types are enum cases from `Observation::typeEnum()`. Configure `observation_type` with a string-backed enum including `Unspecified`; assign a case or its string value. Existing stored values must remain valid in the configured enum.
- Measurements are nullable nonnegative decimals in millimetres with two decimal places.
- Import `resources/js/components/HumanExaminationWorkspace.vue` into the app's Vite build for the Vue workspace. Its default area displays body front/back together, side by side when there is room and stacked in narrow areas. Other head/hand/foot views remain individual choices; a default slot replaces the area. Standalone `AnatomicalViews.vue` supports optional `v-model:view`: `body-front`, `body-back`, and omitted or unknown IDs show the pair. Choosing the body option emits `body-front`; the diagrams retain separate stable sketch IDs.
- `resources/js/sketches.js` exports grouped `sketchGroups` and flat `sketches` entries (`id`, `label`, `width`, `height`, `src`, plus body-view `sides` ordered image-left to image-right). Vite bundles the source SVGs; store stable IDs instead of generated asset URLs. Keep SVG attribution and view boxes intact.
- Diagram selection only changes the display; pin placement is not implemented yet. The workspace uses local `v-model:observations` by default. Enable `persistent-observations` with a saved examination and wire `save-observation(draft, complete)` and `delete-observation(observation, complete)`. Strip `draft.id` before HTTP writes, refresh the observations prop after success, and call `complete()` or `complete({ field: 'Message' })`. Pending writes disable controls; failed writes keep the draft and show errors.
- Store a complete location using `sketch_id`, `x`, and `y`, or leave all three null. Use stable sketch identifiers and coordinates normalized to 0–1 from the top-left, stored with six decimal places. Anatomical left/right refer to the subject.
- Model saves throw `DomainException` for invalid numbers, measurements, or locations. Bulk/query builder writes bypass this validation. Foreign keys and observation-number uniqueness are database constraints.
- Both models use integer IDs, timestamps, and soft deletion. Soft-deleting an examination preserves its observations; query them through an active authorized examination. Force deletion cascades to every observation.
- Keep host record links, teams, authorization, and user auditing in the consuming app. Do not automatically duplicate existing host examinations or observations.

## Rules, References, and Templates

Read before executing:

- `README.md` for installation, publishing, model usage, and integration behavior
- `src/Models/Examination.php` and `src/Models/Observation.php` for model APIs
- `config/human-examinations.php`, `routes/human-examinations.php`, and `src/Http/Controllers/ExaminationController.php` for optional route configuration and authorization
- `src/Http/Controllers/ObservationController.php` for parent-scoped observation HTTP behavior and validation
- `src/Actions/` for examination actions
- `resources/js/components/AnatomicalViews.vue`, `resources/js/sketches.js`, and `resources/sketches/README.md` for SVG views, catalogue IDs, and artwork attribution
- `database/migrations/` for table definitions

## Examples

```php
use HumanExaminations\HumanExaminations\Models\Examination;

$examination = Examination::create(['reference' => 'EX-001', 'date' => '2026-11-12']);
$observation = $examination->observations()->create([
    'number' => 1,
    'type' => 'Scar',
    'region' => 'Right upper arm',
    'length_mm' => '32.50',
]);
```

In application tests, assert the observation belongs to the examination and that an unmapped observation has null `sketch_id`, `x`, and `y`.

## Anti-patterns

- do not document package internals here; keep the skill focused on adoption in Laravel apps
- do not require host-specific person, team, user, or examination models
- do not store build-dependent asset paths as sketch identifiers or treat anatomical left as viewer left
- do not rely on model validation when using bulk database writes
- do not enable HTTP write routes without host authorization or hardcode frontend URLs when route prefixes are configurable
