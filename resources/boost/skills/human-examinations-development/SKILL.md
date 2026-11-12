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
- Create observations through `$examination->observations()->create([...])`; `Observation::examination()` is the inverse relationship. Observations live in `human_examination_observations`.
- Examination fields are nullable `reference`, `examiner`, `date`, and `notes`. The date serializes as `Y-m-d`.
- Supply a positive observation `number`, unique within the examination even across soft-deleted observations. Other fields are `type` (default `Unspecified`), `label`, `region`, `description`, `length_mm`, and `width_mm`.
- Measurements are nullable nonnegative decimals in millimetres with two decimal places.
- Store a complete location using `sketch_id`, `x`, and `y`, or leave all three null. Use stable sketch identifiers and coordinates normalized to 0–1 from the top-left, stored with six decimal places. Anatomical left/right refer to the subject.
- Model saves throw `DomainException` for invalid numbers, measurements, or locations. Bulk/query builder writes bypass this validation. Foreign keys and observation-number uniqueness are database constraints.
- Both models use integer IDs, timestamps, and soft deletion. Soft-deleting an examination preserves its observations; query them through an active authorized examination. Force deletion cascades to every observation.
- Keep host record links, teams, authorization, and user auditing in the consuming app. Do not automatically duplicate existing host examinations or observations.

## Rules, References, and Templates

Read before executing:

- `README.md` for installation, publishing, model usage, and integration behavior
- `src/Models/Examination.php` and `src/Models/Observation.php` for model APIs
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
