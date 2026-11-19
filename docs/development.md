# Development and Workbench

[Documentation index](README.md) · [Package overview](../README.md)

## Local Demo

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
in the [integration guide](integration.md#optional-http-routes).

Create an examination, then use **+** in Observations to add findings. Edits and
removals are saved through the package routes; refresh the workspace to reload
its active observations from the database. Invalid observation saves keep the form open
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

To keep existing data and choose an available port, use native Composer:

```bash
composer exec -- testbench serve --port=18271
```

### Interactive Artisan prompts on Windows

Run Testbench directly with PHP when a command prompts for input. On Windows,
`composer exec` can close the child process's input stream, causing the prompt
to abort as soon as it opens. The server command above can still run through
Composer.

```powershell
php vendor/bin/testbench human-examinations:create
php vendor/bin/testbench human-examinations:show
```

## Validation Commands

Run commands from the package repository:

```bash
composer test         # PHPStan, formatting, type coverage, and PHP tests
composer test:unit    # Pest tests
composer lint:check   # Formatting check
composer analyse     # Static analysis
npm test             # Frontend tests
npm run build        # Workbench assets only
```

CI covers PHP 8.3–8.5, Laravel 12/13, dependency stability lanes, and Linux/Windows. Frontend tests and builds run on both operating systems.

## Maintaining Region Maps

Follow the [region maintenance notes](body-regions.md#maintaining-the-reference) when changing the catalog, selection zones, or SVG artwork.
