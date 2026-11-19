# Installation

[Documentation index](README.md) · [Package overview](../README.md)

## Requirements

- PHP 8.3+ and Laravel 12 or 13.
- Vue 3.5+ and the host application's Vite build when using the optional workspace.

## Install the package
You can install the package via Composer:

```bash
composer require spawaskar/human-examinations
```

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

## Frontend setup

The optional workspace uses Vue 3.5+ and the host application’s Vite build. Import its source component from the installed Composer package; see the [workspace guide](workspace.md#getting-started). Models and actions can be used without the Vue workspace.

Continue with [application integration](integration.md) to configure policies, routes, and persistence.
