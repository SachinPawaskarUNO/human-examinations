# Installation

[Documentation index](README.md) · [Package overview](../README.md)

## Requirements

- PHP 8.3+ and Laravel 12 or 13.
- Vue 3.5+ and the host application's Vite build when using the optional workspace.

## Install the Package
You can install the package via Composer:

```bash
composer require spawaskar/human-examinations
php artisan human-examinations:install
```

The installer publishes the package config and migrations and preserves existing
files. In an interactive terminal it asks whether to run pending application
migrations. For scripts, use `--no-interaction` to publish only, or add
`--migrate` to also run Laravel's normal `migrate` command. That command runs
all pending application migrations and follows Laravel's production safeguards.

After migrating, the installer also offers to add [sample data](#sample-data).
For scripts, add `--seed` alongside `--migrate`. Sample data is never added in
production.

You may publish all of the package's resources at once:

```bash
php artisan vendor:publish --tag="human-examinations"
```

Or, you may publish each resource individually:

## Publishing the Configuration File

```bash
php artisan vendor:publish --tag="human-examinations-config"
```

## Publishing and Running the Migrations

```bash
php artisan vendor:publish --tag="human-examinations-migrations"
php artisan migrate
```

## Publishing the Views

```bash
php artisan vendor:publish --tag="human-examinations-views"
```

## Publishing the Translations

```bash
php artisan vendor:publish --tag="human-examinations-lang"
```

## Publishing the Public Assets

```bash
php artisan vendor:publish --tag="human-examinations-assets"
```

## Frontend Setup

The optional workspace uses Vue 3.5+ and the host application’s Vite build. Import its source component from the installed Composer package; see the [workspace guide](workspace.md#getting-started). Models and actions can be used without the Vue workspace.

Continue with [application integration](integration.md) to configure policies, routes, and persistence.

## Artisan Commands

Run these commands from the Laravel application's root after creating the
package tables:

| Command | Behavior |
| --- | --- |
| `php artisan human-examinations:create` | Prompts for optional reference, examiner, date, and notes, then asks before saving. |
| `php artisan human-examinations:list --limit=20` | Lists recent active examinations and their active observation counts. The limit accepts 1–100. |
| `php artisan human-examinations:show 1` | Shows examination details and active observations in number order. Omit the ID in an interactive terminal to be prompted for it. |

Use options to create an examination from a script:

```bash
php artisan human-examinations:create --reference=EX-001 --examiner="Demo Examiner" --date=2026-11-19 --no-interaction
```

Every creation field is optional. Supply dates in `YYYY-MM-DD` format. The
create command uses the configured `CreateExamination` action. The commands
run with the application's console and database access, so use them from a
trusted shell. HTTP route settings and examination policies apply to HTTP
requests.

## Sample Data

The package includes a seeder that adds three sample examinations: one with
markers on the front and back sketches, one with observations not yet placed,
and an empty draft. Run it, or call it from your app's `DatabaseSeeder`:

```bash
php artisan db:seed --class="HumanExaminations\HumanExaminations\Database\Seeders\HumanExaminationsSeeder"
```

```php
$this->call(\HumanExaminations\HumanExaminations\Database\Seeders\HumanExaminationsSeeder::class);
```

The seeder always skips production (`APP_ENV=production`), even with `--force`,
so it is safe to leave in your `DatabaseSeeder`. The samples use the references
`SAMPLE-001` to `SAMPLE-003`, or your `reference_format` when one is set, and
running it again does not duplicate them.
Observation types use your configured enum, falling back to `Unspecified` when
it lacks a sample type such as `Scar`. Person names are saved only when
`person_name` is `'typed'`.

The installer can run it for you after migrating:

```bash
php artisan human-examinations:install --migrate --seed --no-interaction
```
