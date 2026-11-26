# Import and Export

[Documentation index](README.md) · [Package overview](../README.md)

## Workspace Controls

Use **Export → Export JSON** for a portable examination, or **Export CSV** for a spreadsheet. **Import JSON** opens a file chooser and a confirmation showing the examination reference and observation count. Save or cancel an open observation form before transferring files.

## PNG Sketches

Each sketch with saved markers has an **Export as PNG** button beside its zoom
controls. It downloads that sketch as a US Letter page at 300 DPI, with its
numbered markers, the examination details, a legend of the types shown, and the
artwork credit. PNGs are created in the browser, so they need no package route;
the button follows the same rules as JSON and CSV export. It is hidden in
Density mode and disabled while an observation form is open.

In the workbench, confirming an import creates a **new examination** and opens its workspace. Existing examinations remain unchanged. In the reusable workspace's default local mode, confirmation replaces its in-memory examination and observations instead; export first to keep a copy. A successful local import clears observation undo/redo history. Invalid files and failed requests leave the current examination intact and show errors. Failed imports can be retried from the confirmation.

## What is Included

JSON preserves examination reference, examiner, date, notes, and active observations: numbers (including gaps), types, labels, region text, descriptions, measurements, sketch identifiers, and normalized coordinates. Measurements use millimetres with two decimal places; coordinates use six decimal places. Manual region text is preserved without recalculating suggestions. When the `person_name` config is set, JSON and CSV also include `person_name`; see [person names](integration.md#person-names).

Database IDs, host associations, timestamps, deleted observations, undo history, zoom/pan settings, and uploaded media are excluded. Imports receive new database IDs. This is a data transfer format, rather than a database backup. Unknown custom sketch identifiers are preserved, but the receiving UI needs the matching artwork to display those markers.

CSV is export-only. It contains one row per active observation, with examination metadata repeated on each row. An examination without observations has one metadata row with blank observation columns. Files use UTF-8 with a BOM, quoted text where needed, and spreadsheet formula protection: potentially executable cell values receive a leading apostrophe. JSON retains the original text.

## JSON Format, Version #1

```json
{
  "format": "spawaskar/human-examinations",
  "version": 1,
  "examination": {
    "reference": "EX-001",
    "examiner": "Demo Examiner",
    "date": "2026-11-19",
    "notes": "Example examination."
  },
  "observations": [
    {
      "number": 7,
      "type": "Scar",
      "label": "Linear scar",
      "region": "Right upper arm",
      "description": "Healed linear scar.",
      "length_mm": "32.50",
      "width_mm": "4.25",
      "sketch_id": "body-front",
      "x": "0.310000",
      "y": "0.320000"
    }
  ]
}
```

The format identifier, integer version, examination object, and observations array are required. Examination metadata fields are optional and nullable. Each observation needs a unique positive integer `number` and a `type` from the receiving application's configured observation enum. Remaining observation fields are optional and nullable. Locations require `sketch_id`, `x`, and `y` together, or all three null/omitted. Coordinates must be between 0 and 1; measurements must be between 0 and 99,999,999.99. Dates use `YYYY-MM-DD`. Unknown fields and unsupported versions are rejected. Files may include a UTF-8 BOM.

The complete document is validated before inserting records, and the import runs inside a database transaction. A failed insert rolls back the new examination and its observations. Imported numbers are preserved; the next normal observation uses the next available number after the imported maximum.

Use `{}` for empty examination details and `[]` for an empty observation list.
Use `null` or omit an optional date, sketch identifier, coordinate, or
measurement. Blank strings are invalid for those fields.

## HTTP Integration

Enable the [optional package routes](integration.md#optional-http-routes), retaining the host's authentication middleware and examination policy:

| Method | Default URL | Route name | Permission |
| --- | --- | --- | --- |
| GET | `/human-examinations/examinations/{examination}/export?format=json` | `human-examinations.examinations.export` | `view` on the examination |
| GET | `/human-examinations/examinations/{examination}/export?format=csv` | `human-examinations.examinations.export` | `view` on the examination |
| POST | `/human-examinations/examinations/import` | `human-examinations.examinations.import` | `create` on `Examination::class` |

Generate URLs with the configured route names. Exports return downloadable files. Import accepts a multipart `file` upload and redirects to `workspace_route` with the new examination, or back when no workspace route is configured. Validation errors use `file` or `document.*` keys. Soft-deleted examinations cannot be exported through these routes.

Set `features.import` or `features.export` to `false` to omit the corresponding package route. Pass the same settings to the workspace to hide its controls. See [workspace features and marker colors](integration.md#workspace-features-and-marker-colors).

Defaults in `config/human-examinations.php` limit imports to 10 MiB and 10,000 observations:

```php
'transfer' => [
    'max_file_kilobytes' => 10240,
    'max_observations' => 10000,
],
```

PHP's `upload_max_filesize` and `post_max_size`, and your web server's upload limit, may impose a smaller ceiling. Supply matching UI limits with `:transfer-limits="{maxFileBytes: 10485760, maxObservations: 10000}"`; the workbench derives these from configuration.

## Actions

Host code may call these actions after authorization, without enabling HTTP routes:

```php
use HumanExaminations\HumanExaminations\Actions\ExportExamination;
use HumanExaminations\HumanExaminations\Actions\ImportExamination;

$document = app(ExportExamination::class)->handle($examination);
$imported = app(ImportExamination::class)->handle($document);
```

Override `actions.export_examination` or `actions.import_examination` with an action subclass. The default importer calls the configured `CreateExamination` action, so host ownership defaults can be attached there. It creates observations through the new parent's relationship, preserving their supplied numbers rather than using `CreateObservation` to allocate new ones. Preserve validation, transaction boundaries, and parent scoping in custom import implementations. The file-size limit applies to HTTP uploads; the observation-count limit also applies to direct action imports.

## Persistent Vue Workspaces

In `persistent-observations` mode, transfer controls appear only when the matching host callbacks are supplied. Export also requires a saved examination ID:

```vue
<HumanExaminationWorkspace
    :examination="examination"
    :observations="observations"
    :types="observationTypes"
    persistent-observations
    @import-examination="importExamination"
    @export-examination="exportExamination"
/>
```

`import-examination` emits `(file, complete)`. Upload the `File` as multipart data, then navigate to the imported examination and refresh the workspace props before calling `complete()`. `export-examination` emits `(format, complete)`, where `format` is `json` or `csv`. Download from the authorized export URL using a regular browser request or `fetch`, rather than an Inertia page visit. The package's `resources/js/examinationTransfer.js` exports `downloadExaminationResponse(url, format, filename)` for this purpose.

Both callbacks must call `complete()` on success or `complete({field: 'Message'})` on validation, network, cancellation, or other failure. Pending transfers disable observation writes and history controls. The confirmation and chosen file remain available after failure. See the [workbench page](../workbench/resources/js/Pages/Workspace.vue) and [routes](../workbench/routes/web.php) for a working Inertia example, including its `examinationImport` error bag.
