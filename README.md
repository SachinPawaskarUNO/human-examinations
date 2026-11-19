<div align="center">
    <h1>Human Examinations</h1>
</div>

<p align="center">
    <a href="composer.json"><img src="https://img.shields.io/badge/PHP-%5E8.3-777BB4?style=flat-square" alt="PHP ^8.3"></a>
    <a href="composer.json"><img src="https://img.shields.io/badge/Laravel-12%20%7C%2013-FF2D20?style=flat-square" alt="Laravel 12 | 13"></a>
    <a href="https://github.com/SachinPawaskarUNO/human-examinations/actions/workflows/tests.yml?query=branch%3Amain"><img src="https://img.shields.io/github/actions/workflow/status/SachinPawaskarUNO/human-examinations/tests.yml?branch=main&amp;label=Tests&amp;style=flat-square" alt="Tests (main)"></a>
    <a href="LICENSE.md"><img src="https://img.shields.io/badge/License-MIT-blue?style=flat-square" alt="License: MIT"></a>
</p>

A Laravel package for recording human examinations and mapping observations on anatomical sketches, with an interactive Vue workspace.

## Features

- Examination and observation storage with configurable actions, optional authorized write routes, and soft deletion/restoration.
- A Vue workspace with 15 anatomical sketches, numbered markers, and 99 editable region choices.
- Independent zoom/pan, shared viewport height, density visualization, and observation undo/redo.

## Installation

Requires PHP 8.3+ and Laravel 12 or 13. Install the package and its tables:

```bash
composer require spawaskar/human-examinations
php artisan vendor:publish --tag="human-examinations-migrations"
php artisan migrate
```

See [installation](docs/installation.md) for configuration, views, translations, and asset publish tags. The optional Vue workspace uses Vue 3.5+ and the host’s Vite build.

## Quick start

```php
use HumanExaminations\HumanExaminations\Actions\CreateObservation;
use HumanExaminations\HumanExaminations\Enums\BodyRegion;
use HumanExaminations\HumanExaminations\Models\Examination;

$examination = Examination::create([
    'reference' => 'EX-001',
    'examiner' => 'Demo Examiner',
    'date' => '2026-11-18',
]);

$observation = app(CreateObservation::class)->handle($examination, [
    'type' => 'Scar',
    'label' => 'Linear scar',
    'region' => BodyRegion::RightUpperArm->value,
    'length_mm' => '32.50',
    'width_mm' => '4.25',
]);
```

The create action allocates the next per-examination observation number. Hosts own authorization and links to their existing records. See the [models/API reference](docs/api.md) and [integration guide](docs/integration.md) before enabling HTTP writes.

## Documentation

- [Documentation index](docs/README.md)
- [Workspace guide](docs/workspace.md)
- [Body regions and illustrated selection maps](docs/body-regions.md)
- [Application integration and persistence](docs/integration.md)
- [Models, observation types, and locations](docs/api.md)
- [Development and local workbench](docs/development.md)

## Local demo

From the package repository, install dependencies and start the native PHP/Composer workbench:

```bash
composer install
npm ci
composer serve
```

`composer serve` and `composer build` recreate the demo database. To preserve existing records, restart with `composer exec -- testbench serve`; use `--port=18271` if another local app occupies the default port. Frontend-only rebuilds use `composer build:assets`.

See the [development guide](docs/development.md) for the complete setup, validation commands, and region-map maintenance.

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

[all-contributors]: https://github.com/SachinPawaskarUNO/human-examinations/graphs/contributors

[our-security-policy]: .github/SECURITY.md

[contributing-guide]: .github/CONTRIBUTING.md
[mit-license]: LICENSE.md
